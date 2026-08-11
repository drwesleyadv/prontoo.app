<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog14
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.14.admin_daily_conference_panel.01" => (
                "SELECT COALESCE(SUM(ABS(difference_cents)),0) FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND difference_cents<>0"
            ),
            "financial.14.admin_daily_consolidate.01" => (
                "SELECT id FROM pi_clinics WHERE id=? FOR UPDATE"
            ),
            "financial.14.admin_daily_consolidate.02" => (
                "SELECT id FROM pi_financial_daily_closings WHERE clinic_id=? AND business_date=? FOR UPDATE"
            ),
            "financial.14.admin_daily_consolidate.03" => (
                "INSERT INTO pi_financial_daily_closings (clinic_id,business_date,status,expected_cents,received_cents,pending_cents,drawer_cents,safe_cents,bank_cents,movement_count,closed_drawer_count,notes,consolidated_by,consolidated_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE status='consolidado', expected_cents=VALUES(expected_cents), received_cents=VALUES(received_cents), pending_cents=VALUES(pending_cents), drawer_cents=VALUES(drawer_cents), safe_cents=VALUES(safe_cents), bank_cents=VALUES(bank_cents), movement_count=VALUES(movement_count), closed_drawer_count=VALUES(closed_drawer_count), notes=VALUES(notes), consolidated_by=VALUES(consolidated_by), consolidated_at=NOW(), updated_at=NOW()"
            ),
            "financial.14.admin_save_receipt.01" => (
                "INSERT INTO pi_financial_revenues (clinic_id,patient_link_id,title,amount_cents,status,payment_method,account_id,received_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())"
            ),
            "financial.14.admin_save_payment.01" => (
                "SELECT fc.id,p.full_name FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.id=? AND fc.clinic_id=? AND fc.kind='credor' AND fc.active=1 LIMIT 1"
            ),
            "financial.14.admin_save_payment.02" => (
                "INSERT INTO pi_financial_expenses (clinic_id,counterparty_id,account_id,title,expense_category,amount_cents,status,due_at,paid_at,payment_method,notes,created_by,created_at) VALUES (?,?,?,?,?,?,'paga',CURDATE(),NOW(),?,?,?,NOW())"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
