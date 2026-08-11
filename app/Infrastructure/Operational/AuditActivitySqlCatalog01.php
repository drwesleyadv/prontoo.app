<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AuditActivitySqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.audit_activity.01.audit_chain_integrity_status.01' => (
                AuditActivityQuerySql::select() . " ORDER BY a.id DESC LIMIT " . $limit
            ),
            'operational.audit_activity.01.fetch_map.01' => (
                AuditLookupSql::statement($projection, true, (int) $itemCount)
            ),
            'operational.audit_activity.01.fetch_map.02' => (
                AuditLookupSql::statement($projection, false, (int) $itemCount)
            ),
            'operational.audit_activity.01.scoped_patient_map.01' => (
                "SELECT id,person_id FROM pi_patients WHERE clinic_id=? AND id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ')'
            ),
            'operational.audit_activity.01.scoped_user_map.01' => (
                "SELECT id,name FROM pi_users u WHERE u.id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ") AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) "
            ),
            'operational.audit_activity.01.audit_rows_light.01' => (
                AuditActivityQuerySql::select() .
                                    " WHERE " .
                                    AuditActivityQuerySql::where($criteria) .
                                    " AND a.event_key NOT IN ('login_clinica_pendente','login_credencial_pendente') ORDER BY a.id DESC LIMIT $limit OFFSET $offset"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
