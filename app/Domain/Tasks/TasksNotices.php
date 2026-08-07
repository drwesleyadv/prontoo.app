<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
function notice_target_sql(array $c, string $alias = "n"): array
{
    return \Prontoo\Domain\Legacy\TasksNotices\TasksNoticesDomainOperations01::notice_target_sql($c, $alias);
}
function notice_target_label(array $n, array $users = []): string
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::notice_target_label($n, $users);
}
function notice_recipient_phrase(array $n, array $users = []): string
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::notice_recipient_phrase($n, $users);
}
function notice_recipient_people(int $cid, array $notice, array $team): array
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::notice_recipient_people($cid, $notice, $team);
}
function notice_recipient_pills(int $cid, array $notice, array $team): string
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::notice_recipient_pills($cid, $notice, $team);
}
function task_event(
    int $cid,
    int $taskId,
    string $eventKey,
    ?int $actorUserId = null,
    ?string $fromStatus = null,
    ?string $toStatus = null,
    ?string $note = null,
): void {
    \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::task_event($cid, $taskId, $eventKey, $actorUserId, $fromStatus, $toStatus, $note);
}
function notify_task_personal_assignment(
    int $cid,
    int $taskId,
    string $taskTitle,
    ?string $taskDescription,
    int $targetUserId,
    ?int $createdBy = null,
    string $source = "manual",
): void {
    \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::notify_task_personal_assignment($cid, $taskId, $taskTitle, $taskDescription, $targetUserId, $createdBy, $source);
}
function workflow_valid_role(int $cid, ?string $role): ?string
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::workflow_valid_role($cid, $role);
}
function workflow_notice_body(string $body, string $action = ""): string
{
    return \Prontoo\Domain\Legacy\TasksNotices\TasksNoticesDomainOperations01::workflow_notice_body($body, $action);
}
function workflow_care_notice(
    int $cid,
    string $title,
    string $body,
    string $targetScope = "role",
    ?string $targetRole = null,
    ?int $targetUserId = null,
    string $sourceEvent = "",
    string $sourceEntity = "",
    string $sourceEntityId = "",
    int $requiresAck = 1,
    string $action = "",
): ?int {
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::workflow_care_notice($cid, $title, $body, $targetScope, $targetRole, $targetUserId, $sourceEvent, $sourceEntity, $sourceEntityId, $requiresAck, $action);
}
function workflow_task_notice(
    int $cid,
    int $taskId,
    string $taskTitle,
    string $description,
    ?int $patientLinkId,
    ?int $appointmentId,
    string $targetScope,
    ?string $targetRole,
    ?int $targetUserId,
    string $sourceEvent,
    string $sourceEntity,
    string $sourceEntityId,
): void {
    \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::workflow_task_notice($cid, $taskId, $taskTitle, $description, $patientLinkId, $appointmentId, $targetScope, $targetRole, $targetUserId, $sourceEvent, $sourceEntity, $sourceEntityId);
}
function workflow_appointment_payment_pending(
    int $cid,
    int $appointmentId,
): bool {
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::workflow_appointment_payment_pending($cid, $appointmentId);
}
function workflow_on_task_started(
    array $task,
    int $startedBy,
    string $role,
): void {
    \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations01::workflow_on_task_started($task, $startedBy, $role);
}
function workflow_on_appointment_finished(
    int $cid,
    int $appointmentId,
    int $patientLinkId,
    ?int $finishedBy = null,
    string $source = "atendimento",
): void {
    \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations02::workflow_on_appointment_finished($cid, $appointmentId, $patientLinkId, $finishedBy, $source);
}
function unread_notifications_count(array $c): int
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations02::unread_notifications_count($c);
}
function notification_button(array $c): string
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations02::notification_button($c);
}
function team_user_ids_for_roles(int $cid, array $roles): array
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations02::team_user_ids_for_roles($cid, $roles);
}
function first_team_user_for_role(int $cid, string $role): ?int
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations02::first_team_user_for_role($cid, $role);
}
function create_workflow_task(
    int $cid,
    string $title,
    string $description,
    ?int $assignedTo,
    ?int $patientLinkId,
    ?int $appointmentId,
    string $sourceEvent,
    string $sourceEntity,
    string $sourceEntityId,
    ?string $dueAt = null,
    string $targetScope = "",
    ?string $targetRole = null,
    bool $strict = false,
): ?int {
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations02::create_workflow_task($cid, $title, $description, $assignedTo, $patientLinkId, $appointmentId, $sourceEvent, $sourceEntity, $sourceEntityId, $dueAt, $targetScope, $targetRole, $strict);
}
function workflow_on_patient_arrived(
    int $cid,
    int $appointmentId,
    int $patientLinkId,
): void {
    \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations02::workflow_on_patient_arrived($cid, $appointmentId, $patientLinkId);
}
function workflow_on_task_completed(
    array $task,
    int $completedBy,
    string $role,
): void {
    \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations02::workflow_on_task_completed($task, $completedBy, $role);
}
function task_nav_counts(array $c): array
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations02::task_nav_counts($c);
}
function page_admin_global_notices(): void
{
    \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations03::page_admin_global_notices();
}
function page_tasks(): void
{
    \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations04::page_tasks();
}
function readonly_support_alerts_ensure_schema(): void
{
    \Prontoo\Infrastructure\Legacy\TasksNotices\TasksNoticesInfrastructureOperations01::readonly_support_alerts_ensure_schema();
}

function readonly_support_notice_screen(array $c, string $view = "sent"): string
{
    return \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations05::readonly_support_notice_screen($c, $view);
}
function page_notices(): void
{
    \Prontoo\Runtime\Legacy\TasksNotices\TasksNoticesRuntimeOperations06::page_notices();
}
