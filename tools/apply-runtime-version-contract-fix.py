from pathlib import Path
import datetime
import hashlib
import json
import re
import subprocess
import time

root = Path('.')
version = '1.8.4.7'
previous = '1.8.4.6'
asset = '1.7.15.10'
build = '1.8.4.7-runtime-version-contract-assets'
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


replace_const('app/prontoo.php', 'PRONTOO_VERSION_FALLBACK', version)
replace_const('app/prontoo.php', 'PRONTOO_ASSET_REV_FALLBACK', asset)
replace_const('app/prontoo.php', 'PRONTOO_PREVIOUS_VERSION', previous)
replace_const('app/prontoo.php', 'PRONTOO_PREVIOUS_ASSET_REV', asset)
replace_const('br/index.php', 'BR_LANDING_VERSION_FALLBACK', version)

checker = root / 'tools/version-asset-contract-check.php'
checker.write_text('''<?php
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
echo json_encode([
    'ok' => true,
    'asset_version' => $assetRevision,
    'assets_verified' => count($assets),
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
    'visual_changes': False,
    'documentation_changes': True,
    'previous_version': previous,
    'notes': 'Restaura o asset_version canônico 1.7.15.10 e valida a existência dos assets públicos antes da publicação.',
    'deployment_sync_id': 'github-runtime-version-contract-assets-1-8-4-7',
    'deployment_sync_requested_at': now,
    'rewrite_scope': 'runtime_version_and_public_asset_contract',
})
version_path.write_text(json.dumps(version_data, ensure_ascii=False, indent=4) + '\n')

architecture_path = root / 'app/architecture.manifest.json'
architecture = json.loads(architecture_path.read_text())
architecture['version'] = version
architecture['logic_changes'] = True
architecture['visual_changes'] = False
architecture['notes'] = 'Contrato de publicação valida o asset_version e os cinco assets públicos canônicos.'
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + '\n')

changelog = root / 'CHANGELOG.md'
text = changelog.read_text()
heading = '# Histórico de versões\n'
entry = '''
## 1.8.4.7 — Correção do contrato de assets públicos

- corrige o bloqueio de runtime causado por `asset_version` sem arquivos públicos correspondentes;
- restaura o conjunto canônico de assets para `1.7.15.10`;
- mantém a versão funcional da aplicação independente da revisão de assets;
- adiciona validação automática da existência dos cinco arquivos públicos versionados;
- valida também a referência versionada do PIX no CSS;
- preserva integralmente as ondas de telemetria publicadas na versão anterior;
- não altera banco de dados nem schema.
'''
if not text.startswith(heading):
    raise SystemExit('CHANGELOG heading not found')
changelog.write_text(heading + entry + text[len(heading):])

legacy = root / 'ChangeLog.txt'
legacy_text = legacy.read_text()
legacy.write_text('''1.8.4.7 - Correção do contrato de assets públicos
- Restaura o asset_version canônico 1.7.15.10.
- Corrige o bloqueio de runtime provocado por assets públicos inexistentes.
- Adiciona verificação automática dos cinco arquivos versionados e da referência CSS.
- Preserva as ondas de telemetria da versão anterior.
- Sem alteração de banco de dados ou schema.

''' + legacy_text)

base_architecture = subprocess.check_output(
    ['git', 'show', 'origin/prontoo:.github/workflows/architecture.yml'],
    text=True,
)
needle = '''          PHP
      - name: Documentation and comment contracts'''
replacement = '''          PHP
          php tools/version-asset-contract-check.php
      - name: Documentation and comment contracts'''
if needle not in base_architecture:
    raise SystemExit('architecture workflow insertion point not found')
(root / '.github/workflows/architecture.yml').write_text(
    base_architecture.replace(needle, replacement, 1)
)

for temp in [root / 'tools/apply-runtime-version-contract-fix.py']:
    if temp.exists():
        temp.unlink()

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
    'visual_changes': False,
    'documentation_changes': True,
    'notes': 'Contrato de publicação corrigido para o conjunto real de assets públicos 1.7.15.10.',
    'deployment_sync_id': 'github-runtime-version-contract-assets-1-8-4-7',
    'deployment_sync_requested_at': now,
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
