from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import subprocess

NEW_VERSION = "1.8.4.2"
PREVIOUS_VERSION = "1.8.4.1"
BUILD = "1.8.4.2-read-write-half-opacity"
DEPLOYMENT = "github-read-write-half-opacity-1-8-4-2"

now_dt = datetime.now(timezone.utc)
now = now_dt.isoformat()
now_unix = int(now_dt.timestamp())


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    Path(path).write_text(content, encoding="utf-8")


def replace_once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one occurrence, found {count}")
    return source.replace(old, new, 1)


css_path = "public/assets/design-system.css"
css = read(css_path)
count_rules = """
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load,
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{opacity:.5}
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-line-load,
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-line-response{stroke:none}
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-dot-load,
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-dot-response{display:none}
"""
if count_rules.strip() not in css:
    anchor = ".metric-dual-time-chart .metric-chart-hit{fill:transparent;stroke:transparent;cursor:help}\n"
    css = replace_once(css, anchor, anchor + count_rules, "chart css anchor")
write(css_path, css)

architecture_check_path = "tools/architecture-check.php"
architecture_check = read(architecture_check_path)
required_tokens = """        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load,',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{opacity:.5}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-line-load,',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-line-response{stroke:none}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-dot-load,',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-dot-response{display:none}',
"""
if required_tokens.strip() not in architecture_check:
    anchor = "        '.metric-dual-time-chart .metric-chart-line-response',\n"
    architecture_check = replace_once(
        architecture_check,
        anchor,
        anchor + required_tokens,
        "architecture css token anchor",
    )
architecture_check = architecture_check.replace(
    "    '[data-metric-value-type=\"count\"] .metric-chart-fill',\n",
    "    '[data-metric-value-type=\"count\"] .metric-chart-fill{',\n",
)
write(architecture_check_path, architecture_check)

changelog_path = "CHANGELOG.md"
changelog = read(changelog_path)
changelog_entry = """## 1.8.4.2 — Áreas translúcidas em Leitura e gravação

- remove os contornos das séries Requisições e Registros no gráfico Leitura e gravação;
- remove os marcadores finais dessas duas séries para preservar a leitura exclusivamente por área;
- aplica 50% de opacidade aos dois preenchimentos inferiores;
- mantém o gráfico Velocidade com suas linhas e opacidades atuais;
- aplica a mesma composição no Painel do Desenvolvedor e na página pública `/status`;
- não altera banco de dados nem schema.

"""
if changelog_entry.strip() not in changelog:
    changelog = replace_once(
        changelog,
        "# Histórico de versões\n\n",
        "# Histórico de versões\n\n" + changelog_entry,
        "CHANGELOG heading",
    )
write(changelog_path, changelog)

legacy_changelog_path = "ChangeLog.txt"
legacy_changelog = read(legacy_changelog_path)
legacy_entry = """Prontoo 1.8.4.2 — Áreas translúcidas em Leitura e gravação

- As séries Requisições e Registros deixam de exibir contornos e marcadores finais.
- As áreas preenchidas passam a usar 50% de opacidade.
- A alteração vale igualmente para o Painel do Desenvolvedor e para `/status`.
- O gráfico Velocidade permanece inalterado.
- Banco de dados e schema permanecem inalterados.

"""
if legacy_entry.strip() not in legacy_changelog:
    legacy_changelog = legacy_entry + legacy_changelog
write(legacy_changelog_path, legacy_changelog)

prontoo_path = "app/prontoo.php"
prontoo = read(prontoo_path)
prontoo, version_subs = re.subn(
    r'const PRONTOO_VERSION_FALLBACK = "[^"]+";',
    f'const PRONTOO_VERSION_FALLBACK = "{NEW_VERSION}";',
    prontoo,
    count=1,
)
prontoo, previous_subs = re.subn(
    r'const PRONTOO_PREVIOUS_VERSION = "[^"]+";',
    f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS_VERSION}";',
    prontoo,
    count=1,
)
if version_subs != 1 or previous_subs != 1:
    raise RuntimeError("app/prontoo.php version constants not found")
write(prontoo_path, prontoo)

landing_path = "br/index.php"
landing = read(landing_path)
landing, landing_subs = re.subn(
    r'const BR_LANDING_VERSION_FALLBACK = "[^"]+";',
    f'const BR_LANDING_VERSION_FALLBACK = "{NEW_VERSION}";',
    landing,
    count=1,
)
if landing_subs != 1:
    raise RuntimeError("br/index.php version fallback not found")
