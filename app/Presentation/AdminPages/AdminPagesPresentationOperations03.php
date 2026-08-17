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

    public static function developer_clinic_search_html(
        string $search,
        string $view,
        callable $href,
    ): string {
        $search = mb_trim($search);
        $view = preg_replace("/[^a-z_]/", "", $view) ?: "all";
        if (!in_array($view, ["all", "attention", "onboarding", "readonly", "expiring"], true)) {
            $view = "all";
        }
        $clear = $search !== "" || $view !== "all"
            ? '<a class="ghost small" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href("admin_clinics")) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                '<span>Limpar</span></a>'
            : "";
        return '<section class="patient-directory-search ds-search-block"><form method="get" class="patient-search-bar" role="search"><input type="hidden" name="r" value="admin_clinics"><input type="hidden" name="view" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($search !== "" ? "all" : $view) .
            '"><label class="search-field"><input name="q" type="search" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($search) .
            '" placeholder="Nome, área, status ou vencimento" autocomplete="off" aria-label="Buscar consultório por nome, área, status ou vencimento"></label><button class="primary small" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("search") .
            '<span>Busca rápida</span></button>' .
            $clear .
            '</form></section>';
    }

    public static function developer_clinic_directory_html(
        array $rows,
        string $view,
        string $search,
        callable $href,
        callable $dateLabel,
    ): string {
        $search = mb_trim($search);
        $searchMode = $search !== "";
        $view = preg_replace("/[^a-z_]/", "", $view) ?: "all";
        if (!in_array($view, ["all", "attention", "onboarding", "readonly", "expiring"], true)) {
            $view = "all";
        }
        if ($searchMode) {
            $view = "all";
        }
        $filterSpecs = [
            "all" => ["Todos", "format_list_bulleted"],
            "attention" => ["Atenção", "priority_high"],
            "onboarding" => ["Onboarding", "playlist_add_check"],
            "readonly" => ["Somente leitura", "lock"],
            "expiring" => ["Vencendo", "event_upcoming"],
        ];
        $normalFilters = "";
        foreach ($filterSpecs as $filterKey => [$filterLabel, $filterIcon]) {
            $activeFilter = !$searchMode && $view === $filterKey;
            $normalFilters .= '<a class="patient-filter-chip ds-filter-chip ' .
                ($activeFilter ? "active is-active" : "") .
                '" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    (string) $href("admin_clinics", $filterKey === "all" ? [] : ["view" => $filterKey]),
                ) .
                '"' .
                ($activeFilter ? ' aria-current="page"' : "") .
                '>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($filterIcon) .
                '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($filterLabel) .
                '</span></a>';
        }
        $bodyRows = "";
        $foundCount = 0;
        $now = time();
        foreach ($rows as $entry) {
            $r = (array) ($entry["clinic"] ?? []);
            $billing = (array) ($entry["billing"] ?? []);
            $id = (int) ($r["id"] ?? 0);
            $professionals = (int) ($entry["professionals"] ?? 0);
            $collaborators = (int) ($entry["collaborators"] ?? 0);
            $globalAdminOwned = !empty($entry["global_admin_owned"]);
            $dueCandidate = mb_trim((string) ($billing["paid_until"] ?? ""));
            if ($dueCandidate === "") {
                $dueCandidate = mb_trim((string) ($billing["trial_ends_at"] ?? ""));
            }
            $dueTimestamp = $dueCandidate !== "" ? strtotime($dueCandidate) : false;
            $expiresSoon = empty($billing["exempt"]) &&
                $dueTimestamp !== false &&
                $dueTimestamp >= $now &&
                $dueTimestamp <= $now + 7 * 86400;
            $onboardingPending = (int) ($r["onboarding_done"] ?? 0) !== 1;
            $needsAttention = (int) ($r["active"] ?? 0) !== 1 || !empty($billing["read_only"]) || $onboardingPending || $expiresSoon;
            $matchesFilter = match ($view) {
                "attention" => $needsAttention,
                "onboarding" => $onboardingPending,
                "readonly" => !empty($billing["read_only"]),
                "expiring" => $expiresSoon,
                default => true,
            };
            if (!$matchesFilter) {
                continue;
            }
            $attentionClass = "is-stable";
            $attentionIcon = "verified";
            $attentionLabel = "Ativo";
            $attentionNote = "";
            if ((int) ($r["active"] ?? 0) !== 1) {
                $attentionClass = "is-muted";
                $attentionIcon = "pause_circle";
                $attentionLabel = "Inativo";
                $attentionNote = "Consultório desativado";
            } elseif (!empty($billing["exempt"])) {
                $attentionClass = "is-exempt";
                $attentionIcon = "workspace_premium";
                $attentionLabel = "Isento";
                $attentionNote = $globalAdminOwned
                    ? "Isento do Desenvolvedor fora das estatísticas"
                    : "Isento de cobrança";
            } elseif (!empty($billing["read_only"])) {
                $attentionClass = "is-critical";
                $attentionIcon = "lock";
                $attentionLabel = "Somente leitura";
                $attentionNote = "Alterações temporariamente bloqueadas";
            }
            $actions = '<a class="primary small" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href("admin_clinics", ["clinic_id" => $id])) .
                '" aria-label="Abrir gestão do consultório">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("folder_open") .
                '<span>Gerenciar</span></a>';
            $createdLabel = (string) $dateLabel($r["created_at"] ?? "");
            $dueLabel = !empty($billing["exempt"])
                ? "Isento"
                : (mb_trim((string) ($billing["paid_until"] ?? "")) !== ""
                    ? (string) $dateLabel($billing["paid_until"])
                    : (!empty($billing["trial_active"])
                        ? (string) $dateLabel($billing["trial_ends_at"] ?? "")
                        : "Sem vencimento"));
            $professionLabel = mb_trim((string) ($r["responsible_profession"] ?? "")) ?: "Área não informada";
            $adminStatsNote = !empty($billing["exempt"]) && $globalAdminOwned
                ? '<span class="ds-clinic-test-pill">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("bar_chart_off") .
                    '<span>Fora das estatísticas</span></span>'
                : "";
            $searchData = implode(" ", [
                (string) ($r["display_name"] ?? ""),
                $professionLabel,
                $attentionLabel,
                $attentionNote,
                $createdLabel,
                $dueLabel,
                $onboardingPending ? "Onboarding pendente" : "Onboarding concluído",
                $expiresSoon ? "Vencendo" : "",
                (string) $professionals,
                (string) $collaborators,
            ]);
            if ($searchMode && mb_stripos($searchData, $search) === false) {
                continue;
            }
            $foundCount++;
            $statusLevel = $attentionClass === "is-critical"
                ? "bad"
                : ($needsAttention && $attentionClass !== "is-exempt" ? "warn" : "ok");
            $clinicMetrics = '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event") .
                '<span>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($createdLabel) . ' · Data Cadastro</span></span>' .
                '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("stethoscope") .
                '<span>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $professionals) . ' profissional(is)</span></span>' .
                '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("groups") .
                '<span>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $collaborators) . ' colaborador(es)</span></span>' .
                '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
                '<span>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($dueLabel) . ' · Vencimento</span></span>';
            $bodyRows .= '<article class="patient-card-row ds-person-row ds-patient-row patient-status-' .
                $statusLevel .
                ' admin-clinic-card-row ' .
                $attentionClass .
                '"><span class="patient-card-avatar ds-person-avatar" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("home_health") .
                '</span><div class="patient-card-main ds-person-main"><div class="patient-card-title ds-person-title"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($r["display_name"] ?? "Consultório")) .
                '</strong><span class="pill patient-status-pill ds-status-pill ' .
                $statusLevel .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($attentionIcon) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($attentionLabel) .
                '</span>' .
                $adminStatsNote .
                '</div><div class="patient-card-meta ds-person-meta"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("work") .
                '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(((int) ($r["active"] ?? 0) ? "Operando" : "Inativo") . " · " . $professionLabel) .
                '</span></span>' .
                ($attentionNote !== ""
                    ? '<span>' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("info") .
                        '<span>' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($attentionNote) .
                        '</span></span>'
                    : "") .
                $clinicMetrics .
                '</div></div><div class="patient-card-actions ds-person-actions">' .
                $actions .
                '</div></article>';
        }
        $filters = $searchMode
            ? '<span class="patient-filter-chip ds-filter-chip active is-active patient-filter-found" aria-current="page">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("manage_search") .
                '<span>Encontrados</span><small>' .
                number_format($foundCount, 0, ",", ".") .
                '</small></span>' .
                $normalFilters
            : $normalFilters;
        $filterNav = '<nav class="patient-filter-chips ds-filter-list-chips" aria-label="Filtros de consultórios">' .
            $filters .
            '</nav>';
        $emptyMessage = $searchMode
            ? "Nenhum consultório encontrado para esta busca."
            : "Nenhum consultório corresponde a este filtro.";
        $table = '<div class="patient-directory-list ds-person-list ds-patient-list">' .
            ($bodyRows !== ""
                ? $bodyRows
                : '<div class="empty patient-directory-empty">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("manage_search") .
                    '<strong>' .
                    $emptyMessage .
                    '</strong><span>Altere a busca ou escolha outro filtro para ampliar os resultados.</span></div>') .
            '</div>';
        return $filterNav . $table;
    }

    public static function developer_overview_html(array $context, callable $href, callable $actionLabel): string
    {
        $actions = (array) ($context["actions"] ?? []);
        $health = (array) ($context["health"] ?? []);
        $healthState = (string) ($health["state"] ?? "Operacional");
        $state = $healthState === "Crítico" ? "Crítico" : ($actions ? "Atenção" : $healthState);
        $critical = $state === "Crítico";
        $hasUnknownEvidence = false;
        foreach ((array) ($health["dimensions"] ?? []) as $dimension) {
            if ((string) ($dimension["state"] ?? "") === "unknown") {
                $hasUnknownEvidence = true;
                break;
            }
        }
        $stateCopy = match ($state) {
            "Crítico" => "Existe uma condição estrutural que exige intervenção técnica.",
            "Atenção" => $hasUnknownEvidence
                ? "Uma ou mais evidências de saúde precisam ser renovadas antes de considerar a plataforma normal."
                : "Há decisões ou exceções que justificam sua revisão.",
            default => "Nada exige intervenção neste momento.",
        };
        $stateClass = match ($state) {
            "Crítico" => "is-critical",
            "Atenção" => "is-attention",
            default => "is-operational",
        };
        $updatedAt = mb_trim((string) ($health["updated_at"] ?? ""));
        $freshnessLabel = mb_trim((string) ($health["freshness_label"] ?? ""));
        $statusCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="developer-control-status"><div><span class="eyebrow">Estado do Prontoo</span><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                '</h2><p>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($stateCopy) .
                '</p>' .
                ($freshnessLabel !== ""
                    ? '<small>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(ucfirst($freshnessLabel)) . '</small>'
                    : ($updatedAt !== "" ? '<small>Atualizado em ' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($updatedAt) . '</small>' : "")) .
                '</div><span class="developer-state-badge ' .
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
                '<div><b>Nada precisa de você agora</b><p>Decisões, incidentes e bloqueios relevantes aparecerão aqui.</p></div></div>';
        $actionsCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Precisa de você</h2><p>Somente itens que pedem decisão ou intervenção.</p></div></div>' . $actionsBody,
            "developer-actions-card",
        );
        return '<section class="developer-overview-minimal">' . $statusCard . $actionsCard . '</section>';
    }


    public static function developer_administration_tools_html(callable $href): string
    {
        $primary = [
            ["groups", "Usuários", "Credenciais, vínculos e identidades administrativas.", "admin_people"],
            ["mail", "Mensagens internas", "Comunicação restrita aos perfis do Desenvolvedor.", "admin_alerts"],
            ["notifications_active", "Avisos aos consultórios", "Comunicações institucionais exibidas nos ambientes clínicos.", "admin_global_notices"],
            ["history", "Auditoria", "Rastreabilidade administrativa e eventos relevantes.", "admin_audit"],
        ];
        $advanced = [
            ["settings", "Configurações", "Parâmetros globais de baixa frequência.", "admin_settings"],
            ["construction", "Manutenção", "Controles extraordinários de disponibilidade.", "admin_maintenance"],
        ];
        $render = static function (array $tools) use ($href): string {
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
            return $grid . '</div>';
        };
        return '<div class="section-head"><div><h2>Administração</h2><p>Acesso, comunicação e rastreabilidade em um único lugar.</p></div></div>' .
            $render($primary) .
            '<details class="form-panel developer-advanced-tools"><summary><span>Configuração avançada</span></summary>' .
            $render($advanced) .
            '</details>';
    }


    public static function admin_observability_html(array $summary, string $telemetryCharts): string
    {
        $rows = isset($summary["routes"]) && is_array($summary["routes"])
            ? $summary["routes"]
            : [];
        $total = (int) ($summary["total"] ?? 0);
        $avg = (float) ($summary["avg_ms"] ?? 0);
        $slow = $rows[0] ?? null;
        $updated = (string) ($summary["updated_at"] ?? "");
        $errors = 0;
        foreach ($rows as $row) {
            $errors += max(0, (int) ($row["errors"] ?? 0));
        }
        $failureRate = $total > 0 ? ($errors / $total) * 100 : 0.0;
        $failureLabel = number_format($failureRate, $failureRate < 1 ? 2 : 1, ",", ".") . "%";
        $stats =
            '<div class="stats-grid admin-performance-stats">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Requisições · 10d", $total, "route", "eventos canônicos") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Latência média", self::admin_performance_format_ms($avg), "speed", "média geral") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Falhas", $failureLabel, "error", $errors . " evento(s)") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Rota mais lenta",
                $slow ? self::admin_performance_format_ms((float) ($slow["avg_ms"] ?? 0)) : "—",
                "timer",
                $slow ? (string) ($slow["route"] ?? "") : "Sem dados",
            ) .
            '</div>';
        $routesCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head admin-performance-head"><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("route") .
                '<span>Rotas</span></h2><p>Detalhamento dos últimos 10 dias.' .
                ($updated !== "" ? " Última atualização: " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($updated) . "." : "") .
                '</p></div>' .
                self::admin_performance_rows_html($rows),
            "admin-performance-card",
        );
        return '<section class="admin-performance-screen">' .
            $stats .
            $telemetryCharts .
            $routesCard .
            '</section>';
    }


}
