from pathlib import Path
import runpy
import subprocess

root = Path(__file__).resolve().parents[1]
runpy.run_path(str(Path(__file__).with_name('zero-legacy-final-v2.py')), run_name='__main__')
for path in root.rglob('*.php'):
    rel = path.relative_to(root).as_posix()
    if any(part in rel for part in ('.git/', 'ssd/', 'vendor/', 'node_modules/')):
        continue
    source = path.read_text()
    normalized = source.replace('\\\\Prontoo\\Core\\Architecture\\OperationGateway', '\\Prontoo\\Core\\Architecture\\OperationGateway')
    if normalized != source:
        path.write_text(normalized)
    result = subprocess.run(['php', '-l', str(path)], text=True, capture_output=True)
    if result.returncode != 0:
        print(f'GENERATED_PHP_SYNTAX_ERROR={rel}')
        print(result.stdout)
        print(result.stderr)
        raise SystemExit(result.returncode)
