from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

VERSION = "1.7.30.15"
PREVIOUS_VERSION = "1.7.30.14"
BUILD = "1.7.30.15-public-status-bordered-count-fills"


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    Path(path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one occurrence, found {count}")
    return content.replace(old, new, 1)


admin_path = "app/Admin/AdminPages.php"
admin = read(admin_path)
admin = replace_once(
    admin,
    """    return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-ds-card="admin-dual-area-chart" aria-label="' .""",
    """    return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-metric-value-type="' .
        e($valueType) .
        '" data-ds-card="admin-dual-area-chart" aria-label="' .""",
    "chart value type attribute",
)
admin = replace_once(admin, "function page_stats(): void", "function page_status(): void", "public page function")
admin = replace_once(admin, "https://prontoo.app/stats", "https://prontoo.app/status", "public canonical url")
admin = replace_once(
    admin,
    'class="public scope-global stats-public"',
    'class="public scope-global status-public"',
    "public body class",
)
admin = replace_once(admin, 'data-route="stats"', 'data-route="status"', "public route attribute")
write(admin_path, admin)

runner_path = "app/Runtime/Runner.php"
runner = read(runner_path)
for old, new, label in [
    ('        "stats",\n        "login",', '        "status",\n        "login",', "route map"),
    ('        "stats",\n        "login",', '        "status",\n        "login",', "public route list"),
    ('        ["mobile_web_access", "signup", "stats"],', '        ["mobile_web_access", "signup", "status"],', "public light route"),
    ('        $publicStats = $r === "stats";', '        $publicStatus = $r === "status";', "public route flag"),
    ('        headers_secure($publicStats);', '        headers_secure($publicStatus);', "public headers"),
    ('        $cNow = $publicStats || $publicHome || $r === "logout" ? [] : ctx();', '        $cNow = $publicStatus || $publicHome || $r === "logout" ? [] : ctx();', "public empty context"),
    ('            !$publicStats &&', '            !$publicStatus &&', "public maintenance bypass"),
    ('        if ($r !== "logout" && !$publicStats) {', '        if ($r !== "logout" && !$publicStatus) {', "public integrity bypass"),
]:
    runner = replace_once(runner, old, new, label)
write(runner_path, runner)

loader_path = "app/Support/ModuleLoader.php"
loader = read(loader_path)
loader = replace_once(
    loader,
    "return ['stats', 'login', 'login_autotest', 'mfa', 'mobile_web_access', 'signup', 'logout'];",
    "return ['status', 'login', 'login_autotest', 'mfa', 'mobile_web_access', 'signup', 'logout'];",
    "public light loader route",
)
loader = replace_once(
    loader,
    "$stats = ['stats' => ['Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']];",
    "$status = ['status' => ['Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']];",
    "status module group",
)
loader = replace_once(loader, "        'stats' => $stats,", "        'status' => $status,", "status module map")
write(loader_path, loader)

htaccess_path = ".htaccess"
htaccess = read(htaccess_path)
htaccess = replace_once(
    htaccess,
    "    RewriteRule ^stats/?$ index.php?r=stats [QSA,L,NC]",
    "    RewriteRule ^status/?$ index.php?r=status [QSA,L,NC]",
    "status rewrite",
)
write(htaccess_path, htaccess)

css_path = "public/assets/design-system.css"
css = read(css_path)
css = replace_once(css, 'body[data-route="stats"]', 'body[data-route="status"]', "status route css one")
css = replace_once(css, 'body[data-route="stats"]', 'body[data-route="status"]', "status route css two")
css = replace_once(css, 'body[data-route="stats"]', 'body[data-route="status"]', "status route css three")
line_anchor = ".metric-dual-time-chart .metric-chart-dot-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%)}"
line_insert = line_anchor + "\n.metric-dual-time-chart[data-metric-value-type=\"count\"] .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:.3}\n.metric-dual-time-chart[data-metric-value-type=\"count\"] .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:.42}"
css = replace_once(css, line_anchor, line_insert, "count fill colors")
old_public_css = """body.stats-public{margin:0!important;padding:0!important;}
body.stats-public #conteudo{width:95vw!important;max-width:none!important;margin:0 auto!important;padding:0!important;}
body.stats-public .admin-performance-card{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:transparent!important;}
body.stats-public .admin-performance-card>.section-head{margin:0!important;padding:0!important;border:0!important;}
body.stats-public .global-performance-charts{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;gap:0!important;}
body.stats-public .global-performance-charts>.metric-area-chart{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:transparent!important;}
body.stats-public .global-performance-charts>.metric-area-chart svg{display:block;width:100%!important;max-width:none!important;}"""
new_public_css = """body.status-public{margin:0!important;padding:0!important;background:var(--md-sys-color-surface,#f7faf8)!important;}
body.status-public #conteudo{width:95vw!important;max-width:none!important;margin:0 auto!important;padding:clamp(14px,2.5vw,32px)!important;}
body.status-public .admin-performance-card{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:1px solid var(--md-sys-color-outline-variant)!important;border-radius:24px!important;box-shadow:var(--md-sys-elevation-level1)!important;background:var(--md-sys-color-surface-container-lowest)!important;overflow:hidden!important;}
body.status-public .admin-performance-card>.section-head{margin:0!important;padding:18px 20px!important;border:0!important;border-bottom:1px solid var(--md-sys-color-outline-variant)!important;}
body.status-public .global-performance-charts{width:auto!important;max-width:none!important;margin:16px!important;padding:16px!important;gap:16px!important;border:1px solid var(--md-sys-color-outline-variant)!important;border-radius:20px!important;background:var(--md-sys-color-surface-container-low)!important;}
body.status-public .global-performance-charts>.metric-area-chart{width:100%!important;max-width:none!important;margin:0!important;padding:16px!important;border:1px solid var(--md-sys-color-outline-variant)!important;border-radius:16px!important;box-shadow:none!important;background:var(--md-sys-color-surface-container-lowest)!important;}
body.status-public .global-performance-charts>.metric-area-chart svg{display:block;width:100%!important;max-width:none!important;}
@media(max-width:760px){body.status-public #conteudo{width:100%!important;padding:10px!important;}body.status-public .admin-performance-card{border-radius:18px!important;}body.status-public .admin-performance-card>.section-head{padding:15px!important;}body.status-public .global-performance-charts{margin:10px!important;padding:10px!important;gap:10px!important;border-radius:16px!important;}body.status-public .global-performance-charts>.metric-area-chart{padding:12px!important;border-radius:14px!important;}}"""
css = replace_once(css, old_public_css, new_public_css, "public bordered layout")
write(css_path, css)

check_path = "tools/architecture-check.php"
check = read(check_path)
for old, new in [
    ("function page_stats(): void", "function page_status(): void"),
    ("https://prontoo.app/stats", "https://prontoo.app/status"),
    ("$publicStats", "$publicStatus"),
    ("$stats = [\\'stats\\' => [\\'Domain/Maestro/Maestro.php\\', \\'Admin/AdminPages.php\\']]", "$status = [\\'status\\' => [\\'Domain/Maestro/Maestro.php\\', \\'Admin/AdminPages.php\\']]"),
    ("\\'stats\\' => $stats", "\\'status\\' => $status"),
    ("RewriteRule ^stats/?$ index.php?r=stats [QSA,L,NC]", "RewriteRule ^status/?$ index.php?r=status [QSA,L,NC]"),
    ("$publicStatsStart", "$publicStatusStart"),
    ("$publicStatsEnd", "$publicStatusEnd"),
    ("$publicStatsSource", "$publicStatusSource"),
    ("public_stats_page_boundary", "public_status_page_boundary"),
    ("public_stats_forbidden_token", "public_status_forbidden_token"),
    ("$publicStatsForbiddenToken", "$publicStatusForbiddenToken"),
    ("$statsWideLayoutCss", "$statusPublicLayoutCss"),
    ("$statsWideLayoutFailures", "$statusPublicLayoutFailures"),
    ("$statsWideLayoutToken", "$statusPublicLayoutToken"),
    ("Stats wide layout contract failed", "Status public layout contract failed"),
    ("body.stats-public", "body.status-public"),
]:
    check = check.replace(old, new)
check = replace_once(
    check,
    """        '.metric-dual-time-chart .metric-chart-line-response',
    ],""",
    """        '.metric-dual-time-chart .metric-chart-line-response',
        '.metric-dual-time-chart[data-metric-value-type=\"count\"] .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:.3}',
        '.metric-dual-time-chart[data-metric-value-type=\"count\"] .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:.42}',
    ],""",
    "count fill contract tokens",
)
old_layout_contract = """foreach ([
    'body.status-public #conteudo{width:95vw!important;max-width:none!important;margin:0 auto!important;padding:0!important;}',
    'body.status-public .admin-performance-card{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:transparent!important;}',
    'body.status-public .global-performance-charts{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;gap:0!important;}',
    'body.status-public .global-performance-charts>.metric-area-chart{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:transparent!important;}',
] as $statusPublicLayoutToken) {"""
new_layout_contract = """foreach ([
    'body.status-public #conteudo{width:95vw!important;max-width:none!important;margin:0 auto!important;padding:clamp(14px,2.5vw,32px)!important;}',
    'body.status-public .admin-performance-card{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border:1px solid var(--md-sys-color-outline-variant)!important;border-radius:24px!important;',
    'body.status-public .admin-performance-card>.section-head{margin:0!important;padding:18px 20px!important;border:0!important;border-bottom:1px solid var(--md-sys-color-outline-variant)!important;}',
    'body.status-public .global-performance-charts{width:auto!important;max-width:none!important;margin:16px!important;padding:16px!important;gap:16px!important;border:1px solid var(--md-sys-color-outline-variant)!important;',
    'body.status-public .global-performance-charts>.metric-area-chart{width:100%!important;max-width:none!important;margin:0!important;padding:16px!important;border:1px solid var(--md-sys-color-outline-variant)!important;',
] as $statusPublicLayoutToken) {"""
check = replace_once(check, old_layout_contract, new_layout_contract, "status layout contract")
check = replace_once(
    check,
    """foreach ([
    'function admin_metric_dual_count_chart(',""",
    """foreach ([
    'function page_stats(): void',
    'https://prontoo.app/stats',
    'stats-public',
    'data-route=\"stats\"',
    'RewriteRule ^stats/?$ index.php?r=stats',
    'function admin_metric_dual_count_chart(',""",
    "obsolete stats tokens",
)
write(check_path, check)

version_path = Path("version.json")
version_data = json.loads(version_path.read_text(encoding="utf-8"))
now = datetime.now(timezone.utc)
now_iso = now.isoformat()
version_data.update(
    {
        "version": VERSION,
        "release": VERSION,
        "generated_at_unix": int(now.timestamp()),
        "generated_at": now_iso,
        "updated_at": now_iso,
        "build": BUILD,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "functional_equivalence_policy": "preserves_dashboard_metrics_uses_one_shared_chart_renderer_and_exposes_only_the_read_only_performance_card_at_public_status",
        "notes": "A página pública passa a /status com superfícies delimitadas; no gráfico Leitura e gravação, cada área usa a mesma cor-base de sua linha.",
        "previous_version": PREVIOUS_VERSION,
        "deployment_sync_id": "github-public-status-bordered-count-fills-1-7-30-15",
        "deployment_sync_requested_at": now_iso,
        "rewrite_scope": "developer_dashboard_shared_renderer_public_read_only_status_card_bordered_layout_and_count_fill_line_color_parity",
    }
)
version_path.write_text(json.dumps(version_data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

architecture_path = Path("app/architecture.manifest.json")
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update(
    {
        "version": VERSION,
        "release": VERSION,
        "build": BUILD,
        "generated_at": now_iso,
        "updated_at": now_iso,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_two_charts_one_shared_renderer_count_averages_include_zero_days_count_fills_match_line_colors_and_public_read_only_status_card_without_authenticated_context",
        "public_status_policy": "get_only_prontoo_app_status_renders_exclusively_admin_performance_card_without_auth_context_post_or_schema_mutation",
        "public_status_layout_policy": "viewport_95_percent_bordered_outer_card_chart_group_and_chart_surfaces_public_only",
    }
)
architecture.pop("public_stats_policy", None)
architecture.pop("public_stats_layout_policy", None)
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

manifest_path = Path("app/update.manifest.json")
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update(
    {
        "version": VERSION,
        "release": VERSION,
        "build": BUILD,
        "generated_at_unix": int(now.timestamp()),
        "generated_at": now_iso,
        "updated_at": now_iso,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "previous_version": PREVIOUS_VERSION,
        "notes": "A rota pública passa de /stats para /status, recupera bordas coerentes e alinha as cores-base das áreas de Leitura e gravação às respectivas linhas.",
        "deployment_sync_id": "github-public-status-bordered-count-fills-1-7-30-15",
    }
)
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

app = read("app/prontoo.php")
app = replace_once(app, 'const PRONTOO_VERSION_FALLBACK = "1.7.30.14";', 'const PRONTOO_VERSION_FALLBACK = "1.7.30.15";', "app version fallback")
write("app/prontoo.php", app)

landing = read("br/index.php")
landing = replace_once(landing, 'const BR_LANDING_VERSION_FALLBACK = "1.7.30.14";', 'const BR_LANDING_VERSION_FALLBACK = "1.7.30.15";', "landing version fallback")
write("br/index.php", landing)

changelog = read("CHANGELOG.md")
entry = """# Histórico de versões

## 1.7.30.15 — Status público com bordas e cores correspondentes

- renomeia a página pública de `/stats` para `/status`;
- restaura bordas no card externo, no agrupamento dos gráficos e em cada gráfico público;
- mantém 95% da largura da viewport com espaçamento responsivo;
- aplica às áreas de Requisições e Registros a mesma cor-base de suas respectivas linhas;
- preserva o gráfico Velocidade, as métricas, o banco e o schema.

"""
changelog = replace_once(changelog, "# Histórico de versões\n\n", entry, "markdown changelog")
write("CHANGELOG.md", changelog)

plain = read("ChangeLog.txt")
plain_entry = """Prontoo 1.7.30.15 — Status público com bordas e cores correspondentes

- Página pública renomeada de /stats para /status.
- Card, agrupamento e gráficos públicos recuperam bordas coerentes.
- Áreas de Requisições e Registros usam a mesma cor-base de suas linhas.
- Banco e schema permanecem inalterados.

"""
write("ChangeLog.txt", plain_entry + plain)

print(json.dumps({"ok": True, "version": VERSION, "build": BUILD}, ensure_ascii=False))
