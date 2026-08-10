<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use Prontoo\Application\Financial\FinancialGoalPort;
use Prontoo\Core\Temporal\PiTime;
use Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01;

final class PdoFinancialGoalRepository implements FinancialGoalPort
{
    public function save(
        int $clinicId,
        string $monthKey,
        int $targetCents,
        string $baseMetric,
        bool $shareWithTeam,
        int $userId,
    ): void {
        [$sql, $params] = PiTime::prepareRuntimeQuery(
            "INSERT INTO pi_financial_goals (clinic_id,month_key,target_cents,base_metric,share_with_team,updated_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_cents=VALUES(target_cents), base_metric=VALUES(base_metric), share_with_team=VALUES(share_with_team), updated_by=VALUES(updated_by), updated_at=NOW()",
            [$clinicId, $monthKey, $targetCents, $baseMetric, $shareWithTeam ? 1 : 0, $userId],
        );
        $statement = DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $statement->closeCursor();
    }
}
