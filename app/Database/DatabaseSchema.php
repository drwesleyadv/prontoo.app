<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function db_runtime_notice_once(string $key, string $message): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_runtime_notice_once($key, $message);
}

function db_mysql_version(PDO $connection): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_mysql_version($connection);
}

function db_assert_mysql_runtime(PDO $connection): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_assert_mysql_runtime($connection);
}

function db_session_sql_modes(PDO $connection): array
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_session_sql_modes($connection);
}

function db_session_sql_mode_is_safe(array $modes): bool
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_session_sql_mode_is_safe($modes);
}

function db_apply_mysql_session_contract(
    PDO $connection,
    bool $strictMode = true,
): void {
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_apply_mysql_session_contract($connection, $strictMode);
}

function pdo(): PDO
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
}

function db_retryable_conflict(Throwable $error): bool
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_retryable_conflict($error);
}

function db_retry_delay_us(int $attempt): int
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_retry_delay_us($attempt);
}

function db_log_query_failure(Throwable $error, string $sql): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_log_query_failure($error, $sql);
}

function db_reject_runtime_ddl(string $sql): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_reject_runtime_ddl($sql);
}

function q(string $sql, array $params = []): PDOStatement
{
    return \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q($sql, $params);
}

function one(string $sql, array $params = []): ?array
{
    return \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one($sql, $params);
}

function val(string $sql, array $params = []): mixed
{
    return \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val($sql, $params);
}

function db_last_insert_id(): int
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_last_insert_id();
}

function db_temp_space_error(Throwable $error): bool
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_temp_space_error($error);
}

function db_prepare_write_transaction(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_prepare_write_transaction();
}

function db_begin_transaction(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_begin_transaction();
}

function db_commit(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_commit();
}

function db_rollback(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
}

function db_tx(callable $callback): mixed
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_tx($callback);
}

function prontoo_schema_file(): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_file();
}

function prontoo_operational_schema_contract_file(): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_operational_schema_contract_file();
}

function prontoo_operational_schema_contract(): array
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_operational_schema_contract();
}

function prontoo_schema_expected_table_names(): array
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_expected_table_names();
}

function prontoo_schema_release_contract_file(): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_release_contract_file();
}

function prontoo_schema_release_contract_hash(): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_release_contract_hash();
}

function prontoo_schema_previous_contract_hash(): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_previous_contract_hash();
}

function prontoo_schema_clear_caches(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_clear_caches();
}

function prontoo_schema_promote_release_contract(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_promote_release_contract();
}

function prontoo_schema_sql(): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::prontoo_schema_sql();
}

function schema_split_sql(string $sql): array
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_split_sql($sql);
}

function prontoo_schema_statements(): array
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_statements();
}

function schema_split_definitions(string $body): array
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_split_definitions($body);
}

function schema_assert_mysql_constraint_compatibility(array $statements): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_assert_mysql_constraint_compatibility($statements);
}

function prontoo_schema_definition_map(): array
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_definition_map();
}

function prontoo_schema_table_names(): array
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_table_names();
}

function prontoo_schema_columns(): array
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_columns();
}

function prontoo_schema_constraint_names(): array
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_constraint_names();
}

function prontoo_schema_contract_hash(): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_contract_hash();
}

function allowed_db_table(string $table): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::allowed_db_table($table);
}

function safe_db_columns(string $columns): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::safe_db_columns($columns);
}

function db_ident(string $name): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_ident($name);
}

function db_schema_error_is_missing_table(Throwable $error): bool
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_schema_error_is_missing_table($error);
}

function run_schema_sql(string $sql): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::run_schema_sql($sql);
}

function db_table_exists(string $table): bool
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_table_exists($table);
}

function db_column_exists(string $table, string $column): bool
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_column_exists($table, $column);
}

function db_index_exists(string $table, string $index): bool
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_index_exists($table, $index);
}

function schema_lock_file(): string
{
    return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::schema_lock_file();
}

function schema_mark_ready(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::schema_mark_ready();
}

function schema_validate_complete(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::schema_validate_complete();
}

function schema_seed_meta(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::schema_seed_meta();
}

function schema_cleanup_failed_install(array $tables): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::schema_cleanup_failed_install($tables);
}

function install_fresh_schema(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::install_fresh_schema();
}

function schema_apply_pending_release_migrations(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::schema_apply_pending_release_migrations();
}

function ensure_runtime_schema_minimum(): void
{
    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::ensure_runtime_schema_minimum();
}

function db_assert_tables(array $tables, string $domain): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::db_assert_tables($tables, $domain);
}

function ensure_lead_events_schema(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::ensure_lead_events_schema();
}

function ensure_financial_operational_schema(): void
{
    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::ensure_financial_operational_schema();
}
