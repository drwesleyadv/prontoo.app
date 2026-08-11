<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AdminPagesSqlCatalog06
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.admin_pages.06.page_admin_painel.01' => (
                "INSERT INTO pi_financial_goals (clinic_id,month_key,target_cents,base_metric,share_with_team,updated_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_cents=VALUES(target_cents), base_metric=VALUES(base_metric), share_with_team=VALUES(share_with_team), updated_by=VALUES(updated_by), updated_at=NOW()"
            ),
            'operational.admin_pages.06.page_admin_painel.02' => (
                "SELECT sp.*,c.display_name FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.id=? AND sp.status='pending_admin'"
            ),
            'operational.admin_pages.06.page_admin_painel.03' => (
                "UPDATE pi_subscription_payments SET status='confirmed', reviewed_by=?, reviewed_at=NOW(), review_note=?, proof_path=NULL WHERE id=? AND clinic_id=?"
            ),
            'operational.admin_pages.06.page_admin_painel.04' => (
                "UPDATE pi_clinics SET active=1, subscription_status='active', paid_until=COALESCE(?,paid_until), subscription_trust_blocked_until=NULL, subscription_last_payment_claim_at=NULL, updated_at=NOW() WHERE id=?"
            ),
            'operational.admin_pages.06.page_admin_painel.05' => (
                "UPDATE pi_subscription_payments SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=? AND clinic_id=?"
            ),
            'operational.admin_pages.06.page_admin_painel.06' => (
                "UPDATE pi_clinics SET subscription_status='read_only', paid_until=CURDATE(), subscription_trust_blocked_until=?, updated_at=NOW() WHERE id=?"
            ),
            'operational.admin_pages.06.page_admin_painel.07' => (
                OperationalDynamicSqlCatalog::statement($query, $bindings)
            ),
            'operational.admin_pages.06.page_admin_painel.08' => (
                "SELECT sp.id,sp.clinic_id,sp.amount_cents,sp.account_self,sp.account_holder_name,sp.proof_path,sp.applied_until,sp.created_at,c.display_name,(SELECT COUNT(*) FROM pi_subscription_payments spr WHERE spr.clinic_id=sp.clinic_id AND spr.status='rejected') AS rejected_count FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.status='pending_admin' " .
                                ModelClinicQuerySql::exclude('sp.clinic_id') .
                                " ORDER BY sp.created_at ASC LIMIT 20"
            ),
            'operational.admin_pages.06.page_admin_people.01' => (
                "SELECT u.id,u.name,u.email,u.active,u.is_global_admin,p.cpf,p.birth_date,COUNT(ur.id) AS vinculos FROM pi_users u JOIN pi_persons p ON p.id=u.person_id LEFT JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.active=1 GROUP BY u.id,u.name,u.email,u.active,u.is_global_admin,p.cpf,p.birth_date ORDER BY u.name ASC LIMIT 200"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
