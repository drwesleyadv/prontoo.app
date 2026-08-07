<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function prontoo_performance_replace_chart(
    string $html,
    string $currentLabel,
    string $replacementLabel,
    array $replacements = [],
    bool $markSpeedChart = false,
): string {
    return \Prontoo\Presentation\Legacy\SpeedChartGeometry\SpeedChartGeometryPresentationOperations01::prontoo_performance_replace_chart($html, $currentLabel, $replacementLabel, $replacements, $markSpeedChart);
}

function prontoo_performance_transform_html(string $html): string
{
    return \Prontoo\Presentation\Legacy\SpeedChartGeometry\SpeedChartGeometryPresentationOperations01::prontoo_performance_transform_html($html);
}

function prontoo_speed_chart_geometry_script(): string
{
    return \Prontoo\Presentation\Legacy\SpeedChartGeometry\SpeedChartGeometryPresentationOperations01::prontoo_speed_chart_geometry_script();
}

function prontoo_performance_geometry_transform_html(string $html): string
{
    return \Prontoo\Presentation\Legacy\SpeedChartGeometry\SpeedChartGeometryPresentationOperations01::prontoo_performance_geometry_transform_html($html);
}

function prontoo_register_speed_chart_geometry(): void
{
    \Prontoo\Presentation\Legacy\SpeedChartGeometry\SpeedChartGeometryPresentationOperations01::prontoo_register_speed_chart_geometry();
}

prontoo_register_speed_chart_geometry();
