#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import re
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OLD_VERSION = "1.7.21.8"
NEW_VERSION = "1.7.22.1"
GENERATED_AT = "2026-07-22T12:34:55Z"
BUILD = "1.7.22.1-public-installer-config-guard-hotfix"
PACKAGE_TYPE = "public_installer_config_guard_hotfix"
NOTES = (
    "Corrige a proteção residual do runtime sem configuração para reutilizar a decisão "
    "canônica e autoexpirável do instalador público."
)


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1 trecho, encontrado {count}")
    return content.replace(old, new, 1)


def replace_regex(content: str, pattern: str, replacement: str, label: str) -> str:
    updated, count = re.subn(pattern, lambda _: replacement, content, count=1, flags=re.S)
    if count != 1:
        raise RuntimeError(f"{label}: padrão não encontrado uma única vez")
    return updated


# 1. Corrige a segunda guarda do runtime. Durante a janela vigente, a ausência
# de config.php deve redirecionar ao instalador público, usando exatamente a
# mesma decisão que protege install.php e a janela estrutural.
runner = read("app/Runtime/Runner.php")
runner = replace_once(
    runner,
    ".Core.Install.InstallAccess::isLocalHttpRequest`, `headers_sent`",
    ".Core.Install.InstallAccess::isInstallerExecutionAllowed`, `headers_sent`",
    "guia de dependência do runner",
)
runner = replace_once(
    runner,
    "            if (\\Prontoo\\Core\\Install\\InstallAccess::isLocalHttpRequest()) {\n",
    "            if (\\Prontoo\\Core\\Install\\InstallAccess::isInstallerExecutionAllowed()) {\n",
    "guarda de configuração ausente",
)
write("app/Runtime/Runner.php", runner)

# 2. Mantém os comentários de dependência coerentes com o chamador real.
access = read("app/Core/Install/InstallAccess.php")
access = replace_once(
    access,
    "         * Chamadores detectados: `prontoo_run`.\n         * Dependências chamadas: `self::isLocalServer`.\n",
    "         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.\n         * Dependências chamadas: `self::isLocalServer`.\n",
    "guia isLocalHttpRequest",
)
access = replace_once(
    access,
    "         * Chamadores detectados: `Core.Database.SchemaMutationLock::mayOpenInstallerWindow`, `Core.Install.InstallAccess::assertInstallerEntry`.\n",
    "         * Chamadores detectados: `Core.Database.SchemaMutationLock::mayOpenInstallerWindow`, `Core.Install.InstallAccess::assertInstallerEntry`, `prontoo_run`.\n",
    "guia isInstallerExecutionAllowed",
)
write("app/Core/Install/InstallAccess.php", access)

# 3. Torna permanente a regressão: a ramificação sem config.php não pode voltar
# a usar a checagem exclusiva de localhost.
security = read("tools/install-security-check.php")
anchor = """if ($guardPos === false || $bootstrapPos === false || $guardPos > $bootstrapPos) {
    $errors[] = 'install_entry_guard_order';
}
"""
addition = anchor + """
$runner = (string) file_get_contents($root . '/app/Runtime/Runner.php');
$configGuardStart = strpos($runner, 'if (!has_cfg() && !$installMode && !$publicHome)');
$configGuardEnd = $configGuardStart === false
    ? false
    : strpos($runner, 'prontoo_boot_database_for_route($r);', $configGuardStart);
if ($configGuardStart === false || $configGuardEnd === false) {
    $errors[] = 'runtime_missing_config_guard_not_found';
} else {
    $configGuard = substr($runner, $configGuardStart, $configGuardEnd - $configGuardStart);
    if (!str_contains($configGuard, 'InstallAccess::isInstallerExecutionAllowed()')) {
        $errors[] = 'runtime_missing_config_not_using_canonical_installer_gate';
    }
    if (str_contains($configGuard, 'InstallAccess::isLocalHttpRequest()')) {
        $errors[] = 'runtime_missing_config_still_localhost_only';
    }
    if (!str_contains($configGuard, 'Location: /install.php')) {
        $errors[] = 'runtime_missing_config_no_installer_redirect';
    }
}
"""
security = replace_once(security, anchor, addition, "teste da guarda do runtime")
write("tools/install-security-check.php", security)

