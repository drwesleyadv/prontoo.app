<?php
declare(strict_types=1);

namespace Prontoo\Core\Invariant;

interface InvariantRuntimePort
{
    public function clinicReadOnly(int $clinicId): bool;

    public function recordScopeViolation(string $key, string $sql, string $detail): void;

    public function foreignKeyRelations(string $table): array;

    public function foreignKeyTargetClinicId(
        string $table,
        string $column,
        mixed $value,
        string $scopeColumn,
        int $clinicId,
    ): ?int;

    public function appointmentStatuses(int $clinicId, array $ids): array;
}
