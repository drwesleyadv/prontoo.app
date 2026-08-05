from __future__ import annotations

import hashlib
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path

root = Path(__file__).resolve().parent
script = Path(__file__).resolve()
workflow = ".github/workflows/agent-php84-manifest-finalize.yml"
manifest_path = root / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))

script.unlink()
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).split(b"\0")
candidates = {raw.decode("utf-8") for raw in tracked if raw}
candidates.discard(".agent-php84-manifest-finalize.py")
candidates.discard(workflow)

files: dict[str, str] = {}
total = 0
for relative in sorted(candidates):
    if relative == "app/update.manifest.json":
        continue
    absolute = root / relative
    if not absolute.is_file():
        continue
    data = absolute.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)

now = datetime.now(timezone.utc)
manifest["generated_at"] = now.isoformat()
manifest["generated_at_unix"] = int(now.timestamp())
manifest["updated_at"] = now.isoformat()
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["files"] = files
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

print(json.dumps({"ok": True, "files": len(files), "bytes": total}, ensure_ascii=False))
