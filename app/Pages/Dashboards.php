<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function page_home(): void
{
    \Prontoo\Runtime\Legacy\Dashboards\DashboardsRuntimeOperations01::page_home();
}
function page_medico_painel(array $c): void
{
    \Prontoo\Runtime\Legacy\Dashboards\DashboardsRuntimeOperations01::page_medico_painel($c);
}
function page_recepcao_painel(array $c): void
{
    \Prontoo\Runtime\Legacy\Dashboards\DashboardsRuntimeOperations01::page_recepcao_painel($c);
}
function page_triagem_painel(array $c): void
{
    \Prontoo\Runtime\Legacy\Dashboards\DashboardsRuntimeOperations02::page_triagem_painel($c);
}
function manager_metric_val(string $sql, array $params = []): int
{
    return \Prontoo\Runtime\Legacy\Dashboards\DashboardsRuntimeOperations02::manager_metric_val($sql, $params);
}
function manager_metric_row(string $sql, array $params = []): array
{
    return \Prontoo\Runtime\Legacy\Dashboards\DashboardsRuntimeOperations02::manager_metric_row($sql, $params);
}
function manager_count_business_days(string $from, string $to): int
{
    return \Prontoo\Presentation\Legacy\Dashboards\DashboardsPresentationOperations01::manager_count_business_days($from, $to);
}
function manager_percent_label(float $v): string
{
    return \Prontoo\Presentation\Legacy\Dashboards\DashboardsPresentationOperations01::manager_percent_label($v);
}
function manager_dashboard_card(
    string $label,
    string $value,
    string $iconName,
    string $note = "",
    string $class = "",
): string {
    return \Prontoo\Presentation\Legacy\Dashboards\DashboardsPresentationOperations01::manager_dashboard_card($label, $value, $iconName, $note, $class);
}
function manager_action_card(
    string $iconName,
    string $title,
    string $body,
    string $route,
    string $label = "Abrir",
): string {
    return \Prontoo\Runtime\Legacy\Dashboards\DashboardsRuntimeOperations02::manager_action_card($iconName, $title, $body, $route, $label);
}
function page_gerente_painel(array $c): void
{
    \Prontoo\Runtime\Legacy\Dashboards\DashboardsRuntimeOperations03::page_gerente_painel($c);
}
function page_painel(): void
{
    \Prontoo\Runtime\Legacy\Dashboards\DashboardsRuntimeOperations03::page_painel();
}
