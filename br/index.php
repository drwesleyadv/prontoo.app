<?php
declare(strict_types=1);
$brRouteStartedMonotonicNs = hrtime(true);
$brRouteStartedUnixUs = (int) floor(microtime(true) * 1000000);
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
require_once dirname(__DIR__) . "/app/Runtime/Autoload/ProntooAutoloader.php";
\Prontoo\Runtime\SupportTelemetry\SupportTelemetryRuntimeOperations01::telemetry_route_start_marker(
    (string) ($_GET["asset"] ?? "") !== ""
        ? "landing_" . (string) $_GET["asset"]
        : "landing",
    $brRouteStartedMonotonicNs,
    $brRouteStartedUnixUs,
);
unset($brRouteStartedMonotonicNs, $brRouteStartedUnixUs);

if (PHP_SAPI !== "cli") {
    
    $brSecure = \Prontoo\Runtime\SecurityPrivacy\SecurityPrivacyRuntimeOperations01::security_https_active();
    $brHost = strtolower(mb_trim((string) ($_SERVER["HTTP_HOST"] ?? "")));
    $brHost = preg_replace('/:\d+$/', '', $brHost) ?? "";
    if (!$brSecure || $brHost !== "prontoo.app") {
        $brUri = (string) ($_SERVER["REQUEST_URI"] ?? "/");
        if ($brUri === "" || !str_starts_with($brUri, "/")) {
            $brUri = "/";
        }
        if (!headers_sent()) {
            header("Location: https://prontoo.app" . $brUri, true, 308);
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        exit;
    }
    unset($brSecure, $brHost, $brUri);
}
const BR_LANDING_VERSION_FALLBACK = "1.9.15.5";
require __DIR__ . "/runtime-core.php";
require __DIR__ . "/runtime-data.php";
require __DIR__ . "/runtime-schema.php";
require __DIR__ . "/landing/view-head.php";
require __DIR__ . "/landing/view-hero.php";
require __DIR__ . "/landing/view-flow.php";
require __DIR__ . "/landing/view-final.php";
\Prontoo\Runtime\SupportTelemetry\SupportTelemetryRuntimeOperations01::telemetry_route_finish_marker();
