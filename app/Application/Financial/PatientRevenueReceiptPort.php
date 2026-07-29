<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

interface PatientRevenueReceiptPort
{
    public function receive(
        int $clinicId,
        int $patientId,
        int $revenueId,
        int $userId,
        string $role,
    ): array;
}
