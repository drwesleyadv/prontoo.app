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

final class DashboardsRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function page_home(): void
    
    {
    
        redirect("login");
    
    }

    public static function page_medico_painel(array $c): void
    
    {
    
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        [$todayStart, $todayEnd] = app_local_day_utc_range(
            app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        $today = q(
            "SELECT id,patient_link_id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND doctor_user_id=? AND start_at>=? AND start_at<? ORDER BY start_at ASC LIMIT 120",
            [$cid, $uid, $todayStart, $todayEnd],
        )->fetchAll();
        $total = count($today);
        $done = 0;
        $delaySum = 0;
        $delayCount = 0;
        foreach ($today as $a) {
            if (is_done_appointment($a)) {
                $done++;
            }
            $d = delay_minutes_from_appointment($a);
            if ($d !== null) {
                $delaySum += $d;
                $delayCount++;
            }
        }
        $avgDelay = $delayCount ? (int) round($delaySum / $delayCount, 0, \RoundingMode::HalfAwayFromZero) : null;
        $lateTasks =
            (int) (val(
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status='aberta' AND due_at IS NOT NULL AND due_at<NOW() AND (assigned_to IS NULL OR assigned_to=?)",
                [$cid, $uid],
            ) ?? 0);
        $next = q(
            "SELECT id,patient_link_id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND doctor_user_id=? AND start_at>=NOW() ORDER BY start_at ASC LIMIT 3",
            [$cid, $uid],
        )->fetchAll();
        $names = appointment_patient_names($next, $cid);
        $items = [];
        foreach ($next as $a) {
            $patient =
                $names[(int) ($a["patient_link_id"] ?? 0)] ??
                "Paciente não identificado";
            $delay = delay_minutes_from_appointment($a);
            $startTs = app_storage_timestamp($a["start_at"]);
            $endTs = app_storage_timestamp($a["end_at"]);
            $period =
                app_time_br((string) $a["start_at"], $cid) .
                "–" .
                app_time_br((string) $a["end_at"], $cid);
            $dateLabel = date_br($a["start_at"]);
            $journey = appointment_journey_meta($a, "medico");
            $reason = (string) ($a["reason"] ?: "Rotina");
            $items[] = [
                "badge" => app_time_br((string) $a["start_at"], $cid),
                "time" => $dateLabel . " · " . $period,
                "title" => $patient,
                "body" =>
                    $reason .
                    " · Jornada: " .
                    $journey["phase_label"] .
                    " · " .
                    $journey["label"],
                "meta" =>
                    "Responsável: " .
                    $journey["owner"] .
                    " · Próxima medida: " .
                    $journey["action"] .
                    " · Fim previsto: " .
                    dt_br($a["end_at"]) .
                    ($delay !== null
                        ? " · atraso registrado: " . format_minutes($delay)
                        : ""),
                "class" => "appointment-timeline",
                "html" =>
                    '<div class="appointment-mini">' .
                    audit_preview_for_appointment($cid, (int) $a["id"]) .
                    '<a class="ghost small" href="' .
                    href("patient", ["id" => (int) $a["patient_link_id"]]) .
                    '">' .
                    icon("folder_open") .
                    "<span>Abrir</span></a></div>",
            ];
        }
        $cards =
            '<div class="kpis"><div><b>' .
            e($done . "/" . $total) .
            "</b><span>Consultas hoje</span></div><div><b>" .
            e((string) $lateTasks) .
            "</b><span>Tarefas Atrasadas</span></div><div><b>" .
            e(format_minutes($avgDelay)) .
            "</b><span>Atraso médio do dia</span></div></div>";
        $intro =
            "Resumo prático da rotina clínica: atendimentos do dia, pendências atrasadas e próximos pacientes com contexto auditável.";
        page(
            "Painel",
            page_head("Painel", $intro) .
                $cards .
                card(
                    "<h2>Próximas consultas</h2>" .
                        timeline($items, "Não há consultas nas próximas horas."),
                ),
        );
    
    }

    public static function page_recepcao_painel(array $c): void
    
    {
    
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        $role = (string) ($c["role"] ?? "recepcionista");
        $activeStatuses = ["aberta", "em_andamento", "aguardando"];
        [$todayStart, $todayEnd] = app_local_day_utc_range(
            app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        $today = q(
            "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<? AND status NOT IN ('cancelado') ORDER BY start_at ASC LIMIT 180",
            [$cid, $todayStart, $todayEnd],
        )->fetchAll();
        $blocks = [];
        try {
            $blocks = q(
                "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at<? AND end_at>? ORDER BY start_at ASC LIMIT 80",
                [$cid, $todayEnd, $todayStart],
            )->fetchAll();
        } catch (Throwable $e) {
            $blocks = [];
        }
        $names = appointment_patient_names($today, $cid);
        $doctorIds = int_ids($today, "doctor_user_id");
        $creatorIds = int_ids($blocks, "created_by");
        $users = fetch_map(
            "pi_users",
            array_values(array_unique(array_merge($doctorIds, $creatorIds))),
            "id,name",
        );
        $total = count($today);
        $waiting = 0;
        $late = 0;
        $inCare = 0;
        $finished = 0;
        $next = null;
        $nowTs = time();
        foreach ($today as $a) {
            $status = mb_strtolower((string) ($a["status"] ?? "agendado"));
            $started = !empty($a["consultation_started_at"]);
            $done =
                !empty($a["consultation_finished_at"]) ||
                in_array($status, ["atendimento_concluido", "finalizado"], true);
            $arrived = !empty($a["arrived_at"]);
            $startTs = app_storage_timestamp($a["start_at"]) ?: 0;
            if ($arrived && !$started && !$done) {
                $waiting++;
            }
            if ($started && !$done) {
                $inCare++;
            }
            if ($done) {
                $finished++;
            }
            if (
                !$arrived &&
                !$started &&
                !$done &&
                $startTs > 0 &&
                $startTs < $nowTs
            ) {
                $late++;
            }
            if ($next === null && !$done && $startTs >= $nowTs) {
                $next = $a;
            }
        }
        $taskOpen =
            (int) (val(
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND (assigned_to=? OR (assigned_to IS NULL AND (COALESCE(target_scope,'clinic')='clinic' OR (COALESCE(target_scope,'clinic')='role' AND target_role=?) OR (COALESCE(target_scope,'clinic')='user' AND target_user_id=?))))",
                [$cid, $uid, $role, $uid],
            ) ?? 0);
        $taskOverdue =
            (int) (val(
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at<NOW() AND (assigned_to=? OR (assigned_to IS NULL AND (COALESCE(target_scope,'clinic')='clinic' OR (COALESCE(target_scope,'clinic')='role' AND target_role=?) OR (COALESCE(target_scope,'clinic')='user' AND target_user_id=?))))",
                [$cid, $uid, $role, $uid],
            ) ?? 0);
        $activeLeads =
            (int) (val(
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                    lead_active_stage_sql("stage"),
                [$cid],
            ) ?? 0);
        $needsUpdate =
            (int) (val(
                "SELECT COUNT(*) FROM pi_patients WHERE clinic_id=? AND active=1 AND registration_needs_update=1",
                [$cid],
            ) ?? 0);
        $unread = unread_notifications_count($c);
        $nextLabel = "—";
        if ($next) {
            $nextLabel = app_time_br((string) $next["start_at"], $cid, $c);
        }
        $cards =
            '<div class="kpis reception-kpis">' .
            "<div>" .
            icon("calendar_month") .
            "<p><b>" .
            (int) $total .
            "</b><span>Consultas hoje</span></p></div>" .
            "<div>" .
            icon("schedule") .
            "<p><b>" .
            e($nextLabel) .
            "</b><span>Próxima consulta</span></p></div>" .
            "<div>" .
            icon("chair") .
            "<p><b>" .
            (int) $waiting .
            "</b><span>Aguardando</span></p></div>" .
            "<div>" .
            icon("warning") .
            "<p><b>" .
            (int) $late .
            "</b><span>Atrasados</span></p></div>" .
            "<div>" .
            icon("task_alt") .
            "<p><b>" .
            (int) $taskOpen .
            "</b><span>Tarefas abertas</span></p></div>" .
            "<div>" .
            icon("campaign") .
            "<p><b>" .
            (int) $unread .
            "</b><span>Avisos</span></p></div>" .
            "</div>";
        $statusLabel = function (array $a) use ($role): array {
    
            $m = appointment_journey_meta($a, $role);
            return [
                $m["label"],
                $m["class"],
                $m["icon"],
                $m["owner"],
                $m["action"],
            ];
        };
        $appointmentRow = function (array $a, bool $compact = false) use (
            $names,
            $users,
            $statusLabel,
            $role,
        ): string {
    
            $startTs = app_storage_timestamp($a["start_at"]);
            $endTs = app_storage_timestamp($a["end_at"]);
            $pid = (int) ($a["patient_link_id"] ?? 0);
            $doctor = $users[(int) ($a["doctor_user_id"] ?? 0)]["name"] ?? "";
            [$label, $class, $ico, $owner, $action] = $statusLabel($a);
            $patient = $names[$pid] ?? "Paciente não identificado";
            $period =
                app_time_br((string) $a["start_at"]) .
                ($compact ? "" : "–" . app_time_br((string) $a["end_at"]));
            $reason = mb_trim((string) ($a["reason"] ?? ""));
            $meta = trim(
                ($doctor !== ""
                    ? "Profissional: " . first_name($doctor)
                    : "Profissional não definido") .
                    ($reason !== "" ? " · " . $reason : ""),
                " ·",
            );
            $link =
                $pid > 0 ? href("patient", ["id" => $pid]) : href("appointments");
            $measure = appointment_journey_compact_html($a, $role);
            return '<a class="reception-row reception-row-' .
                $class .
                ' journey-row" href="' .
                $link .
                '"><span class="reception-time">' .
                e($period) .
                '</span><span class="reception-main"><strong>' .
                e($patient) .
                "</strong><small>" .
                e($meta) .
                "</small>" .
                $measure .
                '</span><span class="pill ' .
                $class .
                '">' .
                icon($ico) .
                e($label) .
                "</span></a>";
        };
        $urgent = [];
        $used = [];
        foreach ($today as $a) {
            [$label] = $statusLabel($a);
            if (
                in_array(
                    $label,
                    [
                        "Atrasado",
                        "Chegou",
                        "Em preparo",
                        "Pronto para atendimento",
                        "Em atendimento",
                        "Atendimento concluído",
                    ],
                    true,
                )
            ) {
                $urgent[] = $appointmentRow($a, true);
                $used[(int) $a["id"]] = 1;
                if (count($urgent) >= 6) {
                    break;
                }
            }
        }
        if (count($urgent) < 6) {
            foreach ($today as $a) {
                if (isset($used[(int) $a["id"]])) {
                    continue;
                }
                $st = app_storage_timestamp($a["start_at"]) ?: 0;
                if ($st >= $nowTs) {
                    $urgent[] = $appointmentRow($a, true);
                    if (count($urgent) >= 6) {
                        break;
                    }
                }
            }
        }
        $nowHtml =
            '<div class="reception-list reception-now-list">' .
            ($urgent
                ? implode("", $urgent)
                : '<div class="empty compact-empty">Nenhuma consulta exigindo ação imediata.</div>') .
            "</div>";
        $pendingItems = [];
        $pendingItems[] = [
            "href" => href("tasks"),
            "icon" => "checklist",
            "label" => "Tarefas pendentes",
            "value" => $taskOpen,
            "note" =>
                $taskOverdue > 0
                    ? $taskOverdue . " atrasada(s)"
                    : "sem atraso crítico",
        ];
        $pendingItems[] = [
            "href" => href("leads"),
            "icon" => "person_search",
            "label" => "Interessados ativos",
            "value" => $activeLeads,
            "note" => "acompanhar retorno e conversão",
        ];
        $pendingItems[] = [
            "href" => href("patients"),
            "icon" => "badge",
            "label" => "Cadastros a revisar",
            "value" => $needsUpdate,
            "note" => "dados recuperados ou incompletos",
        ];
        $pending = "";
        foreach ($pendingItems as $pi) {
            $pending .=
                '<a class="reception-pending" href="' .
                $pi["href"] .
                '">' .
                icon($pi["icon"]) .
                "<span><b>" .
                (int) $pi["value"] .
                "</b><strong>" .
                e($pi["label"]) .
                "</strong><small>" .
                e($pi["note"]) .
                "</small></span></a>";
        }
        $flow =
            '<div class="reception-flow">' .
            "<span><b>" .
            (int) $waiting .
            "</b><small>em preparo/fila</small></span>" .
            "<span><b>" .
            (int) $inCare .
            "</b><small>em atendimento</small></span>" .
            "<span><b>" .
            (int) $finished .
            "</b><small>finalizados</small></span>" .
            "<span><b>" .
            (int) count($blocks) .
            "</b><small>bloqueios</small></span>" .
            "</div>";
        $agenda = [];
        foreach ($today as $a) {
            $agenda[] = [
                "ts" => app_storage_timestamp($a["start_at"]) ?: 0,
                "html" => $appointmentRow($a),
            ];
        }
        foreach ($blocks as $b) {
            $startTs = app_storage_timestamp($b["start_at"]) ?: 0;
            $endTs = app_storage_timestamp($b["end_at"]) ?: 0;
            $doctor = $users[(int) ($b["doctor_user_id"] ?? 0)]["name"] ?? "";
            $creator = $users[(int) ($b["created_by"] ?? 0)]["name"] ?? "";
            $target =
                $doctor !== ""
                    ? "Agenda de " . first_name($doctor)
                    : "Todo o consultório";
            $meta = trim(
                $target .
                    ($creator !== ""
                        ? " · bloqueado por " . first_name($creator)
                        : ""),
                " ·",
            );
            $period =
                ($startTs ? date("H:i", $startTs) : "--:--") .
                "–" .
                ($endTs ? date("H:i", $endTs) : "--:--");
            $agenda[] = [
                "ts" => $startTs,
                "html" =>
                    '<a class="reception-row reception-row-block" href="' .
                    href("appointments") .
                    '"><span class="reception-time">' .
                    e($period) .
                    '</span><span class="reception-main"><strong>Horário bloqueado</strong><small>' .
                    e(trim($meta . " · " . (string) $b["reason"], " ·")) .
                    '</small></span><span class="pill bad">' .
                    icon("event_busy") .
                    "Bloqueio</span></a>",
            ];
        }
        usort($agenda,  fn($a, $b) => $a["ts"] <=> $b["ts"]);
        $agendaHtml = "";
        $shown = 0;
        foreach ($agenda as $row) {
            $agendaHtml .= $row["html"];
            if (++$shown >= 22) {
                break;
            }
        }
        if ($agendaHtml === "") {
            $agendaHtml =
                '<div class="empty compact-empty">Nenhum atendimento ou bloqueio registrado para hoje.</div>';
        }
        $quickHtml = "";
        $body =
            $cards .
            '<section class="reception-panel-grid">' .
            card(
                '<h2>Agora</h2><p class="muted-copy">Pacientes em espera, atrasos e próximos horários.</p>' .
                    $nowHtml .
                    $quickHtml,
                "reception-card reception-now",
            ) .
            card(
                '<h2>Pendências da recepção</h2><p class="muted-copy">Itens que podem travar o atendimento ou exigir contato.</p><div class="reception-pending-grid">' .
                    $pending .
                    "</div>",
                "reception-card",
            ) .
            card(
                '<h2>Fluxo do atendimento</h2><p class="muted-copy">Situação resumida dos pacientes e bloqueios de hoje.</p>' .
                    $flow,
                "reception-card",
            ) .
            card(
                '<h2>Agenda de hoje</h2><p class="muted-copy">Linha do tempo compacta com consultas e bloqueios.</p><div class="reception-list reception-agenda-list">' .
                    $agendaHtml .
                    "</div>",
                "reception-card reception-agenda",
            ) .
            "</section>";
        page(
            "Painel",
            page_head(
                "Painel",
                "Mesa operacional da recepção: chegadas, atrasos, pendências e fluxo do dia.",
            ) .
                '<div class="reception-dashboard">' .
                $body .
                "</div>",
        );
    
    }
}
