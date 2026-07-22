#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OLD_VERSION = "1.7.22.1"
NEW_VERSION = "1.7.22.2"
GENERATED_AT = "2026-07-22T12:48:47Z"
GENERATED_AT_UNIX = 1784724527
BUILD = "1.7.22.2-installer-method-hotfix"
PACKAGE_TYPE = "installer_method_hotfix"
NOTES = (
    "Corrige a chamada legada assertLocalEntry no instalador para reutilizar "
    "a guarda canônica assertInstallerEntry já aplicada no ponto de entrada."
)


def read_text(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write_text(path: str, value: str) -> None:
    if not value.endswith("\n"):
        value += "\n"
    (ROOT / path).write_text(value, encoding="utf-8")


def load_json(path: str) -> dict:
    return json.loads(read_text(path))


def write_json(path: str, value: dict, indent: int = 2) -> None:
    write_text(path, json.dumps(value, ensure_ascii=False, indent=indent))


installer_path = "app/Install/Installer.php"
installer = read_text(installer_path)
legacy_count = installer.count("assertLocalEntry")
if legacy_count < 1:
    raise SystemExit("A chamada legada assertLocalEntry não foi encontrada no instalador.")
installer = installer.replace("assertLocalEntry", "assertInstallerEntry")
if "InstallAccess::assertInstallerEntry();" not in installer:
    raise SystemExit("A chamada canônica assertInstallerEntry não foi aplicada.")
if "assertLocalEntry" in installer:
    raise SystemExit("A referência legada permaneceu no instalador.")
write_text(installer_path, installer)

security_path = "tools/install-security-check.php"
security = read_text(security_path)
needle = "$installEntry = (string) file_get_contents($root . '/install.php');"
addition = """$installerSource = (string) file_get_contents($root . '/app/Install/Installer.php');
if (str_contains($installerSource, 'InstallAccess::assertLocalEntry') ||
    !str_contains($installerSource, 'InstallAccess::assertInstallerEntry')) {
    $errors[] = 'installer_legacy_access_method_reference';
}

"""
if addition.strip() not in security:
    if needle not in security:
        raise SystemExit("Ponto de inserção do teste regressivo não encontrado.")
    security = security.replace(needle, addition + needle, 1)
write_text(security_path, security)

changelog_path = "ChangeLog.txt"
changelog = read_text(changelog_path)
entry = """Prontoo 1.7.22.2 — hotfix do método de acesso do instalador

- Corrige a falha fatal `Call to undefined method Prontoo\\Core\\Install\\InstallAccess::assertLocalEntry()` observada após o diagnóstico público alcançar o instalador.
- Atualiza `prontoo_install()` para chamar `InstallAccess::assertInstallerEntry()`, a guarda canônica existente e já utilizada por `install.php`.
- Atualiza o guia de manutenção da função para refletir a dependência real e elimina toda referência executável ao método removido.
- Acrescenta teste regressivo que falha se `app/Install/Installer.php` voltar a referenciar `assertLocalEntry` ou deixar de chamar `assertInstallerEntry`.
- Preserva a janela pública vigente, sua expiração automática, banco, schema, dados, interface e ativos.

"""
if not changelog.startswith(entry):
    changelog = entry + changelog
write_text(changelog_path, changelog)

version_path = "version.json"
version = load_json(version_path)
version["version"] = NEW_VERSION
version["release"] = NEW_VERSION
version["generated_at_unix"] = GENERATED_AT_UNIX
version["generated_at"] = GENERATED_AT
version["updated_at"] = GENERATED_AT
version["build"] = BUILD
version["package_type"] = PACKAGE_TYPE
version["previous_version"] = OLD_VERSION
version["notes"] = NOTES
version["database_changes"] = False
version["schema_changes"] = False
version["logic_changes"] = True
version["visual_changes"] = False
version["documentation_changes"] = True
write_json(version_path, version, 2)

runtime_path = "app/prontoo.php"
runtime = read_text(runtime_path)
runtime, fallback_count = re.subn(
    r'const PRONTOO_VERSION_FALLBACK = "[^"]+";',
    f'const PRONTOO_VERSION_FALLBACK = "{NEW_VERSION}";',
    runtime,
    count=1,
)
runtime, previous_count = re.subn(
    r'const PRONTOO_PREVIOUS_VERSION = "[^"]+";',
    f'const PRONTOO_PREVIOUS_VERSION = "{OLD_VERSION}";',
    runtime,
    count=1,
)
if fallback_count != 1 or previous_count != 1:
    raise SystemExit("Não foi possível sincronizar as constantes de versão do runtime.")
write_text(runtime_path, runtime)

landing_path = "br/index.php"
landing = read_text(landing_path)
landing, landing_count = re.subn(
    r'(BR_LANDING_VERSION_FALLBACK\s*=\s*["\'])[^"\']+(["\'])',
    rf'\g<1>{NEW_VERSION}\2',
    landing,
    count=1,
)
if landing_count != 1:
    raise SystemExit("Não foi possível sincronizar a versão da landing page.")
write_text(landing_path, landing)

schema_contract_path = "app/Database/operational-schema.contract.json"
schema_contract = load_json(schema_contract_path)
schema_contract["version"] = NEW_VERSION
write_json(schema_contract_path, schema_contract, 2)

architecture_path = "app/architecture.manifest.json"
architecture = load_json(architecture_path)
architecture["version"] = NEW_VERSION
architecture["logic_changes"] = True
write_json(architecture_path, architecture, 2)

manifest_path = "app/update.manifest.json"
manifest = load_json(manifest_path)
manifest["version"] = NEW_VERSION
manifest["release"] = NEW_VERSION
manifest["build"] = BUILD
manifest["package_type"] = PACKAGE_TYPE
manifest["generated_at"] = GENERATED_AT
manifest["database_changes"] = False
manifest["schema_changes"] = False
manifest["logic_changes"] = True
manifest["visual_changes"] = False
manifest["documentation_changes"] = True
files = manifest.get("files")
if not isinstance(files, dict) or not files:
    raise SystemExit("Lista selada de arquivos ausente no manifesto.")
if manifest_path in files:
    raise SystemExit("O manifesto não pode incluir o próprio hash.")
sealed: dict[str, str] = {}
total = 0
for relative in files:
    path = ROOT / relative
    if not path.is_file():
        raise SystemExit(f"Arquivo selado ausente: {relative}")
    payload = path.read_bytes()
    sealed[relative] = hashlib.sha256(payload).hexdigest()
    total += len(payload)
manifest["files"] = sealed
manifest["file_count"] = len(sealed)
manifest["total_uncompressed_bytes"] = total
write_json(manifest_path, manifest, 4)

if read_text(installer_path).count("assertLocalEntry") != 0:
    raise SystemExit("A referência legada reapareceu após a selagem.")
print(json.dumps({
    "ok": True,
    "version": NEW_VERSION,
    "legacy_references_replaced": legacy_count,
    "window_end_utc": version.get("temporary_public_installer", {}).get("end_utc"),
    "sealed_files": len(sealed),
    "sealed_bytes": total,
}, ensure_ascii=False))
