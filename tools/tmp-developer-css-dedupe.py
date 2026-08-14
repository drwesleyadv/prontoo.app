from pathlib import Path

path = Path('public/assets/presentation.css')
text = path.read_text()
marker = '/* Developer control center v1 */'
if marker not in text:
    raise SystemExit('developer CSS marker missing')
head, tail = text.split(marker, 1)
replacements = {
    'body.scope-global .developer-control-status{flex-direction:column;gap:14px}': 'body.scope-global main .developer-control-status{flex-direction:column;gap:14px}',
    'body.scope-global .developer-state-badge{align-self:flex-start}': 'body.scope-global main .developer-state-badge{align-self:flex-start}',
}
for old, new in replacements.items():
    if old not in tail:
        raise SystemExit(f'expected responsive selector missing: {old}')
    tail = tail.replace(old, new, 1)
path.write_text(head + marker + tail)
