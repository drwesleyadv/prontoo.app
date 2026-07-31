from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import time

BASE_COMMIT = "5e52426b251063f1f12a2c0ced70d683ca8a0fa9"
VERSION = "1.7.31.2"
PREVIOUS = "1.7.31.1"
BUILD = "1.7.31.2-clean-install-window-four-hours"
WINDOW_START = 1785500520
WINDOW_END = 1785514920
DEPLOYMENT_SYNC_ID = "github-clean-install-window-four-hours-1-7-31-2"
NOW = datetime.now(timezone.utc).isoformat()
NOW_UNIX = int(time.time())


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"Substituição não determinística em {path}: {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


replace_once(
    "app/Core/Install/InstallAccess.php",
    "    private const PUBLIC_INSTALL_WINDOW_START_UNIX = 1785186607;\n    private const PUBLIC_INSTALL_WINDOW_END_UNIX = 1785190207;",
    f"    private const PUBLIC_INSTALL_WINDOW_START_UNIX = {WINDOW_START};\n    private const PUBLIC_INSTALL_WINDOW_END_UNIX = {WINDOW_END};",
)

replace_once(
    ".htaccess",
    '<Files "install.php">\n    Require all denied\n</Files>',
    '<Files "install.php">\n    Require all granted\n</Files>',
)

replace_once(
    "tools/install-security-check.php",
    '''if (!preg_match('/<Files\\s+"install\\.php">\\s*(?:#[^\\n]*\\s*)*Require\\s+all\\s+denied\\s*<\\/Files>/s', $htaccess) ||
    preg_match('/<Files\\s+"install\\.php">\\s*Require\\s+all\\s+granted\\s*<\\/Files>/s', $htaccess)) {
    $errors[] = 'webserver_install_not_denied';
}
''',
    '''if (!preg_match('/<Files\\s+"install\\.php">\\s*(?:#[^\\n]*\\s*)*Require\\s+all\\s+granted\\s*<\\/Files>/s', $htaccess) ||
    preg_match('/<Files\\s+"install\\.php">\\s*Require\\s+all\\s+denied\\s*<\\/Files>/s', $htaccess)) {
    $errors[] = 'webserver_install_window_entry_not_reachable';
}
if (!preg_match('/PUBLIC_INSTALL_WINDOW_START_UNIX\\s*=\\s*(\\d+)\\s*;/', $installAccessSource, $installWindowStartMatch) ||
    !preg_match('/PUBLIC_INSTALL_WINDOW_END_UNIX\\s*=\\s*(\\d+)\\s*;/', $installAccessSource, $installWindowEndMatch) ||
    (int) ($installWindowEndMatch[1] ?? 0) - (int) ($installWindowStartMatch[1] ?? 0) !== 14400 ||
    !str_contains($installAccessSource, "self::requestHostFrom(\$request) !== 'prontoo.app'") ||
    !str_contains($installAccessSource, "!is_file(\$root . '/app/config.php')") ||
    !str_contains($installAccessSource, "!is_file(\$root . '/ssd/install.lock')")) {
    $errors[] = 'guarded_four_hour_clean_install_window_policy';
}
''',
)

replace_once(
    "app/prontoo.php",
    'const PRONTOO_VERSION_FALLBACK = "1.7.31.1";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.31.2";',
)
replace_once(
    "app/prontoo.php",
    'const PRONTOO_PREVIOUS_VERSION = "1.7.30.22";',
    'const PRONTOO_PREVIOUS_VERSION = "1.7.31.1";',
)
replace_once(
    "br/index.php",
    'const BR_LANDING_VERSION_FALLBACK = "1.7.31.1";',
    'const BR_LANDING_VERSION_FALLBACK = "1.7.31.2";',
)

