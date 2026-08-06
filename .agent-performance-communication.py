from __future__ import annotations

import hashlib
import json
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent
VERSION = "1.8.6.3"
PREVIOUS = "1.8.6.2"
BUILD = "1.8.6.3-performance-communication-30d"
SYNC = "github-prontoo-1.8.6.3-performance-communication-30d"
SELF = ROOT / ".agent-performance-communication.py"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, value: str) -> None:
    (ROOT / path).write_text(value, encoding="utf-8")


def once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: {count}")
    return source.replace(old, new, 1)


def mutate(source: str, name: str, next_name: str | None, pairs: list[tuple[str, str]]) -> str:
    start = source.find(f"function {name}(")
    end = len(source) if next_name is None else source.find(f"function {next_name}(", start + 1)
    if start < 0 or end <= start:
        raise RuntimeError(f"Função não localizada: {name}")
    block = source[start:end]
    for old, new in pairs:
        block = once(block, old, new, f"{name}:{old}")
    return source[:start] + block + source[end:]


admin = read("app/Admin/AdminPages.php")
admin = mutate(admin, "admin_global_sequence_series_20d", "admin_maestro_health_time_label", [
    ("function admin_global_sequence_series_20d(", "function admin_global_sequence_series_30d("),
    ("for ($i = 19; $i >= 0; $i--)", "for ($i = 29; $i >= 0; $i--)"),
    ('"label" => $day->format("d")', '"label" => telemetry_day_axis_label($day)'),
    ("(19 - $i)", "(29 - $i)"),
])
admin = mutate(admin, "admin_global_perf_charts_html", "admin_performance_card_content_html", [
    ('$requests = telemetry_route_requests_series_20d();', '$views = telemetry_route_requests_series_30d();'),
    ('$records = admin_global_sequence_series_20d();', '$records = admin_global_sequence_series_30d();'),
    ('"Velocidade",', '"Últimas 24 horas",'),
    ('"secondary_label" => "Landing Page"', '"secondary_label" => "Landing"'),
    ('"Tempo médio das rotas nos últimos 5 minutos"', '"Velocidade média nos últimos 5 minutos"'),
    ('"Tempo médio das rotas nos últimos 30 minutos"', '"Velocidade média nos últimos 30 minutos"'),
    ('"Tempo médio das rotas nas últimas 24 horas"', '"Velocidade média nas últimas 24 horas"'),
    ('"dados de duração de rotas e da Landing Page."', '"dados de velocidade das rotas e da Landing."'),
    ('"Leitura e gravação",', '"Últimos 30 dias",'),
    ('$requests,', '$views,'),
    ('"primary_label" => "Requisições"', '"primary_label" => "Visualizações"'),
    ('"Requisições de hoje"', '"Visualizações de hoje"'),
    ('"Média diária de requisições nos últimos 7 dias"', '"Média diária de visualizações nos últimos 7 dias"'),
    ('"Média diária de requisições nos últimos 20 dias"', '"Média diária de visualizações nos últimos 30 dias"'),
    ('"dados de Requisições e Registros dos últimos 20 dias."', '"dados de Visualizações e Registros dos últimos 30 dias, incluindo hoje."'),
])
admin = once(admin, '<h2>Telemetria das últimas 24 horas</h2>', '<h2>Performance</h2>', "título Performance")
admin = once(admin, '"Requisições",', '"Visualizações",', "card Visualizações")
admin = once(admin, '"Tempo médio das rotas",', '"Velocidade média",', "card Velocidade média")
admin = once(admin, '"Execuções da Landing Page",', '"Visualizações da Landing",', "card Landing")
write("app/Admin/AdminPages.php", admin)

