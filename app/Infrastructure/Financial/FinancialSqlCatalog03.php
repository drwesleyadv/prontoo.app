<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.03.recent_operations_timeline.01" => (
                "SELECT kind,title,amount_cents,happened_at,account_name,extra_name FROM (
                     SELECT 'revenue' kind, r.title title, r.amount_cents amount_cents, r.received_at happened_at, COALESCE(a.name,'sem conta') account_name, NULL extra_name
                     FROM pi_financial_revenues r LEFT JOIN pi_financial_accounts a ON a.id=r.account_id AND a.clinic_id=r.clinic_id
                     WHERE r.clinic_id=? AND r.status='efetivada' AND r.received_at IS NOT NULL AND r.received_at>=?
                     UNION ALL
                     SELECT 'expense' kind, e.title title, e.amount_cents amount_cents, e.paid_at happened_at, COALESCE(a.name,'sem conta') account_name, COALESCE(p.full_name,'sem credor') extra_name
                     FROM pi_financial_expenses e LEFT JOIN pi_financial_accounts a ON a.id=e.account_id AND a.clinic_id=e.clinic_id LEFT JOIN pi_financial_counterparties fc ON fc.id=e.counterparty_id AND fc.clinic_id=e.clinic_id LEFT JOIN pi_persons p ON p.id=fc.person_id
                     WHERE e.clinic_id=? AND e.status='paga' AND e.paid_at IS NOT NULL AND e.paid_at>=?
                     UNION ALL
                     SELECT 'transfer' kind, 'Transferência entre contas' title, t.amount_cents amount_cents, t.transfer_at happened_at, COALESCE(af.name,'origem') account_name, COALESCE(atc.name,'destino') extra_name
                     FROM pi_financial_transfers t LEFT JOIN pi_financial_accounts af ON af.id=t.account_from_id AND af.clinic_id=t.clinic_id LEFT JOIN pi_financial_accounts atc ON atc.id=t.account_to_id AND atc.clinic_id=t.clinic_id
                     WHERE t.clinic_id=? AND t.transfer_at>=?
                     ) ops ORDER BY happened_at DESC LIMIT 20"
            ),
            "financial.03.ensure_admin_safe.01" => (
                "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='admin_safe' AND active=1 ORDER BY id ASC LIMIT 1"
            ),
            "financial.03.ensure_admin_safe.02" => (
                "INSERT INTO pi_financial_locations (clinic_id,location_type,name,user_id,account_id,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())"
            ),
            "financial.03.ensure_bank_location.01" => (
                "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='bank_account' AND account_id=? AND active=1 ORDER BY id ASC LIMIT 1"
            ),
            "financial.03.ensure_bank_location.02" => (
                "SELECT id,name FROM pi_financial_accounts WHERE id=? AND clinic_id=? AND active=1 LIMIT 1"
            ),
            "financial.03.ensure_bank_location.03" => (
                "INSERT INTO pi_financial_locations (clinic_id,location_type,name,user_id,account_id,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())"
            ),
            "financial.03.cashier_user_options.01" => (
                "SELECT DISTINCT u.id,u.name FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.clinic_id=? AND ur.active=1 AND ur.role_code IN ('recepcionista') ORDER BY u.name LIMIT 300"
            ),
            "financial.03.drawer_location_options.01" => (
                "SELECT id,name FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND active=1 ORDER BY name,id LIMIT 200"
            ),
            "financial.03.cashier_assigned_locations.01" => (
                "SELECT l.id,l.name FROM pi_financial_locations l JOIN pi_financial_location_users lu ON lu.location_id=l.id AND lu.clinic_id=l.clinic_id AND lu.user_id=? AND lu.active=1 WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,l.id LIMIT 20"
            ),
            "financial.03.cashier_assigned_locations.02" => (
                "SELECT id,name FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND user_id=? AND active=1 ORDER BY id ASC LIMIT 20"
            ),
            "financial.03.create_drawer.01" => (
                "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND name=? AND active=1 LIMIT 1"
            ),
            "financial.03.create_drawer.02" => (
                "INSERT INTO pi_financial_locations (clinic_id,location_type,name,user_id,account_id,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())"
            ),
            "financial.03.rename_drawer.01" => (
                "SELECT id,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND location_type='pos' AND active=1 LIMIT 1"
            ),
            "financial.03.rename_drawer.02" => (
                "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND active=1 AND name=? AND id<>? LIMIT 1"
            ),
            "financial.03.rename_drawer.03" => (
                "UPDATE pi_financial_locations SET name=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'"
            ),
            "financial.03.link_drawer_user.01" => (
                "SELECT id,location_type,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1"
            ),
            "financial.03.link_drawer_user.02" => (
                "UPDATE pi_financial_location_users SET active=0, updated_at=NOW() WHERE clinic_id=? AND user_id=? AND active=1"
            ),
            "financial.03.link_drawer_user.03" => (
                "INSERT INTO pi_financial_location_users (clinic_id,location_id,user_id,active,created_by,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE active=1, updated_at=NOW(), created_by=VALUES(created_by)"
            ),
            "financial.03.unlink_drawer_user.01" => (
                "SELECT * FROM pi_financial_location_users WHERE id=? AND clinic_id=? AND active=1 LIMIT 1"
            ),
            "financial.03.unlink_drawer_user.02" => (
                "UPDATE pi_financial_location_users SET active=0, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            "financial.03.deactivate_drawer.01" => (
                "SELECT s.id,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id=? AND s.status='open' LIMIT 1"
            ),
            "financial.03.deactivate_drawer.02" => (
                "UPDATE pi_financial_locations SET active=0, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'"
            ),
            "financial.03.deactivate_drawer.03" => (
                "UPDATE pi_financial_location_users SET active=0, updated_at=NOW() WHERE clinic_id=? AND location_id=?"
            ),
            "financial.03.drawer_row.01" => (
                "SELECT * FROM pi_financial_locations WHERE id=? AND clinic_id=? AND location_type='pos' AND active=1 LIMIT 1"
            ),
            "financial.03.drawer_auto_unlock_row_if_due.01" => (
                "UPDATE pi_financial_locations SET drawer_lock_status='unlocked', drawer_unlocked_at=NOW(), updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'"
            ),
            "financial.03.notify_drawer_locked.01" => (
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
