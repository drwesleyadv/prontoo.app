<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
function document_identifier_consonants(): array
{
    return \Prontoo\Domain\Legacy\Documents\DocumentIdentifierPolicy::document_identifier_consonants();
}
function document_identifier_base(): int
{
    return \Prontoo\Domain\Legacy\Documents\DocumentIdentifierPolicy::document_identifier_base();
}
function document_identifier_random_alphabet(): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentIdentifierPolicy::document_identifier_random_alphabet();
}
function document_identifier_valid_alphabet(string $alphabet): bool
{
    return \Prontoo\Domain\Legacy\Documents\DocumentIdentifierPolicy::document_identifier_valid_alphabet($alphabet);
}
function document_identifier_digits(int $sequence): array
{
    return \Prontoo\Domain\Legacy\Documents\DocumentIdentifierPolicy::document_identifier_digits($sequence);
}
function document_identifier_numeric_part(int $sequence): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentIdentifierPolicy::document_identifier_numeric_part($sequence);
}
function document_identifier_encode(int $sequence, string $alphabet): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentIdentifierPolicy::document_identifier_encode($sequence, $alphabet);
}
function document_identifier_display(?string $identifier): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentIdentifierPolicy::document_identifier_display($identifier);
}
function document_identifier_capacity_for_length(int $length = 3): int
{
    return \Prontoo\Domain\Legacy\Documents\DocumentIdentifierPolicy::document_identifier_capacity_for_length($length);
}
function document_assign_identifier(int $cid, int $docId): string
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations01::document_assign_identifier($cid, $docId);
}
function document_print_header_html(?string $identifier): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentIdentifierPolicy::document_print_header_html($identifier);
}
function document_print_footer_html(?string $identifier): string
{
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_print_footer_html($identifier);
}
function document_status_label(string $status): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTypePolicy::document_status_label($status);
}
function document_status_class(string $status): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTypePolicy::document_status_class($status);
}
function document_editable_status(string $status): bool
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTypePolicy::document_editable_status($status);
}
function create_document_draft_from_template(
    array $c,
    int $templateId,
    int $patientId = 0,
    int $appointmentId = 0,
    array $context = [],
): int {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations01::create_document_draft_from_template($c, $templateId, $patientId, $appointmentId, $context);
}
function save_document_draft(
    array $c,
    int $docId,
    string $title,
    int $patientId,
    string $content,
    string $status = "preparado",
    int $appointmentId = 0,
    array $context = [],
): void {
    \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations01::save_document_draft($c, $docId, $title, $patientId, $content, $status, $appointmentId, $context);
}
function discard_document_draft(array $c, int $docId): void
{
    \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations01::discard_document_draft($c, $docId);
}
function confirm_document_issue(array $c, int $docId): void
{
    \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations01::confirm_document_issue($c, $docId);
}
function document_type_options(): array
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTypePolicy::document_type_options();
}
function document_system_field_groups(): array
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTypePolicy::document_system_field_groups();
}
function document_system_fields(): array
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTypePolicy::document_system_fields();
}
function document_allowed_types_for_role(string $role): array
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTypePolicy::document_allowed_types_for_role($role);
}
function document_type_options_for_role(string $role): array
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTypePolicy::document_type_options_for_role($role);
}
function document_role_config(string $role, int $cid): array
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations01::document_role_config($role, $cid);
}
function document_ds_metric(
    string $label,
    mixed $value,
    string $iconName,
    string $note = "",
    string $class = "",
): string {
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_ds_metric($label, $value, $iconName, $note, $class);
}
function document_ds_status_chip(string $status): string
{
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_ds_status_chip($status);
}
function document_ds_type_label(array $typeOptions, ?string $type): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTypePolicy::document_ds_type_label($typeOptions, $type);
}
function document_ds_recent_row(array $d, array $typeOptions): string
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations01::document_ds_recent_row($d, $typeOptions);
}
function document_ds_model_row(array $tpl, array $typeOptions, int $cid): string
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations01::document_ds_model_row($tpl, $typeOptions, $cid);
}
if (!function_exists("cpf_br")) {
    function cpf_br(string $cpf): string
    {
        return \Prontoo\Domain\Legacy\Documents\DocumentsCompatibilityOperations01::cpfBr($cpf);
    }
}
function document_template_visible_where(array $c, string $alias = "dt"): array
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTemplatePolicy::document_template_visible_where($c, $alias);
}
function can_edit_document_template(array $c, array $tpl): bool
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTemplatePolicy::can_edit_document_template($c, $tpl);
}
function document_template_status_after_save(
    array $c,
    string $ownerRole,
    bool $isUpdate = false,
): array {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations01::document_template_status_after_save($c, $ownerRole, $isUpdate);
}
function document_can_issue_template(array $c, array $tpl): bool
{
    return \Prontoo\Domain\Legacy\Documents\DocumentTemplatePolicy::document_can_issue_template($c, $tpl);
}
function document_sanitize_html(string $html): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentHtmlPolicy::document_sanitize_html($html);
}
function document_body_to_html(string $body): string
{
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_body_to_html($body);
}
function document_print_page_core_html(
    string $html,
    ?string $identifier = null,
): string {
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_print_page_core_html($html, $identifier);
}
function document_preview_page_html(
    string $html,
    ?string $identifier = null,
): string {
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_preview_page_html($html, $identifier);
}
function document_print_document_shell_html(
    array $doc,
    bool $autoPrint = true,
    string $mode = "print",
): string {
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_print_document_shell_html($doc, $autoPrint, $mode);
}
function document_body_is_empty(string $html): bool
{
    return \Prontoo\Domain\Legacy\Documents\DocumentHtmlPolicy::document_body_is_empty($html);
}
function document_issue_meta_sentence(
    ?string $issuedName,
    ?string $patientName,
    null|string|int $issuedAt,
    string $status = "emitido",
): string {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations02::document_issue_meta_sentence($issuedName, $patientName, $issuedAt, $status);
}
function render_document_body(string $body, array $vars): string
{
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::render_document_body($body, $vars);
}
function document_time_br(?string $dt): string
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations02::document_time_br($dt);
}
function document_appointment_row(int $cid, int $appointmentId): ?array
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations02::document_appointment_row($cid, $appointmentId);
}
function document_resolve_patient_appointment(
    int $cid,
    int $patientId = 0,
    int $appointmentId = 0,
): array {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations02::document_resolve_patient_appointment($cid, $patientId, $appointmentId);
}
function document_appointment_options(
    int $cid,
    int $selectedId = 0,
    int $limit = 240,
): array {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations02::document_appointment_options($cid, $selectedId, $limit);
}
function document_appointment_select_field(
    int $cid,
    int $selectedId = 0,
): string {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations02::document_appointment_select_field($cid, $selectedId);
}
function document_context_json_decode(mixed $raw): array
{
    return \Prontoo\Domain\Legacy\Documents\DocumentHtmlPolicy::document_context_json_decode($raw);
}
function document_context_json_encode(array $context): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentsDomainOperations02::document_context_json_encode($context);
}
function document_context_ids_from_post(array $c): array
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations02::document_context_ids_from_post($c);
}
function document_context_select_options(
    int $cid,
    string $kind,
    int $selectedId = 0,
    int $limit = 120,
): array {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations02::document_context_select_options($cid, $kind, $selectedId, $limit);
}
function document_context_binding_fields(array $c, array $doc): string
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations02::document_context_binding_fields($c, $doc);
}
function document_issue_context(
    array $c,
    int $patientId = 0,
    int $appointmentId = 0,
    array $context = [],
): array {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations03::document_issue_context($c, $patientId, $appointmentId, $context);
}
function approved_document_template_options(array $c): array
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations03::approved_document_template_options($c);
}
function patient_document_timeline_items(
    array $c,
    int $patientId,
    int $limit = 40,
): array {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations03::patient_document_timeline_items($c, $patientId, $limit);
}
function patient_summary_excerpt(string $text, int $limit = 220): string
{
    return \Prontoo\Domain\Legacy\Documents\DocumentsDomainOperations02::patient_summary_excerpt($text, $limit);
}
function patient_summary_clinical_block(
    string $title,
    string $iconName,
    array $items,
    string $empty,
): string {
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::patient_summary_clinical_block($title, $iconName, $items, $empty);
}
function document_can_access(array $c, array $doc): bool
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations03::document_can_access($c, $doc);
}
function fetch_document_for_current_user(array $c, int $docId): ?array
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations03::fetch_document_for_current_user($c, $docId);
}
function page_document_view(): void
{
    \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations03::page_document_view();
}
function page_document_print(): void
{
    \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations03::page_document_print();
}
function document_field_buttons(): string
{
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_field_buttons();
}
function document_editor_button(
    string $cmd,
    string $iconName,
    string $label,
    string $value = "",
): string {
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_editor_button($cmd, $iconName, $label, $value);
}
function document_editor_html(
    string $name,
    string $value = "",
    string $id = "",
): string {
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_editor_html($name, $value, $id);
}
function document_stage_actions(string $stage): string
{
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations03::document_stage_actions($stage);
}
function document_stage_strip(
    string $stage,
    int $approved,
    int $pending,
    int $visibleDocs,
): string {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations04::document_stage_strip($stage, $approved, $pending, $visibleDocs);
}
function document_overview_cards(
    int $approved,
    int $pending,
    int $visibleDocs,
    string $docSearchMode,
): string {
    return \Prontoo\Presentation\Legacy\Documents\DocumentsPresentationOperations01::document_overview_cards($approved, $pending, $visibleDocs, $docSearchMode);
}
function document_template_author_form(
    array $typeOptions,
    string $selectedType = "declaracao",
    string $title = "",
    string $body = "",
    int $id = 0,
    string $submitLabel = "Salvar modelo",
    string $cancelHref = "",
): string {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations04::document_template_author_form($typeOptions, $selectedType, $title, $body, $id, $submitLabel, $cancelHref);
}
function document_model_search_card(
    string $modelSearch,
    string $mode = "emit",
): string {
    return \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations04::document_model_search_card($modelSearch, $mode);
}
function page_documents(): void
{
    \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations05::page_documents();
}
function page_procedures(): void
{
    \Prontoo\Runtime\Legacy\Documents\DocumentsRuntimeOperations06::page_procedures();
}
