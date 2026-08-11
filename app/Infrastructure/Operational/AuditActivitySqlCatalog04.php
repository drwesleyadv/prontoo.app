<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AuditActivitySqlCatalog04
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.audit_activity.04.audit.01' => (
                "INSERT INTO pi_audit (clinic_id,user_id,event_key,event_label,event_icon,entity_key,entity_label,entity_id,friendly_text,context_json,integrity_hash,previous_hash,chain_hash,proof_hash,proof_json,policy_version,ip_hash,user_agent,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,COALESCE(?,NOW()))"
            ),
            'operational.audit_activity.04.audit_team_filter_options.01' => (
                "SELECT DISTINCT u.id,u.name FROM pi_users u INNER JOIN pi_user_roles ur ON ur.user_id=u.id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1 ORDER BY u.name ASC"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
