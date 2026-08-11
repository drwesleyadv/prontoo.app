<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog11
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.11.cashier_page.01" => (
                "SELECT m.movement_type,m.cash_session_id,m.source_entity,m.status,m.title,m.created_at,m.payment_method,m.amount_cents,lt.name to_name FROM pi_financial_movements m LEFT JOIN pi_financial_locations lt ON lt.id=m.to_location_id AND lt.clinic_id=m.clinic_id WHERE m.clinic_id=? AND (m.cash_session_id=? OR (m.cash_session_id IS NULL AND m.created_by=? AND DATE(m.created_at)=? AND m.movement_type='receipt' AND m.source_entity='appointment')) ORDER BY m.created_at DESC,m.id DESC LIMIT 100"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
