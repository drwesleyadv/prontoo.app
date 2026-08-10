<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use PDO;
use Prontoo\Application\Financial\FinancialCashierAttentionPort;

final class PdoFinancialCashierAttentionRepository implements FinancialCashierAttentionPort
{
    public function hasAssignedPosition(int $clinicId, int $userId): bool
    {
        $pdo = self::pdo();
        $statement = $pdo->prepare(
            "SELECT l.id FROM pi_financial_locations l JOIN pi_financial_location_users lu ON lu.location_id=l.id AND lu.clinic_id=l.clinic_id AND lu.user_id=? AND lu.active=1 WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,l.id LIMIT 1",
        );
        $statement->execute([$userId, $clinicId]);
        if ((int) ($statement->fetchColumn() ?: 0) > 0) {
            return true;
        }
        $fallback = $pdo->prepare(
            "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND user_id=? AND active=1 ORDER BY id ASC LIMIT 1",
        );
        $fallback->execute([$clinicId, $userId]);
        return (int) ($fallback->fetchColumn() ?: 0) > 0;
    }

    public function hasOpenSessionBefore(int $clinicId, int $userId, string $businessDate): bool
    {
        $statement = self::pdo()->prepare(
            "SELECT id FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date<? AND status='open' ORDER BY business_date DESC,id DESC LIMIT 1",
        );
        $statement->execute([$clinicId, $userId, $businessDate]);
        return (int) ($statement->fetchColumn() ?: 0) > 0;
    }

    private static function pdo(): PDO
    {
        return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
    }
}
