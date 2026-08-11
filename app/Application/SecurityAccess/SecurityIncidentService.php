<?php
declare(strict_types=1);

namespace Prontoo\Application\SecurityAccess;

use InvalidArgumentException;

final class SecurityIncidentService
{
    public function __construct(private SecurityIncidentPort $port)
    {
    }

    public function recordScopeViolation(
        int $clinicId,
        ?int $userId,
        string $roleCode,
        string $route,
        string $violationKey,
        string $fingerprint,
        string $details,
    ): void {
        if ($clinicId <= 0) {
            return;
        }
        $violationKey = trim($violationKey);
        if ($violationKey === '' || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
            throw new InvalidArgumentException('Incidente de segurança inválido.');
        }
        $this->port->recordScopeViolation(
            $clinicId,
            $userId !== null && $userId > 0 ? $userId : null,
            mb_substr($roleCode, 0, 40),
            mb_substr($route, 0, 120),
            mb_substr($violationKey, 0, 80),
            $fingerprint,
            $details,
        );
    }

    public function scopedEntityReadShape(string $entity): string
    {
        return $this->port->scopedEntityReadShape($entity);
    }
}
