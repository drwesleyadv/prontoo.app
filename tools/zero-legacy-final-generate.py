from pathlib import Path
import json
import runpy
import subprocess

root = Path(__file__).resolve().parents[1]
runpy.run_path(str(Path(__file__).with_name('zero-legacy-final-v2.py')), run_name='__main__')

version_path = root / 'version.json'
version = json.loads(version_path.read_text())
migrations = version.setdefault('architecture_source_path_migrations', {})
for source, target in {
    'app/Auth/AuthOnboarding.php': 'app/Presentation/AuthOnboarding/AdminChoiceCardOperation.php',
    'app/Admin/AdminPages.php': 'app/Presentation/AdminPages/AdminChoiceCardOperation.php',
}.items():
    current = migrations.get(source, [])
    targets = current if isinstance(current, list) else [current]
    targets = [str(item) for item in targets if str(item)]
    if target not in targets:
        targets.append(target)
    migrations[source] = targets[0] if len(targets) == 1 else targets
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

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
