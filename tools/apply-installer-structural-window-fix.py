from __future__ import annotations

from datetime import datetime, timezone
from pathlib import Path
import hashlib
import json
import re
import subprocess

root = Path.cwd()
window_start = 1785770400
window_end = 1785777600
if window_end - window_start != 7200:
    raise SystemExit('A janela precisa ter exatamente 7200 segundos.')

version_path = root / 'version.json'
version_data = json.loads(version_path.read_text(encoding='utf-8'))
previous_version = str(version_data['version'])
prefix = '1.8.3.'
sequence = int(previous_version.removeprefix(prefix)) + 1 if previous_version.startswith(prefix) else 1
version = f'{prefix}{sequence}'
build = f'{version}-installer-structural-window-fix'
generated = datetime.now(timezone.utc).isoformat(timespec='microseconds')
generated_unix = int(datetime.now(timezone.utc).timestamp())
policy = 'temporary_public_https_canonical_host_fresh_state_two_hour_unix_window_then_automatic_404'
deployment_sync_id = f'github-installer-structural-window-fix-{version.replace(".", "-")}'
window_utc = '03/08/2026 15:20 UTC até 17:20 UTC'
window_local = '03/08/2026 11:20 até 13:20 (America/Cuiaba)'


def replace_once(path: Path, pattern: str, replacement: str) -> None:
    text = path.read_text(encoding='utf-8')
    updated, count = re.subn(pattern, replacement, text, count=1)
    if count != 1:
        raise SystemExit(f'Padrão não localizado em {path}: {pattern}')
    path.write_text(updated, encoding='utf-8')


for relative in [
    'app/Core/Install/InstallAccess.php',
    'app/Core/Database/SchemaMutationLock.php',
]:
    path = root / relative
    replace_once(
        path,
        r'private const PUBLIC_INSTALL_WINDOW_START_UNIX = \d+;',
        f'private const PUBLIC_INSTALL_WINDOW_START_UNIX = {window_start};',
    )
    replace_once(
        path,
        r'private const PUBLIC_INSTALL_WINDOW_END_UNIX = \d+;',
        f'private const PUBLIC_INSTALL_WINDOW_END_UNIX = {window_end};',
    )

schema_lock = root / 'app/Core/Database/SchemaMutationLock.php'
schema_text = schema_lock.read_text(encoding='utf-8')
old_route = """        if ((string) ($_GET['r'] ?? '') !== 'install') {
            return false;
        }
"""
new_route = """        $route = trim((string) ($_GET['r'] ?? ''));
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        if ($route !== 'install' && $script !== 'install.php' && $requestPath !== '/install.php') {
            return false;
        }
"""
if old_route not in schema_text:
    raise SystemExit('Validação antiga da rota estrutural não localizada.')
schema_lock.write_text(schema_text.replace(old_route, new_route, 1), encoding='utf-8')

contract = root / 'tools/install-window-contract-check'
contract_text = contract.read_text(encoding='utf-8')
contract_text = contract_text.replace('!== 14400', '!== 7200', 1)
contract_text = contract_text.replace('exatamente quatro horas', 'exatamente duas horas', 1)
contract_text = contract_text.replace("'duration_seconds' => 14400", "'duration_seconds' => 7200", 1)
if '14400' in contract_text:
    raise SystemExit('Contrato antigo de quatro horas ainda presente.')
contract.write_text(contract_text, encoding='utf-8')

security_check = root / 'tools/install-security-check.php'
security_text = security_check.read_text(encoding='utf-8')
anchor = """    !str_contains($installAccessSource, \"!is_file(\\$root . '/ssd/install.lock')\")) {
    $errors[] = 'guarded_two_hour_clean_install_window_policy';
}
"""
replacement = """    !str_contains($installAccessSource, \"!is_file(\\$root . '/ssd/install.lock')\")) {
    $errors[] = 'guarded_two_hour_clean_install_window_policy';
}
if (!str_contains($schemaLockSource, \"$route !== 'install'\") ||
    !str_contains($schemaLockSource, \"$script !== 'install.php'\") ||
    !str_contains($schemaLockSource, \"$requestPath !== '/install.php'\")) {
    $errors[] = 'schema_window_install_route_contract';
}
"""
if anchor not in security_text:
    raise SystemExit('Âncora do verificador de segurança não localizada.')
security_check.write_text(security_text.replace(anchor, replacement, 1), encoding='utf-8')

prontoo_php = root / 'app/prontoo.php'
replace_once(prontoo_php, r'const PRONTOO_VERSION_FALLBACK = "[^"]+";', f'const PRONTOO_VERSION_FALLBACK = "{version}";')
replace_once(prontoo_php, r'const PRONTOO_PREVIOUS_VERSION = "[^"]+";', f'const PRONTOO_PREVIOUS_VERSION = "{previous_version}";')
replace_once(root / 'br/index.php', r'const BR_LANDING_VERSION_FALLBACK = "[^"]+";', f'const BR_LANDING_VERSION_FALLBACK = "{version}";')

changelog = root / 'CHANGELOG.md'
changelog_text = changelog.read_text(encoding='utf-8')
marker = '# Histórico de versões\n\n'
entry = (
    f'## {version} — Correção da janela estrutural do instalador\n\n'
    f'- reabre uma janela integral em `{window_local}` (`{window_utc}`);\n'
    '- sincroniza os timestamps de `InstallAccess` e `SchemaMutationLock`;\n'
    '- reconhece tanto `/install.php` quanto `/?r=install` como entradas autorizadas;\n'
    '- corrige o contrato automatizado para duração exata de 7.200 segundos;\n'
    '- mantém HTTPS, host canônico, banco vazio e fechamento automático por 404;\n'
    '- não altera banco de dados nem schema.\n\n'
)
if not changelog_text.startswith(marker):
    raise SystemExit('Cabeçalho do CHANGELOG.md não localizado.')
