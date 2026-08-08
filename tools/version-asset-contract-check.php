<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$raw = @file_get_contents($root . '/version.json');
$metadata = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($metadata)) {
    throw new RuntimeException('version.json inválido.');
}
$assetRevision = mb_trim((string) ($metadata['asset_version'] ?? ''));
if (!preg_match('/^1\.\d{1,2}\.\d{1,2}\.\d+$/', $assetRevision)) {
    throw new RuntimeException('asset_version inválido.');
}
$app = @file_get_contents($root . '/app/prontoo.php');
if (!is_string($app) ||
    !preg_match('/PRONTOO_ASSET_REV_FALLBACK\s*=\s*["\x27]([^"\x27]+)["\x27]/', $app, $match) ||
    mb_trim((string) ($match[1] ?? '')) !== $assetRevision) {
    throw new RuntimeException('Fallback de assets divergente.');
}
$assets = [
    'app-icon-' . $assetRevision . '.png',
    'prontoo-mark-' . $assetRevision . '.png',
    'favicon-' . $assetRevision . '.png',
    'favicon-' . $assetRevision . '.ico',
    'pix-' . $assetRevision . '.svg',
];
foreach ($assets as $assetFile) {
    if (!is_file($root . '/public/assets/' . $assetFile)) {
        throw new RuntimeException('Asset público ausente: ' . $assetFile);
    }
}
$css = (string) @file_get_contents($root . '/public/assets/design-system.css');
if (!str_contains($css, 'pix-' . $assetRevision . '.svg')) {
    throw new RuntimeException('CSS diverge do asset_version.');
}
$javascript = (string) @file_get_contents($root . '/public/assets/app.js');
foreach ([
    'let needsInitialRefresh = false;',
    'needsInitialRefresh = true;',
    'if (needsInitialRefresh) refresh(true);',
    'endpoint.searchParams.set("r", "login_telemetry_wave");',
    'renderLoginTelemetryWave(wrap, payload);',
] as $contract) {
    if (!str_contains($javascript, $contract)) {
        throw new RuntimeException('Contrato JavaScript das faixas ausente: ' . $contract);
    }
}
$runner = (string) @file_get_contents($root . '/app/Runtime/Runner.php');
$routeCatalog = (string) @file_get_contents($root . '/app/Runtime/Routing/RouteCatalog.php');
$moduleCatalog = (string) @file_get_contents($root . '/app/Runtime/Modules/RuntimeModuleCatalog.php');
$auth = (string) @file_get_contents($root . '/app/Auth/AuthOnboarding.php');
$section = static function (string $source, string $start, string $end): string {
    $from = strpos($source, $start);
    $to = $from === false ? false : strpos($source, $end, $from + strlen($start));
    if ($from === false || $to === false || $to <= $from) {
        throw new RuntimeException('Seção de contrato ausente: ' . $start);
    }
    return substr($source, $from, $to - $from);
};
foreach ([
    'mapa executável' => $section($routeCatalog, 'private const ROUTES = [', 'private const PUBLIC_ROUTES = ['),
    'mapa público' => $section($routeCatalog, 'private const PUBLIC_ROUTES = [', 'private const JSON_ROUTES = ['),
    'mapa JSON' => $section($routeCatalog, 'private const JSON_ROUTES = [', 'private function __construct()'),
    'boot público leve' => $section($routeCatalog, 'public static function isPublicLight(', "\n    }\n}"),
] as $label => $source) {
    if (!str_contains($source, "'login_telemetry_wave'")) {
        throw new RuntimeException('Rota das faixas ausente em ' . $label . '.');
    }
}
foreach ([
    "\$publicTelemetry = \$route === 'login_telemetry_wave';",
    "\$publicStatus = \$route === 'status' || \$publicTelemetry;",
    '\\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations01::headers_secure($publicStatus);',
    "\$context = \$publicStatus || \$publicHome || \$route === 'logout' ? [] : \\ctx();",
    'if (!$publicTelemetry) {',
    "if (\$route !== 'logout' && !\$publicTelemetry) {",
    "if (\$route !== 'logout' && !\$publicStatus) {",
] as $contract) {
    if (!str_contains($runner, $contract)) {
        throw new RuntimeException('Contrato de execução das faixas ausente: ' . $contract);
    }
}
if (!str_contains($moduleCatalog, "'login_telemetry_wave' => []")) {
    throw new RuntimeException('Catálogo modular não reconhece a rota das faixas.');
}
if (is_file($root . '/app/Support/ModuleLoader.php')) {
    throw new RuntimeException('Fachada global de composição ainda está presente.');
}
foreach ([
    'RuntimeModuleComposition::loader()->loadRouteModules($route);',
    'RuntimeBootCoordinator::bootDatabaseForRoute(',
    'JsonResponder::send(',
] as $contract) {
    if (!str_contains($runner, $contract)) {
        throw new RuntimeException('Runner não consome o runtime nativo diretamente: ' . $contract);
    }
}
if (!str_contains($auth, 'function page_login_telemetry_wave(): void')) {
    throw new RuntimeException('Endpoint JSON das faixas ausente.');
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
    'asset_version' => $assetRevision,
    'assets_verified' => count($assets),
    'authenticated_initial_refresh' => true,
    'telemetry_route_registered' => true,
    'telemetry_route_public_json' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;