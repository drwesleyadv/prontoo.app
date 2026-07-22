#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

OLD_START = 1784671890
OLD_END = 1784686290
NEW_START = 1784722014
NEW_END = 1784736414
NEW_START_UTC = "2026-07-22T12:06:54Z"
NEW_END_UTC = "2026-07-22T16:06:54Z"
NEW_START_LOCAL = "22/07/2026 08:06:54 America/Cuiaba"
NEW_END_LOCAL = "22/07/2026 12:06:54 America/Cuiaba"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_contract(content: str, old: str, new: str, label: str) -> str:
    old_count = content.count(old)
    new_count = content.count(new)
    if old_count == 1:
        return content.replace(old, new, 1)
    if old_count == 0 and new_count == 1:
        return content
    raise RuntimeError(f"{label}: contrato inesperado (old={old_count}, new={new_count})")


# Regra do servidor: permite a passagem ao PHP, mas deixa explícita a expiração real.
htaccess = read(".htaccess")
htaccess = replace_contract(
    htaccess,
    "# automaticamente em 2026-07-22T02:11:30Z (21/07/2026 22:11:30 em Cuiabá).",
    "# automaticamente em 2026-07-22T16:06:54Z (22/07/2026 12:06:54 em Cuiabá).",
    ".htaccess",
)
write(".htaccess", htaccess)

# Registro documental único do projeto.
changelog = read("ChangeLog.txt")
old_block = (
    "- Janela fixa: 21/07/2026 18:11:30 America/Cuiaba até 21/07/2026 22:11:30 America/Cuiaba; duração exata de 14.400 segundos.\n"
    "- A revogação é automática e fail-closed no núcleo `InstallAccess`: a partir de 2026-07-22T02:11:30Z, requisições públicas recebem 404 sem depender de cron ou nova publicação."
)
new_block = (
    "- Janela renovada: 22/07/2026 08:06:54 America/Cuiaba até 22/07/2026 12:06:54 America/Cuiaba; duração exata de 14.400 segundos contados do timestamp solicitado.\n"
    "- A revogação é automática e fail-closed no núcleo `InstallAccess`: a partir de 2026-07-22T16:06:54Z, requisições públicas recebem 404 sem depender de cron ou nova publicação."
)
changelog = replace_contract(changelog, old_block, new_block, "ChangeLog.txt")
write("ChangeLog.txt", changelog)

# Contrato canônico executado antes do bootstrap do instalador.
access = read("app/Core/Install/InstallAccess.php")
access = replace_contract(
    access,
    f"public const TEMPORARY_PUBLIC_WINDOW_START_UNIX = {OLD_START};",
    f"public const TEMPORARY_PUBLIC_WINDOW_START_UNIX = {NEW_START};",
    "InstallAccess start",
)
access = replace_contract(
    access,
    f"public const TEMPORARY_PUBLIC_WINDOW_END_UNIX = {OLD_END};",
    f"public const TEMPORARY_PUBLIC_WINDOW_END_UNIX = {NEW_END};",
    "InstallAccess end",
)
access = replace_contract(
    access,
    f"no segundo {OLD_END} o acesso público já deve falhar.",
    f"no segundo {NEW_END} o acesso público já deve falhar.",
    "InstallAccess maintenance guide",
)
write("app/Core/Install/InstallAccess.php", access)

# Manifesto arquitetural: mesma política, nova janela temporal.
architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
window = architecture["temporary_public_installer_window"]
window["start_unix"] = NEW_START
window["end_unix"] = NEW_END
window["end_utc"] = NEW_END_UTC
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

# Teste determinístico publicado junto da implementação.
security = read("tools/install-security-check.php")
security = replace_contract(
    security,
    f"'public_window_start_unix' => {OLD_START},",
    f"'public_window_start_unix' => {NEW_START},",
    "security start",
)
security = replace_contract(
    security,
    f"'public_window_end_unix' => {OLD_END},",
    f"'public_window_end_unix' => {NEW_END},",
    "security end",
)
security = replace_contract(
    security,
    "'public_window_end_utc' => '2026-07-22T02:11:30Z',",
    "'public_window_end_utc' => '2026-07-22T16:06:54Z',",
    "security end UTC",
)
write("tools/install-security-check.php", security)

# Metadados públicos da versão estável.
version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version["updated_at"] = NEW_START_UTC
version["notes"] = "Renova a janela pública temporária do instalador por quatro horas contadas do timestamp atual solicitado, com revogação automática por timestamp fixo."
version_window = version["temporary_public_installer"]
version_window.update({
    "start_unix": NEW_START,
    "end_unix": NEW_END,
    "start_utc": NEW_START_UTC,
    "end_utc": NEW_END_UTC,
    "start_local": NEW_START_LOCAL,
    "end_local": NEW_END_LOCAL,
    "automatic_revocation": True,
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

# Manifesto de distribuição e selagem SHA-256 dos arquivos canônicos.
manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest["installer_policy"] = "localhost_or_https_prontoo_app_until_2026-07-22T16:06:54Z_then_local_only"
manifest["updated_at"] = NEW_START_UTC
manifest["notes"] = "Renova a janela pública temporária do instalador por quatro horas contadas do timestamp atual solicitado e revoga automaticamente por timestamp fixo."
manifest_window = manifest["temporary_public_installer"]
manifest_window.update({
    "start_unix": NEW_START,
    "end_unix": NEW_END,
    "end_utc": NEW_END_UTC,
    "automatic_revocation": True,
})

bytes_total = 0
for relative in list(manifest["files"].keys()):
    file_path = ROOT / relative
    if not file_path.is_file():
        raise RuntimeError(f"Arquivo do manifesto ausente: {relative}")
    payload = file_path.read_bytes()
    manifest["files"][relative] = hashlib.sha256(payload).hexdigest()
    bytes_total += len(payload)
manifest["total_uncompressed_bytes"] = bytes_total
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

if NEW_END - NEW_START != 14400:
    raise RuntimeError("A janela não possui exatamente quatro horas")

print(json.dumps({
    "ok": True,
    "version": version["version"],
    "start_unix": NEW_START,
    "end_unix": NEW_END,
    "duration_seconds": NEW_END - NEW_START,
    "start_local": NEW_START_LOCAL,
    "end_local": NEW_END_LOCAL,
}, ensure_ascii=False, indent=2))
