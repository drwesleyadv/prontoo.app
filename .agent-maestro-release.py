from __future__ import annotations

import hashlib
import json
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent
VERSION = "1.8.5.1"
PREVIOUS_VERSION = "1.8.4.9"
BUILD = "1.8.5.1-maestro-supervised-server-cycle"
SYNC_ID = "github-prontoo-1.8.5.1-maestro-supervised-server-cycle"
FRAGMENT = ROOT / ".agent-maestro-supervisor.fragment"
WORKFLOW = ROOT / ".github/workflows/agent-maestro-release.yml"
SCRIPT = ROOT / ".agent-maestro-release.py"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(source: str, pattern: str, replacement: str, label: str) -> str:
    updated, count = re.subn(pattern, replacement, source, count=1, flags=re.MULTILINE)
    if count != 1:
        raise RuntimeError(f"Substituição não confirmada: {label}")
    return updated


maestro_path = ROOT / "app/Domain/Maestro/Maestro.php"
maestro_source = maestro_path.read_text(encoding="utf-8")
fragment = FRAGMENT.read_text(encoding="utf-8").rstrip() + "\n\n"
if "function maestro_supervised_cron_run(" not in maestro_source:
    anchor = "function maestro_cron_run("
    if anchor not in maestro_source:
        raise RuntimeError("Âncora do executor Maestro não encontrada.")
    maestro_source = maestro_source.replace(anchor, fragment + anchor, 1)
    maestro_path.write_text(maestro_source, encoding="utf-8")

app_source = read("app/prontoo.php")
app_source = replace_once(
    app_source,
    r'const PRONTOO_VERSION_FALLBACK = ["\'][^"\']+["\'];',
    f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";',
    "PRONTOO_VERSION_FALLBACK",
)
app_source = replace_once(
    app_source,
    r'const PRONTOO_PREVIOUS_VERSION = ["\'][^"\']+["\'];',
    f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS_VERSION}";',
    "PRONTOO_PREVIOUS_VERSION",
)
write("app/prontoo.php", app_source)

landing = read("br/index.php")
landing = replace_once(
    landing,
    r'const BR_LANDING_VERSION_FALLBACK = ["\'][^"\']+["\'];',
    f'const BR_LANDING_VERSION_FALLBACK = "{VERSION}";',
    "BR_LANDING_VERSION_FALLBACK",
)
write("br/index.php", landing)

now = datetime.now(timezone.utc)
now_iso = now.isoformat()
now_unix = int(now.timestamp())
version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "generated_at": now_iso,
    "generated_at_unix": now_unix,
    "release_date": "2026-08-05",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "previous_version": PREVIOUS_VERSION,
    "deployment_sync_id": SYNC_ID,
    "functional_equivalence_policy": "maestro-supervised-server-cycle-no-client-trigger",
})
version_path.write_text(
    json.dumps(version, ensure_ascii=False, indent=4, sort_keys=False) + "\n",
    encoding="utf-8",
)

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture["version"] = VERSION
if "generated_at" in architecture:
    architecture["generated_at"] = now_iso
architecture_path.write_text(
    json.dumps(architecture, ensure_ascii=False, indent=4, sort_keys=False) + "\n",
    encoding="utf-8",
)

changelog_path = ROOT / "CHANGELOG.md"
changelog = changelog_path.read_text(encoding="utf-8")
entry = """## 1.8.5.1 — Ciclo supervisionado do Maestro

- separa saúde de regras, auditoria diferida, integridade, preflight e manutenção;
- torna o preflight somente leitura e remove reparos administrativos do ciclo cron;
- preserva o escopo do consultório em auditorias diferidas e elimina a expiração automática em 24 horas;
- aplica backoff, fila morta por falha atual e distinção entre atenção histórica e erro do ciclo;
- retoma ações em erro, valida destinatários em modo fail-closed e calcula janelas no fuso do consultório;
- aplica rodízio entre consultórios, invalidação seletiva de cache e estado operacional pai em JSON;
- mantém execução exclusivamente servidor-side e o intervalo de dez minutos;
- não altera banco de dados, schema ou assets públicos.

"""
if "## 1.8.5.1 —" not in changelog:
    heading = "# Histórico de versões\n\n"
    if not changelog.startswith(heading):
        raise RuntimeError("Cabeçalho do CHANGELOG.md não reconhecido.")
    changelog = heading + entry + changelog[len(heading):]
    changelog_path.write_text(changelog, encoding="utf-8")

for transient in (FRAGMENT, WORKFLOW, SCRIPT):
    if transient.exists():
        transient.unlink()

manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "schema_revision": version["schema_revision"],
    "build": BUILD,
    "package_type": version["package_type"],
    "generated_at": now_iso,
    "generated_at_unix": now_unix,
    "version_format": version["version_format"],
    "minimum_php": version["minimum_php"],
    "minimum_mysql": version["minimum_mysql"],
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "previous_version": PREVIOUS_VERSION,
    "deployment_sync_id": SYNC_ID,
    "functional_equivalence_policy": "maestro-supervised-server-cycle-no-client-trigger",
})
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=ROOT).split(b"\0")
files: dict[str, str] = {}
total = 0
for raw in tracked:
    if not raw:
        continue
    relative = raw.decode("utf-8")
    if relative == "app/update.manifest.json":
        continue
    absolute = ROOT / relative
    if not absolute.is_file():
        continue
    data = absolute.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["files"] = dict(sorted(files.items()))
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4, sort_keys=False) + "\n",
    encoding="utf-8",
)

print(json.dumps({
    "ok": True,
    "version": VERSION,
    "files": len(files),
    "bytes": total,
    "maestro_supervisor_inserted": "function maestro_supervised_cron_run(" in maestro_source,
}, ensure_ascii=False))
