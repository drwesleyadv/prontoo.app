<?php
declare(strict_types=1);
$GLOBALS["PRONTOO_REQUEST_STARTED_AT"] = microtime(true);
$GLOBALS["PRONTOO_QUERY_COUNT"] = 0;
$GLOBALS["PRONTOO_QUERY_TOTAL_MS"] = 0.0;
$GLOBALS["PRONTOO_QUERY_WIDE_SELECT_COUNT"] = 0;
if (!defined("PRONTOO_ROOT")) {
    define("PRONTOO_ROOT", dirname(__DIR__));
}
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
        return $l === null ? substr($s, $a) : substr($s, $a, $l);
    }
    function mb_strlen(string $s, ?string $e = null): int
    {
        return strlen($s);
    }
    function mb_strtolower(string $s, ?string $e = null): string
    {
        return strtolower($s);
    }
    function mb_convert_case(string $s, int $mode, ?string $e = null): string
    {
        return $mode === MB_CASE_TITLE ? ucwords(strtolower($s)) : $s;
    }
}
if (!function_exists("h")) {
    function h($v): string
    {
        return htmlspecialchars(
            (string) $v,
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8",
        );
    }
}
const PRONTOO_VERSION_FALLBACK = "1.7.20.1";
const PRONTOO_ASSET_REV_FALLBACK = "1.7.15.10";
function prontoo_release_metadata(): array
{
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
const PRONTOO_PREVIOUS_VERSION = "1.7.17.4";
const PRONTOO_PREVIOUS_ASSET_REV = "1.7.15.10";
unset($prontooReleaseMetadata, $prontooVersion, $prontooRelease, $prontooAssetRevision);
const PRONTOO_MIN_PHP_VERSION = "8.4.0";
const PRONTOO_MIN_MYSQL_VERSION = "8.0.30";
const PRONTOO_SCHEMA_REV = "prontoo_1_7_13_1_clean_schema_r6_multirole";
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
    if (PHP_SAPI === "cli") {
        return;
    }
    $logDir = PRONTOO_ROOT . "/storage/logs";
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
        parent::__construct($message);
    }
}
require_once __DIR__ . "/bootstrap_architecture.php";
require_once __DIR__ . "/bootstrap_specialized.php";
function prontoo_release_cleanup_1_7_14_8(): void
{
    static $done = false;
    if ($done || (string) getenv("PRONTOO_UPDATE_VALIDATION") === "1") {
        return;
    }
    $done = true;
    $cacheDir = storage_path("cache");
    if (!is_dir($cacheDir)) {
        prontoo_fs_mkdir($cacheDir, 0750);
    }
    $releaseManifestPayload = PRONTOO_ROOT . "/app/release.manifest.json";
    if (is_file($releaseManifestPayload)) {
        $raw = @file_get_contents($releaseManifestPayload);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($json) &&
            trim((string) ($json["version"] ?? "")) === PRONTOO_VERSION &&
            trim((string) ($json["release"] ?? "")) === PRONTOO_VERSION) {
            prontoo_fs_write(
                PRONTOO_ROOT . "/app/update.manifest.json",
                rtrim((string) $raw) . PHP_EOL,
            );
        }
        prontoo_fs_unlink($releaseManifestPayload, false);
    }
    $marker = $cacheDir . "/release_cleanup_1_7_14_8.json";
    if (is_file($marker)) {
        return;
    }
    $removed = [];
    $removeFile = static function (string $path) use (&$removed): void {
        if (!is_file($path)) {
            return;
        }
        if (prontoo_fs_unlink($path, false)) {
            $removed[] = str_replace(PRONTOO_ROOT . "/", "", $path);
        }
    };
    $obsoleteRevisions = [
        "1.7.13.1",
        "1.7.13.2",
        "1.7.13.3",
        "1.7.13.4",
        "1.7.13.5",
        "1.7.13.9",
        "1.7.14.6",
    ];
    foreach ($obsoleteRevisions as $revision) {
        foreach (["app-icon", "favicon", "prontoo-mark"] as $name) {
            $removeFile(
                PRONTOO_ROOT .
                    "/public/assets/" .
                    $name .
                    "-" .
                    $revision .
                    ".png",
            );
        }
        $removeFile(
            PRONTOO_ROOT .
                "/public/assets/favicon-" .
                $revision .
                ".ico",
        );
        $removeFile(
            PRONTOO_ROOT . "/public/assets/pix-" . $revision . ".svg",
        );
    }
    $removeFile($cacheDir . "/br_landing_devices.json");
    $removeFile($cacheDir . "/br_landing_devices.json.lock");
    foreach (
        [
            $cacheDir . "/public_platform_stats_*.json",
            $cacheDir . "/public_platform_stats_*.json.lock",
            $cacheDir . "/public_database_record_total_*.json",
            $cacheDir . "/public_database_record_total_*.json.lock",
        ]
        as $pattern
    ) {
        foreach (glob($pattern) ?: [] as $file) {
            $removeFile((string) $file);
        }
    }
    $currentSchemaMarker = function_exists("prontoo_schema_boot_marker_path")
        ? prontoo_schema_boot_marker_path()
        : "";
    foreach ($cacheDir !== "" ? glob($cacheDir . "/prontoo_schema_boot_*.json") ?: [] : [] as $file) {
        if ($currentSchemaMarker !== "" && $file === $currentSchemaMarker) {
            continue;
        }
        $removeFile((string) $file);
    }
    $payload = json_encode(
        [
            "ok" => true,
            "version" => PRONTOO_VERSION,
            "removed" => count($removed),
            "at" => date("c"),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
    if (is_string($payload)) {
        prontoo_fs_write($marker, $payload);
    }
}
prontoo_release_cleanup_1_7_14_8();
if (function_exists("server_json_cache_register_deferred_invalidation")) {
    server_json_cache_register_deferred_invalidation();
}
register_shutdown_function("prontoo_request_metric_shutdown");
