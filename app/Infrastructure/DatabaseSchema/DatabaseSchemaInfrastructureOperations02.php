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

final class DatabaseSchemaInfrastructureOperations02
{
    private function __construct()
    {
    }

    public static function schema_split_sql(string $sql): array
    
    {
    
        $statements = [];
        $buffer = "";
        $quote = null;
        $escaped = false;
        $length = strlen($sql);
    
        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $buffer .= $character;
    
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($quote !== null && $character === "\\") {
                $escaped = true;
                continue;
            }
            if ($quote !== null) {
                if ($character === $quote) {
                    if (
                        $index + 1 < $length &&
                        $sql[$index + 1] === $quote &&
                        $quote !== "`"
                    ) {
                        $buffer .= $sql[++$index];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if (in_array($character, ["'", '"', "`"], true)) {
                $quote = $character;
                continue;
            }
            if ($character === ";") {
                $statement = trim(substr($buffer, 0, -1));
                if ($statement !== "") {
                    $statements[] = $statement;
                }
                $buffer = "";
            }
        }
    
        if (trim($buffer) !== "") {
            $statements[] = trim($buffer);
        }
        return $statements;
    
    }

    public static function prontoo_schema_statements(): array
    
    {
    
        $statements = $GLOBALS["PRONTOO_SCHEMA_STATEMENTS_CACHE"] ?? null;
        if (is_array($statements)) {
            return $statements;
        }
        $statements = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_split_sql(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_sql());
        $actualTables = [];
        foreach ($statements as $statement) {
            if (
                !preg_match(
                    "/^CREATE\s+TABLE\s+`?(pi_[A-Za-z0-9_]+)`?\s*\(/i",
                    $statement,
                    $match,
                )
            ) {
                throw new RuntimeException(
                    "Contrato SQL contém comando não permitido.",
                );
            }
            $table = strtolower((string) ($match[1] ?? ""));
            if ($table === "" || isset($actualTables[$table])) {
                throw new RuntimeException(
                    "Contrato SQL contém tabela inválida ou duplicada.",
                );
            }
            $actualTables[$table] = true;
        }
        $actualNames = array_keys($actualTables);
        sort($actualNames, SORT_STRING);
        $expectedNames = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_expected_table_names();
        if ($actualNames !== $expectedNames) {
            $missing = array_values(array_diff($expectedNames, $actualNames));
            $unexpected = array_values(array_diff($actualNames, $expectedNames));
            throw new RuntimeException(
                "Contrato SQL diverge da coleção canônica de tabelas: esperadas=" .
                    count($expectedNames) .
                    ", encontradas=" .
                    count($actualNames) .
                    ", ausentes=" .
                    implode(",", array_slice($missing, 0, 8)) .
                    ", inesperadas=" .
                    implode(",", array_slice($unexpected, 0, 8)),
            );
        }
        foreach ($statements as $statement) {
            if (preg_match("/\b(?:DATE|DATETIME|TIMESTAMP)\b/i", $statement)) {
                throw new RuntimeException(
                    "Contrato SQL contém tipo temporal nativo; o Prontoo exige Unix UTC em BIGINT.",
                );
            }
        }
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_assert_mysql_constraint_compatibility($statements);
        $GLOBALS["PRONTOO_SCHEMA_STATEMENTS_CACHE"] = $statements;
        return $statements;
    
    }

    public static function schema_split_definitions(string $body): array
    
    {
    
        $definitions = [];
        $buffer = "";
        $quote = null;
        $escaped = false;
        $depth = 0;
        $length = strlen($body);
    
        for ($index = 0; $index < $length; $index++) {
            $character = $body[$index];
            if ($escaped) {
                $buffer .= $character;
                $escaped = false;
                continue;
            }
            if ($quote !== null && $character === "\\") {
                $buffer .= $character;
                $escaped = true;
                continue;
            }
            if ($quote !== null) {
                $buffer .= $character;
                if ($character === $quote) {
                    $quote = null;
                }
                continue;
            }
            if (in_array($character, ["'", '"', "`"], true)) {
                $quote = $character;
                $buffer .= $character;
                continue;
            }
            if ($character === "(") {
                $depth++;
            } elseif ($character === ")") {
                $depth--;
            }
            if ($character === "," && $depth === 0) {
                $definitions[] = trim($buffer);
                $buffer = "";
                continue;
            }
            $buffer .= $character;
        }
        if (trim($buffer) !== "") {
            $definitions[] = trim($buffer);
        }
        return $definitions;
    
    }

    public static function schema_assert_mysql_constraint_compatibility(array $statements): void
    
    {
    
        foreach ($statements as $statement) {
            if (
                !preg_match(
                    "/^CREATE\\s+TABLE\\s+`?([A-Za-z0-9_]+)`?\\s*\\((.*)\\)\\s*ENGINE=/is",
                    $statement,
                    $tableMatch,
                )
            ) {
                throw new RuntimeException(
                    "Definição de tabela inválida no contrato SQL.",
                );
            }
            $table = $tableMatch[1];
            $autoIncrementColumns = [];
            $generatedBaseColumns = [];
            $referentialActionColumns = [];
            $checks = [];
    
            foreach (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_split_definitions($tableMatch[2]) as $definition) {
                if (
                    preg_match(
                        "/^`?([A-Za-z0-9_]+)`?\\s+.*\\bAUTO_INCREMENT\\b/is",
                        $definition,
                        $columnMatch,
                    )
                ) {
                    $autoIncrementColumns[$columnMatch[1]] = true;
                }
                if (
                    preg_match(
                        "/^`?([A-Za-z0-9_]+)`?\s+.*\bGENERATED\s+ALWAYS\s+AS\s*\((.*)\)\s+STORED\b/is",
                        $definition,
                        $generatedMatch,
                    )
                ) {
                    preg_match_all(
                        "/`([A-Za-z_][A-Za-z0-9_]*)`/",
                        $generatedMatch[2],
                        $baseMatches,
                    );
                    foreach (array_unique($baseMatches[1] ?? []) as $baseColumn) {
                        $generatedBaseColumns[$baseColumn] = true;
                    }
                }
                if (
                    preg_match(
                        "/\\bFOREIGN\\s+KEY\\s*\\(([^)]*)\\).*\\bON\\s+(?:DELETE|UPDATE)\\s+(?:CASCADE|SET\\s+NULL|SET\\s+DEFAULT)\\b/is",
                        $definition,
                        $foreignMatch,
                    )
                ) {
                    foreach (explode(",", $foreignMatch[1]) as $column) {
                        $column = trim($column, " `\\t\\r\\n");
                        if ($column !== "") {
                            $referentialActionColumns[$column] = true;
                        }
                    }
                }
                if (
                    preg_match(
                        "/^CONSTRAINT\\s+`?([A-Za-z0-9_]+)`?\\s+CHECK\\s*\\((.*)\\)$/is",
                        $definition,
                        $checkMatch,
                    )
                ) {
                    preg_match_all(
                        "/`([A-Za-z_][A-Za-z0-9_]*)`/",
                        $checkMatch[2],
                        $columnMatches,
                    );
                    $checks[] = [
                        "name" => $checkMatch[1],
                        "columns" => array_values(
                            array_unique($columnMatches[1] ?? []),
                        ),
                    ];
                }
            }
    
            foreach (array_keys($generatedBaseColumns) as $column) {
                if (isset($referentialActionColumns[$column])) {
                    throw new RuntimeException(
                        "Coluna-base gerada {$table}.{$column} conflita com ação referencial.",
                    );
                }
            }
    
            foreach ($checks as $check) {
                foreach ($check["columns"] as $column) {
                    if (isset($autoIncrementColumns[$column])) {
                        throw new RuntimeException(
                            "CHECK {$table}.{$check['name']} referencia coluna AUTO_INCREMENT {$column}.",
                        );
                    }
                    if (isset($referentialActionColumns[$column])) {
                        throw new RuntimeException(
                            "CHECK {$table}.{$check['name']} conflita com ação referencial da coluna {$column}.",
                        );
                    }
                }
            }
        }
    
    }

    public static function prontoo_schema_definition_map(): array
    
    {
    
        $map = $GLOBALS["PRONTOO_SCHEMA_DEFINITION_MAP_CACHE"] ?? null;
        if (is_array($map)) {
            return $map;
        }
        $map = [];
        foreach (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_statements() as $statement) {
            if (
                !preg_match(
                    "/^CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s*\((.*)\)\s*ENGINE=/is",
                    $statement,
                    $match,
                )
            ) {
                throw new RuntimeException(
                    "Definição de tabela inválida no contrato SQL.",
                );
            }
            $table = $match[1];
            $columns = [];
            $objects = [];
            foreach (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_split_definitions($match[2]) as $definition) {
                if (
                    preg_match(
                        "/^CONSTRAINT\s+`?([A-Za-z0-9_]+)`?/i",
                        $definition,
                        $named,
                    )
                ) {
                    $objects[] = $named[1];
                    continue;
                }
                if (preg_match("/^PRIMARY\s+KEY\b/i", $definition)) {
                    $objects[] = "PRIMARY";
                    continue;
                }
                if (
                    preg_match(
                        "/^(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?([A-Za-z0-9_]+)`?/i",
                        $definition,
                        $index,
                    )
                ) {
                    $objects[] = $index[1];
                    continue;
                }
                if (
                    !preg_match("/^`?([A-Za-z0-9_]+)`?\s+/", $definition, $column)
                ) {
                    throw new RuntimeException("Coluna inválida em {$table}.");
                }
                $columns[] = $column[1];
                if (preg_match("/\bPRIMARY\s+KEY\b/i", $definition)) {
                    $objects[] = "PRIMARY";
                }
                if (preg_match("/\bUNIQUE\b/i", $definition)) {
                    $objects[] = $column[1];
                }
            }
            $map[$table] = [
                "columns" => array_values(array_unique($columns)),
                "objects" => array_values(array_unique($objects)),
            ];
        }
        $GLOBALS["PRONTOO_SCHEMA_DEFINITION_MAP_CACHE"] = $map;
        return $map;
    
    }

    public static function prontoo_schema_table_names(): array
    
    {
    
        return array_keys(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_definition_map());
    
    }

    public static function prontoo_schema_columns(): array
    
    {
    
        $result = [];
        foreach (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_definition_map() as $table => $definition) {
            $result[$table] = $definition["columns"];
        }
        return $result;
    
    }

    public static function prontoo_schema_constraint_names(): array
    
    {
    
        $result = [];
        foreach (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_definition_map() as $table => $definition) {
            $result[$table] = $definition["objects"];
        }
        return $result;
    
    }

    public static function prontoo_schema_contract_hash(): string
    
    {
    
        return hash("sha256", \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_sql());
    
    }

    public static function allowed_db_table(string $table): string
    
    {
    
        $allowed = $GLOBALS["PRONTOO_SCHEMA_ALLOWED_TABLES_CACHE"] ?? null;
        if (!is_array($allowed)) {
            $allowed = array_fill_keys(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_table_names(), true);
            $GLOBALS["PRONTOO_SCHEMA_ALLOWED_TABLES_CACHE"] = $allowed;
        }
        if (!isset($allowed[$table])) {
            throw new RuntimeException("Tabela não permitida.");
        }
        return $table;
    
    }

    public static function safe_db_columns(string $columns): string
    
    {
    
        $items = array_map("trim", explode(",", trim($columns)));
        if ($items === [] || in_array("", $items, true)) {
            throw new RuntimeException("Seleção de colunas inválida.");
        }
        foreach ($items as $item) {
            if (
                !preg_match(
                    '/^(?:`?[A-Za-z_][A-Za-z0-9_]*`?\.)?`?[A-Za-z_][A-Za-z0-9_]*`?(?:\s+AS\s+`?[A-Za-z_][A-Za-z0-9_]*`?)?$/i',
                    $item,
                )
            ) {
                throw new RuntimeException("Seleção de colunas inválida.");
            }
        }
        return implode(",", $items);
    
    }

    public static function db_ident(string $name): string
    
    {
    
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException("Identificador de banco inválido.");
        }
        return "`{$name}`";
    
    }

    public static function db_schema_error_is_missing_table(Throwable $error): bool
    
    {
    
        $message = $error->getMessage();
        return str_contains($message, "doesn't exist") ||
            str_contains($message, "Base table or view not found") ||
            str_contains($message, "1146");
    
    }

    public static function run_schema_sql(string $sql): void
    
    {
    
        if (
            !class_exists("\\Prontoo\\Core\\Database\\SchemaMutationLock") ||
            !\Prontoo\Core\Database\SchemaMutationLock::isActive()
        ) {
            throw new RuntimeException("Comando estrutural fora da janela privada do instalador.");
        }
    if (!preg_match("/^\s*CREATE\s+TABLE\b/i", $sql)) {
            throw new RuntimeException(
                "Somente CREATE TABLE canônico é permitido.",
            );
        }
        $GLOBALS["PRONTOO_INSTALL_LAST_SCHEMA_SQL"] = $sql;
        try {
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->exec($sql);
            if (class_exists("\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity")) {
                \Prontoo\Infrastructure\Integrity\PiIntegrity::proveSchemaOperation(
                    $sql,
                    true,
                    null,
                );
            }
        } catch (Throwable $error) {
            if (class_exists("\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity")) {
                \Prontoo\Infrastructure\Integrity\PiIntegrity::proveSchemaOperation(
                    $sql,
                    false,
                    $error->getMessage(),
                );
            }
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_log_query_failure($error, $sql);
            throw $error;
        }
    
    }

    public static function db_table_exists(string $table): bool
    
    {
    
        static $existing = [];
        if (isset($existing[$table])) {
            return true;
        }
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare(
            "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1",
        );
        $statement->execute([$table]);
        $exists = $statement->fetchColumn() !== false;
        if ($exists) {
            $existing[$table] = true;
        }
        return $exists;
    
    }

    public static function db_column_exists(string $table, string $column): bool
    
    {
    
        static $existing = [];
        $key = $table . "|" . $column;
        if (isset($existing[$key])) {
            return true;
        }
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare(
            "SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1",
        );
        $statement->execute([$table, $column]);
        $exists = $statement->fetchColumn() !== false;
        if ($exists) {
            $existing[$key] = true;
        }
        return $exists;
    
    }

    public static function db_index_exists(string $table, string $index): bool
    
    {
    
        static $existing = [];
        $key = $table . "|" . $index;
        if (isset($existing[$key])) {
            return true;
        }
        $statement = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->prepare(
            "SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=? LIMIT 1",
        );
        $statement->execute([$table, $index]);
        $exists = $statement->fetchColumn() !== false;
        if ($exists) {
            $existing[$key] = true;
        }
        return $exists;
    
    }

    public static function schema_lock_file(): string
    
    {
    
        return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("schema.ready");
    
    }
}
