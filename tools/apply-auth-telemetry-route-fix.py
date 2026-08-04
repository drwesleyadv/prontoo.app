from pathlib import Path
import datetime
import hashlib
import json
import re
import time

root = Path('.')
version = '1.8.4.9'
previous = '1.8.4.8'
asset = '1.7.15.11'
build = '1.8.4.9-auth-telemetry-route-contract'
now = datetime.datetime.now(datetime.timezone.utc).isoformat()
unix = int(time.time())


def replace_once(source: str, old: str, new: str, label: str) -> str:
    if old not in source:
        raise SystemExit(f'insertion point not found: {label}')
    return source.replace(old, new, 1)


def replace_const(path: str, constant: str, value: str) -> None:
    file = root / path
    source = file.read_text()
    source, count = re.subn(
        rf'(const\s+{re.escape(constant)}\s*=\s*["\x27])[^"\x27]+(["\x27]\s*;)',
        rf'\g<1>{value}\g<2>',
        source,
        count=1,
    )
    if count != 1:
        raise SystemExit(f'constant not found: {path}:{constant}')
    file.write_text(source)


runner_path = root / 'app/Runtime/Runner.php'
runner = runner_path.read_text()
runner = replace_once(
    runner,
    '        "login",\n        "login_autotest",',
    '        "login",\n        "login_telemetry_wave",\n        "login_autotest",',
    'route map',
)
runner = replace_once(
    runner,
    '        "login",\n        "login_autotest",',
    '        "login",\n        "login_telemetry_wave",\n        "login_autotest",',
    'public route map',
)
runner = replace_once(
    runner,
    '    return [\n        "patient_lookup",',
    '    return [\n        "login_telemetry_wave",\n        "patient_lookup",',
    'json route map',
)
runner = replace_once(
    runner,
    '    if (in_array($route, ["login", "login_autotest", "mfa", "logout"], true)) {',
    '    if (in_array($route, ["login", "login_telemetry_wave", "login_autotest", "mfa", "logout"], true)) {',
    'public light route',
)
runner = replace_once(
    runner,
    '        $publicStatus = $r === "status";\n        $publicHome = false;\n        headers_secure($publicStatus);',
    '        $publicStatus = $r === "status";\n        $publicTelemetry = $r === "login_telemetry_wave";\n        $publicHome = false;\n        headers_secure($publicStatus || $publicTelemetry);',
    'public telemetry flag',
)
runner = replace_once(
    runner,
    '        $cNow = $publicStatus || $publicHome || $r === "logout" ? [] : ctx();',
    '        $cNow = $publicStatus || $publicTelemetry || $publicHome || $r === "logout" ? [] : ctx();',
    'telemetry context bypass',
)
runner = replace_once(
    runner,
    '        enforce_read_only($cNow, $r);\n        if ($r !== "logout") {\n            enforce_action_integrity($cNow, $r);\n        }',
    '        if (!$publicTelemetry) {\n            enforce_read_only($cNow, $r);\n        }\n        if ($r !== "logout" && !$publicTelemetry) {\n            enforce_action_integrity($cNow, $r);\n        }',
    'telemetry policy bypass',
)
runner = replace_once(
    runner,
    '            !$publicStatus &&\n            !$publicHome &&',
    '            !$publicStatus &&\n            !$publicTelemetry &&\n            !$publicHome &&',
    'maintenance telemetry bypass',
)
runner = replace_once(
    runner,
    '        if ($r !== "logout" && !$publicStatus) {',
    '        if ($r !== "logout" && !$publicStatus && !$publicTelemetry) {',
    'render flush telemetry bypass',
)
runner_path.write_text(runner)

checker_path = root / 'tools/auth-telemetry-route-contract-check.php'
checker_path.write_text('''<?php
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
''')

workflow_path = root / '.github/workflows/architecture.yml'
workflow = workflow_path.read_text()
if 'php tools/auth-telemetry-route-contract-check.php' not in workflow:
    workflow = replace_once(
        workflow,
        '          php tools/version-asset-contract-check.php\n',
        '          php tools/version-asset-contract-check.php\n          php tools/auth-telemetry-route-contract-check.php\n',
        'architecture route contract',
    )
workflow_path.write_text(workflow)

replace_const('app/prontoo.php', 'PRONTOO_VERSION_FALLBACK', version)
replace_const('app/prontoo.php', 'PRONTOO_ASSET_REV_FALLBACK', asset)
replace_const('app/prontoo.php', 'PRONTOO_PREVIOUS_VERSION', previous)
replace_const('app/prontoo.php', 'PRONTOO_PREVIOUS_ASSET_REV', asset)
replace_const('br/index.php', 'BR_LANDING_VERSION_FALLBACK', version)

