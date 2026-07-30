from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.13"
BUILD = "1.7.30.13-shared-speed-chart-renderer-public-stats"
DEPLOYMENT_ID = "github-shared-speed-chart-renderer-public-stats-1-7-30-13"
NOW = datetime.now(timezone.utc).replace(microsecond=0)
NOW_ISO = NOW.isoformat()
NOW_UNIX = int(NOW.timestamp())


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one occurrence, found {count}")
    return content.replace(old, new, 1)


admin_path = "app/Admin/AdminPages.php"
admin = read(admin_path)
maestro_start = admin.index("function admin_maestro_health_pill_html(")
maestro_end = admin.index("function admin_global_perf_charts_html(): string", maestro_start)
maestro = admin[maestro_start:maestro_end]
maestro = replace_once(
    maestro,
    "function admin_maestro_health_pill_html(): string",
    "function admin_maestro_health_pill_html(bool $allowSchemaEnsure = true): string",
    "maestro health signature",
)
maestro = replace_once(
    maestro,
    '''        if (has_cfg()) {
            maestro_ensure_schema();
            $hasSuccess = db_column_exists("pi_maestro_job_runs", "success");''',
    '''        if (has_cfg()) {
            $schemaReady = true;
            if ($allowSchemaEnsure) {
                maestro_ensure_schema();
            } elseif (function_exists("db_table_exists")) {
                $schemaReady = db_table_exists("pi_maestro_job_runs");
            }
            if ($schemaReady) {
                $hasSuccess = db_column_exists("pi_maestro_job_runs", "success");''',
    "public maestro read-only gate",
)
close_anchor = '''            }
        }
    } catch (Throwable $e) {'''
maestro = replace_once(
    maestro,
    close_anchor,
    '''            }
            }
        }
    } catch (Throwable $e) {''',
    "maestro schema gate close",
)
admin = admin[:maestro_start] + maestro + admin[maestro_end:]

helper_anchor = '''function page_admin_operations(): void
{'''
helpers = '''function admin_performance_card_content_html(bool $public = false): string
{
    return '<div class="section-head admin-performance-head"><h2>Desempenho geral</h2>' .
        admin_maestro_health_pill_html(!$public) .
        "</div>" .
        admin_global_perf_charts_html();
}
function admin_performance_card_html(bool $public = false): string
{
    return card(
        admin_performance_card_content_html($public),
        "admin-performance-card",
    );
}
function page_stats(): void
{
    if (strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")) !== "GET") {
        throw new ProntooHttpError(405, "Método não permitido.");
    }
    if (!headers_sent()) {
        header("Content-Type: text/html; charset=utf-8");
        header("Cache-Control: public, max-age=60, stale-while-revalidate=60");
        header("X-Robots-Tag: noindex, nofollow");
    }
    $assetRevision = defined("PRONTOO_ASSET_REV")
        ? (string) PRONTOO_ASSET_REV
        : (string) PRONTOO_VERSION;
    $card = admin_performance_card_html(true);
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="canonical" href="https://prontoo.app/stats"><title>Status · Prontoo</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#238763"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="prontoo-version" content="' .
        e(PRONTOO_VERSION) .
        '"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/png" href="/public/assets/favicon-' .
        rawurlencode($assetRevision) .
        '.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,-25..200&display=swap" rel="stylesheet"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
        rawurlencode($assetRevision) .
        '&release=' .
        rawurlencode(PRONTOO_VERSION) .
        '"><script defer src="/public/assets/app.js?v=' .
        rawurlencode($assetRevision) .
        '&release=' .
        rawurlencode(PRONTOO_VERSION) .
        '"></script></head><body class="public scope-global stats-public" style="--clinic-accent:#238763;--clinic-accent-dark:#105e44;--clinic-accent-soft:#dff3ea;--clinic-on-accent:#ffffff;" data-route="stats" data-app-version="' .
        e(PRONTOO_VERSION) .
        '"><main id="conteudo" tabindex="-1">' .
        $card .
        "</main></body></html>";
}
'''
admin = replace_once(admin, helper_anchor, helpers + helper_anchor, "public stats helpers")
admin = replace_once(
    admin,
    '''    $charts = card(
        '<div class="section-head admin-performance-head"><h2>Desempenho geral</h2>' .
            admin_maestro_health_pill_html() .
            "</div>" .
            admin_global_perf_charts_html(),
        "admin-performance-card",
    );''',
    '''    $charts = admin_performance_card_html();''',
    "admin card helper reuse",
)
write(admin_path, admin)

