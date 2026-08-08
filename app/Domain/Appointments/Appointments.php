<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
function work_weekday_labels(): array
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::work_weekday_labels();
}
function normalize_work_time(?string $v, string $fallback = ""): string
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::normalize_work_time($v, $fallback);
}
function work_time_short(?string $v): string
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::work_time_short($v);
}
function default_work_hours_rows(): array
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::default_work_hours_rows();
}
function user_work_hours(int $cid, int $uid): array
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::user_work_hours($cid, $uid);
}
function save_user_work_hours(int $cid, int $uid, array $data): void
{
    \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::save_user_work_hours($cid, $uid, $data);
}
function work_hours_form_html(int $cid, int $uid = 0): string
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::work_hours_form_html($cid, $uid);
}
function doctor_work_ranges_for_day(int $cid, int $doctorId, string $day): array
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_ranges_for_day($cid, $doctorId, $day);
}
function doctor_work_hours_label(int $cid, int $doctorId, string $day): string
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_hours_label($cid, $doctorId, $day);
}
function appointment_within_doctor_hours(
    int $cid,
    int $doctorId,
    string $startAt,
    string $endAt,
): bool {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_within_doctor_hours($cid, $doctorId, $startAt, $endAt);
}
function doctor_work_hours_conflict_message(
    int $cid,
    int $doctorId,
    string $startAt,
    string $endAt,
): string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_hours_conflict_message($cid, $doctorId, $startAt, $endAt);
}
function agenda_validate_period_message(
    string $startAt,
    string $endAt,
    string $operation,
    int $cid = 0,
): ?string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::agenda_validate_period_message($startAt, $endAt, $operation, $cid);
}
function appointment_status_code(array $appointment): string
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($appointment);
}

function appointment_is_cancelled(array $a): bool
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_is_cancelled($a);
}
function is_done_appointment(array $a): bool
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::is_done_appointment($a);
}
function appointment_is_terminal(array $a): bool
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_is_terminal($a);
}
function appointment_journey_hard_guard_message(
    array $a,
    string $action,
    string $role = "",
    int $uid = 0,
    ?int $nowTs = null,
): string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message($a, $action, $role, $uid, $nowTs);
}
function appointment_journey_can(
    array $a,
    string $action,
    string $role = "",
    int $uid = 0,
    ?int $nowTs = null,
): bool {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_can($a, $action, $role, $uid, $nowTs);
}
function appointment_journey_elapsed_label(
    array $a,
    string $code,
    int $nowTs,
): string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_elapsed_label($a, $code, $nowTs);
}
function appointment_journey_steps(): array
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_steps();
}
function appointment_journey_view_model(
    array $a,
    string $role = "",
    ?int $nowTs = null,
): array {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_view_model($a, $role, $nowTs);
}
function appointment_journey_meta(
    array $a,
    string $role = "",
    ?int $nowTs = null,
): array {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_meta($a, $role, $nowTs);
}
function appointment_journey_view_code(array $a, ?int $nowTs = null): string
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_view_code($a, $nowTs);
}
function appointment_journey_role_matches(string $role, string $expected): bool
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, $expected);
}

