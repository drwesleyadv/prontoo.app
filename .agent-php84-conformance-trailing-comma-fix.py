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
]
for old, new, label in replacements:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1, encontrado {count}")
    source = source.replace(old, new, 1)
target.write_text(source, encoding="utf-8")
subprocess.run(
    [sys.executable, str(root / ".agent-php84-conformance-lint-fix.py")],
    cwd=root,
    check=True,
)
