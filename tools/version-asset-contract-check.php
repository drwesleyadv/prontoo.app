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
foreach ([
    'app-icon-' . $assetRevision . '.png',
    'prontoo-mark-' . $assetRevision . '.png',
    'favicon-' . $assetRevision . '.png',
    'favicon-' . $assetRevision . '.ico',
    'pix-' . $assetRevision . '.svg',
] as $assetFile) {
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
$publicRuntime = (string) @file_get_contents($root . '/app/Runtime/PublicWeb/PublicWebRuntimeOperations01.php');
$uiRuntime = (string) @file_get_contents($root . '/app/Runtime/UiComponents/UiComponentsRuntimeOperations02.php');
$footerPresentation = (string) @file_get_contents($root . '/app/Presentation/AuthOnboarding/AuthOnboardingPresentationOperations01.php');
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
foreach ([
    "'status' => [\\Prontoo\\Runtime\\PublicWeb\\PublicWebRuntimeOperations01::class, 'page_public_status', true, false, ['GET'], []]",
    "'login_telemetry_wave' => [\\Prontoo\\Runtime\\PublicWeb\\PublicWebRuntimeOperations01::class, 'page_public_telemetry_tombstone', true, true, ['GET'], []]",
    "'login_autotest' => [\\Prontoo\\Runtime\\PublicWeb\\PublicWebRuntimeOperations01::class, 'page_public_login_autotest', true, false, ['GET'], []]",
] as $routeContract) {
    if (!str_contains($routeRegistry, $routeContract)) {
        throw new RuntimeException('Superfície pública não está neutralizada: ' . $routeContract);
    }
}
if (!str_contains($routeRegistry, "'footer_telemetry_wave' => [\\Prontoo\\Runtime\\AuthOnboarding\\AuthOnboardingRuntimeOperations07::class, 'page_footer_telemetry_wave', false, true, [], []]")) {
    throw new RuntimeException('Rota JSON do rodapé deve permanecer restrita à sessão autenticada.');
}
if (str_contains($loginRuntime, 'login_telemetry_wave_html()')) {
    throw new RuntimeException('Login público não pode restaurar o renderer legado de telemetria.');
}
foreach ([
    'footer_telemetry_mountains_html(',
    'footer_telemetry_wave_data()',
    '$bodyClass .= " has-telemetry-mountains";',
    '$footerTelemetryHtml .',
] as $required) {
    if (!str_contains($uiRuntime, $required)) {
        throw new RuntimeException('Layout não preserva o rodapé de telemetria normalizada: ' . $required);
    }
}
foreach ([
    'footer_telemetry_wave_path(',
    ' C ',
    '$public ? "#347963"',
    '$public ? "#1f6f56"',
    'viewBox="0 0 1000 250"',
    'data-footer-telemetry-lines-ready="1"',
] as $required) {
    if (!str_contains($footerPresentation, $required)) {
        throw new RuntimeException('Contrato visual histórico seguro do rodapé ausente: ' . $required);
    }
}
if (str_contains($footerPresentation, 'data-refresh-url=') || str_contains($footerPresentation, 'json_encode(')) {
    throw new RuntimeException('Rodapé público não pode serializar valores de telemetria nem abrir refresh público.');
}
foreach ([
    '$publicTelemetry =',
    '$publicStatus =',
    'headers_secure($publicStatus)',
] as $forbidden) {
    if (str_contains($runner, $forbidden)) {
        throw new RuntimeException('Exceção pública de telemetria ainda presente no Runner: ' . $forbidden);
    }
}
foreach ([
    'telemetry_route_requests_series_20d',
    'telemetry_database_record_series_30d',
    'telemetry_comparative_summary',
    'prontoo_login_selftest_light',
    'PRONTOO_VERSION',
    'Prontoo operacional',
    'serviço está disponível',
] as $forbidden) {
    if (str_contains($publicRuntime, $forbidden)) {
        throw new RuntimeException('Sinal operacional público proibido: ' . $forbidden);
    }
}
foreach ([
    '"telemetry_public" => false',
    'http_response_code(404)',
    '"auto_login" => false',
] as $required) {
    if (!str_contains($publicRuntime, $required)) {
        throw new RuntimeException('Contrato público neutralizado ausente: ' . $required);
    }
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
    'public_telemetry' => false,
    'public_telemetry_geometry' => 'historical_cubic_wave_normalized_path_only',
    'public_status_signal' => false,
    'public_login_autotest_signal' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
