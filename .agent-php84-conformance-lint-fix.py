from __future__ import annotations

import subprocess
import sys
from pathlib import Path

root = Path(__file__).resolve().parent
target = root / ".agent-php84-conformance-final.py"
source = target.read_text(encoding="utf-8")

replacements = [
    (
        '''        $depth = 0;\n        $commas = 0;\n        $hasContent = false;\n        $close = null;''',
        '''        $depth = 0;\n        $commas = 0;\n        $hasContent = false;\n        $lastTopLevel = null;\n        $close = null;''',
        "estado da vírgula final",
    ),
    (
        '''            if ($text === ',') {\n                $commas++;\n            } else {\n                $hasContent = true;\n            }''',
        '''            if ($text === ',') {\n                $commas++;\n                $lastTopLevel = ',';\n            } else {\n                $hasContent = true;\n                $lastTopLevel = $text;\n            }''',
        "rastreamento da vírgula final",
    ),
    (
        '''            'args' => $hasContent ? $commas + 1 : 0,\n            'close_offset_bytes' => $close,''',
        '''            'args' => $hasContent ? ($lastTopLevel === ',' ? $commas : $commas + 1) : 0,\n            'trailing_comma' => $lastTopLevel === ',',\n            'close_offset_bytes' => $close,''',
        "contagem real de argumentos",
    ),
    (
        '''        args = int(call["args"])\n        insertion = ""''',
        '''        args = int(call["args"])\n        trailing_comma = bool(call.get("trailing_comma", False))\n        insertion = ""''',
        "estado da vírgula final no Python",
    ),
    (
        '''        if insertion:\n            insertions.append((int(call["close_offset_bytes"]), insertion.encode("utf-8"), label))''',
        '''        if insertion:\n            if trailing_comma and insertion.startswith(", "):\n                insertion = insertion[2:]\n            insertions.append((int(call["close_offset_bytes"]), insertion.encode("utf-8"), label))''',
        "inserção após vírgula final",
    ),
    (
        '''    "strict_level" => "E_" . "STRICT",\n''',
        '''''',
        "remover detecção textual de E_STRICT",
    ),
    (
        '''foreach ($phpFiles as $path) {\n    $source = $read($path);\n    $lint = [];''',
        '''foreach ($phpFiles as $path) {\n    $source = $read($path);\n    foreach (token_get_all($source, TOKEN_PARSE) as $sourceToken) {\n        if (is_array($sourceToken) &&\n            $sourceToken[0] === T_STRING &&\n            strtoupper((string) $sourceToken[1]) === "E_STRICT") {\n            $failures[] = "Constante E_STRICT descontinuada em " . $path;\n        }\n    }\n    $lint = [];''',
        "detecção tokenizada de E_STRICT",
    ),
]
for old, new, label in replacements:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1, encontrado {count}")
    source = source.replace(old, new, 1)

anchor = "# Contrato de funções após todas as refatorações.\nafter_audit = php_audit(php_files)"
replacement = '''# Contrato de funções após todas as refatorações.\nfor lint_path in php_files:\n    lint_process = subprocess.run(\n        [\"php\", \"-d\", \"error_reporting=32767\", \"-d\", \"display_errors=1\", \"-d\", \"log_errors=0\", \"-l\", str(ROOT / lint_path)],\n        cwd=ROOT,\n        text=True,\n        stdout=subprocess.PIPE,\n        stderr=subprocess.STDOUT,\n        check=False,\n    )\n    if lint_process.returncode != 0 or \"deprecated\" in lint_process.stdout.lower():\n        raise RuntimeError(\"Falha PHP 8.4 após refatoração em \" + lint_path + \":\\n\" + lint_process.stdout)\nafter_audit = php_audit(php_files)'''
if source.count(anchor) != 1:
    raise RuntimeError("Âncora do lint intermediário não localizada")
source = source.replace(anchor, replacement, 1)
target.write_text(source, encoding="utf-8")
subprocess.run([sys.executable, str(target)], cwd=root, check=True)
