<?php
if (!function_exists("storage_path")) {
    function storage_path(string $suffix = ""): string {
        $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . "ssd";
        return $suffix === "" ? $base : $base . DIRECTORY_SEPARATOR . ltrim($suffix, "/\\");
    }
}
try {
    $configFile = dirname(__DIR__) . "/app/config.php";
    if (is_file($configFile)) {
        require_once dirname(__DIR__) . "/app/Support/PhpCompat.php";
        require_once dirname(__DIR__) . "/app/Support/Telemetry.php";
        if (function_exists("telemetry_route_cycle_register")) telemetry_route_cycle_register("landing");
    }
} catch (Throwable $e) {
    error_log("[Prontoo landing route cycle] " . $e->getMessage());
}
