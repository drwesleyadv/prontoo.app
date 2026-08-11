<?php
declare(strict_types=1);

if (PHP_SAPI !== "cli") {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}
if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
    $prontooPhpRuntimeMessage = "Prontoo exige exclusivamente PHP 8.4. Runtime atual: " . PHP_VERSION . ".";
    error_log("[Prontoo PHP runtime] " . $prontooPhpRuntimeMessage);
    if (PHP_SAPI === "cli") {
        fwrite(STDERR, $prontooPhpRuntimeMessage . PHP_EOL);
    } else {
        if (!headers_sent()) {
            http_response_code(503);
            header("Content-Type: text/plain; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Retry-After: 300");
        }
        echo $prontooPhpRuntimeMessage;
    }
    unset($prontooPhpRuntimeMessage);
    exit(1);
}

$__prontooCronRoot = dirname(__DIR__);
$__prontooCronBootstrapLog = $__prontooCronRoot . "/ssd/logs/maestro-bootstrap.log";
$__prontooCronFinished = false;

function prontoo_cron_bootstrap_log(string $message): void
{
    global $__prontooCronBootstrapLog;
    $dir = dirname($__prontooCronBootstrapLog);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $line = gmdate("c") . " " . preg_replace('/\s+/u', " ", trim($message)) . PHP_EOL;
    @file_put_contents($__prontooCronBootstrapLog, $line, FILE_APPEND | LOCK_EX);
}

register_shutdown_function(static function (): void {
    global $__prontooCronFinished;
    if ($__prontooCronFinished) {
        return;
    }
    $error = error_get_last();
    if (is_array($error)) {
        prontoo_cron_bootstrap_log(
            "shutdown type=" . (int) ($error["type"] ?? 0) .
            " file=" . basename((string) ($error["file"] ?? "")) .
            " line=" . (int) ($error["line"] ?? 0) .
            " message=" . (string) ($error["message"] ?? ""),
        );
    }
});


define("PRONTOO_CRON", true);
@ini_set("memory_limit", "96M");
@ini_set("log_errors", "1");
@set_time_limit(120);

