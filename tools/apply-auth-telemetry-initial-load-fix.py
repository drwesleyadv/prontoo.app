from pathlib import Path
import datetime
import hashlib
import json
import re
import shutil
import time

root = Path('.')
version = '1.8.4.8'
previous = '1.8.4.7'
asset = '1.7.15.11'
previous_asset = '1.7.15.10'
build = '1.8.4.8-auth-telemetry-initial-fetch'
now = datetime.datetime.now(datetime.timezone.utc).isoformat()
unix = int(time.time())


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


app_js_path = root / 'public/assets/app.js'
app_js = app_js_path.read_text()
app_js, count = app_js.replace(
    '  function initLoginTelemetryWave(root = d) {\n    let wrap = $("[data-login-telemetry-wave]", root);',
    '  function initLoginTelemetryWave(root = d) {\n    let wrap = $("[data-login-telemetry-wave]", root);\n    let needsInitialRefresh = false;',
    1,
), 1
if 'let needsInitialRefresh = false;' not in app_js:
    raise SystemExit('telemetry initializer insertion failed')
create_needle = '      d.body.appendChild(wrap);\n    }\n    if (!wrap || wrap.dataset.loginTelemetryWaveReady) return;'
create_replacement = '      d.body.appendChild(wrap);\n      needsInitialRefresh = true;\n    }\n    if (!wrap || wrap.dataset.loginTelemetryWaveReady) return;'
if create_needle not in app_js:
    raise SystemExit('dynamic telemetry wrapper insertion point not found')
app_js = app_js.replace(create_needle, create_replacement, 1)
refresh_needle = '''    d.addEventListener(
      "visibilitychange",
      () => {
        if (!d.visibilityState || d.visibilityState === "visible")
refresh();
      },
      { passive: true },
    );
  }
  function initAdminGlobalChartsRefresh(root = d) {'''
refresh_replacement = '''    d.addEventListener(
      "visibilitychange",
      () => {
        if (!d.visibilityState || d.visibilityState === "visible")
refresh();
      },
      { passive: true },
    );
    if (needsInitialRefresh) refresh(true);
  }
  function initAdminGlobalChartsRefresh(root = d) {'''
if refresh_needle not in app_js:
    raise SystemExit('initial telemetry refresh insertion point not found')
app_js_path.write_text(app_js.replace(refresh_needle, refresh_replacement, 1))

for filename in [
    'app-icon-1.7.15.10.png',
    'prontoo-mark-1.7.15.10.png',
    'favicon-1.7.15.10.png',
    'favicon-1.7.15.10.ico',
    'pix-1.7.15.10.svg',
]:
    source = root / 'public/assets' / filename
    target = root / 'public/assets' / filename.replace(previous_asset, asset)
    if not source.is_file():
        raise SystemExit(f'canonical source asset missing: {filename}')
    shutil.copyfile(source, target)

css_path = root / 'public/assets/design-system.css'
css = css_path.read_text()
old_pix = f'pix-{previous_asset}.svg'
new_pix = f'pix-{asset}.svg'
if old_pix not in css:
    raise SystemExit('versioned PIX reference not found')
css_path.write_text(css.replace(old_pix, new_pix))

