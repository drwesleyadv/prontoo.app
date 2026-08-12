from pathlib import Path

path = Path('tools/tmp-pagehead-primitive.py')
source = path.read_text()
old = ':where\\(a,button,summary\\)'
new = ':is\\(a,button,summary\\)'
count = source.count(old)
if count != 2:
    raise SystemExit(f'expected 2 bridge selector anchors, found {count}')
path.write_text(source.replace(old, new))
Path(__file__).unlink()
