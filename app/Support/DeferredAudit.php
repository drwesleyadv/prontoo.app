<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function maestro_deferred_storage_dir(): string
{
    return \Prontoo\Infrastructure\Legacy\DeferredAudit\DeferredAuditInfrastructureOperations01::maestro_deferred_storage_dir();
}

function maestro_deferred_storage_dirs(string $type = "audit"): array
{
    return \Prontoo\Infrastructure\Legacy\DeferredAudit\DeferredAuditInfrastructureOperations01::maestro_deferred_storage_dirs($type);
}

function maestro_deferred_canonicalize(mixed $value): mixed
{
    return \Prontoo\Infrastructure\Legacy\DeferredAudit\DeferredAuditInfrastructureOperations01::maestro_deferred_canonicalize($value);
}

function maestro_deferred_sign(array $envelope): string
{
    return \Prontoo\Runtime\Legacy\DeferredAudit\DeferredAuditRuntimeOperations01::maestro_deferred_sign($envelope);
}

function maestro_deferred_spool_write(string $dir, string $id, string $json): bool
{
    return \Prontoo\Infrastructure\Legacy\DeferredAudit\DeferredAuditInfrastructureOperations01::maestro_deferred_spool_write($dir, $id, $json);
}

function maestro_deferred_enqueue(string $type, array $payload): ?string
{
    return \Prontoo\Runtime\Legacy\DeferredAudit\DeferredAuditRuntimeOperations01::maestro_deferred_enqueue($type, $payload);
}

function maestro_defer_audit_event(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $context,
    ?int $actorUserId = null,
): bool {
    return \Prontoo\Runtime\Legacy\DeferredAudit\DeferredAuditRuntimeOperations01::maestro_defer_audit_event($event, $entity, $entityId, $context, $actorUserId);
}

function maestro_deferred_envelope_valid(array $envelope): bool
{
    return \Prontoo\Runtime\Legacy\DeferredAudit\DeferredAuditRuntimeOperations01::maestro_deferred_envelope_valid($envelope);
}

function maestro_deferred_policy_allows(array $envelope): bool
{
    return \Prontoo\Infrastructure\Legacy\DeferredAudit\DeferredAuditInfrastructureOperations01::maestro_deferred_policy_allows($envelope);
}

function maestro_deferred_restore_scope(array $envelope): array
{
    return \Prontoo\Infrastructure\Legacy\DeferredAudit\DeferredAuditInfrastructureOperations01::maestro_deferred_restore_scope($envelope);
}

function maestro_process_deferred_audit(array $envelope): bool
{
    return \Prontoo\Runtime\Legacy\DeferredAudit\DeferredAuditRuntimeOperations01::maestro_process_deferred_audit($envelope);
}

function maestro_deferred_retry_metadata(string $file): array
{
    return \Prontoo\Infrastructure\Legacy\DeferredAudit\DeferredAuditInfrastructureOperations01::maestro_deferred_retry_metadata($file);
}

function maestro_deferred_retry_delay_seconds(int $attempt): int
{
    return \Prontoo\Infrastructure\Legacy\DeferredAudit\DeferredAuditInfrastructureOperations01::maestro_deferred_retry_delay_seconds($attempt);
}

function maestro_deferred_dead_letter(
    string $claimed,
    string $dir,
    string $id,
    string $reason,
): bool {
    return \Prontoo\Infrastructure\Legacy\DeferredAudit\DeferredAuditInfrastructureOperations01::maestro_deferred_dead_letter($claimed, $dir, $id, $reason);
}

function maestro_deferred_state_write(array $stats): void
{
    \Prontoo\Infrastructure\Legacy\DeferredAudit\DeferredAuditInfrastructureOperations01::maestro_deferred_state_write($stats);
}

function maestro_process_deferred_work(int $budgetMs = 5000, int $limit = 500): array
{
    return \Prontoo\Runtime\Legacy\DeferredAudit\DeferredAuditRuntimeOperations01::maestro_process_deferred_work($budgetMs, $limit);
}
