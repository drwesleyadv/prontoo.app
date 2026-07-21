<?php
declare(strict_types=1);
function notice_target_sql(array $c, string $alias = "n"): array
{
    $role = (string) ($c["role"] ?? "");
    $uid = (int) ($c["user"]["id"] ?? 0);
    $p = [$role, $uid];
    $a = $alias !== "" ? $alias . "." : "";
    return [
        "($a" .
        "target_scope='all' OR ($a" .
        "target_scope='role' AND $a" .
        "target_role=?) OR ($a" .
        "target_scope='user' AND $a" .
        "target_user_id=?))",
        $p,
    ];
}
function notice_target_label(array $n, array $users = []): string
{
    $scope = (string) ($n["target_scope"] ?? "all");
    if ($scope === "role") {
        $cx = ctx();
        return "Destinatários: " .
            role_label_for(
                (string) ($n["target_role"] ?? ""),
                (int) ($cx["clinic_id"] ?? 0),
            );
    }
    if ($scope === "user") {
        $u = $users[(int) ($n["target_user_id"] ?? 0)] ?? [];
        return "Destinatário: " . ($u["name"] ?? "colaborador específico");
    }
    return "Destinatários: toda a clínica";
}
function notice_recipient_phrase(array $n, array $users = []): string
{
    $scope = (string) ($n["target_scope"] ?? "all");
    if ($scope === "all") {
        return "toda clínica";
    }
    if ($scope === "role") {
        $cx = ctx();
        return role_label_for(
            (string) ($n["target_role"] ?? ""),
            (int) ($cx["clinic_id"] ?? 0),
        );
    }
    if ($scope === "user") {
        $u = $users[(int) ($n["target_user_id"] ?? 0)] ?? [];
        return (string) ($u["name"] ?? "colaborador específico");
    }
    return "toda clínica";
}
function notice_recipient_people(int $cid, array $notice, array $team): array
{
    $scope = (string) ($notice["target_scope"] ?? "all");
    $ids = [];
    if ($scope === "user") {
        $id = (int) ($notice["target_user_id"] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    } elseif ($scope === "role") {
        $role = (string) ($notice["target_role"] ?? "");
        if ($role !== "") {
            $ids = team_user_ids_for_roles($cid, [$role]);
        }
    } else {
        $ids = array_keys($team);
    }
    $ids = array_values(array_unique(array_filter(array_map("intval", $ids))));
    if (!$ids) {
        return [];
    }
    $missing = array_values(array_filter($ids, fn($id) => !isset($team[$id])));
    if ($missing) {
        foreach (scoped_user_map($cid, $missing, "id,name") as $id => $u) {
            if (!isset($team[(int) $id])) {
                $team[(int) $id] = (string) ($u["name"] ?? "Colaborador");
            }
        }
    }
    $reads = [];
    try {
        $ph = implode(",", array_fill(0, count($ids), "?"));
        $params = array_merge([(int) ($notice["id"] ?? 0)], $ids);
        foreach (
            q(
                "SELECT user_id,read_at,ack_at FROM pi_notice_reads WHERE notice_id=? AND user_id IN ($ph)",
                $params,
            )->fetchAll()
            as $r
        ) {
            $reads[(int) $r["user_id"]] =
                !empty($r["ack_at"]) || !empty($r["read_at"]);
        }
    } catch (Throwable $e) {
        error_log("[Prontoo notice recipients] " . $e->getMessage());
    }
    $people = [];
    foreach ($ids as $id) {
        $people[] = [
            "id" => $id,
            "name" => (string) ($team[$id] ?? "Colaborador"),
            "read" => !empty($reads[$id]),
        ];
    }
    usort(
        $people,
        fn($a, $b) => strnatcasecmp((string) $a["name"], (string) $b["name"]),
    );
    return $people;
}
function notice_recipient_pills(int $cid, array $notice, array $team): string
{
    $people = notice_recipient_people($cid, $notice, $team);
    if (!$people) {
        return '<span class="notice-recipient-pills"><span class="notice-recipient-pill is-unread">' .
            icon("check") .
            "<span>Equipe</span></span></span>";
    }
    $total = count($people);
    $limit = 12;
    $shown = array_slice($people, 0, $limit);
    $html = '<span class="notice-recipient-pills" aria-label="Destinatários">';
    foreach ($shown as $person) {
        $read = !empty($person["read"]);
        $iconName = $read ? "done_all" : "check";
        $label = first_name((string) $person["name"]);
        $title = ($read ? "Lido por " : "Não lido por ") . $label;
        $html .=
            '<span class="notice-recipient-pill ' .
            ($read ? "is-read" : "is-unread") .
            '" title="' .
            e($title) .
            '">' .
            icon($iconName) .
            "<span>" .
            e($label) .
            "</span></span>";
    }
    if ($total > $limit) {
        $html .=
            '<span class="notice-recipient-pill notice-recipient-more">+' .
            (int) ($total - $limit) .
            "</span>";
    }
    return $html . "</span>";
}
function task_event(
    int $cid,
    int $taskId,
    string $eventKey,
    ?int $actorUserId = null,
    ?string $fromStatus = null,
    ?string $toStatus = null,
    ?string $note = null,
): void {
    try {
        if ($cid <= 0 || $taskId <= 0 || $eventKey === "") {
            return;
        }
        q(
            "INSERT INTO pi_task_events (clinic_id,task_id,event_key,actor_user_id,from_status,to_status,note,created_at) VALUES (?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                $taskId,
                mb_substr($eventKey, 0, 60),
                $actorUserId,
                $fromStatus,
                $toStatus,
                $note !== null ? mb_substr($note, 0, 255) : null,
            ],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo task event] " . $e->getMessage());
    }
}
function notify_task_personal_assignment(
    int $cid,
    int $taskId,
    string $taskTitle,
    ?string $taskDescription,
    int $targetUserId,
    ?int $createdBy = null,
    string $source = "manual",
): void {
    try {
        if ($cid <= 0 || $taskId <= 0 || $targetUserId <= 0) {
            return;
        }
        if (!clinic_user_exists($cid, $targetUserId)) {
            return;
        }
        $creator = null;
        if (
            $createdBy !== null &&
            $createdBy > 0 &&
            clinic_user_exists($cid, (int) $createdBy)
        ) {
            $creator = (int) $createdBy;
        }
        $title = "Nova tarefa atribuída a você";
        $body =
            "Você foi nomeado pessoalmente para a tarefa: " . trim($taskTitle);
        $desc = trim((string) $taskDescription);
        if ($desc !== "") {
            $body .= "\n\n" . $desc;
        }
        $body .=
            "\n\nAbra o módulo Tarefas para iniciar ou acompanhar esta demanda.";
        q(
            "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
            [$cid, $title, $body, 1, "user", null, $targetUserId, $creator],
        );
        $noticeId = db_last_insert_id();
        counter_inc("notices_total");
        clinic_metric_inc($cid, "notices");
        audit("notificacao_tarefa_individual", "comunicado", $noticeId, [
            "tarefa_id" => $taskId,
            "destinatario" => $targetUserId,
            "origem" => $source,
            "audit_body" =>
                "Aviso automático criado porque a tarefa foi direcionada a uma pessoa específica.",
        ]);
    } catch (Throwable $e) {
        error_log("[Prontoo task assignment notice] " . $e->getMessage());
    }
}
function workflow_valid_role(int $cid, ?string $role): ?string
{
    $role = trim((string) $role);
    if ($role === "") {
        return null;
    }
    try {
        return array_key_exists($role, clinic_role_options($cid, true))
            ? $role
            : null;
    } catch (Throwable $e) {
        return in_array(
            $role,
            ["recepcionista", "assistente", "medico", "gerente"],
            true,
        )
            ? $role
            : null;
    }
}
function workflow_notice_body(string $body, string $action = ""): string
{
    $body = trim($body);
    $action = trim($action);
    if ($action !== "") {
        $body .= "\n\nPróximo passo: " . $action;
    }
    $body .=
        "\n\nEste é um recado automático do fluxo de cuidado. Arquive quando não precisar mais dele.";
    return $body;
}
function workflow_care_notice(
    int $cid,
    string $title,
    string $body,
    string $targetScope = "role",
    ?string $targetRole = null,
    ?int $targetUserId = null,
    string $sourceEvent = "",
    string $sourceEntity = "",
    string $sourceEntityId = "",
    int $requiresAck = 1,
    string $action = "",
): ?int {
    try {
        if ($cid <= 0 || trim($title) === "" || trim($body) === "") {
            return null;
        }
        $scope = $targetScope === "clinic" ? "all" : $targetScope;
        if (!in_array($scope, ["all", "role", "user"], true)) {
            $scope = "role";
        }
        $targetRole =
            $scope === "role" ? workflow_valid_role($cid, $targetRole) : null;
        $targetUserId = $scope === "user" ? (int) $targetUserId : null;
        if (
            $scope === "user" &&
            (!$targetUserId || !clinic_user_exists($cid, (int) $targetUserId))
        ) {
            $scope = "role";
            $targetRole = workflow_valid_role($cid, "gerente");
            $targetUserId = null;
            $title = "Atenção: " . $title;
            $body .=
                "\n\nO destinatário individual não está ativo. O Administrativo deve redistribuir este cuidado.";
        }
        if ($scope === "role") {
            if (!$targetRole) {
                return null;
            }
            $recipients = team_user_ids_for_roles($cid, [$targetRole]);
            if (!$recipients && $targetRole !== "gerente") {
                $missingLabel = role_label_for($targetRole, $cid);
                $targetRole = workflow_valid_role($cid, "gerente");
                if (!$targetRole) {
                    return null;
                }
                $title = "Sem responsável: " . $title;
                $body .=
                    "\n\nNão há colaborador ativo no cargo " .
                    $missingLabel .
                    ". O Administrativo deve assumir ou corrigir a escala.";
            }
        }
        $title = mb_substr(trim($title), 0, 180);
        $body = workflow_notice_body($body, $action);
        $creator = isset($_SESSION["uid"]) ? (int) $_SESSION["uid"] : null;
        $exists =
            (int) (val(
                "SELECT id FROM pi_notices WHERE clinic_id=? AND title=? AND body=? AND target_scope=? AND (target_role <=> ?) AND (target_user_id <=> ?) AND created_at>=DATE_SUB(NOW(), INTERVAL 6 HOUR) LIMIT 1",
                [$cid, $title, $body, $scope, $targetRole, $targetUserId],
            ) ?:
            0);
        if ($exists > 0) {
            return $exists;
        }
        q(
            "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                $title,
                $body,
                $requiresAck ? 1 : 0,
                $scope,
                $targetRole,
                $targetUserId,
                $creator,
            ],
        );
        $noticeId = db_last_insert_id();
        counter_inc("notices_total");
        clinic_metric_inc($cid, "notices");
        audit("recado_cuidado_criado", "comunicado", $noticeId, [
            "destino" => $scope,
            "cargo" => $targetRole,
            "destinatario" => $targetUserId,
            "source_event" => $sourceEvent,
            "source_entity" => $sourceEntity,
            "source_entity_id" => $sourceEntityId,
            "audit_body" =>
                "Recado automático criado para avisar somente o próximo cargo responsável no fluxo de cuidado.",
        ]);
        return $noticeId;
    } catch (Throwable $e) {
        error_log("[Prontoo workflow notice] " . $e->getMessage());
        return null;
    }
}
function workflow_task_notice(
    int $cid,
    int $taskId,
    string $taskTitle,
    string $description,
    ?int $patientLinkId,
    ?int $appointmentId,
    string $targetScope,
    ?string $targetRole,
    ?int $targetUserId,
    string $sourceEvent,
    string $sourceEntity,
    string $sourceEntityId,
): void {
    $body = trim($description);
    if ($appointmentId) {
        $body .= "\n\nConsulta vinculada: #" . (int) $appointmentId . ".";
    }
    if ($taskId > 0) {
        $body .= "\nTarefa vinculada: #" . (int) $taskId . ".";
    }
    if ($targetScope === "role" && $targetRole) {
        workflow_care_notice(
            $cid,
            $taskTitle,
            $body,
            "role",
            $targetRole,
            null,
            $sourceEvent,
            $sourceEntity,
            $sourceEntityId,
            1,
            "abrir Tarefas, assumir a demanda e registrar a conclusão.",
        );
    } elseif ($targetScope === "user" && $targetUserId) {
        workflow_care_notice(
            $cid,
            $taskTitle,
            $body,
            "user",
            null,
            (int) $targetUserId,
            $sourceEvent,
            $sourceEntity,
            $sourceEntityId,
            1,
            "abrir Tarefas e concluir a etapa quando terminar.",
        );
    }
}
function workflow_appointment_payment_pending(
    int $cid,
    int $appointmentId,
): bool {
    try {
        if ($cid <= 0 || $appointmentId <= 0) {
            return false;
        }
        $a = one(
            "SELECT payment_status,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?",
            [$appointmentId, $cid],
        );
        if (!$a) {
            return false;
        }
        $amount = (int) ($a["payment_amount_cents"] ?? 0);
        $status = (string) ($a["payment_status"] ?? "");
        if ($amount <= 0) {
            return false;
        }
        return !in_array(
            $status,
            ["efetivada", "pago", "paga", "quitado", "quitada"],
            true,
        );
    } catch (Throwable $e) {
        return false;
    }
}
function workflow_on_task_started(
    array $task,
    int $startedBy,
    string $role,
): void {
    try {
        $cid = (int) ($task["clinic_id"] ?? 0);
        $appt = (int) ($task["appointment_id"] ?? 0);
        $source = (string) ($task["source_event"] ?? "");
        if ($cid <= 0 || $appt <= 0) {
            return;
        }
        if ($source === "paciente_chegou") {
            q(
                "UPDATE pi_appointments SET status='em_preparo', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='chegou' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                [$appt, $cid],
            );
            audit("preparo_iniciado", "consulta", $appt, [
                "source_event" => $source,
                "audit_body" =>
                    "A Assistente iniciou o preparo/triagem do paciente.",
            ]);
        }
        if ($source === "preparo_concluido") {
            q(
                "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), status='em_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='pronto_atendimento' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                [$appt, $cid],
            );
            audit("consulta_iniciada", "consulta", $appt, [
                "source_event" => $source,
                "audit_body" =>
                    "O profissional iniciou a etapa de atendimento a partir da tarefa automática do fluxo de cuidado.",
            ]);
        }
    } catch (Throwable $e) {
        error_log("[Prontoo workflow task started] " . $e->getMessage());
    }
}
function workflow_on_appointment_finished(
    int $cid,
    int $appointmentId,
    int $patientLinkId,
    ?int $finishedBy = null,
    string $source = "atendimento",
): void {
    try {
        if ($cid <= 0 || $appointmentId <= 0) {
            return;
        }
        $appt = one(
            "SELECT id,patient_link_id,payment_status,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?",
            [$appointmentId, $cid],
        );
        if (!$appt) {
            return;
        }
        if ($patientLinkId <= 0) {
            $patientLinkId = (int) ($appt["patient_link_id"] ?? 0);
        }
        $name = $patientLinkId
            ? patient_display_name($patientLinkId, $cid)
            : "paciente";
        $stmt = q(
            "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), consultation_finished_at=COALESCE(consultation_finished_at,NOW()), status='atendimento_concluido', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='em_atendimento' AND consultation_finished_at IS NULL",
            [$appointmentId, $cid],
        );
        if ($stmt->rowCount() <= 0) {
            return;
        }
        $desc =
            "Atendimento finalizado. Conferir saída do paciente, retorno, documentos entregues, orientação administrativa e pagamento quando aplicável.";
        if (workflow_appointment_payment_pending($cid, $appointmentId)) {
            $desc .=
                "\n\nAtenção: há pagamento previsto ainda sem baixa nesta consulta.";
        }
        create_workflow_task(
            $cid,
            "Finalizar saída de " . $name,
            $desc,
            null,
            $patientLinkId ?: null,
            $appointmentId,
            "atendimento_finalizado",
            "consulta",
            (string) $appointmentId,
            null,
            "role",
            "recepcionista",
        );
        audit("fluxo_atendimento_finalizado", "consulta", $appointmentId, [
            "patient_link_id" => $patientLinkId,
            "source" => $source,
            "audit_body" =>
                "Atendimento finalizado e recado automático enviado para a Recepção concluir a saída do paciente.",
        ]);
    } catch (Throwable $e) {
        error_log(
            "[Prontoo workflow appointment finished] " . $e->getMessage(),
        );
    }
}
function unread_notifications_count(array $c): int
{
    if (($c["scope"] ?? "") !== "clinic") {
        return 0;
    }
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    [$targetSql, $targetParams] = notice_target_sql($c, "n");
    $n = 0;
    try {
        $n += (int) val(
            "SELECT COUNT(*) FROM pi_notices n WHERE n.clinic_id=? AND $targetSql AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND r.user_id=? AND (r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1)",
            array_merge([$cid], $targetParams, [$uid]),
        );
    } catch (Throwable $e) {
        error_log("[Prontoo unread notices] " . $e->getMessage());
    }
    try {
        $n += (int) val(
            "SELECT COUNT(*) FROM pi_global_notices WHERE active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (expires_at IS NULL OR expires_at>=NOW())",
        );
    } catch (Throwable $e) {
        error_log("[Prontoo unread global notices] " . $e->getMessage());
    }
    return min(99, $n);
}
function notification_button(array $c): string
{
    if (($c["scope"] ?? "") !== "clinic") {
        return "";
    }
    $n = unread_notifications_count($c);
    return '<a class="notify-link top-icon" href="' .
        href("notices") .
        '" aria-label="Avisos" title="Avisos">' .
        icon("campaign") .
        ($n > 0 ? '<b class="badge">' . $n . "</b>" : "") .
        "</a>";
}
function team_user_ids_for_roles(int $cid, array $roles): array
{
    $roles = array_values(array_unique(array_filter($roles)));
    if (!$roles) {
        return [];
    }
    $ph = implode(",", array_fill(0, count($roles), "?"));
    $rows = q(
        "SELECT user_id FROM pi_user_roles WHERE clinic_id=? AND active=1 AND role_code IN ($ph) ORDER BY id ASC LIMIT 300",
        array_merge([$cid], $roles),
    )->fetchAll();
    return int_ids($rows, "user_id");
}
function first_team_user_for_role(int $cid, string $role): ?int
{
    try {
        $id = (int) val(
            "SELECT user_id FROM pi_user_roles WHERE clinic_id=? AND role_code=? AND active=1 ORDER BY id ASC LIMIT 1",
            [$cid, $role],
        );
        return $id > 0 ? $id : null;
    } catch (Throwable $e) {
        return null;
    }
}
function create_workflow_task(
    int $cid,
    string $title,
    string $description,
    ?int $assignedTo,
    ?int $patientLinkId,
    ?int $appointmentId,
    string $sourceEvent,
    string $sourceEntity,
    string $sourceEntityId,
    ?string $dueAt = null,
    string $targetScope = "",
    ?string $targetRole = null,
    bool $strict = false,
): ?int {
    try {
        $targetScope =
            $targetScope !== ""
                ? $targetScope
                : ($assignedTo !== null
                    ? "user"
                    : "clinic");
        if (!in_array($targetScope, ["clinic", "role", "user"], true)) {
            $targetScope = "clinic";
        }
        if (
            $targetScope === "role" &&
            !array_key_exists(
                (string) $targetRole,
                clinic_role_options($cid, true),
            )
        ) {
            $targetScope = "clinic";
            $targetRole = null;
        }
        if ($assignedTo !== null && !clinic_user_exists($cid, $assignedTo)) {
            $assignedTo = null;
        }
        if ($targetScope === "user" && $assignedTo === null) {
            $targetScope = "clinic";
            $targetRole = null;
        }
        $targetUserId = $targetScope === "user" ? $assignedTo : null;
        if ($targetScope !== "role") {
            $targetRole = null;
        }
        if (
            $patientLinkId !== null &&
            !clinic_patient_exists($cid, $patientLinkId, false)
        ) {
            $patientLinkId = null;
        }
        if ($appointmentId !== null) {
            $appt = one(
                "SELECT id,patient_link_id FROM pi_appointments WHERE id=? AND clinic_id=?",
                [$appointmentId, $cid],
            );
            if (!$appt) {
                $appointmentId = null;
            } elseif (
                $patientLinkId !== null &&
                (int) $appt["patient_link_id"] !== $patientLinkId
            ) {
                $patientLinkId = null;
            }
        }
        $exists = (int) val(
            "SELECT t.id FROM pi_tasks t INNER JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id WHERE t.clinic_id=? AND td.source_event=? AND td.source_entity=? AND td.source_entity_id=? AND t.status IN ('aberta','em_andamento','aguardando') LIMIT 1",
            [$cid, $sourceEvent, $sourceEntity, $sourceEntityId],
        );
        if ($exists > 0) {
            return $exists;
        }
        $taskId = (int) db_tx(static function () use (
            $cid,
            $title,
            $targetScope,
            $targetRole,
            $targetUserId,
            $assignedTo,
            $dueAt,
            $description,
            $patientLinkId,
            $appointmentId,
            $sourceEvent,
            $sourceEntity,
            $sourceEntityId,
        ): int {
            q(
                "INSERT INTO pi_tasks (clinic_id,title,target_scope,target_role,target_user_id,assigned_to,status,due_at,created_by,created_at) VALUES (?,?,?,?,?,?, 'aberta', ?, ?, NOW())",
                [
                    $cid,
                    $title,
                    $targetScope,
                    $targetRole,
                    $targetUserId,
                    $assignedTo,
                    $dueAt,
                    $_SESSION["uid"] ?? null,
                ],
            );
            $newTaskId = db_last_insert_id();
            q(
                "INSERT INTO pi_task_details (task_id,clinic_id,description,patient_link_id,appointment_id,source_event,source_entity,source_entity_id) VALUES (?,?,?,?,?,?,?,?)",
                [
                    $newTaskId,
                    $cid,
                    $description,
                    $patientLinkId,
                    $appointmentId,
                    $sourceEvent,
                    $sourceEntity,
                    $sourceEntityId,
                ],
            );
            return $newTaskId;
        });
        task_event(
            $cid,
            $taskId,
            "criada",
            isset($_SESSION["uid"]) ? (int) $_SESSION["uid"] : null,
            null,
            "aberta",
            "Tarefa automática criada pelo fluxo operacional.",
        );
        counter_inc("tasks_total");
        clinic_metric_inc($cid, "tasks");
        if ($targetScope === "user" && $targetUserId) {
            notify_task_personal_assignment(
                $cid,
                $taskId,
                $title,
                $description,
                (int) $targetUserId,
                isset($_SESSION["uid"]) ? (int) $_SESSION["uid"] : null,
                "workflow",
            );
        } elseif ($targetScope === "role" && $targetRole) {
            workflow_task_notice(
                $cid,
                $taskId,
                $title,
                $description,
                $patientLinkId,
                $appointmentId,
                $targetScope,
                $targetRole,
                $targetUserId,
                $sourceEvent,
                $sourceEntity,
                $sourceEntityId,
            );
        }
        audit("tarefa_criada", "tarefa", $taskId, [
            "clinic_id" => $cid,
            "titulo" => $title,
            "destino" => $targetScope,
            "cargo" => $targetRole,
            "source_event" => $sourceEvent,
            "audit_body" =>
                "Tarefa automática criada para distribuir a próxima obrigação da equipe.",
        ]);
        return $taskId;
    } catch (Throwable $e) {
        if ($strict) {
            throw $e;
        }
        error_log("[Prontoo workflow task] " . $e->getMessage());
        return null;
    }
}
function workflow_on_patient_arrived(
    int $cid,
    int $appointmentId,
    int $patientLinkId,
): void {
    $name = patient_display_name($patientLinkId, $cid);
    create_workflow_task(
        $cid,
        "Preparar atendimento de " . $name,
        "Paciente chegou. Conferir dados essenciais, preparativos e triagem quando aplicável. Avise o profissional somente quando estiver pronto.",
        null,
        $patientLinkId,
        $appointmentId,
        "paciente_chegou",
        "consulta",
        (string) $appointmentId,
        date("Y-m-d H:i:s", strtotime("+10 minutes")),
        "role",
        "assistente",
    );
}
function workflow_on_task_completed(
    array $task,
    int $completedBy,
    string $role,
): void {
    $cid = (int) ($task["clinic_id"] ?? 0);
    if ($cid <= 0) {
        return;
    }
    $source = (string) ($task["source_event"] ?? "");
    $appt = (int) ($task["appointment_id"] ?? 0);
    $patient = (int) ($task["patient_link_id"] ?? 0);
    $name = $patient ? patient_display_name($patient, $cid) : "paciente";
    if ($source === "paciente_chegou" && $appt > 0) {
        q(
            "UPDATE pi_appointments SET status='pronto_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('chegou','em_preparo') AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
            [$appt, $cid],
        );
        $doctor =
            (int) (val(
                "SELECT doctor_user_id FROM pi_appointments WHERE id=? AND clinic_id=?",
                [$appt, $cid],
            ) ?:
            0);
        create_workflow_task(
            $cid,
            "Atender " . $name,
            "Preparativos concluídos. O paciente está pronto para iniciar o atendimento. Abra a ficha, inicie a consulta e conclua esta tarefa ao finalizar.",
            $doctor ?: null,
            $patient ?: null,
            $appt,
            "preparo_concluido",
            "consulta",
            (string) $appt,
            null,
            $doctor ? "user" : "role",
            $doctor ? null : "medico",
        );
    }
    if ($source === "preparo_concluido" && $appt > 0) {
        workflow_on_appointment_finished(
            $cid,
            $appt,
            $patient,
            $completedBy,
            "tarefa_profissional",
        );
    }
    if (
        ($source === "atendimento_finalizado" ||
            $source === "atendimento_encaminhado") &&
        $appt > 0
    ) {
        q(
            "UPDATE pi_appointments SET status='finalizado', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='atendimento_concluido' AND consultation_finished_at IS NOT NULL",
            [$appt, $cid],
        );
        if (workflow_appointment_payment_pending($cid, $appt)) {
            create_workflow_task(
                $cid,
                "Conferir pagamento pendente de " . $name,
                "A saída do paciente foi concluída, mas a consulta segue com pagamento previsto sem baixa. Conferir com a Recepção e regularizar o financeiro.",
                null,
                $patient ?: null,
                $appt,
                "pagamento_pos_atendimento_pendente",
                "consulta",
                (string) $appt,
                null,
                "role",
                "gerente",
            );
        } else {
            audit("fluxo_saida_concluida", "consulta", $appt, [
                "patient_link_id" => $patient,
                "audit_body" =>
                    "A Recepção concluiu a saída do paciente. Não foi gerado novo recado porque não havia exceção pendente.",
            ]);
        }
    }
}
function task_nav_counts(array $c): array
{
    $zero = [
        "open" => 0,
        "today" => 0,
        "overdue" => 0,
        "start" => 0,
        "mine" => 0,
        "role" => 0,
        "clinic" => 0,
        "progress" => 0,
        "done" => 0,
        "all" => 0,
    ];
    if (!$c || ($c["scope"] ?? "") !== "clinic") {
        return $zero;
    }
    $cid = (int) ($c["clinic_id"] ?? 0);
    $uid = (int) ($c["user"]["id"] ?? 0);
    $role = (string) ($c["role"] ?? "");
    if ($cid <= 0 || $uid <= 0) {
        return $zero;
    }
    static $cache = [];
    $key = $cid . "|" . $uid . "|" . $role;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    [$todayStart, $todayEnd] = app_local_day_utc_range(
        app_today_in_timezone($cid, $c),
        $cid,
        $c,
    );
    try {
        $row =
            one(
                "SELECT 
 COUNT(*) AS all_count,
 SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') THEN 1 ELSE 0 END) AS open_count,
 SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at>=? AND due_at<? THEN 1 ELSE 0 END) AS today_count,
 SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at<NOW() THEN 1 ELSE 0 END) AS overdue_count,
 SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND assigned_to IS NULL AND (COALESCE(target_scope,'clinic')='clinic' OR (COALESCE(target_scope,'clinic')='role' AND target_role=?) OR (COALESCE(target_scope,'clinic')='user' AND COALESCE(target_user_id,assigned_to)=?)) THEN 1 ELSE 0 END) AS start_count,
 SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND assigned_to=? THEN 1 ELSE 0 END) AS mine_count,
 SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND assigned_to IS NULL AND COALESCE(target_scope,'clinic')='role' AND target_role=? THEN 1 ELSE 0 END) AS role_count,
 SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND assigned_to IS NULL AND COALESCE(target_scope,'clinic')='clinic' THEN 1 ELSE 0 END) AS clinic_count,
 SUM(CASE WHEN status='em_andamento' THEN 1 ELSE 0 END) AS progress_count,
 SUM(CASE WHEN status NOT IN ('aberta','em_andamento','aguardando') THEN 1 ELSE 0 END) AS done_count
 FROM pi_tasks WHERE clinic_id=?",
                [$todayStart, $todayEnd, $role, $uid, $uid, $role, $cid],
            ) ?:
            [];
        $cache[$key] = [
            "open" => (int) ($row["open_count"] ?? 0),
            "today" => (int) ($row["today_count"] ?? 0),
            "overdue" => (int) ($row["overdue_count"] ?? 0),
            "start" => (int) ($row["start_count"] ?? 0),
            "mine" => (int) ($row["mine_count"] ?? 0),
            "role" => (int) ($row["role_count"] ?? 0),
            "clinic" => (int) ($row["clinic_count"] ?? 0),
            "progress" => (int) ($row["progress_count"] ?? 0),
            "done" => (int) ($row["done_count"] ?? 0),
            "all" => (int) ($row["all_count"] ?? 0),
        ];
        return $cache[$key];
    } catch (Throwable $e) {
        return $zero;
    }
}
function page_admin_global_notices(): void
{
    require_can("admin_global_notices");
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = $_POST["act"] ?? "create";
        $id = (int) ($_POST["id"] ?? 0);
        if ($act === "toggle" && $id) {
            q(
                "UPDATE pi_global_notices SET active=1-active, updated_at=NOW() WHERE id=?",
                [$id],
            );
            audit("aviso_global_status", "aviso_global", $id);
            flash("Status da aviso atualizado.");
            redirect("admin_global_notices");
        }
        $title = trim((string) ($_POST["title"] ?? ""));
        $body = trim((string) ($_POST["body"] ?? ""));
        if ($title === "" || $body === "") {
            flash("Informe título e mensagem.", "bad");
            redirect("admin_global_notices");
        }
        $severity = (string) ($_POST["severity"] ?? "info");
        if (!in_array($severity, ["info", "warning", "critical"], true)) {
            $severity = "info";
        }
        $startsAt = trim((string) ($_POST["starts_at"] ?? ""));
        $expiresAt = trim((string) ($_POST["expires_at"] ?? ""));
        $startsAt = $startsAt !== "" ? app_local_to_db_utc($startsAt) : null;
        $expiresAt = $expiresAt !== "" ? app_local_to_db_utc($expiresAt) : null;
        if ($startsAt !== null && $expiresAt !== null && $expiresAt <= $startsAt) {
            flash("O término do aviso deve ser posterior ao início.", "bad");
            redirect("admin_global_notices");
        }
        q(
            "INSERT INTO pi_global_notices (title,body,severity,starts_at,expires_at,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())",
            [
                $title,
                $body,
                $severity,
                $startsAt,
                $expiresAt,
                $_SESSION["uid"] ?? null,
            ],
        );
        counter_inc("global_notices_total");
        audit(
            "aviso_global_criado",
            "aviso_global",
            db_last_insert_id(),
            $_POST,
        );
        flash("Aviso global publicada.");
        redirect("admin_global_notices");
    }
    $form =
        '<details class="form-panel"><summary class="primary small cmdlike">' .
        action_summary_label("Nova aviso global", "notifications") .
        '</summary><form method="post" class="compact">' .
        csrf_field() .
        form_row("Título", input("title", "text", "", "required")) .
        form_row("Mensagem", textarea("body", "", "required")) .
        select_label(
            "Severidade",
            "severity",
            [
                "info" => "Informativo",
                "warning" => "Atenção",
                "critical" => "Crítico",
            ],
            "info",
        ) .
        form_row("Início", input("starts_at", "datetime-local")) .
        form_row("Expira em", input("expires_at", "datetime-local")) .
        form_actions("Publicar") .
        "</form></details>";
    $rows = q(
        "SELECT id,title,body,severity,active,starts_at,expires_at,created_by,created_at FROM pi_global_notices ORDER BY created_at DESC LIMIT 100",
    )->fetchAll();
    $authors = fetch_map("pi_users", int_ids($rows, "created_by"), "id,name");
    $active = 0;
    $critical = 0;
    $inactive = 0;
    foreach ($rows as $r) {
        if ((int) $r["active"]) {
            $active++;
        } else {
            $inactive++;
        }
        if ((string) $r["severity"] === "critical" && (int) $r["active"]) {
            $critical++;
        }
    }
    $stats =
        '<div class="notice-kpis"><div class="notice-kpi">' .
        icon("notifications_active") .
        "<div><b>" .
        (int) $active .
        '</b><span>Ativas</span></div></div><div class="notice-kpi notice-kpi-critical">' .
        icon("priority_high") .
        "<div><b>" .
        (int) $critical .
        '</b><span>Críticas</span></div></div><div class="notice-kpi">' .
        icon("visibility_off") .
        "<div><b>" .
        (int) $inactive .
        "</b><span>Inativas</span></div></div></div>";
    $cards = "";
    $severityMap = [
        "critical" => ["Crítico", "priority_high"],
        "warning" => ["Atenção", "warning"],
        "info" => ["Informativo", "info"],
    ];
    foreach ($rows as $r) {
        $a = $authors[(int) ($r["created_by"] ?? 0)] ?? [];
        $sev = (string) ($r["severity"] ?? "info");
        $sevInfo = $severityMap[$sev] ?? $severityMap["info"];
        $activeLabel = (int) $r["active"] ? "Ativa" : "Inativa";
        $btn =
            '<form method="post" class="inline">' .
            csrf_field() .
            '<input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="' .
            (int) $r["id"] .
            '"><button type="submit" class="small ' .
            ((int) $r["active"] ? "danger" : "primary") .
            '">' .
            ((int) $r["active"] ? "Desativar" : "Ativar") .
            "</button></form>";
        $period = [];
        if (!empty($r["starts_at"])) {
            $period[] = "Início: " . dt_br($r["starts_at"]);
        }
        if (!empty($r["expires_at"])) {
            $period[] = "Expira: " . dt_br($r["expires_at"]);
        }
        $cards .=
            '<article class="notice-card global severity-' .
            e($sev) .
            " " .
            ((int) $r["active"] ? "is-active" : "is-inactive") .
            '">' .
            '<div class="notice-icon">' .
            icon($sevInfo[1]) .
            "</div>" .
            '<div class="notice-main"><header><span class="notice-eyebrow">' .
            e($sevInfo[0]) .
            " · " .
            e($activeLabel) .
            "</span><time>" .
            e(dt_notice_br($r["created_at"])) .
            "</time></header>" .
            "<h3>" .
            e($r["title"]) .
            "</h3><p>" .
            nl2br(e($r["body"])) .
            "</p>" .
            "<footer><span>Autor: " .
            e(first_name($a["name"] ?? "")) .
            "</span>" .
            ($period ? "<span>" . e(implode(" · ", $period)) . "</span>" : "") .
            "</footer></div>" .
            '<div class="notice-actions">' .
            $btn .
            "</div></article>";
    }
    $list =
        $cards !== ""
            ? '<div class="notice-list">' . $cards . "</div>"
            : '<div class="notice-empty">' .
                icon("campaign") .
                "<strong>Nenhuma aviso global.</strong></div>";
    page(
        "Avisos Globais",
        page_head("Avisos Globais", "", $form) .
            '<section class="notice-screen notice-admin">' .
            $stats .
            card($list, "notice-card-shell") .
            "</section>",
    );
}
function page_tasks(): void
{
    $c = require_can("tasks");
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    $role = (string) ($c["role"] ?? "");
    $activeStatuses = "'aberta','em_andamento','aguardando'";
    $roleOptions = clinic_role_options($cid, true);
    $users = team_options($cid);
    $canStartTask = function (array $task) use ($uid, $role): bool {
        if (
            !in_array(
                (string) ($task["status"] ?? "aberta"),
                ["aberta", "aguardando"],
                true,
            )
        ) {
            return false;
        }
        if (!empty($task["started_at"])) {
            return false;
        }
        $assigned = (int) ($task["assigned_to"] ?? 0);
        if ($assigned > 0) {
            return $assigned === $uid || $role === "gerente";
        }
        $scope = (string) ($task["target_scope"] ?? "clinic");
        if ($scope === "" || $scope === "all") {
            $scope = "clinic";
        }
        if ($role === "gerente") {
            return true;
        }
        if ($scope === "clinic") {
            return true;
        }
        if ($scope === "role") {
            return (string) ($task["target_role"] ?? "") === $role;
        }
        if ($scope === "user") {
            return (int) ($task["target_user_id"] ?? 0) === $uid;
        }
        return false;
    };
    $destinationLabel = function (array $task) use ($cid, $users): string {
        $assigned = trim((string) ($task["assigned_name"] ?? ""));
        if ($assigned !== "") {
            return "Responsável: " . first_name($assigned);
        }
        $scope = (string) ($task["target_scope"] ?? "clinic");
        if ($scope === "" || $scope === "all") {
            $scope = "clinic";
        }
        if ($scope === "role") {
            return "Disponível para " .
                role_label_for((string) ($task["target_role"] ?? ""), $cid);
        }
        if ($scope === "user") {
            $u = (int) ($task["target_user_id"] ?? 0);
            return "Disponível para " . ($users[$u] ?? "pessoa específica");
        }
        return "Disponível para a clínica";
    };
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = $_POST["act"] ?? "create";
        if ($act === "start") {
            $tid = (int) ($_POST["id"] ?? 0);
            $task = one(
                "SELECT t.id,t.clinic_id,t.title,t.status,t.assigned_to,t.target_scope,t.target_role,t.target_user_id,t.started_at,td.patient_link_id,td.appointment_id,td.source_event,td.source_entity,td.source_entity_id FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id WHERE t.id=? AND t.clinic_id=?",
                [$tid, $cid],
            );
            if (!$task) {
                flash("Tarefa não encontrada.", "bad");
                redirect("tasks");
            }
            if (!$canStartTask($task)) {
                flash(
                    "Esta tarefa não está disponível para ser iniciada por esta credencial.",
                    "bad",
                );
                redirect("tasks");
            }
            $wasAssigned = (int) ($task["assigned_to"] ?? 0);
            if ($wasAssigned > 0) {
                $st = q(
                    "UPDATE pi_tasks SET started_by=?, started_at=NOW(), status='em_andamento', updated_at=NOW() WHERE id=? AND clinic_id=? AND assigned_to=? AND status IN ('aberta','aguardando') AND started_at IS NULL",
                    [$uid, $tid, $cid, $wasAssigned],
                );
            } else {
                $st = q(
                    "UPDATE pi_tasks SET assigned_to=?, started_by=?, started_at=NOW(), status='em_andamento', updated_at=NOW() WHERE id=? AND clinic_id=? AND assigned_to IS NULL AND status IN ('aberta','aguardando') AND started_at IS NULL",
                    [$uid, $uid, $tid, $cid],
                );
            }
            if ($st->rowCount() < 1) {
                flash("Outra pessoa já começou esta tarefa.", "bad");
                redirect("tasks");
            }
            task_event(
                $cid,
                $tid,
                "iniciada",
                $uid,
                (string) $task["status"],
                "em_andamento",
                $wasAssigned > 0
                    ? "Tarefa marcada como iniciada."
                    : "Tarefa assumida individualmente.",
            );
            workflow_on_task_started($task, $uid, $role);
            audit("tarefa_iniciada", "tarefa", $tid, [
                "titulo" => (string) $task["title"],
                "audit_body" =>
                    $wasAssigned > 0
                        ? "A tarefa foi marcada como iniciada."
                        : "A tarefa foi assumida individualmente pelo usuário.",
            ]);
            flash("Tarefa iniciada. Ela agora aparece em Iniciadas.");
            redirect("tasks", ["view" => "progress"]);
        }
        if ($act === "release") {
            $tid = (int) ($_POST["id"] ?? 0);
            if ($role !== "gerente") {
                flash(
                    "Apenas o Administrativo pode devolver tarefa para a fila.",
                    "bad",
                );
                redirect("tasks");
            }
            $task = one(
                "SELECT id,title,target_scope,assigned_to FROM pi_tasks WHERE id=? AND clinic_id=?",
                [$tid, $cid],
            );
            if (!$task) {
                flash("Tarefa não encontrada.", "bad");
                redirect("tasks");
            }
            if ((string) ($task["target_scope"] ?? "clinic") === "user") {
                flash(
                    "Tarefa destinada a pessoa específica deve ser reatribuída, não devolvida à fila.",
                    "bad",
                );
                redirect("tasks");
            }
            q(
                "UPDATE pi_tasks SET assigned_to=NULL, started_by=NULL, started_at=NULL, status='aberta', updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$tid, $cid],
            );
            task_event(
                $cid,
                $tid,
                "devolvida_fila",
                $uid,
                null,
                "aberta",
                "Tarefa devolvida para a fila coletiva.",
            );
            audit("tarefa_devolvida_fila", "tarefa", $tid, [
                "titulo" => (string) $task["title"],
                "audit_body" =>
                    "A tarefa foi devolvida para sua fila coletiva de origem.",
            ]);
            flash("Tarefa devolvida para a fila coletiva.");
            redirect("tasks");
        }
        if ($act === "done") {
            $tid = (int) ($_POST["id"] ?? 0);
            $task = one(
                "SELECT t.id,t.clinic_id,t.title,t.assigned_to,td.patient_link_id,td.appointment_id,td.source_event,td.source_entity,td.source_entity_id,t.status FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id WHERE t.id=? AND t.clinic_id=?",
                [$tid, $cid],
            );
            if (!$task) {
                flash("Tarefa não encontrada.", "bad");
                redirect("tasks");
            }
            if (empty($task["assigned_to"])) {
                flash("Comece a tarefa antes de concluí-la.", "bad");
                redirect("tasks");
            }
            if ((int) $task["assigned_to"] !== $uid && $role !== "gerente") {
                flash(
                    "Esta tarefa está com outra pessoa. Apenas o responsável ou o Administrativo pode concluí-la.",
                    "bad",
                );
                redirect("tasks");
            }
            q(
                "UPDATE pi_tasks SET status='concluida', completed_by=?, completed_at=NOW(), updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$uid, $tid, $cid],
            );
            task_event(
                $cid,
                $tid,
                "concluida",
                $uid,
                (string) $task["status"],
                "concluida",
                "Tarefa concluída.",
            );
            workflow_on_task_completed($task, $uid, $role);
            clinic_metric_inc($cid, "tasks_completed");
            audit("tarefa_concluida", "tarefa", $tid);
            flash("Tarefa concluída.");
            redirect("tasks");
        }
        if ($act === "comment_edit") {
            $commentId = (int) ($_POST["comment_id"] ?? 0);
            $body = trim((string) ($_POST["comment"] ?? ""));
            if ($body === "") {
                flash("Informe o novo texto do comentário.", "bad");
                redirect("tasks");
            }
            $cm = one(
                "SELECT tc.id,tc.task_id,tc.user_id FROM pi_task_comments tc INNER JOIN pi_tasks t ON t.id=tc.task_id AND t.clinic_id=tc.clinic_id WHERE tc.id=? AND tc.clinic_id=? AND tc.deleted_at IS NULL",
                [$commentId, $cid],
            );
            if (!$cm) {
                flash("Comentário não encontrado.", "bad");
                redirect("tasks");
            }
            if ((int) $cm["user_id"] !== $uid && $role !== "gerente") {
                flash(
                    "Apenas quem escreveu o comentário ou a Gestão pode editá-lo.",
                    "bad",
                );
                redirect("tasks");
            }
            q(
                "UPDATE pi_task_comments SET body=?, edited_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND deleted_at IS NULL",
                [$body, $uid, $commentId, $cid],
            );
            task_event(
                $cid,
                (int) $cm["task_id"],
                "comentario_editado",
                $uid,
                null,
                null,
                "Comentário interno editado.",
            );
            audit("comentario_tarefa_editado", "tarefa", (int) $cm["task_id"], [
                "comentario_id" => $commentId,
            ]);
            flash("Comentário atualizado.");
            redirect("tasks");
        }
        if ($act === "comment_delete") {
            $commentId = (int) ($_POST["comment_id"] ?? 0);
            $cm = one(
                "SELECT tc.id,tc.task_id,tc.user_id FROM pi_task_comments tc INNER JOIN pi_tasks t ON t.id=tc.task_id AND t.clinic_id=tc.clinic_id WHERE tc.id=? AND tc.clinic_id=? AND tc.deleted_at IS NULL",
                [$commentId, $cid],
            );
            if (!$cm) {
                flash("Comentário não encontrado.", "bad");
                redirect("tasks");
            }
            if ((int) $cm["user_id"] !== $uid && $role !== "gerente") {
                flash(
                    "Apenas quem escreveu o comentário ou a Gestão pode excluí-lo.",
                    "bad",
                );
                redirect("tasks");
            }
            q(
                "UPDATE pi_task_comments SET deleted_at=NOW(), deleted_by=? WHERE id=? AND clinic_id=? AND deleted_at IS NULL",
                [$uid, $commentId, $cid],
            );
            task_event(
                $cid,
                (int) $cm["task_id"],
                "comentario_excluido",
                $uid,
                null,
                null,
                "Comentário interno excluído.",
            );
            audit(
                "comentario_tarefa_excluido",
                "tarefa",
                (int) $cm["task_id"],
                ["comentario_id" => $commentId],
            );
            flash("Comentário excluído.");
            redirect("tasks");
        }
        if ($act === "comment") {
            $tid = (int) ($_POST["id"] ?? 0);
            $body = trim((string) ($_POST["comment"] ?? ""));
            if ($body === "") {
                flash("Informe o comentário da tarefa.", "bad");
                redirect("tasks");
            }
            $task = one("SELECT id FROM pi_tasks WHERE id=? AND clinic_id=?", [
                $tid,
                $cid,
            ]);
            if (!$task) {
                flash("Tarefa não encontrada.", "bad");
                redirect("tasks");
            }
            q(
                "INSERT INTO pi_task_comments (clinic_id,task_id,user_id,body,created_at) VALUES (?,?,?,?,NOW())",
                [$cid, $tid, $uid, $body],
            );
            task_event(
                $cid,
                $tid,
                "comentario_criado",
                $uid,
                null,
                null,
                "Comentário interno registrado.",
            );
            audit("comentario_tarefa_criado", "tarefa", $tid, [
                "comentario" => $body,
            ]);
            flash("Comentário registrado.");
            redirect("tasks");
        }
        $title = trim((string) ($_POST["title"] ?? ""));
        if ($title === "") {
            flash("Informe o título da tarefa.", "bad");
            redirect("tasks");
        }
        $targetScope = (string) ($_POST["target_scope"] ?? "clinic");
        if (!in_array($targetScope, ["clinic", "role", "user"], true)) {
            $targetScope = "clinic";
        }
        $targetRole = null;
        $targetUserId = null;
        $assigned = null;
        if ($targetScope === "role") {
            $targetRole = (string) ($_POST["target_role"] ?? "");
            if (!array_key_exists($targetRole, $roleOptions)) {
                flash("Escolha um cargo válido da clínica.", "bad");
                redirect("tasks");
            }
        } elseif ($targetScope === "user") {
            $targetUserId = (int) ($_POST["target_user_id"] ?? 0);
            if (
                $targetUserId <= 0 ||
                !clinic_user_exists($cid, $targetUserId)
            ) {
                flash("Escolha uma pessoa vinculada à clínica atual.", "bad");
                redirect("tasks");
            }
            $assigned = $targetUserId;
        }
        $taskDescription = trim((string) ($_POST["description"] ?? ""));
        $dueAt = trim((string) ($_POST["due_at"] ?? ""));
        $dueAt = $dueAt !== "" ? app_local_to_db_utc($dueAt, $cid, $c) : null;
        q(
            "INSERT INTO pi_tasks (clinic_id,title,target_scope,target_role,target_user_id,assigned_to,due_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                $title,
                $targetScope,
                $targetRole,
                $targetUserId,
                $assigned,
                $dueAt,
                $uid,
            ],
        );
        $taskId = db_last_insert_id();
        q(
            "INSERT INTO pi_task_details (task_id,clinic_id,description) VALUES (?,?,?)",
            [$taskId, $cid, $taskDescription],
        );
        task_event(
            $cid,
            $taskId,
            "criada",
            $uid,
            null,
            "aberta",
            "Tarefa criada manualmente.",
        );
        counter_inc("tasks_total");
        clinic_metric_inc($cid, "tasks");
        if ($targetScope === "user" && $targetUserId) {
            notify_task_personal_assignment(
                $cid,
                $taskId,
                $title,
                $taskDescription,
                (int) $targetUserId,
                $uid,
                "manual",
            );
        }
        audit("tarefa_criada", "tarefa", $taskId, [
            "titulo" => $title,
            "destino" => $targetScope,
            "cargo" => $targetRole,
            "pessoa" => $targetUserId,
        ]);
        flash("Tarefa criada.");
        redirect("tasks");
    }
    $targetOptions = [
        "" => "Escolha o destinatário",
        "clinic" => "Toda a clínica",
        "role" => "Todos do mesmo cargo",
        "user" => "Pessoa específica",
    ];
    $roleField =
        '<div data-task-target-field="role" hidden>' .
        select_label(
            "Cargo específico",
            "target_role",
            ["" => "Selecionar cargo"] + $roleOptions,
            null,
        ) .
        "</div>";
    $userField =
        '<div data-task-target-field="user" hidden>' .
        select_label(
            "Pessoa específica",
            "target_user_id",
            ["" => "Selecionar pessoa"] + $users,
            null,
        ) .
        "</div>";
    $taskCreateFields =
        csrf_field() .
        form_row("Título", input("title", "text", "", "required")) .
        form_row("Descrição", textarea("description")) .
        select_label(
            "Destinatário da tarefa",
            "target_scope",
            $targetOptions,
            null,
            "required data-task-target-scope",
        ) .
        $roleField .
        $userField .
        form_row("Prazo", input("due_at", "datetime-local")) .
        '<small class="field-help">Escolha primeiro o destinatário. Tarefas para toda a clínica ou para um cargo ficam em fila coletiva até alguém clicar em Começar.</small>' .
        form_actions("Salvar tarefa");
    if (($_GET["new"] ?? "") === "1") {
        $back =
            '<a class="ghost small" href="' .
            href("tasks") .
            '">' .
            icon("arrow_back") .
            "<span>Voltar</span></a>";
        page(
            "Nova tarefa",
            page_head(
                "Nova tarefa",
                "Cadastre uma demanda da rotina em uma tela própria, mantendo a listagem apenas para consulta e acompanhamento.",
                $back,
            ) .
                card(
                    '<form method="post" class="compact task-create-form ds-entity-form ds-standalone-form" data-task-create-form>' .
                        $taskCreateFields .
                        "</form>",
                    "task-create-shell ds-form-shell",
                ),
        );
        return;
    }
    $form =
        '<a class="primary small cmdlike" href="' .
        href("tasks", ["new" => 1]) .
        '">' .
        action_summary_label("Nova tarefa", "add_task") .
        "</a>";
    $view = preg_replace("/[^a-z_]/", "", (string) ($_GET["view"] ?? "mine"));
    if ($view === "") {
        $view = "mine";
    }
    $qTerm = trim((string) ($_GET["q"] ?? ""));
    $taskSearchMode = $qTerm !== "";
    $where = ["t.clinic_id=?"];
    $params = [$cid];
    $active = "t.status IN ('aberta','em_andamento','aguardando')";
    if (!$taskSearchMode) {
        if ($view === "mine") {
            $where[] = $active . " AND t.assigned_to=?";
            $params[] = $uid;
        } elseif ($view === "role") {
            $where[] =
                $active .
                " AND t.assigned_to IS NULL AND COALESCE(t.target_scope,'clinic')='role' AND t.target_role=?";
            $params[] = $role;
        } elseif ($view === "clinic") {
            $where[] =
                $active .
                " AND t.assigned_to IS NULL AND COALESCE(t.target_scope,'clinic')='clinic'";
        } elseif ($view === "progress") {
            $where[] = "t.status='em_andamento'";
        } elseif ($view === "overdue") {
            $where[] = $active . " AND t.due_at IS NOT NULL AND t.due_at<NOW()";
        } elseif ($view === "today") {
            [$taskTodayStart, $taskTodayEnd] = app_local_day_utc_range(
                app_today_in_timezone($cid, $c),
                $cid,
                $c,
            );
            $where[] =
                $active .
                " AND t.due_at IS NOT NULL AND t.due_at>=? AND t.due_at<?";
            $params[] = $taskTodayStart;
            $params[] = $taskTodayEnd;
        } elseif ($view === "done") {
            $where[] = "t.status NOT IN ('aberta','em_andamento','aguardando')";
        } elseif ($view === "all") {
        } elseif ($view === "start") {
            $view = "role";
            $where[] =
                $active .
                " AND t.assigned_to IS NULL AND COALESCE(t.target_scope,'clinic')='role' AND t.target_role=?";
            $params[] = $role;
        } else {
            $view = "mine";
            $where[] = $active . " AND t.assigned_to=?";
            $params[] = $uid;
        }
    } else {
        if (
            !in_array(
                $view,
                [
                    "mine",
                    "role",
                    "clinic",
                    "progress",
                    "overdue",
                    "today",
                    "done",
                    "all",
                    "start",
                ],
                true,
            )
        ) {
            $view = "mine";
        }
    }
    if ($qTerm !== "") {
        $like = "%" . $qTerm . "%";
        $where[] =
            "(t.title LIKE ? OR td.description LIKE ? OR p.full_name LIKE ? OR u.name LIKE ? OR DATE_FORMAT(t.due_at,'%d/%m/%Y') LIKE ? OR DATE_FORMAT(t.created_at,'%d/%m/%Y') LIKE ?)";
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    [$taskOrderTodayStart, $taskOrderTodayEnd] = app_local_day_utc_range(
        app_today_in_timezone($cid, $c),
        $cid,
        $c,
    );
    $sql =
        "SELECT t.id,t.title,td.description,t.target_scope,t.target_role,t.target_user_id,t.assigned_to,t.started_at,t.started_by,t.status,t.due_at,t.completed_at,t.created_at,td.patient_link_id,td.appointment_id,td.source_event,td.source_entity,td.source_entity_id,u.name AS assigned_name,su.name AS started_name,p.full_name AS patient_name
 FROM pi_tasks t
 LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id
 LEFT JOIN pi_users u ON u.id=t.assigned_to AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=t.clinic_id AND ur.active=1)
 LEFT JOIN pi_users su ON su.id=t.started_by AND EXISTS (SELECT 1 FROM pi_user_roles ur2 WHERE ur2.user_id=su.id AND ur2.clinic_id=t.clinic_id AND ur2.active=1)
 LEFT JOIN pi_patients pl ON pl.id=td.patient_link_id AND pl.clinic_id=t.clinic_id
 LEFT JOIN pi_persons p ON p.id=pl.person_id
 WHERE " .
        implode(" AND ", $where) .
        "
 ORDER BY 
 CASE 
 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.assigned_to=? THEN 0
 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.assigned_to IS NULL AND COALESCE(t.target_scope,'clinic')='role' THEN 1
 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.assigned_to IS NULL AND COALESCE(t.target_scope,'clinic')='clinic' THEN 2
 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.assigned_to IS NOT NULL THEN 3
 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.due_at IS NOT NULL AND t.due_at<NOW() THEN 4
 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.due_at>=" .
        pdo()->quote($taskOrderTodayStart) .
        " AND t.due_at<" .
        pdo()->quote($taskOrderTodayEnd) .
        " THEN 5
 WHEN t.status NOT IN ('aberta','em_andamento','aguardando') THEN 6
 ELSE 7
 END,
 t.due_at IS NULL ASC,
 t.due_at ASC,
 t.id DESC
 LIMIT 160";
    $rows = q($sql, array_merge($params, [$uid]))->fetchAll();
    $commentsByTask = [];
    $taskIds = int_ids($rows, "id");
    if ($taskIds) {
        $ph = implode(",", array_fill(0, count($taskIds), "?"));
        $commentRows = q(
            "SELECT tc.id,tc.task_id,tc.user_id,tc.body,tc.created_at,tc.updated_at,u.name AS user_name FROM pi_task_comments tc LEFT JOIN pi_users u ON u.id=tc.user_id WHERE tc.clinic_id=? AND tc.deleted_at IS NULL AND tc.task_id IN ($ph) ORDER BY tc.created_at ASC",
            array_merge([$cid], $taskIds),
        )->fetchAll();
        foreach ($commentRows as $cr) {
            $commentsByTask[(int) $cr["task_id"]][] = $cr;
        }
    }
    $counts = task_nav_counts($c);
    $activeClass = function (string $v) use ($view, $qTerm): string {
        return $qTerm === "" && $v === $view ? " active" : "";
    };
    $qs = function (string $v): array {
        return ["view" => $v];
    };
    $taskFoundChip =
        $qTerm !== ""
            ? '<span class="task-filter active is-active patient-filter-found" aria-current="page" data-ds-filter-chip>' .
                icon("manage_search") .
                "<span>Encontrados</span><small>" .
                number_format(count($rows), 0, ",", ".") .
                "</small></span>"
            : "";
    $filters =
        '<div class="task-filters" aria-label="Filtros de tarefas">' .
        $taskFoundChip .
        '<a class="task-filter' .
        $activeClass("mine") .
        '" href="' .
        href("tasks", $qs("mine")) .
        '">' .
        icon("person_check") .
        "<span>Minhas</span>" .
        ($counts["mine"] > 0 ? "<b>" . (int) $counts["mine"] . "</b>" : "") .
        "</a>" .
        '<a class="task-filter' .
        $activeClass("role") .
        '" href="' .
        href("tasks", $qs("role")) .
        '">' .
        icon("groups") .
        "<span>Do meu cargo</span>" .
        ($counts["role"] > 0 ? "<b>" . (int) $counts["role"] . "</b>" : "") .
        "</a>" .
        '<a class="task-filter' .
        $activeClass("clinic") .
        '" href="' .
        href("tasks", $qs("clinic")) .
        '">' .
        icon("home_health") .
        "<span>Da Clínica</span>" .
        ($counts["clinic"] > 0
            ? "<b>" . (int) $counts["clinic"] . "</b>"
            : "") .
        "</a>" .
        '<a class="task-filter' .
        $activeClass("progress") .
        '" href="' .
        href("tasks", $qs("progress")) .
        '">' .
        icon("pending") .
        "<span>Iniciadas</span>" .
        ($counts["progress"] > 0
            ? "<b>" . (int) $counts["progress"] . "</b>"
            : "") .
        "</a>" .
        '<a class="task-filter' .
        $activeClass("overdue") .
        '" href="' .
        href("tasks", $qs("overdue")) .
        '">' .
        icon("priority_high") .
        "<span>Atrasadas</span>" .
        ($counts["overdue"] > 0
            ? "<b>" . (int) $counts["overdue"] . "</b>"
            : "") .
        "</a>" .
        '<a class="task-filter' .
        $activeClass("today") .
        '" href="' .
        href("tasks", $qs("today")) .
        '">' .
        icon("today") .
        "<span>Hoje</span>" .
        ($counts["today"] > 0 ? "<b>" . (int) $counts["today"] . "</b>" : "") .
        "</a>" .
        '<a class="task-filter' .
        $activeClass("done") .
        '" href="' .
        href("tasks", $qs("done")) .
        '">' .
        icon("task_alt") .
        "<span>Concluídas</span></a>" .
        '<a class="task-filter' .
        $activeClass("all") .
        '" href="' .
        href("tasks", $qs("all")) .
        '">' .
        icon("view_list") .
        "<span>Todas</span></a>" .
        "</div>";
    $search =
        '<section class="task-directory-search ds-search-block"><form method="get" class="task-search patient-search-bar" role="search"><input type="hidden" name="r" value="tasks"><input type="hidden" name="view" value="' .
        e($view) .
        '"><label class="search-field"><input name="q" type="search" value="' .
        e($qTerm) .
        '" placeholder="Paciente, responsável, descrição ou data" autocomplete="off" aria-label="Buscar tarefa por paciente, responsável, descrição ou data"></label><button class="primary small icon-only" type="submit" aria-label="Buscar" title="Buscar">' .
        icon("search") .
        '<span class="sr-only">Buscar</span></button>' .
        ($qTerm !== ""
            ? '<a class="ghost small" href="' .
                href("tasks", ["view" => $view]) .
                '">' .
                icon("close") .
                "<span>Limpar</span></a>"
            : "") .
        "</form></section>";
    $groups = [
        "mine" => [
            "title" => "Minhas tarefas",
            "hint" =>
                "Demandas que já estão sob sua responsabilidade individual.",
            "rows" => [],
        ],
        "role" => [
            "title" => "Do meu cargo",
            "hint" =>
                "Fila coletiva do cargo atual. Clique em Começar para assumir uma tarefa.",
            "rows" => [],
        ],
        "clinic" => [
            "title" => "Da Clínica",
            "hint" =>
                "Fila coletiva aberta para a equipe da clínica. Clique em Começar para assumir uma tarefa.",
            "rows" => [],
        ],
        "progress" => [
            "title" => "Iniciadas",
            "hint" => "Tarefas já assumidas por outra pessoa.",
            "rows" => [],
        ],
        "done" => [
            "title" => "Concluídas",
            "hint" => "Últimas tarefas encerradas.",
            "rows" => [],
        ],
    ];
    $originLabels = [
        "paciente_chegou" => "Gerada automaticamente após chegada do paciente.",
        "preparo_concluido" =>
            "Gerada quando a preparação do atendimento foi concluída.",
        "atendimento_encaminhado" =>
            "Gerada para acompanhamento administrativo após o atendimento.",
    ];
    $now = time();
    $today = date("Y-m-d");
    foreach ($rows as $r) {
        $status = (string) $r["status"];
        if (
            !in_array($status, ["aberta", "em_andamento", "aguardando"], true)
        ) {
            $bucket = "done";
        } elseif ($view === "progress" || $status === "em_andamento") {
            $bucket = "progress";
        } elseif ((int) ($r["assigned_to"] ?? 0) === $uid) {
            $bucket = "mine";
        } elseif (!empty($r["assigned_to"])) {
            $bucket = "progress";
        } elseif ((string) ($r["target_scope"] ?? "clinic") === "role") {
            $bucket = "role";
        } else {
            $bucket = "clinic";
        }
        if (isset($groups[$bucket])) {
            $groups[$bucket]["rows"][] = $r;
        }
    }
    $dueLabel = function (array $r) use ($now, $today): array {
        $status = (string) $r["status"];
        $due = (string) ($r["due_at"] ?? "");
        if (
            !in_array($status, ["aberta", "em_andamento", "aguardando"], true)
        ) {
            return [
                "Concluída em " . dt_br($r["completed_at"] ?: $r["created_at"]),
                "done",
                "task_alt",
            ];
        }
        if ($due === "") {
            return ["Sem prazo", "neutral", "event_busy"];
        }
        $ts = strtotime($due);
        if (!$ts) {
            return ["Prazo indefinido", "neutral", "event_busy"];
        }
        if ($ts < $now) {
            $days = max(0, (int) floor(($now - $ts) / 86400));
            return [
                $days > 0
                    ? "Atrasada há " . $days . " dia" . ($days > 1 ? "s" : "")
                    : "Atrasada hoje",
                "bad",
                "priority_high",
            ];
        }
        if (date("Y-m-d", $ts) === $today) {
            return ["Hoje às " . date("H\hi", $ts), "warn", "today"];
        }
        if (date("Y-m-d", $ts) === date("Y-m-d", strtotime("+1 day"))) {
            return ["Amanhã às " . date("H\hi", $ts), "soon", "event_upcoming"];
        }
        return ["Prazo: " . dt_br($due), "neutral", "event"];
    };
    $priorityLabel = function (array $r) use ($now, $today): array {
        if (
            !in_array(
                (string) $r["status"],
                ["aberta", "em_andamento", "aguardando"],
                true,
            )
        ) {
            return ["Concluída", "done"];
        }
        $due = (string) ($r["due_at"] ?? "");
        if (
            $due !== "" &&
            strtotime($due) !== false &&
            strtotime($due) < $now
        ) {
            return ["Urgente", "bad"];
        }
        if ($due !== "" && substr($due, 0, 10) === $today) {
            return ["Alta prioridade", "warn"];
        }
        return ["Prioridade normal", "neutral"];
    };
    $renderTask = function (array $r) use (
        $cid,
        $role,
        $uid,
        $dueLabel,
        $priorityLabel,
        $originLabels,
        $commentsByTask,
        $canStartTask,
        $destinationLabel,
    ): string {
        [$dueTxt, $dueClass, $dueIcon] = $dueLabel($r);
        [$prioTxt, $prioClass] = $priorityLabel($r);
        $patient = trim((string) ($r["patient_name"] ?? ""));
        $context = $patient !== "" ? $patient : "Rotina interna";
        $dest = $destinationLabel($r);
        $status = (string) $r["status"];
        $active = in_array(
            $status,
            ["aberta", "em_andamento", "aguardando"],
            true,
        );
        $canStart = $active && $canStartTask($r);
        $canDone =
            $active &&
            !empty($r["assigned_to"]) &&
            ((int) $r["assigned_to"] === $uid || $role === "gerente");
        $actions = "";
        if ($canStart) {
            $actions .=
                '<form method="post" class="inline task-start-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="start"><input type="hidden" name="id" value="' .
                (int) $r["id"] .
                '"><button type="submit" class="small primary">' .
                icon("play_arrow") .
                "<span>Começar</span></button></form>";
        }
        if ($canDone) {
            $actions .=
                '<form method="post" class="inline task-done-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="done"><input type="hidden" name="id" value="' .
                (int) $r["id"] .
                '"><button type="submit" class="small primary">' .
                icon("check") .
                "<span>Concluir</span></button></form>";
        }
        if (
            $role === "gerente" &&
            $active &&
            !empty($r["assigned_to"]) &&
            (string) ($r["target_scope"] ?? "clinic") !== "user"
        ) {
            $actions .=
                '<form method="post" class="inline task-release-form" onsubmit="return confirm(\'Devolver esta tarefa para a fila coletiva?\')">' .
                csrf_field() .
                '<input type="hidden" name="act" value="release"><input type="hidden" name="id" value="' .
                (int) $r["id"] .
                '"><button type="submit" class="small ghost">' .
                icon("keyboard_return") .
                "<span>Devolver</span></button></form>";
        }
        if (!empty($r["patient_link_id"])) {
            $actions .=
                '<a class="ghost small" href="' .
                href("patient", ["id" => (int) $r["patient_link_id"]]) .
                '">' .
                icon("folder_open") .
                "<span>Abrir paciente</span></a>";
        }
        $origin =
            $originLabels[(string) ($r["source_event"] ?? "")] ??
            (!empty($r["source_event"])
                ? "Gerada pelo fluxo operacional."
                : "Criada manualmente.");
        $desc = trim((string) ($r["description"] ?? ""));
        $scope = (string) ($r["target_scope"] ?? "clinic");
        if ($scope === "") {
            $scope = "clinic";
        }
        $class =
            "task-card status-" .
            preg_replace("/[^a-z0-9_-]+/i", "-", $status) .
            " due-" .
            $dueClass .
            " priority-" .
            $prioClass .
            " target-" .
            $scope .
            ($canStart ? " can-start" : "");
        $html = '<article class="' . $class . '">';
        $html .= '<div class="task-main">';
        $html .=
            '<div class="task-marker">' .
            icon($canStart ? "play_circle" : $dueIcon) .
            "</div>";
        $html .=
            '<div class="task-copy"><h3>' .
            e((string) $r["title"]) .
            "</h3><p>" .
            e($context) .
            '</p><div class="task-meta"><span class="task-chip ' .
            $dueClass .
            '">' .
            icon($dueIcon) .
            "<span>" .
            e($dueTxt) .
            '</span></span><span class="task-chip ' .
            $prioClass .
            '">' .
            icon("flag") .
            "<span>" .
            e($prioTxt) .
            '</span></span><span class="task-chip responsibility">' .
            icon($canStart ? "groups" : "person") .
            "<span>" .
            e($dest) .
            "</span></span></div></div>";
        $html .= '<div class="task-actions">' . $actions . "</div></div>";
        $html .=
            '<details class="task-details"><summary>' .
            icon("expand_more") .
            '<span>Ver detalhes</span></summary><div class="task-detail-grid">' .
            "<div><b>Descrição</b><p>" .
            e($desc !== "" ? $desc : "Sem descrição registrada.") .
            "</p></div>" .
            "<div><b>Destino</b><p>" .
            e($dest) .
            "</p></div>" .
            "<div><b>Origem</b><p>" .
            e($origin) .
            "</p></div>" .
            "<div><b>Criada em</b><p>" .
            e(dt_br($r["created_at"] ?? null)) .
            "</p></div>" .
            "<div><b>Status</b><p>" .
            e($status === "em_andamento" ? "em andamento" : $status) .
            "</p></div>";
        if (!empty($r["started_at"])) {
            $html .=
                "<div><b>Iniciada em</b><p>" .
                e(dt_br($r["started_at"])) .
                "</p></div>";
        }
        if (!empty($r["completed_at"])) {
            $html .=
                "<div><b>Concluída em</b><p>" .
                e(dt_br($r["completed_at"])) .
                "</p></div>";
        }
        if (!empty($r["appointment_id"])) {
            $html .=
                "<div><b>Consulta vinculada</b><p>#" .
                (int) $r["appointment_id"] .
                "</p></div>";
        }
        $html .= '</div><div class="task-comments"><b>Comentários internos</b>';
        $comments = $commentsByTask[(int) $r["id"]] ?? [];
        if ($comments) {
            $html .= '<div class="task-comment-list">';
            foreach ($comments as $cm) {
                $commentId = (int) ($cm["id"] ?? 0);
                $commentUser = (int) ($cm["user_id"] ?? 0);
                $canManageComment =
                    $commentId > 0 &&
                    ($role === "gerente" || $commentUser === $uid);
                $edited = !empty($cm["updated_at"]);
                $meta =
                    e(first_name($cm["user_name"] ?? "Equipe")) .
                    " · " .
                    e(dt_br($cm["created_at"] ?? null)) .
                    ($edited ? " · editado" : "");
                $html .=
                    '<article class="task-comment-item"><div><span>' .
                    $meta .
                    "</span><p>" .
                    e((string) $cm["body"]) .
                    "</p></div>";
                if ($canManageComment) {
                    $html .=
                        '<div class="task-comment-actions"><details><summary>Editar</summary><form method="post" class="task-comment-form">' .
                        csrf_field() .
                        '<input type="hidden" name="act" value="comment_edit"><input type="hidden" name="comment_id" value="' .
                        $commentId .
                        '"><input name="comment" type="text" value="' .
                        e((string) $cm["body"]) .
                        '" required><button class="ghost small" type="submit">' .
                        icon("save") .
                        '<span>Salvar</span></button></form></details><form method="post" onsubmit="return confirm(&quot;Excluir este comentário interno?&quot;)">' .
                        csrf_field() .
                        '<input type="hidden" name="act" value="comment_delete"><input type="hidden" name="comment_id" value="' .
                        $commentId .
                        '"><button class="ghost small" type="submit">' .
                        icon("delete") .
                        "<span>Excluir</span></button></form></div>";
                }
                $html .= "</article>";
            }
            $html .= "</div>";
        } else {
            $html .= '<p class="muted">Nenhum comentário registrado.</p>';
        }
        if ($active) {
            $html .=
                '<form method="post" class="task-comment-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="comment"><input type="hidden" name="id" value="' .
                (int) $r["id"] .
                '"><input name="comment" type="text" placeholder="Registrar observação interna" required><button class="ghost small" type="submit">' .
                icon("add_comment") .
                "<span>Comentar</span></button></form>";
        }
        $html .= "</div></details></article>";
        return $html;
    };
    $board = '<section class="task-board">';
    $shown = 0;
    foreach ($groups as $key => $g) {
        if (!$g["rows"]) {
            continue;
        }
        $shown += count($g["rows"]);
        $board .=
            '<section class="task-group task-group-' .
            $key .
            '"><header><div><h2>' .
            e($g["title"]) .
            "</h2><p>" .
            e($g["hint"]) .
            "</p></div><span>" .
            count($g["rows"]) .
            '</span></header><div class="task-list">';
        foreach ($g["rows"] as $r) {
            $board .= $renderTask($r);
        }
        $board .= "</div></section>";
    }
    if ($shown === 0) {
        $msg =
            $qTerm !== ""
                ? "Nenhuma tarefa encontrada para a busca informada."
                : "Nenhuma tarefa neste filtro.";
        $board .=
            '<div class="empty task-empty">' .
            e($msg) .
            "<small></small></div>";
    }
    $board .= "</section>";
    $summary =
        '<section class="task-overview task-overview-wide kpis kpi-info-strip" aria-label="Resumo de tarefas"><div class="kpi-card">' .
        icon("person") .
        "<p><b>" .
        (int) $counts["mine"] .
        '</b><span>Minhas</span></p></div><div class="kpi-card">' .
        icon("badge") .
        "<p><b>" .
        (int) $counts["role"] .
        '</b><span>Do meu Cargo</span></p></div><div class="kpi-card">' .
        icon("home_health") .
        "<p><b>" .
        (int) $counts["clinic"] .
        '</b><span>Da Clínica</span></p></div><div class="kpi-card ' .
        ($counts["overdue"] > 0 ? "warn" : "") .
        '">' .
        icon("warning") .
        "<p><b>" .
        (int) $counts["overdue"] .
        "</b><span>Atrasadas</span></p></div></section>";
    page(
        "Tarefas",
        page_head("Tarefas", "Pendências da rotina.", $form) .
            $summary .
            card($search, "task-search-card ds-search-card") .
            card($filters . $board, "task-center ds-filter-list-block"),
    );
}
function readonly_support_alerts_ensure_schema(): void
{
    static $validated = false;
    if ($validated || !has_cfg()) {
        return;
    }
    if (!db_table_exists("pi_admin_alerts")) {
        throw new RuntimeException(
            "Schema incompleto: mensagens de suporte indisponíveis.",
        );
    }
    foreach (["sender_clinic_id", "source_scope"] as $column) {
        if (!db_column_exists("pi_admin_alerts", $column)) {
            throw new RuntimeException(
                "Schema incompleto: pi_admin_alerts.{$column} ausente.",
            );
        }
    }
    $validated = true;
}

