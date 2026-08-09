<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\DatabaseSchema;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class DatabaseSchemaInfrastructureOperations03
{
    private function __construct()
    {
    }

    public static function schema_mark_ready(): void
    
    {
    
        $payload = json_encode(
            [
                "revision" => defined("PRONTOO_SCHEMA_REV")
                    ? PRONTOO_SCHEMA_REV
                    : "",
                "contract" => \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_contract_hash(),
                "generated_at" => gmdate("c"),
            ],
            JSON_UNESCAPED_SLASHES,
        );
        if (
            file_put_contents(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_lock_file(), $payload ?: "{}", LOCK_EX) ===
            false
        ) {
            throw new RuntimeException(
                "Não foi possível registrar o schema instalado.",
            );
        }
        \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_chmod(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_lock_file(), 0640);
    
    }

    public static function schema_validate_complete(): void
    
    {
    
        $expected = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_definition_map();
        $normalize = static  fn(mixed $value): string => strtolower(
            mb_trim((string) $value),
        );
    
        $actualColumns = [];
        $columnStatement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->query(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE()",
        );
        foreach (
            ($columnStatement
                ? $columnStatement->fetchAll(PDO::FETCH_NUM)
                : []) as $row
        ) {
            $table = $normalize($row[0] ?? "");
            $column = $normalize($row[1] ?? "");
            if ($table === "" || $column === "") {
                continue;
            }
            $actualColumns[$table][$column] = true;
        }
    
        $actualObjects = [];
        $objectSql =
            "SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.table_constraints WHERE table_schema=DATABASE() " .
            "UNION ALL SELECT TABLE_NAME, INDEX_NAME FROM information_schema.statistics WHERE table_schema=DATABASE()";
        $objectStatement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->query($objectSql);
        foreach (
            ($objectStatement ? $objectStatement->fetchAll(PDO::FETCH_NUM) : [])
            as $row
        ) {
            $table = $normalize($row[0] ?? "");
            $object = $normalize($row[1] ?? "");
            if ($table === "" || $object === "") {
                continue;
            }
            $actualObjects[$table][$object] = true;
        }
    
        $problems = [];
        $expectedTables = [];
        foreach ($expected as $table => $definition) {
            $tableKey = $normalize($table);
            $expectedTables[$tableKey] = true;
            if (!isset($actualColumns[$tableKey])) {
                $problems[] = $table;
                continue;
            }
            $expectedColumns = [];
            foreach ($definition["columns"] as $column) {
                $columnKey = $normalize($column);
                $expectedColumns[$columnKey] = true;
                if (!isset($actualColumns[$tableKey][$columnKey])) {
                    $problems[] = "{$table}.{$column}";
                }
            }
            foreach ($definition["objects"] as $object) {
                $objectKey = $normalize($object);
                if (!isset($actualObjects[$tableKey][$objectKey])) {
                    $problems[] = "{$table}.{$object}";
                }
            }
            foreach (
                array_diff_key($actualColumns[$tableKey], $expectedColumns)
                as $column => $_
            ) {
                $problems[] = "{$table}.{$column} inesperada";
            }
        }
        foreach (array_keys($actualColumns) as $table) {
            if (!isset($expectedTables[$table])) {
                $problems[] = "{$table} inesperada";
            }
        }
        if ($problems !== []) {
            throw new RuntimeException(
                "Schema incompatível: " .
                    implode(", ", array_slice($problems, 0, 20)),
            );
        }
        if (class_exists("\\Prontoo\\Core\\Temporal\\PiTime")) {
            $temporal = \Prontoo\Infrastructure\Temporal\PdoTemporalIntegrity::countTemporalViolations(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo());
            $count = (int) ($temporal["datetime_columns"] ?? 0) +
                (int) ($temporal["date_columns"] ?? 0) +
                (int) ($temporal["timestamp_columns"] ?? 0);
            if ($count > 0) {
                throw new RuntimeException(
                    "Schema incompatível: foram encontradas colunas temporais nativas; a política vigente exige Unix UTC em BIGINT.",
                );
            }
        }
    
    }

    public static function schema_seed_meta(): void
    
    {
    
        $revision = defined("PRONTOO_SCHEMA_REV")
            ? PRONTOO_SCHEMA_REV
            : "prontoo_1_7_20_6_clean_schema_r7_layer2_ledger";
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare(
            "INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value),updated_at=VALUES(updated_at)",
        );
        foreach (
            [
                "schema_revision" => $revision,
                "schema_contract_hash" => \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_contract_hash(),
                "app_secret" => bin2hex(random_bytes(32)),
            ]
            as $key => $value
        ) {
            $statement->execute([$key, $value, time()]);
        }
    
    }

    public static function schema_cleanup_failed_install(array $tables): void
    
    {
    
            \Prontoo\Core\Database\SchemaMutationLock::assertActive();
    $connection = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
        $connection->exec("SET FOREIGN_KEY_CHECKS=0");
        try {
            foreach (array_reverse($tables) as $table) {
                $connection->exec("DROP TABLE IF EXISTS " . \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_ident($table));
            }
        } finally {
            $connection->exec("SET FOREIGN_KEY_CHECKS=1");
        }
    
    }

    public static function install_fresh_schema(): void
    
    {
    
            \Prontoo\Core\Database\SchemaMutationLock::assertActive();
    $connection = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
        $tableCount = (int) $connection
            ->query(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()",
            )
            ->fetchColumn();
        if ($tableCount !== 0) {
            throw new RuntimeException("A instalação limpa requer banco vazio.");
        }
    
        $GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"] = true;
        $created = [];
        try {
            foreach (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_statements() as $statement) {
                if (
                    !preg_match(
                        "/^CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?/i",
                        $statement,
                        $match,
                    )
                ) {
                    throw new RuntimeException("Tabela inválida no contrato SQL.");
                }
                \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::run_schema_sql($statement);
                $created[] = $match[1];
            }
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::schema_seed_meta();
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::schema_validate_complete();
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::schema_mark_ready();
        } catch (Throwable $error) {
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::schema_cleanup_failed_install($created);
            \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_unlink(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_lock_file());
            throw $error;
        } finally {
            unset($GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"]);
        }
    
    }

    public static function schema_apply_pending_release_migrations(): void
    
    {
    
        return;
    
    }

    public static function db_assert_tables(array $tables, string $domain): void
    
    {
    
        foreach ($tables as $table) {
            if (!\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_table_exists((string) $table)) {
                throw new RuntimeException("Schema {$domain} incompleto.");
            }
        }
    
    }

    public static function ensure_lead_events_schema(): void
    
    {
    
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::db_assert_tables(["pi_leads", "pi_lead_events"], "de interessados");
    
    }

    public static function ensure_financial_operational_schema(): void
    
    {
    
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::db_assert_tables(
            [
                "pi_financial_accounts",
                "pi_financial_revenues",
                "pi_financial_expenses",
                "pi_financial_movements",
                "pi_cash_sessions",
            ],
            "financeiro",
        );
    
    }
}
