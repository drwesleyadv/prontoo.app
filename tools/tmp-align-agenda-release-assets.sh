#!/usr/bin/env bash
set -euo pipefail

OLD='1.9.16.1'
NEW='1.9.16.2'

python3 - <<'PY'
import json
from pathlib import Path
path = Path('version.json')
data = json.loads(path.read_text())
data['asset_version'] = '1.9.16.2'
path.write_text(json.dumps(data, ensure_ascii=False, indent=4) + '\n')
PY

for path in public/assets/*"$OLD"*; do
  [ -e "$path" ] || continue
  target="${path//$OLD/$NEW}"
  git mv "$path" "$target"
done

while IFS= read -r -d '' file; do
  python3 - "$file" "$OLD" "$NEW" <<'PY'
from pathlib import Path
import sys
path = Path(sys.argv[1])
old, new = sys.argv[2], sys.argv[3]
try:
    text = path.read_text()
except (UnicodeDecodeError, OSError):
    raise SystemExit(0)
if old in text:
    path.write_text(text.replace(old, new))
PY
done < <(git grep -Il -z "$OLD" -- ':!public/assets/*.png' ':!public/assets/*.ico' || true)

rm -f .github/workflows/align-agenda-release-assets.yml tools/tmp-align-agenda-release-assets.sh

php tools/release-contract-reconcile --write
php tools/release-contract-reconcile --write
php tools/release-contract-reconcile --check
php tools/release-version
php tools/version-asset-contract-check.php
php tools/superseded-reference-contract-check
node --check public/assets/app.js
php tools/test-fast
php tools/quality-gate --fast
git diff --check

git config user.name 'github-actions[bot]'
git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
git add -A
git commit -m 'Alinha assets à release 1.9.16.2'
git push origin HEAD:fix/agenda-blocked-day-rendering
