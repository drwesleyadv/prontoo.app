from pathlib import Path
import re

runtime_path = Path('app/Runtime/AdminPages/AdminPagesRuntimeOperations09.php')
runtime = runtime_path.read_text()
pattern = r'''\n    public static function page_admin_performance\(\): void\s*\{.*?\n    \}\n\}'''
replacement = r'''
    public static function page_admin_performance(): void

    {

        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_performance");
        $summary = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_route_performance_summary(240);
        $comparison = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_comparative_summary();
        $telemetryCards =
            '<div class="wide global-telemetry-grid">' .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::admin_telemetry_kpi_cards_html() .
            '</div>';
        $telemetryCharts = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_performance_card_html();
        $content = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_observability_html(
            $summary,
            $comparison,
            $telemetryCards,
            $telemetryCharts,
        );
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Observabilidade",
                "Métricas de requisição, latência e comportamento das rotas para investigação técnica.",
            ) .
            $content;
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Observabilidade · Desenvolvedor", $body);
    }
}'''
runtime, count = re.subn(pattern, lambda _m: replacement, runtime, count=1, flags=re.S)
if count != 1:
    raise SystemExit(f'Runtime09 observability extraction match count={count}')
runtime_path.write_text(runtime)

presentation_path = Path('app/Presentation/AdminPages/AdminPagesPresentationOperations03.php')
presentation = presentation_path.read_text()
if 'admin_observability_html' in presentation:
    raise SystemExit('admin_observability_html already exists')
helper = r'''

    public static function admin_observability_html(
        array $summary,
        array $comparison,
        string $telemetryCards,
        string $telemetryCharts,
    ): string {
        $rows = isset($summary["routes"]) && is_array($summary["routes"])
            ? $summary["routes"]
            : [];
        $total = (int) ($summary["total"] ?? 0);
        $avg = (float) ($summary["avg_ms"] ?? 0);
        $slow = $rows[0] ?? null;
        $updated = (string) ($summary["updated_at"] ?? "");
        $current = (array) ($comparison["current"] ?? []);
        $landingAverage = isset($current["landing_average_ms"])
            ? (float) $current["landing_average_ms"]
            : null;
        $stats =
            '<div class="stats-grid admin-performance-stats">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Requisições 10d", $total, "route", "Eventos canônicos no período") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Tempo médio de resposta",
                self::admin_performance_format_ms($avg),
                "speed",
                "Média geral nos últimos 10 dias",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Rota mais lenta",
                $slow ? self::admin_performance_format_ms((float) ($slow["avg_ms"] ?? 0)) : "—",
                "timer",
                $slow ? (string) ($slow["route"] ?? "") : "Sem dados",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Landing Page",
                $landingAverage !== null ? self::admin_performance_format_ms($landingAverage) : "—",
                "web",
                "Tempo médio nos últimos 10 dias",
            ) .
            '</div>';
        $routesCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head admin-performance-head"><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("speed") .
                '<span>Rotas nos últimos 10 dias</span></h2><p>Detalhamento calculado diretamente dos eventos canônicos de início e fim.' .
                ($updated !== "" ? " Última atualização: " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($updated) . "." : "") .
                '</p></div>' .
                self::admin_performance_rows_html($rows),
            "admin-performance-card",
        );
        return '<section class="admin-performance-screen">' .
            $telemetryCards .
            $telemetryCharts .
            $stats .
            $routesCard .
            '</section>';
    }
'''
pos = presentation.rfind('\n}')
if pos < 0:
    raise SystemExit('Presentation03 closing brace not found')
presentation_path.write_text(presentation[:pos] + helper + presentation[pos:])
