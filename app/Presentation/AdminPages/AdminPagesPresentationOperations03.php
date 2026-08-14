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

final class AdminPagesPresentationOperations03
{
    private function __construct()
    {
    }

    public static function admin_metric_dual_area_chart(
        string $title,
        array $loadSeries,
        array $responseSeries,
        string $iconName = "speed",
        array $presentation = [],
    ): string 
    {
    
        $primaryLabel = mb_trim((string) ($presentation["primary_label"] ?? "Carregamento"));
        $secondaryLabel = mb_trim((string) ($presentation["secondary_label"] ?? "Resposta"));
        $valueType = (string) ($presentation["value_type"] ?? "ms");
        $visualMode = (string) ($presentation["visual_mode"] ?? "default");
        if ($primaryLabel === "") {
            $primaryLabel = "Carregamento";
        }
        if ($secondaryLabel === "") {
            $secondaryLabel = "Resposta";
        }
        if (!in_array($valueType, ["ms", "count"], true)) {
            $valueType = "ms";
        }
        if (!in_array($visualMode, ["default", "latency", "telemetry"], true)) {
            $visualMode = "default";
        }
        $countSummaryMode = (string) ($presentation["count_summary_mode"] ?? "average");
        $recentPoints = max(1, (int) ($presentation["recent_points"] ?? 5));
        $middlePoints = max(1, (int) ($presentation["middle_points"] ?? 31));
        $comparisonPoints = max(0, (int) ($presentation["comparison_points"] ?? 0));
        $recentTitle = mb_trim((string) ($presentation["recent_title"] ?? "Carregamento médio dos últimos 5 minutos"));
        $middleTitle = mb_trim((string) ($presentation["middle_title"] ?? "Carregamento médio dos últimos 30 minutos"));
        $overallTitle = mb_trim((string) ($presentation["overall_title"] ?? "Carregamento médio das últimas 24 horas"));
        $summaryLead = mb_trim((string) ($presentation["summary_lead"] ?? "indicadores exibem somente o tempo de carregamento."));
        $loadValues = array_values(
            array_map(
                static fn(array $r): float => (float) $r["value"],
                array_filter(
                    $loadSeries,
                    static fn(array $r): bool =>
                        (!array_key_exists("observed", $r) || !empty($r["observed"])) &&
                        isset($r["value"]) &&
                        is_numeric($r["value"]),
                ),
            ),
        );
        $responseValues = array_values(
            array_map(
                static fn(array $r): float => (float) $r["value"],
                array_filter(
                    $responseSeries,
                    static fn(array $r): bool =>
                        (!array_key_exists("observed", $r) || !empty($r["observed"])) &&
                        isset($r["value"]) &&
                        is_numeric($r["value"]),
                ),
            ),
        );
        if (!$loadValues) {
            $loadValues = [0.0];
        }
        if (!$responseValues) {
            $responseValues = [0.0];
        }
        $scaleMin = min(0.0, min($loadValues), min($responseValues));
        $scaleMax = max(0.0, max($loadValues), max($responseValues));
        if ($scaleMax <= $scaleMin) {
            $scaleMax = $scaleMin + 1.0;
        }
        $range = $scaleMax - $scaleMin;
        $w = 720;
        $h = 190;
        $padL = 34;
        $padR = 14;
        $padT = 18;
        $padB = 32;
        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;
        $baseline = $padT + $plotH - ((0.0 - $scaleMin) / $range) * $plotH;
        $makePoints = static function (array $series) use (
            $padL,
            $plotW,
            $padT,
            $plotH,
            $scaleMin,
            $range,
        ): array {
    
            $n = count($series);
            $points = [];
            foreach ($series as $idx => $row) {
                if (
                    (array_key_exists("observed", $row) && empty($row["observed"])) ||
                    !isset($row["value"]) ||
                    !is_numeric($row["value"])
                ) {
                    continue;
                }
                $v = (float) $row["value"];
                $x = $padL + ($n <= 1 ? 0 : $idx * ($plotW / ($n - 1)));
                $y = $padT + $plotH - (($v - $scaleMin) / $range) * $plotH;
                $points[] = [
                    round($x, 2, \RoundingMode::HalfAwayFromZero),
                    round($y, 2, \RoundingMode::HalfAwayFromZero),
                    $v,
                    (string) ($row["label"] ?? ""),
                    (string) ($row["tooltip"] ?? ($row["label"] ?? "")),
                ];
            }
            return $points;
        };
        $path = static function (array $points): string {
    
            $count = count($points);
            if ($count === 0) {
                return "";
            }
            if ($count === 1) {
                return "M" . $points[0][0] . " " . $points[0][1];
            }
            $tension = 0.72;
            $d = "M" . $points[0][0] . " " . $points[0][1];
            for ($i = 0; $i < $count - 1; $i++) {
                $p0 = $points[max(0, $i - 1)];
                $p1 = $points[$i];
                $p2 = $points[$i + 1];
                $p3 = $points[min($count - 1, $i + 2)];
                $cp1x = $p1[0] + (($p2[0] - $p0[0]) * $tension) / 6;
                $cp1y = $p1[1] + (($p2[1] - $p0[1]) * $tension) / 6;
                $cp2x = $p2[0] - (($p3[0] - $p1[0]) * $tension) / 6;
                $cp2y = $p2[1] - (($p3[1] - $p1[1]) * $tension) / 6;
                $segmentMinY = min($p1[1], $p2[1]);
                $segmentMaxY = max($p1[1], $p2[1]);
                $cp1y = max($segmentMinY, min($segmentMaxY, $cp1y));
                $cp2y = max($segmentMinY, min($segmentMaxY, $cp2y));
                $d .=
                    " C " .
                    round($cp1x, 2, \RoundingMode::HalfAwayFromZero) .
                    " " .
                    round($cp1y, 2, \RoundingMode::HalfAwayFromZero) .
                    " " .
                    round($cp2x, 2, \RoundingMode::HalfAwayFromZero) .
                    " " .
                    round($cp2y, 2, \RoundingMode::HalfAwayFromZero) .
                    " " .
                    $p2[0] .
                    " " .
                    $p2[1];
            }
            return $d;
        };
        $fill = static function (
            string $d,
            array $points,
            float $baseline,
        ): string {
    
            if (!$points || $d === "") {
                return "";
            }
            $first = $points[0];
            $last = $points[count($points) - 1];
            return $d .
                " L " .
                $last[0] .
                " " .
                round($baseline, 2, \RoundingMode::HalfAwayFromZero) .
                " L " .
                $first[0] .
                " " .
                round($baseline, 2, \RoundingMode::HalfAwayFromZero) .
                " Z";
        };
        $loadPoints = $makePoints($loadSeries);
        $responsePoints = $makePoints($responseSeries);
        $loadD = $path($loadPoints);
        $responseD = $path($responsePoints);
        $loadFill = $fill($loadD, $loadPoints, $baseline);
        $responseFill = $fill($responseD, $responsePoints, $baseline);
        $grid = "";
        for ($i = 0; $i <= 3; $i++) {
            $gy = $padT + $i * ($plotH / 3);
            $gv = $scaleMax - ($range / 3) * $i;
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
        $n = count($loadPoints);
        $tickEvery = max(1, (int) ceil(max(1, $n) / 8));
        foreach ($loadPoints as $i => $pt) {
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
        foreach ($loadPoints as $pt) {
            $hover .=
                '<circle cx="' .
                $pt[0] .
                '" cy="' .
                $pt[1] .
                '" r="4.5" class="metric-chart-hit"><title>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    $pt[4] .
                        " · " . $primaryLabel . " " .
                        \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_label($pt[2], $valueType),
                ) .
                "</title></circle>";
        }
        foreach ($responsePoints as $pt) {
            $hover .=
                '<circle cx="' .
                $pt[0] .
                '" cy="' .
                $pt[1] .
                '" r="3.8" class="metric-chart-hit"><title>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    $pt[4] .
                        " · " . $secondaryLabel . " " .
                        \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_label($pt[2], $valueType),
                ) .
                "</title></circle>";
        }
        $comparisonMode =
            $valueType === "count" &&
            $comparisonPoints > 0 &&
            count($loadValues) >= $comparisonPoints * 2;
        if ($comparisonMode) {
            $recentValues = array_slice($loadValues, -$comparisonPoints);
            $middleValues = array_slice(
                $loadValues,
                -2 * $comparisonPoints,
                $comparisonPoints,
            );
            $recentValue = $recentValues
                ? array_sum($recentValues) / count($recentValues)
                : 0.0;
            $middleValue = $middleValues
                ? array_sum($middleValues) / count($middleValues)
                : 0.0;
        } else {
            $recentValues = array_slice($loadValues, -$recentPoints);
            $recentValue = $valueType === "count"
                ? ($recentValues
                    ? ($countSummaryMode === "sum" ? array_sum($recentValues) : array_sum($recentValues) / count($recentValues))
                    : 0.0)
                : \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_recent_average(
                    $loadSeries,
                    $recentPoints,
                );
            $middleValues = array_slice($loadValues, -$middlePoints);
            $middleValue = $valueType === "count"
                ? ($middleValues
                    ? ($countSummaryMode === "sum" ? array_sum($middleValues) : array_sum($middleValues) / count($middleValues))
                    : 0.0)
                : \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_recent_average(
                    $loadSeries,
                    $middlePoints,
                );
        }
        $overallValue = $valueType === "count"
            ? (count($loadValues) > 0
                ? ($countSummaryMode === "sum" ? array_sum($loadValues) : array_sum($loadValues) / count($loadValues))
                : 0.0)
            : \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_recent_average(
                $loadSeries,
                count($loadSeries),
            );
        $recentCompact = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_compact($recentValue, $valueType);
        $middleCompact = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_compact($middleValue, $valueType);
        $overallCompact = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_compact($overallValue, $valueType);
        $pills =
            '<span title="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($recentTitle) .
            '" aria-label="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($recentTitle . ": " . $recentCompact) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("radio_button_checked") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($recentCompact) .
            "</b></span>" .
            '<span title="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($middleTitle) .
            '" aria-label="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($middleTitle . ": " . $middleCompact) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("timer") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($middleCompact) .
            "</b></span>" .
            '<span title="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($overallTitle) .
            '" aria-label="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($overallTitle . ": " . $overallCompact) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_today") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($overallCompact) .
            "</b></span>";
        $legend = "";
        $lastLoad = end($loadPoints);
        $lastResp = end($responsePoints);
        $loadCircle = $lastLoad
            ? '<circle cx="' .
                $lastLoad[0] .
                '" cy="' .
                $lastLoad[1] .
                '" r="3.6" class="metric-chart-dot metric-chart-dot-load"><title>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    $lastLoad[4] .
                        " · " . $primaryLabel . " " .
                        \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_label($lastLoad[2], $valueType),
                ) .
                "</title></circle>"
            : "";
        $responseCircle = $lastResp
            ? '<circle cx="' .
                $lastResp[0] .
                '" cy="' .
                $lastResp[1] .
                '" r="3.2" class="metric-chart-dot metric-chart-dot-response"><title>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    $lastResp[4] .
                        " · " . $secondaryLabel . " " .
                        \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_metric_value_label($lastResp[2], $valueType),
                ) .
                "</title></circle>"
            : "";
        $summary =
            $title .
            ": " .
            $summaryLead .
            " " .
            $recentTitle .
            " " .
            $recentCompact .
            ", " .
            $middleTitle .
            " " .
            $middleCompact .
            ", " .
            $overallTitle .
            " " .
            $overallCompact .
            ".";
        $loadArea =
            $loadFill !== ""
                ? '<path d="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($loadFill) . '" class="metric-chart-fill-load"/>'
                : "";
        $responseArea =
            $responseFill !== ""
                ? '<path d="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($responseFill) .
                    '" class="metric-chart-fill-response"/>'
                : "";
        $fillAreas = $loadArea . $responseArea;
        return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-metric-value-type="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($valueType) .
            '" data-metric-visual="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($visualMode) .
            '" data-ds-card="admin-dual-area-chart" aria-label="' .
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
            $fillAreas .
            ($loadD !== ""
                ? '<path d="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($loadD) . '" class="metric-chart-line-load"/>'
                : "") .
            ($responseD !== ""
                ? '<path d="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($responseD) .
                    '" class="metric-chart-line-response"/>'
                : "") .
            $loadCircle .
            $responseCircle .
            $hover .
            $ticks .
            "</svg></article>";
    
    }

    public static function admin_telemetry_variation_badge(?float $variation): string
    {
        if ($variation === null) {
            return '<span class="telemetry-kpi-trend is-neutral is-pending" title="Aguardando base comparável" aria-label="Aguardando base comparável">⌛</span>';
        }
        if (abs($variation) < 0.05) {
            return '<span class="telemetry-kpi-trend is-neutral" title="Sem variação em relação aos 15 dias anteriores" aria-label="Sem variação em relação aos 15 dias anteriores">0%</span>';
        }
        $positive = $variation > 0;
        $compact = number_format(abs($variation), 1, ",", ".");
        $compact = preg_replace('/,0$/', "", $compact) ?: "0";
        $direction = $positive ? "▲" : "▼";
        $description =
            ($positive ? "Alta de " : "Queda de ") .
            $compact .
            "% em relação aos 15 dias anteriores";
        return '<span class="telemetry-kpi-trend ' .
            ($positive ? "is-positive" : "is-negative") .
            '" title="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($description) .
            '" aria-label="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($description) .
            '"><span class="telemetry-kpi-trend-icon" aria-hidden="true">' .
            $direction .
            '</span><span class="telemetry-kpi-trend-rate">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($compact . "%") .
            "</span></span>";
    }

    public static function onboarding_score(array $r): array
    
    {
    
        $checks = [
            (int) $r["onboarding_done"] === 1,
            (int) $r["team"] > 0,
            (int) $r["doctors"] > 0,
            (int) $r["patients"] > 0,
            (int) $r["appointments"] > 0,
            (int) $r["tasks"] > 0,
        ];
        $done = count(array_filter($checks));
        return [$done, count($checks)];
    
    }

    public static function onboarding_use_icon(bool $ok, string $label): string
    
    {
    
        return '<span class="onboard-cell ' .
            ($ok ? "ok" : "bad") .
            '" title="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label . ": " . ($ok ? "usado" : "pendente")) .
            '" aria-label="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label . ": " . ($ok ? "usado" : "pendente")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ok ? "check_circle" : "radio_button_unchecked") .
            "</span>";
    
    }

    public static function onboarding_progress_bar(array $used): string
    
    {
    
        $total = 6;
        $labels = [
            "Cadastro",
            "Equipe",
            "Profissional",
            "Paciente",
            "Agenda",
            "Tarefa",
        ];
        $done = 0;
        $segments = "";
        for ($i = 0; $i < $total; $i++) {
            $ok = !empty($used[$i]);
            if ($ok) {
                $done++;
            }
            $label = $labels[$i] ?? "Etapa " . ($i + 1);
            $segments .=
                '<span class="onboard-stage ' .
                ($ok ? "is-done" : "is-empty") .
                '" title="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    $i +
                        1 .
                        "/" .
                        $total .
                        " · " .
                        $label .
                        ": " .
                        ($ok ? "concluída" : "pendente"),
                ) .
                '" aria-hidden="true"></span>';
        }
        return '<span class="onboard-progress" role="img" aria-label="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e("Onboard: " . $done . " de " . $total . " etapas concluídas") .
            '" title="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e("Onboard: " . $done . " de " . $total . " etapas concluídas") .
            '">' .
            $segments .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($done . "/" . $total) .
            "</b></span>";
    
    }

    public static function admin_clinic_detail_item(
        string $iconName,
        string $label,
        string $value,
        string $note = "",
    ): string 
    {
    
        $value = trim($value) !== "" ? $value : "Não informado";
        return '<div class="admin-clinic-detail-item"><span class="admin-clinic-detail-item-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
            '</span><div><small>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            '</small><b>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value) .
            '</b>' .
            ($note !== "" ? '<span>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($note) . '</span>' : "") .
            '</div></div>';
    
    }

    public static function admin_alert_contact_label(
        ?array $user,
        int $currentUserId = 0,
        bool $asSupport = false,
    ): string 
    {
    
        if ($asSupport) {
            return "Suporte";
        }
        $name = mb_trim((string) ($user["name"] ?? ""));
        if ($name === "") {
            return "Desenvolvedor";
        }
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($name);
    
    }

    public static function admin_performance_format_ms(float $ms): string
    
    {
    
        return number_format(max(0.0, $ms), 2, ",", ".") . " ms";
    
    }

    public static function admin_performance_rows_html(array $rows): string
    
    {
    
        if (!$rows) {
            return '<div class="empty">Ainda não há eventos de rota nos últimos 10 dias. Use o sistema por alguns minutos e retorne a esta tela.</div>';
        }
        $h =
            '<div class="admin-performance-table-wrap"><table class="admin-performance-table"><thead><tr><th>Rota</th><th>Requisições</th><th>Tempo médio</th><th>Máximo</th><th>Falhas</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $route = (string) ($r["route"] ?? "");
            $count = (int) ($r["count"] ?? 0);
            $avg = (float) ($r["avg_ms"] ?? 0);
            $max = (float) ($r["max_ms"] ?? 0);
            $errors = (int) ($r["errors"] ?? 0);
            $tone = $avg >= 1500 ? "is-bad" : ($avg >= 800 ? "is-warn" : "is-ok");
            $h .=
                '<tr class="' .
                $tone .
                '"><td><code>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($route) .
                "</code></td><td>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($count) .
                "</td><td><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_performance_format_ms($avg)) .
                "</b></td><td>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_performance_format_ms($max)) .
                "</td><td>" .
                ($errors > 0
                    ? '<span class="status danger">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($errors) . "</span>"
                    : '<span class="status ok">0</span>') .
                "</td></tr>";
        }
        return $h . "</tbody></table></div>";
    
    }

    public static function developer_overview_html(array $context, callable $href, callable $actionLabel): string

    {

        $actions = (array) ($context["actions"] ?? []);
        $checks = (array) ($context["checks"] ?? []);
        $locks = (int) ($context["locks"] ?? 0);
        $scopeViolations = (int) ($context["scope_violations"] ?? 0);
        $readOnly = (int) ($context["read_only"] ?? 0);
        $trialEnding = (int) ($context["trial_ending"] ?? 0);
        $onboardingPending = (int) ($context["onboarding_pending"] ?? 0);
        $auditChainOk = !empty(($checks["audit_chain"] ?? [])["ok"]);
        $versionContractOk = !empty(($checks["version_contract"] ?? [])["ok"]);
        $integrityOk = $auditChainOk && (int) ($checks["integrity_alerts"] ?? 0) === 0;
        $securityOk = $locks === 0 && $scopeViolations === 0;
        $critical = empty($checks["database"]) || empty($checks["storage"]) || !$auditChainOk || !$versionContractOk;
        $state = $critical ? "Crítico" : ($actions ? "Atenção" : "Operacional");
        $stateCopy = match ($state) {
            "Crítico" => "Há uma condição estrutural que exige intervenção técnica.",
            "Atenção" => "A plataforma está disponível, mas existem decisões ou sinais que merecem revisão.",
            default => "Nenhuma condição relevante exige intervenção neste momento.",
        };
        $stateClass = match ($state) {
            "Crítico" => "is-critical",
            "Atenção" => "is-attention",
            default => "is-operational",
        };
        $statusCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="developer-control-status"><div><span class="eyebrow">Estado do Prontoo</span><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                '</h2><p>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($stateCopy) .
                '</p><small>Atualizado em ' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(date("d/m/Y · H:i")) .
                '</small></div><span class="developer-state-badge ' .
                $stateClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($critical ? "crisis_alert" : ($actions ? "notification_important" : "verified")) .
                '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                '</span></span></div>',
            "developer-status-card",
        );
        $actionsBody = $actions
            ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($actions)
            : '<div class="developer-empty-state">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check_circle") .
                '<div><b>Nada precisa de você agora</b><p>Não há pendências ou sinais relevantes aguardando intervenção.</p></div></div>';
        $actionsCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Precisa de você</h2><p>Somente decisões e sinais que justificam intervenção.</p></div></div>' . $actionsBody,
            "developer-actions-card",
        );
        $clinicStats =
            '<div class="stats-grid developer-compact-stats">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Somente leitura", $readOnly, "lock", "consultórios") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Vencem em 7 dias", $trialEnding, "hourglass_top", "assinaturas") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Onboarding pendente", $onboardingPending, "playlist_add_check", "consultórios") .
            "</div>";
        $clinicsCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Consultórios</h2><p>Ciclo de vida, adoção e situação comercial.</p></div></div>' .
                $clinicStats .
                '<a class="ghost small developer-domain-link" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href("admin_clinics")) .
                '">' .
                (string) $actionLabel("Abrir Consultórios", "arrow_forward") .
                "</a>",
            "developer-domain-card",
        );
        $platformStats =
            '<div class="stats-grid developer-platform-stats">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Banco", !empty($checks["database"]) ? "Normal" : "Atenção", "database", "conectividade") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Storage", !empty($checks["storage"]) ? "Normal" : "Atenção", "folder", "persistência") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Integridade", $integrityOk ? "Normal" : "Atenção", "verified_user", "auditoria") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Segurança", $securityOk ? "Normal" : "Atenção", "shield", "acesso e escopo") .
            "</div>";
        $platformCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Plataforma</h2><p>Confiabilidade resumida; detalhes aparecem apenas quando necessários.</p></div></div>' .
                $platformStats .
                '<a class="ghost small developer-domain-link" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href("admin_health")) .
                '">' .
                (string) $actionLabel("Abrir Confiabilidade", "arrow_forward") .
                "</a>",
            "developer-domain-card",
        );
        $observabilityCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Observabilidade</h2><p>Latência, volume de requisições e comportamento das rotas ficam fora da visão diária e disponíveis para investigação.</p></div></div><a class="ghost small developer-domain-link" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href("admin_performance")) .
                '">' .
                (string) $actionLabel("Abrir Observabilidade", "monitoring") .
                "</a>",
            "developer-observability-card",
        );
        return $statusCard .
            $actionsCard .
            '<div class="developer-overview-grid">' .
            $clinicsCard .
            $platformCard .
            "</div>" .
            $observabilityCard;
    }

    public static function developer_administration_tools_html(callable $href): string

    {

        $tools = [
            ["groups", "Usuários e pessoas", "Credenciais, vínculos e identidades administrativas da plataforma.", "admin_people"],
            ["campaign", "Comunicação técnica", "Avisos entre perfis do Desenvolvedor e acompanhamento das mensagens recebidas.", "admin_alerts"],
            ["notifications_active", "Avisos globais", "Comunicações institucionais exibidas nos ambientes dos consultórios.", "admin_global_notices"],
            ["construction", "Manutenção", "Modo de manutenção e controles operacionais extraordinários.", "admin_maintenance"],
            ["settings", "Configurações", "Parâmetros globais que não pertencem à operação cotidiana.", "admin_settings"],
            ["history", "Auditoria", "Rastreabilidade administrativa e eventos relevantes da plataforma.", "admin_audit"],
        ];
        $grid = '<div class="developer-tool-grid">';
        foreach ($tools as [$icon, $title, $description, $route]) {
            $grid .=
                '<a class="developer-tool-card" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href($route)) .
                '"><span class="developer-tool-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($icon) .
                '</span><span><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
                '</b><small>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($description) .
                '</small></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a>';
        }
        return '<div class="section-head"><div><h2>Ferramentas administrativas</h2><p>Governança, comunicação e suporte ficam agrupados aqui para não competir com a operação diária.</p></div></div>' .
            $grid .
            "</div>";
    }

}
