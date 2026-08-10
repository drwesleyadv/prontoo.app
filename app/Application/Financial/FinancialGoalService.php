<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

use InvalidArgumentException;

final class FinancialGoalService
{
    public function __construct(private FinancialGoalPort $port)
    {
    }

    public function save(
        int $clinicId,
        string $monthKey,
        int $targetCents,
        string $baseMetric,
        bool $shareWithTeam,
        int $userId,
    ): array {
        if ($clinicId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Contexto financeiro inválido.');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            throw new InvalidArgumentException('Competência financeira inválida.');
        }
        if ($targetCents < 0) {
            throw new InvalidArgumentException('A meta financeira não pode ser negativa.');
        }
        $baseMetric = in_array($baseMetric, ['prevista', 'efetivada'], true)
            ? $baseMetric
            : 'efetivada';
        $this->port->save(
            $clinicId,
            $monthKey,
            $targetCents,
            $baseMetric,
            $shareWithTeam,
            $userId,
        );
        return [
            'target_cents' => $targetCents,
            'base_metric' => $baseMetric,
            'share_with_team' => $shareWithTeam ? 1 : 0,
            'month_key' => $monthKey,
        ];
    }
}
