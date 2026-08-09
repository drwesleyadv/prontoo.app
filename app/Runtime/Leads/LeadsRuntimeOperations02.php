<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Leads;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;
use Prontoo\Domain\Leads\LeadsDomainOperations01;

final class LeadsRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function page_leads(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("leads");
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::ensure_lead_events_schema();
        $stageOptions = LeadsDomainOperations01::lead_stage_options();
        $editableStageOptions = [
            "em_aberto" => "Em Aberto",
            "aguarda_retorno" => "Aguarda retorno",
            "arquivado" => "Arquivado",
        ];
        $stageLabels = $stageOptions;
        $stageClass = function (string $stage): string {
    
            $stage = LeadsDomainOperations01::lead_stage_normalize($stage);
            return preg_replace("/[^a-z0-9_-]+/i", "-", $stage) ?: "em_aberto";
        };
        $leadTime = function (?string $v) use ($cid, $c): string {
    
            $v = mb_trim((string) $v);
            if ($v === "") {
                return "";
            }
            return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_to_db_utc($v, $cid, $c);
        };
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "save");
            if ($act === "convert") {
                $leadId = (int) ($_POST["lead_id"] ?? 0);
                $lead = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,person_id,name,phone,notes,stage FROM pi_leads WHERE id=? AND clinic_id=?",
                    [$leadId, $cid],
                );
                if (!$lead) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Interessado não encontrado.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                }
                try {
                    $postedCpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_POST["cpf"] ?? ""));
                    $existingPatient =
                        $postedCpf !== ""
                            ? LeadsRuntimeOperations01::lead_patient_by_cpf($cid, $postedCpf)
                            : null;
                    $leadPersonId = (int) ($lead["person_id"] ?? 0);
                    if (
                        $existingPatient &&
                        (int) ($existingPatient["person_id"] ?? 0) !== $leadPersonId
                    ) {
                        $oldStage = LeadsDomainOperations01::lead_stage_normalize(
                            (string) ($lead["stage"] ?? "em_aberto"),
                        );
                        \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                            "UPDATE pi_leads SET stage='arquivado', updated_at=NOW() WHERE id=? AND clinic_id=?",
                            [$leadId, $cid],
                        );
                        LeadsRuntimeOperations01::lead_event_create(
                            $cid,
                            $leadId,
                            $uid,
                            $oldStage,
                            "arquivado",
                            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($lead["phone"] ?? "")),
                            "",
                            "",
                            null,
                            "Interessado arquivado automaticamente porque o CPF informado já pertencia a paciente ativo.",
                            "arquivamento",
                        );
                        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("lead_arquivado", "lead", $leadId, [
                            "audit_body" =>
                                "Interessado arquivado automaticamente ao tentar tornar paciente com CPF já cadastrado como paciente.",
                            "patient_id" =>
                                (int) ($existingPatient["patient_id"] ?? 0),
                        ]);
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "Este interessado já era paciente. Seu Interesse foi Arquivado. Abra a Ficha do Paciente para Continuar.",
                            "warn",
                        );
                        $existingPatientId =
                            (int) ($existingPatient["patient_id"] ?? 0);
                        if ($existingPatientId > 0) {
                            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $existingPatientId]);
                        }
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads", ["status" => "arquivado"]);
                    }
                    $pid = LeadsRuntimeOperations01::lead_prepare_person_for_patient($lead, $_POST, $cid);
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "INSERT INTO pi_patients (clinic_id,person_id,phone,notes,created_by,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE phone=COALESCE(NULLIF(VALUES(phone),''),phone), notes=COALESCE(NULLIF(VALUES(notes),''),notes), active=1, updated_at=NOW()",
                        [
                            $cid,
                            $pid,
                            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($lead["phone"] ?? "")),
                            mb_trim((string) ($lead["notes"] ?? "")),
                            $uid,
                        ],
                    );
                    $patientId = (int) \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
                        "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? LIMIT 1",
                        [$cid, $pid],
                    );
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_leads SET person_id=?, stage='convertido', updated_at=NOW() WHERE id=? AND clinic_id=?",
                        [$pid, $leadId, $cid],
                    );
                    LeadsRuntimeOperations01::lead_event_create(
                        $cid,
                        $leadId,
                        $uid,
                        LeadsDomainOperations01::lead_stage_normalize(
                            (string) ($lead["stage"] ?? "em_aberto"),
                        ),
                        "convertido",
                        \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($lead["phone"] ?? "")),
                        "",
                        "",
                        null,
                        "Interessado convertido em paciente.",
                        "conversao",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("patient_writes_total");
                    \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "patient_writes");
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("paciente_salvo", "paciente", $patientId ?: $pid, [
                        "origem" => "interessado",
                        "lead_id" => $leadId,
                        "audit_body" =>
                            "Interessado tornado paciente após conferência dos dados pendentes.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Interessado tornado paciente.");
                    if ($patientId > 0) {
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $patientId]);
                    }
                } catch (Throwable $e) {
                    error_log("[Prontoo convert interested] " . $e->getMessage());
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        $e->getMessage() ?:
                        "Não foi possível tornar o interessado paciente.",
                        "bad",
                    );
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
            }
            if ($act === "archive_lead") {
                $leadId = (int) ($_POST["lead_id"] ?? 0);
                $lead = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,name,phone,source,interest,stage,next_action_at FROM pi_leads WHERE id=? AND clinic_id=?",
                    [$leadId, $cid],
                );
                if (!$lead) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Interessado não encontrado.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                }
                $oldStage = LeadsDomainOperations01::lead_stage_normalize(
                    (string) ($lead["stage"] ?? "em_aberto"),
                );
                if ($oldStage === "convertido") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Interessado já convertido em paciente.", "warn");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads", ["status" => "convertido"]);
                }
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "UPDATE pi_leads SET stage='arquivado', updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [$leadId, $cid],
                );
                LeadsRuntimeOperations01::lead_event_create(
                    $cid,
                    $leadId,
                    $uid,
                    $oldStage,
                    "arquivado",
                    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($lead["phone"] ?? "")),
                    (string) ($lead["source"] ?? ""),
                    (string) ($lead["interest"] ?? ""),
                    !empty($lead["next_action_at"])
                        ? (string) $lead["next_action_at"]
                        : null,
                    "Interessado arquivado porque o CPF informado já pertence a um paciente ativo.",
                    "arquivamento",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("lead_arquivado", "lead", $leadId, [
                    "audit_body" =>
                        "Interessado arquivado após tentativa de tornar paciente com CPF já cadastrado como paciente.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Interessado arquivado.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads", ["status" => "arquivado"]);
            }
            if ($act === "update") {
                $leadId = (int) ($_POST["lead_id"] ?? 0);
                $lead = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes FROM pi_leads WHERE id=? AND clinic_id=?",
                    [$leadId, $cid],
                );
                if (!$lead) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Interessado não encontrado.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                }
                $stage = LeadsDomainOperations01::lead_stage_normalize(
                    (string) ($_POST["stage"] ?? "" ?: "em_aberto"),
                );
                if (!isset($editableStageOptions[$stage])) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Etapa do interessado inválida.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                }
                $postedPhone = LeadsRuntimeOperations01::lead_phone_digits((string) ($_POST["phone"] ?? ""));
                $currentPhone = LeadsRuntimeOperations01::lead_phone_digits(
                    (string) ($lead["phone_digits"] ?? ($lead["phone"] ?? "")),
                );
                if (
                    $postedPhone !== "" &&
                    $currentPhone !== "" &&
                    $postedPhone !== $currentPhone
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Telefone é dado imutável do interessado e não pode ser alterado.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                }
                $name = mb_trim((string) ($_POST["name"] ?? ($lead["name"] ?? "")));
                if ($name === "") {
                    $name = (string) ($lead["name"] ?? "Interessado");
                }
                $phone = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($lead["phone"] ?? ""));
                $source = mb_trim((string) ($_POST["source"] ?? ""));
                $interest = mb_trim((string) ($_POST["interest"] ?? ""));
                $next =
                    $leadTime((string) ($_POST["next_action_at"] ?? "")) ?: null;
                $notes = mb_trim((string) ($_POST["notes"] ?? ""));
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "UPDATE pi_leads SET name=?, source=?, interest=?, stage=?, next_action_at=?, notes=COALESCE(NULLIF(?,''),notes), phone_digits=COALESCE(NULLIF(phone_digits,''),?), updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [
                        $name,
                        $source,
                        $interest,
                        $stage,
                        $next,
                        $notes,
                        $currentPhone,
                        $leadId,
                        $cid,
                    ],
                );
                if (isset($_POST["contact_event"])) {
                    LeadsRuntimeOperations01::lead_event_create(
                        $cid,
                        $leadId,
                        $uid,
                        (string) ($lead["stage"] ?? ""),
                        $stage,
                        $phone,
                        $source,
                        $interest,
                        $next,
                        $notes,
                        "contato",
                    );
                }
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("lead_atualizado", "lead", $leadId, [
                    "stage" => $stage,
                    "audit_body" => isset($_POST["contact_event"])
                        ? "Contato registrado no histórico do interessado."
                        : "Interessado atualizado pela tela operacional de Interessados.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    $stage === "arquivado"
                        ? "Interessado arquivado."
                        : "Interessado atualizado.",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads", ["status" => $stage]);
            }
            if ($act === "save") {
                $phoneDigits = LeadsRuntimeOperations01::lead_phone_digits((string) ($_POST["phone"] ?? ""));
                if (strlen($phoneDigits) < 10) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Informe o telefone do interessado para iniciar ou continuar o histórico.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                }
                $name = mb_trim((string) ($_POST["name"] ?? ""));
                $cpfOmitted = isset($_POST["cpf_omitted"]);
                $birthOmitted = isset($_POST["birth_omitted"]);
                $cpf = $cpfOmitted
                    ? ""
                    : \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_POST["cpf"] ?? ""));
                $birth = $birthOmitted
                    ? ""
                    : mb_trim((string) ($_POST["birth_date"] ?? ""));
                if (!$cpfOmitted && $cpf !== "" && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Este CPF não existe.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                }
                if (!$birthOmitted && $birth !== "" && !\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($birth)) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Informe nascimento válido ou marque Não quis informar.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                }
                $stage = LeadsDomainOperations01::lead_stage_normalize(
                    (string) ($_POST["stage"] ?? "" ?: "em_aberto"),
                );
                if (!in_array($stage, ["em_aberto", "aguarda_retorno"], true)) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Etapa inicial do interessado inválida.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                }
                try {
                    $existing = LeadsRuntimeOperations01::lead_find_by_phone($cid, $phoneDigits);
                    $next =
                        $leadTime((string) ($_POST["next_action_at"] ?? "")) ?:
                        null;
                    $source = mb_trim((string) ($_POST["source"] ?? ""));
                    $interest = mb_trim((string) ($_POST["interest"] ?? ""));
                    $notes = mb_trim((string) ($_POST["notes"] ?? ""));
                    $patientByPhone = !$existing
                        ? LeadsRuntimeOperations01::lead_patient_by_phone($cid, $phoneDigits)
                        : null;
                    if ($patientByPhone) {
                        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                            "paciente_localizado_por_telefone",
                            "paciente",
                            (int) $patientByPhone["patient_id"],
                            [
                                "audit_body" =>
                                    "Recepção tentou registrar contato por telefone e o Prontoo localizou ficha de paciente já cadastrada.",
                            ],
                        );
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "Paciente já cadastrado localizado pelo telefone. Abra a ficha para registrar o atendimento.",
                            "warn",
                        );
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", [
                            "id" => (int) $patientByPhone["patient_id"],
                        ]);
                    }
                    if ($existing) {
                        $leadId = (int) $existing["id"];
                        $oldStage = LeadsDomainOperations01::lead_stage_normalize(
                            (string) ($existing["stage"] ?? "em_aberto"),
                        );
                        if ($name === "") {
                            $name = (string) ($existing["name"] ?? "Interessado");
                        }
                        $pid = (int) ($existing["person_id"] ?? 0);
                        if ($pid <= 0 || $cpf !== "" || $birth !== "") {
                            $pid = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::save_person_flexible(
                                $name,
                                $cpf !== "" ? $cpf : null,
                                $birth !== "" ? $birth : null,
                            );
                        }
                        $stageTo =
                            $oldStage === "convertido"
                                ? "convertido"
                                : LeadsDomainOperations01::lead_stage_normalize($stage);
                        \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                            "UPDATE pi_leads SET person_id=COALESCE(NULLIF(?,0),person_id), name=?, source=COALESCE(NULLIF(?,''),source), interest=COALESCE(NULLIF(?,''),interest), stage=?, next_action_at=COALESCE(?,next_action_at), notes=COALESCE(NULLIF(?,''),notes), phone_digits=COALESCE(NULLIF(phone_digits,''),?), updated_at=NOW() WHERE id=? AND clinic_id=?",
                            [
                                $pid,
                                $name,
                                $source,
                                $interest,
                                $stageTo,
                                $next,
                                $notes,
                                $phoneDigits,
                                $leadId,
                                $cid,
                            ],
                        );
                        LeadsRuntimeOperations01::lead_event_create(
                            $cid,
                            $leadId,
                            $uid,
                            $oldStage,
                            $stageTo,
                            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($phoneDigits),
                            $source,
                            $interest,
                            $next,
                            $notes,
                            "contato",
                        );
                        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("lead_atualizado", "lead", $leadId, [
                            "stage" => $stageTo,
                            "audit_body" =>
                                "Novo contato recebido pelo telefone imutável do interessado; ocorrência adicionada ao histórico.",
                        ]);
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "Telefone já cadastrado. O histórico do interessado foi atualizado.",
                        );
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads", ["status" => $stageTo]);
                    }
                    if ($name === "") {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o nome completo do interessado.", "bad");
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                    }
                    $pid = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::save_person_flexible(
                        $name,
                        $cpf !== "" ? $cpf : null,
                        $birth !== "" ? $birth : null,
                    );
                    if (
                        $cpf !== "" &&
                        (int) \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
                            "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND person_id=? AND " .
                                LeadsDomainOperations01::lead_active_stage_sql("stage"),
                            [$cid, $pid],
                        ) > 0
                    ) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "Já existe interessado ativo com o mesmo CPF nesta clínica. Atualize o registro existente em vez de duplicar.",
                            "bad",
                        );
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
                    }
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "INSERT INTO pi_leads (clinic_id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
                        [
                            $cid,
                            $pid,
                            $name,
                            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($phoneDigits),
                            $phoneDigits,
                            $source,
                            $interest,
                            $stage,
                            $next,
                            $notes,
                            $uid,
                        ],
                    );
                    $leadId = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_last_insert_id();
                    LeadsRuntimeOperations01::lead_event_create(
                        $cid,
                        $leadId,
                        $uid,
                        "",
                        $stage,
                        \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($phoneDigits),
                        $source,
                        $interest,
                        $next,
                        $notes,
                        "cadastro",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("leads_total");
                    \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "leads");
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("lead_criado", "lead", $leadId, $_POST);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Interessado salvo.");
                } catch (Throwable $e) {
                    error_log("[Prontoo interested save] " . $e->getMessage());
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Não foi possível salvar o interessado. Revise os dados informados.",
                        "bad",
                    );
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("leads");
            }
        }
        $statusRaw = (string) ($_GET["status"] ?? "em_aberto");
        $status = LeadsDomainOperations01::lead_stage_normalize($statusRaw);
        $qTerm = mb_trim((string) ($_GET["q"] ?? ""));
        $leadSearchMode = $qTerm !== "";
        $where = "clinic_id=?";
        $params = [$cid];
        if (!$leadSearchMode) {
            if (isset($stageOptions[$status])) {
                $where .= " AND " . LeadsDomainOperations01::lead_stage_sql_case("stage") . "=?";
                $params[] = $status;
            } else {
                $status = "em_aberto";
                $where .= " AND " . LeadsDomainOperations01::lead_stage_sql_case("stage") . "=?";
                $params[] = $status;
            }
        } else {
            if (!isset($stageOptions[$status])) {
                $status = "em_aberto";
            }
        }
        if ($leadSearchMode) {
            $like = "%" . $qTerm . "%";
            $digits = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($qTerm);
            $where .=
                " AND (name LIKE ? OR phone LIKE ? OR phone_digits LIKE ? OR source LIKE ? OR interest LIKE ?)";
            $params[] = $like;
            $params[] = $digits !== "" ? "%" . $digits . "%" : $like;
            $params[] = $digits !== "" ? "%" . $digits . "%" : $like;
            $params[] = $like;
            $params[] = $like;
        }
        $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "SELECT id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes,created_at,updated_at FROM pi_leads WHERE $where ORDER BY CASE WHEN stage='convertido' THEN 5 WHEN stage='arquivado' THEN 6 WHEN next_action_at IS NOT NULL AND next_action_at<NOW() THEN 0 WHEN next_action_at IS NOT NULL THEN 1 ELSE 2 END, COALESCE(next_action_at,created_at) ASC, id DESC LIMIT 140",
            $params,
        )->fetchAll();
        $allCounts = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "SELECT " .
                LeadsDomainOperations01::lead_stage_sql_case("stage") .
                " AS stage,COUNT(*) total FROM pi_leads WHERE clinic_id=? GROUP BY " .
                LeadsDomainOperations01::lead_stage_sql_case("stage"),
            [$cid],
        )->fetchAll();
        $counts = [];
        foreach ($allCounts as $cr) {
            $st = LeadsDomainOperations01::lead_stage_normalize((string) $cr["stage"]);
            $counts[$st] = ($counts[$st] ?? 0) + (int) $cr["total"];
        }
        $activeTotal =
            (int) (\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                    LeadsDomainOperations01::lead_active_stage_sql("stage"),
                [$cid],
            ) ?? 0);
        $newLeads =
            (int) (\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                    LeadsDomainOperations01::lead_active_stage_sql("stage") .
                    " AND created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$cid],
            ) ?? 0);
        [$leadTodayStart, $leadTodayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range(
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        $todayReturns =
            (int) (\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                    LeadsDomainOperations01::lead_active_stage_sql("stage") .
                    " AND next_action_at>=? AND next_action_at<?",
                [$cid, $leadTodayStart, $leadTodayEnd],
            ) ?? 0);
        $lateReturns =
            (int) (\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                    LeadsDomainOperations01::lead_active_stage_sql("stage") .
                    " AND next_action_at IS NOT NULL AND next_action_at<NOW()",
                [$cid],
            ) ?? 0);
        $persons = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "pi_persons",
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "person_id"),
            "id,full_name,cpf,birth_date",
        );
        $patientByPerson = [];
        $personIds = \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "person_id");
        if ($personIds) {
            $ph = implode(",", array_fill(0, count($personIds), "?"));
            $prs = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "SELECT id,person_id FROM pi_patients WHERE clinic_id=? AND person_id IN ($ph) AND active=1 LIMIT 160",
                array_merge([$cid], $personIds),
            )->fetchAll();
            foreach ($prs as $pr) {
                $patientByPerson[(int) $pr["person_id"]] = (int) $pr["id"];
            }
        }
        $leadEvents = [];
        $leadEventUsers = [];
        $leadIds = \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "id");
        if ($leadIds) {
            $ph = implode(",", array_fill(0, count($leadIds), "?"));
            $evs = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "SELECT id,lead_id,event_type,stage_from,stage_to,body,created_by,created_at FROM pi_lead_events WHERE clinic_id=? AND lead_id IN ($ph) ORDER BY created_at ASC,id ASC LIMIT 420",
                array_merge([$cid], $leadIds),
            )->fetchAll();
            $uids = [];
            foreach ($evs as $ev) {
                $lid = (int) ($ev["lead_id"] ?? 0);
                if ($lid <= 0) {
                    continue;
                }
                $leadEvents[$lid][] = $ev;
                $u = (int) ($ev["created_by"] ?? 0);
                if ($u > 0) {
                    $uids[$u] = $u;
                }
            }
            if ($uids) {
                $leadEventUsers = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
                    "pi_users",
                    array_values($uids),
                    "id,name",
                );
            }
        }
        $cpfField =
            '<div class="optional-field">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "cpf",
                    "text",
                    "",
                    'inputmode="numeric" maxlength="14" data-cpf-mask',
                ),
            ) .
            '<label class="check"><input type="checkbox" name="cpf_omitted" value="1" data-omit-field="cpf" checked> Não quis informar agora</label></div>';
        $birthField =
            '<div class="optional-field">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Nascimento", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("birth_date", "date", "")) .
            '<label class="check"><input type="checkbox" name="birth_omitted" value="1" data-omit-field="birth_date" checked> Não quis informar agora</label></div>';
        $newFormActions =
            '<div class="form-actions"><a class="ghost" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("leads") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            '<span>Voltar</span></a><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
            "<span>Salvar interessado</span></button></div>";
        $leadCreateIntro =
            '<section class="lead-ds-intro" aria-label="Resumo do cadastro de interessado"><span class="lead-ds-intro-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("person_search") .
            "</span><div><strong>Cadastro inicial do interessado</strong><small>Registre o contato com clareza, mantenha o histórico e converta para paciente somente quando os dados estiverem prontos.</small></div></section>";
        $leadPrimaryOpen =
            '<section class="lead-ds-form-section"><div class="lead-ds-section-head"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("call") .
            '</span><div><strong>Contato e interesse</strong><small>Telefone, nome e interesse formam o cadastro mínimo para acompanhamento.</small></div></div><div class="lead-ds-form-grid">';
        $leadExtraOpen =
            '<section class="lead-ds-form-section lead-ds-form-section-extra"><div class="lead-ds-section-head"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("assignment_ind") .
            '</span><div><strong>Informações complementares</strong><small>Dados opcionais ajudam na conversão futura, sem bloquear o primeiro registro.</small></div></div><div class="lead-form-extra lead-form-extra-open lead-ds-extra-grid"><div class="two">';
        $newForm =
            '<form method="post" action="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("leads") .
            '" class="compact lead-create-form lead-create-page-form lead-ds-create-form" data-lead-create-form>' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="save">' .
            $leadCreateIntro .
            $leadPrimaryOpen .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Telefone / WhatsApp",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "phone",
                    "text",
                    "",
                    'required autocomplete="tel" inputmode="tel" data-lead-phone-lookup="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("lead_lookup")) .
                        '"',
                ),
            ) .
            '<div class="lead-lookup-status" data-lead-lookup-status aria-live="polite"></div>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome completo",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "name",
                    "text",
                    "",
                    'required autocomplete="name" list="prontoo_person_suggestions" data-person-autosuggest',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Interesse principal",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "interest",
                    "text",
                    "",
                    'placeholder="Ex.: consulta, retorno, avaliação"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Etapa inicial",
                "stage",
                [
                    "em_aberto" => "Em Aberto",
                    "aguarda_retorno" => "Aguarda retorno",
                ],
                "em_aberto",
            ) .
            "</div></section>" .
            $leadExtraOpen .
            $cpfField .
            $birthField .
            "</div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Origem do contato",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "source",
                    "text",
                    "",
                    'placeholder="WhatsApp, indicação, Instagram..."',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Próximo contato", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("next_action_at", "datetime-local")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Observação inicial", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("notes", "", 'rows="4"')) .
            "</div></section>" .
            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::person_autosuggest_datalist($cid) .
            $newFormActions .
            "</form>";
        if (isset($_GET["new"]) && (string) $_GET["new"] !== "0") {
            $newLeadHeadAction =
                '<a class="ghost small cmdlike" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("leads") .
                '">' .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Interessados", "arrow_back") .
                "</a>";
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Novo Interessado",
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                    "Novo Interessado",
                    "Cadastro inicial para acompanhar contatos antes da conversão em paciente.",
                    $newLeadHeadAction,
                ) . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($newForm, "lead-new-screen-card lead-ds-new-card"),
            );
            return;
        }
        $headAction =
            '<a class="primary small cmdlike" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("leads", ["new" => 1]) .
            '">' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Novo Interessado", "person_add") .
            "</a>";
        $stats =
            '<section class="lead-overview kpis kpi-info-strip" aria-label="Resumo de interessados"><article class="kpi-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("person_search") .
            "<p><b>" .
            (int) $newLeads .
            '</b><span>Novos Interessados (30 dias)</span></p></article><article class="kpi-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("hourglass_top") .
            "<p><b>" .
            (int) ($counts["aguarda_retorno"] ?? 0) .
            '</b><span>Aguarda retorno</span></p></article><article class="kpi-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("today") .
            "<p><b>" .
            (int) $todayReturns .
            "</b><span>Retornos hoje</span></p></article></section>";
        $floatingLate =
            $lateReturns > 0
                ? '<aside class="lead-floating-pending agenda-floating-kpis ds-fixed-agenda-kpis" data-ds-fixed-layer aria-label="Pendências de interessados"><a class="agenda-floating-card agenda-floating-card-overdue" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("leads", ["status" => "aguarda_retorno"]) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_busy") .
                    '<span class="agenda-floating-copy"><b>' .
                    (int) $lateReturns .
                    "</b><span>Retornos vencidos</span></span></a></aside>"
                : "";
        $chips = $stageOptions;
        $chipIcons = LeadsDomainOperations01::lead_stage_icons();
        $chipHtml =
            '<nav class="patient-filter-chips lead-filter-chips ds-selection-chips" aria-label="Filtros de interessados por etapa">';
        if ($qTerm !== "") {
            $chipHtml .=
                '<span class="patient-filter-chip ds-filter-chip active is-active patient-filter-found" aria-current="page" data-ds-filter-chip>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("manage_search") .
                "<span>Encontrados</span><small>" .
                number_format(count($rows), 0, ",", ".") .
                "</small></span>";
        }
        foreach ($chips as $key => $label) {
            $num = $counts[$key] ?? 0;
            $chipClass =
                "patient-filter-chip ds-filter-chip" .
                ($qTerm === "" && $status === $key ? " active" : "");
            $chipHtml .=
                '<a class="' .
                $chipClass .
                '" data-ds-filter-chip href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("leads", ["status" => $key]) .
                '" aria-label="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                " — " .
                (int) $num .
                ' interessado(s)">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($chipIcons[$key] ?? "label") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                "</span></a>";
        }
        $chipHtml .= "</nav>";
        $search =
            '<form method="get" class="lead-search patient-search-bar"><input type="hidden" name="r" value="leads"><input type="hidden" name="status" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($status) .
            '"><label class="search-field"><input type="search" name="q" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($qTerm) .
            '" placeholder="Nome, telefone, origem ou interesse" aria-label="Buscar interessado por nome, telefone, origem ou interesse"></label><button class="primary small" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("search") .
            "<span>Busca rápida</span></button>" .
            ($qTerm !== ""
                ? '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("leads", ["status" => $status]) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                    "<span>Limpar</span></a>"
                : "") .
            "</form>";
        $convertLeadId = (int) ($_GET["convert_lead"] ?? 0);
        $convertCpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_GET["convert_cpf"] ?? ""));
        $convertExistingPatient =
            $convertCpf !== "" ? LeadsRuntimeOperations01::lead_patient_by_cpf($cid, $convertCpf) : null;
        $cards = "";
        foreach ($rows as $r) {
            $rawStage = (string) ($r["stage"] ?? "em_aberto");
            $normStage = LeadsDomainOperations01::lead_stage_normalize($rawStage);
            $stageLabel = $stageLabels[$normStage] ?? $normStage;
            $stageIcon = $chipIcons[$normStage] ?? "person_search";
            $ps = $persons[(int) ($r["person_id"] ?? 0)] ?? [];
            $cpfOk =
                !empty($ps["cpf"]) &&
                strlen(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) $ps["cpf"])) === 11;
            $birthOk = !empty($ps["birth_date"]);
            $cpfTxt = $cpfOk ? "CPF " . \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($ps["cpf"]) : "CPF pendente";
            $birthTxt = $birthOk
                ? "Nascimento: " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($ps["birth_date"])
                : "nascimento pendente";
            $patientId = $patientByPerson[(int) ($r["person_id"] ?? 0)] ?? 0;
            $nextRaw = (string) ($r["next_action_at"] ?? "");
            $isLate =
                $nextRaw !== "" &&
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($nextRaw) < time() &&
                !in_array($normStage, ["convertido", "arquivado"], true);
            $nextTxt =
                $nextRaw !== ""
                    ? "Próximo contato: " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($nextRaw)
                    : "Sem retorno agendado";
            $lastTxt = !empty($r["updated_at"])
                ? "Atualizado em " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["updated_at"])
                : "Cadastrado em " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["created_at"]);
            $class =
                "lead-card stage-" .
                $stageClass($rawStage) .
                ($isLate ? " is-late" : "");
            $primaryAction = "";
            if ($patientId > 0) {
                $primaryAction =
                    '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => $patientId]) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("folder_open") .
                    "<span>Abrir paciente</span></a>";
            } else {
                $cpfValue = $cpfOk ? \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) $ps["cpf"]) : "";
                $birthValue = $birthOk ? (string) $ps["birth_date"] : "";
                $convertHelp =
                    $cpfOk && $birthOk
                        ? "CPF encontrado para este interessado. Confira os demais dados antes de concluir."
                        : "Informe primeiro o CPF. Depois complete os dados pendentes para concluir.";
                $conflictHtml = "";
                $openAttr = "";
                if ($convertLeadId === (int) $r["id"] && $convertExistingPatient) {
                    $openAttr = " open";
                    $patientName =
                        (string) ($convertExistingPatient["full_name"] ??
                            "Paciente");
                    $patientLinkId =
                        (int) ($convertExistingPatient["patient_id"] ?? 0);
                    $conflictHtml =
                        '<div class="lead-convert-existing flash warn"><strong>Este interessado já era paciente.</strong><span>Seu Interesse foi Arquivado. Abra a Ficha do Paciente para Continuar.</span><div class="lead-convert-existing-actions">' .
                        ($patientLinkId > 0
                            ? '<a class="primary small" href="' .
                                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => $patientLinkId]) .
                                '">' .
                                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("folder_open") .
                                "<span>Abrir Ficha do Paciente</span></a>"
                            : "") .
                        '<form method="post" class="inline">' .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                        '<input type="hidden" name="act" value="archive_lead"><input type="hidden" name="lead_id" value="' .
                        (int) $r["id"] .
                        '"><button class="ghost small" type="submit">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("archive") .
                        "<span>Arquivar interessado</span></button></form></div></div>";
                }
                $primaryAction =
                    '<details class="lead-card-details form-panel lead-convert-panel"' .
                    $openAttr .
                    '><summary class="primary small cmdlike">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Tornar Paciente", "person_add") .
                    "</summary>" .
                    $conflictHtml .
                    '<form method="post" class="compact lead-convert-form" data-lead-convert-form>' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="convert"><input type="hidden" name="lead_id" value="' .
                    (int) $r["id"] .
                    '"><p class="field-help lead-convert-help">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($convertHelp) .
                    "</p>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "CPF",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "cpf",
                            "text",
                            $cpfValue,
                            'required inputmode="numeric" maxlength="14" data-cpf-mask data-lead-patient-cpf-lookup="' .
                                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("lead_patient_lookup")) .
                                '" autocomplete="off"',
                        ),
                    ) .
                    '<div class="lead-convert-existing lead-convert-lookup-status" data-lead-convert-status aria-live="polite"></div><div class="two">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Nome Completo",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "name",
                            "text",
                            $r["name"],
                            'required autocomplete="name" data-lead-convert-name',
                        ),
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Nascimento",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "birth_date",
                            "date",
                            $birthValue,
                            "required data-lead-convert-birth",
                        ),
                    ) .
                    "</div>" .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Concluir") .
                    "</form></details>";
            }
            $phoneDisplay =
                '<input type="text" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($r["phone"]) .
                '" readonly aria-readonly="true" inputmode="tel">';
            $touch =
                '<details class="lead-card-details"><summary class="ghost small cmdlike">' .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Registrar contato", "forum") .
                '</summary><form method="post" class="compact lead-touch-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="update"><input type="hidden" name="contact_event" value="1"><input type="hidden" name="lead_id" value="' .
                (int) $r["id"] .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Nome", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("name", "text", $r["name"], "required")) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Telefone / WhatsApp", $phoneDisplay) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Interesse", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("interest", "text", $r["interest"])) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Origem", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("source", "text", $r["source"])) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label("Etapa", "stage", $editableStageOptions, $normStage) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Próximo contato",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "next_action_at",
                        "datetime-local",
                        !empty($r["next_action_at"])
                            ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local_input(
                                (string) $r["next_action_at"],
                                $cid,
                                $c,
                            )
                            : "",
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Ocorrência / observação do contato",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                        "notes",
                        "",
                        'rows="5" placeholder="Registre apenas o que aconteceu neste contato"',
                    ),
                ) .
                '<small class="field-help lead-immutable-help">Telefone é identidade imutável do interessado.</small>' .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Atualizar interessado") .
                "</form></details>";
            $interestText =
                mb_trim((string) ($r["interest"] ?? "")) ?: "Interesse não informado";
            $phoneText = mb_trim((string) ($r["phone"] ?? "")) ?: "sem telefone";
            $sourceText = mb_trim((string) ($r["source"] ?? "")) ?: "sem origem";
            $leadName = mb_trim((string) ($r["name"] ?? "")) ?: "Interessado sem nome";
            $miniMeta =
                '<div class="lead-card-meta lead-card-mini-meta ds-person-meta"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("call") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($phoneText) .
                "</span></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("medical_information") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($interestText) .
                "</span></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sourceText) .
                "</span></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($isLate ? "notification_important" : "event_upcoming") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($nextTxt) .
                "</span></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("update") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($lastTxt) .
                "</span></span></div>";
            $compactMeta =
                '<div class="lead-card-meta lead-card-mini-meta"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($sourceText) .
                "</span></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cpfTxt) .
                "</span></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("cake") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($birthTxt) .
                "</span></span></div>";
            $cards .=
                '<details class="lead-list-item ds-lead-item"><summary class="lead-list-summary ds-person-row ds-lead-row stage-' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($stageClass($rawStage)) .
                ($isLate ? " is-late" : "") .
                '">' .
                '<span class="lead-card-avatar ds-person-avatar" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($stageIcon) .
                "</span>" .
                '<div class="lead-card-main ds-person-main"><div class="lead-card-title ds-person-title"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($leadName) .
                '</strong><span class="pill lead-status ds-status-pill stage-' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($stageClass($rawStage)) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($stageLabel) .
                "</span></div>" .
                $miniMeta .
                "</div>" .
                '<span class="lead-card-actions ds-person-actions lead-summary-actions"><span class="ghost small lead-open-btn">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("expand_more") .
                "<span>Abrir</span></span></span>" .
                '</summary><article class="' .
                $class .
                ' lead-card-compact lead-card-expanded lead-card-occurrences-only">' .
                LeadsRuntimeOperations01::lead_history_html(
                    $leadEvents[(int) $r["id"]] ?? [],
                    $leadEventUsers,
                    $stageLabels,
                ) .
                '<footer><div class="lead-actions">' .
                $primaryAction .
                $touch .
                "</div></footer></article></details>";
        }
        $list =
            $cards !== ""
                ? '<div class="lead-list ds-person-list ds-lead-list">' .
                    $cards .
                    "</div>"
                : '<div class="empty">' .
                    ($qTerm !== ""
                        ? "Nenhum interessado encontrado para esta busca."
                        : "Nenhum interessado encontrado para este filtro.") .
                    "</div>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Interessados",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Interessados",
                "Pessoas que demonstraram interesse e ainda não foram convertidas em pacientes.",
                $headAction,
            ) .
                $floatingLate .
                $stats .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($search, "lead-search-card ds-search-card") .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($chipHtml . $list, "lead-screen-card ds-filter-list-block"),
        );
    
    }
}
