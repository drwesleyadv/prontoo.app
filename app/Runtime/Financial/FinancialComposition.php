<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Financial;

use Prontoo\Application\Financial\FinancialCashierAttentionService;
use Prontoo\Application\Financial\PatientRevenueReceiptService;
use Prontoo\Infrastructure\Financial\PdoFinancialCashierAttentionRepository;
use Prontoo\Infrastructure\Financial\PdoPatientRevenueReceiptRepository;

final class FinancialComposition
{
    private static ?PatientRevenueReceiptService $patientRevenue = null;
    private static ?FinancialCashierAttentionService $cashierAttention = null;

    private function __construct()
    {
    }

    public static function patientRevenueService(): PatientRevenueReceiptService
    {
        return self::$patientRevenue ??= new PatientRevenueReceiptService(
            new PdoPatientRevenueReceiptRepository(new PatientRevenueSettlementAdapter()),
        );
    }

    public static function cashierAttentionService(): FinancialCashierAttentionService
    {
        return self::$cashierAttention ??= new FinancialCashierAttentionService(
            new PdoFinancialCashierAttentionRepository(),
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
