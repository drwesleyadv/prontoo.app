from pathlib import Path

p = Path('tools/timed-note-apply.py')
s = p.read_text()
old = "    agenda_block + '            if ($act === \\\"confirm\\\") {',"
if old not in s:
    old = "    agenda_block + '            if ($act === \"confirm\") {',"
new = "    lambda _match: agenda_block + '            if ($act === \"confirm\") {',"
if old not in s:
    raise SystemExit('replacement marker not found')
p.write_text(s.replace(old, new, 1))
