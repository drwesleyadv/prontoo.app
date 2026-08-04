<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$runner = (string) file_get_contents($root . '/app/Runtime/Runner.php');
$loader = (string) file_get_contents($root . '/app/Support/ModuleLoader.php');
$auth = (string) file_get_contents($root . '/app/Auth/AuthOnboarding.php');
$javascript = (string) file_get_contents($root . '/public/assets/app.js');
$css = (string) file_get_contents($root . '/public/assets/design-system.css');
$section = static function (string $source, string $start, string $end): string {
    $from = strpos($source, $start);
    $to = $from === false ? false : strpos($source, $end, $from + strlen($start));
    if ($from === false || $to === false || $to <= $from) {
        throw new RuntimeException('Seção de contrato ausente: ' . $start);
    }
    return substr($source, $from, $to - $from);
};
$routeMap = $section($runner, 'function prontoo_route_map(): array', 'function prontoo_public_runtime_routes(): array');
$publicRoutes = $section($runner, 'function prontoo_public_runtime_routes(): array', 'function prontoo_json_runtime_routes(): array');
$jsonRoutes = $section($runner, 'function prontoo_json_runtime_routes(): array', 'function prontoo_route_wants_json(string $route): bool');
$publicLight = $section($runner, 'function prontoo_route_is_public_light(string $route): bool', 'function prontoo_schema_boot_marker_path(): string');
$run = $section($runner, 'function prontoo_run(bool $installMode = false): void', 'function prontoo_patient_tab_active_rows(');
foreach ([
    'mapa executável' => $routeMap,
    'mapa público' => $publicRoutes,
    'mapa JSON' => $jsonRoutes,
    'boot público leve' => $publicLight,
] as $label => $source) {
    if (!str_contains($source, '"login_telemetry_wave"')) {
        throw new RuntimeException('Rota das faixas ausente em ' . $label . '.');
    }
}
foreach ([
    '$publicTelemetry = $r === "login_telemetry_wave";',
    'headers_secure($publicStatus || $publicTelemetry);',
    '$publicStatus || $publicTelemetry || $publicHome || $r === "logout" ? [] : ctx();',
    'if (!$publicTelemetry) {',
    'if ($r !== "logout" && !$publicTelemetry) {',
    '!$publicTelemetry &&',
    'if ($r !== "logout" && !$publicStatus && !$publicTelemetry) {',
] as $contract) {
    if (!str_contains($run, $contract)) {
        throw new RuntimeException('Contrato de execução das faixas ausente: ' . $contract);
    }
}
if (!str_contains($loader, "'login_telemetry_wave' => []")) {
    throw new RuntimeException('ModuleLoader não reconhece a rota das faixas.');
}
if (!str_contains($auth, 'function page_login_telemetry_wave(): void')) {
    throw new RuntimeException('Endpoint JSON das faixas ausente.');
}
foreach ([
    'endpoint.searchParams.set("r", "login_telemetry_wave");',
    'if (needsInitialRefresh) refresh(true);',
    'renderLoginTelemetryWave(wrap, payload);',
] as $contract) {
    if (!str_contains($javascript, $contract)) {
        throw new RuntimeException('Contrato JavaScript das faixas ausente: ' . $contract);
    }
}
foreach ([
    'body.has-telemetry-mountains .login-telemetry-wave{',
    'display:block;',
    'height:15vh;',
    'pointer-events:none;',
    'body.has-telemetry-mountains .login-telemetry-wave-path{stroke:none;opacity:.3}',
] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException('Contrato CSS das faixas ausente: ' . $contract);
    }
}
echo json_encode([
    'ok' => true,
    'route' => 'login_telemetry_wave',
    'registered' => true,
    'public_json' => true,
    'authenticated_initial_render' => true,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
