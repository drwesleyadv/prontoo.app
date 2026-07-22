<?php
declare(strict_types=1);
$GLOBALS["PRONTOO_REQUEST_STARTED_AT"] = microtime(true);
$GLOBALS["PRONTOO_QUERY_COUNT"] = 0;
$GLOBALS["PRONTOO_QUERY_TOTAL_MS"] = 0.0;
$GLOBALS["PRONTOO_QUERY_WIDE_SELECT_COUNT"] = 0;
if (!defined("PRONTOO_ROOT")) {
    define("PRONTOO_ROOT", dirname(__DIR__));
}
if (PHP_SAPI !== "cli") {
    // PRONTOO_HTTPS_RUNTIME_GUARD
    $prontooHttps = strtolower(trim((string) ($_SERVER["HTTPS"] ?? "")));
    $prontooForwardedProtoParts = explode(",", strtolower((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")));
    $prontooForwardedProto = trim((string) ($prontooForwardedProtoParts[0] ?? ""));
    $prontooRequestSecure = in_array($prontooHttps, ["on", "1"], true) ||
        (int) ($_SERVER["SERVER_PORT"] ?? 0) === 443 ||
        $prontooForwardedProto === "https";
    $prontooRequestHost = strtolower(trim((string) ($_SERVER["HTTP_HOST"] ?? "")));
    $prontooRequestHost = preg_replace('/:\d+$/', '', $prontooRequestHost) ?? "";
    if (!$prontooRequestSecure || $prontooRequestHost !== "prontoo.app") {
        $prontooRequestUri = (string) ($_SERVER["REQUEST_URI"] ?? "/");
        if ($prontooRequestUri === "" || !str_starts_with($prontooRequestUri, "/")) {
            $prontooRequestUri = "/";
        }
        if (!headers_sent()) {
            header("Location: https://prontoo.app" . $prontooRequestUri, true, 308);
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        exit;
    }
    unset(
        $prontooHttps,
        $prontooForwardedProtoParts,
        $prontooForwardedProto,
        $prontooRequestSecure,
        $prontooRequestHost,
        $prontooRequestUri,
    );
}
// PRONTOO_SSD_PERSISTENCE_POLICY
if (!defined("PRONTOO_SSD_ROOT")) {
    define("PRONTOO_SSD_ROOT", PRONTOO_ROOT . "/ssd");
    define("PRONTOO_PDF_ROOT", PRONTOO_SSD_ROOT . "/pdfs");
    define("PRONTOO_IMAGE_UPLOAD_ROOT", PRONTOO_SSD_ROOT . "/img");
}
$prontooPersistencePairs = [
    [PRONTOO_ROOT . "/storage", PRONTOO_SSD_ROOT],
    [PRONTOO_ROOT . "/pdfs", PRONTOO_PDF_ROOT],
];
foreach ($prontooPersistencePairs as [$prontooLegacyRoot, $prontooCanonicalRoot]) {
    if (!is_dir($prontooLegacyRoot) || is_link($prontooLegacyRoot)) {
        continue;
    }
    if (!is_dir($prontooCanonicalRoot)) {
        $prontooCanonicalParent = dirname($prontooCanonicalRoot);
        if (!is_dir($prontooCanonicalParent)) {
            @mkdir($prontooCanonicalParent, 0750, true);
        }
        if (@rename($prontooLegacyRoot, $prontooCanonicalRoot)) {
            @chmod($prontooCanonicalRoot, 0750);
            continue;
        }
        @mkdir($prontooCanonicalRoot, 0750, true);
    }
    try {
        $prontooLegacyIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($prontooLegacyRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($prontooLegacyIterator as $prontooLegacyEntry) {
            if ($prontooLegacyEntry->isLink()) {
                continue;
            }
            $prontooLegacyPath = $prontooLegacyEntry->getPathname();
            $prontooRelativePath = substr($prontooLegacyPath, strlen($prontooLegacyRoot) + 1);
            if ($prontooRelativePath === false || $prontooRelativePath === "") {
                continue;
            }
            $prontooCanonicalPath = $prontooCanonicalRoot . DIRECTORY_SEPARATOR . $prontooRelativePath;
            if ($prontooLegacyEntry->isDir()) {
                if (!is_dir($prontooCanonicalPath)) {
                    @mkdir($prontooCanonicalPath, 0750, true);
                }
                @rmdir($prontooLegacyPath);
                continue;
            }
            $prontooCanonicalParent = dirname($prontooCanonicalPath);
            if (!is_dir($prontooCanonicalParent)) {
                @mkdir($prontooCanonicalParent, 0750, true);
            }
            if (!file_exists($prontooCanonicalPath)) {
                @rename($prontooLegacyPath, $prontooCanonicalPath);
            }
        }
        @rmdir($prontooLegacyRoot);
    } catch (Throwable $prontooPersistenceError) {
        error_log("[Prontoo SSD migration] " . $prontooPersistenceError->getMessage());
    }
}
foreach ([PRONTOO_SSD_ROOT, PRONTOO_PDF_ROOT, PRONTOO_IMAGE_UPLOAD_ROOT] as $prontooPersistentDir) {
    if (!is_dir($prontooPersistentDir)) {
        @mkdir($prontooPersistentDir, 0750, true);
    }
    if (is_dir($prontooPersistentDir)) {
        @chmod($prontooPersistentDir, 0750);
    }
}
unset(
    $prontooPersistencePairs,
    $prontooLegacyRoot,
    $prontooCanonicalRoot,
    $prontooCanonicalParent,
    $prontooLegacyIterator,
    $prontooLegacyEntry,
    $prontooLegacyPath,
    $prontooRelativePath,
    $prontooCanonicalPath,
    $prontooPersistenceError,
    $prontooPersistentDir,
);
if (!function_exists("mb_substr")) {
    if (!defined("MB_CASE_TITLE")) {
        define("MB_CASE_TITLE", 2);
    }
    function mb_substr(
        string $s,
        int $a,
        ?int $l = null,
        ?string $e = null,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — mb_substr
         * Responsabilidade: Implementa a responsabilidade “mb substr” dentro do módulo de composição geral do runtime.
         * Local arquitetural: app/prontoo.php (composição geral do runtime).
         * Chamadores detectados: `page_admin_alerts`, `onboarding_tip_key`, `onboarding_tip_dismiss`, `Core.Integrity.PiIntegrity::afterQuery`, `Core.Integrity.PiIntegrity::flushFastEvents`, `Core.Integrity.PiIntegrity::queueEvent`, `Core.Support.Check::clampText`, `db_log_query_failure` e mais 34.
         * Dependências chamadas: `substr`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return $l === null ? substr($s, $a) : substr($s, $a, $l);
    }
    function mb_strlen(string $s, ?string $e = null): int
    {
        /*
         * GUIA DE MANUTENÇÃO — mb_strlen
         * Responsabilidade: Implementa a responsabilidade “mb strlen” dentro do módulo de composição geral do runtime.
         * Local arquitetural: app/prontoo.php (composição geral do runtime).
         * Chamadores detectados: `page_profile`, `mask`, `normalize_subscription_pix_key`, `document_pdf_text_width`, `patient_summary_excerpt`, `page_counterparty_suggest`, `financial_create_drawer`, `financial_rename_drawer` e mais 2.
         * Dependências chamadas: `strlen`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return strlen($s);
    }
    function mb_strtolower(string $s, ?string $e = null): string
    {
        /*
         * GUIA DE MANUTENÇÃO — mb_strtolower
         * Responsabilidade: Implementa a responsabilidade “mb strtolower” dentro do módulo de composição geral do runtime.
         * Local arquitetural: app/prontoo.php (composição geral do runtime).
         * Chamadores detectados: `admin_maestro_health_pill_html`, `appointment_status_code`, `appointment_journey_hard_guard_message`, `appointment_journey_role_matches`, `arrow@app/Domain/Appointments/Appointments.php:812`, `page_appointments`, `activity_status_from_ctx`, `activity_display_label` e mais 25.
         * Dependências chamadas: `strtolower`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return strtolower($s);
    }
    function mb_convert_case(string $s, int $mode, ?string $e = null): string
    {
        /*
         * GUIA DE MANUTENÇÃO — mb_convert_case
         * Responsabilidade: Transforma e normaliza “mb convert case” para um formato canônico utilizado pelo restante da aplicação.
         * Local arquitetural: app/prontoo.php (composição geral do runtime).
         * Chamadores detectados: `activity_display_label`, `patient_record_type_label`, `patient_document_type_human_label`.
         * Dependências chamadas: `ucwords`, `strtolower`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return $mode === MB_CASE_TITLE ? ucwords(strtolower($s)) : $s;
    }
}
if (!function_exists("h")) {
    function h($v): string
    {
        /*
         * GUIA DE MANUTENÇÃO — h
         * Responsabilidade: Implementa a responsabilidade “h” dentro do módulo de composição geral do runtime.
         * Local arquitetural: app/prontoo.php (composição geral do runtime).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `htmlspecialchars`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return htmlspecialchars(
            (string) $v,
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8",
        );
    }
}
const PRONTOO_VERSION_FALLBACK = "1.7.22.6";
const PRONTOO_ASSET_REV_FALLBACK = "1.7.15.10";
function prontoo_release_metadata(): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_release_metadata
     * Responsabilidade: Implementa a responsabilidade “prontoo release metadata” dentro do módulo de composição geral do runtime.
     * Local arquitetural: app/prontoo.php (composição geral do runtime).
     * Chamadores detectados: `prontoo_version_contract_status`.
     * Dependências chamadas: `is_array`, `is_file`, `file_get_contents`, `is_string`, `json_decode`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $metadata = null;
    if (is_array($metadata)) {
        return $metadata;
    }
    $metadata = [];
    $file = PRONTOO_ROOT . "/version.json";
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($json)) {
            $metadata = $json;
        }
    }
    return $metadata;
}
$prontooReleaseMetadata = prontoo_release_metadata();
$prontooVersion = trim((string) ($prontooReleaseMetadata["version"] ?? ""));
$prontooRelease = trim((string) ($prontooReleaseMetadata["release"] ?? ""));
$prontooAssetRevision = trim((string) ($prontooReleaseMetadata["asset_version"] ?? ""));
if (!preg_match('/^1\.\d{1,2}\.\d{1,2}\.\d+$/', $prontooVersion)) {
    $prontooVersion = PRONTOO_VERSION_FALLBACK;
}
if ($prontooRelease !== $prontooVersion) {
    $prontooRelease = $prontooVersion;
}
if (!preg_match('/^1\.\d{1,2}\.\d{1,2}\.\d+$/', $prontooAssetRevision)) {
    $prontooAssetRevision = PRONTOO_ASSET_REV_FALLBACK;
}
define("PRONTOO_VERSION", $prontooVersion);
define("PRONTOO_RELEASE", $prontooRelease);
define("PRONTOO_ASSET_REV", $prontooAssetRevision);
function prontoo_version_contract_status(): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_version_contract_status
     * Responsabilidade: Implementa a responsabilidade “prontoo version contract status” dentro do módulo de composição geral do runtime.
     * Local arquitetural: app/prontoo.php (composição geral do runtime).
     * Chamadores detectados: `platform_backend_selftest`.
     * Dependências chamadas: `is_array`, `prontoo_release_metadata`, `trim`, `is_file`, `file_get_contents`, `is_string`, `json_decode`, `preg_match`, `str_contains`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $status = null;
    if (is_array($status)) {
        return $status;
    }
    $issues = [];
    $metadata = prontoo_release_metadata();
    $expected = (string) PRONTOO_VERSION;
    $assetRevision = (string) PRONTOO_ASSET_REV;
    foreach (["version", "release"] as $key) {
        if (trim((string) ($metadata[$key] ?? "")) !== $expected) {
            $issues[] = "version.json:" . $key;
        }
    }
    if (trim((string) ($metadata["asset_version"] ?? "")) !== $assetRevision) {
        $issues[] = "version.json:asset_version";
    }
    if (PRONTOO_VERSION_FALLBACK !== $expected) {
        $issues[] = "app/prontoo.php:version_fallback";
    }
    if (PRONTOO_ASSET_REV_FALLBACK !== $assetRevision) {
        $issues[] = "app/prontoo.php:asset_fallback";
    }
    $manifestFile = PRONTOO_ROOT . "/app/update.manifest.json";
    if (!is_file($manifestFile)) {
        $issues[] = "app/update.manifest.json:missing";
    } else {
        $raw = @file_get_contents($manifestFile);
        $manifest = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($manifest) ||
            trim((string) ($manifest["version"] ?? "")) !== $expected ||
            trim((string) ($manifest["release"] ?? "")) !== $expected) {
            $issues[] = "app/update.manifest.json";
        }
    }
    $landingFile = PRONTOO_ROOT . "/br/index.php";
    if (!is_file($landingFile)) {
        $issues[] = "br/index.php:missing";
    } else {
        $landing = @file_get_contents($landingFile);
        if (!is_string($landing) ||
            !preg_match('/BR_LANDING_VERSION_FALLBACK\s*=\s*["\']([^"\']+)["\']/', $landing, $match) ||
            trim((string) ($match[1] ?? "")) !== $expected) {
            $issues[] = "br/index.php";
        }
    }
    foreach ([
        "app-icon-" . $assetRevision . ".png",
        "prontoo-mark-" . $assetRevision . ".png",
        "favicon-" . $assetRevision . ".png",
        "favicon-" . $assetRevision . ".ico",
        "pix-" . $assetRevision . ".svg",
    ] as $asset) {
        if (!is_file(PRONTOO_ROOT . "/public/assets/" . $asset)) {
            $issues[] = "public/assets/" . $asset;
        }
    }
    $cssFile = PRONTOO_ROOT . "/public/assets/design-system.css";
    if (!is_file($cssFile)) {
        $issues[] = "public/assets/design-system.css:missing";
    } else {
        $css = @file_get_contents($cssFile);
        if (!is_string($css) || !str_contains($css, "pix-" . $assetRevision . ".svg")) {
            $issues[] = "public/assets/design-system.css";
        }
    }
    $status = [
        "ok" => $issues === [],
        "version" => $expected,
        "release" => (string) PRONTOO_RELEASE,
        "asset_version" => $assetRevision,
        "issues" => $issues,
    ];
    return $status;
}
const PRONTOO_PREVIOUS_VERSION = "1.7.22.5";
const PRONTOO_PREVIOUS_ASSET_REV = "1.7.15.10";
unset($prontooReleaseMetadata, $prontooVersion, $prontooRelease, $prontooAssetRevision);
const PRONTOO_MIN_PHP_VERSION = "8.4.0";
const PRONTOO_MIN_MYSQL_VERSION = "8.0.30";
const PRONTOO_SCHEMA_REV = "prontoo_1_7_20_6_clean_schema_r7_layer2_ledger";
const PRONTOO_ARCH_REV = "mysql_php_design_system_contract_1_7_13_1";
const PRONTOO_TENANT_INTEGRITY_STRICT = true;
const PRONTOO_AUDIT_PAGE_VIEWS = false;
const PRONTOO_AUDIT_COMPACT = true;
const PRONTOO_ADMIN_CACHE_TTL = 60;
const PRONTOO_HOT_LIST_LIMIT = 50;
const PRONTOO_ADMIN_LIST_LIMIT = 100;
const PRONTOO_DASHBOARD_COUNTER_TTL = 45;
const PRONTOO_TELEMETRY_SAMPLE_RATE = 10;
const PRONTOO_SERVER_JSON_CACHE = true;
const PRONTOO_SERVER_JSON_CACHE_POLICY = "server-storage-only-domain-invalidated-short-ttl";
const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-speed-label-no-dual-legend-one-row";
const PRONTOO_CMD_BAR_SHAPE_POLICY = "square-container-rounded-actions";
if (PHP_SAPI !== "cli") {
    ini_set("log_errors", "1");
    if (
        is_file(PRONTOO_ROOT . "/app/config.php") ||
        (string) getenv("PRONTOO_CONFIG_PATH") !== ""
    ) {
        ini_set("display_errors", "0");
        ini_set("display_startup_errors", "0");
        ini_set("html_errors", "0");
    }
}
function prontoo_configure_runtime_error_log(): void
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_configure_runtime_error_log
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “prontoo configure runtime error log”.
     * Local arquitetural: app/prontoo.php (composição geral do runtime).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `is_dir`, `mkdir`, `is_writable`, `ini_set`, `is_file`, `unlink`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (PHP_SAPI === "cli") {
        return;
    }
    $logDir = PRONTOO_ROOT . "/ssd/logs";
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set("error_log", $logDir . "/runtime.log");
        $legacyLog = PRONTOO_ROOT . "/error_log";
        if (is_file($legacyLog)) {
            @unlink($legacyLog);
        }
    }
}
prontoo_configure_runtime_error_log();
if (version_compare(PHP_VERSION, PRONTOO_MIN_PHP_VERSION, "<")) {
    if (PHP_SAPI !== "cli" && !headers_sent()) {
        http_response_code(500);
        header("Content-Type: text/plain; charset=utf-8");
    }
    echo "Prontoo requer PHP " .
        PRONTOO_MIN_PHP_VERSION .
        " ou superior. PHP atual: " .
        PHP_VERSION .
        PHP_EOL;
    exit(1);
}
const PRONTOO_NAME = "Prontoo";
const PRONTOO_DEFAULT_ACCENT_COLOR = "#2f6652";
const PRONTOO_DOMAIN = "prontoo.app";
const PRONTOO_TRIAL_DAYS = 30;
const PRONTOO_MONTHLY_PRICE_CENTS = 9990;
const PRONTOO_TRUST_RELEASE_DAYS = 5;
const PRONTOO_DOCUMENT_PDF_TTL_SECONDS = 86400;
const PRONTOO_CANONICAL_HOST = "";
const PRONTOO_TRUST_PROXY_HEADERS = false;
const PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES = 10;
const PRONTOO_MAESTRO_CRON_BUDGET_MS = 90000;
const PRONTOO_MAESTRO_MEMORY_BUDGET_MB = 96;
const PRONTOO_MAESTRO_PI_BUDGET_MS = 45000;
const PRONTOO_MAESTRO_PI_BATCH_SIZE = 120;
const PRONTOO_MAESTRO_SEQUENCE_BUDGET_MS = 12000;
const PRONTOO_RUNTIME_DEEP_BOOT_TTL_SECONDS = 43200;
const PRONTOO_RUNTIME_SELF_CHECK_TTL_SECONDS = 43200;
const PRONTOO_SESSION_IDLE_SECONDS = 14400;
const PRONTOO_SESSION_ABSOLUTE_SECONDS = 43200;
const PRONTOO_ROLES = [
    "recepcionista" => "Recepção",
    "assistente" => "Assistente",
    "medico" => "Profissional",
    "gerente" => "Administrativo",
];
const PRONTOO_ADMIN_ACTIONS = [
    "admin_painel" => ["label" => "Desenvolvedor", "icon" => "space_dashboard"],
    "admin_clinics" => ["label" => "Consultórios", "icon" => "home_health"],
    "admin_health" => ["label" => "Incidentes", "icon" => "crisis_alert"],
    "admin_performance" => ["label" => "Performance", "icon" => "speed"],
    "admin_alerts" => ["label" => "Avisos", "icon" => "campaign"],
    "admin_maintenance" => ["label" => "Manutenção", "icon" => "construction"],
];
class ProntooHttpError extends RuntimeException
{
    public function __construct(public int $status, string $message)
    {
        /*
         * GUIA DE MANUTENÇÃO — ProntooHttpError::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/prontoo.php (composição geral do runtime).
         * Chamadores detectados: `page_admin_errors`, `page_admin_security`, `Core.Database.SqlScopeGuard::guard`, `Core.Database.SqlScopeGuard::assertScopedWrite`, `Core.Invariant.Context.MaestroContextInvariant::assertWrite`, `Core.Invariant.Context.TaskContextInvariant::deny`, `Core.Invariant.Mutation.MutationInvariant::deny`, `Core.Invariant.Relation.ForeignKeyGraph::deny` e mais 17.
         * Dependências chamadas: `parent::__construct`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        parent::__construct($message);
    }
}
require_once __DIR__ . "/bootstrap_architecture.php";
require_once __DIR__ . "/bootstrap_specialized.php";
if (function_exists("server_json_cache_register_deferred_invalidation")) {
    server_json_cache_register_deferred_invalidation();
}
register_shutdown_function("prontoo_request_metric_shutdown");