version_path = root / 'version.json'
version_data = json.loads(version_path.read_text())
version_data.update({
    'version': version,
    'release': version,
    'generated_at_unix': unix,
    'generated_at': now,
    'updated_at': now,
    'build': build,
    'asset_version': asset,
    'database_changes': False,
    'schema_changes': False,
    'logic_changes': True,
    'visual_changes': True,
    'documentation_changes': True,
    'previous_version': previous,
    'notes': 'Registra o endpoint JSON das faixas no Runner e impede que a resposta autenticada caia em page_home.',
    'deployment_sync_id': 'github-auth-telemetry-route-contract-1-8-4-9',
    'deployment_sync_requested_at': now,
    'rewrite_scope': 'authenticated_telemetry_route_registration_and_json_execution',
    'functional_equivalence_policy': 'preserves_telemetry_series_and_visual_style_while_routing_authenticated_footer_requests_to_the_canonical_json_endpoint',
})
version_path.write_text(json.dumps(version_data, ensure_ascii=False, indent=4) + '\n')

architecture_path = root / 'app/architecture.manifest.json'
architecture = json.loads(architecture_path.read_text())
architecture.update({
    'version': version,
    'release': version,
    'build': build,
    'logic_changes': True,
    'visual_changes': True,
    'previous_version': previous,
    'deployment_sync_id': 'github-auth-telemetry-route-contract-1-8-4-9',
    'notes': 'Endpoint das faixas registrado como rota executável, pública, JSON e de boot leve; resposta não passa por page_home.',
    'documentation_changes': True,
    'login_telemetry_wave_policy': 'public_and_authenticated_bottom_15dvh_route_registered_json_light_boot_immediate_authenticated_fetch_refresh_15m',
    'login_telemetry_route_contract_policy': 'runner_map_public_json_light_boot_context_integrity_maintenance_and_flush_bypass_verified_by_dedicated_contract',
    'login_telemetry_route_contract_check': 'tools/auth-telemetry-route-contract-check.php',
})
architecture['generated_at'] = now
architecture['updated_at'] = now
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + '\n')

changelog_path = root / 'CHANGELOG.md'
changelog = changelog_path.read_text()
heading = '# Histórico de versões\n'
entry = '''
## 1.8.4.9 — Rota efetiva das faixas autenticadas

- corrige exclusivamente a cadeia de exibição das faixas na área autenticada;
- registra `login_telemetry_wave` no mapa executável do `Runner`, evitando o fallback silencioso para `page_home`;
- registra o endpoint como rota pública, JSON e de boot leve;
- impede que a consulta agregada passe por contexto autenticado, integridade de ação, manutenção de tela ou flush de renderização;
- preserva a montagem imediata, as séries, as cores, a opacidade, a altura e o intervalo de atualização;
- adiciona contrato regressivo dedicado à rota, ao endpoint, ao JavaScript e ao CSS das faixas;
- mantém a revisão de assets `1.7.15.11` porque JavaScript e CSS não foram alterados;
- não altera banco de dados nem schema.
'''
if not changelog.startswith(heading):
    raise SystemExit('CHANGELOG heading not found')
changelog_path.write_text(heading + entry + changelog[len(heading):])

legacy_path = root / 'ChangeLog.txt'
legacy = legacy_path.read_text()
legacy_path.write_text('''1.8.4.9 - Rota efetiva das faixas autenticadas
- Registra login_telemetry_wave no mapa executável do Runner.
- Corrige a resposta HTML de page_home que impedia o preenchimento dos paths SVG.
- Trata o endpoint como público, JSON e de boot leve.
- Adiciona contrato regressivo exclusivo para a cadeia das faixas.
- Mantém os assets 1.7.15.11 e não altera banco ou schema.

''' + legacy)

for temporary in [
    root / 'tools/apply-auth-telemetry-route-fix.py',
    root / '.github/workflows/fix-auth-telemetry-route.yml',
]:
    if temporary.exists():
        temporary.unlink()

update_path = root / 'app/update.manifest.json'
update = json.loads(update_path.read_text())
update.update({
    'version': version,
    'release': version,
    'build': build,
    'asset_version': asset,
    'previous_version': previous,
    'generated_at': now,
    'updated_at': now,
    'logic_changes': True,
    'visual_changes': True,
    'documentation_changes': True,
    'notes': 'Rota JSON das faixas registrada no Runner e protegida por contrato regressivo dedicado.',
    'deployment_sync_id': 'github-auth-telemetry-route-contract-1-8-4-9',
    'deployment_sync_requested_at': now,
    'rewrite_scope': 'authenticated_telemetry_route_registration_and_json_execution',
})
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
