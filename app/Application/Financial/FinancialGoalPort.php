<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

interface FinancialGoalPort
{
    public function save(
        int $clinicId,
        string $monthKey,
        int $targetCents,
        string $baseMetric,
        bool $shareWithTeam,
        int $userId,
    ): void;
}
