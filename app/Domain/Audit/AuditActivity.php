<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
function mask(mixed $v): mixed
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::mask($v);
}
function mask_document_value(mixed $v): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::mask_document_value($v);
}
function pt_list(array $items): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::pt_list($items);
}
function audit_change_body(array $fields): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_change_body($fields);
}
function audit_value_present(mixed $v): bool
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_value_present($v);
}
function audit_field_list(array $ctx, array $labels, array $forced = []): array
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_field_list($ctx, $labels, $forced);
}
function audit_registered_body(
    array $fields,
    string $empty = "Nenhum dado adicional foi informado.",
): string {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_registered_body($fields, $empty);
}
function audit_status_body(array $ctx, string $label = "status"): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_status_body($ctx, $label);
}
function audit_patient_name(array $ctx, mixed $entityId = null): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_patient_name($ctx, $entityId);
}
function audit_person_target(
    string $label,
    array $ctx,
    string $nameKey = "target_name",
): string {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_person_target($label, $ctx, $nameKey);
}
function audit_patient_record_target(array $ctx, mixed $entityId = null): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_patient_record_target($ctx, $entityId);
}
function audit_clinic_target(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_clinic_target($ctx);
}
function audit_task_target(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_task_target($ctx);
}
function audit_notice_target(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_notice_target($ctx);
}
function audit_appointment_target(array $ctx): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::audit_appointment_target($ctx);
}
function audit_ctx_pick(array $ctx, array $keys): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_ctx_pick($ctx, $keys);
}
function audit_money_text(
    array $ctx,
    array $keys = ["valor", "amount", "amount_cents", "target_cents"],
): string {
    return \Prontoo\Presentation\Legacy\AuditActivity\AuditActivityPresentationOperations01::audit_money_text($ctx, $keys);
}
function audit_status_text(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_status_text($ctx);
}
function audit_due_text(array $ctx): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::audit_due_text($ctx);
}
function audit_target_scope_label(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_target_scope_label($ctx);
}
function audit_finance_base_label_from_ctx(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_finance_base_label_from_ctx($ctx);
}
function audit_account_name(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_account_name($ctx);
}
function audit_counterparty_name(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_counterparty_name($ctx);
}
function audit_financial_title(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_financial_title($ctx);
}
function audit_body_for_event(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
): string {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::audit_body_for_event($event, $entity, $entityId, $ctx);
}
function audit_context_array(array $row): array
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_context_array($row);
}
function audit_integrity_base(array $r): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_integrity_base($r);
}
function verify_audit_row(array $r): bool
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::verify_audit_row($r);
}
function audit_chain_integrity_status(int $limit = 240): array
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::audit_chain_integrity_status($limit);
}
function audit_select_sql(): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_select_sql();
}
function int_ids(array $rows, string $key): array
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::int_ids($rows, $key);
}
function fetch_map(string $table, array $ids, string $cols = "id"): array
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::fetch_map($table, $ids, $cols);
}
function scoped_patient_map(
    int $cid,
    array $ids,
    string $cols = "id,person_id",
): array {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::scoped_patient_map($cid, $ids, $cols);
}
function scoped_user_map(int $cid, array $ids, string $cols = "id,name"): array
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::scoped_user_map($cid, $ids, $cols);
}
function audit_where_sql(string $where): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::audit_where_sql($where);
}
function audit_rows_light(
    string $where = "1=1",
    array $p = [],
    int $limit = 80,
    int $offset = 0,
): array {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::audit_rows_light($where, $p, $limit, $offset);
}
function activity_axis_for_event(string $event): array
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::activity_axis_for_event($event);
}
function activity_module_label(?string $entity): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::activity_module_label($entity);
}
function activity_time_direct(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::activity_time_direct($value, $clinicId, $context);
}
function activity_text_value(mixed $v): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::activity_text_value($v);
}
function activity_clean_name(string $value, string $fallback = ""): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::activity_clean_name($value, $fallback);
}
function activity_money_from_ctx(array $ctx): string
{
    return \Prontoo\Presentation\Legacy\AuditActivity\AuditActivityPresentationOperations01::activity_money_from_ctx($ctx);
}
function activity_date_from_ctx(array $ctx, array $keys): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::activity_date_from_ctx($ctx, $keys);
}
function activity_status_from_ctx(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations01::activity_status_from_ctx($ctx);
}
function activity_display_label(string $label): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::activity_display_label($label);
}
function activity_changed_fields(
    string $event,
    ?string $entity,
    array $ctx,
): array {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::activity_changed_fields($event, $entity, $ctx);
}
function activity_patient_name(array $ctx, mixed $entityId = null): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::activity_patient_name($ctx, $entityId);
}
function activity_title_from_ctx(
    array $ctx,
    string $fallback = "registro",
): string {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::activity_title_from_ctx($ctx, $fallback);
}
function activity_person_from_ctx(
    array $ctx,
    string $fallback = "colaborador",
): string {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::activity_person_from_ctx($ctx, $fallback);
}
function activity_target_scope_human(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::activity_target_scope_human($ctx);
}
function activity_financial_label(array $ctx, string $fallback): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::activity_financial_label($ctx, $fallback);
}
function activity_environment_label(array $ctx): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations01::activity_environment_label($ctx);
}
function activity_human_sentence(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
    ?int $uid,
): string {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations02::activity_human_sentence($event, $entity, $entityId, $ctx, $uid);
}
function activity_action_verb(string $event): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::activity_action_verb($event);
}
function activity_direct_target(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
): string {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::activity_direct_target($event, $entity, $entityId, $ctx);
}
function activity_direct_title(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
    ?int $uid,
): string {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations02::activity_direct_title($event, $entity, $entityId, $ctx, $uid);
}
function activity_context_details(
    string $event,
    ?string $entity,
    array $ctx,
): array {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations02::activity_context_details($event, $entity, $ctx);
}
function activity_direct_body(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
): string {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations02::activity_direct_body($event, $entity, $entityId, $ctx);
}
function activity_meta_text(
    string $event,
    ?string $entity,
    string $currentMeta = "",
): string {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::activity_meta_text($event, $entity, $currentMeta);
}
function activity_fallback_body(string $event, ?string $entity): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations02::activity_fallback_body($event, $entity);
}
function audit_items(array $rows, bool $global = false): array
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations02::audit_items($rows, $global);
}
function count_for_clinics(
    string $table,
    array $clinicIds,
    string $where = "1=1",
): array {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations02::count_for_clinics($table, $clinicIds, $where);
}
function clinic_recent_metrics(array $clinicIds, int $days = 30): array
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations03::clinic_recent_metrics($clinicIds, $days);
}
function audit_patient_name_by_link(int $patientId, ?int $cid = null): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations03::audit_patient_name_by_link($patientId, $cid);
}
function audit_user_name_lookup(int $uid, ?int $cid = null): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations03::audit_user_name_lookup($uid, $cid);
}
function audit_clinic_name_lookup(int $cid): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations03::audit_clinic_name_lookup($cid);
}
function audit_enrich_context(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $context,
    ?int $cid = null,
): array {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations03::audit_enrich_context($event, $entity, $entityId, $context, $cid);
}
function audit_should_write(string $event): bool
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::audit_should_write($event);
}

