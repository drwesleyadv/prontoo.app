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
                "SELECT COUNT(*) FROM pi_clinics WHERE 1=1 " . \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_exclude_sql('id')
            ),
            'operational.admin_pages.08.page_admin_clinics.07' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 " . \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_exclude_sql('id')
            ),
            'operational.admin_pages.08.page_admin_clinics.08' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status='exempt' " . \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_exclude_sql('id')
            ),
            'operational.admin_pages.08.page_admin_clinics.09' => (
                "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status<>'exempt' AND (subscription_status='read_only' OR (subscription_status<>'active' AND (paid_until IS NULL OR paid_until<CURDATE()) AND (trial_ends_at IS NULL OR trial_ends_at<NOW()))) " . \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_exclude_sql('id')
            ),
            'operational.admin_pages.08.page_admin_clinics.10' => (
                "SELECT id,display_name,responsible_profession,active,onboarding_done,owner_user_id,manager_user_id,created_at,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents FROM pi_clinics ORDER BY CASE WHEN active=0 THEN 4 WHEN subscription_status='exempt' THEN 3 WHEN subscription_status='read_only' OR (subscription_status<>'active' AND (paid_until IS NULL OR paid_until<CURDATE()) AND (trial_ends_at IS NULL OR trial_ends_at<NOW())) THEN 0 ELSE 2 END ASC, updated_at DESC, id DESC LIMIT 120"
            ),
            'operational.admin_pages.08.page_admin_security.01' => (
                "DELETE FROM pi_login_locks WHERE id=?"
            ),
            'operational.admin_pages.08.page_admin_security.02' => (
                "SELECT COUNT(*) FROM pi_login_locks WHERE locked_until>NOW()"
            ),
            'operational.admin_pages.08.page_admin_security.03' => (
                "SELECT id,fail_count,locked_until FROM pi_login_locks WHERE locked_until>NOW() ORDER BY id DESC LIMIT 30"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
