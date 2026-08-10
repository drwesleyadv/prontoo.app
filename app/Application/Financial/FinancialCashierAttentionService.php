<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

final readonly class FinancialCashierAttentionService
{
    public function __construct(private FinancialCashierAttentionPort $port)
    {
    }

    public function requiresAttention(array $context, bool $readOnly, string $businessDate): bool
    {
        if (($context['scope'] ?? '') !== 'clinic' || (string) ($context['role'] ?? '') !== 'recepcionista') {
            return false;
        }
        $clinicId = (int) ($context['clinic_id'] ?? 0);
        $userId = (int) ($context['user']['id'] ?? 0);
        if ($clinicId <= 0 || $userId <= 0 || $readOnly || $businessDate === '') {
            return false;
        }
        return $this->port->hasAssignedPosition($clinicId, $userId) &&
            $this->port->hasOpenSessionBefore($clinicId, $userId, $businessDate);
    }
}
