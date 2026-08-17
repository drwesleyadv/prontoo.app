<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AdminPagesSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        $objectiveKeys = [
            'write_without_clinic_scope',
            'write_without_where',
            'write_without_clinic_where',
            'write_changes_clinic_scope',
            'write_mismatched_clinic_where',
            'insert_without_clinic_column',
            'insert_mismatched_clinic_value',
            'entity_outside_clinic',
            'user_outside_clinic',
        ];
        $quoted = implode(',', array_map(static fn(string $key): string => "'" . $key . "'", $objectiveKeys));
        $modelWhere = ModelClinicQuerySql::exclude('sv.clinic_id');
        return match ($operationId) {
            'operational.admin_pages.01.admin_scope_guard_stats.01' => (
                "SELECT COUNT(*) AS total," .
                                    "SUM(sv.violation_key='write_in_read_only') AS policy_total," .
                                    "SUM(sv.violation_key<>'write_in_read_only') AS actionable_total," .
                                    "SUM(sv.violation_key IN ($quoted)) AS objective_total," .
                                    "COUNT(DISTINCT CASE WHEN sv.violation_key<>'write_in_read_only' THEN CONCAT_WS('|',sv.violation_key,sv.sql_fingerprint,sv.route,sv.clinic_id) END) AS actionable_patterns " .
                                    "FROM pi_scope_violations sv WHERE sv.created_at>=DATE_SUB(NOW(), INTERVAL $hours HOUR) $modelWhere"
            ),
            'operational.admin_pages.01.platform_backend_selftest.01' => (
                "SELECT 1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
