<?php
declare(strict_types=1);

namespace Prontoo\Core\Integrity;


final class PiIntegrity
{
    public const POLICY_VERSION = 'pi-action-ledger-v1';
    public const TABLE_PREFIX = 'pi_';

    private static bool $inside = false;
    private static array $events = [];
    private static array $flushedEvents = [];
    private static array $transactionMarks = [];
    private static bool $shutdownRegistered = false;
    private static ?string $requestId = null;
    private static int $flushErrors = 0;

    private function __construct() {}

    public static function activePrefix(): string
    {
        return self::TABLE_PREFIX;
    }

    public static function physicalTableName(string $logicalTable): string
    {
        return trim($logicalTable, "` \t\n\r\0\x0B");
    }

    public static function rewriteSqlForRuntime(string $sql): string
    {
        return class_exists('Prontoo\\Core\\Temporal\\PiTime')
            ? \Prontoo\Core\Temporal\PiTime::rewriteTemporalFunctions($sql)
            : $sql;
    }

    public static function prepareRuntimeQuery(string $sql, array $params): array
    {
        return class_exists('Prontoo\\Core\\Temporal\\PiTime')
            ? \Prontoo\Core\Temporal\PiTime::prepareRuntimeQuery($sql, $params)
            : [$sql, $params];
    }

    public static function rewriteSchemaSql(string $sql): string
    {
        return class_exists('Prontoo\\Core\\Temporal\\PiTime')
            ? \Prontoo\Core\Temporal\PiTime::rewriteSchemaSql($sql)
            : $sql;
    }

    public static function bootIndexAutotest(int $budgetMs = 450): void
    {
        if (!self::canUseDatabase()) {
            return;
        }
        try {
            self::ensureSystemTables();
        } catch (\Throwable $error) {
            self::logOnce('boot', $error);
        }
    }

    public static function bootIndexLightcheck(int $budgetMs = 80): void
    {
        return;
    }

    public static function ensureGlobalSequence(int $budgetMs = 1800): void
    {
        // Compatibilidade de assinatura: a geração de Seq é nativa no MySQL.
        return;
    }

    public static function runMaestroCycle(int $budgetMs = 120000): array
    {
        self::flushFastEvents();
        return [
            'ok' => true,
            'complete' => true,
            'mode' => self::POLICY_VERSION,
            'requests_confirmed' => 0,
            'operation_events_confirmed' => 0,
            'batches' => 0,
            'remaining' => 0,
            'missing_seq' => 0,
            'errors' => [],
        ];
    }

    public static function beforeQuery(string $sql, array $params): array
    {
        if (self::$inside || !self::canUseDatabase()) {
            return [];
        }
        $kind = self::queryKind($sql);
        if (!in_array($kind, ['insert', 'update', 'delete', 'replace'], true)) {
            return [];
        }
        $table = self::targetTableFromSql($sql);
        if ($table === '' || self::isInternalTable($table)) {
            return [];
        }
        return ['table' => $table, 'kind' => $kind];
    }

    public static function afterQuery(
        string $originalSql,
        string $runtimeSql,
        array $params,
        bool $success,
        int $rowCount,
        float $elapsedMs,
        ?\Throwable $error,
        array $before = [],
    ): void {
        if (self::$inside || !self::canUseDatabase()) {
            return;
        }
        $kind = self::queryKind($originalSql);
        if (!in_array($kind, ['insert', 'update', 'delete', 'replace'], true)) {
            return;
        }
        $table = self::targetTableFromSql($originalSql);
        if ($table === '' || self::isInternalTable($table)) {
            return;
        }
        self::queueEvent(
            'sql_' . $kind,
            $kind,
            $table,
            self::recordPkForEvent($kind, $table),
            $success,
            max(0, $rowCount),
            hash('sha256', self::normalizeSql($runtimeSql)),
            hash('sha256', self::canonicalJson($params)),
            (int) round(max(0.0, $elapsedMs)),
            $error ? mb_substr($error->getMessage(), 0, 220, 'UTF-8') : null,
        );
    }

    public static function proveSchemaOperation(
        string $operation,
        string|bool $table,
        bool|string|null $success = null,
        ?string $error = null,
    ): void {
        if (is_bool($table)) {
            $legacySql = $operation;
            $legacySuccess = $table;
            $legacyError = is_string($success) ? $success : null;
            if (
                !preg_match(
                    '/^\s*CREATE\s+TABLE\s+`?([a-z0-9_]+)`?/i',
                    $legacySql,
                    $match,
                )
            ) {
                return;
            }
            $operation = 'create_table';
            $table = self::physicalTableName((string) ($match[1] ?? ''));
            $success = $legacySuccess;
            $error = $legacyError;
        }
        if (!is_bool($success) || $table === '') {
            throw new \TypeError(
                'Prova estrutural exige operação, tabela, sucesso e erro opcional.',
            );
        }
        if (!empty($GLOBALS['PRONTOO_SCHEMA_INSTALLING'])) {
            return;
        }
        self::queueEvent(
            'schema_' . self::cleanToken($operation, 'operation'),
            'schema',
            self::physicalTableName($table),
            null,
            $success,
            1,
            null,
            null,
            0,
            $error,
        );
    }

