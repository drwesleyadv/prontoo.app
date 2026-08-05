from __future__ import annotations

import hashlib
import json
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent
VERSION = "1.8.5.2"
PREVIOUS = "1.8.5.1"
BUILD = "1.8.5.2-php84-exclusive-runtime"
SYNC_ID = "github-prontoo-1.8.5.2-php84-exclusive-runtime"
SCRIPT = ROOT / ".agent-php84-release.py"
WORKFLOW = ROOT / ".github/workflows/agent-php84-release.yml"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"Contrato não reconhecido para {label}: {count}")
    return source.replace(old, new, 1)


app = read("app/prontoo.php")
app = replace_once(
    app,
    "<?php\ndeclare(strict_types=1);\nif (!defined(\"PRONTOO_ROOT\")) {",
    "<?php\ndeclare(strict_types=1);\nrequire_once __DIR__ . \"/Support/Php84Runtime.php\";\nif (!defined(\"PRONTOO_ROOT\")) {",
    "guarda central",
)
app = re.sub(
    r'const PRONTOO_VERSION_FALLBACK = ["\x27][^"\x27]+["\x27];',
    f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";',
    app,
    count=1,
)
app = re.sub(
    r'const PRONTOO_PREVIOUS_VERSION = ["\x27][^"\x27]+["\x27];',
    f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS}";',
    app,
    count=1,
)
write("app/prontoo.php", app)

landing = read("br/index.php")
landing = replace_once(
    landing,
    "<?php\ndeclare(strict_types=1);\n$brRouteStartedMonotonicNs",
    "<?php\ndeclare(strict_types=1);\nrequire_once dirname(__DIR__) . \"/app/Support/Php84Runtime.php\";\n$brRouteStartedMonotonicNs",
    "guarda landing",
)
landing = re.sub(
    r'const BR_LANDING_VERSION_FALLBACK = ["\x27][^"\x27]+["\x27];',
    f'const BR_LANDING_VERSION_FALLBACK = "{VERSION}";',
    landing,
    count=1,
)
write("br/index.php", landing)

cron = read("cron/maestro.php")
cron = replace_once(
    cron,
    '''if (PHP_SAPI !== "cli") {
    http_response_code(403);
    echo "CLI only\\n";
    exit(1);
}

$__prontooCronRoot''',
    '''if (PHP_SAPI !== "cli") {
    http_response_code(403);
    echo "CLI only\\n";
    exit(1);
}
require_once dirname(__DIR__) . "/app/Support/Php84Runtime.php";

$__prontooCronRoot''',
    "guarda cron",
)
cron = re.sub(
    r'\nif \(version_compare\(PHP_VERSION, "8\.4\.0", "<"\)\) \{.*?\n\}\n\ndefine\("PRONTOO_CRON", true\);',
    '\n\ndefine("PRONTOO_CRON", true);',
    cron,
    count=1,
    flags=re.DOTALL,
)
write("cron/maestro.php", cron)

architecture_workflow = read(".github/workflows/architecture.yml")
needle = "          php tools/version-asset-contract-check.php\n"
replacement = needle + "          php tools/php84-runtime-contract-check.php\n"
architecture_workflow = replace_once(
    architecture_workflow,
    needle,
    replacement,
    "workflow de arquitetura",
)
write(".github/workflows/architecture.yml", architecture_workflow)

now = datetime.now(timezone.utc)
now_iso = now.isoformat()
now_unix = int(now.timestamp())

version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "version": VERSION,
    "release": VERSION,
    "generated_at": now_iso,
    "generated_at_unix": now_unix,
    "updated_at": now_iso,
    "build": BUILD,
    "minimum_php": "8.4.0",
    "required_php_family": "8.4",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "previous_version": PREVIOUS,
    "deployment_sync_id": SYNC_ID,
    "deployment_sync_requested_at": now_iso,
    "functional_equivalence_policy": "php84-exclusive-web-cli-runtime-no-client-change",
    "notes": "Runtime web e CLI limitado exclusivamente à família PHP 8.4, com auditoria permanente do handler, entradas e workflows.",
    "rewrite_scope": "php84_exclusive_runtime_handler_entrypoints_cli_and_ci_contract",
    "release_date": "2026-08-05",
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "generated_at": now_iso,
    "updated_at": now_iso,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "previous_version": PREVIOUS,
    "deployment_sync_id": SYNC_ID,
    "php_runtime_policy": "exact_php_8_4_family_web_cli_ci_fail_closed",
    "notes": "Contrato de runtime PHP 8.4 aplicado antes do bootstrap e auditado no CI.",
})
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

changelog_path = ROOT / "CHANGELOG.md"
changelog = changelog_path.read_text(encoding="utf-8")
entry = """## 1.8.5.2 — Runtime exclusivo PHP 8.4

- fixa o handler público da Hostoo em `ea-php84` para `.php`, `.php8` e `.phtml`;
- bloqueia, antes do bootstrap, qualquer runtime cuja família não seja exatamente PHP 8.4;
- aplica o mesmo contrato à aplicação, landing page, instalador por encadeamento e cron CLI;
- valida metadados de versão, workflows e documentação operacional por teste permanente;
- substitui a política anterior de versão mínima por família exclusiva, impedindo execução acidental em PHP 8.5 ou superior;
- não altera banco de dados, schema, layout ou assets públicos.

"""
heading = "# Histórico de versões\n\n"
if not changelog.startswith(heading):
    raise RuntimeError("Cabeçalho do CHANGELOG.md não reconhecido")
if "## 1.8.5.2 —" not in changelog:
    changelog_path.write_text(heading + entry + changelog[len(heading):], encoding="utf-8")

for transient in (SCRIPT, WORKFLOW):
    if transient.exists():
        transient.unlink()

manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "generated_at": now_iso,
    "generated_at_unix": now_unix,
    "updated_at": now_iso,
    "minimum_php": "8.4.0",
    "required_php_family": "8.4",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "previous_version": PREVIOUS,
    "deployment_sync_id": SYNC_ID,
    "functional_equivalence_policy": "php84-exclusive-web-cli-runtime-no-client-change",
    "notes": "Runtime web, cron e CI limitado exclusivamente à família PHP 8.4.",
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
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

print(json.dumps({"ok": True, "version": VERSION, "tracked_files": len(files)}, ensure_ascii=False))
