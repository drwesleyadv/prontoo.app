<?php
declare(strict_types=1);
function db_runtime_notice_once(string $key, string $message): void
{
    static $requestNotices = [];
    $key = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($key)) ?: "notice";
    if (isset($requestNotices[$key])) {
        return;
    }
    $requestNotices[$key] = true;
    $version = defined("PRONTOO_VERSION") ? PRONTOO_VERSION : "runtime";
    $dir = function_exists("storage_path")
        ? storage_path("cache/runtime-notices")
        : dirname(__DIR__, 2) . "/storage/cache/runtime-notices";
    $marker =
        $dir .
        "/" .
        $key .
        "-" .
        preg_replace('/[^0-9a-z._-]+/i', '_', $version) .
        ".json";
    if (is_file($marker)) {
        return;
    }
    error_log($message);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents(
            $marker,
            json_encode(["recorded_at" => time()], JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
    }
}


function db_mysql_version(PDO $connection): string
{
    $value = $connection->query("SELECT VERSION()")?->fetchColumn();
    return trim((string) $value);
}

function db_assert_mysql_runtime(PDO $connection): void
{
    $rawVersion = db_mysql_version($connection);
    if ($rawVersion === "" || stripos($rawVersion, "mariadb") !== false) {
        throw new RuntimeException("O Prontoo requer MySQL compatível com a instalação limpa.");
    }
    if (!preg_match('/^(\d+\.\d+\.\d+)/', $rawVersion, $match)) {
        throw new RuntimeException("Não foi possível validar a versão do MySQL.");
    }
    $minimum = defined("PRONTOO_MIN_MYSQL_VERSION")
        ? PRONTOO_MIN_MYSQL_VERSION
        : "8.0.30";
    if (version_compare($match[1], $minimum, "<")) {
        throw new RuntimeException(
            "MySQL {$minimum} ou superior é obrigatório; versão detectada: {$match[1]}.",
        );
    }

    try {
        $strict = $connection
            ->query("SELECT @@SESSION.innodb_strict_mode")
            ?->fetchColumn();
        // Estado apenas diagnóstico: o contrato integral do schema é validado
        // independentemente de innodb_strict_mode e não deve poluir o runtime.log.
        $GLOBALS["PRONTOO_INNODB_STRICT_MODE"] = (string) $strict === "1";
    } catch (Throwable $error) {
        db_runtime_notice_once(
            "innodb_strict_mode_unavailable",
            "[Prontoo MySQL] Não foi possível consultar innodb_strict_mode: " .
                $error->getMessage(),
        );
    }
}

function db_session_sql_modes(PDO $connection): array
{
    $raw = (string) ($connection
        ->query("SELECT @@SESSION.sql_mode")
        ?->fetchColumn() ?? "");
    $modes = [];
    foreach (explode(",", strtoupper($raw)) as $mode) {
        $mode = trim($mode);
        if ($mode !== "") {
            $modes[$mode] = true;
        }
    }
    return array_keys($modes);
}

function db_session_sql_mode_is_safe(array $modes): bool
{
    $set = array_fill_keys(array_map("strtoupper", $modes), true);
    $strict = isset($set["STRICT_ALL_TABLES"]) ||
        isset($set["STRICT_TRANS_TABLES"]);
    return $strict &&
        isset($set["ONLY_FULL_GROUP_BY"]) &&
        isset($set["ERROR_FOR_DIVISION_BY_ZERO"]) &&
        isset($set["NO_ENGINE_SUBSTITUTION"]);
}

