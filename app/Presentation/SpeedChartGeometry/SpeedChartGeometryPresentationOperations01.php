<?php
declare(strict_types=1);

namespace Prontoo\Presentation\SpeedChartGeometry;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class SpeedChartGeometryPresentationOperations01
{
    private function __construct()
    {
    }

    public static function prontoo_performance_replace_chart(
        string $html,
        string $currentLabel,
        string $replacementLabel,
        array $replacements = [],
        bool $markSpeedChart = false,
    ): string 
    {
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

    public static function prontoo_performance_transform_html(string $html): string
    {
        if (!str_contains($html, 'data-admin-global-charts')) {
            return $html;
        }
        $html = self::prontoo_performance_replace_chart(
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
        $html = self::prontoo_performance_replace_chart(
            $html,
            'Leitura e gravação',
            'Últimos 30 dias',
            [
                'Requisições de hoje' => 'Visualizações de hoje',
                'Média diária de requisições nos últimos 7 dias' => 'Média diária de visualizações nos últimos 7 dias',
                'Média diária de requisições nos últimos 20 dias' => 'Média diária de visualizações nos últimos 30 dias',
                'dados de Requisições e Registros dos últimos 20 dias.' => 'dados de Visualizações e Registros dos últimos 30 dias, incluindo hoje.',
                'Requisições' => 'Visualizações',
                'class="metric-chart-fill-load"' => 'class="metric-chart-fill-load" style="fill-opacity:0.5"',
                'class="metric-chart-fill-response"' => 'class="metric-chart-fill-response" style="fill-opacity:0.5"',
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

    public static function prontoo_speed_chart_geometry_script(): string
    {
        return <<<'HTML'
    <script>
    (() => {
        const straighten = () => {
            document.querySelectorAll('.metric-dual-time-chart').forEach((chart) => {
                if (chart.dataset.performanceSpeedChart !== '1' || chart.dataset.straightGeometry === '1') {
                    return;
                }
                chart.dataset.straightGeometry = '1';
                const baseline = 158;
                const pairs = [
                    ['.metric-chart-line-load', '.metric-chart-fill-load'],
                    ['.metric-chart-line-response', '.metric-chart-fill-response'],
                ];
                pairs.forEach(([lineSelector, fillSelector]) => {
                    const line = chart.querySelector(lineSelector);
                    const fill = chart.querySelector(fillSelector);
                    if (!line || !fill) {
                        return;
                    }
                    const source = line.getAttribute('d') || '';
                    const start = source.match(/^M\s*([\d.]+)\s+([\d.]+)/i);
                    if (!start) {
                        line.remove();
                        return;
                    }
                    const points = [[start[1], start[2]]];
                    const segments = source.matchAll(/C\s*[\d.]+\s+[\d.]+\s+[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)/gi);
                    for (const segment of segments) {
                        points.push([segment[1], segment[2]]);
                    }
                    if (points.length > 1) {
                        const path = `M${points[0][0]} ${points[0][1]} ` + points.slice(1).map((point) => `L ${point[0]} ${point[1]}`).join(' ');
                        const first = points[0];
                        const last = points[points.length - 1];
                        fill.setAttribute('d', `${path} L ${last[0]} ${baseline} L ${first[0]} ${baseline} Z`);
                    }
                    line.remove();
                });
            });
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', straighten, {once: true});
        } else {
            straighten();
        }
    })();
    </script>
    HTML;
    }

    public static function prontoo_performance_geometry_transform_html(string $html): string
    {
        $html = self::prontoo_performance_transform_html($html);
        if (!str_contains($html, 'data-performance-speed-chart="1"') || !str_contains($html, '</body>')) {
            return $html;
        }
        return str_replace('</body>', self::prontoo_speed_chart_geometry_script() . '</body>', $html);
    }

    public static function prontoo_register_speed_chart_geometry(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $route = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($_GET['r'] ?? '')) ?: 'login';
        if ($route !== 'status' && !str_starts_with((string) $route, 'admin_')) {
            return;
        }
        ob_start([self::class, 'prontoo_performance_geometry_transform_html']);
    }
}