function appointment_journey_role_hint(
    array $a,
    string $role = "",
    ?int $nowTs = null,
): array {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_role_hint($a, $role, $nowTs);
}
function appointment_journey_role_actions(
    array $a,
    string $role = "",
    ?int $nowTs = null,
    int $uid = 0,
): array {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_role_actions($a, $role, $nowTs, $uid);
}
function appointment_journey_quick_actions_html(
    array $a,
    string $role = "",
    ?string $returnHidden = "",
    string $actionUrl = "",
    int $uid = 0,
): string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_quick_actions_html($a, $role, $returnHidden, $actionUrl, $uid);
}
function appointment_journey_steps_html(array $vm): string
{
    return \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::appointment_journey_steps_html($vm);
}
function appointment_journey_fact_html(
    string $iconName,
    string $label,
    string $value,
): string {
    return \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::appointment_journey_fact_html($iconName, $label, $value);
}
function appointment_journey_compact_html(
    array $a,
    string $role = "",
    ?string $returnHidden = "",
    string $actionUrl = "",
): string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_compact_html($a, $role, $returnHidden, $actionUrl);
}
function appointment_journey_card_html(
    array $a,
    string $role = "",
    string $variant = "default",
    ?string $returnHidden = "",
    string $actionUrl = "",
): string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::appointment_journey_card_html($a, $role, $variant, $returnHidden, $actionUrl);
}
function appointment_journey_measure_html(
    array $a,
    string $role = "",
    ?string $returnHidden = "",
    string $actionUrl = "",
): string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::appointment_journey_measure_html($a, $role, $returnHidden, $actionUrl);
}
function delay_minutes_from_appointment(array $a): ?int
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::delay_minutes_from_appointment($a);
}
function format_minutes(?int $minutes): string
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::format_minutes($minutes);
}
function appointment_patient_names(array $appointments, ?int $cid = null): array
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::appointment_patient_names($appointments, $cid);
}
function procedure_options(int $cid, bool $activeOnly = true): array
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_options($cid, $activeOnly);
}
function procedure_option_label(array $p): string
{
    return \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::procedure_option_label($p);
}
function procedure_select_html(
    int $cid,
    string $name = "reason",
    string $selected = "",
    bool $registeredOnly = false,
): string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_select_html($cid, $name, $selected, $registeredOnly);
}
function procedure_reason_from_post(int $cid, string $field = "reason"): string
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_reason_from_post($cid, $field);
}
function doctors(int $cid): array
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::doctors($cid);
}
function normalize_db_datetime(string $value): string
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($value);
}
function agenda_period_label(string $startAt, string $endAt): string
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_period_label($startAt, $endAt);
}
function datetime_local_value(?string $value): string
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::datetime_local_value($value);
}
function agenda_doctor_name(int $cid, ?int $doctorId): string
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_doctor_name($cid, $doctorId);
}
function agenda_conflict_message(
    int $cid,
    ?int $doctorId,
    string $startAt,
    string $endAt,
    string $operation,
    int $ignoreAppointmentId = 0,
    int $ignoreBlockId = 0,
): ?string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_conflict_message($cid, $doctorId, $startAt, $endAt, $operation, $ignoreAppointmentId, $ignoreBlockId);
}
function agenda_day_short_label(string $day): string
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_day_short_label($day);
}
function agenda_month_name_br(string $day): string
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_month_name_br($day);
}
function agenda_day_week_start(string $day): string
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_day_week_start($day);
}
function agenda_week_range_label(string $day): string
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_week_range_label($day);
}
function agenda_crown_label_for_view(
    string $view,
    string $day,
    string $doctorName,
): string {
    return \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::agenda_crown_label_for_view($view, $day, $doctorName);
}
function agenda_iso_local_value(string $day, string $hm): string
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_iso_local_value($day, $hm);
}
function agenda_normalize_local_input(
    string $value,
    string $fallbackDay,
    string $fallbackHm = "08:00",
): string {
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_normalize_local_input($value, $fallbackDay, $fallbackHm);
}
function agenda_local_input_add_minutes(
    string $value,
    int $minutes = 30,
): string {
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_local_input_add_minutes($value, $minutes);
}
function agenda_safe_day_from_local_input(
    string $value,
    string $fallbackDay,
): string {
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_safe_day_from_local_input($value, $fallbackDay);
}
function agenda_notes_ensure_schema(int $cid = 0): bool
{
    return \Prontoo\Infrastructure\Appointments\AppointmentsInfrastructureOperations01::agenda_notes_ensure_schema($cid);
}

function agenda_note_role_codes(array $c): array
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_note_role_codes($c);
}
function agenda_note_visible_for_user(array $note, array $c): bool
{
    return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_note_visible_for_user($note, $c);
}
function agenda_note_visible_for_day(int $cid, string $day, array $c): ?array
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_note_visible_for_day($cid, $day, $c);
}
function agenda_note_card_html(?array $note, ?array $c = null): string
{
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_note_card_html($note, $c);
}
function agenda_note_form_html(
    int $cid,
    string $day,
    array $c,
    array $roleOptions,
): string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations04::agenda_note_form_html($cid, $day, $c, $roleOptions);
}
function page_appointments(): void
{
    \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations05::page_appointments();
}
function appointment_procedure_id_from_post(
    int $cid,
    string $field = "reason",
): ?int {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_procedure_id_from_post($cid, $field);
}
function appointment_procedure_price_cents(
    int $cid,
    ?int $procedureId,
    int $fallback = 0,
): int {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_procedure_price_cents($cid, $procedureId, $fallback);
}
function appointment_payment_post_context(
    int $cid,
    ?int $procedureId,
    int $fallbackAmount = 0,
): array {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_payment_post_context($cid, $procedureId, $fallbackAmount);
}
function appointment_min_duration_message(
    int $cid,
    ?int $procedureId,
    string $startAt,
    string $endAt,
): ?string {
    return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_min_duration_message($cid, $procedureId, $startAt, $endAt);
}
