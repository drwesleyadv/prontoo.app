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

    public static function unread_notifications_count(array $c): int
    
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

    public static function notification_button(array $c): string
    
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

    public static function team_user_ids_for_roles(int $cid, array $roles): array
    
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

    public static function first_team_user_for_role(int $cid, string $role): ?int
    
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
                    clinic_role_options($cid, true),
                )
            ) {
                if ($strict) {
                    throw new RuntimeException("Cargo destinatário inválido para a tarefa.");
                }
                $targetScope = "clinic";
                $targetRole = null;
            }
            if ($assignedTo !== null && !clinic_user_exists($cid, $assignedTo)) {
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

    public static function workflow_on_patient_arrived(
        int $cid,
        int $appointmentId,
        int $patientLinkId,
    ): void 
    {
    
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
}
