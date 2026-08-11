<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class MaestroSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.maestro.01.maestro_runtime_access_marker_ready.01' => (
                "SELECT meta_value FROM pi_meta WHERE meta_key='schema_maestro_runtime_access_v2' LIMIT 1"
            ),
            'operational.maestro.01.maestro_grant_runtime_access.01' => (
                "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) SELECT id,'gerente','maestro',1 FROM pi_clinics WHERE active=1 ON DUPLICATE KEY UPDATE allowed=1"
            ),
            'operational.maestro.01.maestro_grant_runtime_access.02' => (
                "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) SELECT id,'gerente','operations',1 FROM pi_clinics WHERE active=1 ON DUPLICATE KEY UPDATE allowed=1"
            ),
            'operational.maestro.01.maestro_grant_runtime_access.03' => (
                "INSERT INTO pi_permission_rules (clinic_id,role_code,action_key,operation_key,allowed) SELECT id,'gerente','maestro','" .
                                                $op .
                                                "',1 FROM pi_clinics WHERE active=1 ON DUPLICATE KEY UPDATE allowed=1"
            ),
            'operational.maestro.01.maestro_grant_runtime_access.04' => (
                "INSERT INTO pi_meta (meta_key, meta_value) VALUES ('schema_maestro_runtime_access_v2','1') ON DUPLICATE KEY UPDATE meta_value='1', updated_at=NOW()"
            ),
            'operational.maestro.01.maestro_save_rule.01' => (
                "UPDATE pi_maestro_rules SET name=?,active=?,trigger_module=?,trigger_event=?,condition_json=?,action_type=?,action_json=?,priority=?,min_interval_minutes=?,next_run_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.maestro.01.maestro_save_rule.02' => (
                "INSERT INTO pi_maestro_rules (clinic_id,name,active,trigger_module,trigger_event,condition_json,action_type,action_json,priority,min_interval_minutes,next_run_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW())"
            ),
            'operational.maestro.01.maestro_target_label.01' => (
                "SELECT u.name FROM pi_users u WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
