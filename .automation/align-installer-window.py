#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OLD = {
    "1784670941": "1784671890",
    "1784685341": "1784686290",
    "2026-07-21T21:55:41Z": "2026-07-21T22:11:30Z",
    "2026-07-22T01:55:41Z": "2026-07-22T02:11:30Z",
    "21/07/2026 17:55:41 America/Cuiaba": "21/07/2026 18:11:30 America/Cuiaba",
    "21/07/2026 21:55:41 America/Cuiaba": "21/07/2026 22:11:30 America/Cuiaba",
    "21/07/2026 21:55:41 em Cuiabá": "21/07/2026 22:11:30 em Cuiabá",
}
SCHEMA_HASH = "28515f46af3081e5ac0dded5723e6d2d7ceddec11e9c6f782975f31931a69d33"


def replace_all(path: str) -> None:
    file = ROOT / path
    content = file.read_text(encoding="utf-8")
    for old, new in OLD.items():
        content = content.replace(old, new)
    file.write_text(content, encoding="utf-8")


for relative in [
    ".htaccess",
    "ChangeLog.txt",
    "app/Core/Install/InstallAccess.php",
    "tools/install-security-check.php",
]:
    replace_all(relative)

version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version["previous_version"] = "1.7.21.7"
version["notes"] = "Libera temporariamente o instalador público por quatro horas a partir do merge certificado, com revogação automática por timestamp fixo."
window = version["temporary_public_installer"]
window.update({
    "start_unix": 1784671890,
    "end_unix": 1784686290,
    "start_utc": "2026-07-21T22:11:30Z",
    "end_utc": "2026-07-22T02:11:30Z",
    "start_local": "21/07/2026 18:11:30 America/Cuiaba",
    "end_local": "21/07/2026 22:11:30 America/Cuiaba",
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

arch_path = ROOT / "app/architecture.manifest.json"
arch = json.loads(arch_path.read_text(encoding="utf-8"))
arch_window = arch["temporary_public_installer_window"]
arch_window.update({
    "start_unix": 1784671890,
    "end_unix": 1784686290,
    "end_utc": "2026-07-22T02:11:30Z",
})
arch_path.write_text(json.dumps(arch, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest["installer_policy"] = "localhost_or_https_prontoo_app_until_2026-07-22T02:11:30Z_then_local_only"
manifest["notes"] = "Libera temporariamente o instalador público por quatro horas contadas do merge certificado e revoga automaticamente por timestamp fixo."
manifest_window = manifest["temporary_public_installer"]
manifest_window.update({
    "start_unix": 1784671890,
    "end_unix": 1784686290,
    "end_utc": "2026-07-22T02:11:30Z",
})
files = manifest["files"]
total = 0
for relative in list(files):
    path = ROOT / relative
    payload = path.read_bytes()
    files[relative] = hashlib.sha256(payload).hexdigest()
    total += len(payload)
manifest["total_uncompressed_bytes"] = total
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

actual_schema = hashlib.sha256((ROOT / "app/Database/schema.sql").read_bytes()).hexdigest()
if actual_schema != SCHEMA_HASH:
    raise RuntimeError("schema.sql alterado")

print(json.dumps({
    "start_unix": 1784671890,
    "end_unix": 1784686290,
    "duration_seconds": 14400,
    "previous_version": version["previous_version"],
    "schema_unchanged": True,
}, ensure_ascii=False, indent=2))
