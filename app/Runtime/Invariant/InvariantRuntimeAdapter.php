<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Invariant;

use PDO;
use Prontoo\Core\Invariant\InvariantRuntimePort;
use Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01;
use Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01;
use Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03;

final class InvariantRuntimeAdapter implements InvariantRuntimePort
{
    public function clinicReadOnly(int $clinicId): bool
    {
        return ClinicConfigRuntimeOperations01::clinic_read_only_db($clinicId);
    }

    public function recordScopeViolation(string $key, string $sql, string $detail): void
    {
        SecurityAccessRuntimeOperations03::record_scope_violation($key, $sql, $detail);
    }

    public function foreignKeyRelations(string $table): array
    {
        $statement = DatabaseSchemaInfrastructureOperations01::pdo()->prepare(
            "SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME " .
            "FROM information_schema.KEY_COLUMN_USAGE " .
            "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? " .
            "AND REFERENCED_TABLE_NAME IS NOT NULL " .
            "ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION",
        );
        $statement->execute([$table]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $sourceColumn = self::identifier((string) ($row['COLUMN_NAME'] ?? ''));
            $targetTable = self::identifier((string) ($row['REFERENCED_TABLE_NAME'] ?? ''));
            $targetColumn = self::identifier((string) ($row['REFERENCED_COLUMN_NAME'] ?? ''));
            if ($sourceColumn === '' || $targetTable === '' || $targetColumn === '') {
                continue;
            }
            $out[] = [
                'constraint' => (string) ($row['CONSTRAINT_NAME'] ?? ''),
                'source_column' => $sourceColumn,
                'target_table' => $targetTable,
                'target_column' => $targetColumn,
            ];
        }
        return $out;
    }

    public function foreignKeyTargetClinicId(
        string $table,
        string $column,
        mixed $value,
        string $scopeColumn,
        int $clinicId,
    ): ?int {
        $table = self::identifier($table);
        $column = self::identifier($column);
        $scopeColumn = self::identifier($scopeColumn);
        if ($table === '' || $column === '' || $scopeColumn === '') {
            return null;
        }
        $pdo = DatabaseSchemaInfrastructureOperations01::pdo();
        $statement = $pdo->prepare(
            "SELECT `{$scopeColumn}` FROM `{$table}` WHERE `{$column}`=? AND `{$scopeColumn}`=? LIMIT 1",
        );
        $statement->execute([$value, $clinicId]);
        $found = $statement->fetchColumn();
        if ($found !== false && $found !== null) {
            return (int) $found;
        }
        $statement = $pdo->prepare(
            "SELECT `{$scopeColumn}` FROM `{$table}` WHERE `{$column}`=? LIMIT 1",
        );
        $statement->execute([$value]);
        $found = $statement->fetchColumn();
        return $found === false || $found === null ? null : (int) $found;
    }

    public function appointmentStatuses(int $clinicId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($clinicId <= 0 || $ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = DatabaseSchemaInfrastructureOperations01::pdo()->prepare(
            "SELECT id,status FROM pi_appointments WHERE clinic_id=? AND id IN ({$placeholders})",
        );
        $statement->execute(array_merge([$clinicId], $ids));
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function identifier(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^[a-z0-9_]+$/', $value) ? $value : '';
    }
}