replace_const('app/prontoo.php', 'PRONTOO_VERSION_FALLBACK', version)
replace_const('app/prontoo.php', 'PRONTOO_ASSET_REV_FALLBACK', asset)
replace_const('app/prontoo.php', 'PRONTOO_PREVIOUS_VERSION', previous)
replace_const('app/prontoo.php', 'PRONTOO_PREVIOUS_ASSET_REV', previous_asset)
replace_const('br/index.php', 'BR_LANDING_VERSION_FALLBACK', version)

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
$css = @file_get_contents($root . '/public/assets/design-system.css');
if (!is_string($css) || !str_contains($css, 'pix-' . $assetRevision . '.svg')) {
    throw new RuntimeException('CSS diverge do asset_version.');
}
$javascript = @file_get_contents($root . '/public/assets/app.js');
if (!is_string($javascript) ||
    !str_contains($javascript, 'let needsInitialRefresh = false;') ||
    !str_contains($javascript, 'needsInitialRefresh = true;') ||
    !str_contains($javascript, 'if (needsInitialRefresh) refresh(true);')) {
    throw new RuntimeException('Carga inicial das faixas autenticadas ausente.');
}
echo json_encode([
    'ok' => true,
    'asset_version' => $assetRevision,
    'assets_verified' => count($assets),
    'authenticated_initial_refresh' => true,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
''')

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
    'notes': 'A área autenticada consulta imediatamente as séries das faixas e publica assets em uma nova revisão física para invalidar o cache anterior.',
    'deployment_sync_id': 'github-auth-telemetry-initial-fetch-1-8-4-8',
    'deployment_sync_requested_at': now,
    'rewrite_scope': 'authenticated_telemetry_initial_fetch_and_asset_cache_bust',
    'functional_equivalence_policy': 'preserves_authentication_and_telemetry_values_while_fetching_authenticated_footer_series_immediately_after_dynamic_mount',
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
    'deployment_sync_id': 'github-auth-telemetry-initial-fetch-1-8-4-8',
    'notes': 'Faixas autenticadas executam carga inicial imediata; revisão física de assets 1.7.15.11 evita cache obsoleto.',
    'documentation_changes': True,
    'login_telemetry_wave_policy': 'public_server_rendered_and_authenticated_dynamic_bottom_15dvh_full_width_requests_and_records_authenticated_initial_fetch_immediate_refresh_15m',
})
architecture['generated_at'] = now
architecture['updated_at'] = now
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + '\n')

changelog_path = root / 'CHANGELOG.md'
changelog = changelog_path.read_text()
heading = '# Histórico de versões\n'
entry = '''
## 1.8.4.8 — Carga inicial das faixas autenticadas

- corrige a área autenticada, que criava o SVG com caminhos vazios e aguardava até 15 minutos para consultar a telemetria;
- executa a primeira consulta imediatamente após a montagem dinâmica das faixas;
- mantém o login com os caminhos inicialmente renderizados pelo PHP, sem requisição inicial duplicada;
- publica a revisão física de assets `1.7.15.11` para invalidar JavaScript e CSS armazenados em cache;
- inclui os cinco arquivos públicos canônicos correspondentes à nova revisão;
- preserva cores, opacidade, dimensões, dados e intervalo de atualização de 15 minutos;
- não altera banco de dados nem schema.
'''
if not changelog.startswith(heading):
    raise SystemExit('CHANGELOG heading not found')
changelog_path.write_text(heading + entry + changelog[len(heading):])

legacy_path = root / 'ChangeLog.txt'
legacy = legacy_path.read_text()
legacy_path.write_text('''1.8.4.8 - Carga inicial das faixas autenticadas
- Executa a consulta de telemetria imediatamente após criar as faixas na área logada.
- Evita caminhos SVG vazios até a atualização periódica de 15 minutos.
- Mantém o login sem requisição inicial duplicada.
- Publica assets físicos na revisão 1.7.15.11 para invalidar cache antigo.
- Sem alteração de banco de dados ou schema.

''' + legacy)

script_path = root / 'tools/apply-auth-telemetry-initial-load-fix.py'
if script_path.exists():
    script_path.unlink()

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
    'notes': 'Faixas autenticadas recebem carga inicial imediata e assets revisionados para eliminar cache obsoleto.',
    'deployment_sync_id': 'github-auth-telemetry-initial-fetch-1-8-4-8',
    'deployment_sync_requested_at': now,
    'rewrite_scope': 'authenticated_telemetry_initial_fetch_and_asset_cache_bust',
})
files = {}
total = 0
excluded_roots = {'.git', 'ssd', 'vendor', 'node_modules'}
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
