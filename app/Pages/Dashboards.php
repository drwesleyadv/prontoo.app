<?php
declare(strict_types=1);
function page_home(): void
{

    redirect("login");
}
function page_medico_painel(array $c): void
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
    $avgDelay = $delayCount ? (int) round($delaySum / $delayCount) : null;
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
function page_recepcao_painel(array $c): void
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
        $reason = trim((string) ($a["reason"] ?? ""));
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
function page_triagem_painel(array $c): void
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
        $reason = trim((string) ($a["reason"] ?? ""));
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
function manager_metric_val(string $sql, array $params = []): int
{

    try {
        return (int) (val($sql, $params) ?? 0);
    } catch (Throwable $e) {
        error_log("[Prontoo manager metric] " . $e->getMessage());
        return 0;
    }
}
function manager_metric_row(string $sql, array $params = []): array
{

    try {
        return one($sql, $params) ?: [];
    } catch (Throwable $e) {
        error_log("[Prontoo manager metric row] " . $e->getMessage());
        return [];
    }
}
function manager_count_business_days(string $from, string $to): int
{

    $start = strtotime($from . " 00:00:00");
    $end = strtotime($to . " 00:00:00");
    if (!$start || !$end || $end < $start) {
        return 0;
    }
    $n = 0;
    for ($t = $start; $t <= $end; $t += 86400) {
        $w = (int) date("N", $t);
        if ($w >= 1 && $w <= 5) {
            $n++;
        }
    }
    return $n;
}
function manager_percent_label(float $v): string
{

    return number_format($v, 1, ",", ".") . "%";
}
function manager_dashboard_card(
    string $label,
    string $value,
    string $iconName,
    string $note = "",
    string $class = "",
): string {

    return '<article class="stat-card manager-stat ' .
        e($class) .
        '">' .
        icon($iconName) .
        "<div><b>" .
        e($value) .
        "</b><span>" .
        e($label) .
        "</span>" .
        ($note !== "" ? "<small>" . e($note) . "</small>" : "") .
        "</div></article>";
}
function manager_action_card(
    string $iconName,
    string $title,
    string $body,
    string $route,
    string $label = "Abrir",
): string {

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
function page_gerente_painel(array $c): void
{

    $cid = (int) $c["clinic_id"];
    $month = app_month_in_timezone($cid, $c);
    [$monthStart, $nextMonth] = app_local_month_utc_range($month, $cid, $c);
    $localNow = app_now_in_timezone($cid, $c);
    $monthEnd = $localNow->format("Y-m-t");
    [$todayStart, $todayEnd] = app_local_day_utc_range(
        app_today_in_timezone($cid, $c),
        $cid,
        $c,
    );
    $today = manager_metric_row(
        "SELECT COUNT(*) total, SUM(CASE WHEN arrived_at IS NOT NULL OR status IN ('chegou','em_preparo','pronto_atendimento') THEN 1 ELSE 0 END) arrived, SUM(CASE WHEN consultation_finished_at IS NOT NULL OR status IN ('atendimento_concluido','finalizado') THEN 1 ELSE 0 END) finished, SUM(CASE WHEN status='cancelado' THEN 1 ELSE 0 END) canceled FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<?",
        [$cid, $todayStart, $todayEnd],
    );
    $todayTotal = (int) ($today["total"] ?? 0);
    $todayArrived = (int) ($today["arrived"] ?? 0);
    $todayFinished = (int) ($today["finished"] ?? 0);
    $todayCanceled = (int) ($today["canceled"] ?? 0);
    $doctorCount = max(1, count(doctors($cid)));
    $estimatedCapacity = max(1, $doctorCount * 8);
    $estimatedOcc = (float) round(
        min(100, ($todayTotal / $estimatedCapacity) * 100),
        1,
    );
    $activeLeads = manager_metric_val(
        "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
            lead_active_stage_sql("stage"),
        [$cid],
    );
    $leadsNoNext = manager_metric_val(
        "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
            lead_active_stage_sql("stage") .
            " AND next_action_at IS NULL",
        [$cid],
    );
    $lateLeads = manager_metric_val(
        "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
            lead_active_stage_sql("stage") .
            " AND next_action_at IS NOT NULL AND next_action_at<NOW()",
        [$cid],
    );
    $pendingTasks = manager_metric_val(
        "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando')",
        [$cid],
    );
    $lateTasks = manager_metric_val(
        "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at<NOW()",
        [$cid],
    );
    $unassignedTasks = manager_metric_val(
        "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND assigned_to IS NULL",
        [$cid],
    );
    $pendingRevenue = manager_metric_val(
        "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='prevista' AND (r.expected_at IS NULL OR r.expected_at<?) AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))",
        [$cid, $nextMonth],
    );
    $overdueRevenue = manager_metric_val(
        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND expected_at<NOW()",
        [$cid],
    );
    $receivedRevenue = manager_metric_val(
        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?",
        [$cid, $monthStart, $nextMonth],
    );
    $plannedRevenue = manager_metric_val(
        "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.expected_at>=? AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))",
        [$cid, $monthStart, $nextMonth],
    );
    $paidExpenses = manager_metric_val(
        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_expenses WHERE clinic_id=? AND status='paga' AND paid_at>=? AND paid_at<?",
        [$cid, $monthStart, $nextMonth],
    );
    $goal = monthly_goal_status($cid);
    $target = (int) $goal["target_cents"];
    $done = (int) $goal["done_cents"];
    $goalPct = (float) $goal["percent"];
    $goalBar = max(0, min(100, $goalPct));
    $missing = max(0, $target - $done);
    $result = $receivedRevenue - $paidExpenses;
    $goalBaseLabel = (string) ($goal["base_label"] ?? "Receita Efetivada");
    $monthDay = (int) $localNow->format("j");
    $monthDays = (int) $localNow->format("t");
    $expectedPct =
        $target > 0 ? (float) round(($monthDay / $monthDays) * 100, 1) : 0.0;
    $businessLeft = max(
        1,
        manager_count_business_days($localNow->format("Y-m-d"), $monthEnd),
    );
    $dailyNeeded = $missing > 0 ? (int) ceil($missing / $businessLeft) : 0;
    $goalState =
        $target <= 0
            ? "Meta não definida"
            : ($goalPct >= 100
                ? "Meta cumprida"
                : ($goalPct + 5 >= $expectedPct
                    ? "No ritmo da meta"
                    : "Abaixo do ritmo esperado"));
    $finishedMonth = manager_metric_val(
        "SELECT COUNT(*) FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<? AND (consultation_finished_at IS NOT NULL OR status IN ('atendimento_concluido','finalizado'))",
        [$cid, $monthStart, $nextMonth],
    );
    $ticket =
        $finishedMonth > 0 ? (int) round($receivedRevenue / $finishedMonth) : 0;
    $procedureRows = [];
    try {
        $procedureRows = q(
            "SELECT COALESCE(p.title,'Sem procedimento vinculado') title, COALESCE(SUM(fr.amount_cents),0) total FROM pi_financial_revenues fr LEFT JOIN pi_procedures p ON p.id=fr.procedure_id AND p.clinic_id=fr.clinic_id WHERE fr.clinic_id=? AND fr.status='efetivada' AND fr.received_at>=? AND fr.received_at<? GROUP BY COALESCE(p.title,'Sem procedimento vinculado') ORDER BY total DESC LIMIT 5",
            [$cid, $monthStart, $nextMonth],
        )->fetchAll();
    } catch (Throwable $e) {
        error_log("[Prontoo manager procedures] " . $e->getMessage());
    }
    $kpis =
        '<div class="kpis manager-kpis manager-operational-kpis">' .
        manager_dashboard_card(
            "Agendados",
            (string) $todayTotal,
            "calendar_month",
            $todayArrived .
                " chegaram · " .
                $todayFinished .
                " concluídos · " .
                $todayCanceled .
                " cancelados",
        ) .
        manager_dashboard_card(
            "Ocupação Estimada",
            manager_percent_label($estimatedOcc),
            "speed",
            $todayTotal .
                " de " .
                $estimatedCapacity .
                " horários úteis estimados",
        ) .
        manager_dashboard_card(
            "Interessados Ativos",
            (string) $activeLeads,
            "person_search",
            $leadsNoNext . " sem próxima ação",
        ) .
        manager_dashboard_card(
            "Tarefas Pendentes",
            (string) $pendingTasks,
            "pending_actions",
            $lateTasks .
                " atrasadas · " .
                $unassignedTasks .
                " sem responsável",
            $lateTasks > 0 || $unassignedTasks > 0 ? "bad" : "",
        ) .
        manager_dashboard_card(
            "Retornos Vencidos",
            (string) $lateLeads,
            "event_busy",
            "interessados com próximo contato vencido",
            $lateLeads > 0 ? "bad" : "",
        ) .
        "</div>";
    $goalHtml =
        '<section class="card manager-goal"><header><div><h2>Meta mensal</h2><p>' .
        e($goalState . " · base: " . $goalBaseLabel) .
        '</p></div><a class="ghost small" href="' .
        href("financial", ["tab" => "meta"]) .
        '">' .
        icon("flag") .
        '<span>Ajustar meta</span></a></header><div class="manager-goal-bar"><i style="width:' .
        $goalBar .
        '%"></i></div><div class="manager-goal-grid"><span><b>' .
        money_br($done) .
        "</b><small>realizado na base da meta</small></span><span><b>" .
        ($target > 0 ? money_br($target) : "—") .
        "</b><small>meta</small></span><span><b>" .
        money_br($missing) .
        "</b><small>faltante</small></span><span><b>" .
        money_br($dailyNeeded) .
        '</b><small>necessário por dia útil</small></span></div><p class="manager-goal-note">' .
        e(
            $target > 0
                ? "O ritmo esperado para hoje é " .
                    manager_percent_label($expectedPct) .
                    ". A meta está calculada sobre " .
                    $goalBaseLabel .
                    " e considera os dias úteis restantes."
                : "A meta mensal ainda não foi cadastrada. Sem ela, o painel não consegue medir o rumo financeiro do mês.",
        ) .
        "</p></section>";
    $finance =
        '<section class="card manager-finance-card"><h2>Indicadores financeiros</h2><div class="manager-mini-grid"><span><b>' .
        money_br($plannedRevenue) .
        "</b><small>receita prevista no mês</small></span><span><b>" .
        money_br($receivedRevenue) .
        "</b><small>receita efetivada</small></span><span><b>" .
        money_br($pendingRevenue) .
        "</b><small>a receber</small></span><span><b>" .
        money_br($paidExpenses) .
        "</b><small>despesas pagas</small></span><span><b>" .
        money_br($result) .
        "</b><small>resultado parcial</small></span><span><b>" .
        ($ticket > 0 ? money_br($ticket) : "—") .
        "</b><small>ticket médio efetivado</small></span></div></section>";
    $actions = [];
    $paymentRejected = manager_metric_val(
        "SELECT COUNT(*) FROM pi_notices n LEFT JOIN pi_notice_reads nr ON nr.notice_id=n.id AND nr.user_id=? WHERE n.clinic_id=? AND n.title='Pagamento não confirmado' AND (nr.hidden_at IS NULL)",
        [(int) $c["user"]["id"], $cid],
    );
    if ($paymentRejected > 0) {
        $actions[] = manager_action_card(
            "upload_file",
            "Pagamento não confirmado",
            "Envie o comprovante de pagamento em Meu Consultório > Assinatura para nova conferência.",
            "settings",
            "Regularizar",
        );
    }
    if ($target <= 0) {
        $actions[] = manager_action_card(
            "flag",
            "Definir meta mensal",
            "Sem meta, o sistema não consegue orientar ritmo, faltante e tendência financeira.",
            "financial",
            "Configurar",
        );
    } elseif ($goalPct + 5 < $expectedPct) {
        $actions[] = manager_action_card(
            "trending_down",
            "Meta abaixo do ritmo",
            "Revise agenda, conversão de interessados e receitas previstas para recuperar o mês.",
            "financial",
            "Ver financeiro",
        );
    }
    if ($leadsNoNext > 0) {
        $actions[] = manager_action_card(
            "person_search",
            $leadsNoNext . " interessado(s) sem próxima ação",
            "Defina responsável, prazo ou próximo contato para não perder demanda.",
            "leads",
            "Organizar",
        );
    }
    if ($lateLeads > 0) {
        $actions[] = manager_action_card(
            "event_repeat",
            $lateLeads . " retorno(s) vencido(s)",
            "Há interessados com próximo contato vencido. Atualize prazo, responsável ou desfecho.",
            "leads",
            "Revisar retornos",
        );
    }
    if ($lateTasks > 0 || $unassignedTasks > 0) {
        $actions[] = manager_action_card(
            "checklist",
            $lateTasks .
                " tarefa(s) atrasada(s) e " .
                $unassignedTasks .
                " sem responsável",
            "Repriorize a equipe e elimine pendências sem dono claro.",
            "tasks",
            "Revisar",
        );
    }
    if ($overdueRevenue > 0) {
        $actions[] = manager_action_card(
            "account_balance_wallet",
            "Receitas previstas vencidas",
            "Há " .
                money_br($overdueRevenue) .
                " em receitas previstas com data vencida.",
            "financial",
            "Conferir",
        );
    }
    if ($estimatedOcc < 40) {
        $actions[] = manager_action_card(
            "event_busy",
            "Ocupação estimada baixa",
            "A ocupação estimada está abaixo de 40%; avalie retornos, encaixes e contatos pendentes.",
            "appointments",
            "Ver agenda",
        );
    }
    $actionsHtml =
        '<section class="card manager-actions"><h2>Ações recomendadas</h2>' .
        ($actions
            ? '<div class="manager-action-list">' .
                implode("", $actions) .
                "</div>"
            : '<div class="empty">Nenhuma ação crítica detectada agora.</div>') .
        "</section>";
    $procedureHtml = "";
    if ($procedureRows) {
        $procedureHtml = '<div class="manager-procedure-list">';
        foreach ($procedureRows as $r) {
            $procedureHtml .=
                "<span><b>" .
                e((string) $r["title"]) .
                "</b><small>" .
                money_br((int) $r["total"]) .
                "</small></span>";
        }
        $procedureHtml .= "</div>";
    } else {
        $procedureHtml =
            '<div class="empty">Ainda não há receita efetivada por procedimento neste mês.</div>';
    }
    $procedureBlock =
        '<section class="card manager-procedures"><h2>Receita por procedimento</h2>' .
        $procedureHtml .
        "</section>";
    page(
        "Painel",
        page_head(
            "Painel",
            "Indicadores operacionais, meta mensal, financeiro e ações prioritárias.",
        ) .
            $kpis .
            $goalHtml .
            $finance .
            $actionsHtml .
            $procedureBlock,
    );
}
function page_painel(): void
{

    $c = require_can("painel");
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    if (has_effective_role($c, "gerente")) {
        page_gerente_painel($c);
        return;
    }
    if (($c["role"] ?? "") === "medico") {
        page_medico_painel($c);
        return;
    }
    if (($c["role"] ?? "") === "recepcionista") {
        page_recepcao_painel($c);
        return;
    }
    if (($c["role"] ?? "") === "assistente") {
        page_triagem_painel($c);
        return;
    }
    [$todayStart, $todayEnd] = app_local_day_utc_range(
        app_today_in_timezone($cid, $c),
        $cid,
        $c,
    );
    $today =
        (int) (val(
            "SELECT COUNT(*) FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<?",
            [$cid, $todayStart, $todayEnd],
        ) ?? 0);
    $lateTasks =
        (int) (val(
            "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status='aberta' AND due_at IS NOT NULL AND due_at<NOW() AND (assigned_to IS NULL OR assigned_to=?)",
            [$cid, $uid],
        ) ?? 0);
    $cards =
        '<div class="kpis"><div><b>' .
        $today .
        "</b><span>consultas hoje</span></div><div><b>" .
        $lateTasks .
        "</b><span>Tarefas Atrasadas</span></div><div><b>—</b><span>Atraso médio do dia</span></div></div>";
    page(
        "Painel",
        page_head("Painel", "O que você precisa saber agora.") .
            $cards .
            card(
                timeline(
                    recent_events($cid, $uid, $c["role"]),
                    "Nenhum evento relevante.",
                ),
            ),
    );
}
