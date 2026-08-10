<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Financial;

use Prontoo\Application\Financial\PatientRevenueSettlementPort;

final class PatientRevenueSettlementAdapter implements PatientRevenueSettlementPort
{
    public function requireOpenSession(int $clinicId, int $userId): array
    {
        return FinancialRuntimeOperations07::financial_require_open_session($clinicId, $userId);
    }

    public function ensureAdminSafe(int $clinicId, int $userId): int
    {
        return FinancialRuntimeOperations03::financial_ensure_admin_safe($clinicId, $userId);
    }

    public function createMovement(
        int $clinicId,
        string $type,
        int $amount,
        ?int $from,
        ?int $to,
        ?int $sessionId,
        int $userId,
        string $title,
        string $paymentMethod = '',
        string $notes = '',
        string $status = 'confirmed',
        string $sourceEntity = '',
        int $sourceId = 0,
    ): int {
        return FinancialRuntimeOperations06::financial_create_movement(
            $clinicId,
            $type,
            $amount,
            $from,
            $to,
            $sessionId,
            $userId,
            $title,
            $paymentMethod,
            $notes,
            $status,
            $sourceEntity,
            $sourceId,
        );
    }
}