function db_apply_mysql_session_contract(
    PDO $connection,
    bool $strictMode = true,
): void {
    $connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connection->exec("SET time_zone = '+00:00'");

    if (!$strictMode) {
        return;
    }

    $modes = db_session_sql_modes($connection);
    foreach (
        [
            "STRICT_ALL_TABLES",
            "ONLY_FULL_GROUP_BY",
            "ERROR_FOR_DIVISION_BY_ZERO",
            "NO_ENGINE_SUBSTITUTION",
        ] as $required
    ) {
        if (!in_array($required, $modes, true)) {
            $modes[] = $required;
        }
    }

    try {
        $connection->exec(
            "SET SESSION sql_mode = " .
                $connection->quote(implode(",", $modes)),
        );
    } catch (PDOException $error) {
        $driverCode = is_array($error->errorInfo ?? null)
            ? (int) ($error->errorInfo[1] ?? 0)
            : 0;
        $current = db_session_sql_modes($connection);
        if ($driverCode === 1227 && db_session_sql_mode_is_safe($current)) {
            error_log(
                "[Prontoo MySQL] A hospedagem bloqueou a alteração de sql_mode, " .
                    "mas a sessão já possui modo estrito compatível.",
            );
            return;
        }
        throw new RuntimeException(
            "A sessão MySQL não permite aplicar o modo estrito necessário ao Prontoo.",
            0,
            $error,
        );
    }

    $applied = db_session_sql_modes($connection);
    if (!db_session_sql_mode_is_safe($applied)) {
        throw new RuntimeException(
            "O MySQL não confirmou o modo estrito necessário ao Prontoo.",
        );
    }
}

function pdo(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = cfg();
    foreach (["db_host", "db_name", "db_user", "db_pass"] as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException("Configuração de banco incompleta.");
        }
    }

    $host = trim((string) $config["db_host"]);
    $database = trim((string) $config["db_name"]);
    if ($host === "" || $database === "") {
        throw new RuntimeException("Host e banco de dados são obrigatórios.");
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_PERSISTENT => false,
    ];
    if (defined("PDO::MYSQL_ATTR_USE_BUFFERED_QUERY")) {
        $options[constant("PDO::MYSQL_ATTR_USE_BUFFERED_QUERY")] = true;
    }

    $connection = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        (string) $config["db_user"],
        (string) $config["db_pass"],
        $options,
    );
    db_assert_mysql_runtime($connection);
    db_apply_mysql_session_contract($connection, true);
    return $connection;
}

function db_query_metric_record(
    string $sql,
    float $elapsedMs,
    bool $success,
    ?Throwable $error = null,
): void {
    $GLOBALS["PRONTOO_QUERY_COUNT"] =
        (int) ($GLOBALS["PRONTOO_QUERY_COUNT"] ?? 0) + 1;
    $GLOBALS["PRONTOO_QUERY_TOTAL_MS"] =
        (float) ($GLOBALS["PRONTOO_QUERY_TOTAL_MS"] ?? 0.0) +
        max(0.0, $elapsedMs);
    if (preg_match("/^\s*SELECT\s+(.*?)\s+FROM\b/is", $sql, $match)) {
        $selectList = preg_replace(
            "/^(?:DISTINCT|SQL_CALC_FOUND_ROWS)\s+/i",
            "",
            trim((string) ($match[1] ?? "")),
        );
        if (
            is_string($selectList) &&
            preg_match(
                "/(?:^|,)\s*(?:`?[a-z0-9_]+`?\.)?\*\s*(?:,|$)/i",
                $selectList,
            )
        ) {
            $GLOBALS["PRONTOO_QUERY_WIDE_SELECT_COUNT"] =
                (int) ($GLOBALS["PRONTOO_QUERY_WIDE_SELECT_COUNT"] ?? 0) + 1;
        }
    }
}

function db_retryable_conflict(Throwable $error): bool
{
    $code = (string) $error->getCode();
    $message = strtolower($error->getMessage());
    return $code === "40001" ||
        str_contains($message, "deadlock") ||
        str_contains($message, "1213") ||
        str_contains($message, "lock wait timeout") ||
        str_contains($message, "1205") ||
        str_contains($message, "try restarting transaction");
}

function db_retry_delay_us(int $attempt): int
{
    $base = min(300000, 40000 + max(0, $attempt) * 65000);
    try {
        return $base + random_int(0, 20000);
    } catch (Throwable $error) {
        error_log("[Prontoo retry jitter] " . $error->getMessage());
        return $base;
    }
}

function db_log_query_failure(Throwable $error, string $sql): void
{
    $message = function_exists("privacy_sanitize_error_message")
        ? privacy_sanitize_error_message($error, 220)
        : mb_substr($error->getMessage(), 0, 220);
    $sample = preg_replace("/\s+/", " ", trim($sql)) ?? trim($sql);
    error_log("[Prontoo SQL] {$message} | " . mb_substr($sample, 0, 420));
}

