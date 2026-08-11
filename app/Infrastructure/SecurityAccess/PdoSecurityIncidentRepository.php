<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SecurityAccess;

use Prontoo\Application\SecurityAccess\SecurityIncidentPort;

final class PdoSecurityIncidentRepository implements SecurityIncidentPort
{
    public function recordScopeViolation(
        int $clinicId,
        ?int $userId,
        string $roleCode,
        string $route,
        string $violationKey,
        string $fingerprint,
        string $details,
    ): void {
        $sql = 'INSERT INTO pi_scope_violations (clinic_id,user_id,role_code,route,violation_key,sql_fingerprint,details,created_at) VALUES (?,?,?,?,?,?,?,NOW())';
        $parameters = [
            $clinicId,
            $userId,
            $roleCode,
            $route,
            $violationKey,
            $fingerprint,
            $details,
        ];
        if (class_exists('\Prontoo\Infrastructure\Integrity\PiIntegrity')) {
            [$sql, $parameters] = \Prontoo\Infrastructure\Integrity\PiIntegrity::prepareRuntimeQuery(
                $sql,
                $parameters,
            );
        }
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($parameters);
    }

    public function scopedEntityReadShape(string $table, string $columns): string
    {
        $table = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::allowed_db_table($table);
        $columns = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::safe_db_columns($columns);
        return "SELECT {$columns} FROM {$table} WHERE id=? AND clinic_id=?";
    }
}
