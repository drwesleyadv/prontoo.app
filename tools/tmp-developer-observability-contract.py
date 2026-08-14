from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected one match, found {count}: {old[:100]}')
    p.write_text(text.replace(old, new, 1))

# Preserve all useful telemetry capabilities, but move them from the developer homepage to Observability.
replace_once(
    'app/Runtime/AdminPages/AdminPagesRuntimeOperations09.php',
    '''        $body =
            \\Prontoo\\Runtime\\UiComponents\\UiComponentsRuntimeOperations03::page_head(
                "Observabilidade",
                "Métricas de requisição, latência e comportamento das rotas para investigação técnica.",
            ) .
            '<section class="admin-performance-screen">' .
            $stats .
            $routesCard .
            '</section>';''',
    '''        $telemetryCards =
            '<div class="wide global-telemetry-grid">' .
            \\Prontoo\\Runtime\\AdminPages\\AdminPagesRuntimeOperations03::admin_telemetry_kpi_cards_html() .
            '</div>';
        $telemetryCharts = \\Prontoo\\Runtime\\AdminPages\\AdminPagesRuntimeOperations02::admin_performance_card_html();
        $body =
            \\Prontoo\\Runtime\\UiComponents\\UiComponentsRuntimeOperations03::page_head(
                "Observabilidade",
                "Métricas de requisição, latência e comportamento das rotas para investigação técnica.",
            ) .
            '<section class="admin-performance-screen">' .
            $telemetryCards .
            $telemetryCharts .
            $stats .
            $routesCard .
            '</section>';''',
)

contract = 'tools/telemetry-json-source-contract-check'
replace_once(
    contract,
    '''$assert(
    str_contains($admin6, 'admin_telemetry_kpi_cards_html(true)') &&
    str_contains($admin3, 'admin_telemetry_kpi_cards_html()'),
    'Painel do Desenvolvedor e Status devem compartilhar o renderer minimalista dos KPIs',
);''',
    '''$assert(
    !str_contains($admin6, 'admin_telemetry_kpi_cards_html(') &&
    !str_contains($admin6, 'admin_performance_card_html(') &&
    str_contains($admin9, 'admin_telemetry_kpi_cards_html()') &&
    str_contains($admin3, 'admin_telemetry_kpi_cards_html()'),
    'Visão geral deve permanecer sem telemetria detalhada e Observabilidade deve usar o renderer canônico dos KPIs',
);''',
)
replace_once(
    contract,
    '''$assert(
    str_contains($admin6, 'page_admin_painel()') &&
    str_contains($admin6, 'admin_performance_card_html()'),
    'Painel do Desenvolvedor deixou de consumir o gráfico canônico de 30 dias',
);''',
    '''$assert(
    str_contains($admin6, 'page_admin_painel()') &&
    !str_contains($admin6, 'admin_performance_card_html(') &&
    str_contains($admin9, 'page_admin_performance()') &&
    str_contains($admin9, 'admin_performance_card_html()'),
    'Visão geral deve ser orientada a decisões e Observabilidade deve consumir o gráfico canônico de 30 dias',
);''',
)
