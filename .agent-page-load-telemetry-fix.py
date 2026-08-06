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
source = source.replace(
    '''# ADR 0007 — Carregamento de página como fonte de verdade da telemetria\n\n## Status\n\nAceito na versão 1.8.6.2.\n''',
    '''# ADR 0007 — Carregamento de página como fonte de verdade da telemetria\n\n**Status:** aceito\n\n**Data:** 2026-08-06\n''',
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

architecture_write = 'write("tools/architecture-check.php", architecture_check)\n'
architecture_patch = r'''architecture_check = replace_once(
    architecture_check,
    '''"/telemetria.json"''',
    '''"/page-loads.jsonl"''',
    "fonte da verdade no contrato de logout",
)
architecture_check = replace_once(
    architecture_check,
    '''    '"duracao_ms" => round($durationNs / 1000000, 6, \\RoundingMode::HalfAwayFromZero)',
    'return telemetry_storage_dir() . "/telemetria.json";',
''',
    '''    '"duracao_ms" => round(',
    '\\RoundingMode::HalfAwayFromZero',
    'return telemetry_storage_dir() . "/page-loads.jsonl";',
    'return "prontoo.telemetria.pagina.v2";',
    'function telemetry_page_request_candidate(',
    'function telemetry_page_response_candidate(',
    '"tipo" => "page_load"',
    '"marco_inicial" => "front_controller_first_executable_line"',
    '"front_controller_last_useful_line"',
    '"shutdown_fallback"',
''',
    "caracterização da telemetria de página",
)
architecture_check = replace_once(
    architecture_check,
    '''foreach ([
    'page-load',
    'route-performance.json',
''',
    '''foreach ([
    'prontoo.telemetria.rota.v1',
    '"/telemetria.json"',
    'route-performance.json',
''',
    "remoção da proibição do contrato page-load",
)
write("tools/architecture-check.php", architecture_check)
'''
if architecture_write not in source:
    raise RuntimeError("Ponto de fechamento do contrato arquitetural não encontrado")
source = source.replace(architecture_write, architecture_patch, 1)
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
