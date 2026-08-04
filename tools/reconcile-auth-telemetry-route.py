from pathlib import Path
import datetime
import hashlib
import json

root = Path('.')
now = datetime.datetime.now(datetime.timezone.utc).isoformat()


def replace_once(source: str, old: str, new: str, label: str) -> str:
    if old not in source:
        raise SystemExit(f'insertion point not found: {label}')
    return source.replace(old, new, 1)


runner_path = root / 'app/Runtime/Runner.php'
runner = runner_path.read_text()
runner = replace_once(
    runner,
    '        $publicStatus = $r === "status";\n        $publicTelemetry = $r === "login_telemetry_wave";\n        $publicHome = false;\n        headers_secure($publicStatus || $publicTelemetry);',
    '        $publicTelemetry = $r === "login_telemetry_wave";\n        $publicStatus = $r === "status" || $publicTelemetry;\n        $publicHome = false;\n        headers_secure($publicStatus);',
    'public route flags',
)
runner = replace_once(
    runner,
    '        $cNow = $publicStatus || $publicTelemetry || $publicHome || $r === "logout" ? [] : ctx();',
    '        $cNow = $publicStatus || $publicHome || $r === "logout" ? [] : ctx();',
    'context bypass',
)
runner = replace_once(
    runner,
    '        if ($r !== "logout" && !$publicTelemetry) {\n            enforce_action_integrity($cNow, $r);\n        }',
    '        if ($r !== "logout") {\n            if (!$publicTelemetry) {\n                enforce_action_integrity($cNow, $r);\n            }\n        }',
    'integrity bypass',
)
runner = replace_once(
    runner,
    '            !$publicStatus &&\n            !$publicTelemetry &&\n            !$publicHome &&',
    '            !$publicStatus &&\n            !$publicHome &&',
    'maintenance bypass',
)
runner = replace_once(
    runner,
    '        if ($r !== "logout" && !$publicStatus && !$publicTelemetry) {',
    '        if ($r !== "logout" && !$publicStatus) {',
    'flush bypass',
)
runner_path.write_text(runner)

checker_path = root / 'tools/version-asset-contract-check.php'
checker_path.write_text('''<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$raw = @file_get_contents($root . '/version.json');
$metadata = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($metadata)) {
    throw new RuntimeException('version.json inválido.');
}
$assetRevision = trim((string) ($metadata['asset_version'] ?? ''));
if (!preg_match('/^1\\.\\d{1,2}\\.\\d{1,2}\\.\\d+$/', $assetRevision)) {
    throw new RuntimeException('asset_version inválido.');
}
$app = @file_get_contents($root . '/app/prontoo.php');
if (!is_string($app) ||
    !preg_match('/PRONTOO_ASSET_REV_FALLBACK\\s*=\\s*["\\x27]([^"\\x27]+)["\\x27]/', $app, $match) ||
    trim((string) ($match[1] ?? '')) !== $assetRevision) {
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
$loader = (string) @file_get_contents($root . '/app/Support/ModuleLoader.php');
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
    'mapa executável' => $section($runner, 'function prontoo_route_map(): array', 'function prontoo_public_runtime_routes(): array'),
    'mapa público' => $section($runner, 'function prontoo_public_runtime_routes(): array', 'function prontoo_json_runtime_routes(): array'),
    'mapa JSON' => $section($runner, 'function prontoo_json_runtime_routes(): array', 'function prontoo_route_wants_json(string $route): bool'),
    'boot público leve' => $section($runner, 'function prontoo_route_is_public_light(string $route): bool', 'function prontoo_schema_boot_marker_path(): string'),
] as $label => $source) {
    if (!str_contains($source, '"login_telemetry_wave"')) {
        throw new RuntimeException('Rota das faixas ausente em ' . $label . '.');
    }
}
$run = $section($runner, 'function prontoo_run(bool $installMode = false): void', 'function prontoo_patient_tab_active_rows(');
foreach ([
    '$publicTelemetry = $r === "login_telemetry_wave";',
    '$publicStatus = $r === "status" || $publicTelemetry;',
    'headers_secure($publicStatus);',
    '$cNow = $publicStatus || $publicHome || $r === "logout" ? [] : ctx();',
    'if (!$publicTelemetry) {',
    'if ($r !== "logout") {',
    'if ($r !== "logout" && !$publicStatus) {',
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
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
''')

standalone = root / 'tools/auth-telemetry-route-contract-check.php'
if standalone.exists():
    standalone.unlink()

workflow_path = root / '.github/workflows/architecture.yml'
workflow = workflow_path.read_text()
workflow = workflow.replace('permissions:\n  contents: write\n', 'permissions:\n  contents: read\n', 1)
if '  reconcile-auth-telemetry-route:\n' in workflow:
    start = workflow.index('  reconcile-auth-telemetry-route:\n')
    end = workflow.index('  verify:\n', start)
    workflow = workflow[:start] + workflow[end:]
workflow = workflow.replace("  verify:\n    if: github.event_name != 'pull_request' || github.head_ref != 'agent/fix-auth-telemetry-route-20260804'\n", '  verify:\n', 1)
workflow = workflow.replace('          php tools/auth-telemetry-route-contract-check.php\n', '', 1)
workflow_path.write_text(workflow)

architecture_path = root / 'app/architecture.manifest.json'
architecture = json.loads(architecture_path.read_text())
architecture['transitional_files_max'] = 81
architecture['updated_at'] = now
architecture['notes'] = 'Rota JSON das faixas registrada sem ampliar o baseline arquitetural; contrato integrado ao verificador de assets.'
architecture['login_telemetry_route_contract_check'] = 'tools/version-asset-contract-check.php'
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + '\n')

changelog_path = root / 'CHANGELOG.md'
changelog = changelog_path.read_text()
changelog = changelog.replace(
    '- adiciona contrato regressivo dedicado à rota, ao endpoint, ao JavaScript e ao CSS das faixas;',
    '- integra ao contrato de assets a verificação regressiva da rota, do endpoint, do JavaScript e do CSS das faixas;',
    1,
)
changelog_path.write_text(changelog)

legacy_path = root / 'ChangeLog.txt'
legacy = legacy_path.read_text().replace(
    '- Adiciona contrato regressivo exclusivo para a cadeia das faixas.',
    '- Integra ao contrato de assets a verificação regressiva da cadeia das faixas.',
    1,
)
legacy_path.write_text(legacy)

for temporary in [root / 'tools/reconcile-auth-telemetry-route.py']:
    if temporary.exists():
        temporary.unlink()

update_path = root / 'app/update.manifest.json'
update = json.loads(update_path.read_text())
update['updated_at'] = now
update['notes'] = 'Rota JSON das faixas registrada e contrato regressivo integrado sem novo arquivo PHP transitório.'
files = {}
total = 0
excluded_roots = {'.git', 'git', 'ssd', 'vendor', 'node_modules'}
for path in root.rglob('*'):
    if not path.is_file() or path.is_symlink():
        continue
    rel = path.as_posix().lstrip('./')
    if rel == 'app/update.manifest.json':
        continue
    if rel.split('/', 1)[0] in excluded_roots:
        continue
    data = path.read_bytes()
    files[rel] = hashlib.sha256(data).hexdigest()
    total += len(data)
update['files'] = dict(sorted(files.items()))
update['file_count'] = len(files)
update['total_uncompressed_bytes'] = total
update_path.write_text(json.dumps(update, ensure_ascii=False, indent=4) + '\n')
