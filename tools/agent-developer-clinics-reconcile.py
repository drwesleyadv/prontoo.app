#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import shutil
import subprocess
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

ROOT = Path(__file__).resolve().parents[1]
OLD = "1.8.18.1"
NEW = "1.8.18.2"
BRANCH = "agent/developer-clinics-filters-login-order"


def run(*args: str) -> None:
    subprocess.run(args, cwd=ROOT, check=True)


def replace_exact(path: Path, old: str, new: str, expected: int = 1) -> None:
    source = path.read_text(encoding="utf-8")
    count = source.count(old)
    if count != expected:
        raise SystemExit(f"{path}: esperado {expected} ocorrência(s), encontrado {count}")
    path.write_text(source.replace(old, new), encoding="utf-8")


sql_path = ROOT / "app/Infrastructure/Operational/AdminPagesSqlCatalog08.php"
replace_exact(
    sql_path,
    "SELECT id,display_name,responsible_profession,active,onboarding_done,owner_user_id,manager_user_id,created_at,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents FROM pi_clinics ORDER BY CASE WHEN active=0 THEN 4 WHEN subscription_status='exempt' THEN 3 WHEN subscription_status='read_only' OR (subscription_status<>'active' AND (paid_until IS NULL OR paid_until<CURDATE()) AND (trial_ends_at IS NULL OR trial_ends_at<NOW())) THEN 0 ELSE 2 END ASC, updated_at DESC, id DESC LIMIT 120",
    "SELECT c.id,c.display_name,c.responsible_profession,c.active,c.onboarding_done,c.owner_user_id,c.manager_user_id,c.created_at,c.trial_started_at,c.trial_ends_at,c.subscription_status,c.paid_until,c.monthly_price_cents,ll.last_collaborator_login_at FROM pi_clinics c LEFT JOIN (SELECT ur.clinic_id,MAX(u.last_login_at) AS last_collaborator_login_at FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.active=1 GROUP BY ur.clinic_id) ll ON ll.clinic_id=c.id ORDER BY ll.last_collaborator_login_at IS NULL ASC,ll.last_collaborator_login_at DESC,c.id DESC LIMIT 120",
)

presentation_path = ROOT / "app/Presentation/AdminPages/AdminPagesPresentationOperations03.php"
replace_exact(
    presentation_path,
    '["all", "attention", "onboarding", "readonly", "expiring"]',
    '["all", "active", "expiring", "readonly"]',
    2,
)
replace_exact(
    presentation_path,
    '''        $filterSpecs = [
            "all" => ["Todos", "format_list_bulleted"],
            "attention" => ["Atenção", "priority_high"],
            "onboarding" => ["Onboarding", "playlist_add_check"],
            "readonly" => ["Somente leitura", "lock"],
            "expiring" => ["Vencendo", "event_upcoming"],
        ];''',
    '''        $filterSpecs = [
            "all" => ["Todos", "format_list_bulleted"],
            "active" => ["Ativos", "verified"],
            "expiring" => ["Vencendo", "event_upcoming"],
            "readonly" => ["Somente Leitura", "lock"],
        ];''',
)
replace_exact(
    presentation_path,
    '''        $now = time();
        foreach ($rows as $entry) {''',
    '''        $todayStart = strtotime("today");
        if ($todayStart === false) {
            $todayStart = time();
        }
        $expiringLimit = $todayStart + 10 * 86400 + 86399;
        foreach ($rows as $entry) {''',
)
replace_exact(
    presentation_path,
    '''            $expiresSoon = empty($billing["exempt"]) &&
                $dueTimestamp !== false &&
                $dueTimestamp >= $now &&
                $dueTimestamp <= $now + 7 * 86400;''',
    '''            $expiresSoon = empty($billing["exempt"]) &&
                $dueTimestamp !== false &&
                $dueTimestamp >= $todayStart &&
                $dueTimestamp <= $expiringLimit;''',
)
replace_exact(
    presentation_path,
    '''            $matchesFilter = match ($view) {
                "attention" => $needsAttention,
                "onboarding" => $onboardingPending,
                "readonly" => !empty($billing["read_only"]),
                "expiring" => $expiresSoon,
                default => true,
            };''',
    '''            $isActive = (int) ($r["active"] ?? 0) === 1 && empty($billing["read_only"]);
            $matchesFilter = match ($view) {
                "active" => $isActive,
                "expiring" => $expiresSoon,
                "readonly" => !empty($billing["read_only"]),
                default => true,
            };''',
)