telemetry = read("app/Support/Telemetry.php")
telemetry = mutate(telemetry, "telemetry_retention_microseconds", "telemetry_comparison_microseconds", [
    ("20 * 86400 * 1000000", "31 * 86400 * 1000000"),
])
timezone = '''function telemetry_cuiaba_tz(): DateTimeZone
{
    static $timezone = null;
    return $timezone ??= new DateTimeZone("America/Cuiaba");
}
'''
telemetry = once(telemetry, timezone, timezone + '''
function telemetry_day_axis_label(DateTimeImmutable $day): string
{
    $weekdays = ["D", "S", "T", "Q", "Q", "S", "S"];
    return $weekdays[(int) $day->format("w")] . $day->format("d");
}
''', "helper SDD")
telemetry = mutate(telemetry, "telemetry_route_requests_series_20d", "telemetry_sequence_records_series_20d", [
    ("function telemetry_route_requests_series_20d(", "function telemetry_route_requests_series_30d("),
    ("for ($offset = 19; $offset >= 0; $offset--)", "for ($offset = 29; $offset >= 0; $offset--)"),
    ('"label" => $day->format("d/m")', '"label" => telemetry_day_axis_label($day)'),
])
telemetry = mutate(telemetry, "telemetry_sequence_records_series_20d", None, [
    ("function telemetry_sequence_records_series_20d(", "function telemetry_sequence_records_series_30d("),
    ("for ($i = 19; $i >= 0; $i--)", "for ($i = 29; $i >= 0; $i--)"),
    ('"label" => $day->format("d")', '"label" => telemetry_day_axis_label($day)'),
    ("(19 - $i)", "(29 - $i)"),
])
write("app/Support/Telemetry.php", telemetry)

auth = read("app/Auth/AuthOnboarding.php")
for old, new in [
    ("telemetry_route_requests_series_20d", "telemetry_route_requests_series_30d"),
    ("telemetry_sequence_records_series_20d", "telemetry_sequence_records_series_30d"),
]:
    if old not in auth:
        raise RuntimeError(f"Auth sem token: {old}")
    auth = auth.replace(old, new)
write("app/Auth/AuthOnboarding.php", auth)

check = read("tools/architecture-check.php")
for old, new in [
    ("20 * 86400 * 1000000", "31 * 86400 * 1000000"),
    ("telemetry_route_requests_series_20d", "telemetry_route_requests_series_30d"),
    ("telemetry_sequence_records_series_20d", "telemetry_sequence_records_series_30d"),
    ("admin_global_sequence_series_20d", "admin_global_sequence_series_30d"),
    ('"Requisições"', '"Visualizações"'),
    ('"Tempo médio das rotas"', '"Velocidade média"'),
    ('"Execuções da Landing Page"', '"Visualizações da Landing"'),
    ('"Leitura e gravação"', '"Últimos 30 dias"'),
    ('"primary_label" => "Requisições"', '"primary_label" => "Visualizações"'),
    ('"Velocidade",', '"Últimas 24 horas",'),
    ('$requests = telemetry_route_requests_series_30d();', '$views = telemetry_route_requests_series_30d();'),
    ("        '$requests,',", "        '$views,',"),
    ("canonical-route-telemetry-velocity-and-ledger-write-series-20d", "canonical-page-view-velocity-and-ledger-write-series-30d"),
]:
    if old not in check:
        raise RuntimeError(f"Check sem token: {old}")
    check = check.replace(old, new)
check = once(check, "'function telemetry_page_response_candidate(',\n", "'function telemetry_page_response_candidate(',\n    'function telemetry_day_axis_label(',\n", "check helper")
check = once(check, "'function telemetry_route_requests_series_30d(',\n", "'function telemetry_route_requests_series_30d(',\n    'for ($offset = 29; $offset >= 0; $offset--)',\n    '\"label\" => telemetry_day_axis_label($day)',\n", "check 30d")
write("tools/architecture-check.php", check)

