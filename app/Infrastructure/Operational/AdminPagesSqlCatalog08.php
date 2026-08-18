<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AdminPagesSqlCatalog08
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.admin_pages.08.page_admin_clinics.01' => (
                "SELECT id,active,monthly_price_cents FROM pi_clinics WHERE id=?"
            ),
            'operational.admin_pages.08.page_admin_clinics.02' => (
                "UPDATE pi_clinics SET active=1, subscription_status='active', paid_until=DATE_ADD(CURDATE(), INTERVAL 30 DAY), monthly_price_cents=?, updated_at=NOW() WHERE id=?"
            ),
            'operational.admin_pages.08.page_admin_clinics.03' => (
                "UPDATE pi_clinics SET subscription_status='read_only', paid_until=CURDATE(), updated_at=NOW() WHERE id=?"
            ),
            'operational.admin_pages.08.page_admin_clinics.04' => (
                "UPDATE pi_clinics SET subscription_status=?, paid_until=?, monthly_price_cents=?, updated_at=NOW() WHERE id=?"
            ),
            'operational.admin_pages.08.page_admin_clinics.05' => (
                "UPDATE pi_clinics SET active=?,updated_at=NOW() WHERE id=?"
            ),
            'operational.admin_pages.08.page_admin_clinics.06' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE 1=1 " . ModelClinicQuerySql::exclude('id')
            ),
            'operational.admin_pages.08.page_admin_clinics.07' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 " . ModelClinicQuerySql::exclude('id')
            ),
            'operational.admin_pages.08.page_admin_clinics.08' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status='exempt' " . ModelClinicQuerySql::exclude('id')
            ),
            'operational.admin_pages.08.page_admin_clinics.09' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status<>'exempt' AND (subscription_status='read_only' OR (subscription_status<>'active' AND (paid_until IS NULL OR paid_until<CURDATE()) AND (trial_ends_at IS NULL OR trial_ends_at<NOW()))) " . ModelClinicQuerySql::exclude('id')
            ),
            'operational.admin_pages.08.page_admin_clinics.10' => (
                "SELECT c.id,c.display_name,c.responsible_profession,c.active,c.onboarding_done,c.owner_user_id,c.manager_user_id,c.created_at,c.trial_started_at,c.trial_ends_at,c.subscription_status,c.paid_until,c.monthly_price_cents,ll.last_collaborator_login_at FROM pi_clinics c LEFT JOIN (SELECT ur.clinic_id,MAX(u.last_login_at) AS last_collaborator_login_at FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.active=1 GROUP BY ur.clinic_id) ll ON ll.clinic_id=c.id ORDER BY ll.last_collaborator_login_at IS NULL ASC,ll.last_collaborator_login_at DESC,c.id DESC LIMIT 120"
            ),
            'operational.admin_pages.08.page_admin_clinics.11' => (
                "SELECT sp.*,c.display_name FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.id=? AND sp.status='pending_admin'"
            ),
            'operational.admin_pages.08.page_admin_clinics.12' => (
                "UPDATE pi_subscription_payments SET status='confirmed', reviewed_by=?, reviewed_at=NOW(), review_note=?, proof_path=NULL WHERE id=? AND clinic_id=?"
            ),
            'operational.admin_pages.08.page_admin_clinics.13' => (
                "UPDATE pi_clinics SET active=1, subscription_status='active', paid_until=COALESCE(?,paid_until), subscription_trust_blocked_until=NULL, subscription_last_payment_claim_at=NULL, updated_at=NOW() WHERE id=?"
            ),
            'operational.admin_pages.08.page_admin_clinics.14' => (
                "UPDATE pi_subscription_payments SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=? AND clinic_id=?"
            ),
            'operational.admin_pages.08.page_admin_clinics.15' => (
                "UPDATE pi_clinics SET subscription_status='read_only', paid_until=CURDATE(), subscription_trust_blocked_until=?, updated_at=NOW() WHERE id=?"
            ),
            'operational.admin_pages.08.page_admin_clinics.16' => (
                "SELECT sp.id,sp.clinic_id,sp.amount_cents,sp.account_self,sp.account_holder_name,sp.proof_path,sp.applied_until,sp.created_at,c.display_name,(SELECT COUNT(*) FROM pi_subscription_payments spr WHERE spr.clinic_id=sp.clinic_id AND spr.status='rejected') AS rejected_count FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.status='pending_admin' " .
                                ModelClinicQuerySql::exclude('sp.clinic_id') .
                                " ORDER BY sp.created_at ASC LIMIT 20"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