for stem, suffix in [
    ("app-icon", ".png"),
    ("favicon", ".png"),
    ("favicon", ".ico"),
    ("pix", ".svg"),
    ("prontoo-mark", ".png"),
]:
    old_path = ROOT / f"public/assets/{stem}-{OLD}{suffix}"
    new_path = ROOT / f"public/assets/{stem}-{NEW}{suffix}"
    shutil.copyfile(old_path, new_path)
    old_path.unlink()

excluded = {".git", "ssd", "vendor", "node_modules"}
temporary = {
    Path(".github/workflows/agent-developer-clinics-reconcile.yml"),
    Path(".github/workflows/agent-developer-clinics-pr.yml"),
    Path("tools/agent-developer-clinics-reconcile.py"),
}
for path in ROOT.rglob("*"):
    if not path.is_file() or any(part in excluded for part in path.parts):
        continue
    relative = path.relative_to(ROOT)
    if relative in temporary:
        continue
    try:
        source = path.read_text(encoding="utf-8")
    except (UnicodeDecodeError, OSError):
        continue
    if OLD in source:
        path.write_text(source.replace(OLD, NEW), encoding="utf-8")

version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
if version.get("version") != NEW or version.get("release") != NEW:
    raise SystemExit("version.json não convergiu para a versão incremental")
now = datetime.now(ZoneInfo("America/Cuiaba")).replace(microsecond=0)
iso = now.isoformat()
version.update(
    {
        "version": NEW,
        "release": NEW,
        "asset_version": NEW,
        "generated_at_unix": int(now.timestamp()),
        "generated_at": iso,
        "updated_at": iso,
        "build": "1.8.18.2-developer-clinics-login-order",
        "package_type": "incremental_refinement",
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "documentation_changes": False,
        "functional_equivalence_policy": "developer_clinic_directory_filters_and_collaborator_login_recency_order_without_schema_change",
        "notes": "Consultórios do Desenvolvedor usam Todos, Ativos, Vencendo e Somente Leitura; Vencendo cobre até 10 dias e todas as listas seguem o login mais recente dos colaboradores.",
        "deployment_sync_id": "github-prontoo-1.8.18.2-developer-clinics-login-order",
        "deployment_sync_requested_at": iso,
        "release_date": now.date().isoformat(),
        "changelog": {
            "title": "Filtros e ordenação dos consultórios por atividade",
            "items": [
                "reordena os filtros da lista global para Todos, Ativos, Vencendo e Somente Leitura",
                "considera Vencendo os consultórios cuja assinatura termina entre hoje e os próximos 10 dias",
                "ordena todas as listas pelo login mais recente entre colaboradores ativos do consultório, do mais recente para o mais antigo",
                "mantém consultórios sem login de colaborador ao final e não altera schema ou banco de dados",
            ],
        },
    }
)
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

manifest_path = ROOT / "app/Presentation/Styles/styles.manifest.json"
source_root = ROOT / "app/Presentation/Styles"
target = "components/components.css"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
old_source = subprocess.check_output(
    ["git", "show", f"origin/prontoo:app/Presentation/Styles/{target}"], cwd=ROOT
)
new_source = (source_root / target).read_bytes()
if old_source.replace(OLD.encode(), NEW.encode()) != new_source:
    raise SystemExit("components.css contém mudança além da versão dos assets")
