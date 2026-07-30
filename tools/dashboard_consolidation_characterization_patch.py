from pathlib import Path

path = Path(__file__).resolve().parents[1] / "tools/architecture-check.php"
source = path.read_text(encoding="utf-8")
start = source.find("foreach ([\n    'Consultórios ativos 7d',")
end = source.find("if (is_file($root . '/docs/performance/developer-telemetry-dashboard.md')) {", start)
if start < 0 or end < 0:
    raise RuntimeError("dashboard obsolete-token characterization boundary not found")
replacement = r'''$developerPanelStart = strpos(
    $developerDashboardSources['admin'],
    'function page_admin_painel(): void',
);
$developerPanelEnd = $developerPanelStart === false
    ? false
    : strpos(
        $developerDashboardSources['admin'],
        'function page_admin_people(): void',
        $developerPanelStart,
    );
$developerPanelSource =
    $developerPanelStart !== false && $developerPanelEnd !== false
        ? substr(
            $developerDashboardSources['admin'],
            $developerPanelStart,
            $developerPanelEnd - $developerPanelStart,
        )
        : '';
if ($developerPanelSource === '') {
    $developerDashboardFailures[] = 'developer_dashboard_page_boundary';
}
foreach ([
    'Consultórios ativos 7d',
    'Tempo médio de resposta',
    '"Landing page"',
] as $obsoletePanelToken) {
    if (str_contains($developerPanelSource, $obsoletePanelToken)) {
        $developerDashboardFailures[] =
            'obsolete_dashboard_card:' . $obsoletePanelToken;
    }
}
foreach ([
    'admin_metric_bar_chart(',
    'metric-bar-chart',
    'developer-telemetry-dashboard.md',
] as $obsoleteToken) {
    if (str_contains(
        $developerDashboardSources['admin'] .
            $developerDashboardSources['css'] .
            $developerDashboardSources['docs'],
        $obsoleteToken,
    )) {
        $developerDashboardFailures[] =
            'obsolete_dashboard_token:' . $obsoleteToken;
    }
}
'''
path.write_text(source[:start] + replacement + source[end:], encoding="utf-8")
