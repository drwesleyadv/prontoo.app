from __future__ import annotations

import subprocess
import sys
from pathlib import Path

root = Path(__file__).resolve().parent
target = root / ".agent-php84-conformance-final.py"
source = target.read_text(encoding="utf-8")
anchor = "# Contrato de funções após todas as refatorações.\nafter_audit = php_audit(php_files)"
replacement = '''# Contrato de funções após todas as refatorações.\nfor lint_path in php_files:\n    lint_process = subprocess.run(\n        [\"php\", \"-d\", \"error_reporting=32767\", \"-d\", \"display_errors=1\", \"-d\", \"log_errors=0\", \"-l\", str(ROOT / lint_path)],\n        cwd=ROOT,\n        text=True,\n        stdout=subprocess.PIPE,\n        stderr=subprocess.STDOUT,\n        check=False,\n    )\n    if lint_process.returncode != 0 or \"deprecated\" in lint_process.stdout.lower():\n        raise RuntimeError(\"Falha PHP 8.4 após refatoração em \" + lint_path + \":\\n\" + lint_process.stdout)\nafter_audit = php_audit(php_files)'''
if source.count(anchor) != 1:
    raise RuntimeError("Âncora do lint intermediário não localizada")
target.write_text(source.replace(anchor, replacement, 1), encoding="utf-8")
subprocess.run([sys.executable, str(target)], cwd=root, check=True)
