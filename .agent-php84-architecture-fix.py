from __future__ import annotations

import hashlib
import json
import subprocess
import sys
from pathlib import Path

root = Path(__file__).resolve().parent
self_path = Path(__file__).resolve()
subprocess.run([sys.executable, str(root / ".agent-php84-fix.py")], cwd=root, check=True)

old_guard = root / "app/Support/Php84Runtime.php"
new_guard = root / "app/Core/Runtime/Php84Runtime.php"
new_guard.parent.mkdir(parents=True, exist_ok=True)
if not old_guard.is_file():
    raise RuntimeError("Guarda PHP 8.4 transitória não encontrada")
new_guard.write_text(old_guard.read_text(encoding="utf-8"), encoding="utf-8")
old_guard.unlink()

old_check = root / "tools/php84-runtime-contract-check.php"
new_check = root / "tools/php84-runtime-contract-check"
if not old_check.is_file():
    raise RuntimeError("Verificador PHP 8.4 transitório não encontrado")
check_source = old_check.read_text(encoding="utf-8").replace(
    '$guardPath = "app/Support/Php84Runtime.php";',
    '$guardPath = "app/Core/Runtime/Php84Runtime.php";',
)
new_check.write_text(check_source, encoding="utf-8")
old_check.unlink()

replacements = {
    "app/prontoo.php": (
        'require_once __DIR__ . "/Support/Php84Runtime.php";',
        'require_once __DIR__ . "/Core/Runtime/Php84Runtime.php";',
    ),
    "br/index.php": (
        'require_once dirname(__DIR__) . "/app/Support/Php84Runtime.php";',
        'require_once dirname(__DIR__) . "/app/Core/Runtime/Php84Runtime.php";',
    ),
    "cron/maestro.php": (
        'require_once dirname(__DIR__) . "/app/Support/Php84Runtime.php";',
        'require_once dirname(__DIR__) . "/app/Core/Runtime/Php84Runtime.php";',
    ),
    ".github/workflows/architecture.yml": (
        "php tools/php84-runtime-contract-check.php",
        "php tools/php84-runtime-contract-check",
    ),
}
for relative, (old, new) in replacements.items():
    path = root / relative
    source = path.read_text(encoding="utf-8")
    if source.count(old) != 1:
        raise RuntimeError(f"Referência não reconhecida: {relative}")
    path.write_text(source.replace(old, new, 1), encoding="utf-8")

check_source = new_check.read_text(encoding="utf-8")
entry_replacements = {
    '"app/prontoo.php" => \'require_once __DIR__ . "/Support/Php84Runtime.php";\'':
        '"app/prontoo.php" => \'require_once __DIR__ . "/Core/Runtime/Php84Runtime.php";\'',
    '"br/index.php" => \'require_once dirname(__DIR__) . "/app/Support/Php84Runtime.php";\'':
        '"br/index.php" => \'require_once dirname(__DIR__) . "/app/Core/Runtime/Php84Runtime.php";\'',
    '"cron/maestro.php" => \'require_once dirname(__DIR__) . "/app/Support/Php84Runtime.php";\'':
        '"cron/maestro.php" => \'require_once dirname(__DIR__) . "/app/Core/Runtime/Php84Runtime.php";\'',
}
for old, new in entry_replacements.items():
    if check_source.count(old) != 1:
        raise RuntimeError("Entrada do verificador não reconhecida")
    check_source = check_source.replace(old, new, 1)
new_check.write_text(check_source, encoding="utf-8")

self_path.unlink()

manifest_path = root / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).split(b"\0")
working_candidates = {raw.decode("utf-8") for raw in tracked if raw}
working_candidates.discard("app/Support/Php84Runtime.php")
working_candidates.discard("tools/php84-runtime-contract-check.php")
working_candidates.discard(".agent-php84-architecture-fix.py")
working_candidates.add("app/Core/Runtime/Php84Runtime.php")
working_candidates.add("tools/php84-runtime-contract-check")
files: dict[str, str] = {}
total = 0
for relative in sorted(working_candidates):
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
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

print(json.dumps({
    "ok": True,
    "native_guard": str(new_guard.relative_to(root)),
    "operational_check": str(new_check.relative_to(root)),
}, ensure_ascii=False))
