<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Relation;

use Prontoo\Core\Invariant\Canonical;
use Prontoo\Core\Invariant\SqlExpression;
use Prontoo\Core\Tenant\TenantRegistry;

final class ForeignKeyGraph
{
    private static array $relations = [];

    private function __construct() {}

    public static function assertWrite(
        string $table,
        string $operation,
        string $sql,
        array $params,
        ?array $insert,
        int $clinicId,
    ): array {
        if ($clinicId <= 0 || !TenantRegistry::isScoped($table)) {
            return ["checked" => false, "edges" => 0, "proofs" => []];
        }
        $relations = self::relationsFor($table);
        if ($relations === []) {
            return ["checked" => true, "edges" => 0, "proofs" => []];
        }
        $assignments = $operation === "UPDATE" ? SqlExpression::assignments($sql) : [];
        $proofs = [];
        foreach ($relations as $relation) {
            $sourceColumn = (string) $relation["source_column"];
            $targetTable = (string) $relation["target_table"];
            $targetColumn = (string) $relation["target_column"];
            if ($sourceColumn === "clinic_id" || !TenantRegistry::isScoped($targetTable)) {
                continue;
            }
            $values = [];
            $complete = true;
            if (in_array($operation, ["INSERT", "REPLACE"], true)) {
                if (!is_array($insert)) {
                    continue;
                }
                $values = SqlExpression::insertColumnValues(
                    $sql,
                    $params,
                    $insert,
                    $sourceColumn,
                    $complete,
                );
                if ($values === []) {
                    continue;
                }
            } elseif ($operation === "UPDATE") {
                if (!isset($assignments[$sourceColumn])) {
                    continue;
                }
                $assignment = $assignments[$sourceColumn];
                if (empty($assignment["known_direct"])) {
                    self::deny(
                        "relation_value_unproved",
                        $sql,
                        "A relação {$table}.{$sourceColumn} foi alterada por expressão não demonstrável.",
                    );
                }
                $known = false;
                $values = [SqlExpression::tokenValue(
                    $sql,
                    (string) $assignment["token"],
                    (int) $assignment["offset"],
                    $params,
                    $known,
                )];
                $complete = $known;
            } else {
                continue;
            }
            if (!$complete) {
                self::deny(
                    "relation_value_unproved",
                    $sql,
                    "O valor da relação {$table}.{$sourceColumn} não pôde ser demonstrado.",
                );
            }
            foreach (array_values(array_unique($values, SORT_REGULAR)) as $value) {
                if ($value === null || $value === "" || (is_numeric($value) && (int) $value <= 0)) {
                    continue;
                }
                $targetClinic = self::targetClinicId(
                    $targetTable,
                    $targetColumn,
                    $value,
                    $clinicId,
                );
                if ($targetClinic === null) {
                    self::deny(
                        "relation_target_missing",
                        $sql,
                        "A relação {$table}.{$sourceColumn} aponta para registro inexistente em {$targetTable}.",
                    );
                }
                if ($targetClinic !== $clinicId) {
                    self::deny(
                        "relation_crosses_clinic",
                        $sql,
                        "A relação {$table}.{$sourceColumn} atravessaria o consultório ativo.",
                    );
                }
                $proofs[] = Canonical::hash("relation_edge", [
                    "source_table" => $table,
                    "source_column" => $sourceColumn,
                    "target_table" => $targetTable,
                    "target_column" => $targetColumn,
                    "target_value_hash" => hash("sha256", (string) $value),
                    "clinic_id" => $clinicId,
                ]);
            }
        }
        return [
            "checked" => true,
            "edges" => count($proofs),
            "proofs" => $proofs,
        ];
    }

    private static function relationsFor(string $table): array
    {
        $table = self::identifier($table);
        if ($table === "") {
            return [];
        }
        if (array_key_exists($table, self::$relations)) {
            return self::$relations[$table];
        }
        if (!function_exists("pdo")) {
            return self::$relations[$table] = [];
        }
        $loader = static fn(): array => self::loadRelations($table);
        $revision = defined("PRONTOO_SCHEMA_REV")
            ? (string) PRONTOO_SCHEMA_REV
            : "schema";
        if (function_exists("cache_remember")) {
            try {
                $cached = \cache_remember(
                    "invariant_fk_" . hash("sha256", $revision . "|" . $table),
                    86400,
                    $loader,
                );
                if (is_array($cached)) {
                    return self::$relations[$table] = $cached;
                }
            } catch (\Throwable $error) {
                error_log(
                    "[Prontoo invariant relation cache] " . $error->getMessage(),
                );
            }
        }
        return self::$relations[$table] = $loader();
    }

    private static function loadRelations(string $table): array
    {
        try {
            $statement = \pdo()->prepare(
                "SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME " .
                    "FROM information_schema.KEY_COLUMN_USAGE " .
                    "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? " .
                    "AND REFERENCED_TABLE_NAME IS NOT NULL " .
                    "ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION",
            );
            $statement->execute([$table]);
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $out = [];
            foreach ($rows as $row) {
                $sourceColumn = self::identifier((string) ($row["COLUMN_NAME"] ?? ""));
                $targetTable = self::identifier((string) ($row["REFERENCED_TABLE_NAME"] ?? ""));
                $targetColumn = self::identifier((string) ($row["REFERENCED_COLUMN_NAME"] ?? ""));
                if ($sourceColumn === "" || $targetTable === "" || $targetColumn === "") {
                    continue;
                }
                $out[] = [
                    "constraint" => (string) ($row["CONSTRAINT_NAME"] ?? ""),
                    "source_column" => $sourceColumn,
                    "target_table" => $targetTable,
                    "target_column" => $targetColumn,
                ];
            }
            return $out;
        } catch (\Throwable $error) {
            error_log(
                "[Prontoo invariant relation graph] metadados indisponíveis para {$table}: " .
                    $error->getMessage(),
            );
            return [];
        }
    }

    private static function targetClinicId(
        string $table,
        string $column,
        mixed $value,
        int $clinicId,
    ): ?int {
        $table = self::identifier($table);
        $column = self::identifier($column);
        $scopeColumn = TenantRegistry::scopeColumn($table);
        $scopeColumn = is_string($scopeColumn) ? self::identifier($scopeColumn) : "";
        if ($table === "" || $column === "" || $scopeColumn === "") {
            return null;
        }
        $statement = \pdo()->prepare(
            "SELECT `{$scopeColumn}` FROM `{$table}` WHERE `{$column}`=? AND `{$scopeColumn}`=? LIMIT 1",
        );
        $statement->execute([$value, $clinicId]);
        $found = $statement->fetchColumn();
        if ($found !== false && $found !== null) {
            return (int) $found;
        }
        $statement = \pdo()->prepare(
            "SELECT `{$scopeColumn}` FROM `{$table}` WHERE `{$column}`=? LIMIT 1",
        );
        $statement->execute([$value]);
        $found = $statement->fetchColumn();
        return $found === false || $found === null ? null : (int) $found;
    }

    private static function identifier(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^[a-z0-9_]+$/', $value) ? $value : "";
    }

    private static function deny(string $key, string $sql, string $detail): never
    {
        if (function_exists("record_scope_violation")) {
            \record_scope_violation($key, $sql, $detail);
        } else {
            error_log(
                "[Prontoo invariant relation violation] {$key} | " .
                    hash("sha256", preg_replace('/\s+/', ' ', trim($sql)) ?? $sql),
            );
        }
        throw new \ProntooHttpError(
            500,
            "Proteção de integridade: a relação entre os registros não pertence integralmente ao consultório ativo.",
        );
    }
}
