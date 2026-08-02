<?php
declare(strict_types=1);
function mask(mixed $v): mixed
{

    if (is_array($v)) {
        $o = [];
        foreach ($v as $k => $x) {
            $kl = strtolower((string) $k);
            if (preg_match("/senha|password|csrf|token|hash|secret/i", $kl)) {
                $o[$k] = "***";
            } elseif (
                preg_match(
                    '/(^|_)(cpf|cnpj|legal_document|documento_legal)(_|$)/i',
                    $kl,
                )
            ) {
                $o[$k] = mask_document_value($x);
            } else {
                $o[$k] = mask($x);
            }
        }
        return $o;
    }
    $s = (string) $v;
    if (preg_match('/^\d{11}$/', $s)) {
        return substr($s, 0, 3) . ".***.***-" . substr($s, -2);
    }
    if (preg_match('/^\d{14}$/', $s)) {
        return substr($s, 0, 2) . ".***.***/****-" . substr($s, -2);
    }
    if (filter_var($s, FILTER_VALIDATE_EMAIL)) {
        return preg_replace('/(^.).*(@.*$)/', '$1***$2', $s);
    }
    return mb_strlen($s) > 800 ? mb_substr($s, 0, 800) . "…" : $s;
}
function mask_document_value(mixed $v): string
{

    $d = only_digits((string) $v);
    if (strlen($d) === 11) {
        return substr($d, 0, 3) . ".***.***-" . substr($d, -2);
    }
    if (strlen($d) === 14) {
        return substr($d, 0, 2) . ".***.***/****-" . substr($d, -2);
    }
    return $d !== "" ? "***" : mask($v);
}
function pt_list(array $items): string
{

    $items = array_values(
        array_filter(
            array_map(static  fn($v) => trim((string) $v), $items),
            static  fn($v) => $v !== "",
        ),
    );
    $n = count($items);
    if ($n === 0) {
        return "";
    }
    if ($n === 1) {
        return $items[0];
    }
    if ($n === 2) {
        return $items[0] . " e " . $items[1];
    }
    return implode(", ", array_slice($items, 0, -1)) . " e " . $items[$n - 1];
}
function audit_change_body(array $fields): string
{

    $fields = array_values(
        array_unique(
            array_filter(
                array_map(static  fn($v) => trim((string) $v), $fields),
                static  fn($v) => $v !== "",
            ),
        ),
    );
    if (!$fields) {
        return "Nenhuma informação foi alterada.";
    }
    if (count($fields) === 1) {
        return "O campo " . pt_list($fields) . " foi modificado.";
    }
    return "Os campos " . pt_list($fields) . " foram modificados.";
}
function audit_value_present(mixed $v): bool
{

    if (is_array($v)) {
        return !empty($v);
    }
    return trim((string) $v) !== "";
}
function audit_field_list(array $ctx, array $labels, array $forced = []): array
{

    $out = [];
    foreach ($forced as $label) {
        if (trim((string) $label) !== "") {
            $out[] = (string) $label;
        }
    }
    foreach ($labels as $key => $label) {
        if (
            array_key_exists((string) $key, $ctx) &&
            audit_value_present($ctx[(string) $key])
        ) {
            $out[] = (string) $label;
        }
    }
    return array_values(array_unique($out));
}
function audit_registered_body(
    array $fields,
    string $empty = "Nenhum dado adicional foi informado.",
): string {

    $fields = array_values(
        array_unique(
            array_filter(
                array_map(static  fn($v) => trim((string) $v), $fields),
                static  fn($v) => $v !== "",
            ),
        ),
    );
    if (!$fields) {
        return $empty;
    }
    if (count($fields) === 1) {
        return "Foi registrado: " . $fields[0] . ".";
    }
    return "Foram registrados: " . pt_list($fields) . ".";
}
function audit_status_body(array $ctx, string $label = "status"): string
{

    $status = (string) ($ctx["status"] ?? ($ctx["novo_status"] ?? ""));
    return $status !== ""
        ? "O " . $label . " foi alterado para " . $status . "."
        : "O " . $label . " foi alterado.";
}
function audit_patient_name(array $ctx, mixed $entityId = null): string
{

    $name = trim(
        (string) ($ctx["patient_name"] ??
            ($ctx["paciente"] ?? ($ctx["nome_paciente"] ?? ""))),
    );
    if ($name !== "") {
        return $name;
    }
    $id =
        (int) ($ctx["patient_link_id"] ??
            ($ctx["patient_id"] ?? ($entityId ?? 0)));
    return $id > 0 ? "Paciente #" . $id : "Paciente";
}
function audit_person_target(
    string $label,
    array $ctx,
    string $nameKey = "target_name",
): string {

    $name = trim(
        (string) ($ctx[$nameKey] ?? ($ctx["nome"] ?? ($ctx["name"] ?? ""))),
    );
    return $name !== "" ? $label . " " . $name : $label;
}
function audit_patient_record_target(array $ctx, mixed $entityId = null): string
{

    return "o prontuário de " . audit_patient_name($ctx, $entityId);
}
function audit_clinic_target(array $ctx): string
{

    $name = trim((string) ($ctx["clinic_name"] ?? ($ctx["consultorio"] ?? "")));
    return $name !== "" ? "Consultório " . $name : "Consultório";
}
function audit_task_target(array $ctx): string
{

    $title = trim((string) ($ctx["task_title"] ?? ($ctx["title"] ?? "")));
    return $title !== "" ? "Tarefa “" . $title . "”" : "Tarefa";
}
function audit_notice_target(array $ctx): string
{

    $title = trim((string) ($ctx["notice_title"] ?? ($ctx["title"] ?? "")));
    return $title !== "" ? "Aviso “" . $title . "”" : "Aviso";
}
function audit_appointment_target(array $ctx): string
{

    $patient = audit_patient_name($ctx, $ctx["patient_link_id"] ?? null);
    $start = trim((string) ($ctx["start_at"] ?? ""));
    $when = $start !== "" ? " em " . dt_br($start) : "";
    return "consulta de " . $patient . $when;
}
function audit_ctx_pick(array $ctx, array $keys): string
{

    foreach ($keys as $key) {
        if (array_key_exists((string) $key, $ctx)) {
            $v = trim((string) $ctx[(string) $key]);
            if ($v !== "" && $v !== "0") {
                return $v;
            }
        }
    }
    return "";
}
function audit_money_text(
    array $ctx,
    array $keys = ["valor", "amount", "amount_cents", "target_cents"],
): string {

    foreach ($keys as $key) {
        if (!array_key_exists((string) $key, $ctx)) {
            continue;
        }
        $v = $ctx[(string) $key];
        if (
            is_int($v) ||
            is_float($v) ||
            (is_string($v) && preg_match('/^-?\\d+$/', (string) $v))
        ) {
            return money_br((int) $v);
        }
        $text = trim((string) $v);
        if ($text !== "") {
            return $text;
        }
    }
    return "";
}
function audit_status_text(array $ctx): string
{

    $s = audit_ctx_pick($ctx, ["status", "novo_status", "stage", "active"]);
    if ($s === "") {
        return "";
    }
    if (in_array($s, ["1", "true", "sim"], true)) {
        return "ativo";
    }
    if (in_array($s, ["0", "false", "nao", "não"], true)) {
        return "inativo";
    }
    return str_replace("_", " ", $s);
}
function audit_due_text(array $ctx): string
{

    $v = audit_ctx_pick($ctx, ["due_at", "vencimento", "expected_at"]);
    return $v !== "" ? dt_br($v) : "";
}
function audit_target_scope_label(array $ctx): string
{

    $dest = audit_ctx_pick($ctx, ["destino", "target_scope"]);
    if ($dest === "clinic") {
        return "toda a clínica";
    }
    if ($dest === "role") {
        $cargo = audit_ctx_pick($ctx, ["cargo", "target_role"]);
        return $cargo !== ""
            ? "todos do cargo " . $cargo
            : "um cargo específico";
    }
    if ($dest === "user") {
        $name = audit_ctx_pick($ctx, [
            "assigned_name",
            "target_name",
            "pessoa_nome",
            "pessoa",
        ]);
        return $name !== "" ? "a pessoa " . $name : "uma pessoa específica";
    }
    return $dest !== "" ? $dest : "";
}
function audit_finance_base_label_from_ctx(array $ctx): string
{

    $base = audit_ctx_pick($ctx, ["base", "base_metric"]);
    return $base !== "" ? financial_goal_base_label($base) : "";
}
function audit_account_name(array $ctx): string
{

    return audit_ctx_pick($ctx, [
        "account_name",
        "conta",
        "name",
        "titulo",
        "title",
    ]);
}
function audit_counterparty_name(array $ctx): string
{

    return audit_ctx_pick($ctx, [
        "counterparty_name",
        "credor",
        "target_name",
        "nome",
        "name",
    ]);
}
function audit_financial_title(array $ctx): string
{

    return audit_ctx_pick($ctx, ["titulo", "title", "name", "descricao"]);
}
function audit_body_for_event(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
): string {

    return activity_direct_body($event, $entity, $entityId, $ctx);
}
function audit_context_array(array $row): array
{

    $raw = (string) ($row["context_json"] ?? "");
    if ($raw === "") {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function audit_integrity_base(array $r): string
{

    return implode("|", [
        (string) ($r["clinic_id"] ?? ""),
        (string) ($r["user_id"] ?? ""),
        (string) ($r["event"] ?? ($r["event_key"] ?? "")),
        (string) ($r["entity"] ?? ($r["entity_key"] ?? "")),
        (string) ($r["entity_id"] ?? ""),
        (string) ($r["friendly_text"] ?? ""),
        (string) ($r["context_json"] ?? ""),
    ]);
}
function verify_audit_row(array $r): bool
{

    $hash = trim((string) ($r["integrity_hash"] ?? ""));
    try {
        if (class_exists("\\Prontoo\\Core\\Integrity\\AuditChain")) {
            return \Prontoo\Core\Integrity\AuditChain::verifyRow(
                $r,
                secret_key(),
            );
        }
        return $hash !== "" && hash_equals(
            $hash,
            hash_hmac("sha256", audit_integrity_base($r), secret_key()),
        );
    } catch (Throwable $e) {
        return false;
    }
}
function audit_chain_integrity_status(int $limit = 240): array
{

    $limit = max(2, min(1000, $limit));
    try {
        $rows = q(
            audit_select_sql() . " ORDER BY a.id DESC LIMIT " . $limit,
        )->fetchAll();
        $sequenceOk = \Prontoo\Core\Integrity\AuditChain::verifySequence(
            $rows,
            secret_key(),
        );
        $headOk = \Prontoo\Core\Integrity\AuditChain::storedHeadMatchesLatest();
        return [
            "ok" => $sequenceOk && $headOk,
            "sequence_ok" => $sequenceOk,
            "head_ok" => $headOk,
            "checked" => count($rows),
        ];
    } catch (Throwable $error) {
        error_log("[Prontoo audit chain] " . $error->getMessage());
        return [
            "ok" => false,
            "sequence_ok" => false,
            "head_ok" => false,
            "checked" => 0,
        ];
    }
}
function audit_select_sql(): string
{

    return "SELECT a.id,a.clinic_id,a.user_id,a.event_key,a.event_key AS event,a.event_label,a.event_icon,a.entity_key,a.entity_key AS entity,a.entity_label,a.entity_id,a.friendly_text,a.context_json,a.integrity_hash,a.previous_hash,a.chain_hash,a.proof_hash,a.proof_json,a.policy_version,a.created_at FROM pi_audit a";
}
function int_ids(array $rows, string $key): array
{

    $ids = [];
    foreach ($rows as $r) {
        $v = (int) ($r[$key] ?? 0);
        if ($v > 0) {
            $ids[$v] = $v;
        }
    }
    return array_values($ids);
}
function fetch_map(string $table, array $ids, string $cols = "id"): array
{

    $table = allowed_db_table($table);
    $cols = safe_db_columns($cols);
    $ids = array_values(array_unique(array_map("intval", $ids)));
    if (!$ids) {
        return [];
    }
    $ids = array_slice($ids, 0, 300);
    $scopeCid = session_clinic_scope_id();
    $loader = function () use ($table, $ids, $cols, $scopeCid): array {

        $ph = implode(",", array_fill(0, count($ids), "?"));
        if ($scopeCid > 0 && tenant_table_is_scoped($table)) {
            $rows = q(
                "SELECT $cols FROM $table WHERE clinic_id=? AND id IN ($ph)",
                array_merge([$scopeCid], $ids),
            )->fetchAll();
        } else {
            $rows = q(
                "SELECT $cols FROM $table WHERE id IN ($ph)",
                $ids,
            )->fetchAll();
        }
        $m = [];
        foreach ($rows as $r) {
            if (isset($r["id"])) {
                $m[(int) $r["id"]] = $r;
            }
        }
        return $m;
    };
    if (
        function_exists("server_json_cache_remember") &&
        server_json_cache_read_allowed()
    ) {
        $key = server_json_cache_safe_key("fetch_map", [
            $table,
            $ids,
            $cols,
            $scopeCid,
            defined("PRONTOO_SCHEMA_REV") ? PRONTOO_SCHEMA_REV : "",
        ]);
        return server_json_cache_remember(
            "auxiliary",
            $key,
            server_json_cache_ttl("auxiliary"),
            $loader,
            ["table:" . $table, "scope:" . $scopeCid],
        );
    }
    return $loader();
}
function scoped_patient_map(
    int $cid,
    array $ids,
    string $cols = "id,person_id",
): array {

    $cols = safe_db_columns($cols);
    $ids = array_values(array_unique(array_map("intval", $ids)));
    if ($cid <= 0 || !$ids) {
        return [];
    }
    $ids = array_slice($ids, 0, 300);
    $ph = implode(",", array_fill(0, count($ids), "?"));
    $rows = q(
        "SELECT $cols FROM pi_patients WHERE clinic_id=? AND id IN ($ph)",
        array_merge([$cid], $ids),
    )->fetchAll();
    $m = [];
    foreach ($rows as $r) {
        if (isset($r["id"])) {
            $m[(int) $r["id"]] = $r;
        }
    }
    return $m;
}
function scoped_user_map(int $cid, array $ids, string $cols = "id,name"): array
{

    $cols = safe_db_columns($cols);
    $ids = array_values(array_unique(array_map("intval", $ids)));
    if ($cid <= 0 || !$ids) {
        return [];
    }
    $ids = array_slice($ids, 0, 300);
    $ph = implode(",", array_fill(0, count($ids), "?"));
    $rows = q(
        "SELECT $cols FROM pi_users u WHERE u.id IN ($ph) AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) ",
        array_merge($ids, [$cid]),
    )->fetchAll();
    $m = [];
    foreach ($rows as $r) {
        if (isset($r["id"])) {
            $m[(int) $r["id"]] = $r;
        }
    }
    return $m;
}
function audit_where_sql(string $where): string
{

    return trim($where) === "1=1" ? "1=1" : $where;
}
function audit_rows_light(
    string $where = "1=1",
    array $p = [],
    int $limit = 80,
    int $offset = 0,
): array {

    $limit = max(1, min(120, $limit));
    $offset = max(0, $offset);
    try {
        return q(
            audit_select_sql() .
                " WHERE " .
                audit_where_sql($where) .
                " AND a.event_key NOT IN ('login_clinica_pendente','login_credencial_pendente') ORDER BY a.id DESC LIMIT $limit OFFSET $offset",
            $p,
        )->fetchAll();
    } catch (Throwable $e) {
        if (!db_schema_error_is_missing_table($e)) {
            error_log("[Prontoo audit compact read] " . $e->getMessage());
        }
        return [];
    }
}
function activity_axis_for_event(string $event): array
{

    $map = [
        "janela_aberta" => ["Consultar", "read", "visibility"],
        "login_carregado" => ["Consultar", "read", "login"],
        "autoteste_aviso" => ["Consultar", "read", "health_and_safety"],
        "entrada_realizada" => ["Consultar", "read", "login"],
        "entrada_automatica_dispositivo" => ["Consultar", "read", "devices"],
        "saida_realizada" => ["Consultar", "read", "logout"],
        "falha_entrada" => ["Consultar", "read", "lock"],
        "login_clinica_pendente" => ["Consultar", "read", "account_tree"],
        "login_credencial_pendente" => ["Consultar", "read", "switch_account"],
        "acesso_negado" => ["Consultar", "read", "visibility_off"],
        "csrf_bloqueado" => ["Modificar", "update", "block"],
        "consulta_agendada" => ["Criar", "create", "event_available"],
        "consulta_alterada" => ["Modificar", "update", "edit_calendar"],
        "consulta_excluida" => ["Excluir", "delete", "event_busy"],
        "consulta_iniciada" => ["Modificar", "update", "play_circle"],
        "paciente_chegou" => ["Modificar", "update", "how_to_reg"],
        "agenda_bloqueada" => ["Criar", "create", "event_busy"],
        "bloqueio_alterado" => ["Modificar", "update", "edit_calendar"],
        "bloqueio_removido" => ["Excluir", "delete", "event_available"],
        "lead_criado" => ["Criar", "create", "person_add"],
        "lead_atualizado" => ["Modificar", "update", "manage_accounts"],
        "paciente_salvo" => ["Modificar", "update", "patient_list"],
        "paciente_excluido" => ["Excluir", "delete", "delete"],
        "paciente_recuperado" => ["Modificar", "update", "restore"],
        "aba_paciente_criada" => ["Criar", "create", "add_circle"],
        "responsavel_legal_salvo" => [
            "Modificar",
            "update",
            "supervisor_account",
        ],
        "responsavel_legal_removido" => ["Excluir", "delete", "person_remove"],
        "registro_clinico" => ["Modificar", "update", "clinical_notes"],
        "prontuario_alterado" => ["Modificar", "update", "clinical_notes"],
        "prontuario_visualizado" => ["Consultar", "read", "folder_open"],
        "receita_visualizada" => ["Consultar", "read", "medication"],
        "documento_rascunho_criado" => ["Criar", "create", "note_add"],
        "documento_emitido" => ["Criar", "create", "description"],
        "documento_descartado" => ["Excluir", "delete", "delete_sweep"],
        "documento_visualizado" => ["Consultar", "read", "visibility"],
        "documento_impresso" => ["Consultar", "read", "print"],
        "modelo_documento_criado" => ["Criar", "create", "post_add"],
        "modelo_documento_atualizado" => [
            "Modificar",
            "update",
            "edit_document",
        ],
        "modelo_documento_aprovado" => ["Modificar", "update", "verified"],
        "modelo_documento_rejeitado" => ["Modificar", "update", "block"],
        "procedimento_criado" => ["Criar", "create", "add_task"],
        "procedimento_atualizado" => ["Modificar", "update", "edit_note"],
        "procedimento_status" => ["Modificar", "update", "toggle_on"],
        "tarefa_criada" => ["Criar", "create", "add_task"],
        "tarefa_iniciada" => ["Modificar", "update", "play_arrow"],
        "tarefa_concluida" => ["Modificar", "update", "task_alt"],
        "tarefa_devolvida_fila" => ["Modificar", "update", "undo"],
        "comentario_tarefa_criado" => ["Criar", "create", "add_comment"],
        "comentario_tarefa_editado" => ["Modificar", "update", "edit_note"],
        "comentario_tarefa_excluido" => ["Excluir", "delete", "delete"],
        "notificacao_tarefa_individual" => [
            "Criar",
            "create",
            "notifications_active",
        ],
        "comunicado_criado" => ["Criar", "create", "campaign"],
        "leitura_confirmada" => ["Consultar", "read", "done_all"],
        "usuario_salvo" => ["Modificar", "update", "person_add"],
        "usuario_status" => ["Modificar", "update", "manage_accounts"],
        "usuario_desativado" => ["Excluir", "delete", "person_remove"],
        "senha_redefinida" => ["Modificar", "update", "key"],
        "permissoes_atualizadas" => [
            "Modificar",
            "update",
            "admin_panel_settings",
        ],
        "consultorio_criado" => ["Criar", "create", "home_health"],
        "consultorio_atualizado" => ["Modificar", "update", "home_health"],
        "clinica_criada" => ["Criar", "create", "home_health"],
        "clinica_atualizada" => ["Modificar", "update", "home_health"],
        "clinica_status" => ["Modificar", "update", "toggle_on"],
        "clinica_modelo_atualizada" => [
            "Modificar",
            "update",
            "workspace_premium",
        ],
        "onboarding_concluido" => ["Modificar", "update", "check_circle"],
        "bloqueio_login_removido" => ["Excluir", "delete", "lock_open"],
        "erro_marcado_resolvido" => ["Modificar", "update", "task_alt"],
        "aviso_global_criado" => ["Criar", "create", "campaign"],
        "aviso_global_status" => ["Modificar", "update", "campaign"],
        "manutencao_atualizada" => ["Modificar", "update", "engineering"],
        "config_global_atualizada" => ["Modificar", "update", "tune"],
        "assinatura_ativada" => ["Modificar", "update", "verified"],
        "assinatura_desativada" => ["Excluir", "delete", "block"],
        "assinatura_atualizada" => ["Modificar", "update", "payments"],
        "assinatura_pagamento_informado" => ["Criar", "create", "payments"],
        "assinatura_pagamento_confirmado" => [
            "Modificar",
            "update",
            "verified",
        ],
        "assinatura_pagamento_nao_confirmado" => ["Excluir", "delete", "block"],
        "assinatura_comprovante_visualizado" => [
            "Consultar",
            "read",
            "visibility",
        ],
        "assinatura_comprovante_excluido" => [
            "Excluir",
            "delete",
            "delete_sweep",
        ],
        "somente_leitura_bloqueio" => ["Consultar", "read", "lock"],
        "meta_financeira_salva" => ["Modificar", "update", "flag"],
        "conta_bancaria_criada" => ["Criar", "create", "account_balance"],
        "conta_financeira_criada" => [
            "Criar",
            "create",
            "account_balance_wallet",
        ],
        "forma_pagamento_salva" => ["Modificar", "update", "payments"],
        "credor_salvo" => ["Modificar", "update", "person_pin"],
        "despesa_cadastrada" => ["Criar", "create", "payments"],
        "despesa_operacional_salva" => ["Criar", "create", "payments"],
        "despesa_paga" => ["Modificar", "update", "paid"],
        "despesa_cancelada" => ["Excluir", "delete", "cancel"],
        "recebivel_destinado" => ["Modificar", "update", "payments"],
        "receita_operacional_salva" => ["Criar", "create", "payments"],
        "receita_recebida" => ["Modificar", "update", "paid"],
        "receita_cancelada" => ["Excluir", "delete", "cancel"],
        "transferencia_financeira" => ["Criar", "create", "sync_alt"],
    ];
    if (isset($map[$event])) {
        return $map[$event];
    }
    if (
        str_contains($event, "exclu") ||
        str_contains($event, "remov") ||
        str_contains($event, "desativ") ||
        str_contains($event, "nao_confirmado") ||
        str_contains($event, "cancelad")
    ) {
        return ["Excluir", "delete", "delete"];
    }
    if (
        str_contains($event, "visualiz") ||
        str_contains($event, "abert") ||
        str_contains($event, "login") ||
        str_contains($event, "entrada") ||
        str_contains($event, "acesso") ||
        str_contains($event, "consult")
    ) {
        return ["Consultar", "read", "visibility"];
    }
    if (
        str_contains($event, "criad") ||
        str_contains($event, "cadastr") ||
        str_contains($event, "emitid") ||
        str_contains($event, "informado") ||
        str_contains($event, "salva")
    ) {
        return ["Criar", "create", "add_circle"];
    }
    return ["Modificar", "update", "edit"];
}
function activity_module_label(?string $entity): string
{

    return [
        "clinica" => "Consultório",
        "consultorio" => "Consultório",
        "usuario" => "Equipe",
        "consulta" => "Agenda",
        "paciente" => "Pacientes",
        "lead" => "Interessados",
        "bloqueio" => "Agenda",
        "tarefa" => "Tarefas",
        "comunicado" => "Comunicados",
        "prontuario" => "Ficha do paciente",
        "documento" => "Documentos",
        "janela" => "Navegação",
        "rota" => "Permissões",
        "procedimento" => "Procedimentos",
        "sistema" => "Sistema",
        "permissoes" => "Permissões",
        "erro" => "Incidentes",
        "aviso_global" => "Avisos globais",
        "manutencao" => "Manutenção",
        "configuracao" => "Configurações",
        "assinatura" => "Assinatura",
        "financeiro" => "Financeiro",
        "login" => "Entrada",
        "seguranca" => "Segurança",
        "plataforma" => "Plataforma",
    ][$entity ?? ""] ?? ucfirst(entity_label($entity));
}
function activity_time_direct(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {

    $raw = trim((string) ($value ?? ""));
    if ($raw === "") {
        return "—";
    }
    if (function_exists("app_datetime_br")) {
        $formatted = app_datetime_br($raw, $clinicId, $context);
        if (trim($formatted) !== "" && $formatted !== $raw) {
            return $formatted;
        }
        if (preg_match('/^-?\d+$/', $raw)) {
            $formatted = app_datetime_br((int) $raw, $clinicId, $context);
            if (trim($formatted) !== "" && $formatted !== $raw) {
                return $formatted;
            }
        }
    }
    if (preg_match('/^-?\d+$/', $raw)) {
        $ts = (int) $raw;
        if ($ts > 0) {
            return date("d/m/Y, H\hi", $ts);
        }
    }
    $ts = strtotime($raw);
    if (!$ts) {
        return $raw;
    }
    return date("d/m/Y, H\hi", $ts);
}
function activity_text_value(mixed $v): string
{

    if (is_array($v)) {
        return trim(implode(", ", array_filter(array_map("strval", $v))));
    }
    return trim((string) $v);
}
function activity_clean_name(string $value, string $fallback = ""): string
{

    $value = trim(preg_replace("/\s+/", " ", $value));
    return $value !== "" ? $value : $fallback;
}
function activity_money_from_ctx(array $ctx): string
{

    foreach (
        ["valor", "amount_cents", "amount", "target_cents", "price_cents"]
        as $k
    ) {
        if (array_key_exists($k, $ctx) && is_numeric($ctx[$k])) {
            return money_br((int) $ctx[$k]);
        }
    }
    return "";
}
function activity_date_from_ctx(array $ctx, array $keys): string
{

    foreach ($keys as $k) {
        $v = activity_text_value($ctx[$k] ?? "");
        if ($v !== "") {
            return dt_br($v);
        }
    }
    return "";
}
function activity_status_from_ctx(array $ctx): string
{

    $s = activity_text_value(
        $ctx["status"] ?? ($ctx["novo_status"] ?? ($ctx["active"] ?? "")),
    );
    if ($s === "") {
        return "";
    }
    $l = mb_strtolower($s);
    return match ($l) {
        "1", "true", "sim", "ativo", "ativa" => "ativa",
        "0", "false", "nao", "não", "inativo", "inativa" => "inativa",
        "prevista" => "prevista",
        "paga" => "paga",
        "recebida" => "recebida",
        "cancelada" => "cancelada",
        default => str_replace("_", " ", $s),
    };
}
function activity_display_label(string $label): string
{

    $label = trim($label);
    if ($label === "") {
        return "";
    }
    $known = [
        "cpf" => "CPF",
        "cnpj" => "CNPJ",
        "e-mail" => "E-mail",
        "email" => "E-mail",
        "telefone" => "Telefone",
        "nascimento" => "Nascimento",
        "endereço" => "Endereço",
        "endereco" => "Endereço",
        "nome" => "Nome",
        "nome completo" => "Nome completo",
        "cadastro do paciente" => "Cadastro do paciente",
        "responsável legal" => "Responsável legal",
        "responsavel legal" => "Responsável legal",
        "status" => "Status",
        "valor" => "Valor",
        "título" => "Título",
        "titulo" => "Título",
        "descricao" => "Descrição",
        "descrição" => "Descrição",
        "conta" => "Conta",
        "conta de origem" => "Conta de origem",
        "conta de destino" => "Conta de destino",
        "credor" => "Credor",
        "categoria" => "Categoria",
        "vencimento" => "Vencimento",
        "data prevista" => "Data prevista",
        "data realizada" => "Data realizada",
        "forma de pagamento" => "Forma de pagamento",
        "permissões" => "Permissões",
        "permissoes" => "Permissões",
        "senha" => "Senha",
        "cor de destaque" => "Cor de destaque",
        "ícone" => "Ícone",
        "icone" => "Ícone",
        "nomes dos setores" => "Nomes dos setores",
        "perfil" => "Perfil",
        "cargo" => "Cargo",
        "responsável" => "Responsável",
        "responsavel" => "Responsável",
        "paciente" => "Paciente",
        "profissional" => "Profissional",
        "horário" => "Horário",
        "horario" => "Horário",
        "observações" => "Observações",
        "observacoes" => "Observações",
        "marcadores" => "Marcadores",
    ];
    $key = mb_strtolower($label);
    return $known[$key] ?? mb_convert_case($label, MB_CASE_TITLE, "UTF-8");
}
function activity_changed_fields(
    string $event,
    ?string $entity,
    array $ctx,
): array {

    $labels = [
        "nome" => "Nome",
        "name" => "Nome",
        "full_name" => "Nome completo",
        "phone" => "Telefone",
        "telefone" => "Telefone",
        "email" => "E-mail",
        "cpf" => "CPF",
        "cnpj" => "CNPJ",
        "legal_document" => "CPF/CNPJ",
        "birth_date" => "Nascimento",
        "birth" => "Nascimento",
        "address" => "Endereço",
        "address_line" => "Endereço",
        "address_city" => "Cidade",
        "address_state" => "UF",
        "tags" => "Marcadores",
        "notes" => "Observações",
        "stage" => "Etapa",
        "next_action_at" => "Próxima ação",
        "source" => "Origem",
        "interest" => "Interesse",
        "title" => "Título",
        "titulo" => "Título",
        "description" => "Descrição",
        "descricao" => "Descrição",
        "due_at" => "Vencimento",
        "vencimento" => "Vencimento",
        "expected_at" => "Data prevista",
        "received_at" => "Data realizada",
        "paid_at" => "Data realizada",
        "target_scope" => "Destinatário",
        "target_role" => "Cargo",
        "target_user_id" => "Pessoa",
        "display_name" => "Nome fantasia",
        "legal_name" => "Razão social",
        "clinic_icon" => "Ícone",
        "accent_color" => "Cor de destaque",
        "setores" => "Nomes dos setores",
        "status" => "Status",
        "novo_status" => "Status",
        "active" => "Status",
        "valor" => "Valor",
        "amount" => "Valor",
        "amount_cents" => "Valor",
        "target_cents" => "Valor",
        "account_id" => "Conta",
        "account_name" => "Conta",
        "conta" => "Conta",
        "origem" => "Conta de origem",
        "destino" => "Conta de destino",
        "counterparty_name" => "Credor",
        "credor" => "Credor",
        "categoria" => "Categoria",
        "category" => "Categoria",
        "payment_method" => "Forma de pagamento",
        "forma_pagamento" => "Forma de pagamento",
        "perfil" => "Perfil",
        "role_code" => "Cargo",
        "profession" => "Profissão",
        "responsible_profession" => "Profissão",
        "password" => "Senha",
        "senha" => "Senha",
    ];
    $out = [];
    foreach (["campos", "fields", "changed_fields"] as $k) {
        if (!empty($ctx[$k])) {
            $raw = is_array($ctx[$k])
                ? $ctx[$k]
                : explode(",", (string) $ctx[$k]);
            foreach ($raw as $v) {
                $v = activity_display_label((string) $v);
                if ($v !== "") {
                    $out[] = $v;
                }
            }
        }
    }
    foreach ($labels as $key => $label) {
        if (!array_key_exists($key, $ctx)) {
            continue;
        }
        if (audit_value_present($ctx[$key])) {
            $out[] = activity_display_label($label);
        }
    }
    $eventFields = [
        "transferencia_financeira" => [
            "Valor",
            "Conta de origem",
            "Conta de destino",
        ],
        "consulta_agendada" => ["Paciente", "Profissional", "Horário"],
        "consulta_alterada" => [
            "Paciente",
            "Profissional",
            "Horário",
            "Status",
        ],
        "consulta_iniciada" => ["Status"],
        "paciente_chegou" => ["Status"],
        "tarefa_criada" => ["Título", "Destinatário", "Vencimento"],
        "tarefa_iniciada" => ["Status"],
        "tarefa_concluida" => ["Status"],
        "tarefa_devolvida_fila" => ["Status"],
        "usuario_salvo" => ["Nome", "CPF", "E-mail", "Cargo"],
        "usuario_status" => ["Status"],
        "usuario_desativado" => ["Status"],
        "permissoes_atualizadas" => ["Permissões"],
        "config_global_atualizada" => ["Configurações"],
        "assinatura_pagamento_informado" => ["Valor"],
        "assinatura_pagamento_confirmado" => ["Status"],
        "assinatura_pagamento_nao_confirmado" => ["Status"],
        "receita_operacional_salva" => [
            "Título",
            "Valor",
            "Data prevista",
            "Conta",
        ],
        "receita_recebida" => ["Status", "Valor", "Conta"],
        "receita_cancelada" => ["Status"],
        "despesa_operacional_salva" => [
            "Título",
            "Valor",
            "Credor",
            "Categoria",
            "Vencimento",
            "Conta",
        ],
        "despesa_paga" => ["Status", "Valor", "Conta"],
        "despesa_cancelada" => ["Status"],
    ];
    foreach ($eventFields[$event] ?? [] as $f) {
        $out[] = $f;
    }
    return array_values(
        array_unique(
            array_filter($out, static  fn($v) => trim((string) $v) !== ""),
        ),
    );
}
function activity_patient_name(array $ctx, mixed $entityId = null): string
{

    return activity_clean_name(audit_patient_name($ctx, $entityId), "paciente");
}
function activity_title_from_ctx(
    array $ctx,
    string $fallback = "registro",
): string {

    return activity_clean_name(
        activity_text_value(
            $ctx["titulo"] ??
                ($ctx["title"] ?? ($ctx["name"] ?? ($ctx["descricao"] ?? ""))),
        ),
        $fallback,
    );
}
function activity_person_from_ctx(
    array $ctx,
    string $fallback = "colaborador",
): string {

    $n = activity_text_value(
        $ctx["target_name"] ?? ($ctx["nome"] ?? ($ctx["name"] ?? "")),
    );
    if ($n === "") {
        $n = trim(audit_person_target("", $ctx));
    }
    return activity_clean_name($n, $fallback);
}
function activity_target_scope_human(array $ctx): string
{

    $scope = activity_text_value(
        $ctx["destino"] ?? ($ctx["target_scope"] ?? ""),
    );
    if ($scope === "role") {
        $cargo = activity_text_value(
            $ctx["cargo"] ?? ($ctx["target_role"] ?? ""),
        );
        return $cargo !== "" ? "para o setor " . $cargo : "para um setor";
    }
    if ($scope === "user") {
        $name = activity_text_value(
            $ctx["assigned_name"] ?? ($ctx["target_name"] ?? ""),
        );
        return $name !== "" ? "para " . $name : "para uma pessoa da equipe";
    }
    if ($scope === "clinic") {
        return "para toda a equipe";
    }
    return $scope !== "" ? "para " . $scope : "";
}
function activity_financial_label(array $ctx, string $fallback): string
{

    $title = activity_title_from_ctx($ctx, "");
    if ($title !== "") {
        return $title;
    }
    $cp = activity_clean_name(audit_counterparty_name($ctx), "");
    return $cp !== "" ? $cp : $fallback;
}
function activity_environment_label(array $ctx): string
{

    $label = activity_text_value(
        $ctx["environment_label"] ??
            ($ctx["role_label"] ?? ($ctx["ambiente"] ?? "")),
    );
    if ($label !== "") {
        return $label;
    }
    $role = activity_text_value($ctx["role_code"] ?? ($ctx["role"] ?? ""));
    $cid = (int) ($ctx["clinic_id"] ?? 0);
    if ($role !== "" && function_exists("role_label_for")) {
        try {
            $resolved = role_label_for($role, $cid > 0 ? $cid : null);
            if (trim((string) $resolved) !== "") {
                return trim((string) $resolved);
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    if (
        $role !== "" &&
        defined("PRONTOO_ROLES") &&
        isset(PRONTOO_ROLES[$role])
    ) {
        return (string) PRONTOO_ROLES[$role];
    }
    if (activity_text_value($ctx["scope"] ?? "") === "global") {
        return "Desenvolvedor";
    }
    return "";
}
function activity_human_sentence(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
    ?int $uid,
): string {

    $who = audit_actor_name($ctx, $uid);
    $patient = activity_patient_name($ctx, $entityId);
    $title = activity_title_from_ctx($ctx, "");
    $clinic = trim(str_replace("Consultório", "", audit_clinic_target($ctx)));
    $clinic = $clinic !== "" ? $clinic : "consultório";
    $windowLabel = activity_text_value($ctx["janela"] ?? $title);
if ($windowLabel === "") {
    $windowLabel = "do sistema";
}
    return match ($event) {
        "janela_aberta" => $entity === "paciente" ||
        trim((string) ($ctx["patient_name"] ?? "")) !== ""
            ? $who . " abriu a ficha do paciente " . $patient
            : $who . " abriu a tela " . $windowLabel,
        "login_carregado" => $who . " abriu a tela de entrada",
        "autoteste_aviso" => $who . " recebeu um aviso técnico na entrada",
        "entrada_realizada" => ($env = activity_environment_label($ctx)) !== ""
            ? $who . " entrou no ambiente " . $env
            : $who . " entrou no sistema",
        "entrada_automatica_dispositivo" => ($env = activity_environment_label(
            $ctx,
        )) !== ""
            ? $who .
                " entrou no ambiente " .
                $env .
                " pelo dispositivo reconhecido"
            : $who . " entrou pelo dispositivo reconhecido",
        "saida_realizada" => ($env = activity_environment_label($ctx)) !== ""
            ? $who . " saiu do ambiente " . $env
            : $who . " saiu do sistema",
        "falha_entrada" => $who . " tentou entrar e não conseguiu",
        "login_clinica_pendente" => $who .
            " escolheu um consultório para entrar",
        "login_credencial_pendente" => $who .
            " precisou escolher uma credencial de acesso",
        "acesso_negado" => $who . " tentou abrir uma área sem permissão",
        "csrf_bloqueado" => $who . " teve um envio bloqueado por segurança",
        "consulta_agendada" => $who . " agendou uma consulta para " . $patient,
        "consulta_alterada" => $who . " alterou a consulta de " . $patient,
        "consulta_excluida" => $who . " cancelou a consulta de " . $patient,
        "consulta_iniciada" => $who . " iniciou o atendimento de " . $patient,
        "paciente_chegou" => $who . " registrou a chegada de " . $patient,
        "agenda_bloqueada" => $who . " bloqueou um horário na agenda",
        "bloqueio_alterado" => $who . " alterou um bloqueio da agenda",
        "bloqueio_removido" => $who . " liberou um horário bloqueado na agenda",
        "lead_criado" => $who .
            " cadastrou o interessado " .
            audit_lead_name($ctx),
        "lead_atualizado" => $who .
            " atualizou o interessado " .
            audit_lead_name($ctx),
        "paciente_salvo" => $who .
            " " .
            ((string) ($ctx["acao_paciente"] ?? "") === "cadastrou"
                ? "cadastrou"
                : "alterou") .
            " a ficha do paciente " .
            $patient,
        "paciente_excluido" => $who .
            " removeu a ficha do paciente " .
            $patient .
            " do fluxo ativo",
        "paciente_recuperado" => $who .
            " reativou a ficha do paciente " .
            $patient,
        "aba_paciente_criada" => $who .
            " criou uma nova aba na ficha do paciente " .
            $patient,
        "responsavel_legal_salvo" => $who .
            " atualizou o responsável legal de " .
            $patient,
        "responsavel_legal_removido" => $who .
            " removeu um responsável legal de " .
            $patient,
        "registro_clinico", "prontuario_alterado" => $who .
            " atualizou o prontuário de " .
            $patient,
        "prontuario_visualizado" => $who . " abriu o prontuário de " . $patient,
        "receita_visualizada" => $who . " abriu a receita de " . $patient,
        "documento_rascunho_criado" => $who .
            " preparou um rascunho de documento" .
            (trim((string) ($ctx["patient_name"] ?? "")) !== ""
                ? " para " . trim((string) $ctx["patient_name"])
                : ""),
        "documento_emitido" => $who .
            " emitiu " .
            mb_strtolower(
                audit_document_article(audit_document_type_text($ctx)),
            ) .
            " " .
            audit_document_type_text($ctx) .
            (trim((string) ($ctx["patient_name"] ?? "")) !== ""
                ? " para " . trim((string) $ctx["patient_name"])
                : ""),
        "documento_descartado" => $who . " descartou um rascunho de documento",
        "documento_visualizado" => $who .
            " abriu um documento" .
            (trim((string) ($ctx["patient_name"] ?? "")) !== ""
                ? " de " . trim((string) $ctx["patient_name"])
                : ""),
        "documento_impresso" => $who .
            " imprimiu um documento" .
            (trim((string) ($ctx["patient_name"] ?? "")) !== ""
                ? " de " . trim((string) $ctx["patient_name"])
                : ""),
        "modelo_documento_criado" => $who .
            " criou o modelo de documento " .
            audit_model_title($ctx),
        "modelo_documento_atualizado" => $who .
            " alterou o modelo de documento " .
            audit_model_title($ctx),
        "modelo_documento_aprovado" => $who .
            " aprovou o modelo de documento " .
            audit_model_title($ctx),
        "modelo_documento_rejeitado" => $who .
            " rejeitou o modelo de documento " .
            audit_model_title($ctx),
        "procedimento_criado" => $who .
            " cadastrou o procedimento " .
            ($title ?: "do consultório"),
        "procedimento_atualizado" => $who .
            " alterou o procedimento " .
            ($title ?: "do consultório"),
        "procedimento_status" => $who .
            " mudou o status do procedimento " .
            ($title ?: "do consultório"),
        "tarefa_criada" => $who . " criou a tarefa " . audit_task_name($ctx),
        "tarefa_iniciada" => $who .
            " começou a tarefa " .
            audit_task_name($ctx),
        "tarefa_concluida" => $who .
            " concluiu a tarefa " .
            audit_task_name($ctx),
        "tarefa_devolvida_fila" => $who .
            " devolveu a tarefa " .
            audit_task_name($ctx) .
            " para a fila",
        "comentario_tarefa_criado" => $who .
            " comentou na tarefa " .
            audit_task_name($ctx),
        "comentario_tarefa_editado" => $who .
            " editou um comentário na tarefa " .
            audit_task_name($ctx),
        "comentario_tarefa_excluido" => $who .
            " removeu um comentário da tarefa " .
            audit_task_name($ctx),
        "notificacao_tarefa_individual" => $who .
            " avisou alguém sobre a tarefa " .
            audit_task_name($ctx),
        "comunicado_criado" => $who .
            " publicou o comunicado " .
            audit_notice_name($ctx),
        "leitura_confirmada" => $who .
            " confirmou a leitura do comunicado " .
            audit_notice_name($ctx),
        "usuario_salvo" => $who .
            " atualizou o colaborador " .
            activity_person_from_ctx($ctx),
        "usuario_status" => $who .
            " mudou o status do colaborador " .
            activity_person_from_ctx($ctx),
        "usuario_desativado" => $who .
            " desativou o colaborador " .
            activity_person_from_ctx($ctx),
        "senha_redefinida" => $who .
            " redefiniu a senha de " .
            activity_person_from_ctx($ctx),
        "permissoes_atualizadas" => $who . " ajustou permissões da equipe",
        "consultorio_criado", "clinica_criada" => $who .
            " inaugurou o consultório " .
            $clinic,
        "consultorio_atualizado", "clinica_atualizada" => $who .
            " atualizou o consultório " .
            $clinic,
        "clinica_status" => $who . " mudou o status do consultório " . $clinic,
        "clinica_modelo_atualizada" => $who .
            " atualizou a isenção administrativa da plataforma",
        "onboarding_concluido" => $who .
            " concluiu a configuração inicial do consultório",
        "bloqueio_login_removido" => $who . " liberou uma entrada bloqueada",
        "erro_marcado_resolvido" => $who .
            " marcou um incidente como resolvido",
        "aviso_global_criado" => $who . " publicou um aviso global",
        "aviso_global_status" => $who . " mudou o status de um aviso global",
        "manutencao_atualizada" => $who . " atualizou o modo manutenção",
        "config_global_atualizada" => $who .
            " atualizou configurações globais da plataforma",
        "assinatura_ativada" => $who .
            " ativou a assinatura do consultório " .
            $clinic,
        "assinatura_desativada" => $who .
            " desativou a assinatura do consultório " .
            $clinic,
        "assinatura_atualizada" => $who .
            " atualizou a assinatura do consultório " .
            $clinic,
        "assinatura_pagamento_informado" => $who .
            " informou um pagamento de assinatura",
        "assinatura_pagamento_confirmado" => $who .
            " confirmou um pagamento de assinatura",
        "assinatura_pagamento_nao_confirmado" => $who .
            " recusou um pagamento de assinatura",
        "assinatura_comprovante_visualizado" => $who .
            " abriu um comprovante de assinatura",
        "assinatura_comprovante_excluido" => $who .
            " removeu um comprovante de assinatura",
        "somente_leitura_bloqueio" => $who .
            " tentou alterar dados com a assinatura em somente leitura",
        "meta_financeira_salva" => $who . " atualizou a meta financeira",
        "conta_bancaria_criada", "conta_financeira_criada" => $who .
            " criou a conta financeira " .
            activity_title_from_ctx($ctx, "do consultório"),
        "forma_pagamento_salva" => $who . " atualizou uma forma de pagamento",
        "credor_salvo" => $who .
            " atualizou o favorecido " .
            audit_counterparty_name($ctx),
        "despesa_cadastrada", "despesa_operacional_salva" => $who .
            " registrou a despesa " .
            activity_financial_label($ctx, "do consultório"),
        "despesa_paga" => $who .
            " marcou como paga a despesa " .
            activity_financial_label($ctx, "do consultório"),
        "despesa_cancelada" => $who . " cancelou uma despesa",
        "recebivel_destinado" => $who . " definiu o destino de um recebimento",
        "receita_operacional_salva" => $who .
            " registrou a receita " .
            activity_financial_label($ctx, "do consultório"),
        "receita_recebida" => $who .
            " marcou como recebida a receita " .
            activity_financial_label($ctx, "do consultório"),
        "receita_cancelada" => $who . " cancelou uma receita",
        "transferencia_financeira" => $who . " transferiu saldo entre contas",
        default => $who .
            " " .
            activity_action_verb($event) .
            " " .
            activity_direct_target($event, $entity, $entityId, $ctx),
    };
}
function activity_action_verb(string $event): string
{

    [$axis] = activity_axis_for_event($event);
    return [
        "Criar" => "cadastrou",
        "Modificar" => "alterou",
        "Consultar" => "consultou",
        "Excluir" => "excluiu",
    ][$axis] ?? "alterou";
}
function activity_direct_target(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
): string {

    $patient = activity_patient_name($ctx, $entityId);
    $title = activity_title_from_ctx($ctx, "");
    if ($event === "janela_aberta") {
        return $entity === "paciente" ||
            trim((string) ($ctx["patient_name"] ?? "")) !== ""
            ? "a ficha do paciente " . $patient
            : "a tela " .
                    (activity_text_value($ctx["janela"] ?? $title) ?:
                        "do sistema");
    }
    if ($entity === "paciente") {
        return "a ficha do paciente " . $patient;
    }
    if ($entity === "consulta") {
        return "a consulta de " . $patient;
    }
    if ($entity === "documento") {
        return "o documento " . audit_document_type_text($ctx);
    }
    if ($entity === "tarefa") {
        return "a tarefa " . audit_task_name($ctx);
    }
    if ($entity === "usuario") {
        return "o colaborador " . activity_person_from_ctx($ctx);
    }
    if ($entity === "financeiro") {
        return "o financeiro";
    }
    $label = entity_label($entity);
    $article = in_array(
        $label,
        [
            "consulta",
            "tarefa",
            "aviso",
            "janela",
            "rota",
            "configuração",
            "assinatura",
            "entrada",
            "segurança",
        ],
        true,
    )
        ? "a"
        : "o";
    return $article . " " . $label . ($title !== "" ? " " . $title : "");
}
function activity_direct_title(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
    ?int $uid,
): string {

    return activity_human_sentence($event, $entity, $entityId, $ctx, $uid);
}
function activity_context_details(
    string $event,
    ?string $entity,
    array $ctx,
): array {

    $details = [];
    $money = activity_money_from_ctx($ctx);
    if ($money !== "") {
        $details[] = "Valor: " . $money;
    }
    if (in_array($event, ["consulta_agendada", "consulta_alterada"], true)) {
        $when = activity_date_from_ctx($ctx, [
            "start_at",
            "horario",
            "expected_at",
        ]);
        if ($when !== "") {
            $details[] = "Horário: " . $when;
        }
        $doctor = activity_text_value($ctx["doctor_name"] ?? "");
        if ($doctor !== "") {
            $details[] = "Profissional: " . $doctor;
        }
    }
    if (
        in_array(
            $event,
            ["tarefa_criada", "notificacao_tarefa_individual"],
            true,
        )
    ) {
        $scope = activity_target_scope_human($ctx);
        if ($scope !== "") {
            $details[] = ucfirst($scope);
        }
        $due = activity_date_from_ctx($ctx, ["due_at", "vencimento"]);
        if ($due !== "") {
            $details[] = "Prazo: " . $due;
        }
    }
    if (str_contains($event, "receita_") || $event === "recebivel_destinado") {
        $date = activity_date_from_ctx($ctx, [
            "expected_at",
            "received_at",
            "paid_at",
            "due_at",
        ]);
        if ($date !== "") {
            $details[] = "Data: " . $date;
        }
        $account = activity_text_value(
            $ctx["account_name"] ?? ($ctx["conta"] ?? ""),
        );
        if ($account !== "") {
            $details[] = "Conta: " . $account;
        }
    }
    if (str_contains($event, "despesa_")) {
        $credor = activity_clean_name(audit_counterparty_name($ctx), "");
        if ($credor !== "") {
            $details[] = "Favorecido: " . $credor;
        }
        $cat = activity_text_value(
            $ctx["categoria"] ?? ($ctx["category"] ?? ""),
        );
        if ($cat !== "") {
            $details[] = "Categoria: " . $cat;
        }
        $date = activity_date_from_ctx($ctx, [
            "due_at",
            "vencimento",
            "paid_at",
        ]);
        if ($date !== "") {
            $details[] = "Data: " . $date;
        }
    }
    if ($event === "transferencia_financeira") {
        $from = activity_text_value(
            $ctx["origem"] ?? ($ctx["account_from"] ?? ($ctx["from"] ?? "")),
        );
        $to = activity_text_value(
            $ctx["destino"] ?? ($ctx["account_to"] ?? ($ctx["to"] ?? "")),
        );
        if ($from !== "" || $to !== "") {
            $details[] =
                "Movimento: " .
                ($from !== "" ? $from : "origem") .
                " → " .
                ($to !== "" ? $to : "destino");
        }
    }
    $status = activity_status_from_ctx($ctx);
    if (
        $status !== "" &&
        !in_array(
            $event,
            ["despesa_operacional_salva", "receita_operacional_salva"],
            true,
        )
    ) {
        $details[] = "Status: " . $status;
    }
    return array_values(array_unique(array_filter($details)));
}
function activity_direct_body(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
): string {

    [$axis] = activity_axis_for_event($event);
    if ($axis === "Consultar") {
        return "Nenhuma informação foi alterada.";
    }
    if (
        in_array(
            $event,
            [
                "documento_impresso",
                "assinatura_comprovante_excluido",
                "documento_descartado",
                "comentario_tarefa_excluido",
            ],
            true,
        )
    ) {
        return "O registro original permanece rastreável no histórico.";
    }
    if ($axis === "Excluir") {
        return "O item saiu do fluxo ativo. O histórico foi preservado.";
    }
    $fields = activity_changed_fields($event, $entity, $ctx);
    $details = activity_context_details($event, $entity, $ctx);
    $parts = [];
    if ($fields) {
        $parts[] =
            (count($fields) === 1 ? "Mudou o campo " : "Mudaram os campos ") .
            pt_list($fields) .
            ".";
    }
    if ($details) {
        $parts[] = implode(" · ", $details) . ".";
    }
    if (!$parts) {
        return "Nenhuma informação foi alterada.";
    }
    return implode(" ", $parts);
}
function activity_meta_text(
    string $event,
    ?string $entity,
    string $currentMeta = "",
): string {

    return trim($currentMeta);
}
function activity_fallback_body(string $event, ?string $entity): string
{

    return activity_direct_body($event, $entity, null, []);
}
function audit_items(array $rows, bool $global = false): array
{

    $items = [];
    foreach ($rows as $r) {
        $event = (string) ($r["event_key"] ?? ($r["event"] ?? ""));
        $entityRaw = (string) ($r["entity_key"] ?? ($r["entity"] ?? ""));
        $entity = $entityRaw !== "" ? $entityRaw : null;
        $entityId = $r["entity_id"] ?? null;
        $uid = isset($r["user_id"]) ? (int) $r["user_id"] : null;
        $cid = isset($r["clinic_id"]) ? (int) $r["clinic_id"] : null;
        $ctx = audit_context_array($r);
        $ctx = audit_enrich_context($event, $entity, $entityId, $ctx, $cid);
        if (empty($ctx["actor_name"]) && $uid) {
            $n = audit_user_name_lookup($uid, $cid);
            if ($n !== "") {
                $ctx["actor_name"] = $n;
            }
        }
        [$axis, $axisClass, $axisIcon] = activity_axis_for_event($event);
        $title = activity_direct_title($event, $entity, $entityId, $ctx, $uid);
        if (trim($title) === "") {
            $title = trim(
                (string) ($r["friendly_text"] ?? event_label($event)),
            );
        }
        $body = activity_direct_body($event, $entity, $entityId, $ctx);
        $meta = "";
        if ($global && $cid) {
            $cn = audit_clinic_name_lookup($cid);
            if ($cn !== "") {
                $meta = "Consultório: " . $cn;
            }
        }
        $items[] = [
            "icon" =>
                $axisIcon ?: (string) ($r["event_icon"] ?? event_icon($event)),
            "time" => activity_time_direct($r["created_at"] ?? "", $cid ?: 0),
            "title" => $title,
            "body" => $body,
            "meta" => $meta,
            "class" => "activity-axis-" . $axisClass,
        ];
    }
    return $items;
}
function count_for_clinics(
    string $table,
    array $clinicIds,
    string $where = "1=1",
): array {

    $table = allowed_db_table($table);
    if (!preg_match('/^[A-Za-z0-9_ =?<>()\.\-\']+$/', $where)) {
        throw new RuntimeException("Filtro inválido.");
    }
    $clinicIds = array_values(
        array_unique(array_filter(array_map("intval", $clinicIds))),
    );
    if (!$clinicIds) {
        return [];
    }
    sort($clinicIds, SORT_NUMERIC);
    $clinicIds = array_slice($clinicIds, 0, 300);
    $loader = function () use ($table, $clinicIds, $where): array {

        $out = array_fill_keys($clinicIds, 0);
        $placeholders = implode(",", array_fill(0, count($clinicIds), "?"));
        try {
            $rows = q(
                "SELECT clinic_id,COUNT(*) AS total FROM $table WHERE clinic_id IN ($placeholders) AND $where GROUP BY clinic_id",
                $clinicIds,
            )->fetchAll();
            foreach ($rows as $row) {
                $clinicId = (int) ($row["clinic_id"] ?? 0);
                if ($clinicId > 0) {
                    $out[$clinicId] = (int) ($row["total"] ?? 0);
                }
            }
        } catch (Throwable $e) {
            error_log("[Prontoo grouped clinic count] " . $e->getMessage());
        }
        return $out;
    };
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "dashboard",
            server_json_cache_safe_key("clinic_count", [
                $table,
                $clinicIds,
                $where,
            ]),
            server_json_cache_ttl("dashboard"),
            $loader,
            ["table:" . $table, "admin:clinic_counts"],
        );
    }
    return $loader();
}
function clinic_recent_metrics(array $clinicIds, int $days = 30): array
{

    $clinicIds = array_values(
        array_unique(array_filter(array_map("intval", $clinicIds))),
    );
    if (!$clinicIds) {
        return [];
    }
    sort($clinicIds, SORT_NUMERIC);
    $days = max(1, min(366, $days));
    $from = date("Y-m-d", strtotime("-" . $days . " days"));
    $loader = function () use ($clinicIds, $from): array {

        $placeholders = implode(",", array_fill(0, count($clinicIds), "?"));
        try {
            $rows = q(
                "SELECT clinic_id,metric_key,SUM(metric_value) AS metric_value FROM pi_clinic_daily_stats WHERE clinic_id IN ($placeholders) AND day_date>=? GROUP BY clinic_id,metric_key",
                array_merge($clinicIds, [$from]),
            )->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo clinic_recent_metrics] " . $e->getMessage());
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $clinicId = (int) $row["clinic_id"];
            $metric = (string) $row["metric_key"];
            $out[$clinicId][$metric] = (int) $row["metric_value"];
        }
        return $out;
    };
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "dashboard",
            server_json_cache_safe_key("clinic_metrics", [
                $clinicIds,
                $days,
                $from,
            ]),
            server_json_cache_ttl("dashboard"),
            $loader,
            ["table:pi_clinic_daily_stats", "admin:clinic_metrics"],
        );
    }
    return $loader();
}
function audit_patient_name_by_link(int $patientId, ?int $cid = null): string
{

    if ($patientId <= 0) {
        return "";
    }
    try {
        if ($cid) {
            $pat = one(
                "SELECT id,person_id FROM pi_patients WHERE id=? AND clinic_id=?",
                [$patientId, $cid],
            );
        } else {
            $pat = one("SELECT id,person_id FROM pi_patients WHERE id=?", [
                $patientId,
            ]);
        }
        if (!$pat) {
            return "";
        }
        $person = one("SELECT full_name FROM pi_persons WHERE id=?", [
            (int) $pat["person_id"],
        ]);
        return trim((string) ($person["full_name"] ?? ""));
    } catch (Throwable $e) {
        return "";
    }
}
function audit_user_name_lookup(int $uid, ?int $cid = null): string
{

    static $cache = [];
    if ($uid <= 0) {
        return "";
    }
    $memoryKey = ($cid ? "c" . $cid . ":" : "g:") . $uid;
    if (array_key_exists($memoryKey, $cache)) {
        return $cache[$memoryKey];
    }
    $loader = static function () use ($uid, $cid): string {

        try {
            $u = $cid
                ? one("SELECT u.id,u.name FROM pi_users u WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) LIMIT 1", [$uid, $cid])
                : one("SELECT id,name FROM pi_users WHERE id=?", [$uid]);
            return trim((string) ($u["name"] ?? ""));
        } catch (Throwable $e) {
            error_log("[Prontoo audit user lookup] " . $e->getMessage());
            return "";
        }
    };
    if (function_exists("server_json_cache_remember") && server_json_cache_read_allowed()) {
        $cache[$memoryKey] = (string) server_json_cache_remember(
            "lookup",
            server_json_cache_safe_key("audit_user_name", [$cid ?: 0, $uid]),
            server_json_cache_ttl("lookup"),
            $loader,
            ["table:pi_users", "table:pi_user_roles", "scope:" . ($cid ?: 0)],
        );
    } else {
        $cache[$memoryKey] = $loader();
    }
    return $cache[$memoryKey];
}
function audit_clinic_name_lookup(int $cid): string
{

    static $cache = [];
    if ($cid <= 0) {
        return "";
    }
    if (array_key_exists($cid, $cache)) {
        return $cache[$cid];
    }
    $loader = static function () use ($cid): string {

        try {
            $cl = one("SELECT id,display_name FROM pi_clinics WHERE id=?", [$cid]);
            return trim((string) ($cl["display_name"] ?? ""));
        } catch (Throwable $e) {
            error_log("[Prontoo audit clinic lookup] " . $e->getMessage());
            return "";
        }
    };
    if (function_exists("server_json_cache_remember") && server_json_cache_read_allowed()) {
        $cache[$cid] = (string) server_json_cache_remember(
            "clinic",
            server_json_cache_safe_key("audit_clinic_name", $cid),
            server_json_cache_ttl("clinic"),
            $loader,
            ["table:pi_clinics", "scope:" . $cid],
        );
    } else {
        $cache[$cid] = $loader();
    }
    return $cache[$cid];
}
function audit_enrich_context(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $context,
    ?int $cid = null,
): array {

    $id = (int) $entityId;
    if (empty($context["clinic_name"]) && $cid) {
        $n = audit_clinic_name_lookup((int) $cid);
        if ($n !== "") {
            $context["clinic_name"] = $n;
        }
    }
    if (empty($context["patient_name"])) {
        $pid =
            (int) ($context["patient_link_id"] ??
                ($entity === "paciente" && $id > 0 ? $id : 0));
        if ($pid > 0) {
            $n = audit_patient_name_by_link($pid, $cid);
            if ($n !== "") {
                $context["patient_name"] = $n;
            }
        }
    }
    if (empty($context["doctor_name"]) && !empty($context["doctor_user_id"])) {
        $n = audit_user_name_lookup((int) $context["doctor_user_id"], $cid);
        if ($n !== "") {
            $context["doctor_name"] = $n;
        }
    }
    if (empty($context["assigned_name"]) && !empty($context["assigned_to"])) {
        $n = audit_user_name_lookup((int) $context["assigned_to"], $cid);
        if ($n !== "") {
            $context["assigned_name"] = $n;
        }
    }
    if (empty($context["target_name"]) && $entity === "usuario" && $id > 0) {
        $n = audit_user_name_lookup($id, $cid);
        if ($n !== "") {
            $context["target_name"] = $n;
        }
    }
    if (empty($context["target_name"]) && !empty($context["destinatario"])) {
        $n = audit_user_name_lookup((int) $context["destinatario"], $cid);
        if ($n !== "") {
            $context["target_name"] = $n;
        }
    }
    if (empty($context["task_title"]) && !empty($context["tarefa_id"])) {
        try {
            $t = $cid
                ? one(
                    "SELECT id,title FROM pi_tasks WHERE id=? AND clinic_id=?",
                    [(int) $context["tarefa_id"], $cid],
                )
                : one("SELECT id,title FROM pi_tasks WHERE id=?", [
                    (int) $context["tarefa_id"],
                ]);
            if ($t && !empty($t["title"])) {
                $context["task_title"] = $t["title"];
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    if (
        in_array(
            $event,
            [
                "modelo_documento_criado",
                "modelo_documento_atualizado",
                "modelo_documento_aprovado",
                "modelo_documento_rejeitado",
            ],
            true,
        ) &&
        $id > 0
    ) {
        try {
            $tpl = $cid
                ? one(
                    "SELECT id,title,type_key,status FROM pi_document_templates WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                )
                : one(
                    "SELECT id,title,type_key,status FROM pi_document_templates WHERE id=?",
                    [$id],
                );
            if ($tpl) {
                if (empty($context["titulo"]) && !empty($tpl["title"])) {
                    $context["titulo"] = $tpl["title"];
                }
                if (
                    empty($context["template_title"]) &&
                    !empty($tpl["title"])
                ) {
                    $context["template_title"] = $tpl["title"];
                }
                if (
                    empty($context["document_type"]) &&
                    !empty($tpl["type_key"])
                ) {
                    $context["document_type"] = $tpl["type_key"];
                }
                if (empty($context["status"]) && !empty($tpl["status"])) {
                    $context["status"] = function_exists(
                        "document_status_label",
                    )
                        ? document_status_label((string) $tpl["status"])
                        : (string) $tpl["status"];
                }
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    if (
        $entity === "documento" &&
        $id > 0 &&
        !in_array(
            $event,
            [
                "modelo_documento_criado",
                "modelo_documento_atualizado",
                "modelo_documento_aprovado",
                "modelo_documento_rejeitado",
            ],
            true,
        )
    ) {
        try {
            $d = $cid
                ? one(
                    "SELECT d.id,d.title,d.type_key,d.patient_link_id,p.full_name AS patient_name FROM pi_documents d LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id AND pl.clinic_id=d.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE d.id=? AND d.clinic_id=?",
                    [$id, $cid],
                )
                : one(
                    "SELECT d.id,d.title,d.type_key,d.patient_link_id,p.full_name AS patient_name FROM pi_documents d LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE d.id=?",
                    [$id],
                );
            if ($d) {
                if (empty($context["titulo"]) && !empty($d["title"])) {
                    $context["titulo"] = $d["title"];
                }
                if (
                    empty($context["document_type"]) &&
                    !empty($d["type_key"])
                ) {
                    $context["document_type"] = $d["type_key"];
                }
                if (
                    empty($context["document_type_label"]) &&
                    !empty($d["type_key"])
                ) {
                    $types = function_exists("document_type_options")
                        ? document_type_options()
                        : [];
                    $context["document_type_label"] =
                        $types[(string) $d["type_key"]] ?? "Documento";
                }
                if (
                    empty($context["patient_link_id"]) &&
                    !empty($d["patient_link_id"])
                ) {
                    $context["patient_link_id"] = (int) $d["patient_link_id"];
                }
                if (
                    empty($context["patient_name"]) &&
                    !empty($d["patient_name"])
                ) {
                    $context["patient_name"] = $d["patient_name"];
                }
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    if ($entity === "consulta" && $id > 0) {
        try {
            $a = $cid
                ? one(
                    "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,reason FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                )
                : one(
                    "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,reason FROM pi_appointments WHERE id=?",
                    [$id],
                );
            if ($a) {
                foreach (
                    [
                        "patient_link_id",
                        "doctor_user_id",
                        "start_at",
                        "end_at",
                        "reason",
                    ]
                    as $k
                ) {
                    if (empty($context[$k]) && !empty($a[$k])) {
                        $context[$k] = $a[$k];
                    }
                }
                if (empty($context["patient_name"])) {
                    $n = audit_patient_name_by_link(
                        (int) $a["patient_link_id"],
                        $cid,
                    );
                    if ($n !== "") {
                        $context["patient_name"] = $n;
                    }
                }
                if (empty($context["doctor_name"])) {
                    $n = audit_user_name_lookup(
                        (int) $a["doctor_user_id"],
                        $cid,
                    );
                    if ($n !== "") {
                        $context["doctor_name"] = $n;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    if ($entity === "tarefa" && $id > 0) {
        try {
            $t = $cid
                ? one(
                    "SELECT t.id,t.title,t.assigned_to,td.patient_link_id,t.due_at FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id WHERE t.id=? AND t.clinic_id=?",
                    [$id, $cid],
                )
                : one(
                    "SELECT t.id,t.title,t.assigned_to,td.patient_link_id,t.due_at FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id WHERE t.id=?",
                    [$id],
                );
            if ($t) {
                if (empty($context["task_title"])) {
                    $context["task_title"] = $t["title"] ?? "";
                }
                if (
                    empty($context["assigned_name"]) &&
                    !empty($t["assigned_to"])
                ) {
                    $n = audit_user_name_lookup((int) $t["assigned_to"], $cid);
                    if ($n !== "") {
                        $context["assigned_name"] = $n;
                    }
                }
                if (
                    empty($context["patient_name"]) &&
                    !empty($t["patient_link_id"])
                ) {
                    $n = audit_patient_name_by_link(
                        (int) $t["patient_link_id"],
                        $cid,
                    );
                    if ($n !== "") {
                        $context["patient_name"] = $n;
                    }
                }
                if (empty($context["due_at"]) && !empty($t["due_at"])) {
                    $context["due_at"] = $t["due_at"];
                }
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    if ($entity === "comunicado" && $id > 0) {
        try {
            $n = $cid
                ? one(
                    "SELECT id,title FROM pi_notices WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                )
                : one("SELECT id,title FROM pi_notices WHERE id=?", [$id]);
            if ($n && empty($context["notice_title"])) {
                $context["notice_title"] = $n["title"] ?? "";
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    if ($entity === "lead" && $id > 0) {
        try {
            $l = $cid
                ? one(
                    "SELECT id,name,person_id FROM pi_leads WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                )
                : one("SELECT id,name,person_id FROM pi_leads WHERE id=?", [
                    $id,
                ]);
            if ($l && empty($context["target_name"])) {
                $context["target_name"] = $l["name"] ?? "";
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    if (
        in_array($entity, ["consultorio", "clinica", "assinatura"], true) &&
        $id > 0 &&
        empty($context["clinic_name"])
    ) {
        $n = audit_clinic_name_lookup($id);
        if ($n !== "") {
            $context["clinic_name"] = $n;
        }
    }
    if (
        empty($context["environment_label"]) &&
        !empty($context["role_code"]) &&
        function_exists("role_label_for")
    ) {
        try {
            $context["environment_label"] = role_label_for(
                (string) $context["role_code"],
                $cid ?: null,
            );
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    return $context;
}
function audit_should_write(string $event): bool
{

    if ($event === "janela_aberta" && !PRONTOO_AUDIT_PAGE_VIEWS) {
        return false;
    }
    return true;
}

function audit_trusted_origin_resolve(
    array &$context,
    ?array $trustedOrigin,
): array {
    foreach (array_keys($context) as $key) {
        if (
            str_starts_with((string) $key, "_audit_") ||
            str_starts_with((string) $key, "_skip_")
        ) {
            unset($context[$key]);
        }
    }
    $origin = is_array($trustedOrigin) ? $trustedOrigin : [];
    $createdAt = trim((string) ($origin["created_at"] ?? ""));
    if (
        preg_match(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $createdAt,
        ) !== 1
    ) {
        $createdAt = "";
    }
    $ipHash = strtolower(trim((string) ($origin["ip_hash"] ?? "")));
    if (preg_match('/^[a-f0-9]{64}$/', $ipHash) !== 1) {
        $ipHash = "";
    }
    return [
        "skip_runtime_context" => !empty(
            $origin["skip_runtime_context"]
        ),
        "skip_context_enrichment" => !empty(
            $origin["skip_context_enrichment"]
        ),
        "has_user_id" => array_key_exists("user_id", $origin),
        "user_id" => max(0, (int) ($origin["user_id"] ?? 0)),
        "has_ip_hash" => array_key_exists("ip_hash", $origin),
        "ip_hash" => $ipHash,
        "has_user_agent" => array_key_exists("user_agent", $origin),
        "user_agent" => mb_substr(
            trim((string) ($origin["user_agent"] ?? "")),
            0,
            180,
        ),
        "created_at" => $createdAt,
        "proof_context" =>
            isset($origin["proof_context"]) &&
            is_array($origin["proof_context"])
                ? $origin["proof_context"]
                : null,
    ];
}
function audit(
    string $event,
    ?string $entity = null,
    mixed $entityId = null,
    array $context = [],
    ?array $trustedOrigin = null,
): bool {

    if (!has_cfg() || !audit_should_write($event)) {
        return false;
    }
    try {
        db_tx(function () use (
            $event,
            $entity,
            $entityId,
            $context,
            $trustedOrigin,
        ): void {

            $origin = audit_trusted_origin_resolve(
                $context,
                $trustedOrigin,
            );
            $skipRuntimeContext = (bool) $origin["skip_runtime_context"];
            $skipContextEnrichment = (bool) $origin[
                "skip_context_enrichment"
            ];
            $hasForcedUser = (bool) $origin["has_user_id"];
            $forcedUserId = (int) $origin["user_id"];
            $hasForcedIpHash = (bool) $origin["has_ip_hash"];
            $forcedIpHash = (string) $origin["ip_hash"];
            $hasForcedUserAgent = (bool) $origin["has_user_agent"];
            $forcedUserAgent = (string) $origin["user_agent"];
            $forcedCreatedAt = (string) $origin["created_at"];
            $forcedProofContext = $origin["proof_context"];
            $c = $skipRuntimeContext ? [] : ctx();
            $uid = $hasForcedUser
                ? ($forcedUserId > 0 ? $forcedUserId : null)
                : ((int) ($c["user"]["id"] ?? ($_SESSION["uid"] ?? 0)) ?:
                    null);
            $cid = $context["clinic_id"] ?? ($c["clinic_id"] ?? null);
            unset($context["clinic_id"]);
            if (($c["scope"] ?? "") === "clinic") {
                if (empty($context["role_code"]) && !empty($c["role"])) {
                    $context["role_code"] = (string) $c["role"];
                }
                if (
                    empty($context["environment_label"]) &&
                    !empty($c["role"]) &&
                    function_exists("role_label_for")
                ) {
                    $context["environment_label"] = role_label_for(
                        (string) $c["role"],
                        $cid ? (int) $cid : null,
                    );
                }
            }
            if (!$skipContextEnrichment) {
                $context = audit_enrich_context(
                    $event,
                    $entity,
                    $entityId,
                    $context,
                    $cid ? (int) $cid : null,
                );
            }
            if (trim((string) ($context["audit_body"] ?? "")) === "") {
                $context["audit_body"] = audit_body_for_event(
                    $event,
                    $entity,
                    $entityId,
                    $context,
                );
            }
            $friendly = mb_substr(
                audit_friendly($event, $entity, $entityId, $context, $uid),
                0,
                255,
            );
            $json = json_encode(
                mask($context),
                JSON_UNESCAPED_UNICODE |
                    JSON_HEX_TAG |
                    JSON_HEX_APOS |
                    JSON_HEX_AMP |
                    JSON_HEX_QUOT,
            );
            if ($json === false) {
                $json = "{}";
            }
            $eventLabel = event_label($event);
            $eventIcon = event_icon($event);
            $entityLabel = $entity !== null && $entity !== "" ? entity_label($entity) : null;
            $ip = substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45);
            $ipHash = $hasForcedIpHash
                ? ($forcedIpHash !== "" ? $forcedIpHash : null)
                : ($ip !== "" ? hash("sha256", $ip . "|ip") : null);
            $userAgent = $hasForcedUserAgent
                ? $forcedUserAgent
                : mb_substr(trim((string) ($_SERVER["HTTP_USER_AGENT"] ?? "")), 0, 180);
            if ($userAgent === "") {
                $userAgent = null;
            }
            $row = [
                "clinic_id" => $cid,
                "user_id" => $uid,
                "event_key" => $event,
                "entity_key" => $entity,
                "entity_id" => (string) $entityId,
                "friendly_text" => $friendly,
                "context_json" => $json,
            ];
            $proof = \Prontoo\Core\Integrity\AuditChain::build(
                $row,
                secret_key(),
                $forcedProofContext,
            );
            q(
                "INSERT INTO pi_audit (clinic_id,user_id,event_key,event_label,event_icon,entity_key,entity_label,entity_id,friendly_text,context_json,integrity_hash,previous_hash,chain_hash,proof_hash,proof_json,policy_version,ip_hash,user_agent,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,COALESCE(?,NOW()))",
                [
                    $cid,
                    $uid,
                    $event,
                    $eventLabel,
                    $eventIcon,
                    $entity,
                    $entityLabel,
                    (string) $entityId,
                    $friendly,
                    $json,
                    $proof["integrity_hash"],
                    $proof["previous_hash"],
                    $proof["chain_hash"],
                    $proof["proof_hash"],
                    $proof["proof_json"],
                    $proof["policy_version"],
                    $ipHash,
                    $userAgent,
                    $forcedCreatedAt !== "" ? $forcedCreatedAt : null,
                ],
            );
        });
        return true;
    } catch (Throwable $e) {
        error_log("[Prontoo audit] " . $e->getMessage());
        return false;
    }
}
function audit_actor_name(array $ctx, ?int $uid): string
{

    $actor = trim((string) ($ctx["actor_name"] ?? ""));
    return $actor !== "" ? first_name($actor) : user_name_by_id($uid);
}
function audit_document_type_text(array $ctx): string
{

    $label = trim((string) ($ctx["document_type_label"] ?? ""));
    if ($label === "") {
        $key = (string) ($ctx["document_type"] ?? ($ctx["type_key"] ?? ""));
        $types = function_exists("document_type_options")
            ? document_type_options()
            : [];
        $label = $types[$key] ?? "";
    }
    if ($label === "") {
        $label = trim((string) ($ctx["titulo"] ?? "Documento"));
    }
    return $label !== "" ? $label : "Documento";
}
function audit_document_article(string $label): string
{

    $l = mb_strtolower(trim($label));
    foreach (["receita", "declaração", "solicitação", "orientação"] as $fem) {
        if (str_starts_with($l, $fem)) {
            return "uma";
        }
    }
    return "um";
}
function audit_document_activity_sentence(
    string $who,
    string $action,
    array $ctx,
): string {

    $doc = audit_document_type_text($ctx);
    $patient = trim((string) ($ctx["patient_name"] ?? ""));
    $txt =
        $who . " " . $action . " " . audit_document_article($doc) . " " . $doc;
    if ($patient !== "") {
        $txt .= ($action === "visualizou" ? " de " : " para ") . $patient;
    }
    return $txt . ".";
}
function audit_model_title(array $ctx): string
{

    $t = trim(
        (string) ($ctx["template_title"] ??
            ($ctx["titulo"] ?? ($ctx["title"] ?? ($ctx["modelo"] ?? "")))),
    );
    return $t !== "" ? $t : "documento";
}
function audit_model_activity_sentence(
    string $who,
    string $action,
    array $ctx,
): string {

    $model = audit_model_title($ctx);
    return match ($action) {
        "criou" => "$who criou um modelo de $model.",
        "alterou" => "$who alterou o modelo $model.",
        "aprovou" => "$who aprovou o modelo $model.",
        "rejeitou" => "$who rejeitou o modelo $model.",
        default => "$who alterou o modelo $model.",
    };
}
function audit_direct_events(): array
{

    return [
        "janela_aberta",
        "consulta_agendada",
        "consulta_alterada",
        "consulta_excluida",
        "consulta_iniciada",
        "paciente_chegou",
        "agenda_bloqueada",
        "bloqueio_alterado",
        "bloqueio_removido",
        "lead_criado",
        "lead_atualizado",
        "paciente_salvo",
        "paciente_excluido",
        "paciente_recuperado",
        "registro_clinico",
        "prontuario_alterado",
        "prontuario_visualizado",
        "receita_visualizada",
        "documento_rascunho_criado",
        "documento_emitido",
        "documento_descartado",
        "documento_visualizado",
        "documento_impresso",
        "modelo_documento_criado",
        "modelo_documento_atualizado",
        "modelo_documento_aprovado",
        "modelo_documento_rejeitado",
        "procedimento_criado",
        "procedimento_atualizado",
        "procedimento_status",
        "tarefa_criada",
        "tarefa_iniciada",
        "tarefa_concluida",
        "tarefa_devolvida_fila",
        "comentario_tarefa_criado",
        "comentario_tarefa_editado",
        "comentario_tarefa_excluido",
        "notificacao_tarefa_individual",
        "comunicado_criado",
        "leitura_confirmada",
        "usuario_salvo",
        "usuario_status",
        "usuario_desativado",
        "senha_redefinida",
        "permissoes_atualizadas",
        "consultorio_criado",
        "consultorio_atualizado",
        "clinica_criada",
        "clinica_atualizada",
        "clinica_status",
        "onboarding_concluido",
        "entrada_realizada",
        "entrada_automatica_dispositivo",
        "saida_realizada",
        "login_clinica_pendente",
        "login_credencial_pendente",
        "falha_entrada",
        "acesso_negado",
        "csrf_bloqueado",
        "bloqueio_login_removido",
        "erro_marcado_resolvido",
        "aviso_global_criado",
        "aviso_global_status",
        "manutencao_atualizada",
        "config_global_atualizada",
        "assinatura_atualizada",
        "somente_leitura_bloqueio",
        "meta_financeira_salva",
        "conta_bancaria_criada",
        "forma_pagamento_salva",
        "credor_salvo",
        "despesa_cadastrada",
        "recebivel_destinado",
    ];
}
function audit_task_name(array $ctx): string
{

    $t = trim(
        (string) ($ctx["task_title"] ??
            ($ctx["titulo"] ?? ($ctx["title"] ?? ""))),
    );
    return $t !== "" ? $t : "tarefa";
}
function audit_task_sentence(
    string $who,
    string $verb,
    array $ctx,
    string $suffix = "",
): string {

    $task = audit_task_name($ctx);
    $txt = $who . " " . $verb . " tarefa " . $task;
    $patient = trim((string) ($ctx["patient_name"] ?? ""));
    if (
        $patient !== "" &&
        !str_contains(mb_strtolower($txt), mb_strtolower($patient))
    ) {
        $txt .= " para " . $patient;
    }
    if ($suffix !== "") {
        $txt .= " " . $suffix;
    }
    return $txt . ".";
}
function audit_notice_name(array $ctx): string
{

    $t = trim(
        (string) ($ctx["notice_title"] ??
            ($ctx["titulo"] ?? ($ctx["title"] ?? ""))),
    );
    return $t !== "" ? $t : "comunicado";
}
function audit_lead_name(array $ctx): string
{

    $t = trim(
        (string) ($ctx["target_name"] ??
            ($ctx["name"] ?? ($ctx["nome"] ?? ""))),
    );
    return $t !== "" ? $t : "interessado";
}
function audit_status_verb(
    array $ctx,
    string $active = "ativou",
    string $inactive = "desativou",
    string $changed = "alterou",
): string {

    $s = mb_strtolower(
        trim((string) ($ctx["status"] ?? ($ctx["active"] ?? ""))),
    );
    if (
        in_array(
            $s,
            ["ativo", "ativa", "1", "sim", "true", "liberado", "liberada"],
            true,
        )
    ) {
        return $active;
    }
    if (
        in_array(
            $s,
            [
                "inativo",
                "inativa",
                "0",
                "nao",
                "não",
                "false",
                "bloqueado",
                "bloqueada",
            ],
            true,
        )
    ) {
        return $inactive;
    }
    return $changed;
}
function audit_route_name(array $ctx): string
{

    $r = trim(
        (string) ($ctx["janela"] ?? ($ctx["rota"] ?? ($ctx["route"] ?? ""))),
    );
    return $r !== "" ? $r : "função restrita";
}
function audit_global_notice_title(array $ctx): string
{

    $t = trim(
        (string) ($ctx["notice_title"] ??
            ($ctx["title"] ?? ($ctx["titulo"] ?? ""))),
    );
    return $t !== "" ? $t : "aviso global";
}
function audit_subscription_target(array $ctx): string
{

    $cl = audit_clinic_target($ctx);
    $status = trim(
        (string) ($ctx["subscription_status"] ?? ($ctx["status"] ?? "")),
    );
    return $status !== "" ? $cl . " para " . $status : $cl;
}
function audit_friendly(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
    ?int $uid,
): string {

    return activity_direct_title($event, $entity, $entityId, $ctx, $uid);
}
function event_label(string $e): string
{

    return [
        "janela_aberta" => "Tela consultada",
        "login_carregado" => "Login carregado",
        "autoteste_aviso" => "Aviso de autoteste",
        "consulta_agendada" => "Consulta criada",
        "consulta_alterada" => "Consulta modificada",
        "consulta_excluida" => "Consulta excluída/cancelada",
        "consulta_iniciada" => "Consulta iniciada",
        "paciente_chegou" => "Chegada registrada",
        "agenda_bloqueada" => "Bloqueio criado",
        "bloqueio_alterado" => "Bloqueio modificado",
        "bloqueio_removido" => "Bloqueio excluído",
        "lead_criado" => "Interessado criado",
        "lead_atualizado" => "Interessado modificado",
        "paciente_salvo" => "Paciente criado/modificado",
        "paciente_excluido" => "Paciente excluído",
        "paciente_recuperado" => "Paciente recuperado",
        "aba_paciente_criada" => "Aba de paciente criada",
        "responsavel_legal_salvo" => "Responsável Legal salvo",
        "responsavel_legal_removido" => "Responsável Legal removido",
        "registro_clinico" => "Prontuário modificado",
        "prontuario_alterado" => "Prontuário modificado",
        "prontuario_visualizado" => "Prontuário consultado",
        "receita_visualizada" => "Receita consultada",
        "documento_rascunho_criado" => "Rascunho de documento criado",
        "documento_emitido" => "Documento emitido",
        "documento_descartado" => "Rascunho excluído",
        "documento_visualizado" => "Documento consultado",
        "documento_impresso" => "Documento impresso",
        "modelo_documento_criado" => "Modelo criado",
        "modelo_documento_atualizado" => "Modelo modificado",
        "modelo_documento_aprovado" => "Modelo aprovado",
        "modelo_documento_rejeitado" => "Modelo rejeitado",
        "procedimento_criado" => "Procedimento criado",
        "procedimento_atualizado" => "Procedimento modificado",
        "procedimento_status" => "Status de procedimento modificado",
        "tarefa_criada" => "Tarefa criada",
        "tarefa_iniciada" => "Tarefa iniciada",
        "tarefa_concluida" => "Tarefa concluída",
        "tarefa_devolvida_fila" => "Tarefa devolvida",
        "comentario_tarefa_criado" => "Comentário criado",
        "comentario_tarefa_editado" => "Comentário modificado",
        "comentario_tarefa_excluido" => "Comentário excluído",
        "notificacao_tarefa_individual" => "Aviso de tarefa",
        "comunicado_criado" => "Comunicado criado",
        "leitura_confirmada" => "Leitura confirmada",
        "usuario_salvo" => "Colaborador criado/modificado",
        "usuario_status" => "Status de colaborador modificado",
        "usuario_desativado" => "Colaborador desativado",
        "senha_redefinida" => "Senha modificada",
        "permissoes_atualizadas" => "Permissões modificadas",
        "consultorio_criado" => "Consultório criado",
        "consultorio_atualizado" => "Consultório modificado",
        "clinica_criada" => "Consultório criado",
        "clinica_atualizada" => "Consultório modificado",
        "clinica_status" => "Status do consultório modificado",
        "entrada_realizada" => "Entrada realizada",
        "entrada_automatica_dispositivo" =>
            "Entrada automática por dispositivo",
        "saida_realizada" => "Saída realizada",
        "falha_entrada" => "Falha de entrada",
        "acesso_negado" => "Consulta negada",
        "csrf_bloqueado" => "Ação bloqueada",
        "bloqueio_login_removido" => "Bloqueio de entrada removido",
        "erro_marcado_resolvido" => "Incidente resolvido",
        "aviso_global_criado" => "Aviso global criada",
        "aviso_global_status" => "Status de aviso global",
        "manutencao_atualizada" => "Manutenção modificada",
        "config_global_atualizada" => "Configuração global modificada",
        "assinatura_ativada" => "Assinatura ativada",
        "assinatura_desativada" => "Assinatura desativada",
        "assinatura_atualizada" => "Assinatura modificada",
        "assinatura_pagamento_informado" => "Pagamento informado",
        "assinatura_pagamento_confirmado" => "Pagamento confirmado",
        "assinatura_pagamento_nao_confirmado" => "Pagamento recusado",
        "somente_leitura_bloqueio" => "Bloqueio por assinatura",
        "onboarding_concluido" => "Configuração inicial concluída",
        "meta_financeira_salva" => "Meta financeira modificada",
        "conta_bancaria_criada" => "Conta financeira criada",
        "forma_pagamento_salva" => "Forma de pagamento salva",
        "credor_salvo" => "Credor salvo",
        "despesa_cadastrada" => "Despesa criada",
        "recebivel_destinado" => "Recebível destinado",
    ][$e] ?? str_replace("_", " ", ucfirst($e));
}
function event_icon(string $e): string
{

    return [
        "janela_aberta" => "visibility",
        "login_carregado" => "login",
        "autoteste_aviso" => "health_and_safety",
        "entrada_realizada" => "login",
        "entrada_automatica_dispositivo" => "devices",
        "saida_realizada" => "logout",
        "falha_entrada" => "lock",
        "consulta_agendada" => "event_available",
        "consulta_alterada" => "edit_calendar",
        "consulta_excluida" => "event_busy",
        "consulta_iniciada" => "play_circle",
        "paciente_chegou" => "how_to_reg",
        "agenda_bloqueada" => "event_busy",
        "bloqueio_alterado" => "edit_calendar",
        "bloqueio_removido" => "event_available",
        "lead_criado" => "person_add",
        "lead_atualizado" => "manage_accounts",
        "paciente_salvo" => "patient_list",
        "paciente_excluido" => "delete",
        "paciente_recuperado" => "restore",
        "aba_paciente_criada" => "add_circle",
        "responsavel_legal_salvo" => "supervisor_account",
        "responsavel_legal_removido" => "person_remove",
        "registro_clinico" => "clinical_notes",
        "prontuario_alterado" => "clinical_notes",
        "prontuario_visualizado" => "folder_open",
        "receita_visualizada" => "medication",
        "documento_rascunho_criado" => "note_add",
        "documento_emitido" => "description",
        "documento_descartado" => "delete_sweep",
        "documento_visualizado" => "visibility",
        "documento_impresso" => "print",
        "modelo_documento_criado" => "post_add",
        "modelo_documento_atualizado" => "edit_document",
        "modelo_documento_aprovado" => "verified",
        "modelo_documento_rejeitado" => "block",
        "procedimento_criado" => "add_task",
        "procedimento_atualizado" => "edit_note",
        "procedimento_status" => "toggle_on",
        "tarefa_criada" => "add_task",
        "tarefa_iniciada" => "play_arrow",
        "tarefa_concluida" => "task_alt",
        "tarefa_devolvida_fila" => "undo",
        "comentario_tarefa_criado" => "add_comment",
        "comentario_tarefa_editado" => "edit_note",
        "comentario_tarefa_excluido" => "delete",
        "notificacao_tarefa_individual" => "notifications_active",
        "comunicado_criado" => "campaign",
        "leitura_confirmada" => "mark_email_read",
        "usuario_salvo" => "group_add",
        "usuario_status" => "toggle_on",
        "usuario_desativado" => "person_off",
        "senha_redefinida" => "password",
        "permissoes_atualizadas" => "shield_person",
        "consultorio_criado" => "add_business",
        "consultorio_atualizado" => "home_health",
        "clinica_criada" => "add_business",
        "clinica_atualizada" => "home_health",
        "clinica_status" => "domain_verification",
        "acesso_negado" => "gpp_bad",
        "csrf_bloqueado" => "security",
        "bloqueio_login_removido" => "lock_open",
        "erro_marcado_resolvido" => "bug_report",
        "aviso_global_criado" => "campaign",
        "aviso_global_status" => "campaign",
        "manutencao_atualizada" => "engineering",
        "config_global_atualizada" => "tune",
        "assinatura_ativada" => "paid",
        "assinatura_desativada" => "money_off",
        "assinatura_atualizada" => "payments",
        "assinatura_pagamento_informado" => "payments",
        "assinatura_pagamento_confirmado" => "verified",
        "assinatura_pagamento_nao_confirmado" => "report",
        "somente_leitura_bloqueio" => "lock",
        "onboarding_concluido" => "playlist_add_check",
        "meta_financeira_salva" => "finance_mode",
        "conta_bancaria_criada" => "account_balance",
        "forma_pagamento_salva" => "payments",
        "credor_salvo" => "business_center",
        "despesa_cadastrada" => "receipt_long",
        "recebivel_destinado" => "account_balance_wallet",
    ][$e] ?? "radio_button_checked";
}
function entity_label(?string $e): string
{

    return [
        "clinica" => "consultório",
        "usuario" => "colaborador",
        "consulta" => "consulta",
        "paciente" => "paciente",
        "lead" => "interessado",
        "bloqueio" => "bloqueio",
        "tarefa" => "tarefa",
        "comunicado" => "aviso",
        "prontuario" => "prontuário",
        "documento" => "documento",
        "janela" => "janela",
        "rota" => "rota",
        "procedimento" => "procedimento",
        "sistema" => "sistema",
        "consultorio" => "consultório",
        "permissoes" => "permissões",
        "erro" => "erro",
        "aviso_global" => "aviso global",
        "manutencao" => "manutenção",
        "configuracao" => "configuração",
        "assinatura" => "assinatura",
        "financeiro" => "financeiro",
        "login" => "entrada",
        "seguranca" => "segurança",
    ][$e ?? ""] ?? "sistema";
}
function audit_visibility_filter(array $c, string &$where, array &$params): void
{

    $cid = (int) $c["clinic_id"];
    $role = (string) $c["role"];
    $uid = (int) $c["user"]["id"];
    if ($role === "gerente") {
        return;
    }
    if ($role === "medico") {
        $ids = array_unique(
            array_merge(
                [$uid],
                team_user_ids_for_roles($cid, ["assistente", "recepcionista"]),
            ),
        );
        $ph = implode(",", array_fill(0, count($ids), "?"));
        $where .= " AND (user_id IN ($ph) OR event_key='janela_aberta')";
        $params = array_merge($params, $ids);
        return;
    }
    if ($role === "assistente") {
        $ids = array_unique(
            array_merge(
                [$uid],
                team_user_ids_for_roles($cid, ["recepcionista"]),
            ),
        );
        $ph = implode(",", array_fill(0, count($ids), "?"));
        $where .= " AND (user_id IN ($ph) OR event_key='janela_aberta')";
        $params = array_merge($params, $ids);
        return;
    }
    if ($role === "recepcionista") {
        $ids = array_unique(
            array_merge(
                [$uid],
                team_user_ids_for_roles($cid, ["recepcionista"]),
            ),
        );
        $ph = implode(",", array_fill(0, count($ids), "?"));
        $where .= " AND (user_id IN ($ph) OR entity_key IN ('paciente','consulta','lead') OR event_key IN ('paciente_salvo','consulta_agendada','lead_criado','janela_aberta'))";
        $params = array_merge($params, $ids);
        return;
    }
}
function recent_events(int $cid, int $uid, string $role): array
{

    $rows = audit_rows_light("clinic_id=?", [$cid], 12);
    return audit_items($rows);
}
function audit_preview_for_appointment(int $cid, int $appointmentId): string
{

    $rows = audit_rows_light(
        "clinic_id=? AND entity_key=? AND entity_id=?",
        [$cid, "consulta", (string) $appointmentId],
        3,
    );
    $items = audit_items($rows);
    if (!$items) {
        return '<div class="audit-mini compact-empty">' .
            icon("info") .
            "<span>Nenhum evento de atividade vinculado.</span></div>";
    }
    $h = '<div class="audit-mini compact-audit">';
    foreach ($items as $it) {
        $h .=
            "<div><span>" .
            icon($it["icon"] ?? "radio_button_checked") .
            "</span><p><b>" .
            e((string) ($it["title"] ?? "")) .
            "</b><small>" .
            e((string) ($it["body"] ?? "")) .
            "</small></p></div>";
    }
    return $h . "</div>";
}
function audit_team_filter_options(int $cid): array
{

    if ($cid <= 0) {
        return [];
    }
    $loader = static function () use ($cid): array {

        try {
            $rows = q(
                "SELECT DISTINCT u.id,u.name FROM pi_users u INNER JOIN pi_user_roles ur ON ur.user_id=u.id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1 ORDER BY u.name ASC",
                [$cid],
            )->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo audit team lookup] " . $e->getMessage());
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r["id"] ?? 0);
            $name = trim((string) ($r["name"] ?? ""));
            if ($id <= 0 || $name === "") {
                continue;
            }
            $out[$id] = preg_split("/\s+/u", $name)[0] ?? $name;
        }
        return $out;
    };
    if (function_exists("server_json_cache_remember") && server_json_cache_read_allowed()) {
        return (array) server_json_cache_remember(
            "lookup",
            server_json_cache_safe_key("audit_team", $cid),
            server_json_cache_ttl("lookup"),
            $loader,
            ["table:pi_users", "table:pi_user_roles", "scope:" . $cid],
        );
    }
    return $loader();
}
function audit_activity_day_name(
    string $day,
    int $cid = 0,
    ?array $c = null,
): string {

    $labels = [
        "Domingo",
        "Segunda",
        "Terça",
        "Quarta",
        "Quinta",
        "Sexta",
        "Sábado",
    ];
    try {
        $zone = new DateTimeZone(app_context_timezone($c, $cid));
        $dt = new DateTimeImmutable($day . " 12:00:00", $zone);
        return $labels[(int) $dt->format("w")] ?? "Dia";
    } catch (Throwable $e) {
        $ts = strtotime($day . " 12:00:00");
        return $labels[(int) date("w", $ts ?: time())] ?? "Dia";
    }
}
function audit_period_options(int $cid = 0, ?array $c = null): array
{

    $today = app_today_in_timezone($cid, $c);
    $before = date("Y-m-d", strtotime($today . " -2 days"));
    return [
        "today" => "Hoje",
        "yesterday" => "Ontem",
        "before_yesterday" => audit_activity_day_name($before, $cid, $c),
        "date" => "Escolher data",
    ];
}
function audit_period_clause(
    string $period,
    array &$params,
    int $cid,
    array $c,
    ?string $selectedDate = null,
): string {

    $opts = audit_period_options($cid, $c);
    if (!isset($opts[$period])) {
        $period = "today";
    }
    $today = app_today_in_timezone($cid, $c);
    if ($period === "yesterday") {
        $day = date("Y-m-d", strtotime($today . " -1 day"));
    } elseif ($period === "before_yesterday") {
        $day = date("Y-m-d", strtotime($today . " -2 days"));
    } elseif (
        $period === "date" &&
        is_string($selectedDate) &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)
    ) {
        $day = $selectedDate;
    } else {
        $day = $today;
    }
    [$start, $end] = app_local_day_utc_range($day, $cid, $c);
    $params[] = gmdate("Y-m-d H:i:s", (int) $start);
    $params[] = gmdate("Y-m-d H:i:s", (int) $end);
    return " AND a.created_at>=? AND a.created_at<?";
}
function audit_activity_url(array $extra = []): string
{

    $base = ["r" => "audit"];
    foreach (["member", "period", "date"] as $k) {
        if (isset($_GET[$k]) && trim((string) $_GET[$k]) !== "") {
            $base[$k] = (string) $_GET[$k];
        }
    }
    return href("audit", array_merge($base, $extra));
}
function page_audit(): void
{

    $c = require_can("audit");
    $cid = (int) $c["clinic_id"];
    $member = (int) ($_GET["member"] ?? 0);
    $period = (string) ($_GET["period"] ?? "today");
    $selectedDate = (string) ($_GET["date"] ?? "");
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        $selectedDate = app_today_in_timezone($cid, $c);
    }
    if (!isset(audit_period_options($cid, $c)[$period])) {
        $period = "today";
    }
    $limit = 10;
    $offset = max(0, (int) ($_GET["offset"] ?? 0));
    $where = "a.clinic_id=?";
    $p = [$cid];
    audit_visibility_filter($c, $where, $p);
    $where .= audit_period_clause($period, $p, $cid, $c, $selectedDate);
    $team = audit_team_filter_options($cid);
    if ($member > 0 && isset($team[$member])) {
        $where .= " AND a.user_id=?";
        $p[] = $member;
    }
    $rows = audit_rows_light($where, $p, $limit, $offset);
    if ((string) ($_GET["ajax"] ?? "") === "1") {
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header(
                "Cache-Control: no-store, no-cache, must-revalidate, max-age=0",
            );
        }
        echo json_encode(
            [
                "ok" => true,
                "html" => timeline(
                    audit_items($rows),
                    "Nenhuma atividade encontrada.",
                ),
                "next_offset" => $offset + count($rows),
                "has_more" => count($rows) === $limit,
            ],
            JSON_UNESCAPED_UNICODE,
        );
        return;
    }
    $baseFor = function (array $extra = []) use (
        $member,
        $period,
        $selectedDate,
    ): array {

        $base = [];
        if ($member > 0) {
            $base["member"] = $member;
        }
        if ($period === "date") {
            $base["date"] = $selectedDate;
        }
        return array_merge($base, $extra);
    };
    $memberChips =
        '<nav class="activity-filter-chips" aria-label="Filtrar por membro da equipe"><a class="activity-filter-chip ' .
        ($member <= 0 ? "active" : "") .
        '" href="' .
        href("audit", $baseFor(["period" => $period])) .
        '">' .
        icon("groups") .
        "<span>Todos</span></a>";
    foreach ($team as $id => $first) {
        $memberChips .=
            '<a class="activity-filter-chip ' .
            ($member === $id ? "active" : "") .
            '" href="' .
            href("audit", $baseFor(["member" => $id, "period" => $period])) .
            '">' .
            icon("person") .
            "<span>" .
            e($first) .
            "</span></a>";
    }
    $memberChips .= "</nav>";
    $periodChips =
        '<nav class="activity-period-filter" aria-label="Selecionar data das atividades">';
    foreach (audit_period_options($cid, $c) as $value => $label) {
        if ($value === "date") {
            $periodChips .=
                '<button type="button" class="activity-period-chip ' .
                ($period === "date" ? "active" : "") .
                '" data-open-activity-date>' .
                icon("calendar_month") .
                "<span>" .
                e($label) .
                "</span></button>";
        } else {
            $periodChips .=
                '<a class="activity-period-chip ' .
                ($period === $value ? "active" : "") .
                '" href="' .
                href("audit", $baseFor(["period" => $value])) .
                '">' .
                icon(
                    $value === "today"
                        ? "today"
                        : ($value === "yesterday"
                            ? "history"
                            : "event"),
                ) .
                "<span>" .
                e($label) .
                "</span></a>";
        }
    }
    $periodChips .= "</nav>";
    $dateDialog =
        '<dialog class="activity-date-dialog" data-activity-date-dialog><form method="get" class="activity-date-form"><input type="hidden" name="r" value="audit">' .
        ($member > 0
            ? '<input type="hidden" name="member" value="' .
                (int) $member .
                '">'
            : "") .
        '<input type="hidden" name="period" value="date"><header><strong>Escolher data</strong><button type="button" class="ghost small" data-close-activity-date>' .
        icon("close") .
        '<span>Fechar</span></button></header><input type="date" name="date" value="' .
        e($selectedDate) .
        '" required><div class="form-actions"><button class="primary" type="submit">' .
        icon("check") .
        "<span>Aplicar</span></button></div></form></dialog>";
    $filters =
        '<section class="activity-filter-panel ds-activity-filter-panel" aria-label="Filtros de atividades">' .
        $memberChips .
        $periodChips .
        $dateDialog .
        "</section>";
    $initial = timeline(audit_items($rows), "Nenhuma atividade encontrada.");
    $ajaxParams = ["ajax" => "1", "member" => $member, "period" => $period];
    if ($period === "date") {
        $ajaxParams["date"] = $selectedDate;
    }
    $list =
        '<div class="activity-timeline ds-activity-list" data-activity-results data-activity-next-offset="' .
        ($offset + count($rows)) .
        '" data-activity-limit="' .
        $limit .
        '" data-activity-url="' .
        e(href("audit", $ajaxParams)) .
        '">' .
        $initial .
        '</div><div class="activity-load-sentinel" data-activity-sentinel aria-hidden="true"></div>';
    $sectionHead =
        '<header class="ds-section-head activity-section-head"><span class="notice-minimal-icon">' .
        icon("history") .
        "</span><div><strong>Registro de atividades</strong><span>Filtros e linha do tempo em leitura compacta.</span></div></header>";
    page(
        "Atividades",
        page_head(
            "Atividades",
            "Histórico direto das ações realizadas no consultório.",
        ) .
            card(
                $sectionHead . $filters . $list,
                "activity-screen-card patient-list-card ds-filter-list-block",
            ),
    );
}
