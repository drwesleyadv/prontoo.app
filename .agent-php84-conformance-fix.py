from __future__ import annotations

import subprocess
import sys
from pathlib import Path

root = Path(__file__).resolve().parent
self_path = Path(__file__).resolve()
target = root / ".agent-php84-conformance.py"
source = target.read_text(encoding="utf-8")
old = '''        if call["named"]:
            if name == "round" and args < 3:
                raise RuntimeError(f"round() com argumentos nomeados exige revisão manual: {path}")
            if name in {"fgetcsv", "fputcsv", "str_getcsv"}:
                required = 5 if name in {"fgetcsv", "fputcsv"} else 4
                if args < required:
                    raise RuntimeError(f"{name}() com argumentos nomeados exige revisão manual: {path}")
            continue
'''
if source.count(old) != 1:
    raise RuntimeError("Bloco de falso positivo de argumentos nomeados não localizado")
target.write_text(source.replace(old, "", 1), encoding="utf-8")
self_path.unlink()
subprocess.run([sys.executable, str(target)], cwd=root, check=True)