function readonly_support_notice_screen(array $c, string $view = "sent"): string
{
    readonly_support_alerts_ensure_schema();
    $cid = (int) ($c["clinic_id"] ?? 0);
    $uid = (int) ($c["user"]["id"] ?? 0);
    $rows = [];
    try {
        $rows = q(
            "SELECT id,title,body,severity,read_at,created_at FROM pi_admin_alerts WHERE sender_user_id=? AND sender_clinic_id=? AND source_scope='clinic_readonly' ORDER BY id DESC LIMIT 80",
            [$uid, $cid],
        )->fetchAll();
    } catch (Throwable $e) {
        error_log("[Prontoo readonly support alerts list] " . $e->getMessage());
    }
    $form =
        '<details class="form-panel notice-compose-panel readonly-support-compose" open><summary class="primary small cmdlike">' .
        action_summary_label("Mensagem para o suporte", "support_agent") .
        '</summary><form method="post" class="compact notice-form notice-form-refined" data-notice-form>' .
        csrf_field() .
        '<input type="hidden" name="act" value="support_message"><div class="notice-form-grid">' .
        form_row(
            "Assunto",
            input(
                "title",
                "text",
                "",
                'required placeholder="Ex.: Regularização da assinatura"',
            ),
        ) .
        '<label class="field notice-message-field"><span>Mensagem</span>' .
        textarea(
            "body",
            "",
            'required rows="5" placeholder="Explique sua dúvida sobre pagamento, liberação ou acesso aos seus dados."',
        ) .
        '</label></div><p class="field-help readonly-support-help">Neste modo, Avisos fica restrito ao contato com o Desenvolvedor. Mensagens internas para equipe ficam bloqueadas até a regularização.</p><div class="form-actions notice-form-actions"><button type="submit" class="primary">' .
        icon("support_agent") .
        "<span>Enviar ao suporte</span></button></div></form></details>";
    $cards = "";
    foreach ($rows as $r) {
        $cards .=
            '<article class="notice-card notice-gmail-row ds-notice-row notice-minimal-row admin-alert-row readonly-support-row"><span class="notice-minimal-icon" aria-hidden="true">' .
            icon(empty($r["read_at"]) ? "outgoing_mail" : "done_all") .
            '</span><span class="notice-minimal-sender">Suporte</span><span class="notice-minimal-subject">' .
            e($r["title"]) .
            '</span><time class="notice-minimal-time">' .
            e(dt_notice_br($r["created_at"])) .
            "</time></article>";
    }
    if ($cards === "") {
        $cards =
            '<div class="notice-empty">' .
            icon("support_agent") .
            "<strong>Nenhuma mensagem enviada ao suporte.</strong><span>Use o formulário acima para falar com o Desenvolvedor.</span></div>";
    }
    $stats = "";
    $list = card(
        '<div class="notice-section-head ds-section-head"><h2>Mensagens para suporte</h2><span>' .
            count($rows) .
            '</span></div><div class="notice-list ds-notice-list admin-alert-list">' .
            $cards .
            "</div>",
        "notice-card-shell ds-notice-shell admin-alert-shell",
    );
    return $stats . $form . $list;
}
function page_notices(): void
{
    $c = require_can("notices");
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    $readOnly = !empty(($c["billing"] ?? [])["read_only"]);
    $view = preg_replace(
        "/[^a-z_]/",
        "",
        (string) ($_GET["view"] ??
            ($_POST["view"] ?? ($readOnly ? "sent" : "received"))),
    );
    if (!in_array($view, ["received", "sent", "archived"], true)) {
        $view = $readOnly ? "sent" : "received";
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = $_POST["act"] ?? "create";
        if ($readOnly && $act === "support_message") {
            readonly_support_alerts_ensure_schema();
            $title = trim((string) ($_POST["title"] ?? ""));
            $body = trim((string) ($_POST["body"] ?? ""));
            if ($title === "" || $body === "") {
                flash("Informe assunto e mensagem para o suporte.", "bad");
                redirect("notices");
            }
            q(
                "INSERT INTO pi_admin_alerts (sender_user_id,sender_clinic_id,source_scope,recipient_user_id,title,body,severity,created_at) VALUES (?,?,?,?,?,?,?,NOW())",
                [
                    $uid,
                    $cid,
                    "clinic_readonly",
                    null,
                    mb_substr($title, 0, 180),
                    $body,
                    "warning",
                ],
            );
            $id = db_last_insert_id();
            audit("aviso_suporte_assinatura", "aviso_admin", $id, [
                "clinic_id" => $cid,
                "audit_body" =>
                    "Usuário em modo somente leitura enviou mensagem ao Desenvolvedor pelo contexto Avisos.",
            ]);
            flash("Mensagem enviada ao suporte.");
            redirect("notices", ["view" => "sent"]);
        }
        if ($readOnly && !in_array($act, ["ack", "hide", "unhide"], true)) {
            flash(
                "No modo somente leitura, Avisos fica restrito ao contato com o suporte.",
                "bad",
            );
            redirect("notices");
        }
        if ($act === "ack" || $act === "hide" || $act === "unhide") {
            [$targetSql, $targetParams] = notice_target_sql($c, "n");
            $accessSql = "(" . $targetSql . " OR n.created_by=?)";
            $notice = one(
                "SELECT n.id,n.created_by,n.requires_ack FROM pi_notices n WHERE n.id=? AND n.clinic_id=? AND $accessSql",
                array_merge([(int) ($_POST["id"] ?? 0), $cid], $targetParams, [
                    $uid,
                ]),
            );
            if (!$notice) {
                flash("Aviso não encontrado para este colaborador.", "bad");
                redirect("notices", ["view" => $view]);
            }
            if ($act === "ack") {
                q(
                    "INSERT INTO pi_notice_reads (notice_id,user_id,read_at,ack_at) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=COALESCE(read_at,NOW()), ack_at=COALESCE(ack_at,NOW())",
                    [(int) $notice["id"], $uid],
                );
                clinic_metric_inc($cid, "notice_reads");
                audit("leitura_confirmada", "comunicado", (int) $notice["id"]);
                flash("Leitura registrada.");
                redirect("notices", [
                    "view" => $view,
                    "notice" => (int) $notice["id"],
                ]);
            }
            if ($act === "unhide") {
                q(
                    "UPDATE pi_notice_reads SET hidden_at=NULL WHERE notice_id=? AND user_id=?",
                    [(int) $notice["id"], $uid],
                );
                flash("Aviso restaurado.");
                redirect("notices", [
                    "view" => "received",
                    "notice" => (int) $notice["id"],
                ]);
            }
            q(
                "INSERT INTO pi_notice_reads (notice_id,user_id,hidden_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE hidden_at=NOW()",
                [(int) $notice["id"], $uid],
            );
            flash("Aviso arquivado.");
            redirect("notices", ["view" => "archived"]);
        }
        $title = trim((string) ($_POST["title"] ?? ""));
        $body = trim((string) ($_POST["body"] ?? ""));
        $scope = (string) ($_POST["target_scope"] ?? "all");
        if (!in_array($scope, ["all", "role", "user"], true)) {
            $scope = "all";
        }
        $targetRole = null;
        $targetUser = null;
        if ($scope === "role") {
            $targetRole = (string) $c["role"];
        }
        if ($scope === "user") {
            $targetUser = (int) ($_POST["target_user_id"] ?? 0);
            $team = team_options($cid);
            if ($targetUser < 1 || !isset($team[$targetUser])) {
                flash(
                    "Escolha um colaborador válido para receber o aviso.",
                    "bad",
                );
                redirect("notices", ["view" => $view]);
            }
        }
        if ($title === "" || $body === "") {
            flash("Informe título e mensagem do aviso.", "bad");
            redirect("notices", ["view" => $view]);
        }
        q(
            "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                $title,
                $body,
                isset($_POST["requires_ack"]) ? 1 : 0,
                $scope,
                $targetRole,
                $targetUser,
                $uid,
            ],
        );
        $noticeId = db_last_insert_id();
        q(
            "INSERT INTO pi_notice_reads (notice_id,user_id,read_at,ack_at) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=NOW(), ack_at=NOW(), hidden_at=NULL",
            [$noticeId, $uid],
        );
        counter_inc("notices_total");
        clinic_metric_inc($cid, "notices");
        audit(
            "comunicado_criado",
            "comunicado",
            $noticeId,
            array_merge($_POST, [
                "target_scope" => $scope,
                "target_role" => $targetRole,
                "target_user_id" => $targetUser,
            ]),
        );
        flash("Aviso publicado.");
        redirect("notices", ["view" => "sent", "notice" => $noticeId]);
    }
    $openId = (int) ($_GET["notice"] ?? 0);
    $team = team_options($cid);
    $userOpts = '<option value="">Selecione</option>';
    foreach ($team as $id => $name) {
        $userOpts .=
            '<option value="' . (int) $id . '">' . e($name) . "</option>";
    }
    $noticeCreateForm =
        '<form method="post" class="compact notice-form notice-form-refined ds-entity-form ds-standalone-form" data-notice-form>' .
        csrf_field() .
        '<input type="hidden" name="view" value="' .
        e($view) .
        '"><div class="notice-form-grid">' .
        form_row(
            "Título",
            input(
                "title",
                "text",
                "",
                'required placeholder="Assunto do aviso"',
            ),
        ) .
        '<label class="field notice-target-scope"><span>Destinatários</span><select name="target_scope" data-notice-target-scope><option value="all">Toda a clínica</option><option value="role">Colaboradores do meu cargo</option><option value="user">Colaborador específico</option></select></label><label class="field notice-message-field"><span>Mensagem</span>' .
        textarea(
            "body",
            "",
            'required rows="5" placeholder="Escreva a orientação que precisa ser lida pela equipe."',
        ) .
        '</label><label class="field notice-target-user" data-notice-target-user><span>Colaborador específico</span><select name="target_user_id" data-notice-target-user-select>' .
        $userOpts .
        '</select></label><label class="check notice-ack-field"><input type="checkbox" name="requires_ack" checked> Exigir confirmação de leitura</label></div><div class="form-actions notice-form-actions"><a class="ghost" href="' .
        href("notices", ["view" => $view]) .
        '">' .
        icon("close") .
        '<span>Cancelar</span></a><button type="submit" class="primary">' .
        icon("campaign") .
        "<span>Publicar</span></button></div></form>";
    if (!$readOnly && ($_GET["new"] ?? "") === "1") {
        $back =
            '<a class="ghost small" href="' .
            href("notices", ["view" => $view]) .
            '">' .
            icon("arrow_back") .
            "<span>Voltar</span></a>";
        page(
            "Novo aviso",
            page_head(
                "Novo aviso",
                "Publique uma orientação em tela própria, mantendo a lista de Avisos limpa para leitura e acompanhamento.",
                $back,
            ) .
                card(
                    $noticeCreateForm,
                    "notice-card-shell notice-create-shell ds-form-shell",
                ),
        );
        return;
    }
    $form = $readOnly
        ? ""
        : '<a class="primary small cmdlike" href="' .
            href("notices", ["view" => $view, "new" => 1]) .
            '">' .
            action_summary_label("Novo aviso", "notifications") .
            "</a>";
    [$targetSql, $targetParams] = notice_target_sql($c, "n");
    $accessSql = "(" . $targetSql . " OR n.created_by=?)";
    $rows = q(
        "SELECT n.id,n.title,n.body,n.requires_ack,n.target_scope,n.target_role,n.target_user_id,n.created_by,n.created_at FROM pi_notices n WHERE n.clinic_id=? AND $accessSql ORDER BY n.id DESC LIMIT 160",
        array_merge([$cid], $targetParams, [$uid]),
    )->fetchAll();
    $reads = [];
    if ($rows) {
        $ids = int_ids($rows, "id");
        $ph = implode(",", array_fill(0, count($ids), "?"));
        $params = array_merge([$uid], $ids);
        foreach (
            q(
                "SELECT notice_id,read_at,ack_at,hidden_at FROM pi_notice_reads WHERE user_id=? AND notice_id IN ($ph)",
                $params,
            )->fetchAll()
            as $r
        ) {
            $reads[(int) $r["notice_id"]] = $r;
        }
    }
    $authors = fetch_map("pi_users", int_ids($rows, "created_by"), "id,name");
    $targetUsers = scoped_user_map(
        $cid,
        int_ids($rows, "target_user_id"),
        "id,name",
    );
    $receivedCount = 0;
    $sentCount = 0;
    $hiddenCount = 0;
    $pendingCount = 0;
    foreach ($rows as $r) {
        $read = $reads[(int) $r["id"]] ?? [];
        $isMine = (int) ($r["created_by"] ?? 0) === $uid;
        if (!empty($read["hidden_at"])) {
            $hiddenCount++;
            continue;
        }
        if ($isMine) {
            $sentCount++;
        } else {
            $receivedCount++;
            if ((int) $r["requires_ack"] === 1 && empty($read["ack_at"])) {
                $pendingCount++;
            }
        }
    }
    $filters =
        '<nav class="notice-filter-chips lead-filter-chips notice-gmail-filters ds-notice-filters" aria-label="Filtros de avisos">' .
        '<a class="lead-chip ds-filter-chip ' .
        ($view === "received" ? "active" : "") .
        '" href="' .
        e(href("notices", ["view" => "received"])) .
        '"><span class="material-symbols-rounded">inbox</span><span class="lead-chip-label">Recebidas</span><em>' .
        (int) $receivedCount .
        "</em></a>" .
        '<a class="lead-chip ds-filter-chip ' .
        ($view === "sent" ? "active" : "") .
        '" href="' .
        e(href("notices", ["view" => "sent"])) .
        '"><span class="material-symbols-rounded">send</span><span class="lead-chip-label">Enviadas</span><em>' .
        (int) $sentCount .
        "</em></a>" .
        '<a class="lead-chip ds-filter-chip ' .
        ($view === "archived" ? "active" : "") .
        '" href="' .
        e(href("notices", ["view" => "archived"])) .
        '"><span class="material-symbols-rounded">archive</span><span class="lead-chip-label">Arquivadas</span><em>' .
        (int) $hiddenCount .
        "</em></a>" .
        "</nav>";
    $detail = "";
    if ($openId > 0) {
        $openRow = null;
        foreach ($rows as $r) {
            if ((int) $r["id"] === $openId) {
                $openRow = $r;
                break;
            }
        }
        if ($openRow) {
            $isMine = (int) ($openRow["created_by"] ?? 0) === $uid;
            $read = $reads[$openId] ?? [];
            if (!$isMine && empty($read["ack_at"])) {
                q(
                    "INSERT INTO pi_notice_reads (notice_id,user_id,read_at,ack_at) VALUES (?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=COALESCE(read_at,NOW()), ack_at=COALESCE(ack_at,NOW())",
                    [$openId, $uid],
                );
                clinic_metric_inc($cid, "notice_reads");
                audit("leitura_registrada", "comunicado", $openId);
                $read["read_at"] = $read["read_at"] ?? date("Y-m-d H:i:s");
                $read["ack_at"] = $read["ack_at"] ?? date("Y-m-d H:i:s");
                $reads[$openId] = $read;
            }
            $author = $authors[(int) ($openRow["created_by"] ?? 0)] ?? [];
            $authorFirst = first_name((string) ($author["name"] ?? "Sistema"));
            $archived = !empty($read["hidden_at"]);
            $detailView = $archived
                ? "archived"
                : ($isMine
                    ? "sent"
                    : "received");
            $status = $archived
                ? "Arquivada"
                : ($isMine
                    ? "Enviada"
                    : "Recebida · leitura registrada");
            $iconName = $archived
                ? "archive"
                : ($isMine
                    ? "outgoing_mail"
                    : "mark_email_read");
            $direction = $isMine
                ? '<span class="notice-direction-pill is-sent">' .
                    icon("north_east") .
                    " Enviada</span>" .
                    notice_recipient_pills($cid, $openRow, $team)
                : '<span class="notice-direction-pill is-received">' .
                    icon("south_west") .
                    " " .
                    e($authorFirst) .
                    "</span>" .
                    notice_recipient_pills($cid, $openRow, $team);
            $action = $archived
                ? '<form method="post" class="inline">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="unhide"><input type="hidden" name="view" value="archived"><input type="hidden" name="id" value="' .
                    $openId .
                    '"><button type="submit" class="small ghost">Restaurar</button></form>'
                : '<form method="post" class="inline">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="hide"><input type="hidden" name="view" value="' .
                    $detailView .
                    '"><input type="hidden" name="id" value="' .
                    $openId .
                    '"><button type="submit" class="small ghost">Arquivar</button></form>';
            $detail = card(
                '<article class="notice-reader notice-reader-' .
                    $detailView .
                    '"><header><a class="ghost small" href="' .
                    e(href("notices", ["view" => $detailView])) .
                    '">' .
                    icon("arrow_back") .
                    '<span>Voltar</span></a><span class="notice-reader-state">' .
                    icon($iconName) .
                    " " .
                    e($status) .
                    "</span><time>" .
                    e(dt_notice_br($openRow["created_at"])) .
                    "</time></header><h2>" .
                    e($openRow["title"]) .
                    '</h2><div class="notice-reader-body">' .
                    nl2br(e($openRow["body"])) .
                    "</div><footer>" .
                    $direction .
                    '<div class="notice-actions">' .
                    $action .
                    "</div></footer></article>",
                "notice-card-shell notice-reader-shell",
            );
        } else {
            $detail = card(
                '<div class="notice-empty">' .
                    icon("campaign") .
                    "<strong>Aviso não encontrado.</strong><span>Ela pode ter sido removida ou não estar disponível para sua credencial.</span></div>",
                "notice-card-shell",
            );
        }
    }
    $cards = "";
    foreach ($rows as $r) {
        $read = $reads[(int) $r["id"]] ?? [];
        $isMine = (int) ($r["created_by"] ?? 0) === $uid;
        $archived = !empty($read["hidden_at"]);
        if ($view === "received" && ($isMine || $archived)) {
            continue;
        }
        if ($view === "sent" && (!$isMine || $archived)) {
            continue;
        }
        if ($view === "archived" && !$archived) {
            continue;
        }
        $author = $authors[(int) ($r["created_by"] ?? 0)] ?? [];
        $authorFirst = first_name((string) ($author["name"] ?? "Sistema"));
        $needsAck =
            !$isMine &&
            (int) $r["requires_ack"] === 1 &&
            empty($read["ack_at"]);
        $stateClass = $archived
            ? "archived"
            : ($isMine
                ? "sent"
                : ($needsAck
                    ? "received-unread"
                    : "received-read"));
        $rowIcon = $archived
            ? "archive"
            : ($isMine
                ? "send"
                : ($needsAck
                    ? "mail"
                    : "drafts"));
        $senderName = $isMine
            ? notice_recipient_phrase($r, $targetUsers)
            : $authorFirst;
        $openUrl = e(
            href("notices", ["view" => $view, "notice" => (int) $r["id"]]),
        );
        $cards .=
            '<a class="notice-card notice-gmail-row ds-notice-row notice-minimal-row notice-card-' .
            $stateClass .
            " " .
            ($needsAck ? "needs-ack" : "is-read") .
            " " .
            ($archived
                ? "is-archived"
                : ($isMine
                    ? "is-sent"
                    : "is-received")) .
            '" href="' .
            $openUrl .
            '">' .
            '<span class="notice-minimal-icon" aria-hidden="true">' .
            icon($rowIcon) .
            "</span>" .
            '<span class="notice-minimal-sender">' .
            e($senderName) .
            "</span>" .
            '<span class="notice-minimal-subject">' .
            e($r["title"]) .
            "</span>" .
            '<time class="notice-minimal-time">' .
            e(dt_notice_br($r["created_at"])) .
            "</time>" .
            "</a>";
    }
    $globalCards = "";
    $globalCount = 0;
    if ($view === "received") {
        $globalMeta = [
            "critical" => ["Crítico", "priority_high"],
            "warning" => ["Atenção", "warning"],
            "info" => ["Informativo", "info"],
        ];
        foreach (["critical", "warning", "info"] as $sev) {
            $globals = q(
                "SELECT id,title,body,severity,created_at FROM pi_global_notices WHERE active=1 AND severity=? AND (starts_at IS NULL OR starts_at<=NOW()) AND (expires_at IS NULL OR expires_at>=NOW()) ORDER BY id DESC LIMIT 8",
                [$sev],
            )->fetchAll();
            foreach ($globals as $g) {
                $globalCount++;
                $gm = $globalMeta[$sev] ?? $globalMeta["info"];
                $globalCards .=
                    '<article class="notice-card global ds-notice-row notice-minimal-row severity-' .
                    e($sev) .
                    '"><span class="notice-minimal-icon" aria-hidden="true">' .
                    icon($gm[1]) .
                    '</span><span class="notice-minimal-sender">Prontoo</span><span class="notice-minimal-subject">' .
                    e($g["title"]) .
                    '</span><time class="notice-minimal-time">' .
                    e(dt_notice_br($g["created_at"])) .
                    "</time></article>";
            }
        }
    }
    if ($readOnly) {
        page(
            "Avisos",
            page_head("Avisos") .
                '<section class="notice-screen admin-alert-screen readonly-support-screen">' .
                readonly_support_notice_screen($c, $view) .
                "</section>",
        );
        return;
    }
    $titleMap = [
        "received" => "Recebidas",
        "sent" => "Enviadas",
        "archived" => "Arquivadas",
    ];
    $countMap = [
        "received" => $receivedCount,
        "sent" => $sentCount,
        "archived" => $hiddenCount,
    ];
    $iconMap = [
        "received" => "inbox",
        "sent" => "send",
        "archived" => "archive",
    ];
    $sectionTitle = $titleMap[$view] ?? "Recebidas";
    $sectionCount = (int) ($countMap[$view] ?? 0);
    if ($cards === "") {
        $emptyMsg =
            $view === "received"
                ? "Nenhum aviso recebido."
                : ($view === "sent"
                    ? "Nenhum aviso enviado."
                    : "Nenhum aviso arquivado.");
        $cards =
            '<div class="notice-empty">' .
            icon($iconMap[$view] ?? "campaign") .
            "<strong>" .
            e($emptyMsg) .
            "</strong></div>";
    }
    $list = card(
        '<div class="notice-section-head ds-section-head notice-section-' .
            $view .
            '"><h2>' .
            e($sectionTitle) .
            "</h2><span>" .
            $sectionCount .
            '</span></div><div class="notice-list ds-notice-list">' .
            $cards .
            "</div>",
        "notice-card-shell ds-notice-shell notice-card-shell-" . $view,
    );
    if ($globalCards !== "") {
        $list .= card(
            '<div class="notice-section-head ds-section-head"><h2>Avisos do Prontoo</h2><span>' .
                (int) $globalCount .
                '</span></div><div class="notice-list ds-notice-list">' .
                $globalCards .
                "</div>",
            "notice-card-shell ds-notice-shell",
        );
    }
    $stats =
        '<div class="notice-kpis notice-gmail-kpis ds-notice-kpis"><div class="notice-kpi ds-kpi ' .
        ($pendingCount ? "notice-kpi-pending" : "") .
        '">' .
        icon("inbox") .
        "<div><b>" .
        (int) $receivedCount .
        '</b><span>Recebidas</span></div></div><div class="notice-kpi ds-kpi notice-kpi-sent">' .
        icon("send") .
        "<div><b>" .
        (int) $sentCount .
        '</b><span>Enviadas</span></div></div><div class="notice-kpi ds-kpi notice-kpi-archived">' .
        icon("archive") .
        "<div><b>" .
        (int) $hiddenCount .
        "</b><span>Arquivadas</span></div></div></div>";
    page(
        "Avisos",
        page_head("Avisos", "", $form) .
            '<section class="notice-screen notice-screen-' .
            $view .
            '">' .
            $stats .
            '<section class="notice-filter-list-block ds-filter-list-block" aria-label="Filtros e lista de avisos">' .
            $filters .
            $detail .
            $list .
            "</section></section>",
    );
}