changelog.write_text(marker + entry + changelog_text[len(marker):], encoding='utf-8')

legacy = root / 'ChangeLog.txt'
legacy_text = legacy.read_text(encoding='utf-8')
legacy_entry = (
    f'Prontoo {version} — Correção da janela estrutural do instalador\n\n'
    f'- Janela integral disponível em {window_local} ({window_utc}).\n'
    '- InstallAccess e SchemaMutationLock passam a usar os mesmos timestamps.\n'
    '- /install.php e /?r=install são reconhecidos como entradas autorizadas.\n'
    '- O acesso volta a responder 404 automaticamente após o término.\n'
    '- Banco de dados e schema permanecem inalterados.\n\n'
)
legacy.write_text(legacy_entry + legacy_text, encoding='utf-8')

version_data.update({
    'version': version,
    'release': version,
    'generated_at_unix': generated_unix,
    'generated_at': generated,
    'updated_at': generated,
    'build': build,
    'database_changes': False,
    'schema_changes': False,
    'logic_changes': True,
    'visual_changes': False,
    'functional_equivalence_policy': 'preserves_application_runtime_and_schema_while_synchronizing_the_public_installer_and_structural_mutation_windows',
    'notes': f'Corrige a divergência da janela estrutural e reabre o instalador de {window_local}.',
    'previous_version': previous_version,
    'documentation_changes': True,
    'installer_locked': False,
    'installer_access_policy': policy,
    'public_install_window_start_unix': window_start,
    'public_install_window_end_unix': window_end,
    'public_install_route': '/install.php',
    'post_window_policy': 'automatic_404_without_manual_intervention',
    'deployment_sync_id': deployment_sync_id,
    'deployment_sync_requested_at': generated,
    'rewrite_scope': 'installer_structural_window_contract_alignment',
})
version_path.write_text(json.dumps(version_data, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')

architecture_path = root / 'app/architecture.manifest.json'
architecture = json.loads(architecture_path.read_text(encoding='utf-8'))
architecture.update({
    'version': version,
    'release': version,
    'build': build,
    'database_changes': False,
    'schema_changes': False,
    'visual_changes': False,
    'generated_at': generated,
    'updated_at': generated,
    'installer_locked': False,
    'installer_access_policy': policy,
    'public_install_window_start_unix': window_start,
    'public_install_window_end_unix': window_end,
    'public_install_route': '/install.php',
    'post_window_policy': 'automatic_404_without_manual_intervention',
    'clean_install_schema': True,
    'requires_empty_database': True,
    'previous_version': previous_version,
    'deployment_sync_id': deployment_sync_id,
    'notes': 'Corrige a divergência entre a janela pública e a janela estrutural do instalador.',
})
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')

update_manifest_path = root / 'app/update.manifest.json'
update_manifest = json.loads(update_manifest_path.read_text(encoding='utf-8'))
update_manifest.update({
    'version': version,
    'release': version,
    'build': build,
    'generated_at': generated,
    'updated_at': generated,
    'database_changes': False,
    'schema_changes': False,
    'logic_changes': True,
    'visual_changes': False,
    'clean_install_schema': True,
    'requires_empty_database': True,
    'installer_policy': policy,
    'installer_access_policy': policy,
    'installer_locked': False,
    'public_install_window_start_unix': window_start,
    'public_install_window_end_unix': window_end,
    'public_install_route': '/install.php',
    'post_window_policy': 'automatic_404_without_manual_intervention',
    'previous_version': previous_version,
    'deployment_sync_id': deployment_sync_id,
    'deployment_sync_requested_at': generated,
    'rewrite_scope': 'installer_structural_window_contract_alignment',
    'functional_equivalence_policy': 'preserves_application_runtime_and_schema_while_synchronizing_the_public_installer_and_structural_mutation_windows',
    'notes': 'Corrige a janela estrutural do instalador e mantém fechamento automático por 404.',
})

base_workflow = subprocess.check_output([
    'git',
    'show',
    'origin/prontoo:.github/workflows/architecture.yml',
])
(root / '.github/workflows/architecture.yml').write_bytes(base_workflow)
for auxiliary in [
    root / '.github/workflows/fix-installer-structural-window.yml',
    root / 'tools/apply-installer-structural-window-fix.py',
]:
    if auxiliary.exists():
        auxiliary.unlink()

files: dict[str, str] = {}
total = 0
excluded_roots = {'.git', 'ssd', 'vendor', 'node_modules'}
for path in root.rglob('*'):
    if not path.is_file() or path.is_symlink():
        continue
    relative = path.relative_to(root).as_posix()
    if relative == 'app/update.manifest.json':
        continue
    if relative.split('/', 1)[0] in excluded_roots:
        continue
    payload = path.read_bytes()
    files[relative] = hashlib.sha256(payload).hexdigest()
    total += len(payload)
update_manifest['files'] = dict(sorted(files.items()))
update_manifest['file_count'] = len(files)
update_manifest['total_uncompressed_bytes'] = total
update_manifest_path.write_text(json.dumps(update_manifest, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')