runner_path = "app/Runtime/Runner.php"
runner = read(runner_path)
runner = replace_once(
    runner,
    '''        "home",
        "login",''',
    '''        "home",
        "stats",
        "login",''',
    "stats route map",
)
runner = replace_once(
    runner,
    '''        "login",
        "login_autotest",
        "mfa",''',
    '''        "stats",
        "login",
        "login_autotest",
        "mfa",''',
    "stats public route",
)
runner = replace_once(
    runner,
    '''        ["mobile_web_access", "signup"],''',
    '''        ["mobile_web_access", "signup", "stats"],''',
    "stats public light route",
)
runner = replace_once(
    runner,
    '''        $r = route();
        $publicHome = false;
        headers_secure(false);''',
    '''        $r = route();
        $publicStats = $r === "stats";
        $publicHome = false;
        headers_secure($publicStats);''',
    "stats public runtime flag",
)
runner = replace_once(
    runner,
    '''        $cNow = $publicHome || $r === "logout" ? [] : ctx();''',
    '''        $cNow = $publicStats || $publicHome || $r === "logout" ? [] : ctx();''',
    "stats empty context",
)
runner = replace_once(
    runner,
    '''            !$publicHome &&
            function_exists("maintenance_active")''',
    '''            !$publicStats &&
            !$publicHome &&
            function_exists("maintenance_active")''',
    "stats maintenance independence",
)
runner = replace_once(
    runner,
    '''        if ($r !== "logout") {
            prontoo_flush_integrity_before_render();
        }''',
    '''        if ($r !== "logout" && !$publicStats) {
            prontoo_flush_integrity_before_render();
        }''',
    "stats no integrity flush",
)
write(runner_path, runner)

loader_path = "app/Support/ModuleLoader.php"
loader = read(loader_path)
loader = replace_once(
    loader,
    "return ['login', 'login_autotest', 'mfa', 'mobile_web_access', 'signup', 'logout'];",
    "return ['stats', 'login', 'login_autotest', 'mfa', 'mobile_web_access', 'signup', 'logout'];",
    "stats loader public light",
)
loader = replace_once(
    loader,
    '''    $admin = ['admin' => ['Domain/Leads/Leads.php', 'Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']];
    $map = [''',
    '''    $admin = ['admin' => ['Domain/Leads/Leads.php', 'Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']];
    $stats = ['stats' => ['Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']];
    $map = [''',
    "stats module group",
)
loader = replace_once(
    loader,
    '''        'home' => $commonClinic + $appointments + $tasks + $financial + $patients + $leads,
        'painel' =>''',
    '''        'home' => $commonClinic + $appointments + $tasks + $financial + $patients + $leads,
        'stats' => $stats,
        'painel' =>''',
    "stats module map",
)
write(loader_path, loader)

htaccess_path = ".htaccess"
htaccess = read(htaccess_path)
htaccess = replace_once(
    htaccess,
    '''    RewriteRule ^llms\.txt$ br/index.php?asset=llms [QSA,L,NC]
    RewriteRule ^pdfs/''',
    '''    RewriteRule ^llms\.txt$ br/index.php?asset=llms [QSA,L,NC]
    RewriteRule ^stats/?$ index.php?r=stats [QSA,L,NC]
    RewriteRule ^pdfs/''',
    "public stats rewrite",
)
write(htaccess_path, htaccess)

css_path = "public/assets/design-system.css"
css = read(css_path)
css_anchor = '''body.scope-global .global-performance-charts > .metric-area-chart,
body.scope-global .global-area-charts > .metric-area-chart{
  grid-column:1 / -1!important;
  width:100%!important;
  max-width:none!important;
}
'''
css_add = css_anchor + '''body[data-route="stats"]{min-height:100vh;background:var(--md-sys-color-surface,#f7faf8);}
body[data-route="stats"] main{width:min(100%,1180px);margin:0 auto;padding:clamp(16px,4vw,48px);}
body[data-route="stats"] .admin-performance-card{margin:0;}
'''
css = replace_once(css, css_anchor, css_add, "public stats layout")
write(css_path, css)

