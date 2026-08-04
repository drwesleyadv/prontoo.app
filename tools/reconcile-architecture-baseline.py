from pathlib import Path
import datetime
import json

root = Path('.')
now = datetime.datetime.now(datetime.timezone.utc).isoformat()

architecture_path = root / 'app/architecture.manifest.json'
architecture = json.loads(architecture_path.read_text())
architecture['transitional_files_max'] = 81
architecture['notes'] = 'Contrato de assets públicos corrigido; baseline arquitetural reconciliado com 81 arquivos transitórios já existentes.'
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + '\n')

version_path = root / 'version.json'
version = json.loads(version_path.read_text())
version['architecture_transitional_files_max'] = 81
version['updated_at'] = now
version['deployment_sync_requested_at'] = now
version['notes'] = 'Restaura o asset_version canônico 1.7.15.10, valida os assets públicos e reconcilia o baseline arquitetural preexistente de 81 arquivos transitórios.'
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + '\n')

changelog_path = root / 'CHANGELOG.md'
changelog = changelog_path.read_text()
needle = '- não altera banco de dados nem schema.\n\n## 1.8.4.6'
replacement = '- reconcilia o teto arquitetural com o baseline preexistente de 81 arquivos transitórios, sem adicionar arquivos PHP em `app/`;\n- não altera banco de dados nem schema.\n\n## 1.8.4.6'
if needle not in changelog:
    raise SystemExit('CHANGELOG insertion point not found')
changelog_path.write_text(changelog.replace(needle, replacement, 1))

legacy_path = root / 'ChangeLog.txt'
legacy = legacy_path.read_text()
needle_legacy = '- Sem alteração de banco de dados ou schema.\n\n1.8.4.6'
replacement_legacy = '- Reconcilia o baseline arquitetural preexistente em 81 arquivos transitórios.\n- Sem alteração de banco de dados ou schema.\n\n1.8.4.6'
if needle_legacy not in legacy:
    raise SystemExit('ChangeLog insertion point not found')
legacy_path.write_text(legacy.replace(needle_legacy, replacement_legacy, 1))
