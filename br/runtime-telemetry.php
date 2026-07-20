<?php
if (!function_exists("storage_path")) {
    function storage_path(string $suffix = ""): string
    {
        $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . "storage";
        return $suffix === ""
            ? $base
            : $base . DIRECTORY_SEPARATOR . ltrim($suffix, "/\\");
    }
}

function br_landing_register_route_telemetry(float $startedAt): void
{
    register_shutdown_function(static function () use ($startedAt): void {
        $configFile = dirname(__DIR__) . "/app/config.php";
        if (!is_file($configFile)) {
            return;
        }
        $elapsedMs = round(max(0.0, (microtime(true) - $startedAt) * 1000), 3);
        $status = (int) http_response_code();
        if ($status < 100) {
            $status = 200;
        }
        $lastError = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        $fatal = is_array($lastError) && in_array((int) ($lastError["type"] ?? 0), $fatalTypes, true);
        try {
            require_once dirname(__DIR__) . "/app/Support/Telemetry.php";
            if (!function_exists("telemetry_append_route_performance_metric")) {
                return;
            }
            telemetry_append_route_performance_metric([
                "ts" => time(),
                "route" => "landing",
                "queries" => 0,
                "elapsed_ms" => $elapsedMs,
                "query_ms" => 0.0,
                "success" => !$fatal && $status < 500 ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            error_log("[Prontoo landing telemetry] " . $e->getMessage());
        }
    });
}

br_landing_register_route_telemetry($brLandingRequestStartedAt);

