<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Documents;

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

final class DocumentsRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function document_issue_context(
        array $c,
        int $patientId = 0,
        int $appointmentId = 0,
        array $context = [],
    ): array 
    {
    
        $cid = (int) ($c["clinic_id"] ?? 0);
        [$patientId, $appointmentId, $appt] = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_resolve_patient_appointment(
            $cid,
            $patientId,
            $appointmentId,
        );
        $patientName = "";
        $patientCpf = "";
        $patientBirth = "";
        $patientCity = "";
        $guardianName = "";
        $guardianCpf = "";
        $guardianRel = "";
        if ($patientId > 0) {
            $pat = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.03.document_issue_context.01', [$patientId, $cid], []);
            if ($pat) {
                $patientName = (string) ($pat["full_name"] ?? "");
                $patientCpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cpf_br((string) ($pat["cpf"] ?? ""));
                $patientBirth = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($pat["birth_date"] ?? null);
                $patientCity = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::city_state_label(
                    $pat["address_city"] ?? "",
                    $pat["address_state"] ?? "",
                );
            }
            $guardian = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_primary_legal_guardian($cid, $patientId);
            if ($guardian) {
                $relOpts = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_guardian_relationship_options();
                $guardianName = (string) ($guardian["full_name"] ?? "");
                $guardianCpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cpf_br((string) ($guardian["cpf"] ?? ""));
                $guardianRel =
                    $relOpts[(string) ($guardian["relationship"] ?? "")] ??
                    "Responsável Legal";
            }
        }
        $cl =
            \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.03.document_issue_context.02', [$cid], []) ?:
            [];
        $apptStart = (string) ($appt["start_at"] ?? "");
        $apptEnd = (string) ($appt["end_at"] ?? "");
        $realStart = (string) ($appt["consultation_started_at"] ?? "");
        $realEnd = (string) ($appt["consultation_finished_at"] ?? "");
        $scheduledRange = trim(
            ($apptStart !== "" ? \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_time_br($apptStart) : "") .
                ($apptEnd !== "" ? " a " . \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_time_br($apptEnd) : ""),
        );
        $realDuration = "";
        if (
            $realStart !== "" &&
            $realEnd !== "" &&
            strtotime($realEnd) > strtotime($realStart)
        ) {
            $realDuration = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::format_minutes(
                (int) floor((strtotime($realEnd) - strtotime($realStart)) / 60),
            );
        }
        $ctx = \Prontoo\Domain\Documents\DocumentHtmlPolicy::document_context_json_decode($context);
        $collaboratorName = (string) ($c["user"]["name"] ?? "Colaborador");
        $collaboratorEmail = "";
        $collaboratorRole = "";
        $professional = (string) ($c["user"]["name"] ?? "Profissional");
        $professionalRole = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for((string) ($c["role"] ?? ""), $cid);
        $collabId = (int) ($ctx["collaborator_user_id"] ?? 0);
        if ($collabId > 0 && \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_user_exists($cid, $collabId)) {
            $u = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.03.document_issue_context.03', [$cid, $collabId], []);
            if ($u) {
                $collaboratorName = (string) ($u["name"] ?? $collaboratorName);
                $collaboratorEmail = (string) ($u["email"] ?? "");
                $collaboratorRole = (string) ($u["role_labels"] ?? "");
                if (str_contains((string) ($u["role_codes"] ?? ""), "medico")) {
                    $professional = $collaboratorName;
                    $professionalRole = $collaboratorRole;
                }
            }
        }
        $vars = [
            "paciente" => $patientName ?: "Paciente",
            "nome_paciente" => $patientName ?: "Paciente",
            "paciente_nome" => $patientName ?: "Paciente",
            "cpf_paciente" => $patientCpf ?: "",
            "paciente_cpf" => $patientCpf ?: "",
            "nascimento_paciente" => $patientBirth ?: "",
            "paciente_nascimento" => $patientBirth ?: "",
            "telefone_paciente" => (string) ($pat["phone"] ?? ""),
            "paciente_telefone" => (string) ($pat["phone"] ?? ""),
            "email_paciente" => (string) ($pat["email"] ?? ""),
            "paciente_email" => (string) ($pat["email"] ?? ""),
            "endereco_paciente" => trim(
                (string) ($pat["address"] ?? "") .
                    ((string) ($pat["address_number"] ?? "") !== ""
                        ? ", " . (string) $pat["address_number"]
                        : ""),
            ),
            "bairro_paciente" => (string) ($pat["address_neighborhood"] ?? ""),
            "cep_paciente" => \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::mask_cep((string) ($pat["address_zip"] ?? "")),
            "cidade_paciente" => $patientCity,
            "responsavel_legal" => $guardianName,
            "cpf_responsavel_legal" => $guardianCpf,
            "vinculo_responsavel_legal" => $guardianRel,
            "profissional" => $professional,
            "profissional_cargo" => $professionalRole,
            "colaborador" => $collaboratorName,
            "colaborador_nome" => $collaboratorName,
            "colaborador_email" => $collaboratorEmail,
            "colaborador_cargo" => $collaboratorRole,
            "consultorio" =>
                $cl["display_name"] ?? "" ?:
                ($c["clinic"] ?? "" ?:
                $cl["legal_name"] ?? "Consultório"),
            "data" => date("d/m/Y"),
            "data_abreviada" => date("d/m/Y"),
            "data_extenso" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_extenso_br(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::now()),
            "cidade" => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::city_state_label(
                $cl["address_city"] ?? "",
                $cl["address_state"] ?? "",
            ),
            "endereco" => (string) ($cl["address_line"] ?? ""),
            "agendamento_data" =>
                $apptStart !== "" ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_extenso_br($apptStart) : "",
            "agendamento_hora" => \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_time_br($apptStart),
            "agendamento_horario" => $scheduledRange,
            "horario_agendamento" => $scheduledRange,
            "agendamento_inicio" => $apptStart !== "" ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($apptStart) : "",
            "agendamento_fim" => $apptEnd !== "" ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($apptEnd) : "",
            "agendamento_profissional" => (string) ($appt["doctor_name"] ?? ""),
            "agendamento_procedimento" => (string) ($appt["procedure_title"] ?? ""),
            "agendamento_motivo" => (string) ($appt["reason"] ?? ""),
            "agendamento_observacoes" => (string) ($appt["notes"] ?? ""),
            "agendamento_status" => \Prontoo\Domain\Documents\DocumentTypePolicy::document_status_label(
                (string) ($appt["status"] ?? ""),
            ),
            "atendimento_inicio_real" =>
                $realStart !== "" ? \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_time_br($realStart) : "",
            "horario_real_inicio_consulta" =>
                $realStart !== "" ? \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_time_br($realStart) : "",
            "atendimento_fim_real" =>
                $realEnd !== "" ? \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_time_br($realEnd) : "",
            "horario_real_fim_atendimento" =>
                $realEnd !== "" ? \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_time_br($realEnd) : "",
            "atendimento_duracao_real" => $realDuration,
        ];
        $leadId = (int) ($ctx["lead_id"] ?? 0);
        if ($leadId > 0) {
            $l = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.03.document_issue_context.04', [$leadId, $cid], []);
            if ($l) {
                $vars += [
                    "interessado" => (string) ($l["name"] ?? ""),
                    "interessado_telefone" => (string) ($l["phone"] ?? ""),
                    "interessado_origem" => (string) ($l["source"] ?? ""),
                    "interessado_interesse" => (string) ($l["interest"] ?? ""),
                    "interessado_etapa" => (string) ($l["stage"] ?? ""),
                    "interessado_proximo_contato" => !empty($l["next_action_at"])
                        ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) $l["next_action_at"])
                        : "",
                    "interessado_observacoes" => (string) ($l["notes"] ?? ""),
                ];
            }
        }
        $procId = (int) ($ctx["procedure_id"] ?? 0);
        if ($procId > 0) {
            $pr = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.03.document_issue_context.05', [$procId, $cid], []);
            if ($pr) {
                $vars += [
                    "procedimento" => (string) ($pr["title"] ?? ""),
                    "procedimento_categoria" => (string) ($pr["category"] ?? ""),
                    "procedimento_descricao" => (string) ($pr["description"] ?? ""),
                    "procedimento_duracao" => \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::format_minutes(
                        (int) ($pr["duration_minutes"] ?? 0),
                    ),
                    "procedimento_valor" =>
                        (int) ($pr["price_cents"] ?? 0) > 0
                            ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $pr["price_cents"])
                            : "",
                    "procedimento_formas_pagamento" =>
                        (string) ($pr["payment_methods"] ?? ""),
                    "procedimento_orientacoes_pre" =>
                        (string) ($pr["pre_instructions"] ?? ""),
                    "procedimento_cuidados_pos" =>
                        (string) ($pr["post_care"] ?? ""),
                ];
            }
        }
        $taskId = (int) ($ctx["task_id"] ?? 0);
        if ($taskId > 0) {
            $t = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.03.document_issue_context.06', [$taskId, $cid], []);
            if ($t) {
                $vars += [
                    "tarefa" => (string) ($t["title"] ?? ""),
                    "tarefa_status" => \Prontoo\Domain\Documents\DocumentTypePolicy::document_status_label(
                        (string) ($t["status"] ?? ""),
                    ),
                    "tarefa_prazo" => !empty($t["due_at"])
                        ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) $t["due_at"])
                        : "",
                    "tarefa_responsavel" => (string) ($t["assigned_name"] ?? ""),
                    "tarefa_descricao" => (string) ($t["description"] ?? ""),
                ];
            }
        }
        $careId = (int) ($ctx["care_id"] ?? 0);
        if ($careId > 0) {
            $a = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.03.document_issue_context.07', [$careId, $cid], []);
            if ($a) {
                $vars += [
                    "atividade" => (string) ($a["title"] ?? "" ?: "Atividade"),
                    "atividade_tipo" => \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_record_type_label(
                        (string) ($a["record_type"] ?? ""),
                    ),
                    "atividade_data" => !empty($a["created_at"])
                        ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) $a["created_at"])
                        : "",
                    "atividade_conteudo" => \Prontoo\Domain\Documents\DocumentsDomainOperations02::patient_summary_excerpt(
                        (string) ($a["content"] ?? ""),
                        800,
                    ),
                    "atividade_paciente" => (string) ($a["patient_name"] ?? ""),
                ];
            }
        }
        foreach (\Prontoo\Domain\Documents\DocumentTypePolicy::document_system_fields() as $key => $label) {
            if (!array_key_exists($key, $vars)) {
                $vars[$key] = "";
            }
        }
        return $vars;
    
    }

    public static function approved_document_template_options(array $c): array
    
    {
    
        $cid = (int) ($c["clinic_id"] ?? 0);
        $uid = (int) ($c["user"]["id"] ?? 0);
        $role = (string) ($c["role"] ?? "");
        if ($cid <= 0) {
            return [];
        }
        $loader = function () use ($c, $cid): array {
    
            $visibility = \Prontoo\Domain\Documents\DocumentTemplatePolicy::document_template_visibility($c);
            $params = (array) ($visibility["parameters"] ?? []);
            $visibilityMode = (string) ($visibility["mode"] ?? "role");
            $rows = \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.03.approved_document_template_options.01', array_merge([$cid], $params), compact('visibilityMode'))->fetchAll();
            $types = \Prontoo\Domain\Documents\DocumentTypePolicy::document_type_options();
            $options = [];
            foreach ($rows as $row) {
                $options[(int) $row["id"]] =
                    (string) $row["title"] .
                    " · " .
                    ($types[(string) $row["type_key"]] ?? "Documento");
            }
            return $options;
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "templates",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("approved_options", [$cid, $uid, $role]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("templates"),
                $loader,
                ["clinic:" . $cid, "user:" . $uid, "role:" . $role],
            );
        }
        return $loader();
    
    }

    public static function patient_document_timeline_items(
        array $c,
        int $patientId,
        int $limit = 40,
    ): array 
    {
    
        $cid = (int) ($c["clinic_id"] ?? 0);
        $types = \Prontoo\Domain\Documents\DocumentTypePolicy::document_type_options();
        $limit = max(1, min(200, $limit));
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.03.patient_document_timeline_items.01', [$cid, $patientId], ['limit' => $limit])->fetchAll();
        $items = [];
        foreach ($rows as $d) {
            $docId = (int) $d["id"];
            $items[] = [
                "time" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($d["issued_at"]),
                "icon" => "description",
                "title" => $d["title"],
                "body" =>
                    ($types[$d["type_key"]] ?? "Documento") .
                    " emitido por " .
                    ($d["issued_name"] ?? "colaborador"),
                "html" =>
                    '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("document_view", ["id" => $docId]) .
                    '">Visualizar</a><a class="primary small" target="_blank" rel="noopener" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("document_print", ["id" => $docId]) .
                    '">Imprimir</a>' .
                    \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_link($docId),
            ];
        }
        return $items;
    
    }

    public static function document_can_access(array $c, array $doc): bool
    
    {
    
        $cid = (int) ($c["clinic_id"] ?? 0);
        $uid = (int) ($c["user"]["id"] ?? 0);
        $role = (string) ($c["role"] ?? "");
        if (
            ($c["scope"] ?? "") !== "clinic" ||
            (int) ($doc["clinic_id"] ?? 0) !== $cid
        ) {
            return false;
        }
        if (!\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("documents")) {
            return false;
        }
        $patientId = (int) ($doc["patient_link_id"] ?? 0);
        if ($patientId > 0 && !\Prontoo\Runtime\Patients\PatientsRuntimeOperations02::clinic_patient_exists($cid, $patientId, true)) {
            return false;
        }
        if ($role === "gerente") {
            return true;
        }
        if ($role === "medico") {
            return (int) ($doc["issued_by"] ?? 0) === $uid ||
                (int) ($doc["owner_user_id"] ?? 0) === $uid ||
                in_array(
                    (string) ($doc["owner_role"] ?? ""),
                    ["assistente", "recepcionista"],
                    true,
                );
        }
        return (int) ($doc["issued_by"] ?? 0) === $uid ||
            (int) ($doc["owner_user_id"] ?? 0) === $uid;
    
    }

    public static function fetch_document_for_current_user(array $c, int $docId): ?array
    
    {
    
        if ($docId <= 0) {
            return null;
        }
        $doc = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.03.fetch_document_for_current_user.01', [$docId, (int) ($c["clinic_id"] ?? 0)], []);
        return $doc && \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::document_can_access($c, $doc) ? $doc : null;
    
    }

    public static function page_document_view(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        if (($c["scope"] ?? "") !== "clinic") {
            throw new ProntooHttpError(
                403,
                "Documento disponível apenas no contexto do consultório.",
            );
        }
        $doc = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::fetch_document_for_current_user($c, (int) ($_GET["id"] ?? 0));
        if (!$doc) {
            http_response_code(404);
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Documento",
                '<div class="empty">Documento não encontrado para esta credencial.</div>',
            );
            return;
        }
        $types = \Prontoo\Domain\Documents\DocumentTypePolicy::document_type_options();
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("documento_visualizado", "documento", (int) $doc["id"], [
            "titulo" => $doc["title"] ?? "",
            "document_type" => (string) ($doc["type_key"] ?? ""),
            "document_type_label" =>
                $types[(string) ($doc["type_key"] ?? "")] ?? "Documento",
            "patient_link_id" => (int) ($doc["patient_link_id"] ?? 0),
            "patient_name" => $doc["patient_name"] ?? "",
            "audit_body" => "",
        ]);
        $back =
            (int) ($doc["patient_link_id"] ?? 0) > 0
                ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => (int) $doc["patient_link_id"]])
                : \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("documents", ["doc" => (int) $doc["id"]]);
        $actions =
            '<a class="ghost small" href="' .
            $back .
            '">Voltar</a><a class="primary small" target="_blank" rel="noopener" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("document_print", ["id" => (int) $doc["id"]]) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("print") .
            " Imprimir</a>" .
            \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_link((int) $doc["id"]);
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Documento emitido",
                "Visualização do conteúdo gerado a partir do modelo aprovado.",
                $actions,
            ) .
            '<section class="card doc-print-card"><div class="section-head"><div><h2>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($doc["title"] ?? "Documento") .
            "</h2><p>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_issue_meta_sentence(
                    $doc["issued_name"] ?? null,
                    $doc["patient_name"] ?? null,
                    $doc["issued_at"],
                    "emitido",
                ),
            ) .
            "</p></div></div>" .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_preview_page_html(
                \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_body_to_html((string) $doc["content"]),
                $doc["document_identifier"] ?? null,
            ) .
            "</section>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Documento", $body);
    
    }

    public static function page_document_print(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        if (($c["scope"] ?? "") !== "clinic") {
            throw new ProntooHttpError(
                403,
                "Documento disponível apenas no contexto do consultório.",
            );
        }
        $doc = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::fetch_document_for_current_user($c, (int) ($_GET["id"] ?? 0));
        if (!$doc) {
            http_response_code(404);
            echo '<!doctype html><meta charset="utf-8"><title>Documento não encontrado</title><p>Documento não encontrado.</p>';
            return;
        }
        $types = \Prontoo\Domain\Documents\DocumentTypePolicy::document_type_options();
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("documento_impresso", "documento", (int) $doc["id"], [
            "titulo" => $doc["title"] ?? "",
            "document_type" => (string) ($doc["type_key"] ?? ""),
            "document_type_label" =>
                $types[(string) ($doc["type_key"] ?? "")] ?? "Documento",
            "patient_link_id" => (int) ($doc["patient_link_id"] ?? 0),
            "patient_name" => $doc["patient_name"] ?? "",
            "audit_body" => "",
        ]);
        if (!headers_sent()) {
            header("Content-Type: text/html; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        $content = \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_body_to_html((string) $doc["content"]);
        echo \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_print_document_shell_html($doc, true, "print");
    
    }

    public static function document_stage_actions(string $stage): string
    
    {
    
        $emit = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("documents", ["emit" => 1]);
        $models = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("documents", ["models" => 1]);
        $newModel = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("documents", ["new_model" => 1]);
        if ($stage === "models") {
            return '<span class="doc-page-actions"><a class="primary small" href="' .
                $newModel .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("add_circle") .
                "<span>Novo modelo</span></a></span>";
        }
        if ($stage === "new_model" || $stage === "edit_model") {
            return '<span class="doc-page-actions"><a class="ghost small" href="' .
                $models .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Modelos</span></a></span>";
        }
        return '<span class="doc-page-actions"><a class="primary small" href="' .
            $emit .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("post_add") .
            "<span>Criar documento</span></a></span>";
    
    }
}
