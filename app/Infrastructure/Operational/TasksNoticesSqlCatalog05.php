<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class TasksNoticesSqlCatalog05
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.tasks_notices.05.readonly_support_notice_screen.01' => (
                "SELECT id,title,body,severity,read_at,created_at FROM pi_admin_alerts WHERE sender_user_id=? AND sender_clinic_id=? AND source_scope='clinic_readonly' ORDER BY id DESC LIMIT 80"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
