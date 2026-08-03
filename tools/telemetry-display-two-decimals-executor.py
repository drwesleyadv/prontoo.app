from __future__ import annotations

import hashlib
import json
import os
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.8.3.5"
PREVIOUS = "1.8.3.4"
BUILD = "1.8.3.5-telemetry-display-two-decimals"
SYNC_ID = "github-telemetry-display-two-decimals-1-8-3-5"
ORIGINAL_WORKFLOW_COMMIT = "39178054c957b38ab6ebb70857563635826ea8a9"
HEAD_BRANCH = os.environ.get("HEAD_BRANCH", "").strip()


def replace_once(path: str, old: str, new: str) -> None:
    target = ROOT / path
    text = target.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise RuntimeError(
            f"{path}: substituição esperava 1 ocorrência e encontrou {count}"
        )
    target.write_text(text.replace(old, new, 1), encoding="utf-8")


def run(*args: str) -> None:
    subprocess.run(args, cwd=ROOT, check=True)


def apply_changes() -> None:
    now = datetime.now(timezone.utc)
    now_iso = now.isoformat(timespec="microseconds")
    now_unix = int(now.timestamp())

    replace_once(
        "app/Admin/AdminPages.php",
        'return number_format(max(0.0, $milliseconds), 6, ",", ".") . " ms";',
        'return number_format(max(0.0, $milliseconds), 2, ",", ".") . " ms";',
    )
    replace_once(
        "app/Admin/AdminPages.php",
        'return number_format(max(0.0, $ms), 6, ",", ".") . " ms";',
        'return number_format(max(0.0, $ms), 2, ",", ".") . " ms";',
    )
    replace_once(
        "tools/architecture-check.php",
        "        'telemetry_route_performance_summary(240)',\n",
        "        'telemetry_route_performance_summary(240)',\n"
        "        'return number_format(max(0.0, $milliseconds), 2, \\",\\\", \\\".\\\") . \\\" ms\\\";',\n"
        "        'return number_format(max(0.0, $ms), 2, \\",\\\", \\\".\\\") . \\\" ms\\\";',\n",
    )

    changelog = ROOT / "CHANGELOG.md"
    changelog_text = changelog.read_text(encoding="utf-8")
    header = "# Histórico de versões\n\n"
    if not changelog_text.startswith(header):
        raise RuntimeError("Cabeçalho inesperado em CHANGELOG.md")
    entry = (
        "## 1.8.3.5 — Tempos médios com duas casas decimais\n\n"
        "- limita a apresentação dos tempos médios de telemetria a duas casas decimais;\n"
        "- aplica o padrão aos cards, indicadores e tooltips dos gráficos de velocidade;\n"
        "- preserva a coleta e os cálculos internos em nanossegundos, sem reduzir a precisão da fonte;\n"
        "- não altera banco de dados nem schema.\n\n"
    )
    changelog.write_text(
        header + entry + changelog_text[len(header) :], encoding="utf-8"
    )

    changelog_txt = ROOT / "ChangeLog.txt"
    old_txt = changelog_txt.read_text(encoding="utf-8")
    entry_txt = (
        "Prontoo 1.8.3.5 — Tempos médios com duas casas decimais\n\n"
        "- Os tempos médios passam a ser exibidos com duas casas decimais.\n"
        "- Cards, indicadores e tooltips usam o mesmo padrão visual.\n"
        "- A telemetria mantém sua precisão interna em nanossegundos.\n"
        "- Banco de dados e schema permanecem inalterados.\n\n"
    )
    changelog_txt.write_text(entry_txt + old_txt, encoding="utf-8")

    replace_once(
        "app/prontoo.php",
        'const PRONTOO_VERSION_FALLBACK = "1.8.3.4";',
        'const PRONTOO_VERSION_FALLBACK = "1.8.3.5";',
    )
    replace_once(
        "app/prontoo.php",
        'const PRONTOO_PREVIOUS_VERSION = "1.8.3.3";',
        'const PRONTOO_PREVIOUS_VERSION = "1.8.3.4";',
    )
    replace_once(
        "br/index.php",
        'const BR_LANDING_VERSION_FALLBACK = "1.8.3.4";',
        'const BR_LANDING_VERSION_FALLBACK = "1.8.3.5";',
    )

    version_path = ROOT / "version.json"
    version_data = json.loads(version_path.read_text(encoding="utf-8"))
    version_data.update(
        {
            "version": VERSION,
            "release": VERSION,
            "generated_at_unix": now_unix,
            "generated_at": now_iso,
            "updated_at": now_iso,
            "build": BUILD,
            "database_changes": False,
            "schema_changes": False,
            "logic_changes": False,
            "visual_changes": True,
            "functional_equivalence_policy": "preserves_telemetry_collection_and_weighted_calculations_while_formatting_average_duration_outputs_to_two_decimal_places",
            "notes": "Os tempos médios de telemetria são exibidos com duas casas decimais, mantendo a precisão interna.",
            "previous_version": PREVIOUS,
            "documentation_changes": True,
            "deployment_sync_id": SYNC_ID,
            "deployment_sync_requested_at": now_iso,
            "rewrite_scope": "telemetry_average_duration_display_precision",
        }
    )
    version_path.write_text(
        json.dumps(version_data, ensure_ascii=False, indent=4) + "\n",
        encoding="utf-8",
    )

    architecture_path = ROOT / "app/architecture.manifest.json"
    architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
    architecture.update(
        {
            "version": VERSION,
            "release": VERSION,
            "build": BUILD,
            "generated_at": now_iso,
            "updated_at": now_iso,
            "previous_version": PREVIOUS,
            "deployment_sync_id": SYNC_ID,
            "notes": "A apresentação de tempos médios usa duas casas decimais sem alterar a precisão matemática da telemetria.",
        }
    )
    architecture_path.write_text(
        json.dumps(architecture, ensure_ascii=False, indent=4) + "\n",
        encoding="utf-8",
    )

    original_workflow = subprocess.run(
        [
            "git",
            "show",
            f"{ORIGINAL_WORKFLOW_COMMIT}:.github/workflows/architecture.yml",
        ],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
    ).stdout
    (ROOT / ".github/workflows/architecture.yml").write_text(
        original_workflow, encoding="utf-8"
    )
    (ROOT / ".github/workflows/telemetry-display-two-decimals-v2.yml").unlink(
        missing_ok=True
    )
    Path(__file__).unlink(missing_ok=True)

    manifest_path = ROOT / "app/update.manifest.json"
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    manifest.update(
        {
            "version": VERSION,
            "release": VERSION,
            "build": BUILD,
            "generated_at": now_iso,
            "generated_at_unix": now_unix,
            "database_changes": False,
            "schema_changes": False,
            "logic_changes": False,
            "visual_changes": True,
            "documentation_changes": True,
            "previous_version": PREVIOUS,
            "updated_at": now_iso,
            "notes": "Os tempos médios são apresentados com duas casas decimais, preservando os cálculos internos.",
            "deployment_sync_id": SYNC_ID,
            "deployment_sync_requested_at": now_iso,
            "rewrite_scope": "telemetry_average_duration_display_precision",
            "functional_equivalence_policy": "preserves_telemetry_collection_and_weighted_calculations_while_formatting_average_duration_outputs_to_two_decimal_places",
        }
    )
    files = dict(manifest.get("files") or {})
    files.pop(".github/workflows/telemetry-display-two-decimals-v2.yml", None)
    files.pop("tools/telemetry-display-two-decimals-executor.py", None)
    missing = [relative for relative in files if not (ROOT / relative).is_file()]
    if missing:
        raise RuntimeError("Arquivos ausentes no manifesto: " + ", ".join(missing))
    refreshed: dict[str, str] = {}
    total_bytes = 0
    for relative in sorted(files):
        data = (ROOT / relative).read_bytes()
        refreshed[relative] = hashlib.sha256(data).hexdigest()
        total_bytes += len(data)
    manifest["files"] = refreshed
    manifest["file_count"] = len(refreshed)
    manifest["total_uncompressed_bytes"] = total_bytes
    manifest_path.write_text(
        json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
        encoding="utf-8",
    )


