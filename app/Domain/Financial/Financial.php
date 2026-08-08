<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
const PRONTOO_FINANCIAL_MAX_CENTS = 2147483647;
function parse_money_cents(string $v): int
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::parse_money_cents($v);
}
function financial_assert_amount_cents(
    int $value,
    string $label = "Valor",
): int {
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_amount_cents($value, $label);
}
function financial_assert_balance_cents(
    int $value,
    string $label = "Saldo",
): int {
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_balance_cents($value, $label);
}
function financial_checked_add(
    int $left,
    int $right,
    string $label = "Saldo",
): int {
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add($left, $right, $label);
}
function financial_movement_delta_for_location(
    int $amount,
    ?int $from,
    ?int $to,
    int $locationId,
): int {
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_delta_for_location($amount, $from, $to, $locationId);
}
function financial_validate_movement_topology(
    string $type,
    ?int $from,
    ?int $to,
): void {
    \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_validate_movement_topology($type, $from, $to);
}
function financial_closing_equation(
    int $expected,
    int $declared,
    int $kept,
    int $transferred,
    int $difference,
): bool {
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_closing_equation($expected, $declared, $kept, $transferred, $difference);
}
function payment_methods_options(): array
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::payment_methods_options();
}
function payment_method_type_options(): array
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::payment_method_type_options();
}
function normalize_payment_method(string $v): string
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::normalize_payment_method($v);
}
function financial_goal_base_options(): array
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_goal_base_options();
}
function financial_goal_base_label(string $base): string
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_goal_base_label($base);
}
function financial_expense_category_options(): array
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_expense_category_options();
}
function financial_account_type_options(): array
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_account_type_options();
}
function financial_seed_payment_methods(int $cid, int $uid = 0): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::financial_seed_payment_methods($cid, $uid);
}
function financial_payment_method_options(
    int $cid,
    bool $withEmpty = true,
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::financial_payment_method_options($cid, $withEmpty);
}
function financial_payment_method_from_post(int $cid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::financial_payment_method_from_post($cid);
}
function appointment_payment_destination_options(
    int $cid,
    bool $withEmpty = true,
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::appointment_payment_destination_options($cid, $withEmpty);
}
function appointment_payment_existing_destination(int $cid, array $appt): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::appointment_payment_existing_destination($cid, $appt);
}
function appointment_payment_form_html(int $cid, array $appt = []): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::appointment_payment_form_html($cid, $appt);
}
function financial_sync_appointment(
    int $cid,
    int $appointmentId,
    int $userId,
    int $paymentDestinationLocationId = 0,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::financial_sync_appointment($cid, $appointmentId, $userId, $paymentDestinationLocationId);
}
function monthly_goal_status(int $cid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::monthly_goal_status($cid);
}
function monthly_goal_card(array $c, string $variant = "compact"): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::monthly_goal_card($c, $variant);
}
function page_goal_status(): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::page_goal_status();
}
function page_operations(): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::page_operations();
}
function counterparty_autosuggest_datalist(
    int $cid,
    string $id = "prontoo_counterparty_suggestions",
): string {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::counterparty_autosuggest_datalist($cid, $id);
}
function counterparty_lookup_field(
    int $cid,
    string $hiddenName = "counterparty_id",
    string $hiddenValue = "",
    string $inputName = "counterparty_search",
): string {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::counterparty_lookup_field($cid, $hiddenName, $hiddenValue, $inputName);
}
function posted_counterparty_search_value(): string
{
    return \Prontoo\Presentation\Financial\FinancialPresentationOperations01::posted_counterparty_search_value();
}
function resolve_counterparty_lookup_id(
    int $cid,
    int $postedId,
    string $search = "",
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::resolve_counterparty_lookup_id($cid, $postedId, $search);
}
function page_counterparty_lookup(): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::page_counterparty_lookup();
}
function page_counterparty_suggest(): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::page_counterparty_suggest();
}
function counterparty_options(int $cid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::counterparty_options($cid);
}
function financial_account_options(int $cid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_account_options($cid);
}
function financial_date_or_null(string $date, bool $end = false): ?string
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_date_or_null($date, $end);
}
function financial_account_label_options(
    int $cid,
    string $empty = "Escolha a conta",
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_account_label_options($cid, $empty);
}
function financial_ensure_default_accounts(int $cid, int $uid): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_ensure_default_accounts($cid, $uid);
}
function financial_account_icon(array $account): string
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_account_icon($account);
}
function financial_account_belongs(int $cid, int $accountId): bool
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_account_belongs($cid, $accountId);
}
function financial_counterparty_light(
    int $cid,
    int $uid,
    string $name,
    string $doc = "",
    string $notes = "",
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_counterparty_light($cid, $uid, $name, $doc, $notes);
}
function financial_counterparty_from_post(int $cid, int $uid): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_counterparty_from_post($cid, $uid);
}
function financial_account_balances(int $cid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_account_balances($cid);
}
function financial_dashboard_numbers(int $cid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_dashboard_numbers($cid);
}
function financial_recent_operations_timeline(int $cid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_recent_operations_timeline($cid);
}
function financial_status_pill(
    string $status,
    ?string $date = null,
    string $type = "revenue",
): string {
    return \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_status_pill($status, $date, $type);
}
function financial_report_line(string $label, int $value): string
{
    return \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_report_line($label, $value);
}
function financial_location_type_options(): array
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_location_type_options();
}
function financial_location_type_label(string $type): string
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_location_type_label($type);
}
function financial_money_input(
    string $name,
    string $value = "",
    string $extra = "",
): string {
    return \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input($name, $value, $extra);
}
function financial_cashier_roles(): array
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_cashier_roles();
}
function financial_is_cashier(array $c): bool
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_is_cashier($c);
}
function financial_today(int $cid = 0): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
}
function financial_human_session_status(string $status): string
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_session_status($status);
}
function financial_human_movement_type(string $type): string
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_movement_type($type);
}
function financial_human_movement_status(string $status): string
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_human_movement_status($status);
}
function financial_movement_icon(string $type): string
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_icon($type);
}
function financial_operational_schema_ready(): void
{
    \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
}
function financial_ensure_admin_safe(int $cid, int $uid = 0): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_admin_safe($cid, $uid);
}
function financial_ensure_cashier_location(int $cid, int $uid): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_cashier_location($cid, $uid);
}
function financial_ensure_bank_location(
    int $cid,
    int $accountId,
    int $uid = 0,
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_bank_location($cid, $accountId, $uid);
}
function financial_cashier_user_options(int $cid, bool $withEmpty = true): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_user_options($cid, $withEmpty);
}
function financial_drawer_location_options(
    int $cid,
    bool $withEmpty = true,
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_location_options($cid, $withEmpty);
}
function financial_cashier_assigned_locations(int $cid, int $uid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_assigned_locations($cid, $uid);
}
function financial_cashier_location_for_user(int $cid, int $uid): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_location_for_user($cid, $uid);
}
function financial_cashier_drawer_name(int $cid, int $uid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_drawer_name($cid, $uid);
}
function financial_create_drawer(int $cid, int $uid, string $name): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_create_drawer($cid, $uid, $name);
}
function financial_rename_drawer(
    int $cid,
    int $drawerId,
    int $adminUid,
    string $name,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_rename_drawer($cid, $drawerId, $adminUid, $name);
}
function financial_link_drawer_user(
    int $cid,
    int $drawerId,
    int $cashierUid,
    int $adminUid,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_link_drawer_user($cid, $drawerId, $cashierUid, $adminUid);
}
function financial_unlink_drawer_user(
    int $cid,
    int $linkId,
    int $adminUid,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_unlink_drawer_user($cid, $linkId, $adminUid);
}
function financial_deactivate_drawer(
    int $cid,
    int $drawerId,
    int $adminUid,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_deactivate_drawer($cid, $drawerId, $adminUid);
}
function financial_drawer_row(int $cid, int $drawerId): ?array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_row($cid, $drawerId);
}
function financial_drawer_auto_unlock_if_due(int $cid, int $drawerId): ?array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_auto_unlock_if_due($cid, $drawerId);
}
function financial_drawer_auto_unlock_row_if_due(int $cid, array $d): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_auto_unlock_row_if_due($cid, $d);
}
function financial_drawer_lock_label(array $drawer, int $cid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_lock_label($drawer, $cid);
}
function financial_drawer_guard_can_use(int $cid, int $drawerId): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_guard_can_use($cid, $drawerId);
}
function financial_notify_drawer_locked(
    int $cid,
    int $drawerId,
    int $sessionId,
    int $uid,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_notify_drawer_locked($cid, $drawerId, $sessionId, $uid);
}
function financial_drawer_lock_after_close(
    int $cid,
    int $drawerId,
    string $businessDate,
    int $sessionId,
    int $uid,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_lock_after_close($cid, $drawerId, $businessDate, $sessionId, $uid);
}
function financial_default_drawer_unlock_local(
    int $cid,
    int $drawerId,
    ?array $drawer = null,
): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_default_drawer_unlock_local($cid, $drawerId, $drawer);
}
function financial_schedule_drawer_unlock(
    int $cid,
    int $drawerId,
    int $adminUid,
    string $unlockLocal,
    string $notes = "",
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_schedule_drawer_unlock($cid, $drawerId, $adminUid, $unlockLocal, $notes);
}
function financial_drawer_open_session(
    int $cid,
    int $locationId,
    int $excludeUid = 0,
): ?array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_open_session($cid, $locationId, $excludeUid);
}
function financial_latest_drawer_session(
    int $cid,
    int $locationId,
    string $maxDate = "",
    int $ignoreSessionId = 0,
): ?array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_latest_drawer_session($cid, $locationId, $maxDate, $ignoreSessionId);
}
function financial_drawer_previous_balance(
    int $cid,
    int $locationId,
    string $businessDate = "",
    int $ignoreSessionId = 0,
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_previous_balance($cid, $locationId, $businessDate, $ignoreSessionId);
}
function financial_drawer_pending_previous_review(
    int $cid,
    int $locationId,
    string $today = "",
): ?array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_pending_previous_review($cid, $locationId, $today);
}
function financial_drawer_balance(int $cid, int $locationId): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_balance($cid, $locationId);
}
function financial_drawer_balance_snapshot(
    int $cid,
    array $locationIds,
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_balance_snapshot($cid, $locationIds);
}
function financial_drawer_daily_totals(
    int $cid,
    int $locationId,
    string $date,
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_daily_totals($cid, $locationId, $date);
}
function financial_drawer_daily_totals_empty(): array
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_drawer_daily_totals_empty();
}
function financial_drawer_daily_totals_map(
    int $cid,
    array $locationIds,
    array $dates,
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_daily_totals_map($cid, $locationIds, $dates);
}
function financial_location_belongs(int $cid, int $locationId): bool
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_location_belongs($cid, $locationId);
}
function financial_admin_location_belongs(int $cid, int $locationId): bool
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_admin_location_belongs($cid, $locationId);
}
function financial_admin_location_select_options(int $cid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_admin_location_select_options($cid);
}
function financial_daily_closing_ensure_schema(): void
{
    \Prontoo\Infrastructure\Financial\FinancialInfrastructureOperations01::financial_daily_closing_ensure_schema();
}

