from pathlib import Path
import hashlib
import json

root = Path('.')
artifact_path = root / 'public/assets/presentation.css'
source_path = root / 'app/Presentation/Styles/components/components.css'
manifest_path = root / 'app/Presentation/Styles/styles.manifest.json'
marker = '/* Developer control center v1 */'

artifact = artifact_path.read_text()
if marker not in artifact:
    raise SystemExit('developer artifact CSS marker missing')
_, tail = artifact.split(marker, 1)
block = marker + tail

source = source_path.read_text()
if marker in source:
    raise SystemExit('developer source CSS marker already present')
source += block
source_path.write_text(source)

manifest = json.loads(manifest_path.read_text())
source_entry = next((row for row in manifest['sources'] if row.get('path') == 'components/components.css'), None)
if source_entry is None:
    raise SystemExit('components source manifest entry missing')
source_bytes = source.encode()
source_entry['sha256'] = hashlib.sha256(source_bytes).hexdigest()
source_entry['bytes'] = len(source_bytes)
source_entry['important_count'] = source.count('!important')
source_entry['route_scope_count'] = source.count('body[data-route=')

last = manifest['sequence'][-1]
if last.get('order') != len(manifest['sequence']) or last.get('source') != 'components/components.css':
    raise SystemExit('final CSS sequence owner drifted')
offset = int(last['offset'])
slice_bytes = source_bytes[offset:]
last['bytes'] = len(slice_bytes)
last['sha256'] = hashlib.sha256(slice_bytes).hexdigest()
last['important_count'] = slice_bytes.count(b'!important')
last['route_scope_count'] = slice_bytes.count(b'body[data-route=')

source_map = {}
for row in manifest['sources']:
    source_map[row['path']] = (root / 'app/Presentation/Styles' / row['path']).read_bytes()
built = bytearray()
for segment in manifest['sequence']:
    data = source_map[segment['source']]
    start = int(segment['offset'])
    length = int(segment['bytes'])
    built.extend(data[start:start + length])
built_bytes = bytes(built)
manifest['artifact_contract']['sha256'] = hashlib.sha256(built_bytes).hexdigest()
manifest['artifact_contract']['bytes'] = len(built_bytes)
manifest['artifact_contract']['lines'] = built_bytes.count(b'\n') + 1
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + '\n')
