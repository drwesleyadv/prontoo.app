<?php
declare(strict_types=1);

namespace Prontoo\Presentation;

final class PerformanceCommunication
{
    private function __construct()
    {
    }

    private static function replaceChart(
        string $html,
        string $currentLabel,
        string $replacementLabel,
        array $replacements = [],
        bool $markSpeedChart = false,
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
        $chart = substr($html, $start, $end - $start);
        $chart = str_replace(
            [
                'aria-label="' . $currentLabel . '"',
                '<h3>' . $currentLabel . '</h3>',
            ],
            [
                'aria-label="' . $replacementLabel . '"',
                '<h3>' . $replacementLabel . '</h3>',
            ],
            $chart,
        );
        foreach ($replacements as $search => $replacement) {
            $chart = str_replace((string) $search, (string) $replacement, $chart);
        }
        if ($markSpeedChart) {
            $chart = preg_replace(
                '/<article class="metric-line-chart/',
                '<article data-performance-speed-chart="1" class="metric-line-chart',
                $chart,
                1,
            ) ?? $chart;
            $chart = str_replace(
                [
                    'class="metric-chart-line-load"',
                    'class="metric-chart-line-response"',
                ],
                [
                    'class="metric-chart-line-load" style="stroke-width:1px"',
                    'class="metric-chart-line-response" style="stroke-width:1px"',
                ],
                $chart,
            );
        }
        return substr($html, 0, $start) . $chart . substr($html, $end);
    }

    public static function transformHtml(string $html): string
    {
        if (!str_contains($html, 'data-admin-global-charts')) {
            return $html;
        }

        $html = self::replaceChart(
            $html,
            'Velocidade',
            'Últimas 24 horas',
            [
                'Tempo médio das rotas nos últimos 5 minutos' => 'Velocidade média nos últimos 5 minutos',
                'Tempo médio das rotas nos últimos 30 minutos' => 'Velocidade média nos últimos 30 minutos',
                'Tempo médio das rotas nas últimas 24 horas' => 'Velocidade média nas últimas 24 horas',
                'dados de duração de rotas e da Landing Page.' => 'dados de velocidade das rotas e da Landing Page.',
            ],
            true,
        );
        $html = self::replaceChart(
            $html,
            'Leitura e gravação',
            'Últimos 30 dias',
            [
                'Requisições de hoje' => 'Visualizações de hoje',
                'Média diária de requisições nos últimos 7 dias' => 'Média diária de visualizações nos últimos 7 dias',
                'Média diária de requisições nos últimos 20 dias' => 'Média diária de visualizações nos últimos 30 dias',
                'dados de Requisições e Registros dos últimos 20 dias.' => 'dados de Visualizações e Registros dos últimos 30 dias, incluindo hoje.',
                'Requisições' => 'Visualizações',
            ],
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

    public static function register(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        ob_start([self::class, 'transformHtml']);
    }
}

PerformanceCommunication::register();
