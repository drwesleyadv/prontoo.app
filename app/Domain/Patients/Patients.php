<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
require_once __DIR__ . '/PatientPure.php';

function patient_cpf_br(string $cpf): string
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_cpf_br($cpf);
}
function normalize_patient_tab_icon(?string $icon): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::normalize_patient_tab_icon($icon);
}
function patient_tab_label_clean(string $label): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_tab_label_clean($label);
}
function patient_tab_record_type(int $tabId): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_tab_record_type($tabId);
}
function patient_tab_key(int $tabId): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_tab_key($tabId);
}
function patient_tabs_ensure_schema(): void
{
    \Prontoo\Infrastructure\Legacy\Patients\PatientsInfrastructureOperations01::patient_tabs_ensure_schema();
}

function patient_extra_tabs(int $cid, int $patientId): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_extra_tabs($cid, $patientId);
}
function patient_tab_map_by_type(array $tabs): array
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_tab_map_by_type($tabs);
}
function patient_tab_record_options(array $tabs): array
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_tab_record_options($tabs);
}
function patient_record_type_label(string $type): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_record_type_label($type);
}
function patient_tab_icon_picker(string $current = "clinical_notes"): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_tab_icon_picker($current);
}
function patient_guardians_ensure_schema(): void
{
    \Prontoo\Infrastructure\Legacy\Patients\PatientsInfrastructureOperations01::patient_guardians_ensure_schema();
}

