<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class TasksNoticesSqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.tasks_notices.03.page_admin_global_notices.01' => (
                "UPDATE pi_global_notices SET active=1-active, updated_at=NOW() WHERE id=?"
            ),
            'operational.tasks_notices.03.page_admin_global_notices.02' => (
                "INSERT INTO pi_global_notices (title,body,severity,starts_at,expires_at,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())"
            ),
            'operational.tasks_notices.03.page_admin_global_notices.03' => (
                "SELECT id,title,body,severity,active,starts_at,expires_at,created_by,created_at FROM pi_global_notices ORDER BY created_at DESC LIMIT 100"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
