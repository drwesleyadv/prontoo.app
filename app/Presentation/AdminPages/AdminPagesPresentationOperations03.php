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
        if ($primaryLabel === "") {
            $primaryLabel = "Carregamento";
        }
        if ($secondaryLabel === "") {
            $secondaryLabel = "Resposta";
        }
        if (!in_array($valueType, ["ms", "count"], true)) {
            $valueType = "ms";
        }
        $recentPoints = max(1, (int) ($presentation["recent_points"] ?? 5));
        $middlePoints = max(1, (int) ($presentation["middle_points"] ?? 31));
        $recentTitle = mb_trim((string) ($presentation["recent_title"] ?? "Carregamento médio dos últimos 5 minutos"));
        $middleTitle = mb_trim((string) ($presentation["middle_title"] ?? "Carregamento médio dos últimos 30 minutos"));
        $overallTitle = mb_trim((string) ($presentation["overall_title"] ?? "Carregamento médio das últimas 24 horas"));
        $summaryLead = mb_trim((string) ($presentation["summary_lead"] ?? "indicadores exibem somente o tempo de carregamento."));
        $loadValues = array_map( fn($r) => (float) ($r["value"] ?? 0), $loadSeries);
        $responseValues = array_map(
             fn($r) => (float) ($r["value"] ?? 0),
            $responseSeries,
        );
        if (!$loadValues) {
            $loadValues = [0.0];
        }
        if (!$responseValues) {
            $responseValues = [0.0];
        }
        $max = max(max($loadValues), max($responseValues));
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
        $baseline = $padT + $plotH;
        $makePoints = static function (array $series) use (
            $padL,
            $plotW,
            $padT,
            $plotH,
            $max,
        ): array {
    
            $n = count($series);
            $points = [];
            foreach ($series as $idx => $row) {
                $v = max(0.0, (float) ($row["value"] ?? 0));
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
                e(number_format($gv, 0, ",", ".")) .
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
                    e($pt[3]) .
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
                e(
                    $pt[4] .
                        " · " . $primaryLabel . " " .
                        admin_metric_value_label($pt[2], $valueType),
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
                e(
                    $pt[4] .
                        " · " . $secondaryLabel . " " .
                        admin_metric_value_label($pt[2], $valueType),
                ) .
                "</title></circle>";
        }
        $recentValues = array_slice($loadValues, -$recentPoints);
        $recentValue = $valueType === "count"
            ? ($recentValues
                ? array_sum($recentValues) / count($recentValues)
                : 0.0)
            : admin_metric_recent_average($loadSeries, $recentPoints);
        $overallValue = $valueType === "count"
            ? (count($loadValues) > 0
                ? array_sum($loadValues) / count($loadValues)
                : 0.0)
            : admin_metric_recent_average($loadSeries, count($loadSeries));
        $middleValues = array_slice($loadValues, -$middlePoints);
        $middleValue = $valueType === "count"
            ? (count($middleValues) > 0
                ? array_sum($middleValues) / count($middleValues)
                : 0.0)
            : admin_metric_recent_average($loadSeries, $middlePoints);
        $recentCompact = admin_metric_value_compact($recentValue, $valueType);
        $middleCompact = admin_metric_value_compact($middleValue, $valueType);
        $overallCompact = admin_metric_value_compact($overallValue, $valueType);
        $pills =
            '<span title="' .
            e($recentTitle) .
            '" aria-label="' .
            e($recentTitle . ": " . $recentCompact) .
            '">' .
            icon("radio_button_checked") .
            "<b>" .
            e($recentCompact) .
            "</b></span>" .
            '<span title="' .
            e($middleTitle) .
            '" aria-label="' .
            e($middleTitle . ": " . $middleCompact) .
            '">' .
            icon("timer") .
            "<b>" .
            e($middleCompact) .
            "</b></span>" .
            '<span title="' .
            e($overallTitle) .
            '" aria-label="' .
            e($overallTitle . ": " . $overallCompact) .
            '">' .
            icon("calendar_today") .
            "<b>" .
            e($overallCompact) .
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
                e(
                    $lastLoad[4] .
                        " · " . $primaryLabel . " " .
                        admin_metric_value_label($lastLoad[2], $valueType),
                ) .
                "</title></circle>"
            : "";
        $responseCircle = $lastResp
            ? '<circle cx="' .
                $lastResp[0] .
                '" cy="' .
                $lastResp[1] .
                '" r="3.2" class="metric-chart-dot metric-chart-dot-response"><title>' .
                e(
                    $lastResp[4] .
                        " · " . $secondaryLabel . " " .
                        admin_metric_value_label($lastResp[2], $valueType),
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
                ? '<path d="' . e($loadFill) . '" class="metric-chart-fill-load"/>'
                : "";
        $responseArea =
            $responseFill !== ""
                ? '<path d="' .
                    e($responseFill) .
                    '" class="metric-chart-fill-response"/>'
                : "";
        $fillAreas = $loadArea . $responseArea;
        return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-metric-value-type="' .
            e($valueType) .
            '" data-ds-card="admin-dual-area-chart" aria-label="' .
            e($title) .
            '"><header><div class="metric-chart-title">' .
            icon($iconName) .
            "<div><h3>" .
            e($title) .
            '</h3></div></div><div class="metric-chart-value"><div class="metric-chart-pills">' .
            $pills .
            '</div></div></header><p class="sr-only">' .
            e($summary) .
            '</p><svg viewBox="0 0 ' .
            $w .
            " " .
            $h .
            '" aria-hidden="true" focusable="false"><g>' .
            $grid .
            "</g>" .
            $fillAreas .
            ($loadD !== ""
                ? '<path d="' . e($loadD) . '" class="metric-chart-line-load"/>'
                : "") .
            ($responseD !== ""
                ? '<path d="' .
                    e($responseD) .
                    '" class="metric-chart-line-response"/>'
                : "") .
            $loadCircle .
            $responseCircle .
            $hover .
            $ticks .
            "</svg></article>";
    
    }

    public static function admin_telemetry_variation_note(?float $variation): string
    
    {
        if ($variation === null) {
            return "últimos 10 dias · sem base comparável no período anterior";
        }
        if (abs($variation) < 0.0000005) {
            $variation = 0.0;
        }
        $prefix = $variation > 0 ? "+" : "";
        return "últimos 10 dias · " .
            $prefix .
            number_format($variation, 2, ",", ".") .
            "% vs. 10 dias anteriores";
    
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
            e($label . ": " . ($ok ? "usado" : "pendente")) .
            '" aria-label="' .
            e($label . ": " . ($ok ? "usado" : "pendente")) .
            '">' .
            icon($ok ? "check_circle" : "radio_button_unchecked") .
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
                e(
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
            e("Onboard: " . $done . " de " . $total . " etapas concluídas") .
            '" title="' .
            e("Onboard: " . $done . " de " . $total . " etapas concluídas") .
            '">' .
            $segments .
            "<b>" .
            e($done . "/" . $total) .
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
            icon($iconName) .
            '</span><div><small>' .
            e($label) .
            '</small><b>' .
            e($value) .
            '</b>' .
            ($note !== "" ? '<span>' . e($note) . '</span>' : "") .
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
        return first_name($name);
    
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
                e($route) .
                "</code></td><td>" .
                n($count) .
                "</td><td><b>" .
                e(admin_performance_format_ms($avg)) .
                "</b></td><td>" .
                e(admin_performance_format_ms($max)) .
                "</td><td>" .
                ($errors > 0
                    ? '<span class="status danger">' . n($errors) . "</span>"
                    : '<span class="status ok">0</span>') .
                "</td></tr>";
        }
        return $h . "</tbody></table></div>";
    
    }
}
