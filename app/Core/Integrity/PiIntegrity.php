<?php
declare(strict_types=1);
namespace Prontoo\Core\Integrity;
final class PiIntegrity
{
    public const POLICY_VERSION = "pi-checksum-v2-maestro-unix-utc";
    public const TABLE_PREFIX = "pi_";
    public const CHAIN_NAME = "prontoo-checksum-main";
    private static bool $inside = false;
    private static ?bool $ready = null;
    private static array $events = [];
    private static bool $shutdownRegistered = false;
    private static ?string $requestId = null;
    private static ?string $requestStartChecksum = null;
    private static int $flushErrors = 0;
    private static array $transactionMarks = [];
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
        if (class_exists("\\Prontoo\\Core\\Temporal\\PiTime")) {
            return \Prontoo\Core\Temporal\PiTime::rewriteTemporalFunctions(
                $sql,
            );
        }
        return $sql;
    }

    public static function prepareRuntimeQuery(
        string $sql,
        array $params,
    ): array {
        if (class_exists("\\Prontoo\\Core\\Temporal\\PiTime")) {
            return \Prontoo\Core\Temporal\PiTime::prepareRuntimeQuery(
                $sql,
                $params,
            );
        }
        return [$sql, $params];
    }

    public static function rewriteSchemaSql(string $sql): string
    {
        if (class_exists("\\Prontoo\\Core\\Temporal\\PiTime")) {
            return \Prontoo\Core\Temporal\PiTime::rewriteSchemaSql($sql);
        }
        return $sql;
    }

    public static function bootIndexAutotest(int $budgetMs = 450): void
    {
        if (!self::canUseDatabase()) {
            return;
        }
        try {
            self::ensureSystemTables();
            self::loadRequestStartChecksum();
        } catch (\Throwable $e) {
            self::logOnce("boot", $e);
        }
    }
    public static function bootIndexLightcheck(int $budgetMs = 80): void
    {
        return;
    }
    public static function ensureGlobalSequence(int $budgetMs = 1800): void
    {
        if (!self::canUseDatabase() || !class_exists(PiSequence::class)) {
            return;
        }
        try {
            PiSequence::ensure(self::pdo(), max(100, $budgetMs));
        } catch (\Throwable $error) {
            self::logOnce("sequence", $error);
        }
    }

    public static function runMaestroCycle(int $budgetMs = 120000): array
    {
        $startedAt = microtime(true);
        $result = [
            "ok" => true,
            "complete" => true,
            "mode" => self::POLICY_VERSION,
            "requests_confirmed" => 0,
            "operation_events_confirmed" => 0,
            "batches" => 0,
            "remaining" => 0,
            "missing_seq" => 0,
            "errors" => [],
        ];
        if (!self::canUseDatabase()) {
            return $result;
        }
        try {
            self::ensureSystemTables();
            self::queueEvent(
                "maestro_healthcheck",
                "healthcheck",
                "pi_checksum_state",
                "scope_key=global",
                true,
                1,
                hash("sha256", "PRONTOO_CHECKSUM_MAESTRO"),
                hash("sha256", (string) $budgetMs),
                0,
                null,
            );
            self::flushFastEvents();
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            $availableMs = max(1000, $budgetMs - $elapsedMs);
            $batchSize = defined("PRONTOO_MAESTRO_PI_BATCH_SIZE")
                ? (int) PRONTOO_MAESTRO_PI_BATCH_SIZE
                : 120;
            $result = array_replace(
                $result,
                self::processDeferredEvents($availableMs, $batchSize),
            );
            $sequenceBudget = defined("PRONTOO_MAESTRO_SEQUENCE_BUDGET_MS")
                ? (int) PRONTOO_MAESTRO_SEQUENCE_BUDGET_MS
                : (int) min(max(1000, intdiv($availableMs, 3)), 12000);
            self::ensureGlobalSequence($sequenceBudget);
        } catch (\Throwable $error) {
            $result["ok"] = false;
            $result["complete"] = false;
            $result["errors"][] = $error->getMessage();
            self::logOnce("maestro", $error);
        }
        return $result;
    }
    public static function beforeQuery(string $sql, array $params): array
    {
        if (self::$inside || !self::canUseDatabase()) {
            return [];
        }
        $kind = self::queryKind($sql);
        if (!in_array($kind, ["insert", "update", "delete", "replace"], true)) {
            return [];
        }
        $table = self::targetTableFromSql($sql);
        $physical = $table !== "" ? self::physicalTableName($table) : "";
        if ($physical === "" || self::isInternalTable($physical)) {
            return [];
        }
        return [
            "request_checksum" => self::loadRequestStartChecksum(),
            "table" => $physical,
            "kind" => $kind,
        ];
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
        if (!in_array($kind, ["insert", "update", "delete", "replace"], true)) {
            return;
        }
        $table = self::targetTableFromSql($originalSql);
        $physical = $table !== "" ? self::physicalTableName($table) : "";
        if ($physical === "" || self::isInternalTable($physical)) {
            return;
        }
        try {
            if ($success && in_array($kind, ["insert", "replace"], true)) {
                $lastId = self::lastInsertIdSafe();
                $autoPk = self::autoIncrementPrimaryKeyColumn($physical);
                if ($autoPk !== null && $lastId !== "" && (int) $lastId > 0) {
                    if (class_exists("\\Prontoo\\Core\\Temporal\\PiTime")) {
                        \Prontoo\Core\Temporal\PiTime::normalizeInsertedTemporalDefaults(
                            self::pdo(),
                            $physical,
                            $lastId,
                        );
                    }
                    if (class_exists(PiSequence::class)) {
                        PiSequence::assignById(self::pdo(), $physical, $lastId);
                    }
                }
            }
        } catch (\Throwable $e) {
            self::logOnce("seq", $e);
        }
        self::queueEvent(
            "sql_" . $kind,
            $kind,
            $physical,
            self::recordPkForEvent($kind, $physical),
            $success,
            $rowCount,
            hash_hmac(
                "sha256",
                self::normalizeSql($originalSql),
                self::secret(),
            ),
            hash_hmac("sha256", self::canonicalJson($params), self::secret()),
            (int) round($elapsedMs),
            $error ? $error->getMessage() : null,
        );
    }
    public static function proveSchemaOperation(
        string $sql,
        bool $success,
        ?string $error = null,
    ): void {
        if (!self::canUseDatabase()) {
            return;
        }
        $physical = self::targetTableFromSql($sql);
        if ($physical !== "") {
            $physical = self::physicalTableName($physical);
        }
        self::queueEvent(
            "schema_" . ($success ? "ok" : "fail"),
            "schema",
            $physical ?: null,
            null,
            $success,
            0,
            hash_hmac("sha256", self::normalizeSql($sql), self::secret()),
            null,
            0,
            $error,
        );
    }
    public static function proveExternalFile(
        string $table,
        string $recordPk,
        string $path,
        array $meta = [],
    ): void {
        if (!self::canUseDatabase()) {
            return;
        }
        $hash = is_file($path)
            ? hash_file("sha256", $path)
            : hash("sha256", "missing:" . $path);
        $meta["file_hash"] = $hash;
        $meta["file_size"] = is_file($path) ? (int) @filesize($path) : 0;
        self::queueEvent(
            "external_file",
            "file",
            self::physicalTableName($table),
            $recordPk,
            is_file($path),
            1,
            $hash,
            hash_hmac("sha256", self::canonicalJson($meta), self::secret()),
            0,
            is_file($path) ? null : "Arquivo ausente",
        );
    }
    public static function prepareForWriteTransaction(): void
    {
        if (!self::canUseDatabase()) {
            return;
        }
        self::ensureSystemTables();
        if (class_exists(PiSequence::class)) {
            PiSequence::ensureCounterTable(self::pdo());
        }
    }
    public static function markTransactionStart(): void
    {
        self::$transactionMarks[] = count(self::$events);
    }
    public static function markTransactionCommitted(): void
    {
        if (self::$transactionMarks !== []) {
            array_pop(self::$transactionMarks);
        }
    }
    public static function discardTransactionEvents(): void
    {
        $mark =
            self::$transactionMarks !== []
                ? array_pop(self::$transactionMarks)
                : null;
        if ($mark !== null) {
            self::$events = array_slice(self::$events, 0, max(0, $mark));
        }
    }
    public static function processDeferredEvents(
        int $budgetMs = 30000,
        int $batchSize = 500,
    ): array {
        $started = microtime(true);
        $out = [
            "ok" => true,
            "mode" => "checksum-pending-maestro",
            "requests_confirmed" => 0,
            "operation_events_confirmed" => 0,
            "batches" => 0,
            "remaining" => 0,
            "complete" => true,
            "errors" => [],
        ];
        if (!self::canUseDatabase() || self::$inside) {
            return $out;
        }
        try {
            self::$inside = true;
            self::ensureSystemTables();
            $pdo = self::pdo();
            $batchSize = max(1, min(250, $batchSize));
            while ((microtime(true) - $started) * 1000 < max(1000, $budgetMs)) {
                $pdo->beginTransaction();
                try {
                    self::ensureStateRow($pdo);
                    $state = self::stateRow($pdo, true);
                    $confirmedChecksum =
                        (string) ($state["confirmed_checksum"] ??
                            ($state["checksum"] ?? self::genesisChecksum()));
                    $confirmedEventId =
                        (int) ($state["confirmed_event_id"] ?? 0);
                    $previousBatchHash =
                        (string) ($state["last_batch_hash"] ??
                            $confirmedChecksum);
                    $st = $pdo->prepare(
                        "SELECT * FROM pi_checksum_events WHERE status='pending' AND id>? ORDER BY id ASC LIMIT $batchSize FOR UPDATE",
                    );
                    $st->execute([$confirmedEventId]);
                    $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    if (!$rows) {
                        $pdo->commit();
                        break;
                    }
                    $cursor = $confirmedChecksum;
                    $rollup = [];
                    $opCount = 0;
                    $fromId = (int) $rows[0]["id"];
                    $toId = $fromId;
                    foreach ($rows as $row) {
                        $row = array_change_key_case($row, CASE_LOWER);
                        $id = (int) $row["id"];
                        $toId = $id;
                        $payloadJson = (string) ($row["payload_json"] ?? "");
                        $payloadHash = hash_hmac(
                            "sha256",
                            $payloadJson,
                            self::secret(),
                        );
                        $expectedEvent = self::pendingEventChecksum(
                            $cursor,
                            $payloadHash,
                            (string) $row["request_id"],
                            (int) $row["event_count"],
                            (int) $row["created_at"],
                        );
                        $expectedPending = self::pendingNextChecksum(
                            $cursor,
                            $expectedEvent,
                        );
                        if (
                            !hash_equals(
                                $cursor,
                                (string) $row["previous_pending_checksum"],
                            ) ||
                            !hash_equals(
                                $payloadHash,
                                (string) $row["payload_hash"],
                            ) ||
                            !hash_equals(
                                $expectedEvent,
                                (string) $row["event_checksum"],
                            ) ||
                            !hash_equals(
                                $expectedPending,
                                (string) $row["pending_checksum"],
                            )
                        ) {
                            self::recordIntegrityAlert(
                                $pdo,
                                "checksum_mismatch",
                                "critical",
                                "Divergência em evento pendente de checksum.",
                                [
                                    "id" => $id,
                                    "expected_previous" => $cursor,
                                    "stored_previous" =>
                                        (string) $row[
                                            "previous_pending_checksum"
                                        ],
                                    "expected_payload_hash" => $payloadHash,
                                    "stored_payload_hash" =>
                                        (string) $row["payload_hash"],
                                    "expected_event_checksum" => $expectedEvent,
                                    "stored_event_checksum" =>
                                        (string) $row["event_checksum"],
                                    "expected_pending_checksum" => $expectedPending,
                                    "stored_pending_checksum" =>
                                        (string) $row["pending_checksum"],
                                ],
                            );
                            $pdo->prepare(
                                "UPDATE pi_checksum_events SET status='failed', error_message='checksum_mismatch' WHERE id=?",
                            )->execute([$id]);
                            $pdo->commit();
                            $out["ok"] = false;
                            $out["complete"] = false;
                            $out["errors"][] = "checksum_mismatch:event#" . $id;
                            return $out;
                        }
                        $cursor = $expectedPending;
                        $opCount += (int) $row["event_count"];
                        $rollup[] = [
                            "id" => $id,
                            "request_id" => (string) $row["request_id"],
                            "payload_hash" => $payloadHash,
                            "event_checksum" => $expectedEvent,
                            "pending_checksum" => $expectedPending,
                            "event_count" => (int) $row["event_count"],
                        ];
                    }
                    $batchStarted = time();
                    $batchPayload = [
                        "from_event_id" => $fromId,
                        "to_event_id" => $toId,
                        "request_rows" => count($rows),
                        "operation_events" => $opCount,
                        "previous_batch_hash" => $previousBatchHash,
                        "previous_confirmed_checksum" => $confirmedChecksum,
                        "confirmed_checksum" => $cursor,
                        "events" => $rollup,
                        "policy_version" => self::POLICY_VERSION,
                    ];
                    $batchHash = hash_hmac(
                        "sha256",
                        self::canonicalJson($batchPayload),
                        self::secret(),
                    );
                    $duration = (int) round(
                        (microtime(true) - $started) * 1000,
                    );
                    $ins = $pdo->prepare(
                        "INSERT INTO pi_checksum_batches (from_event_id,to_event_id,request_rows,operation_events,previous_batch_hash,batch_hash,previous_confirmed_checksum,confirmed_checksum,status,started_at,finished_at,duration_ms,policy_version) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    );
                    $ins->execute([
                        $fromId,
                        $toId,
                        count($rows),
                        $opCount,
                        $previousBatchHash,
                        $batchHash,
                        $confirmedChecksum,
                        $cursor,
                        "confirmed",
                        $batchStarted,
                        time(),
                        $duration,
                        self::POLICY_VERSION,
                    ]);
                    $batchId = (int) $pdo->lastInsertId();
                    if (class_exists(PiSequence::class)) {
                        PiSequence::assignById(
                            $pdo,
                            "pi_checksum_batches",
                            $batchId,
                        );
                    }
                    $pdo->prepare(
                        "UPDATE pi_checksum_events SET status='confirmed', batch_id=?, confirmed_at=? WHERE status='pending' AND id BETWEEN ? AND ?",
                    )->execute([$batchId, time(), $fromId, $toId]);
                    $pdo->prepare(
                        "UPDATE pi_checksum_state SET previous_confirmed_checksum=confirmed_checksum, confirmed_checksum=?, checksum=?, confirmed_event_id=?, confirmed_batch_id=?, last_batch_hash=?, pending_count=GREATEST(pending_count-?,0), batch_count=batch_count+1, updated_at=? WHERE scope_key='global'",
                    )->execute([
                        $cursor,
                        $cursor,
                        $toId,
                        $batchId,
                        $batchHash,
                        $opCount,
                        time(),
                    ]);
                    $pdo->commit();
                    $out["requests_confirmed"] += count($rows);
                    $out["operation_events_confirmed"] += $opCount;
                    $out["batches"]++;
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
            $out["remaining"] = self::pendingEventRows();
            $out["complete"] = (int) $out["remaining"] === 0;
        } catch (\Throwable $e) {
            $out["ok"] = false;
            $out["complete"] = false;
            $out["errors"][] = $e->getMessage();
            self::logOnce("deferred", $e);
        } finally {
            self::$inside = false;
        }
        return $out;
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
            $basePdo = self::pdo();
            if ($basePdo->inTransaction()) {
                return;
            }
        } catch (\Throwable) {
            return;
        }
        $events = self::$events;
        self::$events = [];
        try {
            self::$inside = true;
            self::ensureSystemTables();
            $basePdo = self::pdo();
            $ownTx = !$basePdo->inTransaction();
            $pdo = $ownTx ? self::dedicatedPdo() : $basePdo;
            if ($ownTx) {
                $pdo->beginTransaction();
            }
            self::ensureStateRow($pdo);
            $state = self::stateRow($pdo, true);
            $previousPending =
                (string) ($state["pending_checksum"] ??
                    ($state["confirmed_checksum"] ??
                        ($state["checksum"] ?? self::genesisChecksum())));
            $requestId = self::requestId();
            $createdAt = time();
            $payload = [
                "request_id" => $requestId,
                "started_checksum" => self::loadRequestStartChecksum(),
                "clinic_id" => self::sessionInt("clinic_id"),
                "user_id" => self::sessionInt("uid"),
                "route" => function_exists("route")
                    ? (string) \route()
                    : (string) ($_GET["r"] ?? ""),
                "role_code" => function_exists("session_clinic_role_code")
                    ? (string) \session_clinic_role_code()
                    : (string) ($_SESSION["clinic_role"] ?? ""),
                "events" => array_values($events),
                "event_count" => count($events),
                "created_at" => $createdAt,
                "policy_version" => self::POLICY_VERSION,
            ];
            $payloadJson = self::canonicalJson($payload);
            $payloadHash = hash_hmac("sha256", $payloadJson, self::secret());
            $eventChecksum = self::pendingEventChecksum(
                $previousPending,
                $payloadHash,
                $requestId,
                count($events),
                $createdAt,
            );
            $pendingChecksum = self::pendingNextChecksum(
                $previousPending,
                $eventChecksum,
            );
            $firstRoute = mb_substr(
                (string) ($payload["route"] ?? ""),
                0,
                80,
                "UTF-8",
            );
            $firstRole = mb_substr(
                (string) ($payload["role_code"] ?? ""),
                0,
                40,
                "UTF-8",
            );
            $firstTable = null;
            foreach ($events as $ev) {
                if (!empty($ev["table_name"])) {
                    $firstTable = (string) $ev["table_name"];
                    break;
                }
            }
            $insert = $pdo->prepare(
                "INSERT INTO pi_checksum_events (request_id,clinic_id,user_id,route,role_code,event_count,first_table,payload_hash,payload_json,previous_pending_checksum,event_checksum,pending_checksum,status,policy_version,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            );
            $insert->execute([
                $requestId,
                $payload["clinic_id"],
                $payload["user_id"],
                $firstRoute !== "" ? $firstRoute : null,
                $firstRole !== "" ? $firstRole : null,
                count($events),
                $firstTable !== null
                    ? mb_substr($firstTable, 0, 120, "UTF-8")
                    : null,
                $payloadHash,
                $payloadJson,
                $previousPending,
                $eventChecksum,
                $pendingChecksum,
                "pending",
                self::POLICY_VERSION,
                $createdAt,
            ]);
            $eventId = (int) $pdo->lastInsertId();
            if (class_exists(PiSequence::class)) {
                PiSequence::assignById($pdo, "pi_checksum_events", $eventId);
            }
            $pdo->prepare(
                "UPDATE pi_checksum_state SET previous_pending_checksum=pending_checksum, pending_checksum=?, pending_event_id=?, pending_count=pending_count+?, event_count=event_count+?, request_count=request_count+1, last_request_id=?, updated_at=? WHERE scope_key='global'",
            )->execute([
                $pendingChecksum,
                $eventId,
                count($events),
                count($events),
                $requestId,
                $createdAt,
            ]);
            if ($ownTx) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            self::$flushErrors++;
            $domainTx =
                isset($ownTx) &&
                !$ownTx &&
                isset($pdo) &&
                $pdo instanceof \PDO &&
                $pdo->inTransaction();
            try {
                if (
                    isset($ownTx) &&
                    $ownTx &&
                    isset($pdo) &&
                    $pdo instanceof \PDO &&
                    $pdo->inTransaction()
                ) {
                    $pdo->rollBack();
                }
            } catch (\Throwable $recoverableError) {
                error_log(
                    "[Prontoo recoverable " .
                        __FUNCTION__ .
                        "] " .
                        $recoverableError->getMessage(),
                );
            }
            if (
                $e instanceof \PDOException &&
                in_array((string) $e->getCode(), ["40001", "1213"], true)
            ) {
                self::$events = array_merge($events, self::$events);
                if (self::$flushErrors <= 1 && function_exists("usleep")) {
                    @usleep(40000);
                }
                return;
            }
            self::logOnce("checksum-pending-flush", $e);
            if ($domainTx) {
                throw $e;
            }
        } finally {
            self::$inside = false;
        }
    }
    public static function rowHash(array $row): string
    {
        ksort($row);
        return hash_hmac("sha256", self::canonicalJson($row), self::secret());
    }
    public static function canonicalJson(mixed $value): string
    {
        return json_encode(
            self::canonicalize($value),
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PRESERVE_ZERO_FRACTION,
        ) ?:
            "null";
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
            "clinic_id" => self::sessionInt("clinic_id"),
            "user_id" => self::sessionInt("uid"),
            "route" => function_exists("route")
                ? (string) \route()
                : (string) ($_GET["r"] ?? ""),
            "role_code" => function_exists("session_clinic_role_code")
                ? (string) \session_clinic_role_code()
                : (string) ($_SESSION["clinic_role"] ?? ""),
            "event_key" => mb_substr($eventKey, 0, 80, "UTF-8"),
            "operation_key" => mb_substr($operation, 0, 24, "UTF-8"),
            "table_name" => $table ? mb_substr($table, 0, 120, "UTF-8") : null,
            "record_pk" => $recordPk
                ? mb_substr($recordPk, 0, 180, "UTF-8")
                : null,
            "row_count" => $rowCount,
            "success" => $success ? 1 : 0,
            "elapsed_ms" => max(0, $elapsedMs),
            "sql_fingerprint" => $sqlFingerprint,
            "params_hash" => $paramsHash,
            "error_hash" => $error
                ? hash_hmac(
                    "sha256",
                    mb_substr($error, 0, 800, "UTF-8"),
                    self::secret(),
                )
                : null,
        ];
        if (count(self::$events) >= 96) {
            self::flushFastEvents();
        }
    }
    private static function registerShutdown(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function([self::class, "flushFastEvents"]);
    }
    private static function loadRequestStartChecksum(): string
    {
        if (self::$requestStartChecksum !== null) {
            return self::$requestStartChecksum;
        }
        try {
            self::ensureSystemTables();
            $row = self::stateRow(self::pdo(), false);
            self::$requestStartChecksum =
                (string) ($row["pending_checksum"] ??
                    ($row["confirmed_checksum"] ??
                        ($row["checksum"] ?? self::genesisChecksum())));
        } catch (\Throwable) {
            self::$requestStartChecksum = self::genesisChecksum();
        }
        return self::$requestStartChecksum;
    }
    private static function ensureSystemTables(): void
    {
        if (self::$ready === true || !self::canUseDatabase()) {
            return;
        }
        if (!self::systemTablesExist()) {
            throw new \RuntimeException(
                "Tabelas internas de integridade ausentes no schema canônico.",
            );
        }
        self::ensureStateRow(self::pdo());
        self::$ready = true;
    }

    private static function systemTablesExist(): bool
    {
        try {
            $need = [
                "pi_checksum_state",
                "pi_checksum_events",
                "pi_checksum_batches",
                "pi_integrity_alerts",
                "pi_runtime_flags",
            ];
            $in = implode(",", array_fill(0, count($need), "?"));
            $st = self::pdo()->prepare(
                "SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ($in)",
            );
            $st->execute($need);
            $found = array_map(
                "strval",
                $st->fetchAll(\PDO::FETCH_COLUMN) ?: [],
            );
            return count(array_intersect($need, $found)) === count($need);
        } catch (\Throwable) {
            return false;
        }
    }
    private static function ensureStateRow(\PDO $pdo): void
    {
        $genesis = self::genesisChecksum();
        $st = $pdo->prepare(
            "INSERT IGNORE INTO pi_checksum_state (scope_key,checksum,confirmed_checksum,previous_confirmed_checksum,pending_checksum,previous_pending_checksum,event_count,pending_count,request_count,batch_count,last_request_id,pending_event_id,confirmed_event_id,confirmed_batch_id,last_batch_hash,policy_version,updated_at) VALUES ('global',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        );
        $st->execute([
            $genesis,
            $genesis,
            null,
            $genesis,
            null,
            0,
            0,
            0,
            0,
            null,
            null,
            0,
            null,
            $genesis,
            self::POLICY_VERSION,
            time(),
        ]);
        if (class_exists(PiSequence::class)) {
            PiSequence::assignByRecordPk(
                $pdo,
                "pi_checksum_state",
                "scope_key=global",
            );
        }
    }
    private static function stateRow(\PDO $pdo, bool $lock): array
    {
        $sql =
            "SELECT * FROM pi_checksum_state WHERE scope_key='global'" .
            ($lock ? " FOR UPDATE" : "");
        $stmt = $pdo->query($sql);
        $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
        try {
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        } catch (\Throwable $recoverableError) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $recoverableError->getMessage(),
            );
        }
        if (!is_array($row)) {
            $g = self::genesisChecksum();
            return [
                "checksum" => $g,
                "confirmed_checksum" => $g,
                "pending_checksum" => $g,
                "event_count" => 0,
                "pending_count" => 0,
                "confirmed_event_id" => 0,
            ];
        }
        return array_change_key_case($row, CASE_LOWER);
    }
    private static function genesisChecksum(): string
    {
        return hash_hmac(
            "sha256",
            "PRONTOO|GENESIS|" . self::POLICY_VERSION,
            self::secret(),
        );
    }
    private static function pendingEventChecksum(
        string $previousPending,
        string $payloadHash,
        string $requestId,
        int $eventCount,
        int $createdAt,
    ): string {
        return hash_hmac(
            "sha256",
            implode("|", [
                "event",
                $previousPending,
                $payloadHash,
                $requestId,
                (string) $eventCount,
                (string) $createdAt,
                self::POLICY_VERSION,
            ]),
            self::secret(),
        );
    }
    private static function pendingNextChecksum(
        string $previousPending,
        string $eventChecksum,
    ): string {
        return hash_hmac(
            "sha256",
            implode("|", [
                "pending",
                $previousPending,
                $eventChecksum,
                self::POLICY_VERSION,
            ]),
            self::secret(),
        );
    }
    private static function pendingEventRows(): int
    {
        try {
            return (int) (self::pdo()
                ->query(
                    "SELECT COUNT(*) FROM pi_checksum_events WHERE status='pending'",
                )
                ?->fetchColumn() ?:
            0);
        } catch (\Throwable) {
            return 0;
        }
    }
    private static function recordIntegrityAlert(
        \PDO $pdo,
        string $key,
        string $severity,
        string $message,
        array $details,
    ): void {
        try {
            $st = $pdo->prepare(
                "INSERT INTO pi_integrity_alerts (alert_key,severity,message,details_json,created_at) VALUES (?,?,?,?,?)",
            );
            $st->execute([
                $key,
                $severity,
                mb_substr($message, 0, 240, "UTF-8"),
                self::canonicalJson($details),
                time(),
            ]);
            $id = (int) $pdo->lastInsertId();
            if (class_exists(PiSequence::class)) {
                PiSequence::assignById($pdo, "pi_integrity_alerts", $id);
            }
        } catch (\Throwable $e) {
            self::logOnce("alert", $e);
        }
    }
    private static function recordPkForEvent(
        string $kind,
        string $table,
    ): ?string {
        if (in_array($kind, ["insert", "replace"], true)) {
            $id = self::lastInsertIdSafe();
            $pk = self::autoIncrementPrimaryKeyColumn($table);
            if ($pk !== null && $id !== "" && (int) $id > 0) {
                return $pk . "=" . $id;
            }
        }
        return null;
    }
    private static function canUseDatabase(): bool
    {
        return function_exists("has_cfg") &&
            \has_cfg() &&
            function_exists("pdo");
    }
    private static function dedicatedPdo(): \PDO
    {
        if (!function_exists("cfg")) {
            return self::pdo();
        }
        $c = \cfg();
        foreach (["db_host", "db_name", "db_user", "db_pass"] as $k) {
            if (!array_key_exists($k, $c)) {
                return self::pdo();
            }
        }
        $dsn =
            "mysql:host=" .
            $c["db_host"] .
            ";dbname=" .
            $c["db_name"] .
            ";charset=utf8mb4";
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $mysqlBuffered = "PDO::MYSQL_ATTR_USE_BUFFERED_QUERY";
        if (defined($mysqlBuffered)) {
            $options[constant($mysqlBuffered)] = true;
        }
        $pdo = new \PDO(
            $dsn,
            (string) $c["db_user"],
            (string) $c["db_pass"],
            $options,
        );
        if (defined($mysqlBuffered)) {
            try {
                $pdo->setAttribute(constant($mysqlBuffered), true);
            } catch (\Throwable $recoverableError) {
                error_log(
                    "[Prontoo recoverable " .
                        __FUNCTION__ .
                        "] " .
                        $recoverableError->getMessage(),
                );
            }
        }
        \db_assert_mysql_runtime($pdo);
        \db_apply_mysql_session_contract($pdo, true);
        return $pdo;
    }
    private static function pdo(): \PDO
    {
        return \pdo();
    }
    private static function lastInsertIdSafe(): string
    {
        try {
            return (string) self::pdo()->lastInsertId();
        } catch (\Throwable) {
            return "";
        }
    }
    private static function sessionInt(string $key): ?int
    {
        $v = $_SESSION[$key] ?? null;
        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }
    private static function autoIncrementPrimaryKeyColumn(
        string $table,
    ): ?string {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $st = self::pdo()->prepare(
                "SELECT column_name, extra FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_key='PRI' ORDER BY ordinal_position",
            );
            $st->execute([$table]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if (count($rows) !== 1) {
                return $cache[$table] = null;
            }
            $row = array_change_key_case($rows[0], CASE_LOWER);
            $col = trim((string) ($row["column_name"] ?? ""));
            $extra = strtolower((string) ($row["extra"] ?? ""));
            return $cache[$table] =
                $col !== "" && str_contains($extra, "auto_increment")
                    ? $col
                    : null;
        } catch (\Throwable) {
            return $cache[$table] = null;
        }
    }
    private static function isInternalTable(string $table): bool
    {
        foreach (
            [
                "pi_checksum_state",
                "pi_checksum_events",
                "pi_checksum_batches",
                "pi_integrity_alerts",
                "pi_runtime_flags",
                "pi_sequence",
                "pi_audit_chain_heads",
                "pi_integrity_anchors",
                "pi_action_proofs",
                "pi_scope_violations",
                "pi_record_integrity",
            ]
            as $internal
        ) {
            if ($table === $internal || str_starts_with($table, $internal)) {
                return true;
            }
        }
        return false;
    }
    private static function targetTableFromSql(string $sql): string
    {
        $s = ltrim($sql);
        $patterns = [
            "/^insert\s+(?:ignore\s+)?into\s+`?([A-Za-z0-9_]+)`?/i",
            "/^replace\s+(?:into\s+)?`?([A-Za-z0-9_]+)`?/i",
            "/^update\s+`?([A-Za-z0-9_]+)`?/i",
            "/^delete\s+from\s+`?([A-Za-z0-9_]+)`?/i",
            "/^create\s+table\s+(?:if\s+not\s+exists\s+)?`?([A-Za-z0-9_]+)`?/i",
            "/^alter\s+table\s+`?([A-Za-z0-9_]+)`?/i",
            "/^drop\s+table\s+(?:if\s+exists\s+)?`?([A-Za-z0-9_]+)`?/i",
            "/^truncate\s+table\s+`?([A-Za-z0-9_]+)`?/i",
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $s, $m)) {
                return (string) $m[1];
            }
        }
        return "";
    }
    private static function queryKind(string $sql): string
    {
        $sql = ltrim($sql);
        if (!preg_match("/^([a-zA-Z]+)/", $sql, $m)) {
            return "unknown";
        }
        return match (strtolower((string) $m[1])) {
            "insert" => "insert",
            "update" => "update",
            "delete" => "delete",
            "replace" => "replace",
            "create", "alter", "drop", "truncate" => "schema",
            "select", "show", "describe", "explain" => "select",
            default => "unknown",
        };
    }
    private static function normalizeSql(string $sql): string
    {
        $compact = preg_replace("/\s+/", " ", trim($sql)) ?: "";
        $compact =
            preg_replace("/'[^']*'|\"[^\"]*\"|\\b\\d+\\b/u", "?", $compact) ?:
            $compact;
        return mb_substr($compact, 0, 4000, "UTF-8");
    }
    private static function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $isList = array_keys($value) === range(0, count($value) - 1);
            if (!$isList) {
                ksort($value);
            }
            foreach ($value as $k => $v) {
                $value[$k] = self::canonicalize($v);
            }
        }
        return $value;
    }
    private static function secret(): string
    {
        if (function_exists("cfg")) {
            try {
                return (string) (\cfg()["secret"] ?? "prontoo-local-secret");
            } catch (\Throwable $recoverableError) {
                error_log(
                    "[Prontoo recoverable " .
                        __FUNCTION__ .
                        "] " .
                        $recoverableError->getMessage(),
                );
            }
        }
        return "prontoo-local-secret";
    }
    private static function logOnce(string $stage, \Throwable $e): void
    {
        static $seen = [];
        $key =
            $stage .
            "|" .
            hash(
                "sha256",
                $e->getMessage() .
                    "|" .
                    $e->getFile() .
                    "|" .
                    (string) $e->getLine(),
            );
        $minute = date("YmdHi");
        if (($seen[$key] ?? "") === $minute) {
            return;
        }
        $seen[$key] = $minute;
        error_log("[Prontoo PI checksum " . $stage . "] " . $e->getMessage());
    }
    private static function requestId(): string
    {
        if (self::$requestId !== null) {
            return self::$requestId;
        }
        try {
            self::$requestId = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            self::$requestId = substr(hash("sha256", uniqid("", true)), 0, 32);
        }
        return self::$requestId;
    }
}