# 4. Registra a correção na fonte documental única.
changelog = read("ChangeLog.txt")
entry = """Prontoo 1.7.22.1 — hotfix da guarda pública do instalador

- Corrige a segunda proteção acionada quando `app/config.php` está ausente: `prontoo_run()` deixa de aceitar somente localhost e passa a reutilizar `InstallAccess::isInstallerExecutionAllowed()`.
- Durante a janela pública vigente, qualquer fallback do runtime redireciona para `/install.php` em vez de exibir “O instalador não é exposto publicamente”.
- Após o timestamp final, a mesma decisão canônica continua falhando fechada e o instalador volta a responder 404 para acessos públicos.
- Acrescenta teste regressivo que rejeita o retorno da checagem exclusiva `isLocalHttpRequest()` no fluxo sem configuração.
- Não altera banco, schema, dados, interface, ativos ou a duração da janela pública já aprovada.

"""
if not changelog.startswith("Prontoo 1.7.21.8"):
    raise RuntimeError("ChangeLog.txt não começa na versão esperada")
write("ChangeLog.txt", entry + changelog)

# 5. Sincroniza as fontes de versão do runtime e da landing page.
prontoo = read("app/prontoo.php")
prontoo = replace_once(
    prontoo,
    f'const PRONTOO_VERSION_FALLBACK = "{OLD_VERSION}";',
    f'const PRONTOO_VERSION_FALLBACK = "{NEW_VERSION}";',
    "fallback principal de versão",
)
prontoo = replace_regex(
    prontoo,
    r'const PRONTOO_PREVIOUS_VERSION = "[^"]+";',
    f'const PRONTOO_PREVIOUS_VERSION = "{OLD_VERSION}";',
    "versão anterior do runtime",
)
write("app/prontoo.php", prontoo)

landing = read("br/index.php")
landing = replace_once(
    landing,
    f'const BR_LANDING_VERSION_FALLBACK = "{OLD_VERSION}";',
    f'const BR_LANDING_VERSION_FALLBACK = "{NEW_VERSION}";',
    "fallback da landing",
)
write("br/index.php", landing)

# 6. Atualiza contratos JSON sem tocar no schema congelado.
generated_unix = int(datetime.fromisoformat(GENERATED_AT.replace("Z", "+00:00")).timestamp())

version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "version": NEW_VERSION,
    "release": NEW_VERSION,
    "generated_at_unix": generated_unix,
    "generated_at": GENERATED_AT,
    "updated_at": GENERATED_AT,
    "build": BUILD,
    "package_type": PACKAGE_TYPE,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "previous_version": OLD_VERSION,
    "documentation_changes": True,
    "notes": NOTES,
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture["version"] = NEW_VERSION
architecture["database_changes"] = False
architecture["schema_changes"] = False
architecture["visual_changes"] = False
architecture["logic_changes"] = True
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

operational_path = ROOT / "app/Database/operational-schema.contract.json"
operational = json.loads(operational_path.read_text(encoding="utf-8"))
operational["version"] = NEW_VERSION
operational_path.write_text(json.dumps(operational, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": NEW_VERSION,
    "release": NEW_VERSION,
    "build": BUILD,
    "package_type": PACKAGE_TYPE,
    "generated_at": GENERATED_AT,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "previous_version": OLD_VERSION,
    "updated_at": GENERATED_AT,
    "notes": NOTES,
})
files = manifest.get("files")
if not isinstance(files, dict) or not files:
    raise RuntimeError("manifesto sem coleção de arquivos")
if "app/update.manifest.json" in files:
    raise RuntimeError("manifesto não pode selar a si próprio")
bytes_total = 0
for relative in list(files):
    path = ROOT / relative
    if not path.is_file():
        raise RuntimeError(f"arquivo selado ausente: {relative}")
    data = path.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    bytes_total += len(data)
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = bytes_total
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

print(json.dumps({
    "ok": True,
    "version": NEW_VERSION,
    "previous_version": OLD_VERSION,
    "runner_guard": "InstallAccess::isInstallerExecutionAllowed",
    "public_window_start": version.get("temporary_public_installer", {}).get("start_utc"),
    "public_window_end": version.get("temporary_public_installer", {}).get("end_utc"),
    "schema_changed": False,
}, ensure_ascii=False, indent=2))
