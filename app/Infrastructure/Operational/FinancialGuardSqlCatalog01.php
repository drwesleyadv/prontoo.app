<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class FinancialGuardSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.financial_guard.01.financial_movement_write_guard.01' => (
                'SELECT id,clinic_id,created_at,cash_session_id FROM pi_financial_movements WHERE ' .
                                    (string) $target['where']
            ),
            'operational.financial_guard.01.financial_movement_write_guard.02' => (
                'SELECT id FROM pi_clinics WHERE id=? FOR UPDATE'
            ),
            'operational.financial_guard.01.financial_movement_write_guard.03' => (
                'SELECT business_date FROM pi_cash_sessions WHERE id=? AND clinic_id=? LIMIT 1'
            ),
            'operational.financial_guard.01.financial_movement_write_guard.04' => (
                "SELECT id FROM pi_financial_daily_closings WHERE clinic_id=? AND business_date=? AND status='consolidado' LIMIT 1 FOR UPDATE"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
