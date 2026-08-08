<?php
declare(strict_types=1);

namespace Prontoo\Runtime\TasksNotices;

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

final class TasksNoticesRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function page_tasks(): void
    
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
    
            $assigned = mb_trim((string) ($task["assigned_name"] ?? ""));
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
                $body = mb_trim((string) ($_POST["comment"] ?? ""));
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
                $body = mb_trim((string) ($_POST["comment"] ?? ""));
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
            $title = mb_trim((string) ($_POST["title"] ?? ""));
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
            $taskDescription = mb_trim((string) ($_POST["description"] ?? ""));
            $dueAt = mb_trim((string) ($_POST["due_at"] ?? ""));
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
        $qTerm = mb_trim((string) ($_GET["q"] ?? ""));
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
            $patient = mb_trim((string) ($r["patient_name"] ?? ""));
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
            $desc = mb_trim((string) ($r["description"] ?? ""));
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
}
