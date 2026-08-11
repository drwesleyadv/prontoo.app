<?php
declare(strict_types=1);

namespace Prontoo\Application\SecurityAccess;

interface SecurityIncidentPort
{
    public function recordScopeViolation(
        int $clinicId,
        ?int $userId,
        string $roleCode,
        string $route,
        string $violationKey,
        string $fingerprint,
        string $details,
    ): void;

    public function scopedEntityReadShape(string $entity): string;
}
