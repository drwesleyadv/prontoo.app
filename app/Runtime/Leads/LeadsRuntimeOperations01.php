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

final class LeadsRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function lead_phone_digits(string $phone): string
    
    {
    
        return substr(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($phone), 0, 11);
    
    }

    public static function lead_cpf_br(string $cpf): string
    
    {
    
        $d = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cpf);
        if (strlen($d) !== 11) {
            return $cpf;
        }
        return substr($d, 0, 3) .
            "." .
            substr($d, 3, 3) .
            "." .
            substr($d, 6, 3) .
            "-" .
            substr($d, 9, 2);
    
    }

    public static function lead_find_by_phone(
        int $cid,
        string $phoneDigits,
        int $excludeId = 0,
    ): ?array 
    {
    
        $phoneDigits = self::lead_phone_digits($phoneDigits);
        if ($cid <= 0 || $phoneDigits === "") {
            return null;
        }
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::ensure_lead_events_schema();
        $where =
            "clinic_id=? AND (phone_digits=? OR LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),'(',''),')',''),' ',''),'-',''),'.',''),11)=?)";
        $params = [$cid, $phoneDigits, $phoneDigits];
        if ($excludeId > 0) {
            $where .= " AND id<>?";
            $params[] = $excludeId;
        }
        return \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
            "SELECT id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes,created_by,created_at,updated_at FROM pi_leads WHERE $where ORDER BY CASE WHEN " .
                LeadsDomainOperations01::lead_active_stage_sql("stage") .
                " THEN 0 WHEN stage='arquivado' THEN 1 ELSE 2 END, COALESCE(updated_at,created_at) DESC, id DESC LIMIT 1",
            $params,
        ) ?:
            null;
    
    }

    public static function lead_event_create(
        int $cid,
        int $leadId,
        int $uid,
        string $stageFrom,
        string $stageTo,
        string $phone,
        string $source,
        string $interest,
        ?string $next,
        string $body,
        string $eventType = "contato",
    ): void 
    {
    
        if ($cid <= 0 || $leadId <= 0) {
            return;
        }
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::ensure_lead_events_schema();
        $eventType = preg_replace("/[^a-z0-9_\-]/i", "", $eventType) ?: "contato";
        $stageFrom =
            trim($stageFrom) !== "" ? LeadsDomainOperations01::lead_stage_normalize($stageFrom) : "";
        $stageTo = trim($stageTo) !== "" ? LeadsDomainOperations01::lead_stage_normalize($stageTo) : "";
        $body = trim($body);
        if ($body === "") {
            $body =
                $eventType === "cadastro"
                    ? "Contato inicial registrado sem observação adicional."
                    : "Contato registrado sem observação adicional.";
        }
        \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "INSERT INTO pi_lead_events (clinic_id,lead_id,event_type,stage_from,stage_to,phone,source,interest,next_action_at,body,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                $leadId,
                $eventType,
                $stageFrom,
                $stageTo,
                $phone,
                $source,
                $interest,
                $next,
                $body,
                $uid,
            ],
        );
    
    }

    public static function lead_prepare_person_for_patient(
        array $lead,
        array $data,
        int $cid,
    ): int 
    {
    
        $leadId = (int) ($lead["id"] ?? 0);
        $name = mb_trim((string) ($data["name"] ?? ($lead["name"] ?? "")));
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($data["cpf"] ?? ""));
        $birth = mb_trim((string) ($data["birth_date"] ?? ""));
        if ($name === "") {
            throw new RuntimeException(
                "Informe o nome do interessado antes de tornar paciente.",
            );
        }
        if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            throw new RuntimeException(
                "Informe um CPF válido para tornar paciente.",
            );
        }
        if (!\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($birth)) {
            throw new RuntimeException(
                "Informe a data de nascimento para tornar paciente.",
            );
        }
        $pid = (int) ($lead["person_id"] ?? 0);
        $existingByCpf =
            (int) (\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val("SELECT id FROM pi_persons WHERE cpf=? LIMIT 1", [$cpf]) ??
                0);
        if ($existingByCpf > 0 && $existingByCpf !== $pid) {
            $identity = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_identity_immutable_values(
                $existingByCpf,
                $cpf,
                $birth,
                true,
            );
            $birth = (string) $identity["birth_date"];
            $sig = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_value($cpf, $name, $birth);
            \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "UPDATE pi_persons SET full_name=COALESCE(NULLIF(full_name,''),?), assinatura=COALESCE(NULLIF(assinatura,''),?), clinic_id=COALESCE(clinic_id,?), updated_at=NOW() WHERE id=?",
                [$name, $sig, $cid, $existingByCpf],
            );
            if ($leadId > 0) {
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "UPDATE pi_leads SET person_id=?, name=COALESCE(NULLIF(name,''),?), updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [$existingByCpf, $name, $leadId, $cid],
                );
            }
            return $existingByCpf;
        }
        if ($pid > 0) {
            $identity = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_identity_immutable_values($pid, $cpf, $birth, true);
            $cpf = (string) $identity["cpf"];
            $birth = (string) $identity["birth_date"];
            $sig = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_value($cpf, $name, $birth);
            \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "UPDATE pi_persons SET full_name=COALESCE(NULLIF(full_name,''),?), cpf=?, birth_date=?, assinatura=COALESCE(NULLIF(assinatura,''),?), clinic_id=COALESCE(clinic_id,?), updated_at=NOW() WHERE id=?",
                [$name, $cpf, $birth, $sig, $cid, $pid],
            );
            return $pid;
        }
        $pid = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::upsert_person($name, $cpf, $birth);
        if ($leadId > 0) {
            \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "UPDATE pi_leads SET person_id=?, name=COALESCE(NULLIF(name,''),?), updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$pid, $name, $leadId, $cid],
            );
        }
        return $pid;
    
    }

    public static function lead_patient_by_cpf(int $cid, string $cpf): ?array
    
    {
    
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cpf);
        if ($cid <= 0 || !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            return null;
        }
        $row = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
            "SELECT pat.id patient_id, pat.person_id, per.full_name, per.cpf, per.birth_date FROM pi_patients pat JOIN pi_persons per ON per.id=pat.person_id WHERE pat.clinic_id=? AND pat.active=1 AND per.cpf=? LIMIT 1",
            [$cid, $cpf],
        );
        return $row ?: null;
    
    }

    public static function lead_patient_by_phone(int $cid, string $phoneDigits): ?array
    
    {
    
        $phoneDigits = self::lead_phone_digits($phoneDigits);
        if ($cid <= 0 || strlen($phoneDigits) < 10) {
            return null;
        }
        $row = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
            "SELECT pat.id patient_id, pat.person_id, pat.phone, per.full_name, per.cpf, per.birth_date FROM pi_patients pat JOIN pi_persons per ON per.id=pat.person_id WHERE pat.clinic_id=? AND pat.active=1 AND LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(pat.phone,''),'(',''),')',''),' ',''),'-',''),'.',''),11)=? LIMIT 1",
            [$cid, $phoneDigits],
        );
        return $row ?: null;
    
    }

    public static function lead_history_html(
        array $items,
        array $users,
        array $stageLabels,
    ): string 
    {
    
        if (!$items) {
            return '<div class="lead-history lead-history-compact empty-history"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("history") .
                "</span><small>Sem ocorrências registradas.</small></div>";
        }
        $html =
            '<div class="lead-history lead-history-compact" aria-label="Histórico de ocorrências">';
        foreach ($items as $ev) {
            $who = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name(
                $users[(int) ($ev["created_by"] ?? 0)]["name"] ?? "Sistema",
            );
            $when = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br((string) ($ev["created_at"] ?? ""));
            $type = (string) ($ev["event_type"] ?? "contato");
            $body = mb_trim((string) ($ev["body"] ?? ""));
            if ($body === "") {
                $body = "Sem observações.";
            }
            $bodyShort =
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(mb_substr($body, 0, 220)) . (mb_strlen($body) > 220 ? "…" : "");
            $summary = "Falou com " . $who . " em " . $when . ".";
            $html .=
                "<article><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($type === "cadastro" ? "person_add" : "forum") .
                "</span><div><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($summary) .
                "</b><p>" .
                $bodyShort .
                "</p></div></article>";
        }
        return $html . "</div>";
    
    }

    public static function page_lead_lookup(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("leads");
        $cid = (int) $c["clinic_id"];
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        if (\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("lead_lookup"), 40, 300)) {
            http_response_code(429);
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => "Aguarde alguns instantes.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $phoneDigits = self::lead_phone_digits((string) ($_GET["phone"] ?? ""));
        if (strlen($phoneDigits) < 10) {
            echo json_encode(
                [
                    "ok" => true,
                    "found" => false,
                    "message" => "Informe o telefone para continuar.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $lead = self::lead_find_by_phone($cid, $phoneDigits);
        if (!$lead) {
            $patient = self::lead_patient_by_phone($cid, $phoneDigits);
            if ($patient) {
                echo json_encode(
                    [
                        "ok" => true,
                        "found" => true,
                        "patient_found" => true,
                        "already_patient" => true,
                        "patient_id" => (int) $patient["patient_id"],
                        "open_url" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", [
                            "id" => (int) $patient["patient_id"],
                        ]),
                        "message" =>
                            "Paciente já cadastrado localizado pelo telefone. Abra a ficha para registrar o atendimento.",
                        "name" => (string) ($patient["full_name"] ?? ""),
                        "phone" =>
                            (string) ($patient["phone"] ?? \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($phoneDigits)),
                        "source" => "Recepção",
                        "interest" => "Paciente cadastrado",
                        "stage" => "convertido",
                        "next_action_at" => "",
                        "notes" => "",
                    ],
                    JSON_UNESCAPED_UNICODE,
                );
                return;
            }
            echo json_encode(
                [
                    "ok" => true,
                    "found" => false,
                    "message" => "Telefone sem histórico. Continue o cadastro.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $next = "";
        if (!empty($lead["next_action_at"])) {
            $next = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local_input(
                (string) $lead["next_action_at"],
                $cid,
            );
        }
        echo json_encode(
            [
                "ok" => true,
                "found" => true,
                "message" =>
                    "Telefone já registrado. Recuperamos os dados para continuar o histórico de ocorrências.",
                "lead_id" => (int) $lead["id"],
                "name" => (string) ($lead["name"] ?? ""),
                "phone" => (string) ($lead["phone"] ?? \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($phoneDigits)),
                "source" => (string) ($lead["source"] ?? ""),
                "interest" => (string) ($lead["interest"] ?? ""),
                "stage" => LeadsDomainOperations01::lead_stage_normalize(
                    (string) ($lead["stage"] ?? "em_aberto"),
                ),
                "next_action_at" => $next,
                "notes" => (string) ($lead["notes"] ?? ""),
            ],
            JSON_UNESCAPED_UNICODE,
        );
    
    }

    public static function page_lead_patient_lookup(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("leads");
        $cid = (int) $c["clinic_id"];
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        if (
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("lead_patient_lookup"),
                60,
                300,
            )
        ) {
            http_response_code(429);
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => "Aguarde alguns instantes.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_GET["cpf"] ?? ""));
        if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => "Informe um CPF válido.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $person = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
            "SELECT p.id,p.full_name,p.cpf,p.birth_date
             FROM pi_persons p
             WHERE p.cpf=?
               AND (
                 EXISTS (SELECT 1 FROM pi_patients pat WHERE pat.person_id=p.id AND pat.clinic_id=?)
                 OR EXISTS (SELECT 1 FROM pi_leads l WHERE l.person_id=p.id AND l.clinic_id=?)
                 OR EXISTS (
                   SELECT 1
                   FROM pi_users u
                   JOIN pi_user_roles ur ON ur.user_id=u.id
                   WHERE u.person_id=p.id AND ur.clinic_id=?
                 )
               )
             LIMIT 1",
            [$cpf, $cid, $cid, $cid],
        );
        if (!$person) {
            echo json_encode(
                [
                    "ok" => true,
                    "found" => false,
                    "already_patient" => false,
                    "message" => "CPF válido. Complete Nome Completo e Nascimento.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $patient = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
            "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=1 LIMIT 1",
            [$cid, (int) $person["id"]],
        );
        echo json_encode(
            [
                "ok" => true,
                "found" => true,
                "already_patient" => (bool) $patient,
                "patient_id" => $patient ? (int) $patient["id"] : null,
                "open_url" => $patient
                    ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => (int) $patient["id"]])
                    : "",
                "message" => $patient
                    ? "Este interessado já era paciente. Ao concluir, o interesse será arquivado."
                    : "Dados encontrados e preenchidos automaticamente.",
                "name" => (string) ($person["full_name"] ?? ""),
                "cpf" => self::lead_cpf_br((string) ($person["cpf"] ?? "")),
                "birth_date" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(
                    $person["birth_date"] ?? "",
                ),
            ],
            JSON_UNESCAPED_UNICODE,
        );
    
    }
}
