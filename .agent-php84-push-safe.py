from __future__ import annotations

import hashlib
import json
import subprocess
import sys
from pathlib import Path

root = Path(__file__).resolve().parent
self_path = Path(__file__).resolve()
workflow_path = root / ".github/workflows/agent-php84-release.yml"
workflow_content = workflow_path.read_text(encoding="utf-8")

subprocess.run(
    [sys.executable, str(root / ".agent-php84-inline-fix.py")],
    cwd=root,
    check=True,
)

workflow_path.parent.mkdir(parents=True, exist_ok=True)
workflow_path.write_text(workflow_content, encoding="utf-8")
self_path.unlink()

manifest_path = root / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).split(b"\0")
candidates = {raw.decode("utf-8") for raw in tracked if raw}
for obsolete in [
    "app/Support/Php84Runtime.php",
    "app/Core/Runtime/Php84Runtime.php",
    "tools/php84-runtime-contract-check.php",
    ".agent-php84-release.py",
    ".agent-php84-fix.py",
    ".agent-php84-architecture-fix.py",
    ".agent-php84-inline-fix.py",
    ".agent-php84-push-safe.py",
    ".github/workflows/agent-php84-release.yml",
]:
    candidates.discard(obsolete)
candidates.add("tools/php84-runtime-contract-check")
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
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["files"] = files
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

print(json.dumps({
    "ok": True,
    "workflow_restored_for_token_safe_push": True,
    "manifest_targets_final_repository_state": True,
}, ensure_ascii=False))
