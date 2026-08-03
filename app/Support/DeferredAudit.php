<?php
declare(strict_types=1);

function maestro_deferred_storage_dir(): string
{
    return storage_path("maestro-deferred");
}

function maestro_deferred_storage_dirs(string $type = "audit"): array
{
    return array_values(array_unique([
        maestro_deferred_storage_dir(),
        storage_path("logs/maestro-deferred-emergency"),
    ]));
}

function maestro_deferred_canonicalize(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map("maestro_deferred_canonicalize", $value);
    }
    ksort($value);
    foreach ($value as $key => $item) {
        $value[$key] = maestro_deferred_canonicalize($item);
    }
    return $value;
}

function maestro_deferred_sign(array $envelope): string
{
    static $key = null;
    if (!is_string($key) || $key === "") {
        $configSecret = trim((string) (cfg()["secret"] ?? ""));
        $source = strlen($configSecret) >= 32 ? $configSecret : secret_key();
        $key = hash("sha256", "prontoo|maestro-deferred|" . $source, true);
    }
    $json = json_encode(
        maestro_deferred_canonicalize($envelope),
        JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRESERVE_ZERO_FRACTION,
    );
    if (!is_string($json)) {
        throw new RuntimeException("Envelope secundário inválido para assinatura.");
    }
    return hash_hmac("sha256", $json, $key);
}

function maestro_deferred_spool_write(string $dir, string $id, string $json): bool
{
    $temporary = null;
    try {
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }
        if (function_exists("security_storage_deny_file")) {
            security_storage_deny_file($dir);
        }
        $temporary = tempnam($dir, ".pending-");
        if (
            !is_string($temporary) ||
            @file_put_contents($temporary, $json) !== strlen($json)
        ) {
            return false;
        }
        @chmod($temporary, 0640);
        if (!@rename($temporary, $dir . "/" . $id . ".json")) {
            return false;
        }
        $temporary = null;
        return true;
    } catch (Throwable $error) {
        error_log("[Prontoo Maestro spool] " . $error->getMessage());
        return false;
    } finally {
        if (is_string($temporary) && is_file($temporary)) {
            @unlink($temporary);
        }
    }
}

function maestro_deferred_enqueue(string $type, array $payload): ?string
{
    if (!has_cfg() || $type !== "audit") {
        return null;
    }
    try {
        $id = sprintf("%020d", (int) round(microtime(true) * 1000000)) .
            "-" .
            bin2hex(random_bytes(8));
        $unsigned = [
            "version" => 1,
            "id" => $id,
            "type" => "audit",
            "queued_at_utc" => gmdate("Y-m-d H:i:s"),
            "payload" => $payload,
        ];
        $envelope = $unsigned + ["signature" => maestro_deferred_sign($unsigned)];
        $json = json_encode(
            $envelope,
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PRESERVE_ZERO_FRACTION,
        );
        if (!is_string($json)) {
            return null;
        }
        foreach (maestro_deferred_storage_dirs() as $dir) {
            if (maestro_deferred_spool_write($dir, $id, $json)) {
                return $id;
            }
        }
    } catch (Throwable $error) {
        error_log("[Prontoo Maestro defer] " . $error->getMessage());
    }
    return null;
}

function maestro_defer_audit_event(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $context,
    ?int $actorUserId = null,
): bool {
    $ip = substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45);
    $userAgent = mb_substr(
        trim((string) ($_SERVER["HTTP_USER_AGENT"] ?? "")),
        0,
        180,
    );
    return maestro_deferred_enqueue("audit", [
        "event" => mb_substr($event, 0, 80),
        "entity" => $entity !== null ? mb_substr($entity, 0, 80) : null,
        "entity_id" => (string) $entityId,
        "context" => $context,
        "actor_user_id" => $actorUserId !== null && $actorUserId > 0
            ? $actorUserId
            : null,
        "ip_hash" => $ip !== "" ? hash("sha256", $ip . "|ip") : null,
        "user_agent" => $userAgent !== "" ? $userAgent : null,
        "occurred_at_utc" => gmdate("Y-m-d H:i:s"),
        "proof_context" => [
            "route" => function_exists("route")
                ? (string) route()
                : (string) ($_GET["r"] ?? ""),
            "method" => (string) ($_SERVER["REQUEST_METHOD"] ?? ""),
            "scope" => (string) ($_SESSION["scope"] ?? ""),
            "clinic_id" => (int) ($_SESSION["clinic_id"] ?? 0) > 0
                ? (int) $_SESSION["clinic_id"]
                : null,
            "role" => (string) ($_SESSION["role_code"] ?? ""),
        ],
    ]) !== null;
}

