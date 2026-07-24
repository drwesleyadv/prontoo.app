<?php
declare(strict_types=1);
function e(mixed $v): string
{
    /*
     * GUIA DE MANUTENÇÃO — e
     * Responsabilidade: Implementa a responsabilidade “e” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `admin_scope_evidence_html`, `stat_link_card`, `admin_choice_card`, `admin_quick_links`, `admin_global_compact_pill`, `admin_metric_line_chart`, `admin_metric_dual_area_chart`, `admin_metric_bar_chart` e mais 155.
     * Dependências chamadas: `htmlspecialchars`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}
function first_name(?string $name): string
{
    /*
     * GUIA DE MANUTENÇÃO — first_name
     * Responsabilidade: Implementa a responsabilidade “first name” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `admin_scope_evidence_html`, `page_admin_deleted`, `admin_alert_contact_label`, `agenda_conflict_message`, `agenda_crown_label_for_view`, `agenda_note_card_html`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3790` e mais 30.
     * Dependências chamadas: `preg_split`, `trim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $p = preg_split("/\s+/", trim((string) $name));
    return $p[0] ?: "Sistema";
}
function icon(string $name): string
{
    /*
     * GUIA DE MANUTENÇÃO — icon
     * Responsabilidade: Implementa a responsabilidade “icon” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `stat_link_card`, `admin_choice_card`, `admin_quick_links`, `admin_global_compact_pill`, `admin_global_ops_finance_html`, `admin_metric_line_chart`, `admin_metric_dual_area_chart`, `admin_metric_bar_chart` e mais 123.
     * Dependências chamadas: `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    static $aliases = [
        "auto_awesome" => "auto_awesome",
        "finance_mode" => "payments",
        "settings_suggest" => "tune",
        "database" => "storage",
        "schema" => "storage",
        "php" => "code",
        "folder_managed" => "folder",
        "folder_off" => "folder_off",
        "database_off" => "database_off",
        "playlist_add_check" => "fact_check",
        "request_quote" => "payments",
        "receipt_long" => "receipt_long",
        "account_balance_wallet" => "account_balance_wallet",
        "admin_panel_settings" => "admin_panel_settings",
        "health_metrics" => "health_metrics",
        "patient_list" => "patient_list",
        "person_search" => "person_search",
        "home_health" => "home_health",
        "groups" => "groups",
        "clinical_notes" => "clinical_notes",
    ];
    $name = $aliases[$name] ?? $name;
    return '<span class="material-symbols-rounded pt-icon-glyph" aria-hidden="true">' .
        e($name) .
        "</span>";
}
function pix_symbol(): string
{
    /*
     * GUIA DE MANUTENÇÃO — pix_symbol
     * Responsabilidade: Implementa a responsabilidade “pix symbol” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `clinic_subscription_cta`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return '<span class="pix-brand pix-brand-mask" aria-hidden="true"></span>';
}
function reception_cash_state_icon(?array $context = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — reception_cash_state_icon
     * Responsabilidade: Implementa a responsabilidade “reception cash state icon” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `role_actions`, `role_actions_effective`, `page`, `page_operation_specs`, `page_head_icon_name`.
     * Dependências chamadas: `is_array`, `function_exists`, `ctx`, `has_effective_role`, `financial_current_open_session`, `one`, `financial_today`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        $c = $context;
        if (!is_array($c) || !$c) {
            if (function_exists("ctx")) {
                $c = ctx();
            }
        }
        if (
            !is_array($c) ||
            ($c["scope"] ?? "") !== "clinic" ||
            !has_effective_role($c, "recepcionista")
        ) {
            return "point_of_sale";
        }
        $cid = (int) ($c["clinic_id"] ?? 0);
        $uid = (int) ($c["user"]["id"] ?? 0);
        if ($cid <= 0 || $uid <= 0) {
            return "lock_clock";
        }
        if (function_exists("financial_current_open_session")) {
            return financial_current_open_session($cid, $uid)
                ? "currency_exchange"
                : "lock_clock";
        }
        if (function_exists("one") && function_exists("financial_today")) {
            $open = one(
                "SELECT id FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date=? AND status='open' LIMIT 1",
                [$cid, $uid, financial_today($cid)],
            );
            return $open ? "currency_exchange" : "lock_clock";
        }
    } catch (Throwable $e) {
        return "lock_clock";
    }
    return "lock_clock";
}
function n(mixed $value): string
{
    /*
     * GUIA DE MANUTENÇÃO — n
     * Responsabilidade: Implementa a responsabilidade “n” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `stat_link_card`, `admin_global_perf_charts_html`, `page_admin_maintenance`, `admin_performance_rows_html`, `page_admin_performance`, `page_appointments`, `document_stage_strip`, `financial_admin_daily_conference_panel` e mais 2.
     * Dependências chamadas: `is_int`, `is_string`, `preg_match`, `number_format`, `is_float`, `is_numeric`, `trim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (
        is_int($value) ||
        (is_string($value) && preg_match('/^-?\d+$/', $value))
    ) {
        return number_format((int) $value, 0, ",", ".");
    }
    if (is_float($value) || is_numeric($value)) {
        return number_format((float) $value, 0, ",", ".");
    }
    $text = trim((string) $value);
    return $text !== "" ? $text : "0";
}
function money_br(int|float|string|null $cents): string
{
    /*
     * GUIA DE MANUTENÇÃO — money_br
     * Responsabilidade: Implementa a responsabilidade “money br” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `admin_global_ops_finance_html`, `page_admin_painel`, `page_admin_clinics`, `page_signup`, `procedure_option_label`, `procedure_select_html`, `audit_money_text`, `activity_money_from_ctx` e mais 35.
     * Dependências chamadas: `round`, `abs`, `number_format`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $value = (int) round((float) ($cents ?? 0));
    $sign = $value < 0 ? "-" : "";
    $value = abs($value);
    return $sign . 'R$ ' . number_format($value / 100, 2, ",", ".");
}
function prontoo_months_br(): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_months_br
     * Responsabilidade: Implementa a responsabilidade “prontoo months br” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `patient_reception_story_time`, `app_datetime_br`, `date_extenso_br`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return [
        1 => "Janeiro",
        2 => "Fevereiro",
        3 => "Março",
        4 => "Abril",
        5 => "Maio",
        6 => "Junho",
        7 => "Julho",
        8 => "Agosto",
        9 => "Setembro",
        10 => "Outubro",
        11 => "Novembro",
        12 => "Dezembro",
    ];
}
function date_br(null|string|int $value): string
{
    /*
     * GUIA DE MANUTENÇÃO — date_br
     * Responsabilidade: Implementa a responsabilidade “date br” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `admin_clinic_detail_page`, `page_admin_clinics`, `person_autosuggest_datalist`, `agenda_note_form_html`, `page_appointments`, `clinic_subscription_status_card`, `document_issue_context`, `financial_cashier_page` e mais 13.
     * Dependências chamadas: `trim`, `preg_match`, `app_date_br`, `strtotime`, `gmdate`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return "—";
    }
    if (preg_match('/^-?\d+$/', $value)) {
        return app_date_br($value);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $ts = strtotime($value . " 00:00:00 UTC");
        return $ts ? gmdate("d/m/Y", $ts) : $value;
    }
    return app_date_br($value);
}
function date_extenso_br(null|string|int $value): string
{
    /*
     * GUIA DE MANUTENÇÃO — date_extenso_br
     * Responsabilidade: Implementa a responsabilidade “date extenso br” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `document_issue_context`.
     * Dependências chamadas: `trim`, `preg_match`, `DateTimeImmutable::createFromFormat`, `DateTimeZone`, `app_db_utc_to_local`, `prontoo_months_br`, `->format`.
     * Classes ou serviços instanciados: `DateTimeZone`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return "—";
    }
    $dt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
        ? DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $value,
            new DateTimeZone("UTC"),
        )
        : app_db_utc_to_local($value);
    if (!$dt) {
        return $value;
    }
    $m = prontoo_months_br();
    return $dt->format("d") .
        " de " .
        $m[(int) $dt->format("n")] .
        " de " .
        $dt->format("Y");
}
function dt_br(null|string|int $value): string
{
    /*
     * GUIA DE MANUTENÇÃO — dt_br
     * Responsabilidade: Implementa a responsabilidade “dt br” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `admin_scope_guard_timeline_item`, `page_admin_deleted`, `page_admin_errors`, `admin_clinic_detail_page`, `page_admin_security`, `agenda_period_label`, `audit_appointment_target`, `audit_due_text` e mais 30.
     * Dependências chamadas: `app_datetime_br`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return app_datetime_br($value);
}
function dt_notice_br(null|string|int $value): string
{
    /*
     * GUIA DE MANUTENÇÃO — dt_notice_br
     * Responsabilidade: Implementa a responsabilidade “dt notice br” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_alerts`, `lead_history_html`, `page_admin_global_notices`, `readonly_support_notice_screen`, `page_notices`.
     * Dependências chamadas: `dt_br`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return dt_br($value);
}
function dt_card_full_br(null|string|int $value): string
{
    /*
     * GUIA DE MANUTENÇÃO — dt_card_full_br
     * Responsabilidade: Monta a representação de interface associada a “dt card full br” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `patient_profile_overview`, `page_patient`.
     * Dependências chamadas: `dt_br`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return dt_br($value);
}
function notification_button_light(array $c): string
{
    /*
     * GUIA DE MANUTENÇÃO — notification_button_light
     * Responsabilidade: Implementa a responsabilidade “notification button light” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page`.
     * Dependências chamadas: `db_table_exists`, `function_exists`, `notice_target_sql`, `array_merge`, `val`, `href`, `icon`, `min`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (($c["scope"] ?? "") !== "clinic") {
        return "";
    }
    $n = 0;
    try {
        $cid = (int) ($c["clinic_id"] ?? 0);
        $uid = (int) ($c["user"]["id"] ?? 0);
        if (
            $cid > 0 &&
            $uid > 0 &&
            db_table_exists("pi_notices") &&
            db_table_exists("pi_notice_reads")
        ) {
            [$targetSql, $targetParams] = function_exists("notice_target_sql")
                ? notice_target_sql($c, "n")
                : [
                    "(n.target_scope='all' OR (n.target_scope='role' AND n.target_role=?) OR (n.target_scope='user' AND n.target_user_id=?))",
                    [(string) ($c["role"] ?? ""), $uid],
                ];
            $params = array_merge([$cid], $targetParams, [$uid]);
            $n =
                (int) (val(
                    "SELECT COUNT(*) FROM pi_notices n WHERE n.clinic_id=? AND $targetSql AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND r.user_id=? AND (r.read_at IS NOT NULL OR r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1)",
                    $params,
                ) ?:
                0);
        }
    } catch (Throwable $e) {
        $n = 0;
    }
    return '<a class="notify-link top-icon" href="' .
        href("notices") .
        '" aria-label="Avisos" title="Avisos">' .
        icon("campaign") .
        ($n > 0 ? '<b class="badge">' . min(99, $n) . "</b>" : "") .
        "</a>";
}
function shared_goal_cmdbar_html(?array $c = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — shared_goal_cmdbar_html
     * Responsabilidade: Monta a representação de interface associada a “shared goal cmdbar html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page`.
     * Dependências chamadas: `is_array`, `function_exists`, `ctx`, `db_table_exists`, `date`, `one`, `in_array`, `strtotime`, `val`, `round`, `max`, `min` e mais 4.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        if (!is_array($c) || !$c) {
            if (function_exists("ctx")) {
                $c = ctx();
            }
        }
        if (!is_array($c) || ($c["scope"] ?? "") !== "clinic") {
            return "";
        }
        $cid = (int) ($c["clinic_id"] ?? 0);
        if (
            $cid <= 0 ||
            !function_exists("db_table_exists") ||
            !function_exists("one") ||
            !function_exists("val")
        ) {
            return "";
        }
        if (
            !db_table_exists("pi_financial_goals") ||
            !db_table_exists("pi_financial_revenues")
        ) {
            return "";
        }
        $month = app_month_in_timezone($cid, $c);
        $goal = one(
            "SELECT target_cents,base_metric,share_with_team FROM pi_financial_goals WHERE clinic_id=? AND month_key=? AND share_with_team=1 LIMIT 1",
            [$cid, $month],
        );
        if (!$goal) {
            return "";
        }
        $target = (int) ($goal["target_cents"] ?? 0);
        if ($target <= 0) {
            return "";
        }
        $base = (string) ($goal["base_metric"] ?? "efetivada");
        if (!in_array($base, ["prevista", "efetivada"], true)) {
            $base = "efetivada";
        }
        [$start, $next] = app_local_month_utc_range($month, $cid, $c);
        if ($base === "prevista") {
            $done =
                (int) (val(
                    "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.expected_at>=? AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))",
                    [$cid, $start, $next],
                ) ?? 0);
        } else {
            $done =
                (int) (val(
                    "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?",
                    [$cid, $start, $next],
                ) ?? 0);
        }
        $pct = $target > 0 ? round(($done / $target) * 100) : 0;
        $pct = max(0, min(100, (int) $pct));
        $label = "Meta compartilhada: " . $pct . "%";
        return '<div class="cmdbar-shared-goal" role="group" aria-label="' .
            e($label) .
            '" title="Meta compartilhada: ' .
            e((string) $pct) .
            '%">' .
            icon("flag") .
            '<div class="cmdbar-shared-goal-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' .
            e((string) $pct) .
            '" aria-label="' .
            e($label) .
            '"><i style="width:' .
            e((string) $pct) .
            '%"></i></div><strong>' .
            e((string) $pct) .
            "%</strong></div>";
    } catch (Throwable $e) {
        error_log("[Prontoo shared goal cmdbar] " . $e->getMessage());
        return "";
    }
}
function city_state_label(?string $city, ?string $uf): string
{
    /*
     * GUIA DE MANUTENÇÃO — city_state_label
     * Responsabilidade: Monta a representação de interface associada a “city state label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `document_issue_context`, `patient_profile_overview`, `page_patient`.
     * Dependências chamadas: `trim`, `strtoupper`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $city = trim((string) ($city ?? ""));
    $uf = strtoupper(trim((string) ($uf ?? "")));
    if ($city === "" && $uf === "") {
        return "";
    }
    if ($city === "") {
        return $uf;
    }
    if ($uf === "") {
        return $city;
    }
    return $city . " - " . $uf;
}
function cmdbar_access_schema_ready(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — cmdbar_access_schema_ready
     * Responsabilidade: Opera a etapa “cmdbar access schema ready” do contrato de banco e instalação, restrita às janelas autorizadas.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `cmdbar_access_touch`, `cmdbar_access_recency`.
     * Dependências chamadas: `function_exists`, `has_cfg`, `db_table_exists`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (
        !function_exists("has_cfg") ||
        !has_cfg() ||
        !function_exists("db_table_exists")
    ) {
        return $ready = false;
    }
    return $ready = db_table_exists("pi_user_cmdbar_access");
}

function cmdbar_context_key(array $c): array
{
    /*
     * GUIA DE MANUTENÇÃO — cmdbar_context_key
     * Responsabilidade: Implementa a responsabilidade “cmdbar context key” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `cmdbar_access_touch`, `cmdbar_access_recency`.
     * Dependências chamadas: `max`, `substr`, `preg_replace`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $uid = (int) ($c["user"]["id"] ?? 0);
    $scope = (string) ($c["scope"] ?? "clinic") === "global" ? "global" : "clinic";
    $clinic = (int) ($c["clinic_id"] ?? 0);
    if ($scope === "global") {
        $clinic = 0;
    }
    $role = $scope === "global" ? "administrador" : (string) ($c["role"] ?? "");
    return [
        $uid,
        $scope,
        max(0, $clinic),
        substr(preg_replace("/[^a-z0-9_\-]/i", "", $role) ?: "", 0, 40),
    ];
}
function cmdbar_parent_key(
    string $route,
    array $actions,
    bool $global = false,
): string {
    /*
     * GUIA DE MANUTENÇÃO — cmdbar_parent_key
     * Responsabilidade: Implementa a responsabilidade “cmdbar parent key” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `cmdbar_order_items`, `page`.
     * Dependências chamadas: `function_exists`, `admin_nav_parent`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    if ($global && function_exists("admin_nav_parent")) {
        $parent = admin_nav_parent($route);
        if (isset($actions[$parent])) {
            return $parent;
        }
    }
    if (isset($actions[$route])) {
        return $route;
    }
    $map = [
        "admin_stats" => "admin_painel",
        "admin_operations" => "admin_painel",
        "admin_onboarding" => "admin_clinics",
        "admin_users" => "admin_clinics",
        "admin_people" => "admin_clinics",
        "admin_payment_proof" => "admin_clinics",
        "admin_global_notices" => "admin_maintenance",
        "admin_alerts" => "admin_alerts",
        "admin_deleted" => "admin_maintenance",
        "admin_errors" => "admin_health",
        "admin_diagnostics" => "admin_health",
        "admin_integrity" => "admin_health",
        "admin_security" => "admin_health",
        "admin_audit" => "admin_health",
        "painel" => "appointments",
        "patient" => "patients",
        "patient_lookup" => "patients",
        "patient_suggest" => "patients",
        "person_lookup" => "patients",
        "leads" => "patients",
        "lead_lookup" => "patients",
        "lead_patient_lookup" => "patients",
        "creditors" => "patients",
        "documents" => "documents",
        "document_view" => "documents",
        "document_print" => "documents",
        "document_pdf" => "documents",
        "document_pdf_file" => "documents",
        "counterparty_lookup" => "financial",
        "counterparty_suggest" => "financial",
        "goal_status" => "financial",
        "procedures" => "settings",
        "users" => "settings",
        "user" => "settings",
        "permissions" => "settings",
        "operations" => "settings",
        "audit" => "patients",
    ];
    $parent = $map[$route] ?? "";
    return $parent !== "" && isset($actions[$parent]) ? $parent : "";
}
function cmdbar_access_touch(array $c, string $actionKey): void
{
    /*
     * GUIA DE MANUTENÇÃO — cmdbar_access_touch
     * Responsabilidade: Implementa a responsabilidade “cmdbar access touch” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `cmdbar_order_items`.
     * Dependências chamadas: `cmdbar_context_key`, `cmdbar_access_schema_ready`, `q`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    if ($actionKey === "") {
        return;
    }
    [$uid, $scope, $clinic, $role] = cmdbar_context_key($c);
    if ($uid <= 0 || !cmdbar_access_schema_ready()) {
        return;
    }
    try {
        q(
            "INSERT INTO pi_user_cmdbar_access (user_id,scope,clinic_scope_id,role_code,action_key,last_accessed_at,access_count,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),1,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_accessed_at=VALUES(last_accessed_at), access_count=access_count+1, updated_at=NOW()",
            [$uid, $scope, $clinic, $role, $actionKey],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo cmdbar access touch] " . $e->getMessage());
    }
}
function cmdbar_access_recency(array $c, array $keys): array
{
    /*
     * GUIA DE MANUTENÇÃO — cmdbar_access_recency
     * Responsabilidade: Implementa a responsabilidade “cmdbar access recency” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `array_values`, `array_unique`, `array_filter`, `array_map`, `cmdbar_context_key`, `cmdbar_access_schema_ready`, `implode`, `array_fill`, `count`, `array_merge`, `q`, `->fetchAll` e mais 3.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    $keys = array_values(
        array_unique(array_filter(array_map("strval", $keys))),
    );
    if (!$keys) {
        return [];
    }
    [$uid, $scope, $clinic, $role] = cmdbar_context_key($c);
    if ($uid <= 0 || !cmdbar_access_schema_ready()) {
        return [];
    }
    try {
        $ph = implode(",", array_fill(0, count($keys), "?"));
        $params = array_merge([$uid, $scope, $clinic, $role], $keys);
        $rows = q(
            "SELECT action_key,last_accessed_at FROM pi_user_cmdbar_access WHERE user_id=? AND scope=? AND clinic_scope_id=? AND role_code=? AND action_key IN ($ph)",
            $params,
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r["action_key"]] =
                strtotime((string) $r["last_accessed_at"]) ?: 0;
        }
        return $out;
    } catch (Throwable $e) {
        error_log("[Prontoo cmdbar access recency] " . $e->getMessage());
        return [];
    }
}
function cmdbar_order_items(
    array $items,
    string $current,
    array $c,
    bool $global = false,
): array {
    /*
     * GUIA DE MANUTENÇÃO — cmdbar_order_items
     * Responsabilidade: Implementa a responsabilidade “cmdbar order items” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page`.
     * Dependências chamadas: `cmdbar_parent_key`, `cmdbar_access_touch`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $active = cmdbar_parent_key($current, $items, $global);
    if ($active !== "") {
        cmdbar_access_touch($c, $active);
    }
    return $items;
}
function cmdbar_label_html(
    string $label,
    bool $active,
    string $extra = "",
): string {
    /*
     * GUIA DE MANUTENÇÃO — cmdbar_label_html
     * Responsabilidade: Monta a representação de interface associada a “cmdbar label html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page`.
     * Dependências chamadas: `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return $active ? "<span>" . e($label) . "</span>" . $extra : "";
}
function floating_pending_task_access_sql(array $c, string $alias = "t"): array
{
    /*
     * GUIA DE MANUTENÇÃO — floating_pending_task_access_sql
     * Responsabilidade: Implementa a responsabilidade “floating pending task access sql” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `floating_pending_cards_html`.
     * Dependências chamadas: `function_exists`, `has_effective_role`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $uid = (int) ($c["user"]["id"] ?? 0);
    $role = (string) ($c["role"] ?? "");
    $a = $alias !== "" ? $alias . "." : "";
    $scope =
        "COALESCE(NULLIF(" .
        $a .
        "target_scope,''),CASE WHEN " .
        $a .
        "target_user_id IS NOT NULL THEN 'user' WHEN NULLIF(" .
        $a .
        "target_role,'') IS NOT NULL THEN 'role' ELSE 'clinic' END)";
    $clinicWide =
        function_exists("has_effective_role") &&
        has_effective_role($c, "gerente")
            ? " OR " . $scope . "='clinic'"
            : "";
    $sql =
        "(" .
        $a .
        "assigned_to=? OR (" .
        $a .
        "assigned_to IS NULL AND ((" .
        $scope .
        "='role' AND " .
        $a .
        "target_role=?) OR (" .
        $scope .
        "='user' AND " .
        $a .
        "target_user_id=?)" .
        $clinicWide .
        ")))";
    return [$sql, [$uid, $role, $uid]];
}
function floating_pending_count(string $sql, array $params = []): int
{
    /*
     * GUIA DE MANUTENÇÃO — floating_pending_count
     * Responsabilidade: Implementa a responsabilidade “floating pending count” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `floating_pending_cards_html`.
     * Dependências chamadas: `val`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        return (int) (val($sql, $params) ?: 0);
    } catch (Throwable $e) {
        error_log("[Prontoo floating pending count] " . $e->getMessage());
        return 0;
    }
}
function floating_pending_cards_html(array $c, string $current): string
{
    /*
     * GUIA DE MANUTENÇÃO — floating_pending_cards_html
     * Responsabilidade: Monta a representação de interface associada a “floating pending cards html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page`.
     * Dependências chamadas: `function_exists`, `has_cfg`, `db_table_exists`, `floating_pending_task_access_sql`, `array_merge`, `floating_pending_count`, `app_local_day_utc_range`, `app_today_in_timezone`, `href`, `notice_target_sql`, `error_log`, `->getMessage` e mais 5.
     * Efeitos colaterais: consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (($c["scope"] ?? "") !== "clinic") {
        return "";
    }
    if (!function_exists("has_cfg") || !has_cfg()) {
        return "";
    }
    $cid = (int) ($c["clinic_id"] ?? 0);
    $uid = (int) ($c["user"]["id"] ?? 0);
    if ($cid <= 0 || $uid <= 0) {
        return "";
    }
    $cards = [];
    $active = "t.status IN ('aberta','em_andamento','aguardando')";
    if (function_exists("db_table_exists") && db_table_exists("pi_tasks")) {
        [$access, $accessParams] = floating_pending_task_access_sql($c, "t");
        $base = array_merge([$cid], $accessParams);
        $overdue = floating_pending_count(
            "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND $active AND ($access) AND t.due_at IS NOT NULL AND t.due_at<NOW()",
            $base,
        );
        $today = 0;
        try {
            if (
                function_exists("app_today_in_timezone") &&
                function_exists("app_local_day_utc_range")
            ) {
                [$start, $end] = app_local_day_utc_range(
                    app_today_in_timezone($cid, $c),
                    $cid,
                    $c,
                );
                $today = floating_pending_count(
                    "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND $active AND ($access) AND t.due_at IS NOT NULL AND t.due_at>=? AND t.due_at<?",
                    array_merge($base, [$start, $end]),
                );
            } else {
                $today = floating_pending_count(
                    "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND $active AND ($access) AND t.due_at IS NOT NULL AND t.due_at>=CURDATE() AND t.due_at<DATE_ADD(CURDATE(), INTERVAL 1 DAY)",
                    $base,
                );
            }
        } catch (Throwable $e) {
            $today = 0;
        }
        $mine = floating_pending_count(
            "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND $active AND ($access)",
            $base,
        );
        $progress = floating_pending_count(
            "SELECT COUNT(*) FROM pi_tasks t WHERE t.clinic_id=? AND t.status='em_andamento' AND (t.assigned_to=? OR t.started_by=?)",
            [$cid, $uid, $uid],
        );
        if ($overdue > 0) {
            $cards[] = [
                "tasks overdue",
                "task_alt",
                (string) $overdue,
                "Tarefa",
                href("tasks", ["view" => "overdue"]),
                $overdue === 1
                    ? "1 tarefa atrasada"
                    : $overdue . " tarefas atrasadas",
            ];
        } elseif ($today > 0) {
            $cards[] = [
                "tasks today",
                "task_alt",
                (string) $today,
                "Tarefa",
                href("tasks", ["view" => "today"]),
                $today === 1
                    ? "1 tarefa vence hoje"
                    : $today . " tarefas vencem hoje",
            ];
        } elseif ($progress > 0) {
            $cards[] = [
                "tasks progress",
                "task_alt",
                (string) $progress,
                "Tarefa",
                href("tasks", ["view" => "progress"]),
                $progress === 1
                    ? "1 tarefa em andamento"
                    : $progress . " tarefas em andamento",
            ];
        } elseif ($mine > 0) {
            $cards[] = [
                "tasks",
                "task_alt",
                (string) $mine,
                "Tarefa",
                href("tasks"),
                $mine === 1 ? "1 tarefa aberta" : $mine . " tarefas abertas",
            ];
        }
    }
    if (
        function_exists("db_table_exists") &&
        db_table_exists("pi_notices") &&
        db_table_exists("pi_notice_reads") &&
        function_exists("notice_target_sql")
    ) {
        try {
            [$targetSql, $targetParams] = notice_target_sql($c, "n");
            $notice = floating_pending_count(
                "SELECT COUNT(*) FROM pi_notices n WHERE n.clinic_id=? AND n.requires_ack=1 AND $targetSql AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND r.user_id=? AND (r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1)",
                array_merge([$cid], $targetParams, [$uid]),
            );
            if ($notice > 0) {
                $cards[] = [
                    "notices",
                    "campaign",
                    (string) $notice,
                    "Avisos",
                    href("notices"),
                    $notice === 1
                        ? "1 aviso aguardando ciência"
                        : $notice . " avisos aguardando ciência",
                ];
            }
        } catch (Throwable $e) {
            error_log("[Prontoo floating pending notices] " . $e->getMessage());
        }
    }
    if (!$cards) {
        return "";
    }
    $cardHtml = "";
    foreach (array_slice($cards, 0, 2) as $card) {
        [$kind, $ico, $value, $label, $url, $hint] = $card;
        $kindClass = str_replace(
            " ",
            " context-floating-pending-card-",
            preg_replace("/[^a-z0-9_\- ]/i", "", (string) $kind),
        );
        $aria = $label . ": " . $hint;
        $cardHtml .=
            '<a class="agenda-floating-card context-floating-pending-card context-floating-pending-card-' .
            e($kindClass) .
            '" href="' .
            e($url) .
            '" aria-label="' .
            e($aria) .
            '" title="' .
            e($hint) .
            '">' .
            '<span class="agenda-floating-icon" aria-hidden="true">' .
            icon($ico) .
            '</span><div class="agenda-floating-copy"><b>' .
            e($value) .
            '</b><span class="agenda-floating-label">' .
            e($label) .
            '</span><span class="sr-only">' .
            e($hint) .
            "</span></div></a>";
    }
    return '<aside class="context-floating-pending-kpis" data-ds-fixed-layer="context-pending" aria-label="Pendências do usuário">' .
        $cardHtml .
        "</aside>";
}
function page(string $title, string $body, array $opts = []): void
{
    /*
     * GUIA DE MANUTENÇÃO — page
     * Responsabilidade: Implementa a responsabilidade “page” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_operations`, `page_admin_deleted`, `page_admin_health`, `page_admin_diagnostics`, `page_admin_errors`, `page_admin_integrity`, `page_admin_maintenance`, `page_admin_painel` e mais 38.
     * Dependências chamadas: `route`, `ctx`, `function_exists`, `audit_patient_name_by_link`, `audit`, `clinic_visual`, `rawurlencode`, `in_array`, `trim`, `icon`, `e`, `defined` e mais 29.
     * Estado externo lido: `$_SERVER`, `$_GET`.
     * Efeitos colaterais: consome dados da requisição HTTP; produz conteúdo de saída; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    $public = $opts["public"] ?? false;
    $current = route();
    $c = $public ? [] : ctx();
    if (
        PRONTOO_AUDIT_PAGE_VIEWS &&
        function_exists("audit") &&
        $c &&
        !$public &&
        ($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET"
    ) {
        $auditEntity = "janela";
        $auditId = $current;
        $auditCtx = ["titulo" => $title];
        if (
            $current === "patient" &&
            (int) ($_GET["id"] ?? 0) > 0 &&
            ($c["scope"] ?? "") === "clinic"
        ) {
            $patientId = (int) $_GET["id"];
            $patientName = function_exists("audit_patient_name_by_link")
                ? audit_patient_name_by_link($patientId, (int) $c["clinic_id"])
                : "";
            $auditEntity = "paciente";
            $auditId = $patientId;
            $auditCtx = [
                "titulo" => $title,
                "janela" => "Ficha do paciente",
                "patient_link_id" => $patientId,
            ];
            if ($patientName !== "") {
                $auditCtx["patient_name"] = $patientName;
            }
        }
        audit("janela_aberta", $auditEntity, $auditId, $auditCtx);
    }
    $visual = function_exists("clinic_visual")
        ? clinic_visual(
            $c && ($c["scope"] ?? "") === "clinic" ? (int) $c["clinic_id"] : 0,
            $c ?: [],
        )
        : [
            "icon" => "medical_services",
            "brand" => "#334155",
            "brand_dark" => "#1f2937",
            "brand_soft" => "#f1f5f9",
            "brand_soft_2" => "#f8fafc",
            "on_brand" => "#ffffff",
            "favicon" =>
                "/public/assets/favicon-" .
                rawurlencode(PRONTOO_ASSET_REV) .
                ".png",
            "css_vars" =>
                "--clinic-accent:#334155;--clinic-accent-dark:#1f2937;",
        ];
    if ($public && in_array($current, ["login", "signup", "mfa"], true)) {
        $visual["brand"] = "#238763";
        $visual["brand_dark"] = "#105e44";
        $visual["brand_soft"] = "#dff3ea";
        $visual["brand_soft_2"] = "#f1fbf6";
        $visual["on_brand"] = "#ffffff";
        $visual["css_vars"] =
            "--clinic-accent:#238763;--clinic-accent-dark:#105e44;--clinic-accent-soft:#dff3ea;--clinic-on-accent:#ffffff;";
    }
    if (!$public && $c && ($c["scope"] ?? "") === "global") {
        $visual["icon"] = "admin_panel_settings";
        $visual["brand"] = "#238763";
        $visual["brand_dark"] = "#105e44";
        $visual["brand_soft"] = "#dff3ea";
        $visual["brand_soft_2"] = "#f1fbf6";
        $visual["on_brand"] = "#ffffff";
        $visual["css_vars"] =
            "--clinic-accent:#238763;--clinic-accent-dark:#105e44;--clinic-accent-soft:#dff3ea;--clinic-on-accent:#ffffff;";
    }
    if (
        !$public &&
        $c &&
        ($c["scope"] ?? "") === "clinic" &&
        !empty(($c["billing"] ?? [])["read_only"])
    ) {
        $visual["brand"] = "#333333";
        $visual["brand_dark"] = "rgba(0,0,0,.90)";
        $visual["brand_soft"] = "rgba(0,0,0,.10)";
        $visual["brand_soft_2"] = "rgba(0,0,0,.05)";
        $visual["on_brand"] = "#ffffff";
        $visual["css_vars"] .=
            "--clinic-readonly-accent:rgba(0,0,0,.80);--md-ref-palette-primary40:rgba(0,0,0,.80);--md-ref-palette-primary30:rgba(0,0,0,.90);--clinic-accent:rgba(0,0,0,.80);--clinic-accent-rgb:0,0,0;--clinic-accent-strong:rgba(0,0,0,.90);--clinic-accent-hover:rgba(0,0,0,.86);--clinic-accent-pressed:rgba(0,0,0,.72);--clinic-accent-soft:rgba(0,0,0,.10);--clinic-accent-subtle:rgba(0,0,0,.05);--clinic-accent-muted:rgba(0,0,0,.36);--clinic-accent-border:rgba(0,0,0,.26);--clinic-accent-focus:rgba(0,0,0,.22);--clinic-accent-shadow:rgba(0,0,0,.20);--clinic-on-accent:#ffffff;--clinic-clock-bg:rgba(0,0,0,.80);--clinic-clock-border:rgba(0,0,0,.38);--clinic-clock-ink:#ffffff;--brand:rgba(0,0,0,.80);--brand-rgb:0,0,0;--brand-dark:rgba(0,0,0,.90);--brand-soft:rgba(0,0,0,.10);--brand-soft-2:rgba(0,0,0,.05);--brand-border:rgba(0,0,0,.26);--brand-focus:rgba(0,0,0,.22);--brand-shadow:rgba(0,0,0,.20);--on-brand:#ffffff;--md-sys-color-primary:rgba(0,0,0,.80);--md-sys-color-on-primary:#ffffff;--md-sys-color-primary-container:rgba(0,0,0,.10);--md-sys-color-on-primary-container:#111827;--md-sys-color-secondary:rgba(0,0,0,.86);--md-sys-color-on-secondary:#ffffff;--md-sys-color-secondary-container:rgba(0,0,0,.08);--md-sys-color-on-secondary-container:#1f2937;--md-sys-color-tertiary:rgba(0,0,0,.80);--md-sys-color-on-tertiary:#ffffff;--md-sys-color-tertiary-container:rgba(0,0,0,.10);--md-sys-color-on-tertiary-container:#111827;--md-sys-color-inverse-primary:rgba(0,0,0,.54);--md-sys-state-hover:rgba(0,0,0,.07);--md-sys-state-focus:rgba(0,0,0,.12);--md-sys-state-pressed:rgba(0,0,0,.14);";
    }
    $appName =
        $c && ($c["scope"] ?? "") === "clinic"
            ? trim((string) ($c["clinic"] ?? ""))
            : (($c["scope"] ?? "") === "global"
                ? "Desenvolvedor Prontoo"
                : PRONTOO_NAME);
    if ($appName === "") {
        $appName = PRONTOO_NAME;
    }
    $brandIconHtml =
        $c && ($c["scope"] ?? "") === "clinic"
            ? '<span class="brand-mark clinic-brandmark-inline" title="Ícone do consultório">' .
                icon((string) ($visual["icon"] ?? "home_health")) .
                "</span>"
            : '<span class="brand-mark app-brandmark-inline" data-app-brandmark><img class="brand-mark-img app-brandmark-img" src="/public/assets/app-icon-' .
                e(PRONTOO_ASSET_REV) .
                '.png" alt="" aria-hidden="true"></span>';
    $brandVersionHtml = "";
    if ($c && ($c["scope"] ?? "") === "global") {
        $publishedVersion = defined("PRONTOO_VERSION")
            ? (string) PRONTOO_VERSION
            : "";
        $brandVersionHtml =
            '<small class="pagehead-version">Versão: ' .
            e($publishedVersion) .
            "</small>";
    }
    $brand =
        '<a class="brand" href="' .
        href(
            $c && ($c["scope"] ?? "") === "global"
                ? "admin_painel"
                : "appointments",
        ) .
        '" aria-label="' .
        e($appName) .
        '">' .
        $brandIconHtml .
        '<span class="brand-copy"><b>' .
        e($appName) .
        "</b>" .
        $brandVersionHtml .
        "</span></a>";
    $nav = "";
    $topCenter = "";
    if ($c && $c["scope"] === "global") {
        $cmd = "";
        $adminItems = cmdbar_order_items(
            PRONTOO_ADMIN_ACTIONS,
            $current,
            $c,
            true,
        );
        foreach ($adminItems as $key => $a) {
            $adminParent = function_exists("admin_nav_parent")
                ? admin_nav_parent($current)
                : $current;
            $isActive = $adminParent === $key;
            $active = $isActive ? " active" : "";
            $extra = "";
            $labelHtml = cmdbar_label_html(
                (string) $a["label"],
                $isActive,
                $extra,
            );
            $adminIcon = function_exists("prontoo_icon_for_route_label")
                ? prontoo_icon_for_route_label(
                    (string) $key,
                    (string) $a["label"],
                    [],
                    (string) $a["icon"],
                )
                : (string) $a["icon"];
            $cmd .=
                '<a class="cmd' .
                $active .
                '" href="' .
                href($key) .
                '" title="' .
                e($a["label"]) .
                '" aria-label="' .
                e($a["label"]) .
                '"' .
                ($active ? ' aria-current="page"' : "") .
                ">" .
                icon($adminIcon) .
                $labelHtml .
                "</a>";
        }
        $topCenter = "";
        $nav =
            '<nav class="cmdbar contextsbar" aria-label="Contextos">' .
            $cmd .
            "</nav>";
    } elseif ($c) {
        $roleCode = (string) ($c["role"] ?? "");
        $effectiveRoles = array_values(
            array_unique(
                array_filter(
                    array_map(
                        "strval",
                        (array) ($c["effective_roles"] ?? [$roleCode]),
                    ),
                ),
            ),
        );
        $topCenter = "";
        $tabs =
            '<a class="role active" href="' .
            href("switch") .
            '" aria-current="true">' .
            icon(role_icon($roleCode, (int) $c["clinic_id"])) .
            e(role_label_for($roleCode, (int) $c["clinic_id"])) .
            "</a>";
        $visibleActions = [];
        foreach (role_actions_effective($effectiveRoles) as $key => $a) {
            if (can($key)) {
                $visibleActions[$key] = $a;
            }
        }
        if (has_effective_role($c, "gerente") && can("painel")) {
            $adminPainelAction = [
                "label" => "Painel",
                "icon" => "dashboard",
            ];
            $adminVisibleActions = [];
            $adminPainelInserted = false;
            foreach ($visibleActions as $key => $action) {
                if ($key === "painel") {
                    continue;
                }
                if (!$adminPainelInserted && $key === "appointments") {
                    $adminVisibleActions["painel"] = $adminPainelAction;
                    $adminPainelInserted = true;
                }
                $adminVisibleActions[$key] = $action;
            }
            if (!$adminPainelInserted) {
                $adminVisibleActions =
                    ["painel" => $adminPainelAction] + $adminVisibleActions;
            }
            $visibleActions = $adminVisibleActions;
        }
        $visibleActions = cmdbar_order_items(
            $visibleActions,
            $current,
            $c,
            false,
        );
        $activeKey = cmdbar_parent_key($current, $visibleActions, false);
        $acts = "";
        foreach ($visibleActions as $key => $a) {
            $isActive = $activeKey === $key;
            $active = $isActive ? " active" : "";
            $navLabel =
                $key === "financial" && $roleCode === "recepcionista"
                    ? "Caixa"
                    : (string) $a["label"];
            $navIcon =
                $key === "financial" && $roleCode === "recepcionista"
                    ? reception_cash_state_icon($c)
                    : (string) $a["icon"];
            if (function_exists("prontoo_icon_for_route_label")) {
                $navIcon = prontoo_icon_for_route_label(
                    (string) $key,
                    $navLabel,
                    [],
                    $navIcon,
                );
            }
            $extra = "";
            $labelHtml = cmdbar_label_html($navLabel, $isActive, $extra);
            $navHref = $key === "patients" ? href("patients") : href($key);
            $acts .=
                '<a class="cmd' .
                $active .
                '" href="' .
                $navHref .
                '" title="' .
                e($navLabel) .
                '" aria-label="' .
                e($navLabel) .
                '"' .
                ($active ? ' aria-current="page"' : "") .
                ">" .
                icon($navIcon) .
                $labelHtml .
                "</a>";
        }
        $goal = shared_goal_cmdbar_html($c);
        $nav =
            '<nav class="cmdbar contextsbar" aria-label="Contextos">' .
            $acts .
            $goal .
            "</nav>";
    }
    $flash = flash();
    $flashHtml = $flash
        ? '<div class="flash ' .
            $flash[0] .
            '" role="' .
            ($flash[0] === "bad" ? "alert" : "status") .
            '">' .
            e($flash[1]) .
            "</div>"
        : "";
    $logout = "";
    if ($c) {
        $loggedFirstName = first_name((string) ($c["user"]["name"] ?? ""));
        $loggedEnvironment =
            ($c["scope"] ?? "") === "global"
                ? "Desenvolvedor"
                : role_label_for(
                    (string) ($c["role"] ?? ""),
                    (int) ($c["clinic_id"] ?? 0),
                );
        $loggedContextLabel =
            trim($loggedFirstName . " em " . $loggedEnvironment);
        $notifyButton = function_exists("notification_button")
            ? notification_button($c)
            : notification_button_light($c);
        $accountIcon =
            ($c["scope"] ?? "") === "global"
                ? "admin_panel_settings"
                : role_icon(
                    (string) ($c["role"] ?? ""),
                    (int) ($c["clinic_id"] ?? 0),
                );
        $logout =
            '<a class="top-user-name top-account" href="' .
            href("profile") .
            '" title="' .
            e($loggedContextLabel) .
            '" aria-label="' .
            e($loggedContextLabel) .
            '"><span>' .
            e($loggedFirstName) .
            "</span>" .
            icon($accountIcon) .
            "</a>" .
            $notifyButton .
            '<form method="post" action="' .
            href("logout") .
            '" class="logout">' .
            csrf_field() .
            '<button type="submit" class="top-icon logout-icon" aria-label="Sair" title="Sair">' .
            icon("logout") .
            "</button></form>";
    } else {
        $logout =
            '<a class="ghost" href="' .
            href("login") .
            '">Entrar</a>' .
            ($current === "signup" ||
            (function_exists("clinic_signup_blocked") && clinic_signup_blocked())
                ? ""
                : '<a class="primary" href="' .
                    href("signup") .
                    '">Criar consultório</a>');
    }
    $clock = "";
    $pendingFloating = "";
    if ($c && !$public) {
        $suppressFloatingCards =
            $current === "appointments" &&
            (string) ($_GET["view"] ?? "diario") === "mensal";
        $pendingFloating =
            !$suppressFloatingCards &&
            function_exists("floating_pending_cards_html")
                ? floating_pending_cards_html($c, $current)
                : "";
        if ($pendingFloating !== "") {
            $clock = $pendingFloating;
        } elseif ($current !== "appointments") {
            $tz = app_timezone_safe(
                (string) ($c["timezone"] ?? "America/Cuiaba"),
            );
            $clock =
                '<aside class="floating-clock agenda-floating-card context-floating-clock-card" data-floating-clock data-clock-tz="' .
                e($tz) .
                '" aria-label="Horário atual"><span class="agenda-floating-icon" aria-hidden="true">' .
                icon("schedule") .
                '</span><div class="agenda-floating-copy"><b data-clock>--:--</b></div></aside>';
        }
    }
    $installCta = "";
    $bodyClass = ($public ? "public" : "app") . " has-top-shell";
    if ($pendingFloating !== "") {
        $bodyClass .= " has-floating-pending";
    }
    if (!$public && $c) {
        $bodyClass .=
            " scope-" .
            preg_replace(
                "/[^a-z0-9_-]+/i",
                "-",
                (string) ($c["scope"] ?? "clinic"),
            );
        if (($c["scope"] ?? "") !== "global") {
            $bodyClass .=
                " role-" .
                preg_replace(
                    "/[^a-z0-9_-]+/i",
                    "-",
                    (string) ($c["role"] ?? "usuario"),
                );
            if (!empty(($c["billing"] ?? [])["read_only"])) {
                $bodyClass .= " is-read-only";
            }
        }
    }
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="canonical" href="https://prontoo.app/"><title>' .
        e($title) .
        " · " .
        e($appName) .
        '</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="' .
        e($visual["brand"]) .
        '"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="application-name" content="' .
        e($appName) .
        '"><meta name="prontoo-version" content="' .
        e(PRONTOO_VERSION) .
        '"><meta name="csrf-token" content="' .
        e(csrf()) .
        '"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/png" href="/public/assets/favicon-' .
        rawurlencode(PRONTOO_ASSET_REV) .
        '.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,-25..200&display=swap" rel="stylesheet"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
        rawurlencode(
            defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
        ) .
        '&release=' .
        rawurlencode(PRONTOO_VERSION) .
        '"><script defer src="/public/assets/app.js?v=' .
        rawurlencode(
            defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
        ) .
        '&release=' .
        rawurlencode(PRONTOO_VERSION) .
        '&ui=design-system-global-enxuto"></script></head><body class="' .
        e($bodyClass) .
        '" style="' .
        e($visual["css_vars"]) .
        '" data-route="' .
        e($current) .
        '" data-app-version="' .
        e(PRONTOO_VERSION) .
        '"><a class="skip-link" href="#conteudo">Pular para o conteúdo</a><header class="top">' .
        $brand .
        $topCenter .
        '<div class="top-actions">' .
        $logout .
        "</div></header>" .
        $nav .
        '<main id="conteudo" tabindex="-1">' .
        $flashHtml .
        billing_notice($c) .
        onboarding_tip_html($c, $current) .
        $body .
        "</main>" .
        $clock .
        "</body></html>";
}
function context_parent_for_route(string $route, array $c): string
{
    /*
     * GUIA DE MANUTENÇÃO — context_parent_for_route
     * Responsabilidade: Implementa a responsabilidade “context parent for route” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_operation_specs`.
     * Dependências chamadas: `function_exists`, `admin_nav_parent`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    if (($c["scope"] ?? "") === "global") {
        if (function_exists("admin_nav_parent")) {
            return admin_nav_parent($route);
        }
        $map = [
            "admin_stats" => "admin_painel",
            "admin_operations" => "admin_painel",
            "admin_onboarding" => "admin_clinics",
            "admin_users" => "admin_clinics",
            "admin_people" => "admin_clinics",
            "admin_payment_proof" => "admin_clinics",
            "admin_global_notices" => "admin_maintenance",
            "admin_deleted" => "admin_maintenance",
            "admin_errors" => "admin_health",
            "admin_diagnostics" => "admin_health",
            "admin_integrity" => "admin_health",
            "admin_security" => "admin_health",
            "admin_audit" => "admin_health",
        ];
        return $map[$route] ?? $route;
    }
    $map = [
        "painel" => "appointments",
        "patient" => "patients",
        "patient_lookup" => "patients",
        "patient_suggest" => "patients",
        "person_lookup" => "patients",
        "leads" => "patients",
        "lead_lookup" => "patients",
        "lead_patient_lookup" => "patients",
        "creditors" => "patients",
        "document_view" => "documents",
        "document_print" => "documents",
        "document_pdf" => "documents",
        "document_pdf_file" => "documents",
        "counterparty_lookup" => "financial",
        "counterparty_suggest" => "financial",
        "goal_status" => "financial",
        "procedures" => "settings",
        "users" => "settings",
        "user" => "settings",
        "permissions" => "settings",
        "operations" => "settings",
        "audit" => "patients",
    ];
    return $map[$route] ?? $route;
}
function operation_current_match(
    string $route,
    array $params,
    string $current,
): bool {
    /*
     * GUIA DE MANUTENÇÃO — operation_current_match
     * Responsabilidade: Implementa a responsabilidade “operation current match” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `operation_link_html`, `operation_menu_html`.
     * Dependências chamadas: `trim`, `in_array`.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($route !== $current) {
        return false;
    }
    if ($current === "documents") {
        $isModels =
            (string) ($_GET["models"] ?? "") === "1" ||
            (string) ($_GET["new_model"] ?? "") === "1";
        $isEmit = (string) ($_GET["emit"] ?? "") === "1";
        $isDoc = (int) ($_GET["doc"] ?? 0) > 0;
        $isRecent =
            (string) ($_GET["recent"] ?? "") === "1" ||
            trim((string) ($_GET["doc_q"] ?? ($_GET["q"] ?? ""))) !== "" ||
            (!$isModels && !$isEmit && !$isDoc);
        if (isset($params["models"])) {
            return $isModels;
        }
        if (isset($params["recent"])) {
            return $isRecent || $isDoc;
        }
        return !$isModels && !$isEmit;
    }
    if ($current === "appointments" && isset($params["view"])) {
        $want = (string) $params["view"];
        $actual = (string) ($_GET["view"] ?? "diario");
        if ($actual === "") {
            $actual = "diario";
        }
        return $actual === $want;
    }
    if ($current === "admin_alerts") {
        $actualView = (string) ($_GET["view"] ?? "received");
        if (!in_array($actualView, ["received", "sent"], true)) {
            $actualView = "received";
        }
        if (
            isset($params["view"]) &&
            $actualView !== (string) $params["view"]
        ) {
            return false;
        }
        $actualCompose = (string) ($_GET["compose"] ?? "0");
        if (isset($params["compose"])) {
            return $actualCompose === (string) $params["compose"];
        }
        if ($actualCompose === "1") {
            return false;
        }
        return true;
    }
    foreach ($params as $k => $v) {
        if ($k === "tab") {
            if ((string) ($_GET["tab"] ?? "perfil") !== (string) $v) {
                return false;
            }
            continue;
        }
        if ($k === "d" || $k === "doctor") {
            continue;
        }
        if ((string) ($_GET[$k] ?? "") !== (string) $v) {
            return false;
        }
    }
    return true;
}
function operation_link_html(
    string $route,
    string $label,
    string $iconName,
    string $current,
    array $params = [],
): string {
    /*
     * GUIA DE MANUTENÇÃO — operation_link_html
     * Responsabilidade: Monta a representação de interface associada a “operation link html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `operation_menu_html`, `page_operations_html`.
     * Dependências chamadas: `function_exists`, `prontoo_icon_for_route_label`, `operation_current_match`, `href`, `e`, `icon`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (function_exists("prontoo_icon_for_route_label")) {
        $iconName = prontoo_icon_for_route_label(
            $route,
            $label,
            $params,
            $iconName,
        );
    }
    $active = operation_current_match($route, $params, $current);
    $cls = "operation-chip" . ($active ? " is-active" : "");
    return '<a class="' .
        $cls .
        '" href="' .
        href($route, $params) .
        '" title="' .
        e($label) .
        '"' .
        ($active ? ' aria-current="page"' : "") .
        ">" .
        icon($iconName) .
        "<span>" .
        e($label) .
        "</span></a>";
}
function page_operation_specs(string $current, array $c): array
{
    /*
     * GUIA DE MANUTENÇÃO — page_operation_specs
     * Responsabilidade: Coordena a rota e renderiza a tela “page operation specs”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_operations_html`.
     * Dependências chamadas: `context_parent_for_route`, `in_array`, `function_exists`, `reception_cash_state_icon`, `preg_match`, `has_effective_role`, `array_values`, `array_filter`.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    if (!$c) {
        return [];
    }
    if ($current === "audit") {
        return [];
    }
    $parent = context_parent_for_route($current, $c);
    if (($c["scope"] ?? "") === "global") {
        $alertView = (string) ($_GET["view"] ?? "received");
        if (!in_array($alertView, ["received", "sent"], true)) {
            $alertView = "received";
        }
        return match ($parent) {
            "admin_painel" => [
                ["admin_painel", "Desenvolvedor", "space_dashboard"],
                ["admin_operations", "Operação", "account_tree"],
            ],
            "admin_clinics" => [
                ["admin_clinics", "Consultórios", "home_health"],
                ["admin_people", "Usuários", "groups"],
            ],
            "admin_health" => [["admin_health", "Incidentes", "crisis_alert"]],
            "admin_alerts" => [
                ["admin_alerts", "Recebidos", "inbox", ["view" => "received"]],
                ["admin_alerts", "Enviados", "outbox", ["view" => "sent"]],
                [
                    "admin_alerts",
                    "Novo aviso",
                    "add_comment",
                    ["view" => $alertView, "compose" => "1"],
                ],
            ],
            "admin_maintenance" => [
                ["admin_maintenance", "Manutenção", "construction"],
                ["admin_global_notices", "Avisos globais", "campaign"],
                ["admin_deleted", "Excluídos", "restore_from_trash"],
            ],
            "admin_settings" => [
                ["admin_settings", "Configurações", "settings"],
            ],
            default => [],
        };
    }
    $role = (string) ($c["role"] ?? "");
    $cashLabel = $role === "recepcionista" ? "Caixa" : "Painel";
    $cashIcon =
        $role === "recepcionista"
            ? (function_exists("reception_cash_state_icon")
                ? reception_cash_state_icon($c)
                : "point_of_sale")
            : "monitoring";
    $agendaParams = [];
    if (
        isset($_GET["d"]) &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET["d"])
    ) {
        $agendaParams["d"] = (string) $_GET["d"];
    }
    if (isset($_GET["doctor"]) && (int) $_GET["doctor"] > 0) {
        $agendaParams["doctor"] = (int) $_GET["doctor"];
    }
    $peopleRoute = "patients";
    $ops = match ($parent) {
        "appointments" => [
            [
                "appointments",
                "Painel",
                "space_dashboard",
                $agendaParams + ["view" => "resumo"],
            ],
            [
                "appointments",
                "Diário",
                "today",
                $agendaParams + ["view" => "diario"],
            ],
            [
                "appointments",
                "Semanal",
                "view_week",
                $agendaParams + ["view" => "semanal"],
            ],
            [
                "appointments",
                "Mensal",
                "calendar_month",
                $agendaParams + ["view" => "mensal"],
            ],
        ],
        "patients" => [
            ["leads", "Interessados", "person_search"],
            ["patients", "Pacientes", "patient_list"],
        ],
        "documents" => [
            ["documents", "Modelos", "edit_note", ["models" => "1"]],
            ["documents", "Emitidos", "description", ["recent" => "1"]],
        ],
        "tasks" => [["tasks", "Tarefas", "task_alt"]],
        "financial" => [["financial", $cashLabel, $cashIcon]],
        "settings" => [
            ["settings", "Identificação", "home_health", ["tab" => "perfil"]],
            [
                "settings",
                "Departamentos",
                "corporate_fare",
                ["tab" => "setores"],
            ],
            ["procedures", "Procedimentos", "medical_services"],
            ["users", "Colaboradores", "groups"],
        ],
        "profile" => [
            ["settings", "Aparência", "palette", ["tab" => "visual"]],
            ["settings", "Assinatura", "credit_card", ["tab" => "assinatura"]],
            ["permissions", "Permissões", "admin_panel_settings"],
            ["operations", "Fluxo", "account_tree"],
        ],
        "maestro" => [
            ["maestro", "Rotinas", "event_repeat"],
            ["tasks", "Tarefas", "task_alt"],
        ],
        default => [],
    };
    if (
        $parent === "patients" &&
        function_exists("has_effective_role") &&
        has_effective_role($c, "gerente")
    ) {
        $ops[] = ["creditors", "Credores", "receipt_long"];
    }
    if ($role === "assistente" || $role === "medico") {
        $ops = array_values(
            array_filter(
                $ops,
                static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de componentes e composição visual. Dependências diretas: `in_array`. Efeitos: transformação local sem efeito externo detectado. */ fn($op) => !in_array(
                    (string) $op[0],
                    [
                        "leads",
                        "financial",
                        "procedures",
                        "users",
                        "permissions",
                        "operations",
                        "settings",
                    ],
                    true,
                ),
            ),
        );
    }
    if ($role === "recepcionista") {
        $ops = array_values(
            array_filter(
                $ops,
                static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de componentes e composição visual. Dependências diretas: `in_array`. Efeitos: transformação local sem efeito externo detectado. */ fn($op) => !in_array(
                    (string) $op[0],
                    [
                        "procedures",
                        "users",
                        "permissions",
                        "operations",
                        "settings",
                        "maestro",
                    ],
                    true,
                ),
            ),
        );
    }
    return $ops;
}
function operation_menu_html(
    string $label,
    string $iconName,
    array $items,
    string $current,
): string {
    /*
     * GUIA DE MANUTENÇÃO — operation_menu_html
     * Responsabilidade: Monta a representação de interface associada a “operation menu html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_operations_html`.
     * Dependências chamadas: `is_array`, `count`, `json_encode`, `function_exists`, `can`, `operation_current_match`, `operation_link_html`, `icon`, `e`.
     * Efeitos colaterais: produz conteúdo de saída.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $links = "";
    $active = false;
    $seen = [];
    foreach ($items as $item) {
        if (!is_array($item) || count($item) < 3) {
            continue;
        }
        $route = (string) $item[0];
        $params = (array) ($item[3] ?? []);
        $key = $route . ":" . json_encode($params);
        if ($route === "" || isset($seen[$key])) {
            continue;
        }
        if (function_exists("can") && !can($route)) {
            continue;
        }
        $seen[$key] = 1;
        if (operation_current_match($route, $params, $current)) {
            $active = true;
        }
        $links .= operation_link_html(
            $route,
            (string) $item[1],
            (string) $item[2],
            $current,
            $params,
        );
    }
    if ($links === "") {
        return "";
    }
    $cls = "operation-menu" . ($active ? " is-active" : "");
    return '<div class="' .
        $cls .
        '"><button type="button" class="operation-chip operation-menu-trigger" aria-haspopup="true" aria-expanded="false">' .
        icon($iconName) .
        "<span>" .
        e($label) .
        "</span>" .
        icon("expand_more") .
        '</button><div class="operation-menu-panel" role="menu">' .
        $links .
        "</div></div>";
}
function page_operations_html(string $current, array $c): string
{
    /*
     * GUIA DE MANUTENÇÃO — page_operations_html
     * Responsabilidade: Coordena a rota e renderiza a tela “page operations html”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_head`.
     * Dependências chamadas: `page_operation_specs`, `is_array`, `count`, `operation_menu_html`, `json_encode`, `function_exists`, `can`, `operation_link_html`.
     * Efeitos colaterais: produz conteúdo de saída.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $specs = page_operation_specs($current, $c);
    if (!$specs) {
        return "";
    }
    $seen = [];
    $html = "";
    foreach ($specs as $op) {
        if (!is_array($op) || count($op) < 3) {
            continue;
        }
        $route = (string) $op[0];
        if ($route === "__menu") {
            $html .= operation_menu_html(
                (string) $op[1],
                (string) $op[2],
                (array) ($op[3]["items"] ?? []),
                $current,
            );
            continue;
        }
        if (
            $route === "" ||
            isset($seen[$route . ":" . json_encode($op[3] ?? [])])
        ) {
            continue;
        }
        if (function_exists("can") && !can($route)) {
            continue;
        }
        $seen[$route . ":" . json_encode($op[3] ?? [])] = 1;
        $html .= operation_link_html(
            $route,
            (string) $op[1],
            (string) $op[2],
            $current,
            (array) ($op[3] ?? []),
        );
    }
    return $html !== ""
        ? '<nav class="pagehead-operations" aria-label="Operações">' .
                $html .
                "</nav>"
        : "";
}
function page_head_icon_name(string $title = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — page_head_icon_name
     * Responsabilidade: Coordena a rota e renderiza a tela “page head icon name”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_head`.
     * Dependências chamadas: `route`, `function_exists`, `str_starts_with`, `admin_nav_parent`, `prontoo_icon_for_route_label`, `ctx`, `role_icon`, `reception_cash_state_icon`, `actions`, `mb_strtolower`, `str_contains`.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: consome dados da requisição HTTP; produz conteúdo de saída.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $current = route();
    $params = $_GET;
    if ($current === "admin_painel") {
        return "network_ping";
    }
    if (function_exists("prontoo_icon_for_route_label")) {
        if (
            str_starts_with($current, "admin_") &&
            function_exists("admin_nav_parent")
        ) {
            $parent = admin_nav_parent($current);
            if ($parent !== $current) {
                return prontoo_icon_for_route_label(
                    $parent,
                    $title,
                    (array) $params,
                    prontoo_icon_for_route_label(
                        $current,
                        $title,
                        (array) $params,
                        "monitoring",
                    ),
                );
            }
        }
        $ico = prontoo_icon_for_route_label(
            $current,
            $title,
            (array) $params,
            "",
        );
        if ($ico !== "") {
            return $ico;
        }
    }
    if (str_starts_with($current, "admin_")) {
        $parent = admin_nav_parent($current);
        if (isset(PRONTOO_ADMIN_ACTIONS[$parent]["icon"])) {
            return (string) PRONTOO_ADMIN_ACTIONS[$parent]["icon"];
        }
    }
    $c = ctx();
    if ($current === "painel" && ($c["scope"] ?? "") === "clinic") {
        return role_icon(
            (string) ($c["role"] ?? ""),
            (int) ($c["clinic_id"] ?? 0),
        );
    }
    if (
        $current === "financial" &&
        ($c["scope"] ?? "") === "clinic" &&
        (string) ($c["role"] ?? "") === "recepcionista"
    ) {
        return reception_cash_state_icon($c);
    }
    $actions = actions();
    if (isset($actions[$current]["icon"])) {
        return (string) $actions[$current]["icon"];
    }
    $map = [
        "install" => "settings_applications",
        "login" => "login",
        "signup" => "add_business",
        "logout" => "logout",
        "patient" => "patient_list",
        "document_view" => "visibility",
        "document_print" => "print",
        "admin_deleted" => "restore_from_trash",
        "admin_diagnostics" => "troubleshoot",
        "admin_instabilities" => "crisis_alert",
        "admin_integrity" => "verified_user",
        "admin_security" => "security",
        "maintenance" => "construction",
    ];
    if (isset($map[$current])) {
        return $map[$current];
    }
    $t = mb_strtolower($title);
    if (str_contains($t, "documento")) {
        return "description";
    }
    if (str_contains($t, "paciente")) {
        return "patient_list";
    }
    if (str_contains($t, "anotação") || str_contains($t, "anotacao")) {
        return "sticky_note_2";
    }
    if (str_contains($t, "agenda") || str_contains($t, "consulta")) {
        return "calendar_month";
    }
    if (str_contains($t, "atividade")) {
        return "history";
    }
    if (str_contains($t, "aviso") || str_contains($t, "notifica")) {
        return "campaign";
    }
    if (str_contains($t, "tarefa")) {
        return "task_alt";
    }
    if (str_contains($t, "colaborador")) {
        return "groups";
    }
    if (str_contains($t, "caixa")) {
        return "point_of_sale";
    }
    if (str_contains($t, "credor")) {
        return "receipt_long";
    }
    if (str_contains($t, "financeiro")) {
        return "payments";
    }
    if (str_contains($t, "procedimento")) {
        return "medical_services";
    }
    if (str_contains($t, "consultório")) {
        return "home_health";
    }
    if (str_contains($t, "permiss")) {
        return "admin_panel_settings";
    }
    return "monitoring";
}
function page_head(string $title, string $sub = "", string $action = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — page_head
     * Responsabilidade: Coordena a rota e renderiza a tela “page head”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_operations`, `page_admin_deleted`, `page_admin_health`, `page_admin_diagnostics`, `page_admin_errors`, `page_admin_integrity`, `page_admin_maintenance`, `page_admin_painel` e mais 33.
     * Dependências chamadas: `page_head_icon_name`, `ctx`, `function_exists`, `page_operations_html`, `route`, `icon`, `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ico = page_head_icon_name($title);
    $c = ctx();
    if (($c["scope"] ?? "") === "global") {
        $action = "";
    }
    $ops =
        $c && function_exists("page_operations_html")
            ? page_operations_html(route(), $c)
            : "";
    $hasOps = $ops !== "";
    $hasAction = $action !== "";
    return '<section class="pagehead' .
        ($hasOps ? " has-operations" : "") .
        ($hasAction ? " has-actions" : "") .
        '" aria-label="Operações da tela"><div class="pagehead-copy"><h1><span class="pagehead-icon">' .
        icon($ico) .
        "</span><span>" .
        e($title) .
        "</span></h1></div>" .
        $ops .
        ($hasAction
            ? '<div class="pagehead-actions" aria-label="Operações rápidas">' .
                $action .
                "</div>"
            : "") .
        "</section>";
}
function action_icon_for(string $label): string
{
    /*
     * GUIA DE MANUTENÇÃO — action_icon_for
     * Responsabilidade: Implementa a responsabilidade “action icon for” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `action_summary_label`.
     * Dependências chamadas: `mb_strtolower`, `function_exists`, `prontoo_icon_for_route_label`, `str_contains`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $l = mb_strtolower($label);
    if (function_exists("prontoo_icon_for_route_label")) {
        $byLabel = prontoo_icon_for_route_label("", $label, [], "");
        if ($byLabel !== "") {
            return $byLabel;
        }
    }
    if (str_contains($l, "salvar")) {
        return "save";
    }
    if (str_contains($l, "excluir") || str_contains($l, "remover")) {
        return "delete";
    }
    if (str_contains($l, "cancelar") || str_contains($l, "descartar")) {
        return "close";
    }
    if (str_contains($l, "bloquear")) {
        return "event_busy";
    }
    if (str_contains($l, "consulta") || str_contains($l, "agenda")) {
        return "calendar_month";
    }
    if (str_contains($l, "paciente")) {
        return "patient_list";
    }
    if (str_contains($l, "credor")) {
        return "receipt_long";
    }
    if (str_contains($l, "interessado")) {
        return "person_search";
    }
    if (str_contains($l, "tarefa")) {
        return "add_task";
    }
    if (str_contains($l, "aviso") || str_contains($l, "notifica")) {
        return "campaign";
    }
    if (str_contains($l, "alterar")) {
        return "edit";
    }
    if (str_contains($l, "resolver")) {
        return "task_alt";
    }
    if (str_contains($l, "assinatura")) {
        return "credit_card";
    }
    if (str_contains($l, "modelo")) {
        return "note_add";
    }
    if (str_contains($l, "procedimento")) {
        return "medical_services";
    }
    return "add";
}
function action_summary_label(string $label, string $iconName = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — action_summary_label
     * Responsabilidade: Monta a representação de interface associada a “action summary label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_health`, `page_admin_errors`, `page_admin_painel`, `subscription_payment_proof_view_link`, `page_creditors`, `page_leads`, `patient_legal_guardian_card`, `page_patients` e mais 7.
     * Dependências chamadas: `icon`, `action_icon_for`, `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return icon($iconName !== "" ? $iconName : action_icon_for($label)) .
        "<span>" .
        e($label) .
        "</span>";
}
function card(string $html, string $class = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — card
     * Responsabilidade: Monta a representação de interface associada a “card” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_operations`, `page_admin_health`, `page_admin_diagnostics`, `page_admin_errors`, `page_admin_integrity`, `page_admin_maintenance`, `page_admin_painel`, `page_admin_people` e mais 39.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return '<section class="card ' . $class . '">' . $html . "</section>";
}
function ds_class(string ...$classes): string
{
    /*
     * GUIA DE MANUTENÇÃO — ds_class
     * Responsabilidade: Implementa a responsabilidade “ds class” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `ds_card_class`, `ds_search_card_class`, `ds_filter_list_class`, `ds_filter_chip_class`.
     * Dependências chamadas: `preg_split`, `trim`, `implode`, `array_keys`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $out = [];
    foreach ($classes as $class) {
        foreach (preg_split("/\s+/", trim($class)) ?: [] as $part) {
            if ($part !== "" && !isset($out[$part])) {
                $out[$part] = true;
            }
        }
    }
    return implode(" ", array_keys($out));
}
function ds_card_class(string $additionalClass = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — ds_card_class
     * Responsabilidade: Monta a representação de interface associada a “ds card class” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `ds_class`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return ds_class("card", $additionalClass);
}
function ds_search_card_class(string $additionalClass = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — ds_search_card_class
     * Responsabilidade: Localiza, carrega ou resolve os dados de “ds search card class” para consumo pelas camadas superiores.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `ds_class`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return ds_class("ds-search-card", $additionalClass);
}
function ds_filter_list_class(string $additionalClass = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — ds_filter_list_class
     * Responsabilidade: Localiza, carrega ou resolve os dados de “ds filter list class” para consumo pelas camadas superiores.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `ds_class`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return ds_class("ds-filter-list-block", $additionalClass);
}
function ds_filter_chip_class(
    string $additionalClass = "",
    bool $active = false,
): string {
    /*
     * GUIA DE MANUTENÇÃO — ds_filter_chip_class
     * Responsabilidade: Transforma e normaliza “ds filter chip class” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `ds_class`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return ds_class(
        "ds-filter-chip",
        $additionalClass,
        $active ? "is-active" : "",
    );
}
function stat_card(
    string $label,
    mixed $value,
    string $iconName,
    string $note = "",
): string {
    /*
     * GUIA DE MANUTENÇÃO — stat_card
     * Responsabilidade: Monta a representação de interface associada a “stat card” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_health`, `page_admin_painel`, `page_admin_clinics`, `page_admin_security`, `page_admin_performance`, `page_maestro`.
     * Dependências chamadas: `icon`, `n`, `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return '<article class="stat-card">' .
        icon($iconName) .
        "<div><b>" .
        n($value) .
        "</b><span>" .
        e($label) .
        "</span>" .
        ($note ? "<small>" . e($note) . "</small>" : "") .
        "</div></article>";
}
function form_row(string $label, string $input): string
{
    /*
     * GUIA DE MANUTENÇÃO — form_row
     * Responsabilidade: Monta a representação de interface associada a “form row” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_errors`, `page_admin_maintenance`, `admin_clinic_detail_page`, `page_admin_clinics`, `page_admin_alerts`, `page_login`, `page_signup`, `page_profile` e mais 38.
     * Dependências chamadas: `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return '<label class="field"><span>' .
        e($label) .
        "</span>" .
        $input .
        "</label>";
}
function input(
    string $name,
    string $type = "text",
    mixed $value = "",
    string $extra = "",
): string {
    /*
     * GUIA DE MANUTENÇÃO — input
     * Responsabilidade: Implementa a responsabilidade “input” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_maintenance`, `admin_clinic_detail_page`, `page_admin_clinics`, `page_admin_alerts`, `page_login`, `page_signup`, `page_profile`, `page_onboarding` e mais 36.
     * Dependências chamadas: `trim`, `preg_match`, `preg_split`, `stripos`, `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $extra = trim($extra);
    if (
        $type === "text" &&
        preg_match('/(^|_)cpf($|_)/i', $name) &&
        !preg_match('/_omitted$/i', $name)
    ) {
        foreach (
            [
                "data-cpf-mask",
                "data-cpf-validate",
                'inputmode="numeric"',
                'maxlength="14"',
            ]
            as $attr
        ) {
            $key = preg_split("/[= ]/", $attr, 2)[0];
            if (stripos($extra, $key) === false) {
                $extra = trim($extra . " " . $attr);
            }
        }
    }
    return '<input name="' .
        e($name) .
        '" type="' .
        e($type) .
        '" value="' .
        e((string) $value) .
        '" ' .
        $extra .
        ">";
}
function textarea(string $name, mixed $value = "", string $extra = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — textarea
     * Responsabilidade: Implementa a responsabilidade “textarea” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_errors`, `page_admin_maintenance`, `page_admin_alerts`, `agenda_note_form_html`, `page_procedures`, `page_creditors`, `page_leads`, `patient_guardian_form_html` e mais 7.
     * Dependências chamadas: `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return '<textarea name="' .
        e($name) .
        '" ' .
        $extra .
        ">" .
        e((string) $value) .
        "</textarea>";
}
function select_html(
    string $name,
    array $options,
    mixed $selected = null,
    string $extra = "",
): string {
    /*
     * GUIA DE MANUTENÇÃO — select_html
     * Responsabilidade: Localiza, carrega ou resolve os dados de “select html” para consumo pelas camadas superiores.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_maestro`, `select_label`.
     * Dependências chamadas: `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $h = '<select name="' . e($name) . '" ' . $extra . ">";
    foreach ($options as $k => $v) {
        $h .=
            '<option value="' .
            e((string) $k) .
            '" ' .
            ((string) $k === (string) $selected ? "selected" : "") .
            ">" .
            e($v) .
            "</option>";
    }
    return $h . "</select>";
}
function select_label(
    string $label,
    string $name,
    array $opts,
    mixed $sel = null,
    string $extra = "",
): string {
    /*
     * GUIA DE MANUTENÇÃO — select_label
     * Responsabilidade: Localiza, carrega ou resolve os dados de “select label” para consumo pelas camadas superiores.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_maintenance`, `admin_clinic_detail_page`, `page_admin_alerts`, `page_signup`, `agenda_note_form_html`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3468`, `closure@app/Domain/Appointments/Appointments.php:3850` e mais 21.
     * Dependências chamadas: `form_row`, `select_html`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return form_row($label, select_html($name, $opts, $sel, $extra));
}
function form_submit_icon(string $label): string
{
    /*
     * GUIA DE MANUTENÇÃO — form_submit_icon
     * Responsabilidade: Monta a representação de interface associada a “form submit icon” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `document_template_author_form`, `page_creditors`, `page_patients`, `form_actions`.
     * Dependências chamadas: `mb_strtolower`, `str_contains`, `function_exists`, `prontoo_icon_for_route_label`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $l = mb_strtolower($label);
    if (str_contains($l, "salvar")) {
        return "save";
    }
    if (str_contains($l, "excluir") || str_contains($l, "remover")) {
        return "delete";
    }
    if (str_contains($l, "cancelar") || str_contains($l, "descartar")) {
        return "close";
    }
    if (str_contains($l, "agendar") || str_contains($l, "consulta")) {
        return "event_available";
    }
    if (str_contains($l, "bloquear")) {
        return "event_busy";
    }
    if (
        str_contains($l, "publicar") ||
        str_contains($l, "comunicado") ||
        str_contains($l, "aviso")
    ) {
        return "campaign";
    }
    if (str_contains($l, "emitir") || str_contains($l, "documento")) {
        return "description";
    }
    if (str_contains($l, "preencher")) {
        return "sync";
    }
    if (
        str_contains($l, "depositar") ||
        str_contains($l, "pagamento") ||
        str_contains($l, "entrada")
    ) {
        return "payments";
    }
    if (str_contains($l, "registrar")) {
        return "sticky_note_2";
    }
    if (
        str_contains($l, "cadastrar") ||
        str_contains($l, "criar") ||
        str_contains($l, "adicionar")
    ) {
        return "add_circle";
    }
    if (str_contains($l, "confirmar")) {
        return "check_circle";
    }
    if (function_exists("prontoo_icon_for_route_label")) {
        $byLabel = prontoo_icon_for_route_label("", $label, [], "");
        if ($byLabel !== "") {
            return $byLabel;
        }
    }
    return "task_alt";
}
function cancel_button(string $label = "Cancelar"): string
{
    /*
     * GUIA DE MANUTENÇÃO — cancel_button
     * Responsabilidade: Implementa a responsabilidade “cancel button” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `form_actions`.
     * Dependências chamadas: `icon`, `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return '<button type="button" class="ghost" data-close-panel>' .
        icon("close") .
        "<span>" .
        e($label) .
        "</span></button>";
}
function form_actions(string $submitLabel, string $class = "primary"): string
{
    /*
     * GUIA DE MANUTENÇÃO — form_actions
     * Responsabilidade: Monta a representação de interface associada a “form actions” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_clinics`, `page_admin_alerts`, `document_template_author_form`, `financial_cashier_page`, `financial_admin_drawers_panel`, `financial_admin_locations_panel`, `financial_admin_operations_panel`, `financial_admin_page` e mais 6.
     * Dependências chamadas: `cancel_button`, `e`, `icon`, `form_submit_icon`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return '<div class="form-actions">' .
        cancel_button() .
        '<button type="submit" class="' .
        e($class) .
        '">' .
        icon(form_submit_icon($submitLabel)) .
        "<span>" .
        e($submitLabel) .
        "</span></button></div>";
}
function action_panel(
    string $label,
    string $formHtml,
    string $variant = "primary",
    string $hint = "",
): string {
    /*
     * GUIA DE MANUTENÇÃO — action_panel
     * Responsabilidade: Implementa a responsabilidade “action panel” dentro do módulo de componentes e composição visual.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `e`, `action_summary_label`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return '<details class="action-panel"><summary class="' .
        e($variant) .
        ' small cmdlike">' .
        action_summary_label($label) .
        ($hint !== "" ? "<small>" . e($hint) . "</small>" : "") .
        "</summary>" .
        $formHtml .
        "</details>";
}
function timeline(?array $items, string $empty = "Nada por enquanto."): string
{
    /*
     * GUIA DE MANUTENÇÃO — timeline
     * Responsabilidade: Monta a representação de interface associada a “timeline” sem alterar o contrato visual externo.
     * Local arquitetural: app/Ui/Components.php (componentes e composição visual).
     * Chamadores detectados: `page_admin_deleted`, `page_admin_health`, `page_admin_diagnostics`, `page_admin_errors`, `page_admin_integrity`, `page_admin_maintenance`, `page_admin_painel`, `page_admin_people` e mais 9.
     * Dependências chamadas: `e`, `is_array`, `icon`, `preg_replace`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!$items) {
        return '<div class="empty">' . e($empty) . "</div>";
    }
    $h = '<div class="timeline">';
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $dot = !empty($it["badge"])
            ? '<span class="dot time-dot">' .
                e((string) $it["badge"]) .
                "</span>"
            : '<span class="dot">' .
                icon($it["icon"] ?? "radio_button_checked") .
                "</span>";
        $class =
            "titem" .
            (!empty($it["class"])
                ? " " .
                    preg_replace("/[^a-z0-9_\- ]/i", "", (string) $it["class"])
                : "");
        $h .=
            '<article class="' .
            $class .
            '">' .
            $dot .
            "<div><time>" .
            (!empty($it["time_html"])
                ? (string) $it["time_html"]
                : e($it["time"] ?? "")) .
            "</time><h3>" .
            e($it["title"] ?? "") .
            "</h3>" .
            (!empty($it["body"]) ? "<p>" . e($it["body"]) . "</p>" : "") .
            (!empty($it["meta"])
                ? "<small>" . e($it["meta"]) . "</small>"
                : "") .
            (!empty($it["html"])
                ? '<div class="t-actions">' . $it["html"] . "</div>"
                : "") .
            "</div></article>";
    }
    return $h === '<div class="timeline">'
        ? '<div class="empty">' . e($empty) . "</div>"
        : $h . "</div>";
}
