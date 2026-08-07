<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\DatabaseSchema;

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

final class DatabaseSchemaRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function q(string $sql, array $params = []): PDOStatement
    
    {
    
        db_reject_runtime_ddl($sql);
        $connection = pdo();
        $autoIntegrityTransaction =
            !$connection->inTransaction() &&
            preg_match("/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i", $sql) === 1 &&
            class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity") &&
            function_exists("has_cfg") &&
            has_cfg();
        $attempts = $connection->inTransaction() ? 1 : 3;
        $lastError = null;
    
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $startedAt = microtime(true);
            $runtimeSql = $sql;
            $runtimeParams = $params;
            $integrityContext = [];
            $queryCompleted = false;
            $GLOBALS["PRONTOO_INSTALL_LAST_SQL"] = $sql;
            $GLOBALS["PRONTOO_INSTALL_LAST_PARAM_COUNT"] = count($params);
    
            try {
                if (function_exists("sql_write_scope_guard")) {
                    sql_write_scope_guard($sql, $params);
                }
                if ($autoIntegrityTransaction) {
                    db_begin_transaction();
                }
                if (function_exists("financial_movement_write_guard")) {
                    financial_movement_write_guard($sql, $params);
                }
                if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                    [
                        $runtimeSql,
                        $runtimeParams,
                    ] = \Prontoo\Core\Integrity\PiIntegrity::prepareRuntimeQuery(
                        $sql,
                        $params,
                    );
                    $integrityContext = \Prontoo\Core\Integrity\PiIntegrity::beforeQuery(
                        $runtimeSql,
                        $runtimeParams,
                    );
                }
    
                $GLOBALS["PRONTOO_INSTALL_LAST_RUNTIME_SQL"] = $runtimeSql;
                $statement = $connection->prepare($runtimeSql);
                $statement->execute($runtimeParams);
                if (preg_match("/^\s*(INSERT|REPLACE)\b/i", $runtimeSql)) {
                    $GLOBALS[
                        "PRONTOO_LAST_INSERT_ID"
                    ] = (int) $connection->lastInsertId();
                }
    
                $elapsedMs = (microtime(true) - $startedAt) * 1000;
                if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                    \Prontoo\Core\Integrity\PiIntegrity::afterQuery(
                        $sql,
                        $runtimeSql,
                        $runtimeParams,
                        true,
                        $statement->rowCount(),
                        $elapsedMs,
                        null,
                        $integrityContext,
                    );
                }
                $queryCompleted = true;
                if ($autoIntegrityTransaction) {
                    db_commit();
                }
                return $statement;
            } catch (Throwable $error) {
                $lastError = $error;
                if (!$queryCompleted) {
                    $elapsedMs = (microtime(true) - $startedAt) * 1000;
                    if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                        \Prontoo\Core\Integrity\PiIntegrity::afterQuery(
                            $sql,
                            $runtimeSql,
                            $runtimeParams,
                            false,
                            0,
                            $elapsedMs,
                            $error,
                            $integrityContext,
                        );
                    }
                }
                if ($autoIntegrityTransaction && $connection->inTransaction()) {
                    db_rollback();
                }
                if ($attempt + 1 < $attempts && db_retryable_conflict($error)) {
                    usleep(db_retry_delay_us($attempt));
                    continue;
                }
                db_log_query_failure($error, $runtimeSql);
                throw $error;
            }
        }
    
        throw $lastError ?? new RuntimeException("Falha desconhecida no banco.");
    
    }

    public static function one(string $sql, array $params = []): ?array
    
    {
    
        $statement = q($sql, $params);
        $row = $statement->fetch();
        $statement->closeCursor();
        return is_array($row) ? $row : null;
    
    }

    public static function val(string $sql, array $params = []): mixed
    
    {
    
        $statement = q($sql, $params);
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        return $value === false ? null : $value;
    
    }

    public static function ensure_runtime_schema_minimum(): void
    
    {
    
        static $validated = false;
        if ($validated || !has_cfg()) {
            return;
        }
    
        schema_apply_pending_release_migrations();
    
        $expectedRevision = defined("PRONTOO_SCHEMA_REV")
            ? PRONTOO_SCHEMA_REV
            : "prontoo_1_7_20_6_clean_schema_r7_layer2_ledger";
        $revision = val("SELECT meta_value FROM pi_meta WHERE meta_key=?", [
            "schema_revision",
        ]);
        $contract = val("SELECT meta_value FROM pi_meta WHERE meta_key=?", [
            "schema_contract_hash",
        ]);
        if (!hash_equals($expectedRevision, (string) $revision)) {
            throw new RuntimeException(
                "Revisão do banco incompatível com a aplicação.",
            );
        }
        if (!hash_equals(prontoo_schema_contract_hash(), (string) $contract)) {
            throw new RuntimeException(
                "Contrato do banco incompatível com a aplicação.",
            );
        }
    
        $ready = json_decode((string) prontoo_fs_read(schema_lock_file()), true);
        if (
            !is_array($ready) ||
            !hash_equals($expectedRevision, (string) ($ready["revision"] ?? "")) ||
            !hash_equals(
                prontoo_schema_contract_hash(),
                (string) ($ready["contract"] ?? ""),
            )
        ) {
            throw new RuntimeException("Marcador do schema instalado é inválido.");
        }
        $validated = true;
    
    }
}