write(landing_path, landing)

version_path = "version.json"
version = json.loads(read(version_path))
version.update(
    {
        "version": NEW_VERSION,
        "release": NEW_VERSION,
        "generated_at_unix": now_unix,
        "generated_at": now,
        "updated_at": now,
        "build": BUILD,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": False,
        "visual_changes": True,
        "functional_equivalence_policy": "preserves_telemetry_collection_values_and_velocity_chart_while_rendering_read_write_count_areas_at_half_opacity_without_series_outlines_or_terminal_markers",
        "notes": "Requisições e Registros são exibidos somente por áreas com 50% de opacidade no Painel do Desenvolvedor e no status público.",
        "previous_version": PREVIOUS_VERSION,
        "documentation_changes": True,
        "deployment_sync_id": DEPLOYMENT,
        "deployment_sync_requested_at": now,
        "rewrite_scope": "read_write_chart_half_opacity_without_series_outlines",
    }
)
write(version_path, json.dumps(version, ensure_ascii=False, indent=4) + "\n")

architecture_manifest_path = "app/architecture.manifest.json"
architecture_manifest = json.loads(read(architecture_manifest_path))
architecture_manifest.update(
    {
        "version": NEW_VERSION,
        "release": NEW_VERSION,
        "build": BUILD,
        "generated_at": now,
        "updated_at": now,
        "database_changes": False,
        "schema_changes": False,
        "visual_changes": True,
        "previous_version": PREVIOUS_VERSION,
        "deployment_sync_id": DEPLOYMENT,
        "notes": "Leitura e gravação usa áreas a 50% de opacidade sem contornos ou marcadores finais, de forma compartilhada entre o painel e o status.",
        "documentation_changes": True,
        "read_write_chart_visual_policy": "count_series_areas_half_opacity_without_lines_or_terminal_markers_shared_by_developer_panel_and_public_status",
    }
)
if "logic_changes" in architecture_manifest:
    architecture_manifest["logic_changes"] = False
write(
    architecture_manifest_path,
    json.dumps(architecture_manifest, ensure_ascii=False, indent=4) + "\n",
)

update_manifest_path = "app/update.manifest.json"
update_manifest = json.loads(read(update_manifest_path))
update_manifest.update(
    {
        "version": NEW_VERSION,
        "release": NEW_VERSION,
        "build": BUILD,
        "generated_at": now,
        "updated_at": now,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": False,
        "visual_changes": True,
        "documentation_changes": True,
        "previous_version": PREVIOUS_VERSION,
        "deployment_sync_id": DEPLOYMENT,
        "notes": "Leitura e gravação passa a exibir Requisições e Registros sem contornos e com áreas a 50% de opacidade.",
    }
)

for temporary in (
    ".github/workflows/chart-read-write-inspect.yml",
    ".github/workflows/chart-read-write-executor.yml",
    "tools/chart-read-write-inspection.txt",
    "tools/chart-read-write-inspect-trigger.txt",
    "tools/chart-read-write-execute-trigger.txt",
    "tools/chart-read-write-release.py",
):
    path = Path(temporary)
    if path.exists():
        path.unlink()

tracked = subprocess.check_output(["git", "ls-files", "-z"]).split(b"\0")
files = {}
total = 0
for raw in tracked:
    if not raw:
        continue
    relative = raw.decode("utf-8")
    if relative == update_manifest_path:
        continue
    if relative.startswith((".git/", "ssd/", "vendor/", "node_modules/")):
        continue
    path = Path(relative)
    if not path.is_file() or path.is_symlink():
        continue
    data = path.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
update_manifest["files"] = dict(sorted(files.items()))
update_manifest["file_count"] = len(files)
update_manifest["total_uncompressed_bytes"] = total
write(
    update_manifest_path,
    json.dumps(update_manifest, ensure_ascii=False, indent=4) + "\n",
)

css_final = read(css_path)
for token in (
    '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{opacity:.5}',
    '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-line-response{stroke:none}',
    '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-dot-response{display:none}',
):
    if token not in css_final:
        raise RuntimeError("missing final chart rule: " + token)

print(NEW_VERSION)