def validate() -> None:
    excluded = {".git", "ssd", "vendor", "node_modules"}
    for file in ROOT.rglob("*.php"):
        if any(part in excluded for part in file.parts):
            continue
        run("php", "-l", str(file))

    admin = (ROOT / "app/Admin/AdminPages.php").read_text(encoding="utf-8")
    required = [
        'return number_format(max(0.0, $milliseconds), 2, ",", ".") . " ms";',
        'return number_format(max(0.0, $ms), 2, ",", ".") . " ms";',
        '? round(($row["sum"] / $row["count"]) / 1000000, 6)',
        'return round(($durationNs / $weightedCount) / 1000000, 6);',
    ]
    forbidden = [
        'return number_format(max(0.0, $milliseconds), 6, ",", ".") . " ms";',
        'return number_format(max(0.0, $ms), 6, ",", ".") . " ms";',
    ]
    for token in required:
        if token not in admin:
            raise RuntimeError("Contrato ausente na precisão da telemetria: " + token)
    for token in forbidden:
        if token in admin:
            raise RuntimeError("Formatação antiga com seis casas permanece: " + token)

    for command in [
        ("php", "tools/documentation-check.php"),
        ("php", "tools/code-comment-check.php"),
        ("php", "tools/security-regression-check.php"),
        ("php", "tools/architecture-check.php"),
        ("php", "tools/schema-check.php"),
        ("php", "tools/install-security-check.php"),
        ("php", "tools/install-window-contract-check"),
        ("git", "diff", "--check"),
    ]:
        run(*command)


def publish() -> None:
    if HEAD_BRANCH != "agent/telemetry-display-two-decimals-20260803":
        raise RuntimeError("Branch funcional divergente.")
    run("git", "config", "user.name", "github-actions[bot]")
    run(
        "git",
        "config",
        "user.email",
        "41898282+github-actions[bot]@users.noreply.github.com",
    )
    run("git", "add", "-A")
    run("git", "commit", "-m", "Exibir tempos médios com duas casas decimais")
    run("git", "push", "origin", f"HEAD:{HEAD_BRANCH}")


if __name__ == "__main__":
    apply_changes()
    validate()
    publish()
