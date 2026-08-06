<?php
declare(strict_types=1);
function lead_phone_digits(string $phone): string
{

    return substr(only_digits($phone), 0, 11);
}
function lead_cpf_br(string $cpf): string
{

    $d = only_digits($cpf);
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
function lead_stage_options(): array
{

    return [
        "em_aberto" => "Em Aberto",
        "aguarda_retorno" => "Aguarda retorno",
        "convertido" => "Convertido",
        "arquivado" => "Arquivado",
    ];
}
function lead_stage_icons(): array
{

    return [
        "em_aberto" => "chat_bubble",
        "aguarda_retorno" => "schedule",
        "convertido" => "person_add",
        "arquivado" => "archive",
    ];
}
function lead_stage_normalize(?string $stage): string
{

    $candidate = mb_trim((string) $stage);
    return array_key_exists($candidate, lead_stage_options())
        ? $candidate
        : "em_aberto";
}

function lead_active_stages(): array
{

    return ["em_aberto", "aguarda_retorno"];
}
function lead_active_stage_sql(string $column = "stage"): string
{

    $column = preg_replace("/[^A-Za-z0-9_\.]+/", "", $column) ?: "stage";
    $active = array_map(
        static  fn($s) => "'" . str_replace("'", "''", $s) . "'",
        lead_active_stages(),
    );
    return "(" .
        lead_stage_sql_case($column) .
        ") IN (" .
        implode(",", $active) .
        ")";
}
function lead_stage_sql_case(string $column = "stage"): string
{

    return preg_replace("/[^A-Za-z0-9_\.]+/", "", $column) ?: "stage";
}

function lead_find_by_phone(
    int $cid,
    string $phoneDigits,
    int $excludeId = 0,
): ?array {

    $phoneDigits = lead_phone_digits($phoneDigits);
    if ($cid <= 0 || $phoneDigits === "") {
        return null;
    }
    ensure_lead_events_schema();
    $where =
        "clinic_id=? AND (phone_digits=? OR LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),'(',''),')',''),' ',''),'-',''),'.',''),11)=?)";
    $params = [$cid, $phoneDigits, $phoneDigits];
    if ($excludeId > 0) {
        $where .= " AND id<>?";
        $params[] = $excludeId;
    }
    return one(
        "SELECT id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes,created_by,created_at,updated_at FROM pi_leads WHERE $where ORDER BY CASE WHEN " .
            lead_active_stage_sql("stage") .
            " THEN 0 WHEN stage='arquivado' THEN 1 ELSE 2 END, COALESCE(updated_at,created_at) DESC, id DESC LIMIT 1",
        $params,
    ) ?:
        null;
}
function lead_event_create(
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
): void {

    if ($cid <= 0 || $leadId <= 0) {
        return;
    }
    ensure_lead_events_schema();
    $eventType = preg_replace("/[^a-z0-9_\-]/i", "", $eventType) ?: "contato";
    $stageFrom =
        trim($stageFrom) !== "" ? lead_stage_normalize($stageFrom) : "";
    $stageTo = trim($stageTo) !== "" ? lead_stage_normalize($stageTo) : "";
    $body = trim($body);
    if ($body === "") {
        $body =
            $eventType === "cadastro"
                ? "Contato inicial registrado sem observação adicional."
                : "Contato registrado sem observação adicional.";
    }
    q(
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
function lead_prepare_person_for_patient(
    array $lead,
    array $data,
    int $cid,
): int {

    $leadId = (int) ($lead["id"] ?? 0);
    $name = mb_trim((string) ($data["name"] ?? ($lead["name"] ?? "")));
    $cpf = only_digits((string) ($data["cpf"] ?? ""));
    $birth = mb_trim((string) ($data["birth_date"] ?? ""));
    if ($name === "") {
        throw new RuntimeException(
            "Informe o nome do interessado antes de tornar paciente.",
        );
    }
    if (!valid_cpf($cpf)) {
        throw new RuntimeException(
            "Informe um CPF válido para tornar paciente.",
        );
    }
    if (!valid_birth_date($birth)) {
        throw new RuntimeException(
            "Informe a data de nascimento para tornar paciente.",
        );
    }
    $pid = (int) ($lead["person_id"] ?? 0);
    $existingByCpf =
        (int) (val("SELECT id FROM pi_persons WHERE cpf=? LIMIT 1", [$cpf]) ??
            0);
    if ($existingByCpf > 0 && $existingByCpf !== $pid) {
        $identity = person_identity_immutable_values(
            $existingByCpf,
            $cpf,
            $birth,
            true,
        );
        $birth = (string) $identity["birth_date"];
        $sig = person_signature_value($cpf, $name, $birth);
        q(
            "UPDATE pi_persons SET full_name=COALESCE(NULLIF(full_name,''),?), assinatura=COALESCE(NULLIF(assinatura,''),?), clinic_id=COALESCE(clinic_id,?), updated_at=NOW() WHERE id=?",
            [$name, $sig, $cid, $existingByCpf],
        );
        if ($leadId > 0) {
            q(
                "UPDATE pi_leads SET person_id=?, name=COALESCE(NULLIF(name,''),?), updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$existingByCpf, $name, $leadId, $cid],
            );
        }
        return $existingByCpf;
    }
    if ($pid > 0) {
        $identity = person_identity_immutable_values($pid, $cpf, $birth, true);
        $cpf = (string) $identity["cpf"];
        $birth = (string) $identity["birth_date"];
        $sig = person_signature_value($cpf, $name, $birth);
        q(
            "UPDATE pi_persons SET full_name=COALESCE(NULLIF(full_name,''),?), cpf=?, birth_date=?, assinatura=COALESCE(NULLIF(assinatura,''),?), clinic_id=COALESCE(clinic_id,?), updated_at=NOW() WHERE id=?",
            [$name, $cpf, $birth, $sig, $cid, $pid],
        );
        return $pid;
    }
    $pid = upsert_person($name, $cpf, $birth);
    if ($leadId > 0) {
        q(
            "UPDATE pi_leads SET person_id=?, name=COALESCE(NULLIF(name,''),?), updated_at=NOW() WHERE id=? AND clinic_id=?",
            [$pid, $name, $leadId, $cid],
        );
    }
    return $pid;
}
function lead_patient_by_cpf(int $cid, string $cpf): ?array
{

    $cpf = only_digits($cpf);
    if ($cid <= 0 || !valid_cpf($cpf)) {
        return null;
    }
    $row = one(
        "SELECT pat.id patient_id, pat.person_id, per.full_name, per.cpf, per.birth_date FROM pi_patients pat JOIN pi_persons per ON per.id=pat.person_id WHERE pat.clinic_id=? AND pat.active=1 AND per.cpf=? LIMIT 1",
        [$cid, $cpf],
    );
    return $row ?: null;
}
function lead_patient_by_phone(int $cid, string $phoneDigits): ?array
{

    $phoneDigits = lead_phone_digits($phoneDigits);
    if ($cid <= 0 || strlen($phoneDigits) < 10) {
        return null;
    }
    $row = one(
        "SELECT pat.id patient_id, pat.person_id, pat.phone, per.full_name, per.cpf, per.birth_date FROM pi_patients pat JOIN pi_persons per ON per.id=pat.person_id WHERE pat.clinic_id=? AND pat.active=1 AND LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(pat.phone,''),'(',''),')',''),' ',''),'-',''),'.',''),11)=? LIMIT 1",
        [$cid, $phoneDigits],
    );
    return $row ?: null;
}
function lead_history_html(
    array $items,
    array $users,
    array $stageLabels,
): string {

    if (!$items) {
        return '<div class="lead-history lead-history-compact empty-history"><span>' .
            icon("history") .
            "</span><small>Sem ocorrências registradas.</small></div>";
    }
    $html =
        '<div class="lead-history lead-history-compact" aria-label="Histórico de ocorrências">';
    foreach ($items as $ev) {
        $who = first_name(
            $users[(int) ($ev["created_by"] ?? 0)]["name"] ?? "Sistema",
        );
        $when = dt_notice_br((string) ($ev["created_at"] ?? ""));
        $type = (string) ($ev["event_type"] ?? "contato");
        $body = mb_trim((string) ($ev["body"] ?? ""));
        if ($body === "") {
            $body = "Sem observações.";
        }
        $bodyShort =
            e(mb_substr($body, 0, 220)) . (mb_strlen($body) > 220 ? "…" : "");
        $summary = "Falou com " . $who . " em " . $when . ".";
        $html .=
            "<article><span>" .
            icon($type === "cadastro" ? "person_add" : "forum") .
            "</span><div><b>" .
            e($summary) .
            "</b><p>" .
            $bodyShort .
            "</p></div></article>";
    }
    return $html . "</div>";
}
function page_lead_lookup(): void
{

    $c = require_can("leads");
    $cid = (int) $c["clinic_id"];
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    if (security_rate_limit(security_client_bucket("lead_lookup"), 40, 300)) {
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
    $phoneDigits = lead_phone_digits((string) ($_GET["phone"] ?? ""));
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
    $lead = lead_find_by_phone($cid, $phoneDigits);
    if (!$lead) {
        $patient = lead_patient_by_phone($cid, $phoneDigits);
        if ($patient) {
            echo json_encode(
                [
                    "ok" => true,
                    "found" => true,
                    "patient_found" => true,
                    "already_patient" => true,
                    "patient_id" => (int) $patient["patient_id"],
                    "open_url" => href("patient", [
                        "id" => (int) $patient["patient_id"],
                    ]),
                    "message" =>
                        "Paciente já cadastrado localizado pelo telefone. Abra a ficha para registrar o atendimento.",
                    "name" => (string) ($patient["full_name"] ?? ""),
                    "phone" =>
                        (string) ($patient["phone"] ?? phone_br($phoneDigits)),
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
        $next = app_db_utc_to_local_input(
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
            "phone" => (string) ($lead["phone"] ?? phone_br($phoneDigits)),
            "source" => (string) ($lead["source"] ?? ""),
            "interest" => (string) ($lead["interest"] ?? ""),
            "stage" => lead_stage_normalize(
                (string) ($lead["stage"] ?? "em_aberto"),
            ),
            "next_action_at" => $next,
            "notes" => (string) ($lead["notes"] ?? ""),
        ],
        JSON_UNESCAPED_UNICODE,
    );
}
function page_lead_patient_lookup(): void
{

    $c = require_can("leads");
    $cid = (int) $c["clinic_id"];
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    if (
        security_rate_limit(
            security_client_bucket("lead_patient_lookup"),
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
    $cpf = only_digits((string) ($_GET["cpf"] ?? ""));
    if (!valid_cpf($cpf)) {
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
    $person = one(
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
    $patient = one(
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
                ? href("patient", ["id" => (int) $patient["id"]])
                : "",
            "message" => $patient
                ? "Este interessado já era paciente. Ao concluir, o interesse será arquivado."
                : "Dados encontrados e preenchidos automaticamente.",
            "name" => (string) ($person["full_name"] ?? ""),
            "cpf" => lead_cpf_br((string) ($person["cpf"] ?? "")),
            "birth_date" => app_date_input_from_storage(
                $person["birth_date"] ?? "",
            ),
        ],
        JSON_UNESCAPED_UNICODE,
    );
}
function page_leads(): void
{

    $c = require_can("leads");
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    ensure_lead_events_schema();
    $stageOptions = lead_stage_options();
    $editableStageOptions = [
        "em_aberto" => "Em Aberto",
        "aguarda_retorno" => "Aguarda retorno",
        "arquivado" => "Arquivado",
    ];
    $stageLabels = $stageOptions;
    $stageClass = function (string $stage): string {

        $stage = lead_stage_normalize($stage);
        return preg_replace("/[^a-z0-9_-]+/i", "-", $stage) ?: "em_aberto";
    };
    $leadTime = function (?string $v) use ($cid, $c): string {

        $v = mb_trim((string) $v);
        if ($v === "") {
            return "";
        }
        return app_local_to_db_utc($v, $cid, $c);
    };
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "save");
        if ($act === "convert") {
            $leadId = (int) ($_POST["lead_id"] ?? 0);
            $lead = one(
                "SELECT id,person_id,name,phone,notes,stage FROM pi_leads WHERE id=? AND clinic_id=?",
                [$leadId, $cid],
            );
            if (!$lead) {
                flash("Interessado não encontrado.", "bad");
                redirect("leads");
            }
            try {
                $postedCpf = only_digits((string) ($_POST["cpf"] ?? ""));
                $existingPatient =
                    $postedCpf !== ""
                        ? lead_patient_by_cpf($cid, $postedCpf)
                        : null;
                $leadPersonId = (int) ($lead["person_id"] ?? 0);
                if (
                    $existingPatient &&
                    (int) ($existingPatient["person_id"] ?? 0) !== $leadPersonId
                ) {
                    $oldStage = lead_stage_normalize(
                        (string) ($lead["stage"] ?? "em_aberto"),
                    );
                    q(
                        "UPDATE pi_leads SET stage='arquivado', updated_at=NOW() WHERE id=? AND clinic_id=?",
                        [$leadId, $cid],
                    );
                    lead_event_create(
                        $cid,
                        $leadId,
                        $uid,
                        $oldStage,
                        "arquivado",
                        phone_br((string) ($lead["phone"] ?? "")),
                        "",
                        "",
                        null,
                        "Interessado arquivado automaticamente porque o CPF informado já pertencia a paciente ativo.",
                        "arquivamento",
                    );
                    audit("lead_arquivado", "lead", $leadId, [
                        "audit_body" =>
                            "Interessado arquivado automaticamente ao tentar tornar paciente com CPF já cadastrado como paciente.",
                        "patient_id" =>
                            (int) ($existingPatient["patient_id"] ?? 0),
                    ]);
                    flash(
                        "Este interessado já era paciente. Seu Interesse foi Arquivado. Abra a Ficha do Paciente para Continuar.",
                        "warn",
                    );
                    $existingPatientId =
                        (int) ($existingPatient["patient_id"] ?? 0);
                    if ($existingPatientId > 0) {
                        redirect("patient", ["id" => $existingPatientId]);
                    }
                    redirect("leads", ["status" => "arquivado"]);
                }
                $pid = lead_prepare_person_for_patient($lead, $_POST, $cid);
                q(
                    "INSERT INTO pi_patients (clinic_id,person_id,phone,notes,created_by,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE phone=COALESCE(NULLIF(VALUES(phone),''),phone), notes=COALESCE(NULLIF(VALUES(notes),''),notes), active=1, updated_at=NOW()",
                    [
                        $cid,
                        $pid,
                        phone_br((string) ($lead["phone"] ?? "")),
                        mb_trim((string) ($lead["notes"] ?? "")),
                        $uid,
                    ],
                );
                $patientId = (int) val(
                    "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? LIMIT 1",
                    [$cid, $pid],
                );
                q(
                    "UPDATE pi_leads SET person_id=?, stage='convertido', updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [$pid, $leadId, $cid],
                );
                lead_event_create(
                    $cid,
                    $leadId,
                    $uid,
                    lead_stage_normalize(
                        (string) ($lead["stage"] ?? "em_aberto"),
                    ),
                    "convertido",
                    phone_br((string) ($lead["phone"] ?? "")),
                    "",
                    "",
                    null,
                    "Interessado convertido em paciente.",
                    "conversao",
                );
                counter_inc("patient_writes_total");
                clinic_metric_inc($cid, "patient_writes");
                audit("paciente_salvo", "paciente", $patientId ?: $pid, [
                    "origem" => "interessado",
                    "lead_id" => $leadId,
                    "audit_body" =>
                        "Interessado tornado paciente após conferência dos dados pendentes.",
                ]);
                flash("Interessado tornado paciente.");
                if ($patientId > 0) {
                    redirect("patient", ["id" => $patientId]);
                }
            } catch (Throwable $e) {
                error_log("[Prontoo convert interested] " . $e->getMessage());
                flash(
                    $e->getMessage() ?:
                    "Não foi possível tornar o interessado paciente.",
                    "bad",
                );
            }
            redirect("leads");
        }
        if ($act === "archive_lead") {
            $leadId = (int) ($_POST["lead_id"] ?? 0);
            $lead = one(
                "SELECT id,name,phone,source,interest,stage,next_action_at FROM pi_leads WHERE id=? AND clinic_id=?",
                [$leadId, $cid],
            );
            if (!$lead) {
                flash("Interessado não encontrado.", "bad");
                redirect("leads");
            }
            $oldStage = lead_stage_normalize(
                (string) ($lead["stage"] ?? "em_aberto"),
            );
            if ($oldStage === "convertido") {
                flash("Interessado já convertido em paciente.", "warn");
                redirect("leads", ["status" => "convertido"]);
            }
            q(
                "UPDATE pi_leads SET stage='arquivado', updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$leadId, $cid],
            );
            lead_event_create(
                $cid,
                $leadId,
                $uid,
                $oldStage,
                "arquivado",
                phone_br((string) ($lead["phone"] ?? "")),
                (string) ($lead["source"] ?? ""),
                (string) ($lead["interest"] ?? ""),
                !empty($lead["next_action_at"])
                    ? (string) $lead["next_action_at"]
                    : null,
                "Interessado arquivado porque o CPF informado já pertence a um paciente ativo.",
                "arquivamento",
            );
            audit("lead_arquivado", "lead", $leadId, [
                "audit_body" =>
                    "Interessado arquivado após tentativa de tornar paciente com CPF já cadastrado como paciente.",
            ]);
            flash("Interessado arquivado.");
            redirect("leads", ["status" => "arquivado"]);
        }
        if ($act === "update") {
            $leadId = (int) ($_POST["lead_id"] ?? 0);
            $lead = one(
                "SELECT id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes FROM pi_leads WHERE id=? AND clinic_id=?",
                [$leadId, $cid],
            );
            if (!$lead) {
                flash("Interessado não encontrado.", "bad");
                redirect("leads");
            }
            $stage = lead_stage_normalize(
                (string) ($_POST["stage"] ?? "" ?: "em_aberto"),
            );
            if (!isset($editableStageOptions[$stage])) {
                flash("Etapa do interessado inválida.", "bad");
                redirect("leads");
            }
            $postedPhone = lead_phone_digits((string) ($_POST["phone"] ?? ""));
            $currentPhone = lead_phone_digits(
                (string) ($lead["phone_digits"] ?? ($lead["phone"] ?? "")),
            );
            if (
                $postedPhone !== "" &&
                $currentPhone !== "" &&
                $postedPhone !== $currentPhone
            ) {
                flash(
                    "Telefone é dado imutável do interessado e não pode ser alterado.",
                    "bad",
                );
                redirect("leads");
            }
            $name = mb_trim((string) ($_POST["name"] ?? ($lead["name"] ?? "")));
            if ($name === "") {
                $name = (string) ($lead["name"] ?? "Interessado");
            }
            $phone = phone_br((string) ($lead["phone"] ?? ""));
            $source = mb_trim((string) ($_POST["source"] ?? ""));
            $interest = mb_trim((string) ($_POST["interest"] ?? ""));
            $next =
                $leadTime((string) ($_POST["next_action_at"] ?? "")) ?: null;
            $notes = mb_trim((string) ($_POST["notes"] ?? ""));
            q(
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
                lead_event_create(
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
            audit("lead_atualizado", "lead", $leadId, [
                "stage" => $stage,
                "audit_body" => isset($_POST["contact_event"])
                    ? "Contato registrado no histórico do interessado."
                    : "Interessado atualizado pela tela operacional de Interessados.",
            ]);
            flash(
                $stage === "arquivado"
                    ? "Interessado arquivado."
                    : "Interessado atualizado.",
            );
            redirect("leads", ["status" => $stage]);
        }
        if ($act === "save") {
            $phoneDigits = lead_phone_digits((string) ($_POST["phone"] ?? ""));
            if (strlen($phoneDigits) < 10) {
                flash(
                    "Informe o telefone do interessado para iniciar ou continuar o histórico.",
                    "bad",
                );
                redirect("leads");
            }
            $name = mb_trim((string) ($_POST["name"] ?? ""));
            $cpfOmitted = isset($_POST["cpf_omitted"]);
            $birthOmitted = isset($_POST["birth_omitted"]);
            $cpf = $cpfOmitted
                ? ""
                : only_digits((string) ($_POST["cpf"] ?? ""));
            $birth = $birthOmitted
                ? ""
                : mb_trim((string) ($_POST["birth_date"] ?? ""));
            if (!$cpfOmitted && $cpf !== "" && !valid_cpf($cpf)) {
                flash("Este CPF não existe.", "bad");
                redirect("leads");
            }
            if (!$birthOmitted && $birth !== "" && !valid_birth_date($birth)) {
                flash(
                    "Informe nascimento válido ou marque Não quis informar.",
                    "bad",
                );
                redirect("leads");
            }
            $stage = lead_stage_normalize(
                (string) ($_POST["stage"] ?? "" ?: "em_aberto"),
            );
            if (!in_array($stage, ["em_aberto", "aguarda_retorno"], true)) {
                flash("Etapa inicial do interessado inválida.", "bad");
                redirect("leads");
            }
            try {
                $existing = lead_find_by_phone($cid, $phoneDigits);
                $next =
                    $leadTime((string) ($_POST["next_action_at"] ?? "")) ?:
                    null;
                $source = mb_trim((string) ($_POST["source"] ?? ""));
                $interest = mb_trim((string) ($_POST["interest"] ?? ""));
                $notes = mb_trim((string) ($_POST["notes"] ?? ""));
                $patientByPhone = !$existing
                    ? lead_patient_by_phone($cid, $phoneDigits)
                    : null;
                if ($patientByPhone) {
                    audit(
                        "paciente_localizado_por_telefone",
                        "paciente",
                        (int) $patientByPhone["patient_id"],
                        [
                            "audit_body" =>
                                "Recepção tentou registrar contato por telefone e o Prontoo localizou ficha de paciente já cadastrada.",
                        ],
                    );
                    flash(
                        "Paciente já cadastrado localizado pelo telefone. Abra a ficha para registrar o atendimento.",
                        "warn",
                    );
                    redirect("patient", [
                        "id" => (int) $patientByPhone["patient_id"],
                    ]);
                }
                if ($existing) {
                    $leadId = (int) $existing["id"];
                    $oldStage = lead_stage_normalize(
                        (string) ($existing["stage"] ?? "em_aberto"),
                    );
                    if ($name === "") {
                        $name = (string) ($existing["name"] ?? "Interessado");
                    }
                    $pid = (int) ($existing["person_id"] ?? 0);
                    if ($pid <= 0 || $cpf !== "" || $birth !== "") {
                        $pid = save_person_flexible(
                            $name,
                            $cpf !== "" ? $cpf : null,
                            $birth !== "" ? $birth : null,
                        );
                    }
                    $stageTo =
                        $oldStage === "convertido"
                            ? "convertido"
                            : lead_stage_normalize($stage);
                    q(
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
                    lead_event_create(
                        $cid,
                        $leadId,
                        $uid,
                        $oldStage,
                        $stageTo,
                        phone_br($phoneDigits),
                        $source,
                        $interest,
                        $next,
                        $notes,
                        "contato",
                    );
                    audit("lead_atualizado", "lead", $leadId, [
                        "stage" => $stageTo,
                        "audit_body" =>
                            "Novo contato recebido pelo telefone imutável do interessado; ocorrência adicionada ao histórico.",
                    ]);
                    flash(
                        "Telefone já cadastrado. O histórico do interessado foi atualizado.",
                    );
                    redirect("leads", ["status" => $stageTo]);
                }
                if ($name === "") {
                    flash("Informe o nome completo do interessado.", "bad");
                    redirect("leads");
                }
                $pid = save_person_flexible(
                    $name,
                    $cpf !== "" ? $cpf : null,
                    $birth !== "" ? $birth : null,
                );
                if (
                    $cpf !== "" &&
                    (int) val(
                        "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND person_id=? AND " .
                            lead_active_stage_sql("stage"),
                        [$cid, $pid],
                    ) > 0
                ) {
                    flash(
                        "Já existe interessado ativo com o mesmo CPF nesta clínica. Atualize o registro existente em vez de duplicar.",
                        "bad",
                    );
                    redirect("leads");
                }
                q(
                    "INSERT INTO pi_leads (clinic_id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
                    [
                        $cid,
                        $pid,
                        $name,
                        phone_br($phoneDigits),
                        $phoneDigits,
                        $source,
                        $interest,
                        $stage,
                        $next,
                        $notes,
                        $uid,
                    ],
                );
                $leadId = db_last_insert_id();
                lead_event_create(
                    $cid,
                    $leadId,
                    $uid,
                    "",
                    $stage,
                    phone_br($phoneDigits),
                    $source,
                    $interest,
                    $next,
                    $notes,
                    "cadastro",
                );
                counter_inc("leads_total");
                clinic_metric_inc($cid, "leads");
                audit("lead_criado", "lead", $leadId, $_POST);
                flash("Interessado salvo.");
            } catch (Throwable $e) {
                error_log("[Prontoo interested save] " . $e->getMessage());
                flash(
                    "Não foi possível salvar o interessado. Revise os dados informados.",
                    "bad",
                );
            }
            redirect("leads");
        }
    }
    $statusRaw = (string) ($_GET["status"] ?? "em_aberto");
    $status = lead_stage_normalize($statusRaw);
    $qTerm = mb_trim((string) ($_GET["q"] ?? ""));
    $leadSearchMode = $qTerm !== "";
    $where = "clinic_id=?";
    $params = [$cid];
    if (!$leadSearchMode) {
        if (isset($stageOptions[$status])) {
            $where .= " AND " . lead_stage_sql_case("stage") . "=?";
            $params[] = $status;
        } else {
            $status = "em_aberto";
            $where .= " AND " . lead_stage_sql_case("stage") . "=?";
            $params[] = $status;
        }
    } else {
        if (!isset($stageOptions[$status])) {
            $status = "em_aberto";
        }
    }
    if ($leadSearchMode) {
        $like = "%" . $qTerm . "%";
        $digits = only_digits($qTerm);
        $where .=
            " AND (name LIKE ? OR phone LIKE ? OR phone_digits LIKE ? OR source LIKE ? OR interest LIKE ?)";
        $params[] = $like;
        $params[] = $digits !== "" ? "%" . $digits . "%" : $like;
        $params[] = $digits !== "" ? "%" . $digits . "%" : $like;
        $params[] = $like;
        $params[] = $like;
    }
    $rows = q(
        "SELECT id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes,created_at,updated_at FROM pi_leads WHERE $where ORDER BY CASE WHEN stage='convertido' THEN 5 WHEN stage='arquivado' THEN 6 WHEN next_action_at IS NOT NULL AND next_action_at<NOW() THEN 0 WHEN next_action_at IS NOT NULL THEN 1 ELSE 2 END, COALESCE(next_action_at,created_at) ASC, id DESC LIMIT 140",
        $params,
    )->fetchAll();
    $allCounts = q(
        "SELECT " .
            lead_stage_sql_case("stage") .
            " AS stage,COUNT(*) total FROM pi_leads WHERE clinic_id=? GROUP BY " .
            lead_stage_sql_case("stage"),
        [$cid],
    )->fetchAll();
    $counts = [];
    foreach ($allCounts as $cr) {
        $st = lead_stage_normalize((string) $cr["stage"]);
        $counts[$st] = ($counts[$st] ?? 0) + (int) $cr["total"];
    }
    $activeTotal =
        (int) (val(
            "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                lead_active_stage_sql("stage"),
            [$cid],
        ) ?? 0);
    $newLeads =
        (int) (val(
            "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                lead_active_stage_sql("stage") .
                " AND created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$cid],
        ) ?? 0);
    [$leadTodayStart, $leadTodayEnd] = app_local_day_utc_range(
        app_today_in_timezone($cid, $c),
        $cid,
        $c,
    );
    $todayReturns =
        (int) (val(
            "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                lead_active_stage_sql("stage") .
                " AND next_action_at>=? AND next_action_at<?",
            [$cid, $leadTodayStart, $leadTodayEnd],
        ) ?? 0);
    $lateReturns =
        (int) (val(
            "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                lead_active_stage_sql("stage") .
                " AND next_action_at IS NOT NULL AND next_action_at<NOW()",
            [$cid],
        ) ?? 0);
    $persons = fetch_map(
        "pi_persons",
        int_ids($rows, "person_id"),
        "id,full_name,cpf,birth_date",
    );
    $patientByPerson = [];
    $personIds = int_ids($rows, "person_id");
    if ($personIds) {
        $ph = implode(",", array_fill(0, count($personIds), "?"));
        $prs = q(
            "SELECT id,person_id FROM pi_patients WHERE clinic_id=? AND person_id IN ($ph) AND active=1 LIMIT 160",
            array_merge([$cid], $personIds),
        )->fetchAll();
        foreach ($prs as $pr) {
            $patientByPerson[(int) $pr["person_id"]] = (int) $pr["id"];
        }
    }
    $leadEvents = [];
    $leadEventUsers = [];
    $leadIds = int_ids($rows, "id");
    if ($leadIds) {
        $ph = implode(",", array_fill(0, count($leadIds), "?"));
        $evs = q(
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
            $leadEventUsers = fetch_map(
                "pi_users",
                array_values($uids),
                "id,name",
            );
        }
    }
    $cpfField =
        '<div class="optional-field">' .
        form_row(
            "CPF",
            input(
                "cpf",
                "text",
                "",
                'inputmode="numeric" maxlength="14" data-cpf-mask',
            ),
        ) .
        '<label class="check"><input type="checkbox" name="cpf_omitted" value="1" data-omit-field="cpf" checked> Não quis informar agora</label></div>';
    $birthField =
        '<div class="optional-field">' .
        form_row("Nascimento", input("birth_date", "date", "")) .
        '<label class="check"><input type="checkbox" name="birth_omitted" value="1" data-omit-field="birth_date" checked> Não quis informar agora</label></div>';
    $newFormActions =
        '<div class="form-actions"><a class="ghost" href="' .
        href("leads") .
        '">' .
        icon("arrow_back") .
        '<span>Voltar</span></a><button type="submit" class="primary">' .
        icon("save") .
        "<span>Salvar interessado</span></button></div>";
    $leadCreateIntro =
        '<section class="lead-ds-intro" aria-label="Resumo do cadastro de interessado"><span class="lead-ds-intro-icon">' .
        icon("person_search") .
        "</span><div><strong>Cadastro inicial do interessado</strong><small>Registre o contato com clareza, mantenha o histórico e converta para paciente somente quando os dados estiverem prontos.</small></div></section>";
    $leadPrimaryOpen =
        '<section class="lead-ds-form-section"><div class="lead-ds-section-head"><span>' .
        icon("call") .
        '</span><div><strong>Contato e interesse</strong><small>Telefone, nome e interesse formam o cadastro mínimo para acompanhamento.</small></div></div><div class="lead-ds-form-grid">';
    $leadExtraOpen =
        '<section class="lead-ds-form-section lead-ds-form-section-extra"><div class="lead-ds-section-head"><span>' .
        icon("assignment_ind") .
        '</span><div><strong>Informações complementares</strong><small>Dados opcionais ajudam na conversão futura, sem bloquear o primeiro registro.</small></div></div><div class="lead-form-extra lead-form-extra-open lead-ds-extra-grid"><div class="two">';
    $newForm =
        '<form method="post" action="' .
        href("leads") .
        '" class="compact lead-create-form lead-create-page-form lead-ds-create-form" data-lead-create-form>' .
        csrf_field() .
        '<input type="hidden" name="act" value="save">' .
        $leadCreateIntro .
        $leadPrimaryOpen .
        form_row(
            "Telefone / WhatsApp",
            input(
                "phone",
                "text",
                "",
                'required autocomplete="tel" inputmode="tel" data-lead-phone-lookup="' .
                    e(href("lead_lookup")) .
                    '"',
            ),
        ) .
        '<div class="lead-lookup-status" data-lead-lookup-status aria-live="polite"></div>' .
        form_row(
            "Nome completo",
            input(
                "name",
                "text",
                "",
                'required autocomplete="name" list="prontoo_person_suggestions" data-person-autosuggest',
            ),
        ) .
        form_row(
            "Interesse principal",
            input(
                "interest",
                "text",
                "",
                'placeholder="Ex.: consulta, retorno, avaliação"',
            ),
        ) .
        select_label(
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
        form_row(
            "Origem do contato",
            input(
                "source",
                "text",
                "",
                'placeholder="WhatsApp, indicação, Instagram..."',
            ),
        ) .
        form_row("Próximo contato", input("next_action_at", "datetime-local")) .
        form_row("Observação inicial", textarea("notes", "", 'rows="4"')) .
        "</div></section>" .
        person_autosuggest_datalist($cid) .
        $newFormActions .
        "</form>";
    if (isset($_GET["new"]) && (string) $_GET["new"] !== "0") {
        $newLeadHeadAction =
            '<a class="ghost small cmdlike" href="' .
            href("leads") .
            '">' .
            action_summary_label("Interessados", "arrow_back") .
            "</a>";
        page(
            "Novo Interessado",
            page_head(
                "Novo Interessado",
                "Cadastro inicial para acompanhar contatos antes da conversão em paciente.",
                $newLeadHeadAction,
            ) . card($newForm, "lead-new-screen-card lead-ds-new-card"),
        );
        return;
    }
    $headAction =
        '<a class="primary small cmdlike" href="' .
        href("leads", ["new" => 1]) .
        '">' .
        action_summary_label("Novo Interessado", "person_add") .
        "</a>";
    $stats =
        '<section class="lead-overview kpis kpi-info-strip" aria-label="Resumo de interessados"><article class="kpi-card">' .
        icon("person_search") .
        "<p><b>" .
        (int) $newLeads .
        '</b><span>Novos Interessados (30 dias)</span></p></article><article class="kpi-card">' .
        icon("hourglass_top") .
        "<p><b>" .
        (int) ($counts["aguarda_retorno"] ?? 0) .
        '</b><span>Aguarda retorno</span></p></article><article class="kpi-card">' .
        icon("today") .
        "<p><b>" .
        (int) $todayReturns .
        "</b><span>Retornos hoje</span></p></article></section>";
    $floatingLate =
        $lateReturns > 0
            ? '<aside class="lead-floating-pending agenda-floating-kpis ds-fixed-agenda-kpis" data-ds-fixed-layer aria-label="Pendências de interessados"><a class="agenda-floating-card agenda-floating-card-overdue" href="' .
                href("leads", ["status" => "aguarda_retorno"]) .
                '">' .
                icon("event_busy") .
                '<span class="agenda-floating-copy"><b>' .
                (int) $lateReturns .
                "</b><span>Retornos vencidos</span></span></a></aside>"
            : "";
    $chips = $stageOptions;
    $chipIcons = lead_stage_icons();
    $chipHtml =
        '<nav class="patient-filter-chips lead-filter-chips ds-selection-chips" aria-label="Filtros de interessados por etapa">';
    if ($qTerm !== "") {
        $chipHtml .=
            '<span class="patient-filter-chip ds-filter-chip active is-active patient-filter-found" aria-current="page" data-ds-filter-chip>' .
            icon("manage_search") .
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
            href("leads", ["status" => $key]) .
            '" aria-label="' .
            e($label) .
            " — " .
            (int) $num .
            ' interessado(s)">' .
            icon($chipIcons[$key] ?? "label") .
            "<span>" .
            e($label) .
            "</span></a>";
    }
    $chipHtml .= "</nav>";
    $search =
        '<form method="get" class="lead-search patient-search-bar"><input type="hidden" name="r" value="leads"><input type="hidden" name="status" value="' .
        e($status) .
        '"><label class="search-field"><input type="search" name="q" value="' .
        e($qTerm) .
        '" placeholder="Nome, telefone, origem ou interesse" aria-label="Buscar interessado por nome, telefone, origem ou interesse"></label><button class="primary small" type="submit">' .
        icon("search") .
        "<span>Busca rápida</span></button>" .
        ($qTerm !== ""
            ? '<a class="ghost small" href="' .
                href("leads", ["status" => $status]) .
                '">' .
                icon("close") .
                "<span>Limpar</span></a>"
            : "") .
        "</form>";
    $convertLeadId = (int) ($_GET["convert_lead"] ?? 0);
    $convertCpf = only_digits((string) ($_GET["convert_cpf"] ?? ""));
    $convertExistingPatient =
        $convertCpf !== "" ? lead_patient_by_cpf($cid, $convertCpf) : null;
    $cards = "";
    foreach ($rows as $r) {
        $rawStage = (string) ($r["stage"] ?? "em_aberto");
        $normStage = lead_stage_normalize($rawStage);
        $stageLabel = $stageLabels[$normStage] ?? $normStage;
        $stageIcon = $chipIcons[$normStage] ?? "person_search";
        $ps = $persons[(int) ($r["person_id"] ?? 0)] ?? [];
        $cpfOk =
            !empty($ps["cpf"]) &&
            strlen(only_digits((string) $ps["cpf"])) === 11;
        $birthOk = !empty($ps["birth_date"]);
        $cpfTxt = $cpfOk ? "CPF " . mask($ps["cpf"]) : "CPF pendente";
        $birthTxt = $birthOk
            ? "Nascimento: " . date_br($ps["birth_date"])
            : "nascimento pendente";
        $patientId = $patientByPerson[(int) ($r["person_id"] ?? 0)] ?? 0;
        $nextRaw = (string) ($r["next_action_at"] ?? "");
        $isLate =
            $nextRaw !== "" &&
            app_storage_timestamp($nextRaw) < time() &&
            !in_array($normStage, ["convertido", "arquivado"], true);
        $nextTxt =
            $nextRaw !== ""
                ? "Próximo contato: " . dt_br($nextRaw)
                : "Sem retorno agendado";
        $lastTxt = !empty($r["updated_at"])
            ? "Atualizado em " . dt_br($r["updated_at"])
            : "Cadastrado em " . dt_br($r["created_at"]);
        $class =
            "lead-card stage-" .
            $stageClass($rawStage) .
            ($isLate ? " is-late" : "");
        $primaryAction = "";
        if ($patientId > 0) {
            $primaryAction =
                '<a class="ghost small" href="' .
                href("patient", ["id" => $patientId]) .
                '">' .
                icon("folder_open") .
                "<span>Abrir paciente</span></a>";
        } else {
            $cpfValue = $cpfOk ? mask((string) $ps["cpf"]) : "";
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
                            href("patient", ["id" => $patientLinkId]) .
                            '">' .
                            icon("folder_open") .
                            "<span>Abrir Ficha do Paciente</span></a>"
                        : "") .
                    '<form method="post" class="inline">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="archive_lead"><input type="hidden" name="lead_id" value="' .
                    (int) $r["id"] .
                    '"><button class="ghost small" type="submit">' .
                    icon("archive") .
                    "<span>Arquivar interessado</span></button></form></div></div>";
            }
            $primaryAction =
                '<details class="lead-card-details form-panel lead-convert-panel"' .
                $openAttr .
                '><summary class="primary small cmdlike">' .
                action_summary_label("Tornar Paciente", "person_add") .
                "</summary>" .
                $conflictHtml .
                '<form method="post" class="compact lead-convert-form" data-lead-convert-form>' .
                csrf_field() .
                '<input type="hidden" name="act" value="convert"><input type="hidden" name="lead_id" value="' .
                (int) $r["id"] .
                '"><p class="field-help lead-convert-help">' .
                e($convertHelp) .
                "</p>" .
                form_row(
                    "CPF",
                    input(
                        "cpf",
                        "text",
                        $cpfValue,
                        'required inputmode="numeric" maxlength="14" data-cpf-mask data-lead-patient-cpf-lookup="' .
                            e(href("lead_patient_lookup")) .
                            '" autocomplete="off"',
                    ),
                ) .
                '<div class="lead-convert-existing lead-convert-lookup-status" data-lead-convert-status aria-live="polite"></div><div class="two">' .
                form_row(
                    "Nome Completo",
                    input(
                        "name",
                        "text",
                        $r["name"],
                        'required autocomplete="name" data-lead-convert-name',
                    ),
                ) .
                form_row(
                    "Nascimento",
                    input(
                        "birth_date",
                        "date",
                        $birthValue,
                        "required data-lead-convert-birth",
                    ),
                ) .
                "</div>" .
                form_actions("Concluir") .
                "</form></details>";
        }
        $phoneDisplay =
            '<input type="text" value="' .
            e($r["phone"]) .
            '" readonly aria-readonly="true" inputmode="tel">';
        $touch =
            '<details class="lead-card-details"><summary class="ghost small cmdlike">' .
            action_summary_label("Registrar contato", "forum") .
            '</summary><form method="post" class="compact lead-touch-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="update"><input type="hidden" name="contact_event" value="1"><input type="hidden" name="lead_id" value="' .
            (int) $r["id"] .
            '">' .
            form_row("Nome", input("name", "text", $r["name"], "required")) .
            form_row("Telefone / WhatsApp", $phoneDisplay) .
            form_row("Interesse", input("interest", "text", $r["interest"])) .
            form_row("Origem", input("source", "text", $r["source"])) .
            select_label("Etapa", "stage", $editableStageOptions, $normStage) .
            form_row(
                "Próximo contato",
                input(
                    "next_action_at",
                    "datetime-local",
                    !empty($r["next_action_at"])
                        ? app_db_utc_to_local_input(
                            (string) $r["next_action_at"],
                            $cid,
                            $c,
                        )
                        : "",
                ),
            ) .
            form_row(
                "Ocorrência / observação do contato",
                textarea(
                    "notes",
                    "",
                    'rows="5" placeholder="Registre apenas o que aconteceu neste contato"',
                ),
            ) .
            '<small class="field-help lead-immutable-help">Telefone é identidade imutável do interessado.</small>' .
            form_actions("Atualizar interessado") .
            "</form></details>";
        $interestText =
            mb_trim((string) ($r["interest"] ?? "")) ?: "Interesse não informado";
        $phoneText = mb_trim((string) ($r["phone"] ?? "")) ?: "sem telefone";
        $sourceText = mb_trim((string) ($r["source"] ?? "")) ?: "sem origem";
        $leadName = mb_trim((string) ($r["name"] ?? "")) ?: "Interessado sem nome";
        $miniMeta =
            '<div class="lead-card-meta lead-card-mini-meta ds-person-meta"><span>' .
            icon("call") .
            "<span>" .
            e($phoneText) .
            "</span></span><span>" .
            icon("medical_information") .
            "<span>" .
            e($interestText) .
            "</span></span><span>" .
            icon("campaign") .
            "<span>" .
            e($sourceText) .
            "</span></span><span>" .
            icon($isLate ? "notification_important" : "event_upcoming") .
            "<span>" .
            e($nextTxt) .
            "</span></span><span>" .
            icon("update") .
            "<span>" .
            e($lastTxt) .
            "</span></span></div>";
        $compactMeta =
            '<div class="lead-card-meta lead-card-mini-meta"><span>' .
            icon("campaign") .
            "<span>" .
            e($sourceText) .
            "</span></span><span>" .
            icon("badge") .
            "<span>" .
            e($cpfTxt) .
            "</span></span><span>" .
            icon("cake") .
            "<span>" .
            e($birthTxt) .
            "</span></span></div>";
        $cards .=
            '<details class="lead-list-item ds-lead-item"><summary class="lead-list-summary ds-person-row ds-lead-row stage-' .
            e($stageClass($rawStage)) .
            ($isLate ? " is-late" : "") .
            '">' .
            '<span class="lead-card-avatar ds-person-avatar" aria-hidden="true">' .
            icon($stageIcon) .
            "</span>" .
            '<div class="lead-card-main ds-person-main"><div class="lead-card-title ds-person-title"><strong>' .
            e($leadName) .
            '</strong><span class="pill lead-status ds-status-pill stage-' .
            e($stageClass($rawStage)) .
            '">' .
            e($stageLabel) .
            "</span></div>" .
            $miniMeta .
            "</div>" .
            '<span class="lead-card-actions ds-person-actions lead-summary-actions"><span class="ghost small lead-open-btn">' .
            icon("expand_more") .
            "<span>Abrir</span></span></span>" .
            '</summary><article class="' .
            $class .
            ' lead-card-compact lead-card-expanded lead-card-occurrences-only">' .
            lead_history_html(
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
    page(
        "Interessados",
        page_head(
            "Interessados",
            "Pessoas que demonstraram interesse e ainda não foram convertidas em pacientes.",
            $headAction,
        ) .
            $floatingLate .
            $stats .
            card($search, "lead-search-card ds-search-card") .
            card($chipHtml . $list, "lead-screen-card ds-filter-list-block"),
    );
}
