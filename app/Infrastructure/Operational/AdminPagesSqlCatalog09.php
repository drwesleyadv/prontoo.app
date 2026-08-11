<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AdminPagesSqlCatalog09
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.admin_pages.09.admin_alerts_admin_users.01' => (
                "SELECT id,name,email FROM pi_users WHERE is_global_admin=1 AND active=1 ORDER BY name ASC,id ASC LIMIT 300"
            ),
            'operational.admin_pages.09.page_admin_alerts.01' => (
                "UPDATE pi_admin_alerts SET read_at=COALESCE(read_at,NOW()), read_by=COALESCE(read_by,?) WHERE id=? AND recipient_user_id=?"
            ),
            'operational.admin_pages.09.page_admin_alerts.02' => (
                "INSERT INTO pi_admin_alerts (sender_user_id,recipient_user_id,title,body,severity,created_at) VALUES (?,?,?,?,?,NOW())"
            ),
            'operational.admin_pages.09.page_admin_alerts.03' => (
                "SELECT id,sender_user_id,sender_clinic_id,source_scope,recipient_user_id,title,severity,read_at,read_by,created_at FROM pi_admin_alerts WHERE sender_user_id=? OR recipient_user_id=? OR recipient_user_id IS NULL ORDER BY id DESC LIMIT 180"
            ),
            'operational.admin_pages.09.page_admin_alerts.04' => (
                "SELECT body FROM pi_admin_alerts WHERE id=? AND (sender_user_id=? OR recipient_user_id=? OR recipient_user_id IS NULL) LIMIT 1"
            ),
            'operational.admin_pages.09.page_admin_alerts.05' => (
                "UPDATE pi_admin_alerts SET read_at=NOW(), read_by=? WHERE id=? AND recipient_user_id=?"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
