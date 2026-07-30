<?php
declare(strict_types=1);
require_once dirname(__DIR__) . "/app/Support/SecurityPrivacy.php";
if (PHP_SAPI !== "cli") {
    
    $brSecure = security_https_active();
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
    unset($brSecure, $brHost, $brUri);
}
const BR_LANDING_VERSION_FALLBACK = "1.7.30.20";
require __DIR__ . "/runtime-core.php";
require __DIR__ . "/runtime-telemetry.php";
require __DIR__ . "/runtime-data.php";
require __DIR__ . "/runtime-schema.php";
require __DIR__ . "/landing/view-head.php";
require __DIR__ . "/landing/view-hero.php";
require __DIR__ . "/landing/view-flow.php";
require __DIR__ . "/landing/view-final.php";