function db_reject_runtime_ddl(string $sql): void
{
    if (
        preg_match("/^\s*(CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i", $sql) &&
        empty($GLOBALS["PRONTOO_SCHEMA_INSTALLING"])
    ) {
        throw new RuntimeException(
            "Alteração estrutural do banco fora da instalação limpa foi bloqueada.",
        );
    }
}

function q(string $sql, array $params = []): PDOStatement
{
    db_reject_runtime_ddl($sql);
    $connection = pdo();
    $attempts = $connection->inTransaction() ? 1 : 3;
    $lastError = null;

    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        $startedAt = microtime(true);
        $runtimeSql = $sql;
        $runtimeParams = $params;
        $integrityContext = [];
        $GLOBALS["PRONTOO_INSTALL_LAST_SQL"] = $sql;
        $GLOBALS["PRONTOO_INSTALL_LAST_PARAM_COUNT"] = count($params);

        try {
            if (function_exists("sql_write_scope_guard")) {
                sql_write_scope_guard($sql, $params);
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
            db_query_metric_record($runtimeSql, $elapsedMs, true);
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
            return $statement;
        } catch (Throwable $error) {
            $lastError = $error;
            $elapsedMs = (microtime(true) - $startedAt) * 1000;
            db_query_metric_record($runtimeSql, $elapsedMs, false, $error);
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

function one(string $sql, array $params = []): ?array
{
    $statement = q($sql, $params);
    $row = $statement->fetch();
    $statement->closeCursor();
    return is_array($row) ? $row : null;
}

function val(string $sql, array $params = []): mixed
{
    $statement = q($sql, $params);
    $value = $statement->fetchColumn();
    $statement->closeCursor();
    return $value === false ? null : $value;
}

function db_last_insert_id(): int
{
    return (int) ($GLOBALS["PRONTOO_LAST_INSERT_ID"] ?? 0);
}

function db_temp_space_error(Throwable $error): bool
{
    $message = $error->getMessage();
    return str_contains($message, "Errcode: 28") ||
        str_contains($message, "No space left on device") ||
        str_contains($message, "Disk got full writing");
}

function db_prepare_write_transaction(): void
{
    if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
        \Prontoo\Core\Integrity\PiIntegrity::prepareForWriteTransaction();
    }
}

function db_begin_transaction(): void
{
    $connection = pdo();
    if ($connection->inTransaction()) {
        return;
    }
    db_prepare_write_transaction();
    $connection->beginTransaction();
    if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
        \Prontoo\Core\Integrity\PiIntegrity::markTransactionStart();
    }
}

function db_commit(): void
{
    $connection = pdo();
    if (!$connection->inTransaction()) {
        return;
    }
    try {
        if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
            \Prontoo\Core\Integrity\PiIntegrity::flushFastEvents();
        }
        $connection->commit();
        if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
            \Prontoo\Core\Integrity\PiIntegrity::markTransactionCommitted();
        }
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
            \Prontoo\Core\Integrity\PiIntegrity::discardTransactionEvents();
        }
        throw $error;
    }
}

function db_rollback(): void
{
    $connection = pdo();
    if (!$connection->inTransaction()) {
        return;
    }
    $connection->rollBack();
    if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
        \Prontoo\Core\Integrity\PiIntegrity::discardTransactionEvents();
    }
}

function db_tx(callable $callback): mixed
{
    $connection = pdo();
    if ($connection->inTransaction()) {
        return $callback();
    }

    $lastError = null;
    for ($attempt = 0; $attempt < 3; $attempt++) {
        try {
            db_begin_transaction();
            $result = $callback();
            db_commit();
            return $result;
        } catch (Throwable $error) {
            $lastError = $error;
            if ($connection->inTransaction()) {
                db_rollback();
            }
            if ($attempt === 2 || !db_retryable_conflict($error)) {
                throw $error;
            }
            usleep(db_retry_delay_us($attempt));
        }
    }
    throw $lastError ??
        new RuntimeException("Falha transacional desconhecida.");
}

function prontoo_schema_file(): string
{
    return __DIR__ . "/schema.sql";
}

function prontoo_schema_release_contract_file(): string
{
    return __DIR__ . "/schema.r6.contract";
}

function prontoo_schema_release_contract_hash(): string
{
    return "673cb62f7b4ac872682d7a2eef6de3565b52ab72f880628a6d951ad2af52ace0";
}

function prontoo_schema_previous_contract_hash(): string
{
    return "1624a19febf4162ab0957a55897da9327fbc7f469ff5da9b047c3dbc226df50d";
}

