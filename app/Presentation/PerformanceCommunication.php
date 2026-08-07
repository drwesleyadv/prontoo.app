<?php
declare(strict_types=1);

function performance_communication_replace_chart(
    string $html,
    string $currentLabel,
    string $replacement,
): string {
    $needle = 'aria-label="' . $currentLabel . '"';
    $labelPosition = strpos($html, $needle);
    if ($labelPosition === false) {
        return $html;
    }
    $prefix = substr($html, 0, $labelPosition);
    $start = strrpos($prefix, '<article class="metric-line-chart');
    $end = strpos($html, '</article>', $labelPosition);
    if ($start === false || $end === false || $end < $start) {
        return $html;
    }
    $end += strlen('</article>');
    return substr($html, 0, $start) . $replacement . substr($html, $end);
}

function performance_communication_transform_html(string $html): string
{
    if (
        !str_contains($html, 'data-admin-global-charts') ||
        !function_exists('admin_metric_dual_area_chart') ||
        !function_exists('admin_global_metric_series_24h')
    ) {
        return $html;
    }

    $last24Hours = admin_metric_dual_area_chart(
        'Últimas 24 horas',
        admin_global_metric_series_24h('duration'),
        admin_global_metric_series_24h('landing_duration'),
        'speed',
        [
            'primary_label' => 'Rotas',
            'secondary_label' => 'Landing Page',
            'value_type' => 'ms',
            'recent_title' => 'Velocidade média nos últimos 5 minutos',
            'middle_title' => 'Velocidade média nos últimos 30 minutos',
            'overall_title' => 'Velocidade média nas últimas 24 horas',
            'summary_lead' => 'dados de velocidade das rotas e da Landing Page.',
        ],
    );
    $last24Hours = str_replace(
        [
            'class="metric-chart-line-load"',
            'class="metric-chart-line-response"',
        ],
        [
            'class="metric-chart-line-load" style="stroke-width:1px"',
            'class="metric-chart-line-response" style="stroke-width:1px"',
        ],
        $last24Hours,
    );

    $last30Days = admin_metric_dual_area_chart(
        'Últimos 30 dias',
        telemetry_route_requests_series_20d(),
        telemetry_sequence_records_series_20d(),
        'speed',
        [
            'primary_label' => 'Visualizações',
            'secondary_label' => 'Registros',
            'value_type' => 'count',
            'recent_points' => 1,
            'middle_points' => 7,
            'recent_title' => 'Visualizações de hoje',
            'middle_title' => 'Média diária de visualizações nos últimos 7 dias',
            'overall_title' => 'Média diária de visualizações nos últimos 30 dias',
            'summary_lead' => 'dados de Visualizações e Registros dos últimos 30 dias, incluindo hoje.',
        ],
    );

    $html = performance_communication_replace_chart(
        $html,
        'Velocidade',
        $last24Hours,
    );
    $html = performance_communication_replace_chart(
        $html,
        'Leitura e gravação',
        $last30Days,
    );

    return str_replace(
        [
            '<h2>Telemetria das últimas 24 horas</h2>',
            '<span>Requisições</span>',
            '<span>Tempo médio das rotas</span>',
            '<span>Execuções da Landing Page</span>',
        ],
        [
            '<h2>Performance</h2>',
            '<span>Visualizações</span>',
            '<span>Velocidade média</span>',
            '<span>Visualizações da Landing</span>',
        ],
        $html,
    );
}

if (PHP_SAPI !== 'cli') {
    ob_start('performance_communication_transform_html');
}
