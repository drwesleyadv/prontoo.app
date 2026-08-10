<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

interface PatientRevenueSettlementPort
{
    public function requireOpenSession(int $clinicId, int $userId): array;

    public function ensureAdminSafe(int $clinicId, int $userId): int;

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
    ): int;
}
