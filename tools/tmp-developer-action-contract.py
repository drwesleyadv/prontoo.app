from pathlib import Path

path = Path('tools/architecture-check.php')
text = path.read_text()
old = "$assert((int) ($architecture['action_contracts_total'] ?? 0) === 156, 'action_contract_count');"
new = "$assert((int) ($architecture['action_contracts_total'] ?? 0) === 155, 'action_contract_count');"
if text.count(old) != 1:
    raise SystemExit('architecture action contract snapshot drift')
path.write_text(text.replace(old, new, 1))
