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

final class TasksNoticesRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function notice_target_label(array $n, array $users = []): string
    
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

    public static function notice_recipient_phrase(array $n, array $users = []): string
    
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

    public static function notice_recipient_people(int $cid, array $notice, array $team): array
    
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
        $missing = array_values(array_filter($ids,  fn($id) => !isset($team[$id])));
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

    public static function notice_recipient_pills(int $cid, array $notice, array $team): string
    
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

    public static function task_event(
        int $cid,
        int $taskId,
        string $eventKey,
        ?int $actorUserId = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $note = null,
    ): void 
    {
    
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

    public static function notify_task_personal_assignment(
        int $cid,
        int $taskId,
        string $taskTitle,
        ?string $taskDescription,
        int $targetUserId,
        ?int $createdBy = null,
        string $source = "manual",
    ): void 
    {
    
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
            $desc = mb_trim((string) $taskDescription);
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

    public static function workflow_valid_role(int $cid, ?string $role): ?string
    
    {
    
        $role = mb_trim((string) $role);
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

    public static function workflow_care_notice(
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
    ): ?int 
    {
    
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

    public static function workflow_task_notice(
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
    ): void 
    {
    
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

    public static function workflow_appointment_payment_pending(
        int $cid,
        int $appointmentId,
    ): bool 
    {
    
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

    public static function workflow_on_task_started(
        array $task,
        int $startedBy,
        string $role,
    ): void 
    {
    
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
}