try {
    require $__prontooCronRoot . "/app/prontoo.php";
    \Prontoo\Runtime\Modules\RuntimeModuleComposition::loader()->loadFullRuntime();
} catch (Throwable $error) {
    prontoo_cron_bootstrap_log("bootstrap " . $error->getMessage());
    fwrite(
        STDERR,
        json_encode(
            ["ok" => false, "stage" => "bootstrap", "error" => $error->getMessage()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL,
    );
    exit(1);
}

function prontoo_cron_record_failure(float $startedAt, string $note): void
{
    if (!is_callable([\Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::class, 'maestro_supervised_record_job_run'])) {
        return;
    }
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_record_job_run($startedAt, [
        "success" => false,
        "status" => "failed",
        "rules_seen" => 0,
        "rules_run" => 0,
        "actions_created" => 0,
        "deferred" => 0,
        "errors" => 1,
        "duration_ms" => (int) max(0, round((microtime(true) - $startedAt) * 1000, 0, \RoundingMode::HalfAwayFromZero)),
        "load_score" => 0,
        "note" => "falha: " . mb_substr(preg_replace("/\s+/u", " ", trim($note)) ?: "erro", 0, 232),
    ]);
}

function prontoo_cron_remaining_budget_ms(float $deadline): int
{
    return max(0, (int) floor(($deadline - microtime(true)) * 1000));
}

function prontoo_cron_preflight_marker_path(): string
{
    $revision = defined("PRONTOO_SCHEMA_REV")
        ? (string) PRONTOO_SCHEMA_REV
        : "sem_revisao";
    $version = defined("PRONTOO_VERSION")
        ? (string) PRONTOO_VERSION
        : "sem_versao";
    $dir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir . "/cron_preflight_" . hash("sha256", $version . "|" . $revision) . ".done.json";
}

function prontoo_cron_preflight_report_path(string $suffix = "latest"): string
{
    $dir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("logs");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $suffix = preg_replace("/[^a-zA-Z0-9_\-\.]/", "_", $suffix) ?: "latest";
    return $dir . "/cron_preflight_" . $suffix . ".json";
}

function prontoo_cron_preflight_once(): array
{
    $marker = prontoo_cron_preflight_marker_path();
    if (is_file($marker) && getenv("PRONTOO_CRON_PREFLIGHT_FORCE") !== "1") {
        return [
            "ran" => false,
            "ok" => true,
            "warning" => false,
            "marker" => basename($marker),
        ];
    }
    $started = microtime(true);
    $checks = [];
    $failed = false;
    $warning = false;
    $add = static function (
        string $key,
        bool $ok,
        string $message,
        string $level = "error",
    ) use (&$checks, &$failed, &$warning): void {
        $checks[] = [
            "key" => $key,
            "ok" => $ok,
            "level" => $level,
            "message" => $message,
        ];
        if (!$ok && $level === "error") {
            $failed = true;
        }
        if (!$ok && $level === "warn") {
            $warning = true;
        }
    };
    $add("php_sapi", PHP_SAPI === "cli", "Executado por CLI.");
    $add("php_version", \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_php_runtime_ok(), \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_php_runtime_message());
    foreach (["pdo", "pdo_mysql", "json", "openssl", "date", "hash", "session"] as $extension) {
        $add(
            "ext_" . $extension,
            extension_loaded($extension),
            "Extensão " . $extension . " disponível.",
        );
    }
    $memoryLabel = \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_memory_limit_label();
    $add(
        "memory_limit",
        \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_memory_limit_meets(96 * 1024 * 1024),
        "memory_limit do cron: " . $memoryLabel . ".",
        "warn",
    );
    $free = @disk_free_space(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_root());
    $add(
        "disk_free",
        $free !== false && $free > 1024 * 1024 * 1024,
        "Espaço livre: " .
            ($free === false ? "indisponível" : round($free / 1024 / 1024, 1, \RoundingMode::HalfAwayFromZero) . " MB") .
            ".",
    );
    foreach ([
        \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path(),
        \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache"),
        \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("logs"),
        \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("tmp"),
        \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("maestro-deferred"),
    ] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $probe = rtrim($dir, "/") . "/.cron_preflight_" . getmypid();
        $ok = is_dir($dir) &&
            is_writable($dir) &&
            @file_put_contents($probe, "ok", LOCK_EX) !== false;
        @unlink($probe);
        $label = str_replace(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_root() . "/", "", $dir);
        $add(
            "write_" . preg_replace("/[^a-zA-Z0-9_\-]/", "_", $label),
            $ok,
            "Escrita em " . $label . ".",
        );
    }
    if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
        $add("config", false, "Configuração não encontrada.");
    } else {
        $add("config", true, "Configuração encontrada.");
        try {
            $add(
                "db_connect",
                (int) \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->query("SELECT 1")->fetchColumn() === 1,
                "Conexão com banco confirmada.",
            );
            foreach ([
                "pi_audit",
                "pi_maestro_rules",
                "pi_maestro_executions",
                "pi_maestro_job_runs",
                "pi_maestro_job_stats",
            ] as $table) {
                $add(
                    "table_" . $table,
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_table_exists($table),
                    "Tabela " . $table . " presente.",
                );
            }
            foreach ([
                ["pi_maestro_rules", "min_interval_minutes"],
                ["pi_maestro_rules", "next_run_at"],
                ["pi_maestro_job_runs", "duration_ms"],
                ["pi_maestro_job_runs", "success"],
            ] as [$table, $column]) {
                $add(
                    "column_" . $table . "_" . $column,
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_table_exists($table) && \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_column_exists($table, $column),
                    "Coluna " . $table . "." . $column . " presente.",
                );
            }
        } catch (Throwable $error) {
            $add(
                "db_runtime",
                false,
                "Falha de banco durante conferência somente leitura: " . $error->getMessage(),
            );
        }
    }
    $report = [
        "ok" => !$failed,
        "warning" => $warning,
        "ran" => true,
        "automatic" => true,
        "mutation_free" => true,
        "schema_revision" => defined("PRONTOO_SCHEMA_REV") ? PRONTOO_SCHEMA_REV : null,
        "version" => defined("PRONTOO_VERSION") ? PRONTOO_VERSION : null,
        "duration_ms" => (int) round((microtime(true) - $started) * 1000, 0, \RoundingMode::HalfAwayFromZero),
        "checked_at" => gmdate("c"),
        "checks" => $checks,
    ];
    $encoded = json_encode(
        $report,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
    );
    if (is_string($encoded)) {
        @file_put_contents(prontoo_cron_preflight_report_path("latest"), $encoded, LOCK_EX);
        @file_put_contents(
            prontoo_cron_preflight_report_path(gmdate("Ymd_His")),
            $encoded,
            LOCK_EX,
        );
    }
    if (!$failed) {
        @file_put_contents(
            $marker,
            json_encode(
                [
                    "ok" => true,
                    "schema_revision" => $report["schema_revision"],
                    "version" => $report["version"],
                    "checked_at" => $report["checked_at"],
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
            LOCK_EX,
        );
    }
    return $report;
}

function prontoo_cron_integrity_flush(int $budgetMs): array
{
    $started = microtime(true);
    if (
        $budgetMs < 250 ||
        !class_exists("\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity")
    ) {
        return [
            "ok" => $budgetMs < 250,
            "complete" => false,
            "mode" => "runtime-event-flush",
            "note" => $budgetMs < 250
                ? "Flush adiado por orçamento insuficiente."
                : "Núcleo de integridade indisponível.",
            "duration_ms" => 0,
        ];
    }
    try {
        \Prontoo\Infrastructure\Integrity\PiIntegrity::flushFastEvents();
        return [
            "ok" => true,
            "complete" => true,
            "mode" => "runtime-event-flush",
            "note" => "Eventos pendentes do processo atual descarregados.",
            "duration_ms" => (int) round((microtime(true) - $started) * 1000, 0, \RoundingMode::HalfAwayFromZero),
        ];
    } catch (Throwable $error) {
        return [
            "ok" => false,
            "complete" => false,
            "mode" => "runtime-event-flush",
            "note" => mb_substr($error->getMessage(), 0, 220),
            "duration_ms" => (int) round((microtime(true) - $started) * 1000, 0, \RoundingMode::HalfAwayFromZero),
        ];
    }
}

function prontoo_cron_maintenance(): array
{
    $started = microtime(true);
    $removed = 0;
    $cutoff = time() - 30 * 86400;
    foreach ((array) glob(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("logs/cron_preflight_*.json")) as $file) {
        if (str_ends_with($file, "cron_preflight_latest.json")) {
            continue;
        }
        if ((int) @filemtime($file) > 0 && (int) @filemtime($file) < $cutoff && @unlink($file)) {
            $removed++;
        }
    }
    $bootstrap = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("logs/maestro-bootstrap.log");
    if (is_file($bootstrap) && (int) @filesize($bootstrap) > 2 * 1024 * 1024) {
        for ($index = 4; $index >= 1; $index--) {
            $source = $bootstrap . "." . $index;
            $target = $bootstrap . "." . ($index + 1);
            if (is_file($source)) {
                $index === 4 ? @unlink($source) : @rename($source, $target);
            }
        }
        @rename($bootstrap, $bootstrap . ".1");
    }
    return [
        "ok" => true,
        "removed_transient_files" => $removed,
        "authoritative_records_preserved" => true,
        "duration_ms" => (int) round((microtime(true) - $started) * 1000, 0, \RoundingMode::HalfAwayFromZero),
    ];
}

function prontoo_cron_cycle_state_write(array $cycle): void
{
    $dir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("maestro/state");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }
    $encoded = json_encode(
        $cycle,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
    );
    if (!is_string($encoded)) {
        return;
    }
    @file_put_contents($dir . "/latest.json", $encoded, LOCK_EX);
}

$__prontooCronStarted = microtime(true);
$__prontooCronDeadline = $__prontooCronStarted + PRONTOO_MAESTRO_CRON_BUDGET_MS / 1000;

try {
    if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
        $__prontooCronFinished = true;
        echo json_encode(
            ["ok" => true, "note" => "Prontoo ainda não configurado"],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL;
        exit(0);
    }
    $preflight = prontoo_cron_preflight_once();
    if (!($preflight["ok"] ?? false)) {
        prontoo_cron_record_failure(
            $__prontooCronStarted,
            "preflight somente leitura falhou",
        );
        $payload = [
            "ok" => false,
            "stage" => "preflight",
            "preflight" => $preflight,
        ];
        prontoo_cron_cycle_state_write($payload + [
            "version" => PRONTOO_VERSION,
            "finished_at_utc" => gmdate("c"),
        ]);
        $__prontooCronFinished = true;
        fwrite(
            STDERR,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        );
        exit(1);
    }
    $recordSnapshot = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations03::telemetry_database_record_snapshot_capture();
    $rulesBudget = min(45000, prontoo_cron_remaining_budget_ms($__prontooCronDeadline));
    $rules = $rulesBudget >= 5000
        ? \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_cron_run($rulesBudget)
        : [
            "success" => true,
            "status" => "attention",
            "rules_seen" => 0,
            "rules_run" => 0,
            "actions_created" => 0,
            "deferred" => 1,
            "errors" => 0,
            "note" => "Regras adiadas por orçamento residual insuficiente.",
        ];
    $deferredBudget = min(20000, max(250, prontoo_cron_remaining_budget_ms($__prontooCronDeadline)));
    $deferredWork = \Prontoo\Runtime\DeferredAudit\DeferredAuditRuntimeOperations01::maestro_process_deferred_work($deferredBudget, 1000);
    $integrityBudget = min(10000, max(0, prontoo_cron_remaining_budget_ms($__prontooCronDeadline)));
    $piResult = prontoo_cron_integrity_flush($integrityBudget);
    $maintenance = prontoo_cron_maintenance();
    $ok = (bool) ($rules["success"] ?? false) &&
        (bool) ($deferredWork["success"] ?? false) &&
        (bool) ($piResult["ok"] ?? false) &&
        (bool) ($maintenance["ok"] ?? false);
    $attention = (string) ($rules["status"] ?? "") === "attention" ||
        (string) ($deferredWork["status"] ?? "") === "attention" ||
        !($recordSnapshot["ok"] ?? false) ||
        !empty($preflight["warning"]);
    $cycle = [
        "ok" => $ok,
        "status" => $ok ? ($attention ? "attention" : "healthy") : "failed",
        "version" => PRONTOO_VERSION,
        "started_at_utc" => gmdate("c", (int) $__prontooCronStarted),
        "finished_at_utc" => gmdate("c"),
        "duration_ms" => (int) round((microtime(true) - $__prontooCronStarted) * 1000, 0, \RoundingMode::HalfAwayFromZero),
        "database_records" => $recordSnapshot,
        "preflight" => [
            "ran" => (bool) ($preflight["ran"] ?? false),
            "ok" => (bool) ($preflight["ok"] ?? false),
            "warning" => (bool) ($preflight["warning"] ?? false),
            "duration_ms" => $preflight["duration_ms"] ?? null,
        ],
        "rules" => $rules,
        "deferred_work" => $deferredWork,
        "pi_integrity" => $piResult,
        "maintenance" => $maintenance,
    ];
    prontoo_cron_cycle_state_write($cycle);
    $__prontooCronFinished = true;
    echo json_encode(
        [
            "ok" => $ok,
            "status" => $cycle["status"],
            "preflight" => $cycle["preflight"],
            "database_records" => $recordSnapshot,
            "deferred_work" => $deferredWork,
            "pi_integrity" => $piResult,
            "maintenance" => $maintenance,
        ] + $rules,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ) . PHP_EOL;
    exit($ok ? 0 : 1);
} catch (Throwable $error) {
    prontoo_cron_bootstrap_log("runtime " . $error->getMessage());
    prontoo_cron_record_failure($__prontooCronStarted, $error->getMessage());
    $__prontooCronFinished = true;
    fwrite(
        STDERR,
        json_encode(
            ["ok" => false, "stage" => "runtime", "error" => $error->getMessage()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL,
    );
    exit(1);
}
