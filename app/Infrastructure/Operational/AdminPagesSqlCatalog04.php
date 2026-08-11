<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AdminPagesSqlCatalog04
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.admin_pages.04.page_admin_diagnostics.01' => (
                "SELECT 1"
            ),
            'operational.admin_pages.04.page_admin_errors.01' => (
                "UPDATE pi_error_events SET resolved_at=NOW(), notes=? WHERE id=? AND resolved_at IS NULL"
            ),
            'operational.admin_pages.04.page_admin_errors.02' => (
                "SELECT COUNT(*) FROM pi_error_events WHERE resolved_at IS NULL"
            ),
            'operational.admin_pages.04.page_admin_errors.03' => (
                "SELECT id,route,method,http_status,message,file,line,user_id,clinic_id,notes,resolved_at,created_at FROM pi_error_events WHERE resolved_at IS NULL ORDER BY id DESC LIMIT 80"
            ),
            'operational.admin_pages.04.page_admin_errors.04' => (
                "SELECT id,route,method,http_status,message,file,line,user_id,clinic_id,notes,resolved_at,created_at FROM pi_error_events WHERE resolved_at IS NOT NULL ORDER BY id DESC LIMIT " .
                                        (120 - count($rows))
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
