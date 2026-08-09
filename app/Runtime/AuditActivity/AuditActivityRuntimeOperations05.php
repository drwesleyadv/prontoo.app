<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AuditActivity;

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

final class AuditActivityRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function page_audit(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("audit");
        $cid = (int) $c["clinic_id"];
        $member = (int) ($_GET["member"] ?? 0);
        $period = (string) ($_GET["period"] ?? "today");
        $selectedDate = (string) ($_GET["date"] ?? "");
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c);
        }
        if (!isset(\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit_period_options($cid, $c)[$period])) {
            $period = "today";
        }
        $limit = 10;
        $offset = max(0, (int) ($_GET["offset"] ?? 0));
        $where = "a.clinic_id=?";
        $p = [$cid];
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit_visibility_filter($c, $where, $p);
        $where .= \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit_period_clause($period, $p, $cid, $c, $selectedDate);
        $team = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit_team_filter_options($cid);
        if ($member > 0 && isset($team[$member])) {
            $where .= " AND a.user_id=?";
            $p[] = $member;
        }
        $rows = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_rows_light($where, $p, $limit, $offset);
        if ((string) ($_GET["ajax"] ?? "") === "1") {
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=utf-8");
                header(
                    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0",
                );
            }
            echo json_encode(
                [
                    "ok" => true,
                    "html" => \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline(
                        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations02::audit_items($rows),
                        "Nenhuma atividade encontrada.",
                    ),
                    "next_offset" => $offset + count($rows),
                    "has_more" => count($rows) === $limit,
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $baseFor = function (array $extra = []) use (
            $member,
            $period,
            $selectedDate,
        ): array {
    
            $base = [];
            if ($member > 0) {
                $base["member"] = $member;
            }
            if ($period === "date") {
                $base["date"] = $selectedDate;
            }
            return array_merge($base, $extra);
        };
        $memberChips =
            '<nav class="activity-filter-chips" aria-label="Filtrar por membro da equipe"><a class="activity-filter-chip ' .
            ($member <= 0 ? "active" : "") .
            '" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("audit", $baseFor(["period" => $period])) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("groups") .
            "<span>Todos</span></a>";
        foreach ($team as $id => $first) {
            $memberChips .=
                '<a class="activity-filter-chip ' .
                ($member === $id ? "active" : "") .
                '" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("audit", $baseFor(["member" => $id, "period" => $period])) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("person") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($first) .
                "</span></a>";
        }
        $memberChips .= "</nav>";
        $periodChips =
            '<nav class="activity-period-filter" aria-label="Selecionar data das atividades">';
        foreach (\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit_period_options($cid, $c) as $value => $label) {
            if ($value === "date") {
                $periodChips .=
                    '<button type="button" class="activity-period-chip ' .
                    ($period === "date" ? "active" : "") .
                    '" data-open-activity-date>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_month") .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                    "</span></button>";
            } else {
                $periodChips .=
                    '<a class="activity-period-chip ' .
                    ($period === $value ? "active" : "") .
                    '" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("audit", $baseFor(["period" => $value])) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(
                        $value === "today"
                            ? "today"
                            : ($value === "yesterday"
                                ? "history"
                                : "event"),
                    ) .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                    "</span></a>";
            }
        }
        $periodChips .= "</nav>";
        $dateDialog =
            '<dialog class="activity-date-dialog" data-activity-date-dialog><form method="get" class="activity-date-form"><input type="hidden" name="r" value="audit">' .
            ($member > 0
                ? '<input type="hidden" name="member" value="' .
                    (int) $member .
                    '">'
                : "") .
            '<input type="hidden" name="period" value="date"><header><strong>Escolher data</strong><button type="button" class="ghost small" data-close-activity-date>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
            '<span>Fechar</span></button></header><input type="date" name="date" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($selectedDate) .
            '" required><div class="form-actions"><button class="primary" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check") .
            "<span>Aplicar</span></button></div></form></dialog>";
        $filters =
            '<section class="activity-filter-panel ds-activity-filter-panel" aria-label="Filtros de atividades">' .
            $memberChips .
            $periodChips .
            $dateDialog .
            "</section>";
        $initial = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline(\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations02::audit_items($rows), "Nenhuma atividade encontrada.");
        $ajaxParams = ["ajax" => "1", "member" => $member, "period" => $period];
        if ($period === "date") {
            $ajaxParams["date"] = $selectedDate;
        }
        $list =
            '<div class="activity-timeline ds-activity-list" data-activity-results data-activity-next-offset="' .
            ($offset + count($rows)) .
            '" data-activity-limit="' .
            $limit .
            '" data-activity-url="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("audit", $ajaxParams)) .
            '">' .
            $initial .
            '</div><div class="activity-load-sentinel" data-activity-sentinel aria-hidden="true"></div>';
        $sectionHead =
            '<header class="ds-section-head activity-section-head"><span class="notice-minimal-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("history") .
            "</span><div><strong>Registro de atividades</strong><span>Filtros e linha do tempo em leitura compacta.</span></div></header>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Atividades",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Atividades",
                "Histórico direto das ações realizadas no consultório.",
            ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    $sectionHead . $filters . $list,
                    "activity-screen-card patient-list-card ds-filter-list-block",
                ),
        );
    
    }
}