    public static function proveExternalFile(
        string $operation,
        string $path,
        bool $success,
        ?string $error = null,
    ): void {
        self::queueEvent(
            'file_' . self::cleanToken($operation, 'operation'),
            'file',
            null,
            hash('sha256', $path),
            $success,
            1,
            null,
            null,
            0,
            $error,
        );
    }

    public static function prepareForWriteTransaction(): void
    {
        if (!self::canUseDatabase()) {
            return;
        }
        self::ensureSystemTables();
    }

    public static function markTransactionStart(): void
    {
        self::$transactionMarks[] = count(self::$events);
    }

    public static function markTransactionCommitted(): void
    {
        array_pop(self::$transactionMarks);
    }

    public static function discardTransactionEvents(): void
    {
        $mark = array_pop(self::$transactionMarks);
        if (is_int($mark) && $mark >= 0) {
            self::$events = array_slice(self::$events, 0, $mark);
        }
    }

    public static function processDeferredEvents(int $budgetMs = 120000, int $batchSize = 120): array
    {
        self::flushFastEvents();
        return [
            'ok' => true,
            'complete' => true,
            'requests_confirmed' => 0,
            'operation_events_confirmed' => 0,
            'batches' => 0,
            'remaining' => 0,
            'errors' => [],
        ];
    }

