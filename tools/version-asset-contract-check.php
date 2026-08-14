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
$css = (string) @file_get_contents($root . '/public/assets/presentation.css');
if (!str_contains($css, 'pix-' . $assetRevision . '.svg')) {
    throw new RuntimeException('CSS diverge do asset_version.');
}
$runner = (string) @file_get_contents($root . '/app/Runtime/Runner.php');
$routeCatalog = (string) @file_get_contents($root . '/app/Runtime/Routing/RouteCatalog.php');
$routeRegistry = (string) @file_get_contents($root . '/app/Runtime/Routing/RouteRegistry.php');
$moduleCatalog = (string) @file_get_contents($root . '/app/Runtime/Modules/RuntimeModuleCatalog.php');
$loginRuntime = (string) @file_get_contents($root . '/app/Runtime/AuthOnboarding/AuthOnboardingRuntimeOperations03.php');
$autotestRuntime = (string) @file_get_contents($root . '/app/Runtime/SupportFoundation/SupportFoundationRuntimeOperations02.php');
$publicRuntime = (string) @file_get_contents($root . '/app/Runtime/PublicWeb/PublicWebRuntimeOperations01.php');
foreach ([
    'RouteRegistry::names()',
    'RouteRegistry::publicNames()',
    'RouteRegistry::jsonNames()',
    'RouteRegistry::definition($route)?->isPublicLight($method)',
] as $contract) {
    if (!str_contains($routeCatalog, $contract)) {
        throw new RuntimeException('RouteCatalog não deriva do registry unificado: ' . $contract);
    }
}
$telemetrySpec = "'login_telemetry_wave' => [\\Prontoo\\Runtime\\PublicWeb\\PublicWebRuntimeOperations01::class, 'page_public_telemetry_tombstone', true, true, ['GET'], []]";
if (!str_contains($routeRegistry, $telemetrySpec)) {
    throw new RuntimeException('URL legado de telemetria deve apontar somente para o tombstone sem dados.');
}
$statusSpec = "'status' => [\\Prontoo\\Runtime\\PublicWeb\\PublicWebRuntimeOperations01::class, 'page_public_status', true, false, ['GET'], []]";
if (!str_contains($routeRegistry, $statusSpec)) {
    throw new RuntimeException('Status público deve usar a superfície genérica sem métricas.');
}
if (str_contains($loginRuntime, 'login_telemetry_wave_html()')) {
    throw new RuntimeException('Login público não pode renderizar telemetria.');
}
foreach ([
    "\$publicTelemetry =",
    "\$publicStatus =",
    "headers_secure(\$publicStatus)",
] as $forbidden) {
    if (str_contains($runner, $forbidden)) {
        throw new RuntimeException('Exceção pública de telemetria ainda presente no Runner: ' . $forbidden);
    }
}
if (!str_contains($publicRuntime, '"telemetry_public" => false') ||
    str_contains($publicRuntime, 'telemetry_route_requests_series_20d') ||
    str_contains($publicRuntime, 'telemetry_database_record_series_30d')) {
    throw new RuntimeException('Superfície pública de telemetria contém dados ou fonte de métricas.');
}
if (str_contains($publicRuntime, 'PRONTOO_VERSION')) {
    throw new RuntimeException('Status público não deve revelar a versão da aplicação.');
}
if (!str_contains($autotestRuntime, 'REQUEST_METHOD')) {
    throw new RuntimeException('Autoteste de login sem restrição de método.');
}
if (!str_contains($autotestRuntime, 'security_ip_bucket("login_autotest")')) {
    throw new RuntimeException('Autoteste de login sem limite independente por IP.');
}
if (str_contains($autotestRuntime, 'platform_login_loaded_audit(') ||
    str_contains($autotestRuntime, 'login_autoteste_leve')) {
    throw new RuntimeException('Autoteste público não pode gravar auditoria por chamada.');
}
if (!str_contains($moduleCatalog, 'RouteRegistry::definition($route)')) {
    throw new RuntimeException('Catálogo modular não deriva da definição unificada de rota.');
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
echo json_encode([
    'ok' => true,
    'asset_version' => $assetRevision,
    'assets_verified' => count($assets),
    'public_telemetry' => false,
    'public_status_metrics' => false,
    'login_autotest_get_only' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
