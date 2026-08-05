<?php
declare(strict_types=1);

function prontoo_speed_chart_geometry_script(): string
{
    return <<<'HTML'
<script>
(() => {
    const straighten = () => {
        document.querySelectorAll('.metric-dual-time-chart').forEach((chart) => {
            const title = chart.querySelector('h3')?.textContent?.trim() || '';
            if (title !== 'Velocidade' || chart.dataset.straightGeometry === '1') {
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

function prontoo_register_speed_chart_geometry(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $route = function_exists('prontoo_boot_requested_route')
        ? prontoo_boot_requested_route()
        : preg_replace('/[^a-z0-9_\-]/i', '', (string) ($_GET['r'] ?? ''));
    if ($route !== 'status' && !str_starts_with((string) $route, 'admin_')) {
        return;
    }
    ob_start(static function (string $html): string {
        if (!str_contains($html, 'metric-dual-time-chart') || !str_contains($html, '</body>')) {
            return $html;
        }
        return str_replace('</body>', prontoo_speed_chart_geometry_script() . '</body>', $html);
    });
}

prontoo_register_speed_chart_geometry();
