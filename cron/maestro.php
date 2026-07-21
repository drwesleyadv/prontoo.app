<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}
define("PRONTOO_CRON", true);
@ini_set("memory_limit", "96M");
@set_time_limit(120);
require dirname(__DIR__) . "/app/prontoo.php";
prontoo_load_full_runtime_modules();
function prontoo_cron_preflight_marker_path(): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_cron_preflight_marker_path
     * Responsabilidade: Orquestra a execução de “prontoo cron preflight marker path” e delega etapas específicas às dependências do módulo.
     * Local arquitetural: cron/maestro.php (rotinas agendadas e Maestro).
     * Chamadores detectados: `prontoo_cron_preflight_once`.
     * Dependências chamadas: `defined`, `storage_path`, `is_dir`, `mkdir`, `hash`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    $rev = defined("PRONTOO_SCHEMA_REV")
        ? (string) PRONTOO_SCHEMA_REV
        : "sem_revisao";
    $version = defined("PRONTOO_VERSION")
        ? (string) PRONTOO_VERSION
        : "sem_versao";
    $dir = storage_path("cache");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir .
        "/cron_preflight_" .
        hash("sha256", $version . "|" . $rev) .
        ".done.json";
}
function prontoo_cron_preflight_report_path(string $suffix = "latest"): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_cron_preflight_report_path
     * Responsabilidade: Orquestra a execução de “prontoo cron preflight report path” e delega etapas específicas às dependências do módulo.
     * Local arquitetural: cron/maestro.php (rotinas agendadas e Maestro).
     * Chamadores detectados: `prontoo_cron_preflight_once`.
     * Dependências chamadas: `storage_path`, `is_dir`, `mkdir`, `preg_replace`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $dir = storage_path("logs");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $suffix = preg_replace("/[^a-zA-Z0-9_\-\.]/", "_", $suffix) ?: "latest";
    return $dir . "/cron_preflight_" . $suffix . ".json";
}
function prontoo_cron_auth_permission_reset_revision(): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_cron_auth_permission_reset_revision
     * Responsabilidade: Orquestra a execução de “prontoo cron auth permission reset revision” e delega etapas específicas às dependências do módulo.
     * Local arquitetural: cron/maestro.php (rotinas agendadas e Maestro).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `defined`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     * Cuidado 2: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    return defined("PRONTOO_SCHEMA_REV")
        ? (string) PRONTOO_SCHEMA_REV
        : "auth_permission_reset";
}
function prontoo_cron_preflight_once(): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_cron_preflight_once
     * Responsabilidade: Orquestra a execução de “prontoo cron preflight once” e delega etapas específicas às dependências do módulo.
     * Local arquitetural: cron/maestro.php (rotinas agendadas e Maestro).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `prontoo_cron_preflight_marker_path`, `is_file`, `getenv`, `basename`, `microtime`, `prontoo_php_runtime_ok`, `prontoo_php_runtime_message`, `extension_loaded`, `prontoo_memory_limit_label`, `prontoo_memory_limit_meets`, `disk_free_space`, `app_root` e mais 27.
     * Efeitos colaterais: acessa a camada de persistência; produz conteúdo de saída; acessa o sistema de arquivos.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     * Cuidado 2: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    $marker = prontoo_cron_preflight_marker_path();
    if (is_file($marker) && getenv("PRONTOO_CRON_PREFLIGHT_FORCE") !== "1") {
        return ["ran" => false, "ok" => true, "marker" => basename($marker)];
    }
    $started = microtime(true);
    $checks = [];
    $fail = false;
    $warn = false;
    $add = static function (
        string $key,
        bool $ok,
        string $message,
        string $level = "error",
    ) use (&$checks, &$fail, &$warn): void {
        /*
         * GUIA DE MANUTENÇÃO — closure@cron/maestro.php:55
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de rotinas agendadas e Maestro.
         * Local arquitetural: cron/maestro.php (rotinas agendadas e Maestro).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $checks[] = [
            "key" => $key,
            "ok" => $ok,
            "level" => $level,
            "message" => $message,
        ];
        if (!$ok && $level === "error") {
            $fail = true;
        }
        if (!$ok && $level === "warn") {
            $warn = true;
        }
    };
    $add("php_sapi", PHP_SAPI === "cli", "Executado por CLI.");
    $add(
        "php_version",
        prontoo_php_runtime_ok(),
        prontoo_php_runtime_message(),
    );
    foreach (
        ["pdo", "pdo_mysql", "json", "openssl", "date", "hash", "session"]
        as $ext
    ) {
        $add(
            "ext_" . $ext,
            extension_loaded($ext),
            "Extensão " . $ext . " disponível.",
        );
    }
    $memRaw = prontoo_memory_limit_label();
    $memOk = prontoo_memory_limit_meets(96 * 1024 * 1024);
    $add(
        "memory_limit",
        $memOk,
        "memory_limit do cron: " . $memRaw . ".",
        "warn",
    );
    $free = @disk_free_space(app_root());
    $add(
        "disk_free",
        $free !== false && $free > 1024 * 1024 * 1024,
        "Espaço livre: " .
            ($free === false
                ? "indisponível"
                : round($free / 1024 / 1024, 1) . " MB") .
            ".",
    );
    $writableDirs = [
        storage_path(),
        storage_path("cache"),
        storage_path("telemetry"),
        storage_path("logs"),
        storage_path("tmp"),
        document_pdf_dir(),
    ];
    foreach ($writableDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $test = rtrim($dir, "/") . "/.cron_preflight_" . getmypid();
        $ok =
            is_dir($dir) &&
            is_writable($dir) &&
            @file_put_contents($test, "ok", LOCK_EX) !== false;
        @unlink($test);
        $label = str_replace(app_root() . "/", "", $dir);
        $add(
            "write_" . preg_replace("/[^a-zA-Z0-9_\-]/", "_", $label),
            $ok,
            "Escrita em " . $label . ".",
        );
    }
    if (!has_cfg()) {
        $add("config", false, "app/config.php não encontrado.");
    } else {
        $add("config", true, "Configuração encontrada.");
        try {
            $pdo = pdo();
            $add(
                "db_connect",
                (int) $pdo->query("SELECT 1")->fetchColumn() === 1,
                "Conexão com banco confirmada.",
            );
            ensure_runtime_schema_minimum();
            maestro_runtime_upgrade();
            if (function_exists("clinic_auto_assign_missing_managers")) {
                clinic_auto_assign_missing_managers(200);
            }
            $add(
                "schema_apply",
                true,
                "Schema mínimo verificado pelo cron sem rotina de migração incremental.",
            );
            $add(
                "auth_permission_reset",
                true,
                "Normalização de autenticação/permissões ocorre no index, antes da liberação do botão Entrar.",
            );
            $tables = [
                "pi_users",
                "pi_clinics",
                "pi_patients",
                "pi_appointments",
                "pi_documents",
                "pi_document_templates",
                "pi_tasks",
                "pi_audit",
                "pi_maestro_rules",
                "pi_maestro_job_runs",
            ];
            foreach ($tables as $t) {
                $add(
                    "table_" . $t,
                    db_table_exists($t),
                    "Tabela " . $t . " presente.",
                    $t === "pi_maestro_rules" ? "warn" : "error",
                );
            }
            foreach (
                [
                    ["pi_documents", "context_json"],
                    ["pi_maestro_rules", "min_interval_minutes"],
                    ["pi_maestro_rules", "next_run_at"],
                    ["pi_maestro_job_runs", "duration_ms"],
                    ["pi_clinics", "monthly_price_cents"],
                    ["pi_clinics", "document_sequence"],
                ]
                as [$t, $c]
            ) {
                if (db_table_exists($t)) {
                    $add(
                        "column_" . $t . "_" . $c,
                        db_column_exists($t, $c),
                        "Coluna " . $t . "." . $c . " presente.",
                        "warn",
                    );
                }
            }
        } catch (Throwable $e) {
            $add(
                "db_runtime",
                false,
                "Falha de banco/runtime durante conferência automática: " .
                    $e->getMessage(),
            );
        }
    }
    $report = [
        "ok" => !$fail,
        "warning" => $warn,
        "ran" => true,
        "automatic" => true,
        "schema_revision" => defined("PRONTOO_SCHEMA_REV")
            ? PRONTOO_SCHEMA_REV
            : null,
        "version" => defined("PRONTOO_VERSION") ? PRONTOO_VERSION : null,
        "duration_ms" => (int) round((microtime(true) - $started) * 1000),
        "checked_at" => date("c"),
        "checks" => $checks,
    ];
    @file_put_contents(
        prontoo_cron_preflight_report_path("latest"),
        json_encode(
            $report,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        ),
        LOCK_EX,
    );
    @file_put_contents(
        prontoo_cron_preflight_report_path(date("Ymd_His")),
        json_encode(
            $report,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        ),
        LOCK_EX,
    );
    if (!$fail) {
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
$__prontooCronStarted = microtime(true);
try {
    if (!has_cfg()) {
        echo json_encode(
            ["ok" => true, "note" => "Prontoo ainda não configurado"],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL;
        exit(0);
    }
    $preflight = prontoo_cron_preflight_once();
    if (!($preflight["ok"] ?? false)) {
        error_log(
            "[Prontoo cron preflight] Conferência automática falhou; Maestro não será executado neste ciclo.",
        );
        maestro_record_cron_failure(
            $__prontooCronStarted,
            "preflight: conferência automática falhou",
        );
        fwrite(
            STDERR,
            json_encode(
                [
                    "ok" => false,
                    "stage" => "preflight",
                    "preflight" => $preflight,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ) . PHP_EOL,
        );
        exit(1);
    }
    ensure_runtime_schema_minimum();
    maestro_runtime_upgrade();
    if (function_exists("clinic_auto_assign_missing_managers")) {
        clinic_auto_assign_missing_managers(200);
    }
    $piBudget = defined("PRONTOO_MAESTRO_PI_BUDGET_MS")
        ? (int) PRONTOO_MAESTRO_PI_BUDGET_MS
        : (int) max(15000, PRONTOO_MAESTRO_CRON_BUDGET_MS / 2);
    $piResult = class_exists("\Prontoo\Core\Integrity\PiIntegrity")
        ? \Prontoo\Core\Integrity\PiIntegrity::runMaestroCycle($piBudget)
        : ["ok" => true];
    $jobBudget = max(5000, (int) PRONTOO_MAESTRO_CRON_BUDGET_MS - $piBudget);
    $result = maestro_cron_run($jobBudget);
    $ok =
        (bool) ($result["success"] ?? true) && (bool) ($piResult["ok"] ?? true);
    if (function_exists("server_json_cache_clear_categories")) {
        server_json_cache_clear_categories([
            "hot",
            "agenda",
            "recepcao",
            "warm",
            "kpi",
            "cards",
            "dashboard",
            "lookup",
            "auxiliary",
            "context",
            "permissions",
            "cmdbar",
        ]);
    }
    echo json_encode(
        [
            "ok" => $ok,
            "preflight" => [
                "ran" => (bool) ($preflight["ran"] ?? false),
                "duration_ms" => $preflight["duration_ms"] ?? null,
            ],
            "pi_integrity" => $piResult,
        ] + $result,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ) . PHP_EOL;
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    error_log("[Prontoo cron Maestro] " . $e->getMessage());
    maestro_record_cron_failure($__prontooCronStarted, $e->getMessage());
    fwrite(
        STDERR,
        json_encode(
            ["ok" => false, "error" => $e->getMessage()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL,
    );
    exit(1);
}