function audit_trusted_origin_resolve(
    array &$context,
    ?array $trustedOrigin,
): array {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::audit_trusted_origin_resolve($context, $trustedOrigin);
}
function audit(
    string $event,
    ?string $entity = null,
    mixed $entityId = null,
    array $context = [],
    ?array $trustedOrigin = null,
): bool {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::audit($event, $entity, $entityId, $context, $trustedOrigin);
}
function audit_actor_name(array $ctx, ?int $uid): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::audit_actor_name($ctx, $uid);
}
function audit_document_type_text(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::audit_document_type_text($ctx);
}
function audit_document_article(string $label): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::audit_document_article($label);
}
function audit_document_activity_sentence(
    string $who,
    string $action,
    array $ctx,
): string {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::audit_document_activity_sentence($who, $action, $ctx);
}
function audit_model_title(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::audit_model_title($ctx);
}
function audit_model_activity_sentence(
    string $who,
    string $action,
    array $ctx,
): string {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations02::audit_model_activity_sentence($who, $action, $ctx);
}
function audit_direct_events(): array
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::audit_direct_events();
}
function audit_task_name(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::audit_task_name($ctx);
}
function audit_task_sentence(
    string $who,
    string $verb,
    array $ctx,
    string $suffix = "",
): string {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::audit_task_sentence($who, $verb, $ctx, $suffix);
}
function audit_notice_name(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::audit_notice_name($ctx);
}
function audit_lead_name(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::audit_lead_name($ctx);
}
function audit_status_verb(
    array $ctx,
    string $active = "ativou",
    string $inactive = "desativou",
    string $changed = "alterou",
): string {
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::audit_status_verb($ctx, $active, $inactive, $changed);
}
function audit_route_name(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::audit_route_name($ctx);
}
function audit_global_notice_title(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::audit_global_notice_title($ctx);
}
function audit_subscription_target(array $ctx): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::audit_subscription_target($ctx);
}
function audit_friendly(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $ctx,
    ?int $uid,
): string {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::audit_friendly($event, $entity, $entityId, $ctx, $uid);
}
function event_label(string $e): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::event_label($e);
}
function event_icon(string $e): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::event_icon($e);
}
function entity_label(?string $e): string
{
    return \Prontoo\Domain\Legacy\AuditActivity\AuditActivityDomainOperations03::entity_label($e);
}
function audit_visibility_filter(array $c, string &$where, array &$params): void
{
    \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::audit_visibility_filter($c, $where, $params);
}
function recent_events(int $cid, int $uid, string $role): array
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::recent_events($cid, $uid, $role);
}
function audit_preview_for_appointment(int $cid, int $appointmentId): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::audit_preview_for_appointment($cid, $appointmentId);
}
function audit_team_filter_options(int $cid): array
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::audit_team_filter_options($cid);
}
function audit_activity_day_name(
    string $day,
    int $cid = 0,
    ?array $c = null,
): string {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::audit_activity_day_name($day, $cid, $c);
}
function audit_period_options(int $cid = 0, ?array $c = null): array
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::audit_period_options($cid, $c);
}
function audit_period_clause(
    string $period,
    array &$params,
    int $cid,
    array $c,
    ?string $selectedDate = null,
): string {
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::audit_period_clause($period, $params, $cid, $c, $selectedDate);
}
function audit_activity_url(array $extra = []): string
{
    return \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations04::audit_activity_url($extra);
}
function page_audit(): void
{
    \Prontoo\Runtime\Legacy\AuditActivity\AuditActivityRuntimeOperations05::page_audit();
}
