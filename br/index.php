<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    // PRONTOO_HTTPS_RUNTIME_GUARD
    $brHttps = strtolower(trim((string) ($_SERVER["HTTPS"] ?? "")));
    $brForwardedParts = explode(",", strtolower((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")));
    $brForwardedProto = trim((string) ($brForwardedParts[0] ?? ""));
    $brSecure = in_array($brHttps, ["on", "1"], true) ||
        (int) ($_SERVER["SERVER_PORT"] ?? 0) === 443 ||
        $brForwardedProto === "https";
    $brHost = strtolower(trim((string) ($_SERVER["HTTP_HOST"] ?? "")));
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
    unset($brHttps, $brForwardedParts, $brForwardedProto, $brSecure, $brHost, $brUri);
}
const BR_LANDING_VERSION_FALLBACK = "1.7.22.6";
require __DIR__ . "/runtime-core.php";
require __DIR__ . "/runtime-telemetry.php";
require __DIR__ . "/runtime-data.php";
require __DIR__ . "/runtime-schema.php";
require __DIR__ . "/landing/view-head.php";
require __DIR__ . "/landing/view-hero.php";
require __DIR__ . "/landing/view-flow.php";
require __DIR__ . "/landing/view-final.php";
