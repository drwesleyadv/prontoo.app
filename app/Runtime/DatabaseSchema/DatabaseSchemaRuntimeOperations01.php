<?php
declare(strict_types=1);

namespace Prontoo\Runtime\DatabaseSchema;

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
use \PDOStatement;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;
use Prontoo\Runtime\FinancialGuard\FinancialGuardRuntimeOperations01;

final class DatabaseSchemaRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function q(string $sql, array $params = []): PDOStatement
    
    {
    
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_reject_runtime_ddl($sql);
        $autoIntegrityTransaction =
            !\Prontoo\Infrastructure\Database\PdoQueryDriver::inTransaction() &&
            preg_match("/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i", $sql) === 1 &&
            class_exists("\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity") &&
            is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'has_cfg']) &&
            \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg();
        $attempts = \Prontoo\Infrastructure\Database\PdoQueryDriver::inTransaction() ? 1 : 3;
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
                if (is_callable([\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::class, 'sql_write_scope_guard'])) {
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::sql_write_scope_guard($sql, $params);
                }
                if ($autoIntegrityTransaction) {
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_begin_transaction();
                }
                FinancialGuardRuntimeOperations01::financial_movement_write_guard($sql, $params);
                if (class_exists("\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity")) {
                    [
                        $runtimeSql,
                        $runtimeParams,
                    ] = \Prontoo\Infrastructure\Integrity\PiIntegrity::prepareRuntimeQuery(
                        $sql,
                        $params,
                    );
                    $integrityContext = \Prontoo\Infrastructure\Integrity\PiIntegrity::beforeQuery(
                        $runtimeSql,
                        $runtimeParams,
                    );
                }
    
                $GLOBALS["PRONTOO_INSTALL_LAST_RUNTIME_SQL"] = $runtimeSql;
                $statement = \Prontoo\Infrastructure\Database\PdoQueryDriver::execute($runtimeSql, $runtimeParams);
                if (preg_match("/^\s*(INSERT|REPLACE)\b/i", $runtimeSql)) {
                    $GLOBALS[
                        "PRONTOO_LAST_INSERT_ID"
                    ] = \Prontoo\Infrastructure\Database\PdoQueryDriver::lastInsertId();
                }
    
                $elapsedMs = (microtime(true) - $startedAt) * 1000;
                if (class_exists("\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity")) {
                    \Prontoo\Infrastructure\Integrity\PiIntegrity::afterQuery(
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
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_commit();
                }
                return $statement;
            } catch (Throwable $error) {
                $lastError = $error;
                if (!$queryCompleted) {
                    $elapsedMs = (microtime(true) - $startedAt) * 1000;
                    if (class_exists("\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity")) {
                        \Prontoo\Infrastructure\Integrity\PiIntegrity::afterQuery(
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
                if ($autoIntegrityTransaction && \Prontoo\Infrastructure\Database\PdoQueryDriver::inTransaction()) {
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                }
                if ($attempt + 1 < $attempts && \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_retryable_conflict($error)) {
                    usleep(\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_retry_delay_us($attempt));
                    continue;
                }
                \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_log_query_failure($error, $runtimeSql);
                throw $error;
            }
        }
    
        throw $lastError ?? new RuntimeException("Falha desconhecida no banco.");
    
    }

    public static function one(string $sql, array $params = []): ?array
    
    {
    
        $statement = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q($sql, $params);
        $row = $statement->fetch();
        $statement->closeCursor();
        return is_array($row) ? $row : null;
    
    }

    public static function val(string $sql, array $params = []): mixed
    
    {
    
        $statement = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q($sql, $params);
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        return $value === false ? null : $value;
    
    }

    public static function ensure_runtime_schema_minimum(): void
    
    {
    
        \Prontoo\Infrastructure\DatabaseSchema\RuntimeSchemaReadiness::ensure();
    
    }
}