function prontoo_schema_clear_caches(): void
{
    unset(
        $GLOBALS["PRONTOO_SCHEMA_SQL_CACHE"],
        $GLOBALS["PRONTOO_SCHEMA_STATEMENTS_CACHE"],
        $GLOBALS["PRONTOO_SCHEMA_DEFINITION_MAP_CACHE"],
        $GLOBALS["PRONTOO_SCHEMA_ALLOWED_TABLES_CACHE"],
    );
}

function prontoo_schema_promote_release_contract(): void
{
    $target = prontoo_schema_file();
    $payloadFile = prontoo_schema_release_contract_file();
    $expectedHash = prontoo_schema_release_contract_hash();
    $currentHash = is_file($target) ? hash_file("sha256", $target) : false;

    if (is_string($currentHash) && hash_equals($expectedHash, $currentHash)) {
        prontoo_schema_clear_caches();
        prontoo_fs_unlink($payloadFile, false);
        return;
    }
    if (
        !is_string($currentHash) ||
        !hash_equals(prontoo_schema_previous_contract_hash(), $currentHash)
    ) {
        throw new RuntimeException(
            "O contrato SQL instalado não corresponde à revisão esperada para a migração.",
        );
    }

    $payload = prontoo_fs_read($payloadFile, false);
    if (!is_string($payload) || trim($payload) === "") {
        throw new RuntimeException(
            "O contrato SQL da revisão de múltiplos cargos não foi encontrado.",
        );
    }
    $payload = str_replace(["\r\n", "\r"], "\n", $payload);
    if (!hash_equals($expectedHash, hash("sha256", $payload))) {
        throw new RuntimeException(
            "O contrato SQL da revisão de múltiplos cargos falhou na verificação de integridade.",
        );
    }

    $temporary =
        $target . ".release_1_7_13_5_" . bin2hex(random_bytes(4));
    if (prontoo_fs_write($temporary, $payload, LOCK_EX, true) === false) {
        throw new RuntimeException(
            "Não foi possível preparar o contrato SQL atualizado.",
        );
    }
    prontoo_fs_chmod($temporary, 0644, false);
    if (!prontoo_fs_rename($temporary, $target, true)) {
        prontoo_fs_unlink($temporary, false);
        throw new RuntimeException(
            "Não foi possível publicar o contrato SQL atualizado.",
        );
    }
    prontoo_schema_clear_caches();
    $publishedHash = hash_file("sha256", $target);
    if (!is_string($publishedHash) || !hash_equals($expectedHash, $publishedHash)) {
        throw new RuntimeException(
            "O contrato SQL atualizado não pôde ser confirmado.",
        );
    }
}

function prontoo_schema_sql(): string
{
    $cached = $GLOBALS["PRONTOO_SCHEMA_SQL_CACHE"] ?? null;
    if (is_string($cached)) {
        return $cached;
    }
    $contents = file_get_contents(prontoo_schema_file());
    if ($contents === false || trim($contents) === "") {
        throw new RuntimeException(
            "Contrato SQL da instalação não encontrado.",
        );
    }
    $sql = str_replace(["\r\n", "\r"], "\n", $contents);
    $GLOBALS["PRONTOO_SCHEMA_SQL_CACHE"] = $sql;
    return $sql;
}

function schema_split_sql(string $sql): array
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

function prontoo_schema_statements(): array
{
    $statements = $GLOBALS["PRONTOO_SCHEMA_STATEMENTS_CACHE"] ?? null;
    if (is_array($statements)) {
        return $statements;
    }
    $statements = schema_split_sql(prontoo_schema_sql());
    if (count($statements) !== 75) {
        throw new RuntimeException(
            "Contrato SQL deve conter exatamente 75 tabelas.",
        );
    }
    foreach ($statements as $statement) {
        if (
            !preg_match(
                "/^CREATE\s+TABLE\s+`?pi_[A-Za-z0-9_]+`?\s*\(/i",
                $statement,
            )
        ) {
            throw new RuntimeException(
                "Contrato SQL contém comando não permitido.",
            );
        }
    }
    foreach ($statements as $statement) {
        if (preg_match("/\b(?:DATE|DATETIME|TIMESTAMP)\b/i", $statement)) {
            throw new RuntimeException(
                "Contrato SQL contém tipo temporal nativo; o Prontoo exige Unix UTC em BIGINT.",
            );
        }
    }
    schema_assert_mysql_constraint_compatibility($statements);
    $GLOBALS["PRONTOO_SCHEMA_STATEMENTS_CACHE"] = $statements;
    return $statements;
}