    public static function flushFastEvents(): void
    {
        if (!self::canUseDatabase() || self::$inside || self::$events === []) {
            return;
        }
        if (self::$flushErrors > 3) {
            self::$events = [];
            return;
        }
        try {
            $pdo = self::pdo();
            if ($pdo->inTransaction()) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $pendingEvents = self::$events;
        $events = array_merge(self::$flushedEvents, $pendingEvents);
        self::$events = [];
        try {
            self::$inside = true;
            self::ensureSystemTables();
            $pdo = self::pdo();
            $requestId = (string) ($GLOBALS['PRONTOO_ACTION_LEDGER_REQUEST_ID'] ?? self::requestId());
            $ledgerId = (int) ($GLOBALS['PRONTOO_ACTION_LEDGER_ID'] ?? 0);
            $createdAt = time();
            $tables = [];
            foreach ($events as $event) {
                $table = trim((string) ($event['table_name'] ?? ''));
                if ($table !== '') {
                    $tables[$table] = true;
                }
            }
            $payload = [
                'request_id' => $requestId,
                'route' => function_exists('route') ? (string) \route() : (string) ($_GET['r'] ?? ''),
                'clinic_id' => self::sessionInt('clinic_id'),
                'user_id' => self::sessionInt('uid'),
                'events' => array_values($events),
                'event_count' => count($events),
                'affected_tables' => array_keys($tables),
                'policy_version' => self::POLICY_VERSION,
                'finalized_at' => $createdAt,
            ];
            $payloadJson = self::canonicalJson($payload);
            $mutationHash = hash_hmac('sha256', $payloadJson, self::secret());
            $affectedJson = self::canonicalJson(array_keys($tables));

            if ($ledgerId > 0) {
                $statement = $pdo->prepare(
                    "UPDATE pi_action_ledger SET status='committed',mutation_hash=?,mutation_count=?,affected_tables_json=?,mutation_json=?,finalized_at=? WHERE id=? AND allowed=1",
                );
                $statement->execute([
                    $mutationHash,
                    count($events),
                    $affectedJson,
                    $payloadJson,
                    $createdAt,
                    $ledgerId,
                ]);
                if ($statement->rowCount() <= 0) {
                    throw new \RuntimeException('Ledger autorizado ausente durante finalização da mutação.');
                }
            } else {
                $authorizationHash = hash_hmac(
                    'sha256',
                    'system|' . $requestId . '|' . $mutationHash,
                    self::secret(),
                );
                $statement = $pdo->prepare(
                    "INSERT INTO pi_action_ledger (request_id,clinic_id,user_id,route,action_key,module_key,operation_key,scope,role_code,allowed,status,reason,authorization_hash,mutation_hash,mutation_count,affected_tables_json,mutation_json,context_json,policy_version,created_at,finalized_at) VALUES (?,?,?,?,?,?,?,?,?,1,'committed',?,?,?,?,?,?,?,?,?,?)",
                );
                $statement->execute([
                    $requestId,
                    self::sessionInt('clinic_id'),
                    self::sessionInt('uid'),
                    mb_substr((string) ($payload['route'] ?? 'system'), 0, 80, 'UTF-8') ?: 'system',
                    'system_mutation',
                    'integrity',
                    'write',
                    'system',
                    function_exists('session_clinic_role_code') ? (string) \session_clinic_role_code() : null,
                    'Mutação interna registrada pelo núcleo de invariantes.',
                    $authorizationHash,
                    $mutationHash,
                    count($events),
                    $affectedJson,
                    $payloadJson,
                    $payloadJson,
                    self::POLICY_VERSION,
                    $createdAt,
                    $createdAt,
                ]);
                $GLOBALS['PRONTOO_ACTION_LEDGER_ID'] = (int) $pdo->lastInsertId();
                $GLOBALS['PRONTOO_ACTION_LEDGER_REQUEST_ID'] = $requestId;
            }
            self::$flushedEvents = $events;
        } catch (\Throwable $error) {
            self::$flushErrors++;
            self::$events = array_merge($pendingEvents, self::$events);
            self::logOnce('action-ledger-flush', $error);
        } finally {
            self::$inside = false;
        }
    }

    public static function rowHash(array $row): string
    {
        ksort($row);
        return hash_hmac('sha256', self::canonicalJson($row), self::secret());
    }

    public static function canonicalJson(mixed $value): string
    {
        return json_encode(
            self::canonicalize($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        ) ?: 'null';
    }

    private static function queueEvent(
        string $eventKey,
        string $operation,
        ?string $table,
        ?string $recordPk,
        bool $success,
        int $rowCount,
        ?string $sqlFingerprint,
        ?string $paramsHash,
        int $elapsedMs,
        ?string $error,
    ): void {
        if (!self::canUseDatabase()) {
            return;
        }
        self::registerShutdown();
        self::$events[] = [
            'event_key' => mb_substr($eventKey, 0, 80, 'UTF-8'),
            'operation' => mb_substr($operation, 0, 24, 'UTF-8'),
            'table_name' => $table !== null ? mb_substr($table, 0, 120, 'UTF-8') : null,
            'record_pk' => $recordPk,
            'success' => $success,
            'row_count' => max(0, $rowCount),
            'sql_fingerprint' => $sqlFingerprint,
            'params_hash' => $paramsHash,
            'elapsed_ms' => max(0, $elapsedMs),
            'error' => $error !== null ? mb_substr($error, 0, 220, 'UTF-8') : null,
        ];
    }

    private static function registerShutdown(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function([self::class, 'flushFastEvents']);
    }

    private static function ensureSystemTables(): void
    {
        foreach (['pi_action_ledger', 'pi_integrity_alerts'] as $table) {
            if (!self::tableExists($table)) {
                throw new \RuntimeException("Tabela de integridade ausente: {$table}.");
            }
        }
    }

    private static function tableExists(string $table): bool
    {
        $stmt = self::pdo()->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1',
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private static function canUseDatabase(): bool
    {
        return function_exists('has_cfg') && \has_cfg() && function_exists('pdo');
    }

    private static function pdo(): \PDO
    {
        return \pdo();
    }

    private static function sessionInt(string $key): ?int
    {
        $value = (int) ($_SESSION[$key] ?? 0);
        return $value > 0 ? $value : null;
    }

    private static function isInternalTable(string $table): bool
    {
        return in_array(strtolower($table), [
            'pi_action_ledger',
            'pi_integrity_alerts',
            'pi_audit',
            'pi_error_events',
            'pi_scope_violations',
            'pi_platform_counters',
            'pi_clinic_daily_stats',
            'pi_maestro_job_runs',
            'pi_maestro_job_stats',
            'pi_meta',
        ], true);
    }

    private static function targetTableFromSql(string $sql): string
    {
        $patterns = [
            '/^\s*INSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-z0-9_]+)`?/i',
            '/^\s*REPLACE\s+INTO\s+`?([a-z0-9_]+)`?/i',
            '/^\s*UPDATE\s+`?([a-z0-9_]+)`?/i',
            '/^\s*DELETE\s+FROM\s+`?([a-z0-9_]+)`?/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql, $match)) {
                return self::physicalTableName((string) ($match[1] ?? ''));
            }
        }
        return '';
    }

    private static function queryKind(string $sql): string
    {
        return preg_match('/^\s*([a-z]+)/i', $sql, $match)
            ? strtolower((string) $match[1])
            : '';
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));
    }

    private static function recordPkForEvent(string $kind, string $table): ?string
    {
        if (!in_array($kind, ['insert', 'replace'], true)) {
            return null;
        }
        try {
            $id = (string) self::pdo()->lastInsertId();
            return $id !== '' && $id !== '0' ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }
        return $value;
    }

    private static function secret(): string
    {
        try {
            return function_exists('secret_key')
                ? (string) \secret_key()
                : (string) (\cfg()['secret'] ?? 'prontoo-integrity');
        } catch (\Throwable) {
            return 'prontoo-integrity';
        }
    }

    private static function logOnce(string $stage, \Throwable $error): void
    {
        static $logged = [];
        $key = $stage . '|' . $error->getMessage();
        if (isset($logged[$key])) {
            return;
        }
        $logged[$key] = true;
        error_log('[Prontoo integrity ' . $stage . '] ' . $error->getMessage());
    }

    private static function requestId(): string
    {
        if (self::$requestId !== null) {
            return self::$requestId;
        }
        try {
            return self::$requestId = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return self::$requestId = md5(uniqid('prontoo', true));
        }
    }

    private static function cleanToken(string $value, string $fallback): string
    {
        return preg_replace('/[^a-z0-9_-]+/i', '_', trim($value)) ?: $fallback;
    }
}