function maestro_deferred_envelope_valid(array $envelope): bool
{
    $signature = strtolower(trim((string) ($envelope["signature"] ?? "")));
    if (
        (int) ($envelope["version"] ?? 0) !== 1 ||
        preg_match('/^\d{20}-[a-f0-9]{16}$/', (string) ($envelope["id"] ?? "")) !== 1 ||
        (string) ($envelope["type"] ?? "") !== "audit" ||
        !isset($envelope["payload"]) ||
        !is_array($envelope["payload"]) ||
        preg_match('/^[a-f0-9]{64}$/', $signature) !== 1
    ) {
        return false;
    }
    $unsigned = $envelope;
    unset($unsigned["signature"]);
    try {
        return hash_equals(maestro_deferred_sign($unsigned), $signature);
    } catch (Throwable) {
        return false;
    }
}

function maestro_process_deferred_audit(array $envelope): bool
{
    if (
        !function_exists("audit") ||
        !maestro_deferred_envelope_valid($envelope)
    ) {
        return false;
    }
    $id = (string) ($envelope["id"] ?? "");
    $payload = (array) ($envelope["payload"] ?? []);
    $event = mb_substr((string) ($payload["event"] ?? ""), 0, 80);
    if ($event === "") {
        return false;
    }
    $existing = one(
        "SELECT id FROM pi_audit WHERE event_key=? AND JSON_UNQUOTE(JSON_EXTRACT(context_json,'$.deferred_id'))=? ORDER BY id DESC LIMIT 1",
        [$event, $id],
    );
    if ($existing) {
        return true;
    }
    $context = isset($payload["context"]) && is_array($payload["context"])
        ? $payload["context"]
        : [];
    $context["deferred_id"] = $id;
    $context["occurred_at_utc"] = (string) (
        $payload["occurred_at_utc"] ?? ($envelope["queued_at_utc"] ?? "")
    );
    return audit(
        $event,
        isset($payload["entity"]) ? (string) $payload["entity"] : null,
        $payload["entity_id"] ?? null,
        $context,
        [
            "skip_runtime_context" => true,
            "skip_context_enrichment" => true,
            "user_id" => isset($payload["actor_user_id"])
                ? (int) $payload["actor_user_id"]
                : null,
            "ip_hash" => $payload["ip_hash"] ?? null,
            "user_agent" => $payload["user_agent"] ?? null,
            "created_at" => $payload["occurred_at_utc"] ?? null,
            "proof_context" => isset($payload["proof_context"]) && is_array($payload["proof_context"])
                ? $payload["proof_context"]
                : null,
        ],
    );
}

