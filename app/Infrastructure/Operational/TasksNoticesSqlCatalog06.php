<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class TasksNoticesSqlCatalog06
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.tasks_notices.06.page_notices.01' => (
                "INSERT INTO pi_admin_alerts (sender_user_id,sender_clinic_id,source_scope,recipient_user_id,title,body,severity,created_at) VALUES (?,?,?,?,?,?,?,NOW())"
            ),
            'operational.tasks_notices.06.page_notices.02' => (
                "SELECT n.id,n.created_by,n.requires_ack FROM pi_notices n WHERE n.id=? AND n.clinic_id=? AND " . NoticeQuerySql::targetOrCreator('n')
            ),
            'operational.tasks_notices.06.page_notices.03' => (
                "INSERT INTO pi_notice_reads (notice_id,user_id,read_at,ack_at) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=COALESCE(read_at,NOW()), ack_at=COALESCE(ack_at,NOW())"
            ),
            'operational.tasks_notices.06.page_notices.04' => (
                "UPDATE pi_notice_reads SET hidden_at=NULL WHERE notice_id=? AND user_id=?"
            ),
            'operational.tasks_notices.06.page_notices.05' => (
                "INSERT INTO pi_notice_reads (notice_id,user_id,hidden_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE hidden_at=NOW()"
            ),
            'operational.tasks_notices.06.page_notices.06' => (
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.tasks_notices.06.page_notices.07' => (
                "INSERT INTO pi_notice_reads (notice_id,user_id,read_at,ack_at) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=NOW(), ack_at=NOW(), hidden_at=NULL"
            ),
            'operational.tasks_notices.06.page_notices.08' => (
                "SELECT n.id,n.title,n.body,n.requires_ack,n.target_scope,n.target_role,n.target_user_id,n.created_by,n.created_at,r.read_at,r.ack_at,r.hidden_at FROM pi_notices n LEFT JOIN pi_notice_reads r ON r.notice_id=n.id AND r.user_id=? WHERE n.clinic_id=? AND " . NoticeQuerySql::targetOrCreator('n') . " ORDER BY n.id DESC LIMIT 160"
            ),
            'operational.tasks_notices.06.page_notices.10' => (
                "INSERT INTO pi_notice_reads (notice_id,user_id,read_at,ack_at) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=COALESCE(read_at,NOW()), ack_at=COALESCE(ack_at,NOW())"
            ),
            'operational.tasks_notices.06.page_notices.11' => (
                "SELECT id,title,body,severity,created_at FROM pi_global_notices WHERE active=1 AND severity=? AND (starts_at IS NULL OR starts_at<=NOW()) AND (expires_at IS NULL OR expires_at>=NOW()) ORDER BY id DESC LIMIT 8"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