app = read("app/prontoo.php")
app = re.sub(r'const PRONTOO_VERSION_FALLBACK = ["\'][^"\']+["\'];', f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";', app, count=1)
app = re.sub(r'const PRONTOO_PREVIOUS_VERSION = ["\'][^"\']+["\'];', f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS}";', app, count=1)
app = once(app, "canonical-route-telemetry-velocity-and-ledger-write-series-20d", "canonical-page-view-velocity-and-ledger-write-series-30d", "bootstrap 30d")
write("app/prontoo.php", app)

landing = read("br/index.php")
landing = re.sub(r'const BR_LANDING_VERSION_FALLBACK = ["\'][^"\']+["\'];', f'const BR_LANDING_VERSION_FALLBACK = "{VERSION}";', landing, count=1)
write("br/index.php", landing)

now = datetime.now(timezone.utc)
iso = now.isoformat()
unix = int(now.timestamp())
version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "version": VERSION, "release": VERSION, "build": BUILD,
    "generated_at_unix": unix, "generated_at": iso, "updated_at": iso,
    "database_changes": False, "schema_changes": False,
    "logic_changes": True, "visual_changes": True, "documentation_changes": True,
    "previous_version": PREVIOUS, "deployment_sync_id": SYNC,
    "deployment_sync_requested_at": iso, "release_date": "2026-08-06",
    "functional_equivalence_policy": "performance-copy-unified-and-thirty-calendar-day-series",
    "rewrite_scope": "performance_communication_shared_renderer_thirty_day_daily_series_and_supporting_retention",
    "notes": "Performance unificada entre Status e Painel do Desenvolvedor, com Visualizações, Velocidade média e 30 dias incluindo hoje.",
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

arch_path = ROOT / "app/architecture.manifest.json"
arch = json.loads(arch_path.read_text(encoding="utf-8"))
arch.update({
    "version": VERSION, "release": VERSION, "build": BUILD,
    "generated_at": iso, "updated_at": iso,
    "database_changes": False, "schema_changes": False,
    "logic_changes": True, "visual_changes": True, "documentation_changes": True,
    "previous_version": PREVIOUS, "deployment_sync_id": SYNC,
    "server_evolution_phase_three_policy": "every_completed_html_page_navigation_is_measured_once_and_retained_for_thirty_one_days",
    "route_telemetry_source_policy": "ssd_telemetry_page_loads_jsonl_is_the_only_current_source",
    "developer_dashboard_observability_policy": "status_and_developer_panel_share_ten_day_kpis_twenty_four_hour_velocity_and_thirty_day_views_and_writes",
    "performance_communication_policy": "visualizacoes_velocidade_media_visualizacoes_da_landing_performance_24h_30d",
    "performance_daily_series_policy": "thirty_civil_days_including_today_with_weekday_initial_and_two_digit_day_axis_labels",
})
arch_path.write_text(json.dumps(arch, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

changelog_path = ROOT / "CHANGELOG.md"
changelog = changelog_path.read_text(encoding="utf-8")
entry = '''## 1.8.6.3 — Comunicação de Performance e série de 30 dias

- consolida Visualizações, Velocidade média e Visualizações da Landing;
- renomeia os blocos para Performance, Últimas 24 horas e Últimos 30 dias;
- mantém Status e Painel do Desenvolvedor no mesmo renderer;
- amplia Visualizações e Registros para 30 dias civis, incluindo hoje;
- apresenta o eixo diário no formato `SDD` e mantém a data completa no tooltip;
- amplia a retenção técnica para 31 dias, sem alterar banco ou schema.

'''
head = "# Histórico de versões\n\n"
if "## 1.8.6.3 —" not in changelog:
    changelog = once(changelog, head, head + entry, "CHANGELOG")
changelog_path.write_text(changelog, encoding="utf-8")

if SELF.exists():
    SELF.unlink()

manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": VERSION, "release": VERSION, "build": BUILD,
    "generated_at": iso, "generated_at_unix": unix, "updated_at": iso,
    "database_changes": False, "schema_changes": False,
    "logic_changes": True, "visual_changes": True, "documentation_changes": True,
    "previous_version": PREVIOUS, "deployment_sync_id": SYNC,
    "functional_equivalence_policy": "performance-copy-unified-and-thirty-calendar-day-series",
    "notes": "Performance compartilhada com nomenclatura consolidada e séries diárias de 30 dias incluindo hoje.",
})
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=ROOT).split(b"\0")
files: dict[str, str] = {}
total = 0
for raw in tracked:
    if not raw:
        continue
    relative = raw.decode("utf-8")
    if relative in {"app/update.manifest.json", ".agent-performance-communication.py"}:
        continue
    path = ROOT / relative
    if not path.is_file():
        continue
    data = path.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["files"] = dict(sorted(files.items()))
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

for path in ["app/Admin/AdminPages.php", "app/Support/Telemetry.php", "app/Auth/AuthOnboarding.php"]:
    source = read(path)
    if "series_20d" in source:
        raise RuntimeError(f"Série legada em {path}")
print(json.dumps({"ok": True, "version": VERSION, "days": 30, "retention": 31, "files": len(files)}, ensure_ascii=False))
