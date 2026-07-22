#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION_PATH = ROOT / "version.json"
MANIFEST_PATH = ROOT / "app" / "update.manifest.json"
SYNC_ID = "hostoo-redeploy-20260722T145259Z"
SYNC_AT = "2026-07-22T14:52:59Z"


def load_json(path: Path) -> dict:
    data = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(data, dict):
        raise RuntimeError(f"JSON raiz inválido: {path}")
    return data


def write_json(path: Path, data: dict, indent: int) -> None:
    path.write_text(
        json.dumps(data, ensure_ascii=False, indent=indent) + "\n",
        encoding="utf-8",
    )


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


version = load_json(VERSION_PATH)
if version.get("version") != "1.7.22.3" or version.get("release") != "1.7.22.3":
    raise RuntimeError("A sincronização só pode operar sobre a release 1.7.22.3.")
if version.get("installer_locked") is not True:
    raise RuntimeError("O instalador precisa permanecer bloqueado.")
if version.get("database_changes") is not False or version.get("schema_changes") is not False:
    raise RuntimeError("O redeploy não pode declarar alteração de banco ou schema.")

already_applied = version.get("deployment_sync_id") == SYNC_ID
if not already_applied:
    version["updated_at"] = SYNC_AT
    version["deployment_sync_id"] = SYNC_ID
    version["deployment_sync_requested_at"] = SYNC_AT
    write_json(VERSION_PATH, version, 2)

manifest = load_json(MANIFEST_PATH)
if manifest.get("version") != "1.7.22.3" or manifest.get("release") != "1.7.22.3":
    raise RuntimeError("Manifesto fora da release 1.7.22.3.")
files = manifest.get("files")
if not isinstance(files, dict) or "version.json" not in files:
    raise RuntimeError("Manifesto sem version.json.")

files["version.json"] = sha256(VERSION_PATH)
manifest["updated_at"] = SYNC_AT
manifest["deployment_sync_id"] = SYNC_ID
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = sum(
    (ROOT / relative).stat().st_size for relative in files
)
write_json(MANIFEST_PATH, manifest, 4)

for relative, expected in files.items():
    path = ROOT / relative
    if not path.is_file():
        raise RuntimeError(f"Arquivo ausente no manifesto: {relative}")
    actual = sha256(path)
    if actual != expected:
        raise RuntimeError(f"Hash divergente após sincronização: {relative}")

print(json.dumps({
    "ok": True,
    "version": version["version"],
    "deployment_sync_id": SYNC_ID,
    "already_applied": already_applied,
    "file_count": manifest["file_count"],
    "total_uncompressed_bytes": manifest["total_uncompressed_bytes"],
    "version_sha256": files["version.json"],
}, ensure_ascii=False))
