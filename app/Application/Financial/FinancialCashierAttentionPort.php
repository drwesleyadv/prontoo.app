<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

interface FinancialCashierAttentionPort
{
    public function hasAssignedPosition(int $clinicId, int $userId): bool;

    public function hasOpenSessionBefore(int $clinicId, int $userId, string $businessDate): bool;
}
