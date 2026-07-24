from __future__ import annotations

import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
check_path = ROOT / "tools/architecture-check.php"
check = check_path.read_text(encoding="utf-8")
old = "        'VALUES\\n               (?,?,1,UNIX_TIMESTAMP()+2,NOW()),',\n"
new = '        "VALUES\\n               (?,?,1,UNIX_TIMESTAMP()+2,NOW()),",\n'
if check.count(old) != 1:
    raise RuntimeError(f"Contrato esperado não encontrado exatamente uma vez: {check.count(old)}")
check_path.write_text(check.replace(old, new, 1), encoding="utf-8")

self_path = ROOT / "tools/fix-auth-performance-contract.py"
if self_path.exists():
    self_path.unlink()

manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
files = manifest.get("files", {})
if not isinstance(files, dict) or not files:
    raise RuntimeError("Manifesto de arquivos inválido")
new_files: dict[str, str] = {}
total = 0
for rel in sorted(files):
    path = ROOT / rel
    if not path.is_file():
        raise RuntimeError(f"Arquivo versionado ausente: {rel}")
    data = path.read_bytes()
    new_files[rel] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["files"] = new_files
manifest["file_count"] = len(new_files)
manifest["total_uncompressed_bytes"] = total
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
    encoding="utf-8",
)
print("authentication regression contract fixed")
