from __future__ import annotations

import subprocess
import sys
from pathlib import Path

root = Path(__file__).resolve().parent
self_path = Path(__file__).resolve()
target = root / ".agent-maestro-hardening.py"
source = target.read_text(encoding="utf-8")
old = '''    if count != 1:
        raise RuntimeError(f"Trecho {label} encontrado {count} vez(es)")
    return source.replace(old, new, 1)
'''
new = '''    if count < 1:
        raise RuntimeError(f"Trecho {label} não encontrado")
    if count > 1 and label != "escopo estrito da tarefa":
        raise RuntimeError(f"Trecho {label} encontrado {count} vez(es)")
    return source.replace(old, new, 1)
'''
if source.count(old) != 1:
    raise RuntimeError("Contrato do patcher principal não reconhecido")
target.write_text(source.replace(old, new, 1), encoding="utf-8")
self_path.unlink()
subprocess.run([sys.executable, str(target)], cwd=root, check=True)
