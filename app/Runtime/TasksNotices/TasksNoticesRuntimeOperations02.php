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

final class TasksNoticesRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function workflow_on_appointment_finished(
        int $cid,
        int $appointmentId,
        int $patientLinkId,
        ?int $finishedBy = null,
        string $source = "atendimento",
    ): void 
    {
    
        try {
            if ($cid <= 0 || $appointmentId <= 0) {
                return;
            }
            $appt = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->row('operational.tasks_notices.02.workflow_on_appointment_finished.01', [$appointmentId, $cid], []);
            if (!$appt) {
                return;
            }
            if ($patientLinkId <= 0) {
                $patientLinkId = (int) ($appt["patient_link_id"] ?? 0);
            }
            $name = $patientLinkId
                ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::patient_display_name($patientLinkId, $cid)
                : "paciente";
            $stmt = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.02.workflow_on_appointment_finished.02', [$appointmentId, $cid], []);
            if ($stmt->rowCount() <= 0) {
                return;
            }
            $desc =
                "Atendimento finalizado. Conferir saída do paciente, retorno, documentos entregues, orientação administrativa e pagamento quando aplicável.";
            if (\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::workflow_appointment_payment_pending($cid, $appointmentId)) {
                $desc .=
                    "\n\nAtenção: há pagamento previsto ainda sem baixa nesta consulta.";
            }
            \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::create_workflow_task(
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
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("fluxo_atendimento_finalizado", "consulta", $appointmentId, [
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

    public static function unread_notifications_count(array $c): int
    
    {
    
        if (($c["scope"] ?? "") !== "clinic") {
            return 0;
        }
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        $targetParams = \Prontoo\Domain\TasksNotices\TasksNoticesDomainOperations01::notice_target_parameters($c);
        $n = 0;
        try {
            $n += (int) \Prontoo\Runtime\Operational\OperationalComposition::tasks()->scalar('operational.tasks_notices.02.unread_notifications_count.01', array_merge([$cid], $targetParams, [$uid]), []);
        } catch (Throwable $e) {
            error_log("[Prontoo unread notices] " . $e->getMessage());
        }
        try {
            $n += (int) \Prontoo\Runtime\Operational\OperationalComposition::tasks()->scalar('operational.tasks_notices.02.unread_notifications_count.02', [], []);
        } catch (Throwable $e) {
            error_log("[Prontoo unread global notices] " . $e->getMessage());
        }
        return min(99, $n);
    
    }

    public static function notification_button(array $c): string
    
    {
    
        if (($c["scope"] ?? "") !== "clinic") {
            return "";
        }
        $n = \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::unread_notifications_count($c);
        return '<a class="notify-link top-icon" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices") .
            '" aria-label="Avisos" title="Avisos">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
            ($n > 0 ? '<b class="badge">' . $n . "</b>" : "") .
            "</a>";
    
    }

    public static function team_user_ids_for_roles(int $cid, array $roles): array
    
    {
    
        $roles = array_values(array_unique(array_filter($roles)));
        if (!$roles) {
            return [];
        }
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.02.team_user_ids_for_roles.01', array_merge([$cid], $roles), ['itemCount' => count($roles)])->fetchAll();
        return \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "user_id");
    
    }

    public static function first_team_user_for_role(int $cid, string $role): ?int
    
    {
    
        try {
            $id = (int) \Prontoo\Runtime\Operational\OperationalComposition::tasks()->scalar('operational.tasks_notices.02.first_team_user_for_role.01', [$cid, $role], []);
            return $id > 0 ? $id : null;
        } catch (Throwable $e) {
            return null;
        }
    
    }

    public static function create_workflow_task(
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
    ): ?int 
    {
    
        try {
            $targetScope =
                $targetScope !== ""
                    ? $targetScope
                    : ($assignedTo !== null
                        ? "user"
                        : "clinic");
            if (!in_array($targetScope, ["clinic", "role", "user"], true)) {
                if ($strict) {
                    throw new RuntimeException("Escopo de destinatário inválido para a tarefa.");
                }
                $targetScope = "clinic";
            }
            if (
                $targetScope === "role" &&
                !array_key_exists(
                    (string) $targetRole,
                    \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_options($cid, true),
                )
            ) {
                if ($strict) {
                    throw new RuntimeException("Cargo destinatário inválido para a tarefa.");
                }
                $targetScope = "clinic";
                $targetRole = null;
            }
            if ($assignedTo !== null && !\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_user_exists($cid, $assignedTo)) {
                if ($strict) {
                    throw new RuntimeException("Pessoa destinatária inválida para a tarefa.");
                }
                $assignedTo = null;
            }
            if ($targetScope === "user" && $assignedTo === null) {
                if ($strict) {
                    throw new RuntimeException("A tarefa exige uma pessoa destinatária ativa.");
                }
                $targetScope = "clinic";
                $targetRole = null;
            }
            $targetUserId = $targetScope === "user" ? $assignedTo : null;
            if ($targetScope !== "role") {
                $targetRole = null;
            }
            if (
                $patientLinkId !== null &&
                !\Prontoo\Runtime\Patients\PatientsRuntimeOperations02::clinic_patient_exists($cid, $patientLinkId, false)
            ) {
                $patientLinkId = null;
            }
            if ($appointmentId !== null) {
                $appt = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->row('operational.tasks_notices.02.create_workflow_task.01', [$appointmentId, $cid], []);
                if (!$appt) {
                    $appointmentId = null;
                } elseif (
                    $patientLinkId !== null &&
                    (int) $appt["patient_link_id"] !== $patientLinkId
                ) {
                    $patientLinkId = null;
                }
            }
            $createdBy = isset($_SESSION["uid"]) ? (int) $_SESSION["uid"] : null;
            $outcome = \Prontoo\Runtime\Operational\OperationalComposition::taskCommands()
                ->createWorkflowTask(
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
                    $createdBy,
                    static fn(...$arguments): mixed =>
                        \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::task_event(
                            ...$arguments,
                        ),
                );
            $taskId = (int) ($outcome['task_id'] ?? 0);
            if (empty($outcome['created'])) {
                return $taskId;
            }
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("tasks_total");
            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "tasks");
            if ($targetScope === "user" && $targetUserId) {
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::notify_task_personal_assignment(
                    $cid,
                    $taskId,
                    $title,
                    $description,
                    (int) $targetUserId,
                    isset($_SESSION["uid"]) ? (int) $_SESSION["uid"] : null,
                    "workflow",
                );
            } elseif ($targetScope === "role" && $targetRole) {
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::workflow_task_notice(
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
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("tarefa_criada", "tarefa", $taskId, [
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

    public static function workflow_on_patient_arrived(
        int $cid,
        int $appointmentId,
        int $patientLinkId,
    ): void 
    {
    
        $name = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::patient_display_name($patientLinkId, $cid);
        \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::create_workflow_task(
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

    public static function workflow_on_task_completed(
        array $task,
        int $completedBy,
        string $role,
    ): void 
    {
    
        $cid = (int) ($task["clinic_id"] ?? 0);
        if ($cid <= 0) {
            return;
        }
        $source = (string) ($task["source_event"] ?? "");
        $appt = (int) ($task["appointment_id"] ?? 0);
        $patient = (int) ($task["patient_link_id"] ?? 0);
        $name = $patient ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::patient_display_name($patient, $cid) : "paciente";
        if ($source === "paciente_chegou" && $appt > 0) {
            \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.02.workflow_on_task_completed.01', [$appt, $cid], []);
            $doctor =
                (int) (\Prontoo\Runtime\Operational\OperationalComposition::tasks()->scalar('operational.tasks_notices.02.workflow_on_task_completed.02', [$appt, $cid], []) ?:
                0);
            \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::create_workflow_task(
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
            \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::workflow_on_appointment_finished(
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
            \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.02.workflow_on_task_completed.03', [$appt, $cid], []);
            if (\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations01::workflow_appointment_payment_pending($cid, $appt)) {
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::create_workflow_task(
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
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("fluxo_saida_concluida", "consulta", $appt, [
                    "patient_link_id" => $patient,
                    "audit_body" =>
                        "A Recepção concluiu a saída do paciente. Não foi gerado novo recado porque não havia exceção pendente.",
                ]);
            }
        }
    
    }

    public static function task_nav_counts(array $c): array
    
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
        [$todayStart, $todayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range(
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c),
            $cid,
            $c,
        );
        try {
            $row =
                \Prontoo\Runtime\Operational\OperationalComposition::tasks()->row('operational.tasks_notices.02.task_nav_counts.01', [$todayStart, $todayEnd, $role, $uid, $uid, $role, $cid], []) ?:
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
}