check_path = "tools/architecture-check.php"
check = read(check_path)
check = replace_once(
    check,
    '''    'docs' => (string) file_get_contents($root . '/docs/index.md'),
];''',
    '''    'docs' => (string) file_get_contents($root . '/docs/index.md'),
    'htaccess' => (string) file_get_contents($root . '/.htaccess'),
];''',
    "architecture htaccess source",
)
check = replace_once(
    check,
    '''        'function admin_metric_dual_area_chart(',
        'array $presentation = []',''',
    '''        'function admin_metric_dual_area_chart(',
        'array $presentation = []',
        'function admin_performance_card_content_html(bool $public = false): string',
        'function admin_performance_card_html(bool $public = false): string',
        'function page_stats(): void',
        'admin_performance_card_html(true)',
        'https://prontoo.app/stats',''',
    "architecture public stats admin tokens",
)
check = replace_once(
    check,
    '''    'bootstrap' => [
        'dual-area-single-renderer-identical-visuals-data-specific-labels-one-row',
    ],''',
    '''    'bootstrap' => [
        'dual-area-single-renderer-identical-visuals-data-specific-labels-one-row',
    ],
    'runner' => [
        '$publicStats = $r === "stats"',
        'headers_secure($publicStats)',
        '$cNow = $publicStats || $publicHome || $r === "logout" ? [] : ctx()',
        'if ($r !== "logout" && !$publicStats)',
    ],
    'loader' => [
        "$stats = ['stats' => ['Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']]",
        "'stats' => $stats",
    ],
    'htaccess' => [
        'RewriteRule ^stats/?$ index.php?r=stats [QSA,L,NC]',
    ],''',
    "architecture stats routing tokens",
)
check = replace_once(
    check,
    '''if (substr_count(
    $developerDashboardSources['admin'],
    'admin_metric_dual_area_chart(',
) < 3) {''',
    '''$publicStatsStart = strpos(
    $developerDashboardSources['admin'],
    'function page_stats(): void',
);
$publicStatsEnd = $publicStatsStart === false
    ? false
    : strpos(
        $developerDashboardSources['admin'],
        'function page_admin_operations(): void',
        $publicStatsStart,
    );
$publicStatsSource =
    $publicStatsStart !== false && $publicStatsEnd !== false
        ? substr(
            $developerDashboardSources['admin'],
            $publicStatsStart,
            $publicStatsEnd - $publicStatsStart,
        )
        : '';
if ($publicStatsSource === '') {
    $developerDashboardFailures[] = 'public_stats_page_boundary';
} else {
    foreach ([
        'require_can(',
        'ctx(',
        '$_POST',
        'INSERT ',
        'UPDATE ',
        'DELETE ',
        'page_head(',
        'admin-telemetry-card',
        'priority-actions',
    ] as $publicStatsForbiddenToken) {
        if (str_contains($publicStatsSource, $publicStatsForbiddenToken)) {
            $developerDashboardFailures[] =
                'public_stats_forbidden_token:' . $publicStatsForbiddenToken;
        }
    }
}
if (substr_count(
    $developerDashboardSources['admin'],
    'admin_metric_dual_area_chart(',
) < 3) {''',
    "architecture public stats boundary",
)
write(check_path, check)

version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW_ISO,
    "updated_at": NOW_ISO,
    "build": BUILD,
    "functional_equivalence_policy": "preserves_dashboard_metrics_uses_one_shared_chart_renderer_and_exposes_only_the_read_only_performance_card_at_public_stats",
    "notes": "Velocidade e Leitura e gravação usam o mesmo renderer; /stats publica somente o card de desempenho em GET público e sem contexto autenticado.",
    "deployment_sync_id": DEPLOYMENT_ID,
    "deployment_sync_requested_at": NOW_ISO,
    "rewrite_scope": "developer_dashboard_single_shared_dual_area_renderer_and_public_read_only_stats_card",
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update({
    "generated_at": NOW_ISO,
    "updated_at": NOW_ISO,
    "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_two_charts_one_shared_renderer_and_public_read_only_stats_card_without_authenticated_context",
    "public_stats_policy": "get_only_prontoo_app_stats_renders_exclusively_admin_performance_card_without_auth_context_post_or_schema_mutation",
})
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

update_path = ROOT / "app/update.manifest.json"
update = json.loads(update_path.read_text(encoding="utf-8"))
update.update({
    "build": BUILD,
    "generated_at": NOW_ISO,
    "updated_at": NOW_ISO,
    "notes": "Renderer dual-area único e página pública /stats limitada ao card de desempenho em modo somente leitura.",
    "deployment_sync_id": DEPLOYMENT_ID,
})
update_path.write_text(json.dumps(update, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

changelog_path = "CHANGELOG.md"
changelog = read(changelog_path)
changelog = replace_once(
    changelog,
    '''- mantém banco e schema inalterados.

## 1.7.30.12''',
    '''- disponibiliza `https://prontoo.app/stats` como página pública GET com exclusivamente o card de desempenho, sem contexto autenticado, POST ou criação de schema;
- mantém banco e schema inalterados.

## 1.7.30.12''',
    "markdown changelog stats",
)
write(changelog_path, changelog)

text_changelog_path = "ChangeLog.txt"
text_changelog = read(text_changelog_path)
text_changelog = replace_once(
    text_changelog,
    '''- Remove o renderer e os estilos exclusivos anteriores.
- Mantém banco e schema inalterados.
Prontoo 1.7.30.12''',
    '''- Remove o renderer e os estilos exclusivos anteriores.
- Disponibiliza prontoo.app/stats com somente o card de desempenho, em GET público e sem contexto autenticado.
- Mantém banco e schema inalterados.
Prontoo 1.7.30.12''',
    "text changelog stats",
)
write(text_changelog_path, text_changelog)

for relative in [
    admin_path,
    runner_path,
    loader_path,
    htaccess_path,
    css_path,
    check_path,
    "version.json",
    "app/architecture.manifest.json",
    "app/update.manifest.json",
    changelog_path,
    text_changelog_path,
]:
    path = ROOT / relative
    if not path.is_file() or path.stat().st_size == 0:
        raise RuntimeError(f"invalid generated file: {relative}")

print(json.dumps({"ok": True, "version": VERSION, "build": BUILD}, ensure_ascii=False))
