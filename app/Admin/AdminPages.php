<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function platform_storage_status(): array
{
    return \Prontoo\Infrastructure\AdminPages\AdminPagesInfrastructureOperations01::platform_storage_status();
}
function admin_scope_guard_definition(string $key): array
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_scope_guard_definition($key);
}
function admin_scope_guard_stats(int $hours = 24): array
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_guard_stats($hours);
}
function admin_scope_guard_groups(
    int $hours = 24,
    int $limit = 30,
    bool $includePolicy = false,
): array {
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_guard_groups($hours, $limit, $includePolicy);
}
function admin_scope_evidence_html(array $row, bool $compact = false): string
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_evidence_html($row, $compact);
}
function admin_scope_guard_timeline_item(array $row): array
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_scope_guard_timeline_item($row);
}
function platform_backend_selftest(array $preloaded = []): array
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::platform_backend_selftest($preloaded);
}
function platform_autotest_actions(array $checks): array
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::platform_autotest_actions($checks);
}
function platform_login_loaded_audit(
    array $checks,
    bool $autoLogin = false,
): void {
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::platform_login_loaded_audit($checks, $autoLogin);
}
function admin_nav_parent(string $route): string
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_nav_parent($route);
}
function stat_link_card(
    string $label,
    mixed $value,
    string $iconName,
    string $note = "",
    string $route = "",
): string {
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::stat_link_card($label, $value, $iconName, $note, $route);
}
if (!function_exists("admin_choice_card")) {
    function admin_choice_card(): string
    {
        return \Prontoo\Presentation\AdminPages\AdminChoiceCardOperation::render();
    }
}
function admin_global_timezone_options(): array
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_global_timezone_options();
}
function admin_quick_links(): string
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_quick_links();
}
function human_bytes(float $bytes): string
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::human_bytes($bytes);
}
function bool_status(
    bool $ok,
    string $okTxt = "OK",
    string $badTxt = "Atenção",
): string {
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::bool_status($ok, $okTxt, $badTxt);
}
function admin_global_compact_pill(
    string $label,
    string $value,
    string $iconName,
    string $note = "",
    string $route = "",
): string {
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::admin_global_compact_pill($label, $value, $iconName, $note, $route);
}
function admin_global_ops_finance_html(): string
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_ops_finance_html();
}
function admin_global_metric_series_24h(string $metric): array
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_metric_series_24h($metric);
}
function admin_metric_duration_label(
    float $milliseconds,
    bool $compact = false,
): string {
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_duration_label($milliseconds, $compact);
}
function admin_metric_value_label(float $value, string $mode): string
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_label($value, $mode);
}
function admin_metric_value_compact(float $value, string $mode): string
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_compact($value, $mode);
}
function admin_metric_recent_average(array $series, int $minutes = 5): float
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_recent_average($series, $minutes);
}
function admin_metric_line_chart(
    string $title,
    string $description,
    array $series,
    string $iconName,
    string $mode = "count",
): string {
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations02::admin_metric_line_chart($title, $description, $series, $iconName, $mode);
}
function admin_metric_dual_area_chart(
    string $title,
    array $loadSeries,
    array $responseSeries,
    string $iconName = "speed",
    array $presentation = [],
): string {
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_metric_dual_area_chart($title, $loadSeries, $responseSeries, $iconName, $presentation);
}
function admin_global_sequence_series_20d(): array
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_sequence_series_20d();
}
function admin_maestro_health_time_label(?string $value): string
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_maestro_health_time_label($value);
}
function admin_maestro_health_pill_html(bool $allowSchemaEnsure = true): string
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_maestro_health_pill_html($allowSchemaEnsure);
}
function admin_global_perf_charts_html(): string
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_perf_charts_html();
}
function admin_performance_card_content_html(bool $public = false): string
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_performance_card_content_html($public);
}
function admin_performance_card_html(bool $public = false): string
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_performance_card_html($public);
}
function admin_telemetry_variation_note(?float $variation): string
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_telemetry_variation_note($variation);
}
function admin_telemetry_kpi_cards_html(bool $linked = false): string
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::admin_telemetry_kpi_cards_html($linked);
}
function page_status(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::page_status();
}
function page_admin_operations(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::page_admin_operations();
}
function page_admin_deleted(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::page_admin_deleted();
}
function page_admin_health(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::page_admin_health();
}
function page_admin_diagnostics(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::page_admin_diagnostics();
}
function page_admin_errors(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::page_admin_errors();
}
function onboarding_score(array $r): array
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::onboarding_score($r);
}
function page_admin_onboarding(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::page_admin_onboarding();
}
function page_admin_integrity(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::page_admin_integrity();
}
function page_admin_maintenance(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations05::page_admin_maintenance();
}
function page_admin_settings(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations05::page_admin_settings();
}
function page_admin_painel(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::page_admin_painel();
}
function page_admin_people(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::page_admin_people();
}
function page_admin_users(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::page_admin_users();
}
function page_admin_stats(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::page_admin_stats();
}
function onboarding_use_icon(bool $ok, string $label): string
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::onboarding_use_icon($ok, $label);
}
function onboarding_progress_bar(array $used): string
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::onboarding_progress_bar($used);
}
function admin_clinic_detail_item(
    string $iconName,
    string $label,
    string $value,
    string $note = "",
): string {
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item($iconName, $label, $value, $note);
}
function admin_clinic_detail_page(int $id): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations07::admin_clinic_detail_page($id);
}
function admin_clinic_people_counts_by_cpf(array $clinicIds): array
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations07::admin_clinic_people_counts_by_cpf($clinicIds);
}
function page_admin_clinics(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations08::page_admin_clinics();
}
function page_admin_security(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations08::page_admin_security();
}
function page_admin_audit(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations08::page_admin_audit();
}
function admin_alerts_ensure_schema(): void
{
    \Prontoo\Infrastructure\AdminPages\AdminPagesInfrastructureOperations01::admin_alerts_ensure_schema();
}

function admin_alerts_admin_users(): array
{
    return \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations09::admin_alerts_admin_users();
}
function admin_alert_contact_label(
    ?array $user,
    int $currentUserId = 0,
    bool $asSupport = false,
): string {
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_alert_contact_label($user, $currentUserId, $asSupport);
}
function page_admin_alerts(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations09::page_admin_alerts();
}

function admin_performance_format_ms(float $ms): string
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_performance_format_ms($ms);
}
function admin_performance_rows_html(array $rows): string
{
    return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_performance_rows_html($rows);
}
function page_admin_performance(): void
{
    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations09::page_admin_performance();
}
