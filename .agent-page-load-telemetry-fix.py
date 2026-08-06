from __future__ import annotations

import subprocess
import sys
from pathlib import Path

root = Path(__file__).resolve().parent
self_path = Path(__file__).resolve()
target = root / ".agent-page-load-telemetry.py"
source = target.read_text(encoding="utf-8")
source = source.replace(
    '        static fn(): mixed => telemetry_route_finish_marker(true),\n',
    '        static function (): void {\n            telemetry_route_finish_marker(true);\n        },\n',
)
source = source.replace('            \\\\\\RoundingMode::HalfAwayFromZero,', '            \\\\RoundingMode::HalfAwayFromZero,')
old = '''landing = replace_once(
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
'''
new = '''landing = replace_once(
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
'''
if old not in source:
    raise RuntimeError("Bloco de reposicionamento da landing não reconhecido")
source = source.replace(old, new, 1)
target.write_text(source, encoding="utf-8")
self_path.unlink()
subprocess.run([sys.executable, str(target)], cwd=root, check=True)
