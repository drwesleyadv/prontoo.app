<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Dashboards;

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

final class DashboardsRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function page_triagem_painel(array $c): void
    
    {
    
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        [$todayStart, $todayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range(
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        $today = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.dashboards.02.page_triagem_painel.01', [$cid, $todayStart, $todayEnd], [])->fetchAll();
        $names = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::appointment_patient_names($today, $cid);
        $doctorIds = \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($today, "doctor_user_id");
        $users = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map("users_name", $doctorIds);
        $now = time();
        $waiting = [];
        $late = [];
        $next = [];
        foreach ($today as $a) {
            $code = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a);
            $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["start_at"]) ?: 0;
            if (in_array($code, ["chegou", "em_preparo"], true)) {
                $waiting[] = $a;
            } elseif (
                in_array($code, ["agendado", "confirmado"], true) &&
                $ts > 0 &&
                $ts < $now
            ) {
                $late[] = $a;
            } elseif (!\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_is_terminal($a) && $ts >= $now) {
                $next[] = $a;
            }
        }
        $taskOpen =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.dashboards.02.page_triagem_painel.02', [$cid, $uid], []) ?? 0);
        $taskDue =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.dashboards.02.page_triagem_painel.03', [$cid, $uid], []) ?? 0);
        $row = function (array $a, string $class = "neutral") use (
            $names,
            $users,
        ): string {
    
            $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["start_at"]);
            $pid = (int) ($a["patient_link_id"] ?? 0);
            $doctor = $users[(int) ($a["doctor_user_id"] ?? 0)]["name"] ?? "";
            $reason = mb_trim((string) ($a["reason"] ?? ""));
            $m = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_meta($a, "assistente");
            $rowClass = $m["class"] ?: $class;
            $measure = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_compact_html($a, "assistente");
            return '<a class="reception-row reception-row-' .
                $rowClass .
                ' journey-row" href="' .
                ($pid > 0
                    ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => $pid])
                    : \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments")) .
                '"><span class="reception-time">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $a["start_at"])) .
                '</span><span class="reception-main"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($names[$pid] ?? "Paciente não identificado") .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    trim(
                        ($doctor !== ""
                            ? "Profissional: " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($doctor)
                            : "") . ($reason !== "" ? " · " . $reason : ""),
                        " ·",
                    ),
                ) .
                "</small>" .
                $measure .
                '</span><span class="pill ' .
                $rowClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($m["icon"]) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($m["label"]) .
                "</span></a>";
        };
        $waitingHtml = "";
        foreach (array_slice($waiting, 0, 8) as $a) {
            $waitingHtml .= $row($a, "warn");
        }
        if ($waitingHtml === "") {
            $waitingHtml =
                '<div class="empty compact-empty">Nenhum paciente aguardando triagem agora.</div>';
        }
        $lateHtml = "";
        foreach (array_slice($late, 0, 5) as $a) {
            $lateHtml .= $row($a, "bad");
        }
        if ($lateHtml === "") {
            $lateHtml =
                '<div class="empty compact-empty">Nenhum atraso crítico para triagem.</div>';
        }
        $nextHtml = "";
        foreach (array_slice($next, 0, 6) as $a) {
            $nextHtml .= $row($a, "neutral");
        }
        if ($nextHtml === "") {
            $nextHtml =
                '<div class="empty compact-empty">Nenhum próximo atendimento no momento.</div>';
        }
        $kpis =
            '<div class="kpis reception-kpis"><div>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("clinical_notes") .
            "<p><b>" .
            count($waiting) .
            "</b><span>Aguardando preparo</span></p></div><div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("warning") .
            "<p><b>" .
            count($late) .
            "</b><span>Atrasos</span></p></div><div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("task_alt") .
            "<p><b>" .
            $taskOpen .
            "</b><span>Tarefas abertas</span></p></div><div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("timer") .
            "<p><b>" .
            $taskDue .
            "</b><span>Vencem em breve</span></p></div></div>";
        $body =
            $kpis .
            '<section class="reception-panel-grid triage-panel-grid">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Agora na triagem</h2><p class="muted-copy">Pacientes que já chegaram e precisam de preparo antes da consulta.</p><div class="reception-list">' .
                    $waitingHtml .
                    "</div>",
                "reception-card reception-now",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Atenção</h2><p class="muted-copy">Atrasos e tarefas próximas do vencimento.</p><div class="reception-list">' .
                    $lateHtml .
                    '</div><a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks") .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("task_alt") .
                    "<span>Abrir tarefas</span></a>",
                "reception-card",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Próximos horários</h2><p class="muted-copy">Organize materiais e preparo antes da chegada.</p><div class="reception-list">' .
                    $nextHtml .
                    "</div>",
                "reception-card reception-full",
            ) .
            "</section>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Painel",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Painel do Assistente",
                "Fila mínima para os próximos atos: preparar quem chegou, observar atrasos e abrir tarefas.",
            ) . $body,
        );
    
    }

    public static function manager_metric_val(string $query, array $params = [], array $context = []): int
    
    {
    
        try {
            return (int) (\Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.dashboards.02.manager_metric_val.01', $params, ['query' => $query] + $context) ?? 0);
        } catch (Throwable $e) {
            error_log("[Prontoo manager metric] " . $e->getMessage());
            return 0;
        }
    
    }

    public static function manager_metric_row(string $query, array $params = [], array $context = []): array
    
    {
    
        try {
            return \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.dashboards.02.manager_metric_row.01', $params, ['query' => $query] + $context) ?: [];
        } catch (Throwable $e) {
            error_log("[Prontoo manager metric row] " . $e->getMessage());
            return [];
        }
    
    }

    public static function manager_action_card(
        string $iconName,
        string $title,
        string $body,
        string $route,
        string $label = "Abrir",
    ): string 
    {
    
        $inner =
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
            "<div><strong>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
            "</strong><p>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($body) .
            "</p><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span></div>";
        return $route !== ""
            ? '<a class="manager-action" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route) .
                    '">' .
                    $inner .
                    "</a>"
            : '<article class="manager-action">' . $inner . "</article>";
    
    }
}
