<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Financial;

use Prontoo\Application\Financial\PatientRevenueReceiptService;
use Prontoo\Infrastructure\Financial\PdoPatientRevenueReceiptRepository;

final class FinancialComposition
{
    private static ?PatientRevenueReceiptService $patientRevenue = null;

    private function __construct()
    {
    }

    public static function patientRevenueService(): PatientRevenueReceiptService
    {
        return self::$patientRevenue ??= new PatientRevenueReceiptService(
            new PdoPatientRevenueReceiptRepository(),
        );
    }

    public static function receivePatientRevenue(
        int $clinicId,
        int $patientId,
        int $revenueId,
        int $userId,
        string $role,
    ): array {
        return self::patientRevenueService()->receive(
            $clinicId,
            $patientId,
            $revenueId,
            $userId,
            $role,
        );
    }
}
