<?php
declare(strict_types=1);
@date_default_timezone_set("UTC");
function app_root(): string
{
    /*
     * GUIA DE MANUTENÇÃO — app_root
     * Responsabilidade: Implementa a responsabilidade “app root” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_diagnostics`, `page_admin_security`, `subscription_payment_proof_absolute_path`, `document_pdf_public_router_dir`, `install_pdf_dir`, `install_technical_report`, `cfg_file`, `runtime_self_check` e mais 3.
     * Dependências chamadas: `defined`, `dirname`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return defined("PRONTOO_ROOT") ? PRONTOO_ROOT : dirname(__DIR__, 2);
}
function cfg_file(): string
{
    /*
     * GUIA DE MANUTENÇÃO — cfg_file
     * Responsabilidade: Implementa a responsabilidade “cfg file” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `install_environment_checks`, `install_technical_report`, `prontoo_install`, `has_cfg`, `cfg`.
     * Dependências chamadas: `trim`, `getenv`, `str_starts_with`, `app_root`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $env = trim((string) (getenv("PRONTOO_CONFIG_PATH") ?: ""));
    if ($env !== "" && str_starts_with($env, "/")) {
        return $env;
    }
    return app_root() . "/app/config.php";
}
function has_cfg(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — has_cfg
     * Responsabilidade: Avalia ou impõe a regra “has cfg”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_maestro_health_pill_html`, `admin_alerts_ensure_schema`, `onboarding_tips_ensure_schema`, `login_last_credential_remember`, `login_last_credential_from_meta`, `login_last_credential_from_devices`, `ensure_runtime_schema_minimum`, `agenda_notes_ensure_schema` e mais 39.
     * Dependências chamadas: `is_file`, `cfg_file`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return is_file(cfg_file());
}
function now(): string
{
    /*
     * GUIA DE MANUTENÇÃO — now
     * Responsabilidade: Implementa a responsabilidade “now” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `document_template_status_after_save`, `document_issue_context`, `financial_sync_appointment`, `billing_state`.
     * Dependências chamadas: `time`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return (string) time();
}
function app_timezone_safe(string $tz): string
{
    /*
     * GUIA DE MANUTENÇÃO — app_timezone_safe
     * Responsabilidade: Implementa a responsabilidade “app timezone safe” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_maintenance`, `app_global_admin_timezone`, `app_timezone_offset_string`, `app_timezone_offset_minutes`, `app_apply_request_timezone`, `app_context_timezone`, `page`.
     * Dependências chamadas: `trim`, `in_array`, `timezone_identifiers_list`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $tz = trim($tz);
    return in_array($tz, timezone_identifiers_list(), true)
        ? $tz
        : "America/Cuiaba";
}
function app_global_admin_timezone(int $userId = 0): string
{
    /*
     * GUIA DE MANUTENÇÃO — app_global_admin_timezone
     * Responsabilidade: Implementa a responsabilidade “app global admin timezone” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_maintenance`, `mark_login_success`, `ctx`.
     * Dependências chamadas: `has_cfg`, `function_exists`, `val`, `app_timezone_safe`, `error_log`, `->getMessage`.
     * Estado externo lido: `$_SESSION`, `$GLOBALS`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; lê ou altera a sessão; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $userId = $userId > 0 ? $userId : (int) ($_SESSION["uid"] ?? 0);
    if ($userId > 0 && has_cfg() && function_exists("val")) {
        try {
            $tz =
                (string) (val(
                    "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
                    ["global_admin_timezone_user_" . $userId],
                ) ?:
                "");
            if ($tz !== "") {
                return app_timezone_safe($tz);
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo global admin timezone user] " . $e->getMessage(),
            );
        }
    }
    if (has_cfg() && function_exists("val")) {
        try {
            $tz =
                (string) (val(
                    "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
                    ["global_admin_timezone_default"],
                ) ?:
                "");
            if ($tz !== "") {
                return app_timezone_safe($tz);
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo global admin timezone default] " . $e->getMessage(),
            );
        }
    }
    return app_timezone_safe(
        (string) ($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? "America/Cuiaba"),
    );
}
function app_force_utc_runtime(): void
{
    /*
     * GUIA DE MANUTENÇÃO — app_force_utc_runtime
     * Responsabilidade: Implementa a responsabilidade “app force utc runtime” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `app_apply_request_timezone`.
     * Dependências chamadas: `date_default_timezone_set`, `function_exists`, `has_cfg`, `pdo`, `->exec`, `error_log`, `->getMessage`.
     * Efeitos colaterais: gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    @date_default_timezone_set("UTC");
    if (function_exists("pdo") && has_cfg()) {
        try {
            pdo()->exec("SET time_zone='+00:00'");
        } catch (Throwable $e) {
            error_log("[Prontoo timezone UTC session] " . $e->getMessage());
        }
    }
}
function app_timezone_offset_string(string $tz, ?int $timestamp = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — app_timezone_offset_string
     * Responsabilidade: Implementa a responsabilidade “app timezone offset string” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `DateTimeZone`, `app_timezone_safe`, `DateTimeImmutable`, `time`, `->getOffset`, `abs`, `sprintf`, `intdiv`.
     * Classes ou serviços instanciados: `DateTimeZone`, `DateTimeImmutable`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $zone = new DateTimeZone(app_timezone_safe($tz));
    $dt = new DateTimeImmutable("@" . ($timestamp ?? time()));
    $offset = $zone->getOffset($dt);
    $sign = $offset < 0 ? "-" : "+";
    $offset = abs($offset);
    return sprintf(
        "%s%02d:%02d",
        $sign,
        intdiv($offset, 3600),
        intdiv($offset % 3600, 60),
    );
}
function app_timezone_offset_minutes(string $tz, ?int $timestamp = null): int
{
    /*
     * GUIA DE MANUTENÇÃO — app_timezone_offset_minutes
     * Responsabilidade: Implementa a responsabilidade “app timezone offset minutes” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `DateTimeZone`, `app_timezone_safe`, `DateTimeImmutable`, `time`, `floor`, `->getOffset`.
     * Classes ou serviços instanciados: `DateTimeZone`, `DateTimeImmutable`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $zone = new DateTimeZone(app_timezone_safe($tz));
    $dt = new DateTimeImmutable("@" . ($timestamp ?? time()));
    return (int) floor($zone->getOffset($dt) / 60);
}
function app_apply_request_timezone(string $tz): void
{
    /*
     * GUIA DE MANUTENÇÃO — app_apply_request_timezone
     * Responsabilidade: Implementa a responsabilidade “app apply request timezone” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_maintenance`, `mark_login_success`, `ctx`, `server_json_cache_apply_context_session`.
     * Dependências chamadas: `app_timezone_safe`, `app_force_utc_runtime`.
     * Estado externo lido: `$GLOBALS`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] = app_timezone_safe($tz);
    app_force_utc_runtime();
}
function app_context_timezone(?array $context = null, int $clinicId = 0): string
{
    /*
     * GUIA DE MANUTENÇÃO — app_context_timezone
     * Responsabilidade: Implementa a responsabilidade “app context timezone” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `doctor_work_ranges_for_day`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:4397`, `audit_activity_day_name`, `financial_dashboard_numbers`, `financial_default_drawer_unlock_local`, `financial_schedule_drawer_unlock`, `patient_week_utc_range` e mais 4.
     * Dependências chamadas: `is_array`, `trim`, `app_timezone_safe`, `function_exists`, `has_cfg`, `val`, `error_log`, `->getMessage`, `ctx`.
     * Estado externo lido: `$GLOBALS`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (
        is_array($context) &&
        trim((string) ($context["timezone"] ?? "")) !== ""
    ) {
        return app_timezone_safe((string) $context["timezone"]);
    }
    if ($clinicId > 0 && function_exists("val") && has_cfg()) {
        static $cache = [];
        if (isset($cache[$clinicId])) {
            return $cache[$clinicId];
        }
        try {
            $tz =
                (string) (val(
                    "SELECT timezone FROM pi_clinics WHERE id=? LIMIT 1",
                    [$clinicId],
                ) ?:
                "America/Cuiaba");
            return $cache[$clinicId] = app_timezone_safe($tz);
        } catch (Throwable $e) {
            error_log("[Prontoo clinic timezone] " . $e->getMessage());
        }
    }
    if (function_exists("ctx")) {
        try {
            $c = ctx();
            if (($c["scope"] ?? "") === "clinic") {
                return app_timezone_safe(
                    (string) ($c["timezone"] ?? "America/Cuiaba"),
                );
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
    return app_timezone_safe(
        (string) ($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? "America/Cuiaba"),
    );
}
function app_now_utc(): DateTimeImmutable
{
    /*
     * GUIA DE MANUTENÇÃO — app_now_utc
     * Responsabilidade: Implementa a responsabilidade “app now utc” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `financial_drawer_auto_unlock_row_if_due`, `app_now_in_timezone`.
     * Dependências chamadas: `DateTimeImmutable`, `DateTimeZone`.
     * Classes ou serviços instanciados: `DateTimeImmutable`, `DateTimeZone`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return new DateTimeImmutable("now", new DateTimeZone("UTC"));
}
function app_now_in_timezone(
    int $clinicId = 0,
    ?array $context = null,
): DateTimeImmutable {
    /*
     * GUIA DE MANUTENÇÃO — app_now_in_timezone
     * Responsabilidade: Implementa a responsabilidade “app now in timezone” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_maintenance`, `financial_default_drawer_unlock_local`, `app_today_in_timezone`, `app_datetime_br`.
     * Dependências chamadas: `app_now_utc`, `->setTimezone`, `DateTimeZone`, `app_context_timezone`.
     * Classes ou serviços instanciados: `DateTimeZone`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return app_now_utc()->setTimezone(
        new DateTimeZone(app_context_timezone($context, $clinicId)),
    );
}
function app_today_in_timezone(
    int $clinicId = 0,
    ?array $context = null,
): string {
    /*
     * GUIA DE MANUTENÇÃO — app_today_in_timezone
     * Responsabilidade: Implementa a responsabilidade “app today in timezone” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_appointments`, `audit_period_options`, `audit_period_clause`, `page_audit`, `financial_dashboard_numbers`, `financial_today`, `page_leads`, `patient_week_utc_range` e mais 12.
     * Dependências chamadas: `app_now_in_timezone`, `->format`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return app_now_in_timezone($clinicId, $context)->format("Y-m-d");
}
function app_parse_db_utc(null|string|int $value): ?DateTimeImmutable
{
    /*
     * GUIA DE MANUTENÇÃO — app_parse_db_utc
     * Responsabilidade: Transforma e normaliza “app parse db utc” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `agenda_validate_period_message`, `financial_drawer_auto_unlock_row_if_due`, `patient_appointment_duration_label`, `patient_appointment_elapsed_until_now_label`, `patient_appointment_not_started_label`, `app_db_utc_to_local`.
     * Dependências chamadas: `trim`, `preg_match`, `DateTimeImmutable`, `DateTimeZone`.
     * Classes ou serviços instanciados: `DateTimeImmutable`, `DateTimeZone`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return null;
    }
    try {
        if (preg_match('/^-?\d+$/', $value)) {
            return new DateTimeImmutable("@" . (int) $value);
        }
        return new DateTimeImmutable($value, new DateTimeZone("UTC"));
    } catch (Throwable $e) {
        return null;
    }
}
function app_db_utc_to_local(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): ?DateTimeImmutable {
    /*
     * GUIA DE MANUTENÇÃO — app_db_utc_to_local
     * Responsabilidade: Opera a etapa “app db utc to local” do contrato de banco e instalação, restrita às janelas autorizadas.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_maestro_health_time_label`, `appointment_within_doctor_hours`, `agenda_validate_period_message`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:4037`, `closure@app/Domain/Appointments/Appointments.php:4602`, `patient_reception_story_time`, `patient_appointment_not_started_label` e mais 9.
     * Dependências chamadas: `app_parse_db_utc`, `->setTimezone`, `DateTimeZone`, `app_context_timezone`.
     * Classes ou serviços instanciados: `DateTimeZone`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $dt = app_parse_db_utc($value);
    if (!$dt) {
        return null;
    }
    return $dt->setTimezone(
        new DateTimeZone(app_context_timezone($context, $clinicId)),
    );
}
function app_local_to_db_utc(
    ?string $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    /*
     * GUIA DE MANUTENÇÃO — app_local_to_db_utc
     * Responsabilidade: Opera a etapa “app local to db utc” do contrato de banco e instalação, restrita às janelas autorizadas.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `normalize_db_datetime`, `page_leads`, `closure@app/Domain/Leads/Leads.php:462`, `page_admin_global_notices`, `page_tasks`.
     * Dependências chamadas: `trim`, `class_exists`, `.Core.Temporal.PiTime::fromLocalToUtcTimestamp`, `preg_match`, `DateTimeImmutable`, `DateTimeZone`, `app_context_timezone`, `->setTimezone`, `->getTimestamp`.
     * Classes ou serviços instanciados: `DateTimeImmutable`, `DateTimeZone`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return "";
    }
    if (class_exists("\\Prontoo\\Core\\Temporal\\PiTime")) {
        return \Prontoo\Core\Temporal\PiTime::fromLocalToUtcTimestamp(
            $value,
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
        );
    }
    try {
        $dt = new DateTimeImmutable(
            $value,
            new DateTimeZone(app_context_timezone($context, $clinicId)),
        );
        return (string) $dt
            ->setTimezone(new DateTimeZone("UTC"))
            ->getTimestamp();
    } catch (Throwable $e) {
        return $value;
    }
}
function app_storage_timestamp(
    null|string|int $value,
    bool $endOfDay = false,
): int {
    /*
     * GUIA DE MANUTENÇÃO — app_storage_timestamp
     * Responsabilidade: Implementa a responsabilidade “app storage timestamp” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_painel`, `appointment_journey_hard_guard_message`, `appointment_journey_elapsed_label`, `closure@app/Domain/Appointments/Appointments.php:561`, `appointment_journey_view_model`, `appointment_journey_view_code`, `appointment_journey_role_actions`, `delay_minutes_from_appointment` e mais 22.
     * Dependências chamadas: `trim`, `preg_match`, `strtotime`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return 0;
    }
    if (preg_match('/^-?\d+$/', $value)) {
        return (int) $value;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $value .= $endOfDay ? " 23:59:59" : " 00:00:00";
    }
    $ts = strtotime($value . " UTC");
    if (!$ts) {
        $ts = strtotime($value);
    }
    return $ts ? (int) $ts : 0;
}
function app_date_input_from_storage(null|string|int $value): string
{
    /*
     * GUIA DE MANUTENÇÃO — app_date_input_from_storage
     * Responsabilidade: Implementa a responsabilidade “app date input from storage” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_clinic_detail_page`, `db_birth_date_input`, `upsert_person`, `save_person_flexible`, `page_counterparty_lookup`, `financial_drawer_daily_totals_map`, `page_lead_patient_lookup`, `patient_autosuggest_datalist` e mais 6.
     * Dependências chamadas: `trim`, `preg_match`, `gmdate`, `substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return "";
    }
    if (preg_match('/^-?\d+$/', $value)) {
        return gmdate("Y-m-d", (int) $value);
    }
    return preg_match("/^\d{4}-\d{2}-\d{2}/", $value)
        ? substr($value, 0, 10)
        : "";
}
function app_db_utc_to_local_input(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    /*
     * GUIA DE MANUTENÇÃO — app_db_utc_to_local_input
     * Responsabilidade: Opera a etapa “app db utc to local input” do contrato de banco e instalação, restrita às janelas autorizadas.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `datetime_local_value`, `page_lead_lookup`, `page_leads`.
     * Dependências chamadas: `app_db_utc_to_local`, `->format`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $dt = app_db_utc_to_local($value, $clinicId, $context);
    return $dt ? $dt->format("Y-m-d\TH:i") : "";
}
function app_local_day_utc_range(
    string $day,
    int $clinicId = 0,
    ?array $context = null,
): array {
    /*
     * GUIA DE MANUTENÇÃO — app_local_day_utc_range
     * Responsabilidade: Implementa a responsabilidade “app local day utc range” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_appointments`, `audit_period_clause`, `financial_dashboard_numbers`, `financial_daily_consolidation_state`, `financial_admin_daily_consolidation_html`, `financial_cashier_pending_receipts_html`, `financial_admin_daily_ledger_timeline`, `financial_admin_daily_conference_panel` e mais 14.
     * Dependências chamadas: `preg_match`, `app_today_in_timezone`, `DateTimeZone`, `app_context_timezone`, `DateTimeImmutable`, `->modify`, `->setTimezone`, `->getTimestamp`.
     * Classes ou serviços instanciados: `DateTimeZone`, `DateTimeImmutable`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        $day = app_today_in_timezone($clinicId, $context);
    }
    $zone = new DateTimeZone(app_context_timezone($context, $clinicId));
    $start = new DateTimeImmutable($day . " 00:00:00", $zone);
    $end = $start->modify("+1 day");
    return [
        (string) $start->setTimezone(new DateTimeZone("UTC"))->getTimestamp(),
        (string) $end->setTimezone(new DateTimeZone("UTC"))->getTimestamp(),
    ];
}
function app_date_only_end_timestamp(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): int {
    /*
     * GUIA DE MANUTENÇÃO — app_date_only_end_timestamp
     * Responsabilidade: Implementa a responsabilidade “app date only end timestamp” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `clinic_read_only_db`, `subscription_paid_is_active`, `billing_state`.
     * Dependências chamadas: `app_date_input_from_storage`, `preg_match`, `checkdate`, `app_local_day_utc_range`, `max`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ymd = app_date_input_from_storage($value);
    if ($ymd === "" || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return 0;
    }
    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return 0;
    }
    [, $end] = app_local_day_utc_range($ymd, $clinicId, $context);
    return max(0, (int) $end - 1);
}
function app_time_br(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    /*
     * GUIA DE MANUTENÇÃO — app_time_br
     * Responsabilidade: Implementa a responsabilidade “app time br” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `agenda_conflict_message`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3790`, `closure@app/Domain/Appointments/Appointments.php:3850`, `closure@app/Domain/Appointments/Appointments.php:4397`, `financial_cashier_pending_receipts_html`, `financial_admin_drawers_panel`, `financial_admin_daily_ledger_timeline` e mais 8.
     * Dependências chamadas: `app_db_utc_to_local`, `->format`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $dt = app_db_utc_to_local($value, $clinicId, $context);
    return $dt ? $dt->format("H:i") : "--:--";
}
function app_date_br(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    /*
     * GUIA DE MANUTENÇÃO — app_date_br
     * Responsabilidade: Implementa a responsabilidade “app date br” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `financial_creditor_directory_card`, `patient_directory_card`, `page_patient_suggest`, `date_br`.
     * Dependências chamadas: `app_db_utc_to_local`, `->format`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $dt = app_db_utc_to_local($value, $clinicId, $context);
    return $dt ? $dt->format("d/m/Y") : "—";
}
function app_datetime_br(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    /*
     * GUIA DE MANUTENÇÃO — app_datetime_br
     * Responsabilidade: Implementa a responsabilidade “app datetime br” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `activity_time_direct`, `dt_br`.
     * Dependências chamadas: `app_db_utc_to_local`, `trim`, `prontoo_months_br`, `->format`, `app_now_in_timezone`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $dt = app_db_utc_to_local($value, $clinicId, $context);
    if (!$dt) {
        return trim((string) ($value ?? "")) ?: "—";
    }
    $m = prontoo_months_br();
    $year = (int) $dt->format("Y");
    $current = (int) app_now_in_timezone($clinicId, $context)->format("Y");
    $date =
        $dt->format("d") .
        " de " .
        $m[(int) $dt->format("n")] .
        ($year === $current ? "" : " de " . $dt->format("Y"));
    return $date . ", às " . $dt->format("H\hi");
}
function patient_display_name(int $patientLinkId, ?int $cid = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — patient_display_name
     * Responsabilidade: Implementa a responsabilidade “patient display name” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_appointments`, `maestro_fetch_candidates`, `workflow_on_appointment_finished`, `workflow_on_patient_arrived`, `workflow_on_task_completed`.
     * Dependências chamadas: `function_exists`, `trim`, `audit_patient_name_by_link`, `val`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        if (function_exists("audit_patient_name_by_link")) {
            $name = trim((string) audit_patient_name_by_link($patientLinkId, $cid));
            if ($name !== "") {
                return $name;
            }
        }
        $params = [$patientLinkId];
        $where = "pp.id=?";
        if ($cid !== null && $cid > 0) {
            $where .= " AND pp.clinic_id=?";
            $params[] = $cid;
        }
        $name = trim((string) (val(
            "SELECT p.full_name FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE $where LIMIT 1",
            $params,
        ) ?: ""));
        return $name !== "" ? $name : "paciente #" . $patientLinkId;
    } catch (Throwable $e) {
        error_log("[Prontoo patient display name] " . $e->getMessage());
        return "paciente #" . $patientLinkId;
    }
}
function maintenance_active(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — maintenance_active
     * Responsabilidade: Implementa a responsabilidade “maintenance active” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_maintenance`, `prontoo_run`.
     * Dependências chamadas: `has_cfg`, `function_exists`, `meta_get`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return has_cfg() && function_exists("meta_get") && meta_get("maintenance_active", "0") === "1";
}
function page_maintenance_notice(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_maintenance_notice
     * Responsabilidade: Coordena a rota e renderiza a tela “page maintenance notice”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_run`.
     * Dependências chamadas: `function_exists`, `meta_get`, `page`, `e`, `href`.
     * Efeitos colaterais: produz conteúdo de saída.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $message = function_exists("meta_get")
        ? meta_get(
            "maintenance_message",
            "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.",
        )
        : "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.";
    page(
        "Manutenção programada",
        '<section class="auth widebox"><h1>Prontoo em manutenção</h1><p>' .
            e($message) .
            '</p><p><a class="ghost" href="' .
            e(href("login")) .
            '">Voltar ao início</a></p></section>',
        ["public" => true, "robots" => "noindex,nofollow"],
    );
}
function only_digits(string $s): string
{
    /*
     * GUIA DE MANUTENÇÃO — only_digits
     * Responsabilidade: Implementa a responsabilidade “only digits” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `valid_cpf`, `valid_cnpj`, `page_login`, `page_signup`, `upsert_person`, `save_person_flexible`, `phone_br`, `page_person_lookup` e mais 40.
     * Dependências chamadas: `preg_replace`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return preg_replace("/\D+/", "", $s) ?? "";
}
function cpf_br(?string $cpf): string
{
    /*
     * GUIA DE MANUTENÇÃO — cpf_br
     * Responsabilidade: Implementa a responsabilidade “cpf br” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_clinic_detail_page`, `page_person_lookup`, `document_issue_context`.
     * Dependências chamadas: `only_digits`, `strlen`, `substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $d = only_digits((string) ($cpf ?? ""));
    return strlen($d) === 11
        ? substr($d, 0, 3) .
                "." .
                substr($d, 3, 3) .
                "." .
                substr($d, 6, 3) .
                "-" .
                substr($d, 9, 2)
        : (string) ($cpf ?? "");
}
function app_config_string(string $key, string $default = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — app_config_string
     * Responsabilidade: Implementa a responsabilidade “app config string” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `document_pdf_token_secret`, `app_is_production`, `app_canonical_host`.
     * Dependências chamadas: `function_exists`, `has_cfg`, `cfg`, `is_scalar`, `trim`, `error_log`, `->getMessage`, `getenv`, `strtoupper`, `is_string`.
     * Efeitos colaterais: gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        if (function_exists("cfg") && has_cfg()) {
            $c = cfg();
            if (isset($c[$key]) && is_scalar($c[$key])) {
                return trim((string) $c[$key]);
            }
        }
    } catch (Throwable $e) {
        error_log("[Prontoo config string] " . $e->getMessage());
    }
    $env = getenv("PRONTOO_" . strtoupper($key));
    return is_string($env) && trim($env) !== "" ? trim($env) : $default;
}
function app_is_production(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — app_is_production
     * Responsabilidade: Avalia ou impõe a regra “app is production”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `app_enforce_canonical_host`, `request_host`, `app_debug`.
     * Dependências chamadas: `has_cfg`, `strtolower`, `app_config_string`, `getenv`, `in_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $defaultEnv = has_cfg() ? "production" : "development";
    $env = strtolower(
        app_config_string(
            "app_env",
            (string) (getenv("APP_ENV") ?:
            getenv("PRONTOO_ENV") ?:
            $defaultEnv),
        ),
    );
    return in_array($env, ["prod", "production", "prd"], true);
}

function app_canonical_host(): string
{
    /*
     * GUIA DE MANUTENÇÃO — app_canonical_host
     * Responsabilidade: Implementa a responsabilidade “app canonical host” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `app_enforce_canonical_host`, `request_host`.
     * Dependências chamadas: `app_config_string`, `defined`, `strtolower`, `preg_replace`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $host = app_config_string(
        "canonical_host",
        defined("PRONTOO_CANONICAL_HOST")
            ? (string) PRONTOO_CANONICAL_HOST
            : "",
    );
    $host = strtolower(
        preg_replace(
            "/[^a-zA-Z0-9.\-]/",
            "",
            preg_replace('/:\d+$/', "", $host),
        ) ?:
        "",
    );
    return $host;
}
function request_host_raw(): string
{
    /*
     * GUIA DE MANUTENÇÃO — request_host_raw
     * Responsabilidade: Implementa a responsabilidade “request host raw” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `app_enforce_canonical_host`, `request_host`.
     * Dependências chamadas: `preg_replace`.
     * Estado externo lido: `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $host = (string) ($_SERVER["HTTP_HOST"] ?? "localhost");
    $host = preg_replace("/[^a-zA-Z0-9\.\-:\[\]]/", "", $host) ?: "localhost";
    return $host;
}
function app_enforce_canonical_host(): void
{
    /*
     * GUIA DE MANUTENÇÃO — app_enforce_canonical_host
     * Responsabilidade: Implementa a responsabilidade “app enforce canonical host” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `guard_request`.
     * Dependências chamadas: `app_is_production`, `app_canonical_host`, `strtolower`, `preg_replace`, `request_host_raw`, `in_array`, `ProntooHttpError`.
     * Classes ou serviços instanciados: `ProntooHttpError`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (PHP_SAPI === "cli" || !app_is_production()) {
        return;
    }
    $canonical = app_canonical_host();
    if ($canonical === "") {
        return;
    }
    $raw = strtolower(preg_replace('/:\d+$/', "", request_host_raw()) ?: "");
    if (
        $raw === "" ||
        in_array($raw, ["localhost", "127.0.0.1", "::1"], true)
    ) {
        return;
    }
    if ($raw !== $canonical) {
        throw new ProntooHttpError(
            421,
            "Host não reconhecido para esta instalação.",
        );
    }
}

function is_https(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — is_https
     * Responsabilidade: Avalia ou impõe a regra “is https”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `base_url`.
     * Dependências chamadas: `function_exists`, `security_https_active`.
     * Estado externo lido: `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (function_exists("security_https_active")) {
        return security_https_active();
    }
    return (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
        (string) ($_SERVER["SERVER_PORT"] ?? "") === "443";
}
function request_host(): string
{
    /*
     * GUIA DE MANUTENÇÃO — request_host
     * Responsabilidade: Implementa a responsabilidade “request host” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `base_url`.
     * Dependências chamadas: `app_is_production`, `app_canonical_host`, `request_host_raw`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $canonical = app_is_production() ? app_canonical_host() : "";
    if ($canonical !== "") {
        return $canonical;
    }
    return request_host_raw();
}
function base_path(): string
{
    /*
     * GUIA DE MANUTENÇÃO — base_path
     * Responsabilidade: Implementa a responsabilidade “base path” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `onboarding_tip_dismiss`, `document_pdf_public_url`, `base_url`, `href`.
     * Dependências chamadas: `rtrim`, `str_replace`.
     * Estado externo lido: `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $script = (string) ($_SERVER["SCRIPT_NAME"] ?? "/");
    $dir = rtrim(str_replace(["install.php", "index.php"], "", $script), "/");
    return $dir ?: "";
}
function base_url(string $suffix = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — base_url
     * Responsabilidade: Implementa a responsabilidade “base url” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `is_https`, `request_host`, `base_path`, `ltrim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return (is_https() ? "https://" : "http://") .
        request_host() .
        base_path() .
        "/" .
        ltrim($suffix, "/");
}
function href(string $route, array $params = []): string
{
    /*
     * GUIA DE MANUTENÇÃO — href
     * Responsabilidade: Implementa a responsabilidade “href” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `stat_link_card`, `admin_quick_links`, `admin_global_compact_pill`, `page_admin_health`, `page_admin_maintenance`, `page_admin_painel`, `admin_clinic_detail_page`, `page_admin_clinics` e mais 73.
     * Dependências chamadas: `base_path`, `http_build_query`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return (base_path() ?: "") .
        "/?" .
        http_build_query(["r" => $route] + $params);
}
function redirect(string $route, array $params = []): void
{
    /*
     * GUIA DE MANUTENÇÃO — redirect
     * Responsabilidade: Implementa a responsabilidade “redirect” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_deleted`, `page_admin_errors`, `page_admin_onboarding`, `page_admin_maintenance`, `page_admin_settings`, `page_admin_painel`, `page_admin_users`, `page_admin_stats` e mais 37.
     * Dependências chamadas: `headers_sent`, `header`, `href`.
     * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    if (!headers_sent()) {
        header("Location: " . href($route, $params));
    }
    exit();
}
function route(): string
{
    /*
     * GUIA DE MANUTENÇÃO — route
     * Responsabilidade: Implementa a responsabilidade “route” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `onboarding_tip_dismiss`, `financial_cash_exception_debug`, `financial_cash_debug_request_active`, `prontoo_run`, `log_runtime_error`, `check_csrf`, `device_session_remember_after_login`, `device_session_update_current_context` e mais 11.
     * Dependências chamadas: `preg_replace`.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return preg_replace("/[^a-z0-9_\-]/i", "", $_GET["r"] ?? "login") ?:
        "login";
}
function app_debug(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — app_debug
     * Responsabilidade: Implementa a responsabilidade “app debug” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_diagnostics`, `financial_cash_debug_details_enabled`, `app_fail`.
     * Dependências chamadas: `app_is_production`, `getenv`, `has_cfg`, `cfg`, `error_log`, `->getMessage`.
     * Efeitos colaterais: gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (
        app_is_production() &&
        (string) getenv("PRONTOO_ALLOW_PRODUCTION_DEBUG") !== "1"
    ) {
        return false;
    }
    try {
        $c = has_cfg() ? cfg() : [];
        return !empty($c["debug"]);
    } catch (Throwable $e) {
        error_log("[Prontoo debug cfg] " . $e->getMessage());
        return false;
    }
}
function app_public_error_message(
    Throwable $error,
    string $fallback = "Não foi possível concluir esta ação.",
): string {
    /*
     * GUIA DE MANUTENÇÃO — app_public_error_message
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “app public error message”.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_profile`, `page_settings`, `page_documents`, `page_creditors`, `financial_admin_page`, `page_patient`, `page_user`.
     * Dependências chamadas: `trim`, `->getMessage`, `preg_match`, `mb_substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $message = trim($error->getMessage());
    if ($message === "" || $error instanceof PDOException) {
        return $fallback;
    }
    if (
        preg_match(
            "/(?:SQLSTATE|\b(?:SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\b|\b(?:table|column|constraint|driver|database)\b|\.php\b|stack trace|\/app\/|\\app\\)/i",
            $message,
        )
    ) {
        return $fallback;
    }
    return mb_substr($message, 0, 240);
}

function app_fail(Throwable $e, int $status = 500): void
{
    /*
     * GUIA DE MANUTENÇÃO — app_fail
     * Responsabilidade: Implementa a responsabilidade “app fail” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_install`, `prontoo_run`.
     * Dependências chamadas: `error_log`, `function_exists`, `privacy_sanitize_error_message`, `->getMessage`, `privacy_log_file_label`, `->getFile`, `basename`, `->getLine`, `log_runtime_error`, `headers_sent`, `http_response_code`, `header` e mais 6.
     * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP; produz conteúdo de saída; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    $status = $e instanceof ProntooHttpError ? $e->status : $status;
    $http = $e instanceof ProntooHttpError;
    if (!$http || $status >= 500) {
        error_log(
            "[Prontoo fatal] " .
                (function_exists("privacy_sanitize_error_message")
                    ? privacy_sanitize_error_message($e, 240)
                    : $e->getMessage()) .
                " in " .
                (function_exists("privacy_log_file_label")
                    ? privacy_log_file_label($e->getFile())
                    : basename($e->getFile())) .
                ":" .
                $e->getLine(),
        );
        log_runtime_error($e, $status);
    }
    if (!headers_sent()) {
        http_response_code($status);
        header("Content-Type: text/html; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    $title = $http
        ? "Não foi possível continuar com esta sessão."
        : "Não foi possível concluir esta ação.";
    $message = $http
        ? $e->getMessage()
        : "O Prontoo registrou uma instabilidade e evitou continuar em estado inconsistente. Tente novamente em alguns instantes.";
    $detail = app_debug()
        ? "<pre>" .
            e($e->getMessage()) .
            "
" .
            e($e->getFile() . ":" . $e->getLine()) .
            "</pre>"
        : "";
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="canonical" href="https://prontoo.app/"><title>Prontoo — instabilidade temporária</title><meta name="theme-color" content="#334155"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="prontoo-version" content="' .
        e(PRONTOO_VERSION) .
        '"><meta name="csrf-token" content="' .
        e(csrf()) .
        '"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" href="/public/assets/favicon-' .
        rawurlencode(PRONTOO_ASSET_REV) .
        '.png" type="image/png"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
        rawurlencode(
            defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
        ) .
        '"></head><body class="public"><main><section class="auth widebox"><h1>' .
        e($title) .
        "</h1><p>" .
        e($message) .
        "</p>" .
        $detail .
        '<p><a class="primary" href="' .
        e(href("login")) .
        '">Voltar ao início</a></p></section></main></body></html>';
    exit();
}
function runtime_self_check(): void
{
    /*
     * GUIA DE MANUTENÇÃO — runtime_self_check
     * Responsabilidade: Avalia ou impõe a regra “runtime self check”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_install`, `prontoo_run_runtime_maintenance_cycle`.
     * Dependências chamadas: `.Core.Install.RuntimeContract::assert`, `app_root`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    \Prontoo\Core\Install\RuntimeContract::assert(app_root(), PRONTOO_VERSION);
}
function cfg(): array
{
    /*
     * GUIA DE MANUTENÇÃO — cfg
     * Responsabilidade: Implementa a responsabilidade “cfg” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `pdo`, `app_config_string`, `app_debug`, `secret_key`, `seq_footer_deterministic_alphabet`, `pdo_metric`.
     * Dependências chamadas: `has_cfg`, `cfg_file`, `is_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = has_cfg() ? require cfg_file() : [];
    return is_array($c) ? $c : [];
}
function storage_path(string $path = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — storage_path
     * Responsabilidade: Implementa a responsabilidade “storage path” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `platform_storage_status`, `db_runtime_notice_once`, `schema_lock_file`, `subscription_payment_proof_storage`, `subscription_payment_proof_absolute_path`, `document_pdf_storage_dir`, `document_pdf_cleanup_due`, `maestro_cron_run` e mais 18.
     * Dependências chamadas: `app_root`, `ltrim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return app_root() . "/ssd" . ($path ? "/" . ltrim($path, "/") : "");
}
function cache_path(string $key): string
{
    /*
     * GUIA DE MANUTENÇÃO — cache_path
     * Responsabilidade: Gerencia o cache ou a memoização de “cache path”, reduzindo I/O sem substituir a fonte canônica.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `cache_get`, `cache_set`.
     * Dependências chamadas: `storage_path`, `is_dir`, `mkdir`, `preg_replace`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $dir = storage_path("cache");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir .
        "/" .
        preg_replace("/[^a-zA-Z0-9_\-\.]/", "_", $key) .
        ".json";
}
function cache_get(string $key, int $ttl): mixed
{
    /*
     * GUIA DE MANUTENÇÃO — cache_get
     * Responsabilidade: Localiza, carrega ou resolve os dados de “cache get” para consumo pelas camadas superiores.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `cache_remember`, `cached_val`, `log_runtime_error`, `security_rate_limit`.
     * Dependências chamadas: `cache_path`, `is_file`, `time`, `filemtime`, `file_get_contents`, `json_decode`, `is_array`, `array_key_exists`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $f = cache_path($key);
    if (!is_file($f) || time() - filemtime($f) > $ttl) {
        return null;
    }
    $raw = @file_get_contents($f);
    if ($raw === false) {
        return null;
    }
    $j = json_decode($raw, true);
    return is_array($j) && array_key_exists("v", $j) ? $j["v"] : null;
}
function cache_set(string $key, mixed $value): mixed
{
    /*
     * GUIA DE MANUTENÇÃO — cache_set
     * Responsabilidade: Gerencia o cache ou a memoização de “cache set”, reduzindo I/O sem substituir a fonte canônica.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `cache_remember`, `cached_val`, `log_runtime_error`, `security_rate_limit`.
     * Dependências chamadas: `file_put_contents`, `cache_path`, `json_encode`, `time`.
     * Efeitos colaterais: produz conteúdo de saída; acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    @file_put_contents(
        cache_path($key),
        json_encode(["t" => time(), "v" => $value], JSON_UNESCAPED_UNICODE),
        LOCK_EX,
    );
    return $value;
}
function cache_remember(string $key, int $ttl, callable $fn): mixed
{
    /*
     * GUIA DE MANUTENÇÃO — cache_remember
     * Responsabilidade: Gerencia o cache ou a memoização de “cache remember”, reduzindo I/O sem substituir a fonte canônica.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `platform_backend_selftest`.
     * Dependências chamadas: `error_log`, `->getMessage`, `cache_get`, `cache_set`.
     * Efeitos colaterais: lê, grava ou invalida cache; gera trilha de auditoria ou telemetria.
     * Cuidado 1: O cache é derivado: preserve TTL, chave por escopo e invalidação por tags; nunca o trate como fonte de verdade.
     */
    if ($ttl <= 0) {
        try {
            return $fn();
        } catch (Throwable $e) {
            error_log("[Prontoo cache_remember realtime] " . $e->getMessage());
            return null;
        }
    }
    $v = cache_get($key, $ttl);
    if ($v !== null) {
        return $v;
    }
    try {
        return cache_set($key, $fn());
    } catch (Throwable $e) {
        error_log("[Prontoo cache_remember] " . $e->getMessage());
        return null;
    }
}
function cached_val(string $key, int $ttl, string $sql, array $p = []): mixed
{
    /*
     * GUIA DE MANUTENÇÃO — cached_val
     * Responsabilidade: Implementa a responsabilidade “cached val” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `platform_backend_selftest`, `page_admin_health`, `page_admin_integrity`, `open_incidents_count`, `count_recent_or_counter`.
     * Dependências chamadas: `max`, `val`, `hash`, `json_encode`, `function_exists`, `server_json_cache_read_allowed`, `server_json_cache_remember`, `cache_get`, `cache_set`.
     * Efeitos colaterais: acessa a camada de persistência; produz conteúdo de saída; lê, grava ou invalida cache.
     * Cuidado 1: O cache é derivado: preserve TTL, chave por escopo e invalidação por tags; nunca o trate como fonte de verdade.
     */
    $ttl = max(0, $ttl);
    if ($ttl <= 0) {
        return val($sql, $p);
    }
    $ck =
        "sql_" .
        $key .
        "_" .
        hash(
            "sha256",
            $sql .
                "|" .
                json_encode(
                    $p,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
        );
    if (
        function_exists("server_json_cache_remember") &&
        server_json_cache_read_allowed()
    ) {
        return server_json_cache_remember(
            "warm",
            $ck,
            $ttl,
            /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `val`. Efeitos: acessa a camada de persistência. */ fn() => val($sql, $p),
            ["sql"],
        );
    }
    $cached = cache_get($ck, $ttl);
    if ($cached !== null) {
        return $cached;
    }
    return cache_set($ck, val($sql, $p));
}
function bounded_limit(int $n, int $max = PRONTOO_HOT_LIST_LIMIT): int
{
    /*
     * GUIA DE MANUTENÇÃO — bounded_limit
     * Responsabilidade: Implementa a responsabilidade “bounded limit” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `max`, `min`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return max(1, min($max, $n));
}
function counter_key(string $name): string
{
    /*
     * GUIA DE MANUTENÇÃO — counter_key
     * Responsabilidade: Implementa a responsabilidade “counter key” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `clinic_metric_inc`, `counter_inc`, `counter_get`.
     * Dependências chamadas: `preg_replace`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return preg_replace("/[^a-z0-9_\-\.]/i", "_", $name) ?: "counter";
}
function counter_inc(string $name, int $by = 1): void
{
    /*
     * GUIA DE MANUTENÇÃO — counter_inc
     * Responsabilidade: Implementa a responsabilidade “counter inc” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_signup`, `page_appointments`, `financial_notify_drawer_locked`, `financial_notify_opening_authorization_request`, `page_leads`, `maestro_create_action`, `page_patients`, `page_patient` e mais 7.
     * Dependências chamadas: `q`, `counter_key`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    try {
        q(
            "INSERT INTO pi_platform_counters (counter_key,counter_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE counter_value=counter_value+VALUES(counter_value), updated_at=NOW()",
            [counter_key($name), $by],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo counter_inc] " . $e->getMessage());
    }
}
function counter_get(string $name, int $fallback = 0): int
{
    /*
     * GUIA DE MANUTENÇÃO — counter_get
     * Responsabilidade: Localiza, carrega ou resolve os dados de “counter get” para consumo pelas camadas superiores.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `val`, `counter_key`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        return (int) (val(
            "SELECT counter_value FROM pi_platform_counters WHERE counter_key=?",
            [counter_key($name)],
        ) ?? $fallback);
    } catch (Throwable $e) {
        error_log("[Prontoo counter_get] " . $e->getMessage());
        return $fallback;
    }
}
function prontoo_login_selftest_light(): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_login_selftest_light
     * Responsabilidade: Implementa a responsabilidade “prontoo login selftest light” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_login_autotest`.
     * Dependências chamadas: `pdo`, `->query`, `->fetchColumn`, `hash`, `->getMessage`, `db_table_exists`, `function_exists`, `storage_path`, `app_root`, `disk_free_space`, `is_dir`, `is_writable`.
     * Efeitos colaterais: acessa a camada de persistência.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ok = true;
    $checks = ["mode" => "login_light", "version" => PRONTOO_VERSION];
    try {
        $dbOk = (int) pdo()->query("SELECT 1")->fetchColumn() === 1;
        $checks["db"] = $dbOk;
        $checks["database"] = $dbOk;
        $ok = $ok && $dbOk;
    } catch (Throwable $e) {
        $checks["db"] = false;
        $checks["database"] = false;
        $checks["db_error_hash"] = hash("sha256", $e->getMessage());
        $ok = false;
    }
    try {
        $runtimeOk =
            db_table_exists("pi_meta");
        $checks["pi_runtime"] = $runtimeOk;
    } catch (Throwable $e) {
        $checks["pi_runtime"] = false;
    }
    try {
        $storageDir = function_exists("storage_path")
            ? storage_path()
            : app_root();
        $free = @disk_free_space($storageDir);
        $writable = is_dir($storageDir) && is_writable($storageDir);
        $freeOk = $free === false ? true : $free > 20 * 1024 * 1024;
        $checks["storage"] = $writable && $freeOk;
        $checks["storage_free_bytes"] = $free === false ? null : (float) $free;
        $ok = $ok && (bool) $checks["storage"];
    } catch (Throwable $e) {
        $checks["storage"] = false;
        $ok = false;
    }
    $checks["maintenance_deferred"] = true;
    $checks["ok"] = $ok;
    return $checks;
}
function page_login_autotest(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_login_autotest
     * Responsabilidade: Coordena a rota e renderiza a tela “page login autotest”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `headers_sent`, `header`, `security_rate_limit`, `security_client_bucket`, `http_response_code`, `json_encode`, `prontoo_login_selftest_light`, `device_session_auto_login`, `ctx`, `href`, `function_exists`, `platform_login_loaded_audit` e mais 3.
     * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP; produz conteúdo de saída; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 2: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    if (
        security_rate_limit(security_client_bucket("login_autotest"), 90, 300)
    ) {
        http_response_code(429);
        echo json_encode(
            [
                "ok" => false,
                "auto_login" => false,
                "redirect" => "",
                "version" => PRONTOO_VERSION,
                "mode" => "login_light",
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        exit();
    }
    $checks = prontoo_login_selftest_light();
    $auto = false;
    $redirect = "";
    if (!empty($checks["ok"]) && device_session_auto_login()) {
        $auto = true;
        $c = ctx();
        $redirect = href(
            ($c["scope"] ?? "") === "global" ? "admin_painel" : "appointments",
        );
    }
    if (function_exists("platform_login_loaded_audit")) {
        platform_login_loaded_audit($checks, $auto);
    } elseif (function_exists("audit")) {
        try {
            audit("login_autoteste_leve", "login", null, [
                "ok" => !empty($checks["ok"]) ? 1 : 0,
                "maintenance_deferred" => 1,
                "audit_body" =>
                    "Autoteste leve do login concluído; diagnósticos e manutenção pesada ficam para depois da senha correta.",
            ]);
        } catch (Throwable $e) {
            error_log("[Prontoo light login audit] " . $e->getMessage());
        }
    }
    echo json_encode(
        [
            "ok" => !empty($checks["ok"]),
            "auto_login" => $auto,
            "redirect" => $redirect,
            "version" => PRONTOO_VERSION,
            "mode" => $checks["mode"] ?? "login_light",
            "maintenance_deferred" => true,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
    exit();
}
function person_signature_value(
    string $identity,
    string $name,
    ?string $date,
): string {
    /*
     * GUIA DE MANUTENÇÃO — person_signature_value
     * Responsabilidade: Implementa a responsabilidade “person signature value” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `upsert_person`, `save_person_flexible`, `lead_prepare_person_for_patient`, `person_signature_sync`.
     * Dependências chamadas: `only_digits`, `mb_strtolower`, `trim`, `hash_hmac`, `secret_key`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $base =
        only_digits($identity) .
        "|" .
        mb_strtolower(trim($name), "UTF-8") .
        "|" .
        trim((string) $date);
    return hash_hmac("sha256", $base, secret_key());
}
function person_signature_sync(int $personId, bool $verify = false): void
{
    /*
     * GUIA DE MANUTENÇÃO — person_signature_sync
     * Responsabilidade: Implementa a responsabilidade “person signature sync” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `person_signature_refresh`, `person_signature_refresh_verified`.
     * Dependências chamadas: `one`, `RuntimeException`, `person_signature_value`, `trim`, `hash_equals`, `q`, `val`, `error_log`, `->getMessage`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    if ($personId <= 0) {
        return;
    }
    try {
        $person = one(
            "SELECT id,full_name,cpf,birth_date,assinatura FROM pi_persons WHERE id=?",
            [$personId],
        );
        if (!$person) {
            if ($verify) {
                throw new RuntimeException("Pessoa não encontrada para validar a identidade.");
            }
            return;
        }
        $expected = person_signature_value(
            (string) ($person["cpf"] ?? ""),
            (string) ($person["full_name"] ?? ""),
            (string) ($person["birth_date"] ?? ""),
        );
        $current = trim((string) ($person["assinatura"] ?? ""));
        if ($current === "" || !hash_equals($expected, $current)) {
            q(
                "UPDATE pi_persons SET assinatura=?, updated_at=COALESCE(updated_at,NOW()) WHERE id=?",
                [$expected, $personId],
            );
        }
        if ($verify) {
            $stored = trim((string) val(
                "SELECT assinatura FROM pi_persons WHERE id=?",
                [$personId],
            ));
            if ($stored === "" || !hash_equals($expected, $stored)) {
                throw new RuntimeException(
                    "A assinatura de identidade da pessoa não pôde ser atualizada.",
                );
            }
        }
    } catch (Throwable $e) {
        if ($verify) {
            throw $e;
        }
        error_log("[Prontoo person signature] " . $e->getMessage());
    }
}
function person_signature_refresh(int $personId): void
{
    /*
     * GUIA DE MANUTENÇÃO — person_signature_refresh
     * Responsabilidade: Implementa a responsabilidade “person signature refresh” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_patient`.
     * Dependências chamadas: `person_signature_sync`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    person_signature_sync($personId, false);
}
function person_signature_refresh_verified(int $personId): void
{
    /*
     * GUIA DE MANUTENÇÃO — person_signature_refresh_verified
     * Responsabilidade: Implementa a responsabilidade “person signature refresh verified” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `upsert_person`, `save_person_flexible`, `page_user`.
     * Dependências chamadas: `person_signature_sync`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    person_signature_sync($personId, true);
}
function person_identity_immutable_values(
    int $personId,
    ?string $cpfInput = null,
    ?string $birthInput = null,
    bool $requireMissing = false,
): array {
    /*
     * GUIA DE MANUTENÇÃO — person_identity_immutable_values
     * Responsabilidade: Implementa a responsabilidade “person identity immutable values” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `upsert_person`, `save_person_flexible`, `lead_prepare_person_for_patient`, `page_patient`.
     * Dependências chamadas: `one`, `only_digits`, `trim`, `app_date_input_from_storage`, `RuntimeException`, `valid_cpf`, `valid_birth_date`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $current =
        $personId > 0
            ? (one("SELECT cpf,birth_date FROM pi_persons WHERE id=?", [
                $personId,
            ]) ?:
            [])
            : [];
    $currentCpf = only_digits((string) ($current["cpf"] ?? ""));
    $currentBirthRaw = trim((string) ($current["birth_date"] ?? ""));
    $currentBirth = app_date_input_from_storage($currentBirthRaw);
    $newCpf = only_digits((string) ($cpfInput ?? ""));
    $newBirth = app_date_input_from_storage(trim((string) ($birthInput ?? "")));
    if ($currentCpf !== "") {
        if ($newCpf !== "" && $newCpf !== $currentCpf) {
            throw new RuntimeException(
                "CPF é dado de identidade imutável e não pode ser alterado.",
            );
        }
        $newCpf = $currentCpf;
    } else {
        if ($newCpf !== "" && !valid_cpf($newCpf)) {
            throw new RuntimeException("Este CPF não existe.");
        }
        if ($requireMissing && $newCpf === "") {
            throw new RuntimeException("Informe um CPF válido.");
        }
    }
    if ($currentBirth !== "") {
        if ($newBirth !== "" && $newBirth !== $currentBirth) {
            throw new RuntimeException(
                "Nascimento é dado de identidade imutável e não pode ser alterado.",
            );
        }
        $newBirth = $currentBirthRaw !== "" ? $currentBirthRaw : $currentBirth;
    } else {
        if ($newBirth !== "" && !valid_birth_date($newBirth)) {
            throw new RuntimeException("Nascimento inválido.");
        }
        if ($requireMissing && $newBirth === "") {
            throw new RuntimeException("Nascimento inválido.");
        }
    }
    return ["cpf" => $newCpf, "birth_date" => $newBirth];
}
function count_recent_or_counter(
    string $counter,
    string $sql,
    array $p = [],
    int $ttl = PRONTOO_DASHBOARD_COUNTER_TTL,
): int {
    /*
     * GUIA DE MANUTENÇÃO — count_recent_or_counter
     * Responsabilidade: Implementa a responsabilidade “count recent or counter” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `cached_val`, `max`, `error_log`, `->getMessage`.
     * Efeitos colaterais: gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        return (int) cached_val("counter_" . $counter, max(0, $ttl), $sql, $p);
    } catch (Throwable $e) {
        error_log(
            "[Prontoo realtime counter] " . $counter . " " . $e->getMessage(),
        );
        return 0;
    }
}
function meta_cache_ttl(string $key): int
{
    /*
     * GUIA DE MANUTENÇÃO — meta_cache_ttl
     * Responsabilidade: Gerencia o cache ou a memoização de “meta cache ttl”, reduzindo I/O sem substituir a fonte canônica.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `meta_get`.
     * Dependências chamadas: `str_starts_with`, `in_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($key === "auth_generation") {
        return 15;
    }
    if (str_starts_with($key, "global_admin_timezone_")) {
        return 300;
    }
    if (in_array($key, ["signup_clinics_blocked", "maintenance"], true)) {
        return 30;
    }
    return 60;
}
function meta_get(string $key, mixed $default = null): mixed
{
    /*
     * GUIA DE MANUTENÇÃO — meta_get
     * Responsabilidade: Localiza, carrega ou resolve os dados de “meta get” para consumo pelas camadas superiores.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_maintenance`, `login_last_credential_from_meta`, `default_monthly_price_cents`, `default_trial_days`, `subscription_pix_key`, `maintenance_active`, `page_maintenance_notice`, `clinic_signup_blocked` e mais 2.
     * Dependências chamadas: `val`, `error_log`, `->getMessage`, `function_exists`, `server_json_cache_remember`, `server_json_cache_safe_key`, `meta_cache_ttl`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $loader = function () use ($key, $default): mixed {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Support/Foundation.php:1002
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de serviços transversais de suporte.
         * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `val`, `error_log`, `->getMessage`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        try {
            $value = val("SELECT meta_value FROM pi_meta WHERE meta_key=?", [$key]);
            return $value === null ? $default : $value;
        } catch (Throwable $e) {
            error_log("[Prontoo meta_get] " . $e->getMessage());
            return $default;
        }
    };
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "meta",
            server_json_cache_safe_key("meta", [$key]),
            meta_cache_ttl($key),
            $loader,
            ["meta:" . $key],
        );
    }
    return $loader();
}
function meta_set(string $key, mixed $value): void
{
    /*
     * GUIA DE MANUTENÇÃO — meta_set
     * Responsabilidade: Implementa a responsabilidade “meta set” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_maintenance`, `page_admin_clinics`, `login_last_credential_remember`, `default_trial_days`, `seq_footer_alphabet`, `seq_footer_regenerate_alphabet`.
     * Dependências chamadas: `q`, `function_exists`, `str_starts_with`, `server_json_cache_clear_categories`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    q(
        "INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value), updated_at=NOW()",
        [$key, (string) $value],
    );
    if (function_exists("server_json_cache_clear_categories")) {
        $categories = ["meta"];
        if ($key === "auth_generation") {
            $categories[] = "context";
        }
        if (str_starts_with($key, "global_admin_timezone_") || $key === "maintenance") {
            $categories[] = "context";
            $categories[] = "clinic";
        }
        server_json_cache_clear_categories($categories);
    }
}
function clinic_signup_blocked(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_signup_blocked
     * Responsabilidade: Implementa a responsabilidade “clinic signup blocked” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_maintenance`, `page_login`, `page_signup`, `page`.
     * Dependências chamadas: `function_exists`, `has_cfg`, `meta_get`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!function_exists("has_cfg") || !has_cfg()) {
        return false;
    }
    return (string) meta_get("signup_clinics_blocked", "0") === "1";
}
function log_runtime_error(Throwable $e, int $status = 500): void
{
    /*
     * GUIA DE MANUTENÇÃO — log_runtime_error
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “log runtime error”.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `app_fail`.
     * Dependências chamadas: `function_exists`, `privacy_sanitize_error_message`, `mb_substr`, `->getMessage`, `privacy_log_file_label`, `->getFile`, `basename`, `storage_path`, `is_dir`, `mkdir`, `security_storage_deny_file`, `date` e mais 12.
     * Estado externo lido: `$_SERVER`, `$_SESSION`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; lê ou altera a sessão; consome dados da requisição HTTP; acessa o sistema de arquivos; lê, grava ou invalida cache; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: O cache é derivado: preserve TTL, chave por escopo e invalidação por tags; nunca o trate como fonte de verdade.
     */
    $message = function_exists("privacy_sanitize_error_message")
        ? privacy_sanitize_error_message($e, 900)
        : mb_substr($e->getMessage(), 0, 900);
    $file = function_exists("privacy_log_file_label")
        ? privacy_log_file_label($e->getFile())
        : basename($e->getFile());
    $dir = storage_path("logs");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($dir);
    }
    $line =
        "[" .
        date("c") .
        "] " .
        route() .
        " " .
        $status .
        " " .
        $message .
        " @ " .
        $file .
        ":" .
        $e->getLine() .
        "
";
    @file_put_contents($dir . "/runtime.log", $line, FILE_APPEND | LOCK_EX);
    @chmod($dir . "/runtime.log", 0640);
    if (!has_cfg() || db_temp_space_error($e)) {
        return;
    }
    try {
        $hash = hash(
            "sha256",
            route() .
                "|" .
                $status .
                "|" .
                $message .
                "|" .
                $file .
                "|" .
                $e->getLine(),
        );
        $throttle = cache_get("err_" . $hash, 60);
        if ($throttle) {
            return;
        }
        cache_set("err_" . $hash, 1);
        q(
            "INSERT INTO pi_error_events (route,method,http_status,message,file,line,user_id,clinic_id,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
            [
                route(),
                (string) ($_SERVER["REQUEST_METHOD"] ?? "GET"),
                $status,
                $message,
                mb_substr($file, 0, 255),
                $e->getLine(),
                $_SESSION["uid"] ?? null,
                session_clinic_scope_id() ?: null,
            ],
        );
    } catch (Throwable $ignored) {
        error_log(
            "[Prontoo error_event] " .
                (function_exists("privacy_sanitize_error_message")
                    ? privacy_sanitize_error_message($ignored, 240)
                    : $ignored->getMessage()),
        );
    }
}
function person_common_profile_schema_ready(): void
{
    /*
     * GUIA DE MANUTENÇÃO — person_common_profile_schema_ready
     * Responsabilidade: Opera a etapa “person common profile schema ready” do contrato de banco e instalação, restrita às janelas autorizadas.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `financial_creditor_upsert_from_post`, `page_creditors`, `person_common_profile_update`.
     * Dependências chamadas: `ensure_runtime_schema_minimum`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    ensure_runtime_schema_minimum();
}
function person_common_profile_from_array(
    array $data,
    string $prefix = "",
): array {
    /*
     * GUIA DE MANUTENÇÃO — person_common_profile_from_array
     * Responsabilidade: Implementa a responsabilidade “person common profile from array” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `financial_creditor_upsert_from_post`, `save_team_member`, `page_user`.
     * Dependências chamadas: `trim`, `only_digits`, `strlen`, `in_array`, `phone_br`, `strtoupper`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $k = function (string $name) use ($data, $prefix) {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Support/Foundation.php:1130
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de serviços transversais de suporte.
         * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return trim((string) ($data[$prefix . $name] ?? ""));
    };
    $doc = only_digits($k("legal_document"));
    $type = $k("legal_type");
    if ($type === "" && $doc !== "") {
        $type = strlen($doc) === 14 ? "cnpj" : "cpf";
    }
    if (!in_array($type, ["cpf", "cnpj"], true)) {
        $type = null;
    }
    $email = $k("email");
    $phone = phone_br($k("phone"));
    $uf = strtoupper($k("address_state"));
    $city = $k("address_city");
    $cityIbge = (int) ($data[$prefix . "address_city_ibge"] ?? 0);
    return [
        "legal_type" => $type,
        "legal_document" => $doc !== "" ? $doc : null,
        "phone" => $phone !== "" ? $phone : null,
        "email" => $email !== "" ? $email : null,
        "address_zip" => only_digits($k("address_zip")) ?: null,
        "address" => $k("address") ?: null,
        "address_number" => $k("address_number") ?: null,
        "address_neighborhood" => $k("address_neighborhood") ?: null,
        "address_complement" => $k("address_complement") ?: null,
        "address_state" => $uf ?: null,
        "address_city" => $city ?: null,
        "address_city_ibge" => $cityIbge > 0 ? $cityIbge : null,
    ];
}
function person_common_profile_update(int $personId, array $profile): void
{
    /*
     * GUIA DE MANUTENÇÃO — person_common_profile_update
     * Responsabilidade: Valida e executa a mutação “person common profile update”, preservando as invariantes do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `financial_creditor_upsert_from_post`, `save_team_member`, `page_user`.
     * Dependências chamadas: `person_common_profile_schema_ready`, `array_key_exists`, `filter_var`, `q`, `implode`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    if ($personId <= 0) {
        return;
    }
    person_common_profile_schema_ready();
    $allowed = [
        "legal_type",
        "legal_document",
        "phone",
        "email",
        "address_zip",
        "address",
        "address_number",
        "address_neighborhood",
        "address_complement",
        "address_state",
        "address_city",
        "address_city_ibge",
    ];
    $sets = [];
    $vals = [];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $profile)) {
            continue;
        }
        $val = $profile[$col];
        if (
            $col === "email" &&
            $val !== null &&
            $val !== "" &&
            !filter_var((string) $val, FILTER_VALIDATE_EMAIL)
        ) {
            $val = null;
        }
        $sets[] = $col . "=?";
        $vals[] = $val !== "" ? $val : null;
    }
    if (!$sets) {
        return;
    }
    $sets[] = "updated_at=NOW()";
    $vals[] = $personId;
    try {
        q(
            "UPDATE pi_persons SET " . implode(",", $sets) . " WHERE id=?",
            $vals,
        );
    } catch (Throwable $e) {
        error_log("[Prontoo pessoas perfil comum update] " . $e->getMessage());
    }
}
function person_common_profile_fields_html(
    int $cid,
    array $p = [],
    string $prefix = "",
    bool $includeEmail = true,
    bool $includePhone = true,
): string {
    /*
     * GUIA DE MANUTENÇÃO — person_common_profile_fields_html
     * Responsabilidade: Monta a representação de interface associada a “person common profile fields html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
     * Chamadores detectados: `page_creditors`, `page_users`, `page_user`.
     * Dependências chamadas: `e`, `function_exists`, `br_states`, `form_row`, `input`, `select_label`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $get = function (string $k, string $def = "") use ($p) {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Support/Foundation.php:1220
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de serviços transversais de suporte.
         * Local arquitetural: app/Support/Foundation.php (serviços transversais de suporte).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return (string) ($p[$k] ?? $def);
    };
    $uf = $get("address_state");
    $city = $get("address_city");
    $cityIbge = $get("address_city_ibge");
    $cityOptions =
        $city !== ""
            ? '<option value="' .
                e($city) .
                '" data-ibge="' .
                e($cityIbge) .
                '" selected>' .
                e($city) .
                "</option>"
            : '<option value="">Escolha primeiro o estado</option>';
    $states =
        ["" => "Escolha o estado"] +
        (function_exists("br_states") ? br_states() : []);
    $contact = "";
    $contactFields = "";
    if ($includePhone) {
        $contactFields .= form_row(
            "Telefone",
            input(
                $prefix . "phone",
                "text",
                $get("phone"),
                'autocomplete="tel" inputmode="tel" placeholder="(00) 00000-0000"',
            ),
        );
    }
    if ($includeEmail) {
        $contactFields .= form_row(
            "E-mail",
            input(
                $prefix . "email",
                "email",
                $get("email"),
                'autocomplete="email"',
            ),
        );
    }
    if ($contactFields !== "") {
        $contact = '<div class="two">' . $contactFields . "</div>";
    }
    return $contact .
        '<section class="patient-address-block person-common-address-block" data-patient-address-block>' .
        '<div class="two">' .
        form_row(
            "CEP",
            input(
                $prefix . "address_zip",
                "text",
                $get("address_zip"),
                'inputmode="numeric" maxlength="9" autocomplete="postal-code" data-patient-address-zip',
            ),
        ) .
        form_row(
            "Logradouro",
            input(
                $prefix . "address",
                "text",
                $get("address"),
                'maxlength="255" autocomplete="address-line1" data-patient-address-street',
            ),
        ) .
        '</div><div class="two">' .
        form_row(
            "Número",
            input(
                $prefix . "address_number",
                "text",
                $get("address_number"),
                'maxlength="20" autocomplete="address-line2" data-patient-address-number',
            ),
        ) .
        form_row(
            "Bairro",
            input(
                $prefix . "address_neighborhood",
                "text",
                $get("address_neighborhood"),
                'maxlength="120" data-patient-address-neighborhood',
            ),
        ) .
        '</div><div class="two">' .
        form_row(
            "Complemento",
            input(
                $prefix . "address_complement",
                "text",
                $get("address_complement"),
                'maxlength="120" autocomplete="address-line3" data-patient-address-complement',
            ),
        ) .
        select_label(
            "Estado",
            $prefix . "address_state",
            $states,
            $uf,
            "data-br-state data-patient-address-state",
        ) .
        "</div>" .
        form_row(
            "Cidade",
            '<select name="' .
                e($prefix . "address_city") .
                '" data-br-city data-selected-city="' .
                e($city) .
                '" data-patient-address-city>' .
                $cityOptions .
                '</select><input type="hidden" name="' .
                e($prefix . "address_city_ibge") .
                '" value="' .
                e($cityIbge) .
                '" data-br-city-ibge data-patient-address-ibge>',
        ) .
        "</section>";
}
