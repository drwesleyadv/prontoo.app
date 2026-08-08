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
        [$todayStart, $todayEnd] = app_local_day_utc_range(
            app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        $today = q(
            "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<? AND status NOT IN ('cancelado') ORDER BY start_at ASC LIMIT 160",
            [$cid, $todayStart, $todayEnd],
        )->fetchAll();
        $names = appointment_patient_names($today, $cid);
        $doctorIds = int_ids($today, "doctor_user_id");
        $users = fetch_map("pi_users", $doctorIds, "id,name");
        $now = time();
        $waiting = [];
        $late = [];
        $next = [];
        foreach ($today as $a) {
            $code = appointment_status_code($a);
            $ts = app_storage_timestamp($a["start_at"]) ?: 0;
            if (in_array($code, ["chegou", "em_preparo"], true)) {
                $waiting[] = $a;
            } elseif (
                in_array($code, ["agendado", "confirmado"], true) &&
                $ts > 0 &&
                $ts < $now
            ) {
                $late[] = $a;
            } elseif (!appointment_is_terminal($a) && $ts >= $now) {
                $next[] = $a;
            }
        }
        $taskOpen =
            (int) (val(
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND (assigned_to=? OR (assigned_to IS NULL AND (target_scope='role' AND target_role='assistente' OR target_scope='clinic')))",
                [$cid, $uid],
            ) ?? 0);
        $taskDue =
            (int) (val(
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at<=DATE_ADD(NOW(), INTERVAL 30 MINUTE) AND (assigned_to=? OR (assigned_to IS NULL AND (target_scope='role' AND target_role='assistente' OR target_scope='clinic')))",
                [$cid, $uid],
            ) ?? 0);
        $row = function (array $a, string $class = "neutral") use (
            $names,
            $users,
        ): string {
    
            $ts = app_storage_timestamp($a["start_at"]);
            $pid = (int) ($a["patient_link_id"] ?? 0);
            $doctor = $users[(int) ($a["doctor_user_id"] ?? 0)]["name"] ?? "";
            $reason = mb_trim((string) ($a["reason"] ?? ""));
            $m = appointment_journey_meta($a, "assistente");
            $rowClass = $m["class"] ?: $class;
            $measure = appointment_journey_compact_html($a, "assistente");
            return '<a class="reception-row reception-row-' .
                $rowClass .
                ' journey-row" href="' .
                ($pid > 0
                    ? href("patient", ["id" => $pid])
                    : href("appointments")) .
                '"><span class="reception-time">' .
                e(app_time_br((string) $a["start_at"])) .
                '</span><span class="reception-main"><strong>' .
                e($names[$pid] ?? "Paciente não identificado") .
                "</strong><small>" .
                e(
                    trim(
                        ($doctor !== ""
                            ? "Profissional: " . first_name($doctor)
                            : "") . ($reason !== "" ? " · " . $reason : ""),
                        " ·",
                    ),
                ) .
                "</small>" .
                $measure .
                '</span><span class="pill ' .
                $rowClass .
                '">' .
                icon($m["icon"]) .
                e($m["label"]) .
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
            icon("clinical_notes") .
            "<p><b>" .
            count($waiting) .
            "</b><span>Aguardando preparo</span></p></div><div>" .
            icon("warning") .
            "<p><b>" .
            count($late) .
            "</b><span>Atrasos</span></p></div><div>" .
            icon("task_alt") .
            "<p><b>" .
            $taskOpen .
            "</b><span>Tarefas abertas</span></p></div><div>" .
            icon("timer") .
            "<p><b>" .
            $taskDue .
            "</b><span>Vencem em breve</span></p></div></div>";
        $body =
            $kpis .
            '<section class="reception-panel-grid triage-panel-grid">' .
            card(
                '<h2>Agora na triagem</h2><p class="muted-copy">Pacientes que já chegaram e precisam de preparo antes da consulta.</p><div class="reception-list">' .
                    $waitingHtml .
                    "</div>",
                "reception-card reception-now",
            ) .
            card(
                '<h2>Atenção</h2><p class="muted-copy">Atrasos e tarefas próximas do vencimento.</p><div class="reception-list">' .
                    $lateHtml .
                    '</div><a class="ghost small" href="' .
                    href("tasks") .
                    '">' .
                    icon("task_alt") .
                    "<span>Abrir tarefas</span></a>",
                "reception-card",
            ) .
            card(
                '<h2>Próximos horários</h2><p class="muted-copy">Organize materiais e preparo antes da chegada.</p><div class="reception-list">' .
                    $nextHtml .
                    "</div>",
                "reception-card reception-full",
            ) .
            "</section>";
        page(
            "Painel",
            page_head(
                "Painel do Assistente",
                "Fila mínima para os próximos atos: preparar quem chegou, observar atrasos e abrir tarefas.",
            ) . $body,
        );
    
    }

    public static function manager_metric_val(string $sql, array $params = []): int
    
    {
    
        try {
            return (int) (val($sql, $params) ?? 0);
        } catch (Throwable $e) {
            error_log("[Prontoo manager metric] " . $e->getMessage());
            return 0;
        }
    
    }

    public static function manager_metric_row(string $sql, array $params = []): array
    
    {
    
        try {
            return one($sql, $params) ?: [];
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
            icon($iconName) .
            "<div><strong>" .
            e($title) .
            "</strong><p>" .
            e($body) .
            "</p><span>" .
            e($label) .
            "</span></div>";
        return $route !== ""
            ? '<a class="manager-action" href="' .
                    href($route) .
                    '">' .
                    $inner .
                    "</a>"
            : '<article class="manager-action">' . $inner . "</article>";
    
    }
}
