<?php
declare(strict_types=1);
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
if (!defined("PRONTOO_ROOT")) {
    define("PRONTOO_ROOT", dirname(__DIR__));
}
require_once __DIR__ . "/Runtime/Autoload/ProntooAutoloader.php";
\Prontoo\Core\Invariant\InvariantRuntimeBinding::configure(
    new \Prontoo\Infrastructure\Invariant\PdoInvariantRuntimeAdapter(
        static fn(int $clinicId): bool => \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_read_only_db($clinicId),
        static function (string $key, string $sql, string $detail): void {
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::record_scope_violation($key, $sql, $detail);
        },
    ),
);
if (PHP_SAPI !== "cli") {
    
    $prontooRequestSecure = \Prontoo\Runtime\SecurityPrivacy\SecurityPrivacyRuntimeOperations01::security_https_active();
    $prontooRequestHost = strtolower(mb_trim((string) ($_SERVER["HTTP_HOST"] ?? "")));
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
        $prontooRequestSecure,
        $prontooRequestHost,
        $prontooRequestUri,
    );
}

if (!defined("PRONTOO_SSD_ROOT")) {
    define("PRONTOO_SSD_ROOT", PRONTOO_ROOT . "/ssd");
    define("PRONTOO_PDF_ROOT", PRONTOO_SSD_ROOT . "/pdfs");
    define("PRONTOO_IMAGE_UPLOAD_ROOT", PRONTOO_SSD_ROOT . "/img");
}
foreach ([PRONTOO_SSD_ROOT, PRONTOO_PDF_ROOT, PRONTOO_IMAGE_UPLOAD_ROOT] as $prontooPersistentDir) {
    if (!is_dir($prontooPersistentDir)) {
        @mkdir($prontooPersistentDir, 0750, true);
    }
    if (is_dir($prontooPersistentDir)) {
        @chmod($prontooPersistentDir, 0750);
    }
}
unset($prontooPersistentDir);
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
const PRONTOO_VERSION_FALLBACK = "1.9.14.3";
const PRONTOO_ASSET_REV_FALLBACK = "1.9.14.3";
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
$prontooVersion = mb_trim((string) ($prontooReleaseMetadata["version"] ?? ""));
$prontooRelease = mb_trim((string) ($prontooReleaseMetadata["release"] ?? ""));
$prontooAssetRevision = mb_trim((string) ($prontooReleaseMetadata["asset_version"] ?? ""));
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
        if (mb_trim((string) ($metadata[$key] ?? "")) !== $expected) {
            $issues[] = "version.json:" . $key;
        }
    }
    if (mb_trim((string) ($metadata["asset_version"] ?? "")) !== $assetRevision) {
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
        if (!is_array($manifest)) {
            $issues[] = "app/update.manifest.json:invalid";
        } else {
            foreach ([
                "version" => $expected,
                "release" => $expected,
                "build" => mb_trim((string) ($metadata["build"] ?? "")),
                "schema_revision" => mb_trim((string) ($metadata["schema_revision"] ?? "")),
            ] as $key => $canonicalValue) {
                if (mb_trim((string) ($manifest[$key] ?? "")) !== $canonicalValue) {
                    $issues[] = "app/update.manifest.json:" . $key;
                }
            }
        }
    }
    $landingFile = PRONTOO_ROOT . "/br/index.php";
    if (!is_file($landingFile)) {
        $issues[] = "br/index.php:missing";
    } else {
        $landing = @file_get_contents($landingFile);
        if (!is_string($landing) ||
            !preg_match('/BR_LANDING_VERSION_FALLBACK\s*=\s*["\']([^"\']+)["\']/', $landing, $match) ||
            mb_trim((string) ($match[1] ?? "")) !== $expected) {
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
    $cssFile = PRONTOO_ROOT . "/public/assets/presentation.css";
    if (!is_file($cssFile)) {
        $issues[] = "public/assets/presentation.css:missing";
    } else {
        $css = @file_get_contents($cssFile);
        if (!is_string($css) || !str_contains($css, "pix-" . $assetRevision . ".svg")) {
            $issues[] = "public/assets/presentation.css";
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
unset($prontooReleaseMetadata, $prontooVersion, $prontooRelease, $prontooAssetRevision);
const PRONTOO_MIN_PHP_VERSION = "8.4.0";
const PRONTOO_MIN_MYSQL_VERSION = "8.0.30";
const PRONTOO_SCHEMA_REV = "prontoo_clean_schema_r7_layer2_ledger";
const PRONTOO_ARCH_REV = "mysql_php_presentation_contract_v1";
const PRONTOO_TENANT_INTEGRITY_STRICT = true;
const PRONTOO_AUDIT_PAGE_VIEWS = false;
const PRONTOO_AUDIT_COMPACT = true;
const PRONTOO_ADMIN_CACHE_TTL = 60;
const PRONTOO_HOT_LIST_LIMIT = 50;
const PRONTOO_ADMIN_LIST_LIMIT = 100;
const PRONTOO_DASHBOARD_COUNTER_TTL = 45;
const PRONTOO_SERVER_JSON_CACHE = true;
const PRONTOO_SERVER_JSON_CACHE_POLICY = "filesystem-scoped-generation-read-your-writes-v2";
const PRONTOO_PERFORMANCE_CHARTS_POLICY = "canonical-route-telemetry-velocity-and-ledger-write-series-20d";
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
    $logDir = PRONTOO_ROOT . "/ssd/logs";
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set("error_log", $logDir . "/runtime.log");
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
const PRONTOO_SESSION_IDLE_SECONDS = 3600;
const PRONTOO_SESSION_ABSOLUTE_SECONDS = 43200;
const PRONTOO_AUTH_POLICY_GENERATION = "prontoo-auth-mfa-v1";
const PRONTOO_ROLES = [
    "recepcionista" => "Recepção",
    "assistente" => "Assistente",
    "medico" => "Profissional",
    "gerente" => "Administrativo",
];
const PRONTOO_ADMIN_ACTIONS = [
    "admin_performance" => ["label" => "Métricas", "icon" => "monitoring"],
    "admin_clinics" => ["label" => "Consultórios", "icon" => "home_health"],
    "admin_administration" => ["label" => "Administração", "icon" => "tune"],
];
class ProntooHttpError extends RuntimeException
{
    public function __construct(public int $status, string $message)
    {

        parent::__construct($message);
    }
}
require_once __DIR__ . "/bootstrap_architecture.php";
\Prontoo\Presentation\SpeedChartGeometry\SpeedChartGeometryPresentationOperations01::prontoo_register_speed_chart_geometry();
\Prontoo\Runtime\Modules\RuntimeModuleComposition::loader()->loadRuntimeCore(
    \Prontoo\Runtime\Modules\RuntimeBootPolicy::useLightBoot(getenv('PRONTOO_DISABLE_LIGHT_BOOT')),
);
if (is_callable([\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::class, 'server_json_cache_register_deferred_invalidation'])) {
    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_register_deferred_invalidation();
}
