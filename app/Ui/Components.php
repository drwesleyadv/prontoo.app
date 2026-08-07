<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function e(mixed $v): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::e($v);
}
function first_name(?string $name): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::first_name($name);
}
function icon(string $name): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::icon($name);
}
function pix_symbol(): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::pix_symbol();
}
function reception_cash_state_icon(?array $context = null): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::reception_cash_state_icon($context);
}
function n(mixed $value): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::n($value);
}
function money_br(int|float|string|null $cents): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::money_br($cents);
}
function prontoo_months_br(): array
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::prontoo_months_br();
}
function date_br(null|string|int $value): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::date_br($value);
}
function date_extenso_br(null|string|int $value): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::date_extenso_br($value);
}
function dt_br(null|string|int $value): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::dt_br($value);
}
function dt_notice_br(null|string|int $value): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($value);
}
function dt_card_full_br(null|string|int $value): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::dt_card_full_br($value);
}
function notification_button_light(array $c): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::notification_button_light($c);
}
function shared_goal_cmdbar_html(?array $c = null): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::shared_goal_cmdbar_html($c);
}
function city_state_label(?string $city, ?string $uf): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::city_state_label($city, $uf);
}
function cmdbar_access_schema_ready(): bool
{
    return \Prontoo\Infrastructure\Legacy\UiComponents\UiComponentsInfrastructureOperations01::cmdbar_access_schema_ready();
}

function cmdbar_context_key(array $c): array
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::cmdbar_context_key($c);
}
function cmdbar_parent_key(
    string $route,
    array $actions,
    bool $global = false,
): string {
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::cmdbar_parent_key($route, $actions, $global);
}
function cmdbar_access_touch(array $c, string $actionKey): void
{
    \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::cmdbar_access_touch($c, $actionKey);
}
function cmdbar_access_recency(array $c, array $keys): array
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::cmdbar_access_recency($c, $keys);
}
function cmdbar_order_items(
    array $items,
    string $current,
    array $c,
    bool $global = false,
): array {
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::cmdbar_order_items($items, $current, $c, $global);
}
function cmdbar_label_html(
    string $label,
    bool $active,
    string $extra = "",
): string {
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::cmdbar_label_html($label, $active, $extra);
}
function floating_pending_task_access_sql(array $c, string $alias = "t"): array
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::floating_pending_task_access_sql($c, $alias);
}
function floating_pending_count(string $sql, array $params = []): int
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::floating_pending_count($sql, $params);
}
function floating_pending_cards_html(array $c, string $current): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations01::floating_pending_cards_html($c, $current);
}
function page(string $title, string $body, array $opts = []): void
{
    \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations02::page($title, $body, $opts);
}
function context_parent_for_route(string $route, array $c): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::context_parent_for_route($route, $c);
}
function operation_current_match(
    string $route,
    array $params,
    string $current,
): bool {
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::operation_current_match($route, $params, $current);
}
function operation_link_html(
    string $route,
    string $label,
    string $iconName,
    string $current,
    array $params = [],
): string {
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations02::operation_link_html($route, $label, $iconName, $current, $params);
}
function page_operation_specs(string $current, array $c): array
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations03::page_operation_specs($current, $c);
}
function operation_menu_html(
    string $label,
    string $iconName,
    array $items,
    string $current,
): string {
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations03::operation_menu_html($label, $iconName, $items, $current);
}
function page_operations_html(string $current, array $c): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations03::page_operations_html($current, $c);
}
function page_head_icon_name(string $title = ""): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations03::page_head_icon_name($title);
}
function page_head(string $title, string $sub = "", string $action = ""): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations03::page_head($title, $sub, $action);
}
function action_icon_for(string $label): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations03::action_icon_for($label);
}
function action_summary_label(string $label, string $iconName = ""): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations03::action_summary_label($label, $iconName);
}
function card(string $html, string $class = ""): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::card($html, $class);
}
function ds_class(string ...$classes): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::ds_class(...$classes);
}
function ds_card_class(string $additionalClass = ""): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::ds_card_class($additionalClass);
}
function ds_search_card_class(string $additionalClass = ""): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::ds_search_card_class($additionalClass);
}
function ds_filter_list_class(string $additionalClass = ""): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::ds_filter_list_class($additionalClass);
}
function ds_filter_chip_class(
    string $additionalClass = "",
    bool $active = false,
): string {
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::ds_filter_chip_class($additionalClass, $active);
}
function stat_card(
    string $label,
    mixed $value,
    string $iconName,
    string $note = "",
): string {
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::stat_card($label, $value, $iconName, $note);
}
function form_row(string $label, string $input): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::form_row($label, $input);
}
function input(
    string $name,
    string $type = "text",
    mixed $value = "",
    string $extra = "",
): string {
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::input($name, $type, $value, $extra);
}
function textarea(string $name, mixed $value = "", string $extra = ""): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::textarea($name, $value, $extra);
}
function select_html(
    string $name,
    array $options,
    mixed $selected = null,
    string $extra = "",
): string {
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::select_html($name, $options, $selected, $extra);
}
function select_label(
    string $label,
    string $name,
    array $opts,
    mixed $sel = null,
    string $extra = "",
): string {
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::select_label($label, $name, $opts, $sel, $extra);
}
function form_submit_icon(string $label): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations04::form_submit_icon($label);
}
function cancel_button(string $label = "Cancelar"): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::cancel_button($label);
}
function form_actions(string $submitLabel, string $class = "primary"): string
{
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations04::form_actions($submitLabel, $class);
}
function action_panel(
    string $label,
    string $formHtml,
    string $variant = "primary",
    string $hint = "",
): string {
    return \Prontoo\Runtime\Legacy\UiComponents\UiComponentsRuntimeOperations04::action_panel($label, $formHtml, $variant, $hint);
}
function timeline(?array $items, string $empty = "Nada por enquanto."): string
{
    return \Prontoo\Presentation\Legacy\UiComponents\UiComponentsPresentationOperations01::timeline($items, $empty);
}