function patient_guardian_relationship_options(): array
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_guardian_relationship_options();
}
function normalize_guardian_relationship(string $v): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::normalize_guardian_relationship($v);
}
function patient_age_years(null|string|int $birth): ?int
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_age_years($birth);
}
function patient_is_minor(array $p): bool
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_is_minor($p);
}
function patient_identity_complete(array $p): bool
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_identity_complete($p);
}
function patient_invoice_registration_missing_fields(array $p): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_invoice_registration_missing_fields($p);
}
function patient_invoice_registration_complete(array $p): bool
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_invoice_registration_complete($p);
}
function patient_invoice_registration_alert_message(array $p): string
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_invoice_registration_alert_message($p);
}
function patient_appointment_registration_block_reason(
    int $cid,
    int $patientId,
): ?string {
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_appointment_registration_block_reason($cid, $patientId);
}
function patient_legal_guardians(int $cid, int $patientId): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_legal_guardians($cid, $patientId);
}
function patient_primary_legal_guardian(int $cid, int $patientId): ?array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_primary_legal_guardian($cid, $patientId);
}
function patient_has_legal_guardian(int $cid, int $patientId): bool
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_has_legal_guardian($cid, $patientId);
}
function patient_profile_status(array $p, array $guardians): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_profile_status($p, $guardians);
}
function patient_sensitive_block_reason(int $cid, int $patientId): ?string
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_sensitive_block_reason($cid, $patientId);
}
function patient_guardian_form_html(
    array $guardian = [],
    string $submit = "Salvar responsável legal",
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_guardian_form_html($guardian, $submit);
}
function patient_legal_guardian_card(
    int $cid,
    int $patientId,
    array $p,
    array $guardians,
    bool $canManage = true,
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_legal_guardian_card($cid, $patientId, $p, $guardians, $canManage);
}
function require_patient_in_clinic(int $cid, int $patientId): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::require_patient_in_clinic($cid, $patientId);
}
function patient_location_defaults(int $cid, array $p = []): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_location_defaults($cid, $p);
}
function patient_zip_input(array $p = []): string
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_zip_input($p);
}
function patient_address_fields(int $cid, array $p = []): string
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_address_fields($cid, $p);
}
function patient_location_fields(int $cid, array $p = []): string
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations01::patient_location_fields($cid, $p);
}
function patient_location_from_post(int $cid): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_location_from_post($cid);
}
function mask_cep(string $cep): string
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::mask_cep($cep);
}
function patient_invoice_contact_from_post(): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_invoice_contact_from_post();
}
function clinic_patient_exists(
    int $cid,
    int $patientId,
    bool $activeOnly = true,
): bool {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::clinic_patient_exists($cid, $patientId, $activeOnly);
}
function patient_options(int $cid): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_options($cid);
}
function patient_autosuggest_datalist(
    int $cid,
    string $id = "prontoo_patient_suggestions",
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_autosuggest_datalist($cid, $id);
}
function patient_lookup_field(
    int $cid,
    string $hiddenName = "patient_link_id",
    string $hiddenValue = "",
    string $inputName = "patient_search",
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_lookup_field($cid, $hiddenName, $hiddenValue, $inputName);
}
function posted_patient_search_value(): string
{
    return \Prontoo\Presentation\Legacy\Patients\PatientsPresentationOperations01::posted_patient_search_value();
}
function resolve_patient_lookup_id(
    int $cid,
    int $postedId,
    string $search = "",
): int {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::resolve_patient_lookup_id($cid, $postedId, $search);
}
function patient_identity_by_cpf(string $cpf, int $cid): ?array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_identity_by_cpf($cpf, $cid);
}
function patient_lookup_payload(int $cid, string $cpf): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_lookup_payload($cid, $cpf);
}
function page_patient_lookup(): void
{
    \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::page_patient_lookup();
}
function patient_directory_filter_options(): array
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_directory_filter_options();
}
function patient_directory_filter_default(): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_directory_filter_default();
}
function patient_directory_filter_icons(): array
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_directory_filter_icons();
}
function patient_directory_cancel_statuses(): array
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_directory_cancel_statuses();
}
function patient_week_utc_range(int $cid): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_week_utc_range($cid);
}
function patient_today_utc_range(int $cid): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_today_utc_range($cid);
}
function patient_directory_filter_where(
    string $filter,
    array &$params,
    string $patientAlias = "pp",
    string $personAlias = "p",
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_directory_filter_where($filter, $params, $patientAlias, $personAlias);
}
function patient_directory_select_metrics_sql(int $cid): string
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations02::patient_directory_select_metrics_sql($cid);
}
function patient_directory_order_sql(string $filter): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_directory_order_sql($filter);
}
function patient_directory_status(array $r): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations03::patient_directory_status($r);
}
function patient_directory_card(array $r, int $cid = 0): string
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations03::patient_directory_card($r, $cid);
}
function patient_profile_overview(
    array $p,
    array $profileStatus,
    int $appointmentCount,
    int $documentCount,
    int $totalConsultations,
    string $firstConsultationLabel,
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations03::patient_profile_overview($p, $profileStatus, $appointmentCount, $documentCount, $totalConsultations, $firstConsultationLabel);
}
function patient_reception_story_time(null|string|int $value): string
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations03::patient_reception_story_time($value);
}
function patient_reception_story_title(
    string $who,
    null|string|int $createdAt,
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations03::patient_reception_story_title($who, $createdAt);
}
function patient_reception_meta_chips_html(array $parts): string
{
    return \Prontoo\Presentation\Legacy\Patients\PatientsPresentationOperations01::patient_reception_meta_chips_html($parts);
}
function patient_reception_history_items(
    int $cid,
    int $patientId,
    array $p,
): array {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations03::patient_reception_history_items($cid, $patientId, $p);
}
function patient_reception_history_panel(array $items): string
{
    return \Prontoo\Presentation\Legacy\Patients\PatientsPresentationOperations01::patient_reception_history_panel($items);
}
function page_patient_suggest(): void
{
    \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations04::page_patient_suggest();
}
function page_patients(): void
{
    \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations05::page_patients();
}
function patient_appointment_duration_label(
    null|string|int $startAt,
    null|string|int $endAt,
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations05::patient_appointment_duration_label($startAt, $endAt);
}
function patient_appointment_elapsed_until_now_label(
    null|string|int $startAt,
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations05::patient_appointment_elapsed_until_now_label($startAt);
}
function patient_appointment_code(array $a): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_appointment_code($a);
}
function patient_appointment_not_started_label(
    array $a,
    int $cid = 0,
    array $context = [],
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations05::patient_appointment_not_started_label($a, $cid, $context);
}
function patient_appointment_real_duration_label(
    array $a,
    int $cid = 0,
    array $context = [],
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations06::patient_appointment_real_duration_label($a, $cid, $context);
}
function patient_appointment_status_title(array $a): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_appointment_status_title($a);
}
function patient_appointment_icon(array $a): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_appointment_icon($a);
}
function patient_appointment_status_class(array $a): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_appointment_status_class($a);
}
function patient_document_type_human_label(?string $typeKey): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_document_type_human_label($typeKey);
}
function patient_appointment_docs_by_appointment(
    int $cid,
    array $appointmentIds,
): array {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations06::patient_appointment_docs_by_appointment($cid, $appointmentIds);
}
function patient_appointment_docs_label(array $docs): string
{
    return \Prontoo\Domain\Legacy\Patients\PatientsDomainOperations01::patient_appointment_docs_label($docs);
}
function patient_appointment_real_start_label(
    array $a,
    int $cid,
    array $context = [],
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations06::patient_appointment_real_start_label($a, $cid, $context);
}
function patient_appointment_real_end_label(
    array $a,
    int $cid,
    array $context = [],
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations06::patient_appointment_real_end_label($a, $cid, $context);
}
function patient_appointment_first_line(
    array $a,
    int $cid,
    array $context = [],
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations06::patient_appointment_first_line($a, $cid, $context);
}
function patient_appointment_first_line_html(
    array $a,
    int $cid,
    array $context = [],
): string {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations06::patient_appointment_first_line_html($a, $cid, $context);
}
function patient_appointment_options_from_rows(array $appts): array
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations06::patient_appointment_options_from_rows($appts);
}
function patient_default_document_appointment(array $appts): ?int
{
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations06::patient_default_document_appointment($appts);
}
function patient_appointment_timeline_items(
    int $cid,
    array $appointments,
    array $context = [],
): array {
    return \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations06::patient_appointment_timeline_items($cid, $appointments, $context);
}
function page_patient(): void
{
    \Prontoo\Runtime\Legacy\Patients\PatientsRuntimeOperations07::page_patient();
}