source_entry = next((entry for entry in manifest["sources"] if entry["path"] == target), None)
if source_entry is None:
    raise SystemExit("components.css ausente do manifesto")
source_entry["sha256"] = hashlib.sha256(new_source).hexdigest()
source_entry["bytes"] = len(new_source)
source_entry["important_count"] = new_source.count(b"!important")
source_entry["route_scope_count"] = new_source.count(b"body[data-route=")
new_offset = 0
reconstructed = bytearray()
for segment in [item for item in manifest["sequence"] if item["source"] == target]:
    old_offset = int(segment["offset"])
    old_bytes = int(segment["bytes"])
    new_slice = old_source[old_offset : old_offset + old_bytes].replace(OLD.encode(), NEW.encode())
    segment["offset"] = new_offset
    segment["bytes"] = len(new_slice)
    segment["sha256"] = hashlib.sha256(new_slice).hexdigest()
    segment["important_count"] = new_slice.count(b"!important")
    segment["route_scope_count"] = new_slice.count(b"body[data-route=")
    reconstructed.extend(new_slice)
    new_offset += len(new_slice)
if bytes(reconstructed) != new_source:
    raise SystemExit("sequência CSS não reconstrói components.css")
source_data = {entry["path"]: (source_root / entry["path"]).read_bytes() for entry in manifest["sources"]}
built = bytearray()
next_offsets = {path: 0 for path in source_data}
for segment in manifest["sequence"]:
    source = segment["source"]
    offset = int(segment["offset"])
    size = int(segment["bytes"])
    if offset != next_offsets[source]:
        raise SystemExit(f"cobertura CSS descontínua: {source}")
    chunk = source_data[source][offset : offset + size]
    if len(chunk) != size or hashlib.sha256(chunk).hexdigest() != segment["sha256"]:
        raise SystemExit(f"slice CSS divergente: {source}")
    built.extend(chunk)
    next_offsets[source] += size
for source, data in source_data.items():
    if next_offsets[source] != len(data):
        raise SystemExit(f"fonte CSS não integralmente coberta: {source}")
manifest["artifact_contract"]["sha256"] = hashlib.sha256(bytes(built)).hexdigest()
manifest["artifact_contract"]["bytes"] = len(built)
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
(ROOT / manifest["artifact"]).write_bytes(bytes(built))

for relative in temporary:
    path = ROOT / relative
    if path.exists():
        path.unlink()

run("php", "tools/presentation-css-build", "--lint")
run("php", "tools/presentation-css-build", "--check")
run("php", "tools/release-contract-reconcile", "--write")
run("php", "tools/release-contract-reconcile", "--write")
run("php", "tools/release-contract-reconcile", "--check")
run("git", "add", "-A")
run("php", "tools/version-asset-contract-check.php")
run("php", "tools/superseded-reference-contract-check")
run("php", "tools/php84-runtime-contract-check")
run("php", "tools/presentation-visual-contract-check")
run("php", "-l", "app/Infrastructure/Operational/AdminPagesSqlCatalog08.php")
run("php", "-l", "app/Presentation/AdminPages/AdminPagesPresentationOperations03.php")
for stem, suffix in [
    ("app-icon", ".png"),
    ("favicon", ".png"),
    ("favicon", ".ico"),
    ("pix", ".svg"),
    ("prontoo-mark", ".png"),
]:
    old_bytes = subprocess.check_output(
        ["git", "show", f"origin/prontoo:public/assets/{stem}-{OLD}{suffix}"], cwd=ROOT
    )
    new_bytes = (ROOT / f"public/assets/{stem}-{NEW}{suffix}").read_bytes()
    if old_bytes != new_bytes:
        raise SystemExit(f"asset visual alterado indevidamente: {stem}{suffix}")

print(json.dumps({"ok": True, "release": NEW, "branch": BRANCH}, ensure_ascii=False))
