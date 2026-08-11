<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class SupportFoundationSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.support_foundation.01.app_global_admin_timezone.01' => (
                "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1"
            ),
            'operational.support_foundation.01.app_global_admin_timezone.02' => (
                "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1"
            ),
            'operational.support_foundation.01.app_context_timezone.01' => (
                "SELECT timezone FROM pi_clinics WHERE id=? LIMIT 1"
            ),
            'operational.support_foundation.01.patient_display_name.01' => (
                "SELECT p.full_name FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=?" .
                                (!empty($tenantScoped) ? " AND pp.clinic_id=?" : "") .
                                " LIMIT 1"
            ),
            'operational.support_foundation.01.cached_val.01' => (
                OperationalDynamicSqlCatalog::statement($query, $bindings)
            ),
            'operational.support_foundation.01.cached_val.02' => (
                OperationalDynamicSqlCatalog::statement($query, $bindings)
            ),
            'operational.support_foundation.01.cached_val.03' => (
                OperationalDynamicSqlCatalog::statement($query, $bindings)
            ),
            'operational.support_foundation.01.counter_inc.01' => (
                "INSERT INTO pi_platform_counters (counter_key,counter_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE counter_value=counter_value+VALUES(counter_value), updated_at=NOW()"
            ),
            'operational.support_foundation.01.counter_get.01' => (
                "SELECT counter_value FROM pi_platform_counters WHERE counter_key=?"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