function financial_day_is_consolidated(
    int $cid,
    string $businessDate = "",
): bool {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_day_is_consolidated($cid, $businessDate);
}
function financial_daily_metrics(int $cid, string $businessDate): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_daily_metrics($cid, $businessDate);
}
function financial_daily_reconciliation(
    int $cid,
    string $businessDate,
    int $uid = 0,
    bool $prepareLegacy = false,
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_daily_reconciliation($cid, $businessDate, $uid, $prepareLegacy);
}
function financial_daily_consolidation_state(
    int $cid,
    string $businessDate = "",
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_daily_consolidation_state($cid, $businessDate);
}
function financial_daily_consolidation_blockers_html(array $state): string
{
    return \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_daily_consolidation_blockers_html($state);
}
function financial_patient_pending_revenue_count(int $cid, int $patientId): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_patient_pending_revenue_count($cid, $patientId);
}
function financial_pending_revenue_belongs_to_patient(
    int $cid,
    int $revenueId,
    int $patientId,
): bool {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_pending_revenue_belongs_to_patient($cid, $revenueId, $patientId);
}
function financial_cancel_appointment_revenue(
    int $cid,
    int $appointmentId,
    int $uid,
    string $reason = "",
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_cancel_appointment_revenue($cid, $appointmentId, $uid, $reason);
}
function financial_admin_receive_expected_revenue(
    int $cid,
    int $uid,
    int $revenueId,
    string $method,
    int $destinationLocationId,
    string $notes = "",
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_admin_receive_expected_revenue($cid, $uid, $revenueId, $method, $destinationLocationId, $notes);
}
function financial_session_position_cents(
    array $session,
    int $excludeMovementId = 0,
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_session_position_cents($session, $excludeMovementId);
}
function financial_location_potential_balance(
    int $cid,
    int $locationId,
    int $excludeMovementId = 0,
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_location_potential_balance($cid, $locationId, $excludeMovementId);
}
function financial_validate_movement_invariants(
    int $cid,
    string $type,
    int $amount,
    ?int $from,
    ?int $to,
    ?int $sessionId,
    string $status,
    int $excludeMovementId = 0,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_validate_movement_invariants($cid, $type, $amount, $from, $to, $sessionId, $status, $excludeMovementId);
}
function financial_update_existing_movement(
    int $cid,
    int $movementId,
    string $type,
    int $amount,
    ?int $from,
    ?int $to,
    ?int $sessionId,
    int $uid,
    string $title,
    string $paymentMethod,
    string $notes,
    string $status,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_update_existing_movement($cid, $movementId, $type, $amount, $from, $to, $sessionId, $uid, $title, $paymentMethod, $notes, $status);
}
function financial_create_movement(
    int $cid,
    string $type,
    int $amount,
    ?int $from,
    ?int $to,
    ?int $sessionId,
    int $uid,
    string $title,
    string $paymentMethod = "",
    string $notes = "",
    string $status = "confirmed",
    string $sourceEntity = "",
    int $sourceId = 0,
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_create_movement($cid, $type, $amount, $from, $to, $sessionId, $uid, $title, $paymentMethod, $notes, $status, $sourceEntity, $sourceId);
}
function financial_session_for_date(int $cid, int $uid, string $date): ?array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_session_for_date($cid, $uid, $date);
}
function financial_latest_session(int $cid, int $uid): ?array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_latest_session($cid, $uid);
}
function financial_previous_drawer_balance(
    int $cid,
    int $uid,
    string $beforeDate = "",
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_previous_drawer_balance($cid, $uid, $beforeDate);
}
function financial_expected_opening_balance(
    int $cid,
    int $uid,
    string $businessDate = "",
    int $locationId = 0,
    int $ignoreSessionId = 0,
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_expected_opening_balance($cid, $uid, $businessDate, $locationId, $ignoreSessionId);
}
function financial_cashier_name(int $uid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_cashier_name($uid);
}
function financial_notify_opening_authorization_request(
    int $cid,
    int $cashierUid,
    int $sessionId,
    int $expected,
    int $informed,
    int $createdBy,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations06::financial_notify_opening_authorization_request($cid, $cashierUid, $sessionId, $expected, $informed, $createdBy);
}
function financial_request_opening_authorization(
    int $cid,
    int $uid,
    int $loc,
    string $today,
    int $expected,
    int $informed,
    ?array $existing = null,
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_request_opening_authorization($cid, $uid, $loc, $today, $expected, $informed, $existing);
}
function financial_unclosed_previous_session(
    int $cid,
    int $uid,
    string $today = "",
): ?array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_unclosed_previous_session($cid, $uid, $today);
}
function financial_keep_closed(int $cid, int $uid): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_keep_closed($cid, $uid);
}
function financial_open_session(int $cid, int $uid, int $openingBalance): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_open_session($cid, $uid, $openingBalance);
}
function financial_session_movement_totals(int $cid, int $sessionId): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_session_movement_totals($cid, $sessionId);
}
function financial_session_expected(array $session): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_session_expected($session);
}
function financial_record_cash_difference(
    int $cid,
    int $uid,
    int $sessionId,
    int $locationId,
    int $difference,
    string $phase,
    string $status,
    string $notes = "",
    bool $linkSession = true,
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_record_cash_difference($cid, $uid, $sessionId, $locationId, $difference, $phase, $status, $notes, $linkSession);
}
function financial_ensure_closing_adjustment(
    array $session,
    int $uid,
    string $status,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_ensure_closing_adjustment($session, $uid, $status);
}
function financial_current_open_session(int $cid, int $uid): ?array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_current_open_session($cid, $uid);
}
function financial_require_open_session(int $cid, int $uid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_require_open_session($cid, $uid);
}
function financial_close_session(
    int $cid,
    int $uid,
    int $sessionId,
    int $declared,
    int $withdrawalAmount,
    int $withdrawalDestinationId = 0,
    string $notes = "",
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations08::financial_close_session($cid, $uid, $sessionId, $declared, $withdrawalAmount, $withdrawalDestinationId, $notes);
}
function financial_review_opening_request(
    int $cid,
    int $adminUid,
    int $sessionId,
    string $decision,
    string $notes = "",
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations08::financial_review_opening_request($cid, $adminUid, $sessionId, $decision, $notes);
}
function financial_review_session(
    int $cid,
    int $uid,
    int $sessionId,
    string $decision,
    string $notes = "",
    string $drawerUnlockLocal = "",
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations08::financial_review_session($cid, $uid, $sessionId, $decision, $notes, $drawerUnlockLocal);
}
function financial_assert_session_reconciled(array $session): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations08::financial_assert_session_reconciled($session);
}
function financial_location_movement_balance(int $cid, int $locationId): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations08::financial_location_movement_balance($cid, $locationId);
}
function financial_location_movement_balances(
    int $cid,
    array $locationIds,
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_location_movement_balances($cid, $locationIds);
}
function financial_pos_balance_for_user(int $cid, int $uid): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_pos_balance_for_user($cid, $uid);
}
function financial_global_position(int $cid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_global_position($cid);
}
function financial_cashier_requires_attention(array $c): bool
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_cashier_requires_attention($c);
}
function financial_register_appointment_payment_movement(
    int $cid,
    int $appointmentId,
    int $userId,
    int $amount,
    string $method,
    string $title,
    bool $paid,
    int $paymentDestinationLocationId = 0,
): void {
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_register_appointment_payment_movement($cid, $appointmentId, $userId, $amount, $method, $title, $paid, $paymentDestinationLocationId);
}
function financial_revenue_id_for_appointment(
    int $cid,
    int $appointmentId,
    int $uid = 0,
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_revenue_id_for_appointment($cid, $appointmentId, $uid);
}
function financial_appointment_payment_state(array $a): array
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_appointment_payment_state($a);
}
function financial_appointment_operational_chip_html(
    int $cid,
    array $a,
    string $role = "",
): string {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_appointment_operational_chip_html($cid, $a, $role);
}
function financial_daily_drawer_closure_state(
    int $cid,
    string $businessDate = "",
): array {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_daily_drawer_closure_state($cid, $businessDate);
}
function financial_daily_drawer_closure_blocking_html(array $state): string
{
    return \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_daily_drawer_closure_blocking_html($state);
}
function financial_admin_daily_consolidation_html(int $cid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_admin_daily_consolidation_html($cid);
}
function financial_cashier_pending_receipts_html(int $cid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_cashier_pending_receipts_html($cid);
}
function financial_location_select_options(int $cid, string $type = ""): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_location_select_options($cid, $type);
}
function financial_cashier_receipt_method_options(): array
{
    return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_cashier_receipt_method_options();
}
function financial_office_destination_options(int $cid, int $uid = 0): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_office_destination_options($cid, $uid);
}
function financial_office_destination_belongs(int $cid, int $locationId): bool
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_office_destination_belongs($cid, $locationId);
}
function financial_expected_appointment_revenue_options(int $cid): array
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_expected_appointment_revenue_options($cid);
}
function financial_receive_expected_appointment_revenue(
    int $cid,
    int $uid,
    int $revenueId,
    string $method,
    int $destinationLocationId = 0,
    string $notes = "",
): int {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_receive_expected_appointment_revenue($cid, $uid, $revenueId, $method, $destinationLocationId, $notes);
}
function financial_tabs_html(array $tabs, string $active): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_tabs_html($tabs, $active);
}
function financial_cash_exception_debug(
    Throwable $e,
    array $c,
    string $act = "",
): string {
    return \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_cash_exception_debug($e, $c, $act);
}
function financial_cash_debug_details_enabled(): bool
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cash_debug_details_enabled();
}
function financial_cash_store_debug(
    Throwable $e,
    array $c,
    string $act = "",
): void {
    \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_cash_store_debug($e, $c, $act);
}
function financial_cash_debug_error_html(): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cash_debug_error_html();
}
function financial_cash_debug_request_active(): bool
{
    return \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_cash_debug_request_active();
}
function financial_cashier_pagehead_link(
    string $op,
    string $label,
    string $iconName,
    bool $enabled,
    string $active,
): string {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cashier_pagehead_link($op, $label, $iconName, $enabled, $active);
}
function financial_cashier_pagehead_actions(
    bool $canOpen,
    bool $canPayReceive,
    bool $canClose,
    string $active = "",
): string {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cashier_pagehead_actions($canOpen, $canPayReceive, $canClose, $active);
}
function financial_cashier_drawer_summary(
    ?array $session = null,
    ?array $prev = null,
    int $fallbackOpening = 0,
    ?array $drawer = null,
): string {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cashier_drawer_summary($session, $prev, $fallbackOpening, $drawer);
}
function financial_cash_debug_failure_page(Throwable $e): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cash_debug_failure_page($e);
}
function financial_cashier_page(array $c): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations11::financial_cashier_page($c);
}
function financial_admin_drawers_panel(int $cid, int $uid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations12::financial_admin_drawers_panel($cid, $uid);
}
function financial_admin_daily_ledger_timeline(int $cid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations12::financial_admin_daily_ledger_timeline($cid);
}
function financial_admin_reviews_panel(int $cid, int $uid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_admin_reviews_panel($cid, $uid);
}
function financial_location_account_id(int $cid, int $locationId): ?int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_location_account_id($cid, $locationId);
}
function financial_admin_balance_kpis_html(array $pos): string
{
    return \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_admin_balance_kpis_html($pos);
}
function financial_admin_locations_panel(int $cid, int $uid, array $pos): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_admin_locations_panel($cid, $uid, $pos);
}
function financial_admin_movements_panel(int $cid, int $limit = 180): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_admin_movements_panel($cid, $limit);
}
function financial_daily_drawer_partials_html(
    int $cid,
    int $uid,
    array $state,
): string {
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations13::financial_daily_drawer_partials_html($cid, $uid, $state);
}
function financial_admin_daily_conference_panel(int $cid, int $uid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_daily_conference_panel($cid, $uid);
}
function financial_admin_daily_consolidate(int $cid, int $uid): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_daily_consolidate($cid, $uid);
}
function financial_admin_save_receipt(int $cid, int $uid): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_save_receipt($cid, $uid);
}
function financial_admin_save_payment(int $cid, int $uid): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_save_payment($cid, $uid);
}
function financial_admin_save_transfer(int $cid, int $uid): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations14::financial_admin_save_transfer($cid, $uid);
}
function financial_admin_operations_panel(int $cid, int $uid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations15::financial_admin_operations_panel($cid, $uid);
}
function financial_creditor_upsert_from_post(int $cid, int $uid): int
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations15::financial_creditor_upsert_from_post($cid, $uid);
}
function financial_creditor_directory_card(array $r, int $cid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations15::financial_creditor_directory_card($r, $cid);
}
function page_creditors(): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations16::page_creditors();
}
function financial_admin_attention_panel(int $cid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations16::financial_admin_attention_panel($cid);
}
function financial_admin_conferences_panel(int $cid, int $uid): string
{
    return \Prontoo\Runtime\Financial\FinancialRuntimeOperations16::financial_admin_conferences_panel($cid, $uid);
}
function financial_admin_page(array $c): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations17::financial_admin_page($c);
}
function page_financial(): void
{
    \Prontoo\Runtime\Financial\FinancialRuntimeOperations17::page_financial();
}
