<?php
declare(strict_types=1);

namespace Prontoo\Presentation\AdminPages;

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

final class AdminPagesPresentationOperations02
{
    private function __construct()
    {
    }

    public static function admin_metric_line_chart(
        string $title,
        string $description,
        array $series,
        string $iconName,
        string $mode = "count",
    ): string 
    {
    
        $values = array_map( fn($r) => (float) ($r["value"] ?? 0), $series);
        if (!$values) {
            $values = [0.0];
        }
        $max = max($values);
        if ($max <= 0) {
            $max = 1.0;
        }
        $w = 720;
        $h = 190;
        $padL = 34;
        $padR = 14;
        $padT = 18;
        $padB = 32;
        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;
        $n = count($series);
        $points = [];
        foreach ($series as $idx => $row) {
            $v = (float) ($row["value"] ?? 0);
            $x = $padL + ($n <= 1 ? 0 : $idx * ($plotW / ($n - 1)));
            $y = $padT + $plotH - ($v / $max) * $plotH;
            $points[] = [
                round($x, 2, \RoundingMode::HalfAwayFromZero),
                round($y, 2, \RoundingMode::HalfAwayFromZero),
                $v,
                (string) ($row["label"] ?? ""),
                (string) ($row["tooltip"] ?? ($row["label"] ?? "")),
            ];
        }
        $d = "";
        foreach ($points as $i => $pt) {
            $d .= ($i === 0 ? "M" : "L") . $pt[0] . " " . $pt[1] . " ";
        }
        $baseline = $padT + $plotH;
        $fillD = "";
        if ($points) {
            $firstPt = $points[0];
            $lastSeriesPt = $points[count($points) - 1];
            $fillD =
                trim($d) .
                " L " .
                $lastSeriesPt[0] .
                " " .
                round($baseline, 2, \RoundingMode::HalfAwayFromZero) .
                " L " .
                $firstPt[0] .
                " " .
                round($baseline, 2, \RoundingMode::HalfAwayFromZero) .
                " Z";
        }
        $last = $points ? $points[count($points) - 1][2] : 0.0;
        $nowAvg5 = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_recent_average($series, 5);
        $nonZero = array_values(
            array_filter($values, static  fn($v) => (float) $v > 0),
        );
        $avg24 = count($nonZero) ? array_sum($nonZero) / count($nonZero) : 0.0;
        $recentValues = array_slice($values, -31);
        $recentNonZero = array_values(
            array_filter($recentValues, static  fn($v) => (float) $v > 0),
        );
        $avg30 = count($recentNonZero)
            ? array_sum($recentNonZero) / count($recentNonZero)
            : 0.0;
        $grid = "";
        for ($i = 0; $i <= 3; $i++) {
            $gy = $padT + $i * ($plotH / 3);
            $gv = $max - ($max / 3) * $i;
            $grid .=
                '<line x1="' .
                $padL .
                '" y1="' .
                round($gy, 2, \RoundingMode::HalfAwayFromZero) .
                '" x2="' .
                ($w - $padR) .
                '" y2="' .
                round($gy, 2, \RoundingMode::HalfAwayFromZero) .
                '" class="metric-grid-line"/><text x="6" y="' .
                round($gy + 4, 2, \RoundingMode::HalfAwayFromZero) .
                '" class="metric-axis-label">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(number_format($gv, 0, ",", ".")) .
                "</text>";
        }
        $ticks = "";
        $tickEvery = max(1, (int) ceil(max(1, $n) / 8));
        foreach ($points as $i => $pt) {
            if ($i % $tickEvery === 0 || $i === $n - 1) {
                $ticks .=
                    '<text x="' .
                    $pt[0] .
                    '" y="' .
                    ($h - 8) .
                    '" class="metric-axis-label metric-x-label">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pt[3]) .
                    "</text>";
            }
        }
        $hover = "";
        foreach ($points as $pt) {
            $label = $pt[4] . " · " . \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_label($pt[2], $mode);
            $hover .=
                '<circle cx="' .
                $pt[0] .
                '" cy="' .
                $pt[1] .
                '" r="4.5" class="metric-chart-hit"><title>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                "</title></circle>";
        }
        $lastPt = end($points);
        $circle = $lastPt
            ? '<circle cx="' .
                $lastPt[0] .
                '" cy="' .
                $lastPt[1] .
                '" r="3.5" class="metric-chart-dot"><title>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    $lastPt[4] .
                        " · " .
                        \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_label($lastPt[2], $mode),
                ) .
                "</title></circle>"
            : "";
        $summary =
            $title .
            ": agora, média dos últimos 5 minutos, " .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_compact($nowAvg5, $mode) .
            ", média de 30 minutos " .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_compact($avg30, $mode) .
            ", média de 24 horas " .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_compact($avg24, $mode);
        $nowCompact = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_compact($nowAvg5, $mode);
        $avg30Compact = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_compact($avg30, $mode);
        $avg24Compact = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_compact($avg24, $mode);
        $pills =
            '<span title="Média dos últimos 5 minutos" aria-label="Agora, média dos últimos 5 minutos: ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($nowCompact) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("radio_button_checked") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($nowCompact) .
            "</b></span>" .
            '<span title="Média dos últimos 30 minutos" aria-label="Média dos últimos 30 minutos: ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($avg30Compact) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("timer") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($avg30Compact) .
            "</b></span>" .
            '<span title="Média das últimas 24 horas" aria-label="Média das últimas 24 horas: ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($avg24Compact) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_today") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($avg24Compact) .
            "</b></span>";
        return '<article class="metric-line-chart metric-area-chart" data-ds-card="admin-area-chart" aria-label="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
            '"><header><div class="metric-chart-title">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
            "<div><h3>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
            '</h3></div></div><div class="metric-chart-value"><div class="metric-chart-pills">' .
            $pills .
            '</div></div></header><p class="sr-only">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($summary) .
            '</p><svg viewBox="0 0 ' .
            $w .
            " " .
            $h .
            '" aria-hidden="true" focusable="false"><g>' .
            $grid .
            "</g>" .
            ($fillD !== ""
                ? '<path d="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fillD) . '" class="metric-chart-fill"/>'
                : "") .
            '<path d="' .
            trim($d) .
            '" class="metric-chart-line"/>' .
            $circle .
            $hover .
            $ticks .
            "</svg></article>";
    
    }
}
