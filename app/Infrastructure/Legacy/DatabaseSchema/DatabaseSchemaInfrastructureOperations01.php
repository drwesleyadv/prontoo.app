<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Legacy\DatabaseSchema;

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

final class DatabaseSchemaInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function db_runtime_notice_once(string $key, string $message): void
    
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
            : dirname((dirname(__DIR__, 3) . '/Database'), 2) . "/ssd/cache/runtime-notices";
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

    public static function db_mysql_version(PDO $connection): string
    
    {
    
        $value = $connection->query("SELECT VERSION()")?->fetchColumn();
        return mb_trim((string) $value);
    
    }

    public static function db_assert_mysql_runtime(PDO $connection): void
    
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
    
            $GLOBALS["PRONTOO_INNODB_STRICT_MODE"] = (string) $strict === "1";
        } catch (Throwable $error) {
            db_runtime_notice_once(
                "innodb_strict_mode_unavailable",
                "[Prontoo MySQL] Não foi possível consultar innodb_strict_mode: " .
                    $error->getMessage(),
            );
        }
    
    }

    public static function db_session_sql_modes(PDO $connection): array
    
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

    public static function db_session_sql_mode_is_safe(array $modes): bool
    
    {
    
        $set = array_fill_keys(array_map("strtoupper", $modes), true);
        $strict = isset($set["STRICT_ALL_TABLES"]) ||
            isset($set["STRICT_TRANS_TABLES"]);
        return $strict &&
            isset($set["ONLY_FULL_GROUP_BY"]) &&
            isset($set["ERROR_FOR_DIVISION_BY_ZERO"]) &&
            isset($set["NO_ENGINE_SUBSTITUTION"]);
    
    }

    public static function db_apply_mysql_session_contract(
        PDO $connection,
        bool $strictMode = true,
    ): void 
    {
    
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

    public static function pdo(): PDO
    
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
    
        $host = mb_trim((string) $config["db_host"]);
        $database = mb_trim((string) $config["db_name"]);
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
    
        $connection = PDO::connect(
            "mysql:host={$host};dbname={$database};charset=utf8mb4",
            (string) $config["db_user"],
            (string) $config["db_pass"],
            $options,
        );
        db_assert_mysql_runtime($connection);
        db_apply_mysql_session_contract($connection, true);
        return $connection;
    
    }

    public static function db_retryable_conflict(Throwable $error): bool
    
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

    public static function db_retry_delay_us(int $attempt): int
    
    {
    
        $base = min(300000, 40000 + max(0, $attempt) * 65000);
        try {
            return $base + random_int(0, 20000);
        } catch (Throwable $error) {
            error_log("[Prontoo retry jitter] " . $error->getMessage());
            return $base;
        }
    
    }

    public static function db_log_query_failure(Throwable $error, string $sql): void
    
    {
    
        $message = function_exists("privacy_sanitize_error_message")
            ? privacy_sanitize_error_message($error, 220)
            : mb_substr($error->getMessage(), 0, 220);
        $sample = preg_replace("/\s+/", " ", trim($sql)) ?? trim($sql);
        error_log("[Prontoo SQL] {$message} | " . mb_substr($sample, 0, 420));
    
    }

    public static function db_reject_runtime_ddl(string $sql): void
    
    {
    
        if (!preg_match("/^\s*(CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i", $sql)) {
            return;
        }
        if (
            !class_exists("\\Prontoo\\Core\\Database\\SchemaMutationLock") ||
            !\Prontoo\Core\Database\SchemaMutationLock::isActive()
        ) {
            throw new RuntimeException(
                "A estrutura do banco está congelada fora da janela privada do instalador.",
            );
        }
    
    }

    public static function db_last_insert_id(): int
    
    {
    
        return (int) ($GLOBALS["PRONTOO_LAST_INSERT_ID"] ?? 0);
    
    }

    public static function db_temp_space_error(Throwable $error): bool
    
    {
    
        $message = $error->getMessage();
        return str_contains($message, "Errcode: 28") ||
            str_contains($message, "No space left on device") ||
            str_contains($message, "Disk got full writing");
    
    }

    public static function db_prepare_write_transaction(): void
    
    {
    
        if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
            \Prontoo\Infrastructure\Integrity\PiIntegrity::prepareForWriteTransaction();
        }
    
    }

    public static function db_begin_transaction(): void
    
    {
    
        $connection = pdo();
        if ($connection->inTransaction()) {
            return;
        }
        db_prepare_write_transaction();
        $connection->beginTransaction();
        if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
            \Prontoo\Infrastructure\Integrity\PiIntegrity::markTransactionStart();
        }
    
    }

    public static function db_commit(): void
    
    {
    
        $connection = pdo();
        if (!$connection->inTransaction()) {
            return;
        }
        try {
            if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                \Prontoo\Infrastructure\Integrity\PiIntegrity::flushTransactionEvents();
            }
            $connection->commit();
            if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                \Prontoo\Infrastructure\Integrity\PiIntegrity::markTransactionCommitted();
            }
        } catch (Throwable $error) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                \Prontoo\Infrastructure\Integrity\PiIntegrity::discardTransactionEvents();
            }
            throw $error;
        }
    
    }

    public static function db_rollback(): void
    
    {
    
        $connection = pdo();
        if (!$connection->inTransaction()) {
            return;
        }
        $connection->rollBack();
        if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
            \Prontoo\Infrastructure\Integrity\PiIntegrity::discardTransactionEvents();
        }
    
    }

    public static function db_tx(callable $callback): mixed
    
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

    public static function prontoo_schema_file(): string
    
    {
    
        return (dirname(__DIR__, 3) . '/Database') . "/schema.sql";
    
    }

    public static function prontoo_operational_schema_contract_file(): string
    
    {
    
        return (dirname(__DIR__, 3) . '/Database') . "/operational-schema.contract.json";
    
    }

    public static function prontoo_operational_schema_contract(): array
    
    {
    
        $cached = $GLOBALS["PRONTOO_OPERATIONAL_SCHEMA_CONTRACT_CACHE"] ?? null;
        if (is_array($cached)) {
            return $cached;
        }
    
        $file = prontoo_operational_schema_contract_file();
        $raw = is_file($file) ? file_get_contents($file) : false;
        if (!is_string($raw) || trim($raw) === "") {
            throw new RuntimeException(
                "Contrato operacional do schema não encontrado.",
            );
        }
        try {
            $contract = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException(
                "Contrato operacional do schema inválido.",
                0,
                $error,
            );
        }
        if (!is_array($contract)) {
            throw new RuntimeException(
                "Contrato operacional do schema inválido.",
            );
        }
        $GLOBALS["PRONTOO_OPERATIONAL_SCHEMA_CONTRACT_CACHE"] = $contract;
        return $contract;
    
    }

    public static function prontoo_schema_expected_table_names(): array
    
    {
    
        $contract = prontoo_operational_schema_contract();
        $tables = array_keys((array) ($contract["tables"] ?? []));
        foreach (["redesigned_tables", "new_support_tables"] as $key) {
            foreach ((array) ($contract[$key] ?? []) as $table) {
                $table = mb_trim((string) $table);
                if ($table !== "") {
                    $tables[] = $table;
                }
            }
        }
        $tables = array_values(array_unique($tables));
        sort($tables, SORT_STRING);
    
        $expectedCount = (int) ($contract["schema_table_count"] ?? 0);
        if ($expectedCount <= 0 || count($tables) !== $expectedCount) {
            throw new RuntimeException(
                "Contrato operacional não define a coleção completa de tabelas do schema.",
            );
        }
        foreach ($tables as $table) {
            if (!preg_match('/^pi_[a-z0-9_]+$/', $table)) {
                throw new RuntimeException(
                    "Contrato operacional contém nome de tabela inválido.",
                );
            }
        }
        return $tables;
    
    }

    public static function prontoo_schema_release_contract_file(): string
    
    {
    
        return (dirname(__DIR__, 3) . '/Database') . "/schema.r6.contract";
    
    }

    public static function prontoo_schema_release_contract_hash(): string
    
    {
    
        return "673cb62f7b4ac872682d7a2eef6de3565b52ab72f880628a6d951ad2af52ace0";
    
    }

    public static function prontoo_schema_previous_contract_hash(): string
    
    {
    
        return "1624a19febf4162ab0957a55897da9327fbc7f469ff5da9b047c3dbc226df50d";
    
    }

    public static function prontoo_schema_clear_caches(): void
    
    {
    
        unset(
            $GLOBALS["PRONTOO_SCHEMA_SQL_CACHE"],
            $GLOBALS["PRONTOO_SCHEMA_STATEMENTS_CACHE"],
            $GLOBALS["PRONTOO_SCHEMA_DEFINITION_MAP_CACHE"],
            $GLOBALS["PRONTOO_SCHEMA_ALLOWED_TABLES_CACHE"],
            $GLOBALS["PRONTOO_OPERATIONAL_SCHEMA_CONTRACT_CACHE"],
        );
    
    }

    public static function prontoo_schema_promote_release_contract(): void
    
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

    public static function prontoo_schema_sql(): string
    
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
}
