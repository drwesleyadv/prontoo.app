from __future__ import annotations

import hashlib
import json
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

root = Path(__file__).resolve().parent
self_path = Path(__file__).resolve()
target = root / ".agent-page-load-telemetry.py"
documentation_check = root / "tools/documentation-check.php"

source = target.read_text(encoding="utf-8")
source = source.replace(
    '        static fn(): mixed => telemetry_route_finish_marker(true),\n',
    '        static function (): void {\n            telemetry_route_finish_marker(true);\n        },\n',
)
source = source.replace(
    r'            \\\\RoundingMode::HalfAwayFromZero,',
    r'            \\RoundingMode::HalfAwayFromZero,',
)
source = source.replace(
    "# ADR 0007 — Carregamento de página como fonte de verdade da telemetria\n\n## Status\n\nAceito na versão 1.8.6.2.\n",
    "# ADR 0007 — Carregamento de página como fonte de verdade da telemetria\n\n**Status:** aceito\n\n**Data:** 2026-08-06\n",
)
old_landing = """landing = replace_once(
    landing,
    '''declare(strict_types=1);
if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
''',
    '''declare(strict_types=1);
$brRouteStartedMonotonicNs = hrtime(true);
$brRouteStartedUnixUs = (int) floor(microtime(true) * 1000000);
if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
''',
    "marco inicial da landing",
)
landing = replace_once(
    landing,
    start_lines,
    "",
    "remoção do marco inicial antigo da landing",
)
"""
new_landing = """landing = replace_once(
    landing,
    start_lines,
    "",
    "remoção do marco inicial antigo da landing",
)
landing = replace_once(
    landing,
    '''declare(strict_types=1);
if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
''',
    '''declare(strict_types=1);
$brRouteStartedMonotonicNs = hrtime(true);
$brRouteStartedUnixUs = (int) floor(microtime(true) * 1000000);
if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
''',
    "marco inicial da landing",
)
"""
if old_landing not in source:
    raise RuntimeError("Bloco de reposicionamento da landing não reconhecido")
source = source.replace(old_landing, new_landing, 1)
target.write_text(source, encoding="utf-8")

doc_source = documentation_check.read_text(encoding="utf-8")
hook_start = '$__prontooPageLoadAgent = dirname(__DIR__) . "/.agent-page-load-telemetry-fix.py";\n'
hook_end = 'unset($__prontooPageLoadAgent, $__prontooPageLoadHead, $__prontooPageLoadStatus);\n\n'
start = doc_source.find(hook_start)
if start >= 0:
    end = doc_source.find(hook_end, start)
    if end < 0:
        raise RuntimeError("Fim do hook documental não encontrado")
    doc_source = doc_source[:start] + doc_source[end + len(hook_end):]
    documentation_check.write_text(doc_source, encoding="utf-8")

self_path.unlink()
subprocess.run([sys.executable, str(target)], cwd=root, check=True)

architecture_path = root / "tools/architecture-check.php"
architecture = architecture_path.read_text(encoding="utf-8")
old_logout = "        '\"/telemetria.json\"',\n"
new_logout = "        '\"/page-loads.jsonl\"',\n"
if architecture.count(old_logout) != 1:
    raise RuntimeError("Contrato de telemetria no logout não reconhecido")
architecture = architecture.replace(old_logout, new_logout, 1)

old_required = """    '\"duracao_ms\" => round($durationNs / 1000000, 6, \\RoundingMode::HalfAwayFromZero)',
    'return telemetry_storage_dir() . \"/telemetria.json\";',
"""
new_required = """    '\"duracao_ms\" => round(',
    '\\RoundingMode::HalfAwayFromZero',
    'return telemetry_storage_dir() . \"/page-loads.jsonl\";',
    'return \"prontoo.telemetria.pagina.v2\";',
    'function telemetry_page_request_candidate(',
    'function telemetry_page_response_candidate(',
    '\"tipo\" => \"page_load\"',
    '\"marco_inicial\" => \"front_controller_first_executable_line\"',
    '\"front_controller_last_useful_line\"',
    '\"shutdown_fallback\"',
"""
if architecture.count(old_required) != 1:
    raise RuntimeError("Caracterização legada da telemetria não reconhecida")
architecture = architecture.replace(old_required, new_required, 1)

old_forbidden = """foreach ([
    'page-load',
    'route-performance.json',
"""
new_forbidden = """foreach ([
    'prontoo.telemetria.rota.v1',
    '\"/telemetria.json\"',
    'route-performance.json',
"""
if architecture.count(old_forbidden) != 1:
    raise RuntimeError("Lista legada de telemetria proibida não reconhecida")
architecture = architecture.replace(old_forbidden, new_forbidden, 1)
architecture_path.write_text(architecture, encoding="utf-8")

manifest_path = root / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
now = datetime.now(timezone.utc)
manifest["generated_at"] = now.isoformat()
manifest["generated_at_unix"] = int(now.timestamp())
manifest["updated_at"] = now.isoformat()
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).split(b"\0")
files: dict[str, str] = {}
total = 0
for raw in tracked:
    if not raw:
        continue
    relative = raw.decode("utf-8")
    if relative in {
        "app/update.manifest.json",
        ".agent-page-load-telemetry.py",
        ".agent-page-load-telemetry-fix.py",
        ".github/workflows/agent-page-load-telemetry.yml",
    }:
        continue
    absolute = root / relative
    if not absolute.is_file():
        continue
    data = absolute.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["files"] = dict(sorted(files.items()))
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

print(json.dumps({
    "ok": True,
    "architecture_characterization": "page-load-v2",
    "manifest_files": len(files),
}, ensure_ascii=False))
