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
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("tasks");
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        $role = (string) ($c["role"] ?? "");
        $activeStatuses = "'aberta','em_andamento','aguardando'";
        $roleOptions = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_options($cid, true);
        $users = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::team_options($cid);
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
                return "Responsável: " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($assigned);
            }
            $scope = (string) ($task["target_scope"] ?? "clinic");
            if ($scope === "" || $scope === "all") {
                $scope = "clinic";
            }
            if ($scope === "role") {
                return "Disponível para " .
                    \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for((string) ($task["target_role"] ?? ""), $cid);
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
                $task = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->row('operational.tasks_notices.04.page_tasks.01', [$tid, $cid], []);
                if (!$task) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Tarefa não encontrada.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                if (!$canStartTask($task)) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Esta tarefa não está disponível para ser iniciada por esta credencial.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                $wasAssigned = (int) ($task["assigned_to"] ?? 0);
                if ($wasAssigned > 0) {
                    $st = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.04.page_tasks.02', [$uid, $tid, $cid, $wasAssigned], []);
                } else {
                    $st = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.04.page_tasks.03', [$uid, $uid, $tid, $cid], []);
                }
                if ($st->rowCount() < 1) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Outra pessoa já começou esta tarefa.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::task_event(
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
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::workflow_on_task_started($task, $uid, $role);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("tarefa_iniciada", "tarefa", $tid, [
                    "titulo" => (string) $task["title"],
                    "audit_body" =>
                        $wasAssigned > 0
                            ? "A tarefa foi marcada como iniciada."
                            : "A tarefa foi assumida individualmente pelo usuário.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Tarefa iniciada. Ela agora aparece em Iniciadas.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks", ["view" => "progress"]);
            }
            if ($act === "release") {
                $tid = (int) ($_POST["id"] ?? 0);
                if ($role !== "gerente") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Apenas o Administrativo pode devolver tarefa para a fila.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                $task = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->row('operational.tasks_notices.04.page_tasks.04', [$tid, $cid], []);
                if (!$task) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Tarefa não encontrada.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                if ((string) ($task["target_scope"] ?? "clinic") === "user") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Tarefa destinada a pessoa específica deve ser reatribuída, não devolvida à fila.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.04.page_tasks.05', [$tid, $cid], []);
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::task_event(
                    $cid,
                    $tid,
                    "devolvida_fila",
                    $uid,
                    null,
                    "aberta",
                    "Tarefa devolvida para a fila coletiva.",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("tarefa_devolvida_fila", "tarefa", $tid, [
                    "titulo" => (string) $task["title"],
                    "audit_body" =>
                        "A tarefa foi devolvida para sua fila coletiva de origem.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Tarefa devolvida para a fila coletiva.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
            }
            if ($act === "done") {
                $tid = (int) ($_POST["id"] ?? 0);
                $task = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->row('operational.tasks_notices.04.page_tasks.06', [$tid, $cid], []);
                if (!$task) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Tarefa não encontrada.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                if (empty($task["assigned_to"])) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Comece a tarefa antes de concluí-la.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                if ((int) $task["assigned_to"] !== $uid && $role !== "gerente") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Esta tarefa está com outra pessoa. Apenas o responsável ou o Administrativo pode concluí-la.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.04.page_tasks.07', [$uid, $tid, $cid], []);
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::task_event(
                    $cid,
                    $tid,
                    "concluida",
                    $uid,
                    (string) $task["status"],
                    "concluida",
                    "Tarefa concluída.",
                );
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::workflow_on_task_completed($task, $uid, $role);
                \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "tasks_completed");
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("tarefa_concluida", "tarefa", $tid);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Tarefa concluída.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
            }
            if ($act === "comment_edit") {
                $commentId = (int) ($_POST["comment_id"] ?? 0);
                $body = mb_trim((string) ($_POST["comment"] ?? ""));
                if ($body === "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o novo texto do comentário.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                $cm = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->row('operational.tasks_notices.04.page_tasks.08', [$commentId, $cid], []);
                if (!$cm) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Comentário não encontrado.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                if ((int) $cm["user_id"] !== $uid && $role !== "gerente") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Apenas quem escreveu o comentário ou a Gestão pode editá-lo.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.04.page_tasks.09', [$body, $uid, $commentId, $cid], []);
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::task_event(
                    $cid,
                    (int) $cm["task_id"],
                    "comentario_editado",
                    $uid,
                    null,
                    null,
                    "Comentário interno editado.",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("comentario_tarefa_editado", "tarefa", (int) $cm["task_id"], [
                    "comentario_id" => $commentId,
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Comentário atualizado.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
            }
            if ($act === "comment_delete") {
                $commentId = (int) ($_POST["comment_id"] ?? 0);
                $cm = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->row('operational.tasks_notices.04.page_tasks.10', [$commentId, $cid], []);
                if (!$cm) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Comentário não encontrado.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                if ((int) $cm["user_id"] !== $uid && $role !== "gerente") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Apenas quem escreveu o comentário ou a Gestão pode excluí-lo.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.04.page_tasks.11', [$uid, $commentId, $cid], []);
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::task_event(
                    $cid,
                    (int) $cm["task_id"],
                    "comentario_excluido",
                    $uid,
                    null,
                    null,
                    "Comentário interno excluído.",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                    "comentario_tarefa_excluido",
                    "tarefa",
                    (int) $cm["task_id"],
                    ["comentario_id" => $commentId],
                );
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Comentário excluído.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
            }
            if ($act === "comment") {
                $tid = (int) ($_POST["id"] ?? 0);
                $body = mb_trim((string) ($_POST["comment"] ?? ""));
                if ($body === "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o comentário da tarefa.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                $task = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->row('operational.tasks_notices.04.page_tasks.12', [
                    $tid,
                    $cid,
                ], []);
                if (!$task) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Tarefa não encontrada.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.04.page_tasks.13', [$cid, $tid, $uid, $body], []);
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::task_event(
                    $cid,
                    $tid,
                    "comentario_criado",
                    $uid,
                    null,
                    null,
                    "Comentário interno registrado.",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("comentario_tarefa_criado", "tarefa", $tid, [
                    "comentario" => $body,
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Comentário registrado.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
            }
            $title = mb_trim((string) ($_POST["title"] ?? ""));
            if ($title === "") {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o título da tarefa.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
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
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Escolha um cargo válido da clínica.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
            } elseif ($targetScope === "user") {
                $targetUserId = (int) ($_POST["target_user_id"] ?? 0);
                if (
                    $targetUserId <= 0 ||
                    !\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_user_exists($cid, $targetUserId)
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Escolha uma pessoa vinculada à clínica atual.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
                }
                $assigned = $targetUserId;
            }
            $taskDescription = mb_trim((string) ($_POST["description"] ?? ""));
            $dueAt = mb_trim((string) ($_POST["due_at"] ?? ""));
            $dueAt = $dueAt !== "" ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_to_db_utc($dueAt, $cid, $c) : null;
            \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.04.page_tasks.14', [
                    $cid,
                    $title,
                    $targetScope,
                    $targetRole,
                    $targetUserId,
                    $assigned,
                    $dueAt,
                    $uid,
                ], []);
            $taskId = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->lastInsertId();
            \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.04.page_tasks.15', [$taskId, $cid, $taskDescription], []);
            \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::task_event(
                $cid,
                $taskId,
                "criada",
                $uid,
                null,
                "aberta",
                "Tarefa criada manualmente.",
            );
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("tasks_total");
            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "tasks");
            if ($targetScope === "user" && $targetUserId) {
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::notify_task_personal_assignment(
                    $cid,
                    $taskId,
                    $title,
                    $taskDescription,
                    (int) $targetUserId,
                    $uid,
                    "manual",
                );
            }
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("tarefa_criada", "tarefa", $taskId, [
                "titulo" => $title,
                "destino" => $targetScope,
                "cargo" => $targetRole,
                "pessoa" => $targetUserId,
            ]);
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Tarefa criada.");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("tasks");
        }
        $targetOptions = [
            "" => "Escolha o destinatário",
            "clinic" => "Toda a clínica",
            "role" => "Todos do mesmo cargo",
            "user" => "Pessoa específica",
        ];
        $roleField =
            '<div data-task-target-field="role" hidden>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Cargo específico",
                "target_role",
                ["" => "Selecionar cargo"] + $roleOptions,
                null,
            ) .
            "</div>";
        $userField =
            '<div data-task-target-field="user" hidden>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Pessoa específica",
                "target_user_id",
                ["" => "Selecionar pessoa"] + $users,
                null,
            ) .
            "</div>";
        $taskCreateFields =
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Título", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("title", "text", "", "required")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Descrição", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("description")) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Destinatário da tarefa",
                "target_scope",
                $targetOptions,
                null,
                "required data-task-target-scope",
            ) .
            $roleField .
            $userField .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Prazo", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("due_at", "datetime-local")) .
            '<small class="field-help">Escolha primeiro o destinatário. Tarefas para toda a clínica ou para um cargo ficam em fila coletiva até alguém clicar em Começar.</small>' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Salvar tarefa");
        if (($_GET["new"] ?? "") === "1") {
            $back =
                '<a class="ghost small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks") .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Voltar</span></a>";
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Nova tarefa",
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                    "Nova tarefa",
                    "Cadastre uma demanda da rotina em uma tela própria, mantendo a listagem apenas para consulta e acompanhamento.",
                    $back,
                ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                        '<form method="post" class="compact task-create-form ds-entity-form ds-standalone-form" data-task-create-form>' .
                            $taskCreateFields .
                            "</form>",
                        "task-create-shell ds-form-shell",
                    ),
            );
            return;
        }
        $form =
            '<a class="pagehead-control pagehead-control--primary" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", ["new" => 1]) .
            '">' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Nova tarefa", "add_task") .
            "</a>";
        $view = preg_replace("/[^a-z_]/", "", (string) ($_GET["view"] ?? "mine"));
        if ($view === "") {
            $view = "mine";
        }
        $qTerm = mb_trim((string) ($_GET["q"] ?? ""));
        $taskSearchMode = $qTerm !== "";
        $params = [$cid];
        if (!$taskSearchMode) {
            if ($view === "mine") {
                $params[] = $uid;
            } elseif ($view === "role") {
                $params[] = $role;
            } elseif ($view === "today") {
                [$taskTodayStart, $taskTodayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range(
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c),
                    $cid,
                    $c,
                );
                $params[] = $taskTodayStart;
                $params[] = $taskTodayEnd;
            } elseif ($view === "start") {
                $view = "role";
                $params[] = $role;
            } elseif (!in_array($view, ["clinic", "progress", "overdue", "done", "all"], true)) {
                $view = "mine";
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
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        [$taskOrderTodayStart, $taskOrderTodayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range(
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result(
            'operational.tasks_notices.04.page_tasks.16',
            array_merge($params, [$uid, $taskOrderTodayStart, $taskOrderTodayEnd]),
            ['view' => $view, 'search' => $taskSearchMode],
        )->fetchAll();
        $commentsByTask = [];
        $taskIds = \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "id");
        if ($taskIds) {
            $commentRows = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.04.page_tasks.17', array_merge([$cid], $taskIds), ['itemCount' => count($taskIds)])->fetchAll();
            foreach ($commentRows as $cr) {
                $commentsByTask[(int) $cr["task_id"]][] = $cr;
            }
        }
        $counts = \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::task_nav_counts($c);
        $activeClass = function (string $v) use ($view, $qTerm): string {
    
            return $qTerm === "" && $v === $view ? " active" : "";
        };
        $qs = function (string $v): array {
    
            return ["view" => $v];
        };
        $taskFoundChip =
            $qTerm !== ""
                ? '<span class="task-filter active is-active patient-filter-found" aria-current="page" data-ds-filter-chip>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("manage_search") .
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
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", $qs("mine")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("person_check") .
            "<span>Minhas</span>" .
            ($counts["mine"] > 0 ? "<b>" . (int) $counts["mine"] . "</b>" : "") .
            "</a>" .
            '<a class="task-filter' .
            $activeClass("role") .
            '" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", $qs("role")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("groups") .
            "<span>Do meu cargo</span>" .
            ($counts["role"] > 0 ? "<b>" . (int) $counts["role"] . "</b>" : "") .
            "</a>" .
            '<a class="task-filter' .
            $activeClass("clinic") .
            '" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", $qs("clinic")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("home_health") .
            "<span>Da Clínica</span>" .
            ($counts["clinic"] > 0
                ? "<b>" . (int) $counts["clinic"] . "</b>"
                : "") .
            "</a>" .
            '<a class="task-filter' .
            $activeClass("progress") .
            '" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", $qs("progress")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("pending") .
            "<span>Iniciadas</span>" .
            ($counts["progress"] > 0
                ? "<b>" . (int) $counts["progress"] . "</b>"
                : "") .
            "</a>" .
            '<a class="task-filter' .
            $activeClass("overdue") .
            '" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", $qs("overdue")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("priority_high") .
            "<span>Atrasadas</span>" .
            ($counts["overdue"] > 0
                ? "<b>" . (int) $counts["overdue"] . "</b>"
                : "") .
            "</a>" .
            '<a class="task-filter' .
            $activeClass("today") .
            '" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", $qs("today")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("today") .
            "<span>Hoje</span>" .
            ($counts["today"] > 0 ? "<b>" . (int) $counts["today"] . "</b>" : "") .
            "</a>" .
            '<a class="task-filter' .
            $activeClass("done") .
            '" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", $qs("done")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("task_alt") .
            "<span>Concluídas</span></a>" .
            '<a class="task-filter' .
            $activeClass("all") .
            '" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", $qs("all")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("view_list") .
            "<span>Todas</span></a>" .
            "</div>";
        $search =
            '<section class="task-directory-search ds-search-block"><form method="get" class="task-search patient-search-bar" role="search"><input type="hidden" name="r" value="tasks"><input type="hidden" name="view" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($view) .
            '"><label class="search-field"><input name="q" type="search" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($qTerm) .
            '" placeholder="Paciente, responsável, descrição ou data" autocomplete="off" aria-label="Buscar tarefa por paciente, responsável, descrição ou data"></label><button class="primary small icon-only" type="submit" aria-label="Buscar" title="Buscar">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("search") .
            '<span class="sr-only">Buscar</span></button>' .
            ($qTerm !== ""
                ? '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks", ["view" => $view]) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
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
                    "Concluída em " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["completed_at"] ?: $r["created_at"]),
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
            return ["Prazo: " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($due), "neutral", "event"];
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
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="start"><input type="hidden" name="id" value="' .
                    (int) $r["id"] .
                    '"><button type="submit" class="small primary">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("play_arrow") .
                    "<span>Começar</span></button></form>";
            }
            if ($canDone) {
                $actions .=
                    '<form method="post" class="inline task-done-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="done"><input type="hidden" name="id" value="' .
                    (int) $r["id"] .
                    '"><button type="submit" class="small primary">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check") .
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
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="release"><input type="hidden" name="id" value="' .
                    (int) $r["id"] .
                    '"><button type="submit" class="small ghost">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("keyboard_return") .
                    "<span>Devolver</span></button></form>";
            }
            if (!empty($r["patient_link_id"])) {
                $actions .=
                    '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => (int) $r["patient_link_id"]]) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("folder_open") .
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($canStart ? "play_circle" : $dueIcon) .
                "</div>";
            $html .=
                '<div class="task-copy"><h3>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $r["title"]) .
                "</h3><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($context) .
                '</p><div class="task-meta"><span class="task-chip ' .
                $dueClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($dueIcon) .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($dueTxt) .
                '</span></span><span class="task-chip ' .
                $prioClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("flag") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($prioTxt) .
                '</span></span><span class="task-chip responsibility">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($canStart ? "groups" : "person") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($dest) .
                "</span></span></div></div>";
            $html .= '<div class="task-actions">' . $actions . "</div></div>";
            $html .=
                '<details class="task-details"><summary>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("expand_more") .
                '<span>Ver detalhes</span></summary><div class="task-detail-grid">' .
                "<div><b>Descrição</b><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($desc !== "" ? $desc : "Sem descrição registrada.") .
                "</p></div>" .
                "<div><b>Destino</b><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($dest) .
                "</p></div>" .
                "<div><b>Origem</b><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($origin) .
                "</p></div>" .
                "<div><b>Criada em</b><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["created_at"] ?? null)) .
                "</p></div>" .
                "<div><b>Status</b><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($status === "em_andamento" ? "em andamento" : $status) .
                "</p></div>";
            if (!empty($r["started_at"])) {
                $html .=
                    "<div><b>Iniciada em</b><p>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["started_at"])) .
                    "</p></div>";
            }
            if (!empty($r["completed_at"])) {
                $html .=
                    "<div><b>Concluída em</b><p>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["completed_at"])) .
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
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($cm["user_name"] ?? "Equipe")) .
                        " · " .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($cm["created_at"] ?? null)) .
                        ($edited ? " · editado" : "");
                    $html .=
                        '<article class="task-comment-item"><div><span>' .
                        $meta .
                        "</span><p>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $cm["body"]) .
                        "</p></div>";
                    if ($canManageComment) {
                        $html .=
                            '<div class="task-comment-actions"><details><summary>Editar</summary><form method="post" class="task-comment-form">' .
                            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                            '<input type="hidden" name="act" value="comment_edit"><input type="hidden" name="comment_id" value="' .
                            $commentId .
                            '"><input name="comment" type="text" value="' .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $cm["body"]) .
                            '" required><button class="ghost small" type="submit">' .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
                            '<span>Salvar</span></button></form></details><form method="post" onsubmit="return confirm(&quot;Excluir este comentário interno?&quot;)">' .
                            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                            '<input type="hidden" name="act" value="comment_delete"><input type="hidden" name="comment_id" value="' .
                            $commentId .
                            '"><button class="ghost small" type="submit">' .
                            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
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
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="comment"><input type="hidden" name="id" value="' .
                    (int) $r["id"] .
                    '"><input name="comment" type="text" placeholder="Registrar observação interna" required><button class="ghost small" type="submit">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("add_comment") .
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($g["title"]) .
                "</h2><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($g["hint"]) .
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($msg) .
                "<small></small></div>";
        }
        $board .= "</section>";
        $summary =
            '<section class="task-overview task-overview-wide kpis kpi-info-strip" aria-label="Resumo de tarefas"><div class="kpi-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("person") .
            "<p><b>" .
            (int) $counts["mine"] .
            '</b><span>Minhas</span></p></div><div class="kpi-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
            "<p><b>" .
            (int) $counts["role"] .
            '</b><span>Do meu Cargo</span></p></div><div class="kpi-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("home_health") .
            "<p><b>" .
            (int) $counts["clinic"] .
            '</b><span>Da Clínica</span></p></div><div class="kpi-card ' .
            ($counts["overdue"] > 0 ? "warn" : "") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("warning") .
            "<p><b>" .
            (int) $counts["overdue"] .
            "</b><span>Atrasadas</span></p></div></section>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Tarefas",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Tarefas", "Pendências da rotina.", $form) .
                $summary .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($search, "task-search-card ds-search-card") .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($filters . $board, "task-center ds-filter-list-block"),
        );
    
    }
}
