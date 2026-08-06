from __future__ import annotations

import subprocess
import sys
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
old = """landing = replace_once(
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
new = """landing = replace_once(
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
if old not in source:
    raise RuntimeError("Bloco de reposicionamento da landing não reconhecido")
source = source.replace(old, new, 1)
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
