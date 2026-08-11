<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog12
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.12.admin_drawers_panel.01" => (
                "SELECT id,name,drawer_lock_status,drawer_locked_business_date,drawer_unlock_at FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND active=1 ORDER BY name,id LIMIT 200"
            ),
            "financial.12.admin_drawers_panel.02" => (
                "SELECT id,location_id,name FROM (SELECT lu.id,lu.location_id,u.name,ROW_NUMBER() OVER (PARTITION BY lu.location_id ORDER BY u.name,lu.id) row_rank FROM pi_financial_location_users lu JOIN pi_users u ON u.id=lu.user_id WHERE lu.clinic_id=? AND lu.location_id IN (" . \Prontoo\Infrastructure\Operational\OperationalSequenceSql::placeholders((int) $itemCount) . ") AND lu.active=1) ranked WHERE row_rank<=80 ORDER BY location_id,name,id"
            ),
            "financial.12.admin_daily_ledger_timeline.01" => (
                "SELECT m.movement_type,m.status,m.payment_method,m.created_at,m.title,m.amount_cents,lf.name from_name,lt.name to_name,u.name user_name FROM pi_financial_movements m LEFT JOIN pi_financial_locations lf ON lf.id=m.from_location_id AND lf.clinic_id=m.clinic_id LEFT JOIN pi_financial_locations lt ON lt.id=m.to_location_id AND lt.clinic_id=m.clinic_id LEFT JOIN pi_users u ON u.id=m.created_by WHERE m.clinic_id=? AND m.created_at>=? AND m.created_at<? AND m.status IN ('confirmed','pending_review') ORDER BY m.created_at ASC,m.id ASC LIMIT 240"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
