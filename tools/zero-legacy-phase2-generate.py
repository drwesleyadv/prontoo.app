from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
path = root / 'version.json'
version = json.loads(path.read_text())
migrations = dict(version.get('php84_baseline_path_migrations', {}))
source = 'app/Core/Invariant/Request/ActionProof.php'
target = 'app/Runtime/LayeredKernel.php'
if source in migrations and migrations[source] != target:
    raise SystemExit('ActionProof PHP 8.4 migration already points elsewhere')
migrations[source] = target
version['php84_baseline_path_migrations'] = migrations
path.write_text(json.dumps(version, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')
