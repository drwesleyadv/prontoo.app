<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
function lead_phone_digits(string $phone): string
{
    return \Prontoo\Runtime\Leads\LeadsRuntimeOperations01::lead_phone_digits($phone);
}
function lead_cpf_br(string $cpf): string
{
    return \Prontoo\Runtime\Leads\LeadsRuntimeOperations01::lead_cpf_br($cpf);
}
function lead_stage_options(): array
{
    return \Prontoo\Domain\Leads\LeadsDomainOperations01::lead_stage_options();
}
function lead_stage_icons(): array
{
    return \Prontoo\Domain\Leads\LeadsDomainOperations01::lead_stage_icons();
}
function lead_stage_normalize(?string $stage): string
{
    return \Prontoo\Domain\Leads\LeadsDomainOperations01::lead_stage_normalize($stage);
}

function lead_active_stages(): array
{
    return \Prontoo\Domain\Leads\LeadsDomainOperations01::lead_active_stages();
}
function lead_active_stage_sql(string $column = "stage"): string
{
    return \Prontoo\Domain\Leads\LeadsDomainOperations01::lead_active_stage_sql($column);
}
function lead_stage_sql_case(string $column = "stage"): string
{
    return \Prontoo\Domain\Leads\LeadsDomainOperations01::lead_stage_sql_case($column);
}

function lead_find_by_phone(
    int $cid,
    string $phoneDigits,
    int $excludeId = 0,
): ?array {
    return \Prontoo\Runtime\Leads\LeadsRuntimeOperations01::lead_find_by_phone($cid, $phoneDigits, $excludeId);
}
function lead_event_create(
    int $cid,
    int $leadId,
    int $uid,
    string $stageFrom,
    string $stageTo,
    string $phone,
    string $source,
    string $interest,
    ?string $next,
    string $body,
    string $eventType = "contato",
): void {
    \Prontoo\Runtime\Leads\LeadsRuntimeOperations01::lead_event_create($cid, $leadId, $uid, $stageFrom, $stageTo, $phone, $source, $interest, $next, $body, $eventType);
}
function lead_prepare_person_for_patient(
    array $lead,
    array $data,
    int $cid,
): int {
    return \Prontoo\Runtime\Leads\LeadsRuntimeOperations01::lead_prepare_person_for_patient($lead, $data, $cid);
}
function lead_patient_by_cpf(int $cid, string $cpf): ?array
{
    return \Prontoo\Runtime\Leads\LeadsRuntimeOperations01::lead_patient_by_cpf($cid, $cpf);
}
function lead_patient_by_phone(int $cid, string $phoneDigits): ?array
{
    return \Prontoo\Runtime\Leads\LeadsRuntimeOperations01::lead_patient_by_phone($cid, $phoneDigits);
}
function lead_history_html(
    array $items,
    array $users,
    array $stageLabels,
): string {
    return \Prontoo\Runtime\Leads\LeadsRuntimeOperations01::lead_history_html($items, $users, $stageLabels);
}
function page_lead_lookup(): void
{
    \Prontoo\Runtime\Leads\LeadsRuntimeOperations01::page_lead_lookup();
}
function page_lead_patient_lookup(): void
{
    \Prontoo\Runtime\Leads\LeadsRuntimeOperations01::page_lead_patient_lookup();
}
function page_leads(): void
{
    \Prontoo\Runtime\Leads\LeadsRuntimeOperations02::page_leads();
}