function maestro_process_deferred_work(int $budgetMs = 5000, int $limit = 500): array
{
    $started = microtime(true);
    $budgetMs = max(250, min(30000, $budgetMs));
    $limit = max(1, min(5000, $limit));
    $stats = [
        "success" => true,
        "queued" => 0,
        "processed" => 0,
        "audit" => 0,
        "invalid" => 0,
        "errors" => 0,
        "retrying" => 0,
        "dead_letter" => 0,
        "remaining" => 0,
        "oldest_age_seconds" => 0,
        "alert" => false,
        "duration_ms" => 0,
    ];
    $dirs = array_values(array_filter(
        maestro_deferred_storage_dirs(),
        static fn(string $dir): bool => is_dir($dir),
    ));
    if ($dirs === []) {
        return $stats;
    }
    $files = [];
    foreach ($dirs as $dir) {
        foreach ((array) glob($dir . "/*.json.processing") as $stale) {
            if ((int) @filemtime($stale) > 0 && time() - (int) @filemtime($stale) > 300) {
                @rename($stale, substr($stale, 0, -strlen(".processing")));
            }
        }
        foreach ((array) glob($dir . "/*.json") as $file) {
            $files[] = $file;
        }
    }
    sort($files, SORT_STRING);
    $stats["queued"] = count($files);
    foreach (array_slice($files, 0, $limit) as $file) {
        if ((microtime(true) - $started) * 1000 >= $budgetMs) {
            break;
        }
        $dir = dirname($file);
        $queuedAge = max(0, time() - (int) @filemtime($file));
        $stats["oldest_age_seconds"] = max($stats["oldest_age_seconds"], $queuedAge);
        $claimed = $file . ".processing";
        if (!@rename($file, $claimed)) {
            continue;
        }
        $completed = false;
        $invalid = false;
        $expired = $queuedAge > 86400;
        $attempt = preg_match('/\.retry-(\d+)\.json$/', basename($file), $match) === 1
            ? max(0, (int) ($match[1] ?? 0))
            : 0;
        $envelope = null;
        try {
            $raw = @file_get_contents($claimed);
            $envelope = is_string($raw) && trim($raw) !== ""
                ? json_decode($raw, true)
                : null;
            if (!is_array($envelope) || !maestro_deferred_envelope_valid($envelope)) {
                $invalid = true;
                $stats["invalid"]++;
            } elseif ($expired) {
                $stats["errors"]++;
            } else {
                $completed = maestro_process_deferred_audit($envelope);
                if ($completed) {
                    $stats["processed"]++;
                    $stats["audit"]++;
                } else {
                    $stats["errors"]++;
                }
            }
        } catch (Throwable $error) {
            $stats["errors"]++;
            error_log("[Prontoo Maestro deferred] " . $error->getMessage());
        }
        if ($completed) {
            @unlink($claimed);
            continue;
        }
        $attempt++;
        $deadReason = $invalid
            ? "invalid"
            : ($expired ? "expired" : ($attempt >= 5 ? "retries" : ""));
        if ($deadReason !== "") {
            $deadDir = $dir . "/dead-letter";
            if (!is_dir($deadDir)) {
                @mkdir($deadDir, 0750, true);
            }
            $deadName = (string) (
                is_array($envelope)
                    ? ($envelope["id"] ?? basename($file, ".json"))
                    : basename($file, ".json")
            ) . "." . $deadReason . "." . time();
            if (@rename($claimed, $deadDir . "/" . $deadName)) {
                $stats["dead_letter"]++;
            } else {
                @rename($claimed, $file);
                $stats["errors"]++;
            }
            continue;
        }
        $id = is_array($envelope) &&
            preg_match('/^\d{20}-[a-f0-9]{16}$/', (string) ($envelope["id"] ?? "")) === 1
                ? (string) $envelope["id"]
                : basename($file, ".json");
        $retryFile = $dir . "/" . $id . ".retry-" . $attempt . ".json";
        if (@rename($claimed, $retryFile)) {
            $stats["retrying"]++;
        } else {
            @rename($claimed, $file);
            $stats["errors"]++;
        }
    }
    $remainingFiles = [];
    $deadLetter = 0;
    foreach ($dirs as $dir) {
        foreach ((array) glob($dir . "/*.json") as $file) {
            $remainingFiles[] = $file;
        }
        $deadLetter += count((array) glob($dir . "/dead-letter/*"));
    }
    $stats["remaining"] = count($remainingFiles);
    $stats["dead_letter"] = $deadLetter;
    foreach ($remainingFiles as $file) {
        $stats["oldest_age_seconds"] = max(
            $stats["oldest_age_seconds"],
            max(0, time() - (int) @filemtime($file)),
        );
    }
    $stats["duration_ms"] = (int) round((microtime(true) - $started) * 1000);
    $stats["alert"] = $stats["errors"] > 0 ||
        $stats["invalid"] > 0 ||
        $stats["dead_letter"] > 0 ||
        $stats["oldest_age_seconds"] > 3600;
    $stats["success"] = !$stats["alert"];
    $stateDir = maestro_deferred_storage_dir() . "/state";
    if (!is_dir($stateDir)) {
        @mkdir($stateDir, 0750, true);
    }
    if (is_dir($stateDir) && is_writable($stateDir)) {
        @file_put_contents(
            $stateDir . "/deferred-work.json",
            json_encode(
                [
                    "updated_at_utc" => gmdate("Y-m-d H:i:s"),
                    "status" => $stats["alert"] ? "attention" : "healthy",
                    "stats" => $stats,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
            LOCK_EX,
        );
    }
    return $stats;
}