function schema_split_definitions(string $body): array
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

function schema_assert_mysql_constraint_compatibility(array $statements): void
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

        foreach (schema_split_definitions($tableMatch[2]) as $definition) {
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

function prontoo_schema_definition_map(): array
{
    $map = $GLOBALS["PRONTOO_SCHEMA_DEFINITION_MAP_CACHE"] ?? null;
    if (is_array($map)) {
        return $map;
    }
    $map = [];
    foreach (prontoo_schema_statements() as $statement) {
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
        foreach (schema_split_definitions($match[2]) as $definition) {
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

function prontoo_schema_table_names(): array
{
    return array_keys(prontoo_schema_definition_map());
}

function prontoo_schema_columns(): array
{
    $result = [];
    foreach (prontoo_schema_definition_map() as $table => $definition) {
        $result[$table] = $definition["columns"];
    }
    return $result;
}

function prontoo_schema_constraint_names(): array
{
    $result = [];
    foreach (prontoo_schema_definition_map() as $table => $definition) {
        $result[$table] = $definition["objects"];
    }
    return $result;
}

function prontoo_schema_contract_hash(): string
{
    return hash("sha256", prontoo_schema_sql());
}

function allowed_db_table(string $table): string
{
    $allowed = $GLOBALS["PRONTOO_SCHEMA_ALLOWED_TABLES_CACHE"] ?? null;
    if (!is_array($allowed)) {
        $allowed = array_fill_keys(prontoo_schema_table_names(), true);
        $GLOBALS["PRONTOO_SCHEMA_ALLOWED_TABLES_CACHE"] = $allowed;
    }
    if (!isset($allowed[$table])) {
        throw new RuntimeException("Tabela não permitida.");
    }
    return $table;
}

function safe_db_columns(string $columns): string
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

function db_ident(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException("Identificador de banco inválido.");
    }
    return "`{$name}`";
}

function db_schema_error_is_missing_table(Throwable $error): bool
{
    $message = $error->getMessage();
    return str_contains($message, "doesn't exist") ||
        str_contains($message, "Base table or view not found") ||
        str_contains($message, "1146");
}

function run_schema_sql(string $sql): void
{
    if (empty($GLOBALS["PRONTOO_SCHEMA_INSTALLING"])) {
        throw new RuntimeException("Comando estrutural fora da instalação.");
    }
    if (!preg_match("/^\s*CREATE\s+TABLE\b/i", $sql)) {
        throw new RuntimeException(
            "Somente CREATE TABLE canônico é permitido.",
        );
    }
    $GLOBALS["PRONTOO_INSTALL_LAST_SCHEMA_SQL"] = $sql;
    try {
        pdo()->exec($sql);
        if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
            \Prontoo\Core\Integrity\PiIntegrity::proveSchemaOperation(
                $sql,
                true,
                null,
            );
        }
    } catch (Throwable $error) {
        if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
            \Prontoo\Core\Integrity\PiIntegrity::proveSchemaOperation(
                $sql,
                false,
                $error->getMessage(),
            );
        }
        db_log_query_failure($error, $sql);
        throw $error;
    }
}

function db_table_exists(string $table): bool
{
    static $existing = [];
    if (isset($existing[$table])) {
        return true;
    }
    $statement = pdo()->prepare(
        "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1",
    );
    $statement->execute([$table]);
    $exists = $statement->fetchColumn() !== false;
    if ($exists) {
        $existing[$table] = true;
    }
    return $exists;
}

function db_column_exists(string $table, string $column): bool
{
    static $existing = [];
    $key = $table . "|" . $column;
    if (isset($existing[$key])) {
        return true;
    }
    $statement = pdo()->prepare(
        "SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1",
    );
    $statement->execute([$table, $column]);
    $exists = $statement->fetchColumn() !== false;
    if ($exists) {
        $existing[$key] = true;
    }
    return $exists;
}

function db_index_exists(string $table, string $index): bool
{
    static $existing = [];
    $key = $table . "|" . $index;
    if (isset($existing[$key])) {
        return true;
    }
    $statement = pdo()->prepare(
        "SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=? LIMIT 1",
    );
    $statement->execute([$table, $index]);
    $exists = $statement->fetchColumn() !== false;
    if ($exists) {
        $existing[$key] = true;
    }
    return $exists;
}

function schema_lock_file(): string
{
    return storage_path("schema.ready");
}

function schema_mark_ready(): void
{
    $payload = json_encode(
        [
            "revision" => defined("PRONTOO_SCHEMA_REV")
                ? PRONTOO_SCHEMA_REV
                : "",
            "contract" => prontoo_schema_contract_hash(),
            "generated_at" => gmdate("c"),
        ],
        JSON_UNESCAPED_SLASHES,
    );
    if (
        file_put_contents(schema_lock_file(), $payload ?: "{}", LOCK_EX) ===
        false
    ) {
        throw new RuntimeException(
            "Não foi possível registrar o schema instalado.",
        );
    }
    prontoo_fs_chmod(schema_lock_file(), 0640);
}

function schema_validate_complete(): void
{
    $expected = prontoo_schema_definition_map();
    $normalize = static fn(mixed $value): string => strtolower(
        trim((string) $value),
    );

    $actualColumns = [];
    $columnStatement = pdo()->query(
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
    $objectStatement = pdo()->query($objectSql);
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
        $temporal = \Prontoo\Core\Temporal\PiTime::countTemporalViolations(pdo());
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

function schema_seed_meta(): void
{
    $revision = defined("PRONTOO_SCHEMA_REV")
        ? PRONTOO_SCHEMA_REV
        : "prontoo_1_7_20_6_clean_schema_r7_layer2_ledger";
    $statement = pdo()->prepare(
        "INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value),updated_at=VALUES(updated_at)",
    );
    foreach (
        [
            "schema_revision" => $revision,
            "schema_contract_hash" => prontoo_schema_contract_hash(),
            "app_secret" => bin2hex(random_bytes(32)),
        ]
        as $key => $value
    ) {
        $statement->execute([$key, $value, time()]);
    }
}

function schema_cleanup_failed_install(array $tables): void
{
    $connection = pdo();
    $connection->exec("SET FOREIGN_KEY_CHECKS=0");
    try {
        foreach (array_reverse($tables) as $table) {
            $connection->exec("DROP TABLE IF EXISTS " . db_ident($table));
        }
    } finally {
        $connection->exec("SET FOREIGN_KEY_CHECKS=1");
    }
}

function install_fresh_schema(): void
{
    $connection = pdo();
    $tableCount = (int) $connection
        ->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()",
        )
        ->fetchColumn();
    if ($tableCount !== 0) {
        throw new RuntimeException("A instalação limpa requer banco vazio.");
    }

    $GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"] = true;
    $GLOBALS["PRONTOO_SCHEMA_INSTALLING"] = true;
    $created = [];
    try {
        foreach (prontoo_schema_statements() as $statement) {
            if (
                !preg_match(
                    "/^CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?/i",
                    $statement,
                    $match,
                )
            ) {
                throw new RuntimeException("Tabela inválida no contrato SQL.");
            }
            run_schema_sql($statement);
            $created[] = $match[1];
        }
        schema_seed_meta();
        schema_validate_complete();
        schema_mark_ready();
    } catch (Throwable $error) {
        schema_cleanup_failed_install($created);
        prontoo_fs_unlink(schema_lock_file());
        throw $error;
    } finally {
        unset(
            $GLOBALS["PRONTOO_SCHEMA_INSTALLING"],
            $GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"],
        );
    }
}

function schema_apply_pending_release_migrations(): void
{
    // A revisão 1.7.20.6 é exclusivamente de instalação limpa.
    // Nenhuma transformação in-place de dados operacionais é permitida.
    return;
}

function ensure_runtime_schema_minimum(): void
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

function db_assert_tables(array $tables, string $domain): void
{
    foreach ($tables as $table) {
        if (!db_table_exists((string) $table)) {
            throw new RuntimeException("Schema {$domain} incompleto.");
        }
    }
}

function ensure_lead_events_schema(): void
{
    db_assert_tables(["pi_leads", "pi_lead_events"], "de interessados");
}

function ensure_financial_operational_schema(): void
{
    db_assert_tables(
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