version_path = Path("version.json")
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "previous_version": PREVIOUS,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "installer_locked": False,
    "installer_access_policy": "temporary_public_https_canonical_host_fresh_state_four_hour_unix_window_then_automatic_404",
    "public_install_window_start_unix": WINDOW_START,
    "public_install_window_end_unix": WINDOW_END,
    "public_install_route": "/install.php",
    "post_window_policy": "automatic_404_without_manual_intervention",
    "clean_install_schema": True,
    "requires_empty_database": True,
    "functional_equivalence_policy": "preserves_application_runtime_and_schema_while_temporarily_exposing_the_guarded_clean_installer_for_exactly_four_hours",
    "notes": "Janela de quatro horas aberta para instalação limpa em banco vazio, exclusivamente por HTTPS no domínio canônico e somente sem configuração ou lock existentes.",
    "deployment_sync_id": DEPLOYMENT_SYNC_ID,
    "deployment_sync_requested_at": NOW,
    "rewrite_scope": "guarded_clean_install_public_window_four_hours",
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

architecture_path = Path("app/architecture.manifest.json")
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "generated_at": NOW,
    "updated_at": NOW,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "installer_access_policy": "temporary_public_https_canonical_host_fresh_state_four_hour_unix_window_then_automatic_404",
    "public_install_window_start_unix": WINDOW_START,
    "public_install_window_end_unix": WINDOW_END,
    "public_install_route": "/install.php",
    "post_window_policy": "automatic_404_without_manual_intervention",
    "clean_install_schema": True,
    "requires_empty_database": True,
})
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

manifest_path = Path("app/update.manifest.json")
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "previous_version": PREVIOUS,
    "deployment_sync_id": DEPLOYMENT_SYNC_ID,
    "generated_at": NOW,
    "updated_at": NOW,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "installer_locked": False,
    "installer_access_policy": "temporary_public_https_canonical_host_fresh_state_four_hour_unix_window_then_automatic_404",
    "public_install_window_start_unix": WINDOW_START,
    "public_install_window_end_unix": WINDOW_END,
    "public_install_route": "/install.php",
    "post_window_policy": "automatic_404_without_manual_intervention",
    "clean_install_schema": True,
    "requires_empty_database": True,
    "notes": "Janela de quatro horas aberta para instalação limpa em banco de dados zerado.",
})

md_entry = (
    "## 1.7.31.2 — Janela de quatro horas para instalação limpa\n\n"
    "- abre o instalador público de 31/07/2026 12:22 UTC até 16:22 UTC;\n"
    "- restringe o acesso a HTTPS no domínio canônico prontoo.app;\n"
    "- permite a execução somente em estado novo, sem app/config.php e sem ssd/install.lock;\n"
    "- mantém a exigência de banco de dados vazio e encerra o acesso automaticamente com resposta 404;\n"
    "- não altera banco de dados nem schema.\n\n"
)
txt_entry = (
    "Prontoo 1.7.31.2 — Janela de quatro horas para instalação limpa\n\n"
    "- Instalador público disponível de 31/07/2026 12:22 UTC até 16:22 UTC.\n"
    "- Acesso somente por HTTPS em prontoo.app e apenas em instalação nova.\n"
    "- Banco vazio continua obrigatório; após a janela, o acesso retorna 404 automaticamente.\n"
    "- Banco de dados e schema permanecem inalterados.\n\n"
)
changelog = Path("CHANGELOG.md")
changelog_text = changelog.read_text(encoding="utf-8")
marker = "# Histórico de versões\n\n"
if changelog_text.count(marker) != 1:
    raise RuntimeError("Cabeçalho canônico do CHANGELOG não encontrado")
changelog.write_text(changelog_text.replace(marker, marker + md_entry, 1), encoding="utf-8")
legacy = Path("ChangeLog.txt")
legacy.write_text(txt_entry + legacy.read_text(encoding="utf-8"), encoding="utf-8")

Path(".github/workflows/architecture.yml").write_bytes(
    subprocess.check_output(["git", "show", BASE_COMMIT + ":.github/workflows/architecture.yml"])
)
Path(".github/clean_install_window_publish.py").unlink(missing_ok=True)

files = {}
total = 0
excluded_roots = (".git/", "ssd/", "vendor/", "node_modules/")
for file in Path(".").rglob("*"):
    if not file.is_file() or file.is_symlink():
        continue
    relative = file.as_posix()
    if relative == "app/update.manifest.json" or relative.startswith(excluded_roots):
        continue
    data = file.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["files"] = dict(sorted(files.items()))
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["updated_at"] = NOW
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
