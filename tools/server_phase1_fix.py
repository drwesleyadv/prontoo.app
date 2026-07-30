from pathlib import Path

path = Path(__file__).with_name("server_phase1_apply.py")
source = path.read_text(encoding="utf-8")
old = '''marker = "$result = [\\n"
if source.count(marker) != 1:
    raise SystemExit("Marcador final do architecture-check.php divergente")'''
new = '''marker = "$result = [\\n"
marker_index = source.rfind(marker)
if marker_index < 0:
    raise SystemExit("Marcador final do architecture-check.php divergente")'''
if old not in source:
    raise SystemExit("Bloco de marcador não localizado")
source = source.replace(old, new, 1)
old = "source = source.replace(marker, block + marker, 1)"
new = "source = source[:marker_index] + block + source[marker_index:]"
if old not in source:
    raise SystemExit("Aplicação do bloco não localizada")
path.write_text(source.replace(old, new, 1), encoding="utf-8")
