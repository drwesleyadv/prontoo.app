<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Documents;

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
        [$patientId, $appointmentId, $appt] = document_resolve_patient_appointment(
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
            $pat = one(
                "SELECT p.full_name,p.cpf,p.birth_date,pl.phone,pl.email,pl.address,pl.address_number,pl.address_neighborhood,pl.address_city,pl.address_state,pl.address_zip FROM pi_patients pl JOIN pi_persons p ON p.id=pl.person_id WHERE pl.id=? AND pl.clinic_id=? AND pl.active=1",
                [$patientId, $cid],
            );
            if ($pat) {
                $patientName = (string) ($pat["full_name"] ?? "");
                $patientCpf = cpf_br((string) ($pat["cpf"] ?? ""));
                $patientBirth = date_br($pat["birth_date"] ?? null);
                $patientCity = city_state_label(
                    $pat["address_city"] ?? "",
                    $pat["address_state"] ?? "",
                );
            }
            $guardian = patient_primary_legal_guardian($cid, $patientId);
            if ($guardian) {
                $relOpts = patient_guardian_relationship_options();
                $guardianName = (string) ($guardian["full_name"] ?? "");
                $guardianCpf = cpf_br((string) ($guardian["cpf"] ?? ""));
                $guardianRel =
                    $relOpts[(string) ($guardian["relationship"] ?? "")] ??
                    "Responsável Legal";
            }
        }
        $cl =
            one(
                "SELECT display_name,legal_name,address_city,address_state,address_line FROM pi_clinics WHERE id=?",
                [$cid],
            ) ?:
            [];
        $apptStart = (string) ($appt["start_at"] ?? "");
        $apptEnd = (string) ($appt["end_at"] ?? "");
        $realStart = (string) ($appt["consultation_started_at"] ?? "");
        $realEnd = (string) ($appt["consultation_finished_at"] ?? "");
        $scheduledRange = trim(
            ($apptStart !== "" ? document_time_br($apptStart) : "") .
                ($apptEnd !== "" ? " a " . document_time_br($apptEnd) : ""),
        );
        $realDuration = "";
        if (
            $realStart !== "" &&
            $realEnd !== "" &&
            strtotime($realEnd) > strtotime($realStart)
        ) {
            $realDuration = format_minutes(
                (int) floor((strtotime($realEnd) - strtotime($realStart)) / 60),
            );
        }
        $ctx = document_context_json_decode($context);
        $collaboratorName = (string) ($c["user"]["name"] ?? "Colaborador");
        $collaboratorEmail = "";
        $collaboratorRole = "";
        $professional = (string) ($c["user"]["name"] ?? "Profissional");
        $professionalRole = role_label_for((string) ($c["role"] ?? ""), $cid);
        $collabId = (int) ($ctx["collaborator_user_id"] ?? 0);
        if ($collabId > 0 && clinic_user_exists($cid, $collabId)) {
            $u = one(
                "SELECT u.id,u.name,u.email,GROUP_CONCAT(DISTINCT ur.role_code ORDER BY ur.role_code SEPARATOR ',') AS role_codes,GROUP_CONCAT(DISTINCT cr.label ORDER BY cr.sort_order SEPARATOR ', ') AS role_labels FROM pi_users u JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE u.id=? GROUP BY u.id,u.name,u.email",
                [$cid, $collabId],
            );
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
            "cep_paciente" => mask_cep((string) ($pat["address_zip"] ?? "")),
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
            "data_extenso" => date_extenso_br(now()),
            "cidade" => city_state_label(
                $cl["address_city"] ?? "",
                $cl["address_state"] ?? "",
            ),
            "endereco" => (string) ($cl["address_line"] ?? ""),
            "agendamento_data" =>
                $apptStart !== "" ? date_extenso_br($apptStart) : "",
            "agendamento_hora" => document_time_br($apptStart),
            "agendamento_horario" => $scheduledRange,
            "horario_agendamento" => $scheduledRange,
            "agendamento_inicio" => $apptStart !== "" ? dt_br($apptStart) : "",
            "agendamento_fim" => $apptEnd !== "" ? dt_br($apptEnd) : "",
            "agendamento_profissional" => (string) ($appt["doctor_name"] ?? ""),
            "agendamento_procedimento" => (string) ($appt["procedure_title"] ?? ""),
            "agendamento_motivo" => (string) ($appt["reason"] ?? ""),
            "agendamento_observacoes" => (string) ($appt["notes"] ?? ""),
            "agendamento_status" => document_status_label(
                (string) ($appt["status"] ?? ""),
            ),
            "atendimento_inicio_real" =>
                $realStart !== "" ? document_time_br($realStart) : "",
            "horario_real_inicio_consulta" =>
                $realStart !== "" ? document_time_br($realStart) : "",
            "atendimento_fim_real" =>
                $realEnd !== "" ? document_time_br($realEnd) : "",
            "horario_real_fim_atendimento" =>
                $realEnd !== "" ? document_time_br($realEnd) : "",
            "atendimento_duracao_real" => $realDuration,
        ];
        $leadId = (int) ($ctx["lead_id"] ?? 0);
        if ($leadId > 0) {
            $l = one(
                "SELECT name,phone,source,interest,stage,next_action_at,notes FROM pi_leads WHERE id=? AND clinic_id=?",
                [$leadId, $cid],
            );
            if ($l) {
                $vars += [
                    "interessado" => (string) ($l["name"] ?? ""),
                    "interessado_telefone" => (string) ($l["phone"] ?? ""),
                    "interessado_origem" => (string) ($l["source"] ?? ""),
                    "interessado_interesse" => (string) ($l["interest"] ?? ""),
                    "interessado_etapa" => (string) ($l["stage"] ?? ""),
                    "interessado_proximo_contato" => !empty($l["next_action_at"])
                        ? dt_br((string) $l["next_action_at"])
                        : "",
                    "interessado_observacoes" => (string) ($l["notes"] ?? ""),
                ];
            }
        }
        $procId = (int) ($ctx["procedure_id"] ?? 0);
        if ($procId > 0) {
            $pr = one(
                "SELECT title,category,description,duration_minutes,price_cents,payment_methods,pre_instructions,post_care FROM pi_procedures WHERE id=? AND clinic_id=?",
                [$procId, $cid],
            );
            if ($pr) {
                $vars += [
                    "procedimento" => (string) ($pr["title"] ?? ""),
                    "procedimento_categoria" => (string) ($pr["category"] ?? ""),
                    "procedimento_descricao" => (string) ($pr["description"] ?? ""),
                    "procedimento_duracao" => format_minutes(
                        (int) ($pr["duration_minutes"] ?? 0),
                    ),
                    "procedimento_valor" =>
                        (int) ($pr["price_cents"] ?? 0) > 0
                            ? money_br((int) $pr["price_cents"])
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
            $t = one(
                "SELECT t.title,t.status,t.due_at,td.description,u.name AS assigned_name FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id LEFT JOIN pi_users u ON u.id=COALESCE(t.assigned_to,t.target_user_id) WHERE t.id=? AND t.clinic_id=?",
                [$taskId, $cid],
            );
            if ($t) {
                $vars += [
                    "tarefa" => (string) ($t["title"] ?? ""),
                    "tarefa_status" => document_status_label(
                        (string) ($t["status"] ?? ""),
                    ),
                    "tarefa_prazo" => !empty($t["due_at"])
                        ? dt_br((string) $t["due_at"])
                        : "",
                    "tarefa_responsavel" => (string) ($t["assigned_name"] ?? ""),
                    "tarefa_descricao" => (string) ($t["description"] ?? ""),
                ];
            }
        }
        $careId = (int) ($ctx["care_id"] ?? 0);
        if ($careId > 0) {
            $a = one(
                "SELECT c.title,c.record_type,c.created_at,cc.content,p.full_name AS patient_name FROM pi_care c LEFT JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id JOIN pi_patients pl ON pl.id=c.patient_link_id AND pl.clinic_id=c.clinic_id JOIN pi_persons p ON p.id=pl.person_id WHERE c.id=? AND c.clinic_id=? AND c.deleted_at IS NULL",
                [$careId, $cid],
            );
            if ($a) {
                $vars += [
                    "atividade" => (string) ($a["title"] ?? "" ?: "Atividade"),
                    "atividade_tipo" => patient_record_type_label(
                        (string) ($a["record_type"] ?? ""),
                    ),
                    "atividade_data" => !empty($a["created_at"])
                        ? dt_br((string) $a["created_at"])
                        : "",
                    "atividade_conteudo" => patient_summary_excerpt(
                        (string) ($a["content"] ?? ""),
                        800,
                    ),
                    "atividade_paciente" => (string) ($a["patient_name"] ?? ""),
                ];
            }
        }
        foreach (document_system_fields() as $key => $label) {
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
    
            [$where, $params] = document_template_visible_where($c, "dt");
            $rows = q(
                "SELECT dt.id,dt.title,dt.type_key FROM pi_document_templates dt WHERE dt.clinic_id=? AND dt.status='approved' AND $where ORDER BY dt.title ASC, dt.id DESC",
                array_merge([$cid], $params),
            )->fetchAll();
            $types = document_type_options();
            $options = [];
            foreach ($rows as $row) {
                $options[(int) $row["id"]] =
                    (string) $row["title"] .
                    " · " .
                    ($types[(string) $row["type_key"]] ?? "Documento");
            }
            return $options;
        };
        if (function_exists("server_json_cache_remember")) {
            return server_json_cache_remember(
                "templates",
                server_json_cache_safe_key("approved_options", [$cid, $uid, $role]),
                server_json_cache_ttl("templates"),
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
        $types = document_type_options();
        $limit = max(1, min(200, $limit));
        $rows = q(
            "SELECT d.id,d.title,d.type_key,d.issued_at,u.name AS issued_name FROM pi_documents d LEFT JOIN pi_users u ON u.id=d.issued_by WHERE d.clinic_id=? AND d.patient_link_id=? ORDER BY d.issued_at DESC,d.id DESC LIMIT $limit",
            [$cid, $patientId],
        )->fetchAll();
        $items = [];
        foreach ($rows as $d) {
            $docId = (int) $d["id"];
            $items[] = [
                "time" => dt_br($d["issued_at"]),
                "icon" => "description",
                "title" => $d["title"],
                "body" =>
                    ($types[$d["type_key"]] ?? "Documento") .
                    " emitido por " .
                    ($d["issued_name"] ?? "colaborador"),
                "html" =>
                    '<a class="ghost small" href="' .
                    href("document_view", ["id" => $docId]) .
                    '">Visualizar</a><a class="primary small" target="_blank" rel="noopener" href="' .
                    href("document_print", ["id" => $docId]) .
                    '">Imprimir</a>' .
                    document_pdf_link($docId),
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
        if (!can("documents")) {
            return false;
        }
        $patientId = (int) ($doc["patient_link_id"] ?? 0);
        if ($patientId > 0 && !clinic_patient_exists($cid, $patientId, true)) {
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
        $doc = one(
            "SELECT d.*,dt.owner_user_id,dt.owner_role,u.name AS issued_name,p.full_name AS patient_name,p.cpf AS patient_cpf,p.birth_date AS patient_birth_date FROM pi_documents d LEFT JOIN pi_document_templates dt ON dt.id=d.template_id AND dt.clinic_id=d.clinic_id LEFT JOIN pi_users u ON u.id=d.issued_by LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id AND pl.clinic_id=d.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE d.id=? AND d.clinic_id=? LIMIT 1",
            [$docId, (int) ($c["clinic_id"] ?? 0)],
        );
        return $doc && document_can_access($c, $doc) ? $doc : null;
    
    }

    public static function page_document_view(): void
    
    {
    
        $c = need_login();
        if (($c["scope"] ?? "") !== "clinic") {
            throw new ProntooHttpError(
                403,
                "Documento disponível apenas no contexto do consultório.",
            );
        }
        $doc = fetch_document_for_current_user($c, (int) ($_GET["id"] ?? 0));
        if (!$doc) {
            http_response_code(404);
            page(
                "Documento",
                '<div class="empty">Documento não encontrado para esta credencial.</div>',
            );
            return;
        }
        $types = document_type_options();
        audit("documento_visualizado", "documento", (int) $doc["id"], [
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
                ? href("patient", ["id" => (int) $doc["patient_link_id"]])
                : href("documents", ["doc" => (int) $doc["id"]]);
        $actions =
            '<a class="ghost small" href="' .
            $back .
            '">Voltar</a><a class="primary small" target="_blank" rel="noopener" href="' .
            href("document_print", ["id" => (int) $doc["id"]]) .
            '">' .
            icon("print") .
            " Imprimir</a>" .
            document_pdf_link((int) $doc["id"]);
        $body =
            page_head(
                "Documento emitido",
                "Visualização do conteúdo gerado a partir do modelo aprovado.",
                $actions,
            ) .
            '<section class="card doc-print-card"><div class="section-head"><div><h2>' .
            e($doc["title"] ?? "Documento") .
            "</h2><p>" .
            e(
                document_issue_meta_sentence(
                    $doc["issued_name"] ?? null,
                    $doc["patient_name"] ?? null,
                    $doc["issued_at"],
                    "emitido",
                ),
            ) .
            "</p></div></div>" .
            document_preview_page_html(
                document_body_to_html((string) $doc["content"]),
                $doc["document_identifier"] ?? null,
            ) .
            "</section>";
        page("Documento", $body);
    
    }

    public static function page_document_print(): void
    
    {
    
        $c = need_login();
        if (($c["scope"] ?? "") !== "clinic") {
            throw new ProntooHttpError(
                403,
                "Documento disponível apenas no contexto do consultório.",
            );
        }
        $doc = fetch_document_for_current_user($c, (int) ($_GET["id"] ?? 0));
        if (!$doc) {
            http_response_code(404);
            echo '<!doctype html><meta charset="utf-8"><title>Documento não encontrado</title><p>Documento não encontrado.</p>';
            return;
        }
        $types = document_type_options();
        audit("documento_impresso", "documento", (int) $doc["id"], [
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
        $content = document_body_to_html((string) $doc["content"]);
        echo document_print_document_shell_html($doc, true, "print");
    
    }

    public static function document_stage_actions(string $stage): string
    
    {
    
        $emit = href("documents", ["emit" => 1]);
        $models = href("documents", ["models" => 1]);
        $newModel = href("documents", ["new_model" => 1]);
        if ($stage === "models") {
            return '<span class="doc-page-actions"><a class="primary small" href="' .
                $newModel .
                '">' .
                icon("add_circle") .
                "<span>Novo modelo</span></a></span>";
        }
        if ($stage === "new_model" || $stage === "edit_model") {
            return '<span class="doc-page-actions"><a class="ghost small" href="' .
                $models .
                '">' .
                icon("arrow_back") .
                "<span>Modelos</span></a></span>";
        }
        return '<span class="doc-page-actions"><a class="primary small" href="' .
            $emit .
            '">' .
            icon("post_add") .
            "<span>Criar documento</span></a></span>";
    
    }
}
