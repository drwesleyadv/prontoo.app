<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Appointments;

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

final class AppointmentsRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function page_appointments(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("appointments");
        $cid = (int) $c["clinic_id"];
        \Prontoo\Infrastructure\Appointments\AppointmentsInfrastructureOperations01::agenda_notes_ensure_schema($cid);
        $docs = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::doctors($cid);
        $pats = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_options($cid);
        $prePatientId = (int) ($_GET["patient_id"] ?? 0);
        if ($prePatientId > 0 && !isset($pats[$prePatientId])) {
            $prePatientId = 0;
        }
        $procedures = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_options($cid, true);
        $day = (string) ($_GET["d"] ?? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c);
        }
        $viewDoctor = (int) ($_GET["doctor"] ?? 0);
        if (count($docs) === 1) {
            $viewDoctor = (int) array_key_first($docs);
        } elseif (
            count($docs) > 1 &&
            $viewDoctor > 0 &&
            !isset($docs[$viewDoctor])
        ) {
            $viewDoctor = 0;
        } elseif ($viewDoctor > 0 && !isset($docs[$viewDoctor])) {
            $viewDoctor = 0;
        }
        $returnParams = function (?string $d = null, ?int $doctor = null) use (
            $day,
            $viewDoctor,
        ): array {
    
            $d = $d ?: $day;
            $doctor = $doctor ?? $viewDoctor;
            $params = ["d" => $d];
            if ($doctor > 0) {
                $params["doctor"] = $doctor;
            }
            return $params;
        };
        $redirectBack = function (
            ?string $targetDay = null,
            ?int $targetDoctor = null,
        ) use ($returnParams): void {
    
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("appointments", $returnParams($targetDay, $targetDoctor));
        };
        $role = (string) ($c["role"] ?? "");
        $uid = (int) ($c["user"]["id"] ?? 0);
        $returnHidden =
            '<input type="hidden" name="return_day" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($day) .
            '"><input type="hidden" name="return_doctor" value="' .
            (int) $viewDoctor .
            '">';
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "create");
            $postDay = (string) ($_POST["return_day"] ?? $day);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDay)) {
                $postDay = $day;
            }
            $postDoctor = (int) ($_POST["return_doctor"] ?? $viewDoctor);
            if ($postDoctor > 0 && !isset($docs[$postDoctor])) {
                $postDoctor = $viewDoctor;
            }
            $postRedirect = function () use (
                $postDay,
                $postDoctor,
                $redirectBack,
            ): void {
    
                $redirectBack($postDay, $postDoctor);
            };
            if ($act === "agenda_note_delete") {
                $noteId = (int) ($_POST["id"] ?? 0);
                $note =
                    $noteId > 0
                        ? \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                            "SELECT id,note_date,created_by FROM pi_agenda_notes WHERE id=? AND clinic_id=? AND deleted_at IS NULL LIMIT 1",
                            [$noteId, $cid],
                        )
                        : null;
                if (!$note) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Anotação não encontrada ou já excluída.", "bad");
                    $postRedirect();
                }
                $noteDay = (string) ($note["note_date"] ?? $postDay);
                $creator = (int) ($note["created_by"] ?? 0);
                if ($creator <= 0 || $creator !== $uid) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Somente quem criou a anotação pode excluí-la.", "bad");
                    $redirectBack($noteDay, $postDoctor);
                }
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "UPDATE pi_agenda_notes SET deleted_at=NOW(), deleted_by=?, updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND deleted_at IS NULL AND created_by=?",
                    [$uid, $uid, $noteId, $cid, $uid],
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("agenda_anotacao_excluida", "agenda_note", $noteId, [
                    "note_date" => $noteDay,
                    "audit_body" =>
                        "Anotação da Agenda excluída logicamente pelo usuário que a criou.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Anotação excluída.");
                $redirectBack($noteDay, $postDoctor);
            }
            if ($act === "agenda_note") {
                $noteDate = (string) ($_POST["note_date"] ?? $postDay);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDate)) {
                    $noteDate = $postDay;
                }
                $content = mb_trim((string) ($_POST["content"] ?? ""));
                if ($content === "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o conteúdo da anotação.", "bad");
                    $postRedirect();
                }
                $content = mb_substr($content, 0, 4000);
                $visibility = (string) ($_POST["visibility_scope"] ?? "my_role");
                $roleOptions = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_options($cid, false);
                $targetScope = "clinic";
                $targetRole = null;
                if ($visibility === "clinic") {
                    $targetScope = "clinic";
                    $targetRole = null;
                } else {
                    $targetScope = "role";
                    $targetRole = (string) ($c["role"] ?? "");
                    if ($targetRole === "" || !isset($roleOptions[$targetRole])) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "Não foi possível identificar seu cargo para restringir a anotação.",
                            "bad",
                        );
                        $postRedirect();
                    }
                }
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "INSERT INTO pi_agenda_notes (clinic_id,note_date,content,target_scope,target_role,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())",
                    [
                        $cid,
                        $noteDate,
                        $content,
                        $targetScope,
                        $targetRole,
                        (int) $c["user"]["id"],
                        (int) $c["user"]["id"],
                    ],
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                    "agenda_anotacao_criada",
                    "agenda_note",
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_last_insert_id(),
                    [
                        "note_date" => $noteDate,
                        "target_scope" => $targetScope,
                        "target_role" => $targetRole,
                    ],
                );
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Anotação registrada para a data escolhida.");
                $redirectBack($noteDate, $postDoctor);
            }
            if ($act === "confirm") {
                $id = (int) ($_POST["id"] ?? 0);
                $a = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                $guard = $a
                    ? \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                        $a,
                        "confirm",
                        $role,
                        $uid,
                    )
                    : "Agendamento não encontrado.";
                if ($guard === "") {
                    $stmt = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_appointments SET status='confirmado', updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consulta_confirmada", "consulta", $id, [
                        "patient_link_id" => (int) ($a["patient_link_id"] ?? 0),
                        "audit_body" =>
                            "Presença do paciente confirmada pela Recepção. Próxima medida: registrar chegada quando ele estiver no consultório.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Presença confirmada. Próxima medida: registrar chegada no horário.",
                    );
                } else {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($guard, "bad");
                }
                $postRedirect();
            }
            if ($act === "arrived") {
                $id = (int) ($_POST["id"] ?? 0);
                $a = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                $guard = $a
                    ? \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                        $a,
                        "arrived",
                        $role,
                        $uid,
                    )
                    : "Agendamento não encontrado.";
                if ($guard === "") {
                    $stmt = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_appointments SET status='chegou', arrived_at=COALESCE(arrived_at,NOW()), updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::workflow_on_patient_arrived(
                        $cid,
                        $id,
                        (int) ($a["patient_link_id"] ?? 0),
                    );
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("paciente_chegou", "consulta", $id, [
                        "patient_link_id" => (int) ($a["patient_link_id"] ?? 0),
                        "audit_body" =>
                            "Paciente chegou ao consultório. A equipe de triagem recebeu tarefa automática para preparar o atendimento.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Chegada registrada. Próxima medida: Assistente inicia preparo/triagem.",
                    );
                } else {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($guard, "bad");
                }
                $postRedirect();
            }
            if ($act === "no_show") {
                $id = (int) ($_POST["id"] ?? 0);
                $reason = mb_trim((string) ($_POST["no_show_reason"] ?? ""));
                $a = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                if (!$a) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Agendamento não encontrado.", "bad");
                    $postRedirect();
                }
                $guard = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                    $a,
                    "no_show",
                    $role,
                    $uid,
                );
                if ($guard !== "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($guard, "bad");
                    $postRedirect();
                }
                $stmt = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "UPDATE pi_appointments SET status='nao_compareceu', cancel_reason=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                    [
                        $reason !== ""
                            ? $reason
                            : "Paciente não compareceu no horário agendado.",
                        $id,
                        $cid,
                    ],
                );
                if ($stmt->rowCount() <= 0) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                if (is_callable([\Prontoo\Runtime\Financial\FinancialRuntimeOperations05::class, 'financial_cancel_appointment_revenue'])) {
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_cancel_appointment_revenue(
                        $cid,
                        $id,
                        $uid,
                        $reason !== ""
                            ? $reason
                            : "Paciente não compareceu no horário agendado.",
                    );
                }
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("paciente_nao_compareceu", "consulta", $id, [
                    "patient_link_id" => (int) ($a["patient_link_id"] ?? 0),
                    "motivo" => $reason,
                    "audit_body" =>
                        "Ausência registrada. Próxima medida: Recepção decide contato, orientação ou remarcação.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Não comparecimento registrado. Próxima medida: orientar contato ou remarcação.",
                );
                $postRedirect();
            }
            if (
                in_array(
                    $act,
                    [
                        "start_prepare",
                        "finish_prepare",
                        "start_consultation",
                        "finish_consultation",
                        "finish_checkout",
                    ],
                    true,
                )
            ) {
                $id = (int) ($_POST["id"] ?? 0);
                $a = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at,payment_status,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                if (!$a) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Agendamento não encontrado.", "bad");
                    $postRedirect();
                }
                $code = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a);
                $patientId = (int) ($a["patient_link_id"] ?? 0);
                $uidNow = (int) ($c["user"]["id"] ?? 0);
                if ($act === "start_prepare") {
                    $guard = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                        $a,
                        "start_prepare",
                        $role,
                        $uidNow,
                    );
                    if ($guard !== "") {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($guard, "bad");
                        $postRedirect();
                    }
                    $stmt = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_appointments SET status='em_preparo', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='chegou' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='em_andamento', t.started_by=COALESCE(t.started_by,?), t.started_at=COALESCE(t.started_at,NOW()), t.assigned_to=COALESCE(t.assigned_to,?), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='paciente_chegou' AND t.status IN ('aberta','aguardando')",
                        [$uidNow, $uidNow, $cid, $id],
                    );
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("preparo_iniciado_atalho", "consulta", $id, [
                        "patient_link_id" => $patientId,
                        "audit_body" =>
                            "Assistente iniciou o preparo/triagem pelo atalho visual da jornada.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Preparo iniciado. Próxima medida: concluir preparo e liberar ao profissional.",
                    );
                    $postRedirect();
                }
                if ($act === "finish_prepare") {
                    $guard = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                        $a,
                        "finish_prepare",
                        $role,
                        $uidNow,
                    );
                    if ($guard !== "") {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($guard, "bad");
                        $postRedirect();
                    }
                    $stmt = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_appointments SET status='pronto_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='em_preparo' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='paciente_chegou' AND t.status IN ('aberta','em_andamento','aguardando')",
                        [$uidNow, $cid, $id],
                    );
                    if (is_callable([\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::class, 'create_workflow_task'])) {
                        $name = $patientId
                            ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::patient_display_name($patientId, $cid)
                            : "paciente";
                        $doctor =
                            (int) (\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
                                "SELECT doctor_user_id FROM pi_appointments WHERE id=? AND clinic_id=?",
                                [$id, $cid],
                            ) ?:
                            0);
                        \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::create_workflow_task(
                            $cid,
                            "Atender " . $name,
                            "Preparativos concluídos. O paciente está pronto para iniciar o atendimento.",
                            $doctor ?: null,
                            $patientId ?: null,
                            $id,
                            "preparo_concluido",
                            "consulta",
                            (string) $id,
                            null,
                            $doctor ? "user" : "role",
                            $doctor ? null : "medico",
                        );
                    }
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("preparo_concluido_atalho", "consulta", $id, [
                        "patient_link_id" => $patientId,
                        "audit_body" =>
                            "Assistente liberou o paciente para atendimento pelo atalho visual da jornada.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Paciente liberado. Próxima medida: Profissional inicia atendimento.",
                    );
                    $postRedirect();
                }
                if ($act === "start_consultation") {
                    $guard = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                        $a,
                        "start_consultation",
                        $role,
                        $uidNow,
                    );
                    if ($guard !== "") {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($guard, "bad");
                        $postRedirect();
                    }
                    $stmt = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), status='em_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='pronto_atendimento' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL AND (doctor_user_id IS NULL OR doctor_user_id=?)",
                        [$id, $cid, $uidNow],
                    );
                    if ($stmt->rowCount() <= 0) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='em_andamento', t.started_by=COALESCE(t.started_by,?), t.started_at=COALESCE(t.started_at,NOW()), t.assigned_to=COALESCE(t.assigned_to,?), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','aguardando')",
                        [$uidNow, $uidNow, $cid, $id],
                    );
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consulta_iniciada_atalho", "consulta", $id, [
                        "patient_link_id" => $patientId,
                        "audit_body" =>
                            "Profissional iniciou o atendimento pelo atalho visual da jornada.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Atendimento iniciado. A Ficha do Paciente ficará aberta durante o atendimento.",
                    );
                    if ($patientId > 0) {
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", [
                            "id" => $patientId,
                            "appointment_id" => $id,
                            "from_appointments" => 1,
                            "return_day" => $postDay,
                            "return_doctor" => $postDoctor,
                        ]);
                    }
                    $postRedirect();
                }
                if ($act === "finish_consultation") {
                    $guard = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                        $a,
                        "finish_consultation",
                        $role,
                        $uidNow,
                    );
                    if ($guard !== "") {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($guard, "bad");
                        $postRedirect();
                    }
                    if (is_callable([\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::class, 'workflow_on_appointment_finished'])) {
                        \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::workflow_on_appointment_finished(
                            $cid,
                            $id,
                            $patientId,
                            $uidNow,
                            "atalho_jornada",
                        );
                    } else {
                        $stmt = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                            "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), consultation_finished_at=COALESCE(consultation_finished_at,NOW()), status='atendimento_concluido', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='em_atendimento' AND consultation_finished_at IS NULL",
                            [$id, $cid],
                        );
                        if ($stmt->rowCount() <= 0) {
                            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                                "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                                "bad",
                            );
                            $postRedirect();
                        }
                    }
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','em_andamento','aguardando')",
                        [$uidNow, $cid, $id],
                    );
                    try {
                        if (is_callable([\Prontoo\Runtime\Financial\FinancialRuntimeOperations01::class, 'financial_sync_appointment'])) {
                            \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::financial_sync_appointment($cid, $id, $uidNow);
                        }
                    } catch (Throwable $e) {
                        error_log(
                            "[Prontoo agenda financial sync after finish] " .
                                $e->getMessage(),
                        );
                    }
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consulta_concluida_atalho", "consulta", $id, [
                        "patient_link_id" => $patientId,
                        "audit_body" =>
                            "Profissional concluiu o atendimento pelo atalho visual da jornada.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        (int) ($a["payment_amount_cents"] ?? 0) > 0 &&
                        !in_array(
                            mb_strtolower(
                                mb_trim((string) ($a["payment_status"] ?? "")),
                            ),
                            [
                                "efetivada",
                                "recebido",
                                "pago",
                                "isento",
                                "cortesia",
                                "sem_cobranca",
                            ],
                            true,
                        )
                            ? "Atendimento concluído. Próxima medida: Recepção recebe o pagamento e finaliza a saída."
                            : "Atendimento concluído. Próxima medida: Recepção finaliza saída.",
                    );
                    $postRedirect();
                }
                if ($act === "finish_checkout") {
                    $guard = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                        $a,
                        "finish_checkout",
                        $role,
                        $uidNow,
                    );
                    if ($guard !== "") {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($guard, "bad");
                        $postRedirect();
                    }
                    $stmt = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_appointments SET status='finalizado', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='atendimento_concluido' AND consultation_finished_at IS NOT NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='atendimento_finalizado' AND t.status IN ('aberta','em_andamento','aguardando')",
                        [$uidNow, $cid, $id],
                    );
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("saida_finalizada_atalho", "consulta", $id, [
                        "patient_link_id" => $patientId,
                        "audit_body" =>
                            "Recepção finalizou a saída do paciente pelo atalho visual da jornada.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Jornada finalizada.");
                    $postRedirect();
                }
            }
            if ($act === "block") {
                $startAt = (string) ($_POST["start_at"] ?? "");
                $endAt = (string) ($_POST["end_at"] ?? "");
                $reason = mb_trim((string) ($_POST["reason"] ?? ""));
                $rawDoctor = (string) ($_POST["doctor_user_id"] ?? "");
                $blockDoctor =
                    $rawDoctor !== "" && (int) $rawDoctor > 0
                        ? (int) $rawDoctor
                        : null;
                if ($blockDoctor !== null && !isset($docs[$blockDoctor])) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Selecione um profissional válido para o bloqueio ou use bloqueio do consultório inteiro.",
                        "bad",
                    );
                    $postRedirect();
                }
                if ($blockDoctor === null && !\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente")) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Bloqueio de todo o consultório exige ambiente Administrativo. Bloqueie apenas a agenda de um profissional ou acione o Administrativo.",
                        "bad",
                    );
                    $postRedirect();
                }
                if (
                    $startAt === "" ||
                    $endAt === "" ||
                    strtotime($endAt) <= strtotime($startAt) ||
                    $reason === ""
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Informe início, fim posterior ao início e motivo do bloqueio.",
                        "bad",
                    );
                    $postRedirect();
                }
                $startAt = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($startAt);
                $endAt = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($endAt);
                $blockId = 0;
                try {
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_begin_transaction();
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                    $conflict = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_conflict_message(
                        $cid,
                        $blockDoctor,
                        $startAt,
                        $endAt,
                        "bloqueio",
                    );
                    if ($conflict) {
                        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($conflict, "bad");
                        $postRedirect();
                    }
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "INSERT INTO pi_blocks (clinic_id,doctor_user_id,start_at,end_at,reason,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())",
                        [
                            $cid,
                            $blockDoctor,
                            $startAt,
                            $endAt,
                            $reason,
                            (int) $c["user"]["id"],
                        ],
                    );
                    $blockId = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_last_insert_id();
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_commit();
                } catch (Throwable $e) {
                    if (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->inTransaction()) {
                        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                    }
                    error_log("[Prontoo agenda block] " . $e->getMessage());
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Não foi possível registrar o bloqueio. Revise os horários e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "agenda_blocks");
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("agenda_bloqueada", "bloqueio", $blockId, $_POST);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Horário bloqueado.");
                $postRedirect();
            }
            if ($act === "update_block") {
                $id = (int) ($_POST["id"] ?? 0);
                $b = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one("SELECT id FROM pi_blocks WHERE id=? AND clinic_id=?", [
                    $id,
                    $cid,
                ]);
                if (!$b) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Bloqueio não encontrado.", "bad");
                    $postRedirect();
                }
                $startAt = (string) ($_POST["start_at"] ?? "");
                $endAt = (string) ($_POST["end_at"] ?? "");
                $reason = mb_trim((string) ($_POST["reason"] ?? ""));
                $rawDoctor = (string) ($_POST["doctor_user_id"] ?? "");
                $blockDoctor =
                    $rawDoctor !== "" && (int) $rawDoctor > 0
                        ? (int) $rawDoctor
                        : null;
                if ($blockDoctor !== null && !isset($docs[$blockDoctor])) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Selecione um profissional válido para o bloqueio ou use bloqueio do consultório inteiro.",
                        "bad",
                    );
                    $postRedirect();
                }
                if ($blockDoctor === null && !\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente")) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Bloqueio de todo o consultório exige ambiente Administrativo. Bloqueie apenas a agenda de um profissional ou acione o Administrativo.",
                        "bad",
                    );
                    $postRedirect();
                }
                if (
                    $startAt === "" ||
                    $endAt === "" ||
                    strtotime($endAt) <= strtotime($startAt) ||
                    $reason === ""
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Informe início, fim posterior ao início e motivo do bloqueio.",
                        "bad",
                    );
                    $postRedirect();
                }
                $startAt = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($startAt);
                $endAt = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($endAt);
                $hoursMessage =
                    $blockDoctor !== null
                        ? \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_hours_conflict_message(
                            $cid,
                            $blockDoctor,
                            $startAt,
                            $endAt,
                        )
                        : "";
                if ($hoursMessage !== "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($hoursMessage, "bad");
                    $postRedirect();
                }
                try {
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_begin_transaction();
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                    $conflict = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_conflict_message(
                        $cid,
                        $blockDoctor,
                        $startAt,
                        $endAt,
                        "alteração do bloqueio",
                        0,
                        $id,
                    );
                    if ($conflict) {
                        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($conflict, "bad");
                        $postRedirect();
                    }
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_blocks SET doctor_user_id=?, start_at=?, end_at=?, reason=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                        [$blockDoctor, $startAt, $endAt, $reason, $id, $cid],
                    );
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_commit();
                } catch (Throwable $e) {
                    if (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->inTransaction()) {
                        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                    }
                    error_log("[Prontoo agenda block update] " . $e->getMessage());
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Não foi possível alterar o bloqueio. Revise os dados e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("bloqueio_alterado", "bloqueio", $id, $_POST);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Bloqueio alterado.");
                $postRedirect();
            }
            if ($act === "delete_block" || $act === "unblock") {
                $id = (int) ($_POST["id"] ?? 0);
                $cancelReason = mb_trim((string) ($_POST["cancel_reason"] ?? ""));
                if ($cancelReason === "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o motivo da exclusão do bloqueio.", "bad");
                    $postRedirect();
                }
                $b = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id FROM pi_blocks WHERE id=? AND clinic_id=? AND deleted_at IS NULL",
                    [$id, $cid],
                );
                if ($b) {
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_blocks SET deleted_at=NOW(),deleted_by=?,cancel_reason=?,updated_at=NOW() WHERE id=? AND clinic_id=? AND deleted_at IS NULL",
                        [(int) $c["user"]["id"], $cancelReason, $id, $cid],
                    );
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("bloqueio_removido", "bloqueio", $id, [
                        "motivo" => $cancelReason,
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Bloqueio excluído da agenda e preservado no histórico.");
                } else {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Bloqueio não encontrado.", "bad");
                }
                $postRedirect();
            }
            if ($act === "update_appointment") {
                $id = (int) ($_POST["id"] ?? 0);
                $a = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                if (!$a) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Agendamento não encontrado.", "bad");
                    $postRedirect();
                }
                $guard = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                    $a,
                    "update_appointment",
                    $role,
                    $uid,
                );
                if ($guard !== "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($guard, "bad");
                    $postRedirect();
                }
                $did = (int) ($_POST["doctor_user_id"] ?? 0);
                $startAt = (string) ($_POST["start_at"] ?? "");
                $endAt = (string) ($_POST["end_at"] ?? "");
                $patient = (int) ($_POST["patient_link_id"] ?? 0);
                if (!isset($docs[$did])) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Selecione um profissional válido.", "bad");
                    $postRedirect();
                }
                if (
                    $patient <= 0 ||
                    !isset($pats[$patient]) ||
                    $startAt === "" ||
                    $endAt === "" ||
                    strtotime($endAt) <= strtotime($startAt)
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Informe paciente da clínica atual, início e fim posterior ao início.",
                        "bad",
                    );
                    $postRedirect();
                }
                if (
                    $blockReason = \Prontoo\Runtime\Patients\PatientComposition::registrationBlockReason(
                        $cid,
                        $patient,
                    )
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($blockReason, "bad");
                    $postRedirect();
                }
                $startAt = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($startAt);
                $endAt = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($endAt);
                $hoursMessage = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_hours_conflict_message(
                    $cid,
                    $did,
                    $startAt,
                    $endAt,
                );
                if ($hoursMessage !== "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($hoursMessage, "bad");
                    $postRedirect();
                }
                $procId = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_procedure_id_from_post($cid);
                $durationMessage = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_min_duration_message(
                    $cid,
                    $procId,
                    $startAt,
                    $endAt,
                );
                if ($durationMessage) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($durationMessage, "bad");
                    $postRedirect();
                }
                try {
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_begin_transaction();
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                    $locked = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                        "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? FOR UPDATE",
                        [$id, $cid],
                    );
                    $lockedGuard = $locked
                        ? \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                            $locked,
                            "update_appointment",
                            $role,
                            $uid,
                        )
                        : "Agendamento não encontrado.";
                    if ($lockedGuard !== "") {
                        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($lockedGuard, "bad");
                        $postRedirect();
                    }
                    $conflict = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_conflict_message(
                        $cid,
                        $did,
                        $startAt,
                        $endAt,
                        "alteração do agendamento",
                        $id,
                        0,
                    );
                    if ($conflict) {
                        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($conflict, "bad");
                        $postRedirect();
                    }
                    [
                        $paid,
                        $method,
                        $amount,
                        $paymentDestination,
                        $paymentError,
                    ] = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_payment_post_context(
                        $cid,
                        $procId,
                        (int) ($a["procedure_id"] ?? 0) === (int) $procId
                    ? (int) ($a["payment_amount_cents"] ?? 0)
                    : 0,
                    );
                    if ($paymentError) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($paymentError, "bad");
                        $postRedirect();
                    }
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_appointments SET patient_link_id=?, doctor_user_id=?, start_at=?, end_at=?, procedure_id=?, reason=?, notes=?, change_reason=?, payment_amount_cents=?, payment_method=?, payment_status=?, payment_confirmed_at=IF(?,COALESCE(payment_confirmed_at,NOW()),NULL), updated_at=NOW() WHERE id=? AND clinic_id=?",
                        [
                            $patient,
                            $did,
                            $startAt,
                            $endAt,
                            $procId,
                            \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_reason_from_post($cid),
                            mb_trim((string) ($_POST["notes"] ?? "")),
                            mb_trim((string) ($_POST["change_reason"] ?? "")),
                            $amount,
                            $method ?: null,
                            $paid ? "efetivada" : "prevista",
                            $paid ? 1 : 0,
                            $id,
                            $cid,
                        ],
                    );
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::financial_sync_appointment(
                        $cid,
                        $id,
                        (int) $c["user"]["id"],
                        $paymentDestination,
                    );
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_commit();
                } catch (Throwable $e) {
                    if (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->inTransaction()) {
                        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                    }
                    error_log("[Prontoo appointment update] " . $e->getMessage());
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        $e instanceof RuntimeException &&
                        trim($e->getMessage()) !== ""
                            ? $e->getMessage()
                            : "Não foi possível alterar o agendamento. Revise os horários e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consulta_alterada", "consulta", $id, $_POST);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Agendamento alterado.");
                $postRedirect();
            }
            if ($act === "delete_appointment") {
                $id = (int) ($_POST["id"] ?? 0);
                $cancelReason = mb_trim((string) ($_POST["cancel_reason"] ?? ""));
                if ($cancelReason === "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Informe o motivo da exclusão/cancelamento do agendamento.",
                        "bad",
                    );
                    $postRedirect();
                }
                $a = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                $guard = $a
                    ? \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                        $a,
                        "delete_appointment",
                        $role,
                        $uid,
                    )
                    : "Agendamento não encontrado.";
                if ($a && $guard === "") {
                    $stmt = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_appointments SET status='cancelado', cancel_reason=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                        [$cancelReason, $id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    if (is_callable([\Prontoo\Runtime\Financial\FinancialRuntimeOperations05::class, 'financial_cancel_appointment_revenue'])) {
                        \Prontoo\Runtime\Financial\FinancialRuntimeOperations05::financial_cancel_appointment_revenue(
                            $cid,
                            $id,
                            $uid,
                            $cancelReason,
                        );
                    }
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consulta_excluida", "consulta", $id, [
                        "motivo" => $cancelReason,
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Agendamento excluído da agenda e preservado como cancelado no histórico.",
                    );
                } else {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($guard, "bad");
                }
                $postRedirect();
            }
            $did = (int) ($_POST["doctor_user_id"] ?? 0);
            $startAt = (string) ($_POST["start_at"] ?? "");
            $endAt = (string) ($_POST["end_at"] ?? "");
            $patient = (int) ($_POST["patient_link_id"] ?? 0);
            if (!isset($docs[$did])) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Selecione um profissional válido.", "bad");
                $postRedirect();
            }
            if (
                $patient <= 0 ||
                !isset($pats[$patient]) ||
                $startAt === "" ||
                $endAt === "" ||
                strtotime($endAt) <= strtotime($startAt)
            ) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Informe paciente da clínica atual, início e fim posterior ao início.",
                    "bad",
                );
                $postRedirect();
            }
            if (
                $blockReason = \Prontoo\Runtime\Patients\PatientComposition::registrationBlockReason(
                    $cid,
                    $patient,
                )
            ) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($blockReason, "bad");
                $postRedirect();
            }
            $startAt = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($startAt);
            $endAt = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::normalize_db_datetime($endAt);
            $hoursMessage = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_hours_conflict_message(
                $cid,
                $did,
                $startAt,
                $endAt,
            );
            if ($hoursMessage !== "") {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($hoursMessage, "bad");
                $postRedirect();
            }
            $procId = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_procedure_id_from_post($cid);
            if ($act === "create" && !$procId) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_options($cid, true) === []
                        ? "Cadastre Procedimentos primeiro."
                        : "Selecione um procedimento cadastrado.",
                    "bad",
                );
                $postRedirect();
            }
            $appointmentId = 0;
            try {
                \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_begin_transaction();
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                $lockedProcedure = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT id,title,duration_minutes FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1 FOR UPDATE",
                    [$procId, $cid],
                );
                if (!$lockedProcedure) {
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "O procedimento selecionado não está mais disponível. Escolha outro procedimento cadastrado.",
                        "bad",
                    );
                    $postRedirect();
                }
                $durationMessage = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_min_duration_message(
                    $cid,
                    $procId,
                    $startAt,
                    $endAt,
                );
                if ($durationMessage) {
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($durationMessage, "bad");
                    $postRedirect();
                }
                $conflict = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_conflict_message(
                    $cid,
                    $did,
                    $startAt,
                    $endAt,
                    "agendamento",
                );
                if ($conflict) {
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($conflict, "bad");
                    $postRedirect();
                }
                [
                    $paid,
                    $method,
                    $amount,
                    $paymentDestination,
                    $paymentError,
                ] = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations06::appointment_payment_post_context($cid, $procId, 0);
                if ($paymentError) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($paymentError, "bad");
                    $postRedirect();
                }
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "INSERT INTO pi_appointments (clinic_id,patient_link_id,doctor_user_id,start_at,end_at,status,procedure_id,reason,notes,payment_amount_cents,payment_method,payment_status,payment_confirmed_at,created_by,created_at) VALUES (?,?,?,?,?,'agendado',?,?,?,?,?,?,IF(?,NOW(),NULL),?,NOW())",
                    [
                        $cid,
                        $patient,
                        $did,
                        $startAt,
                        $endAt,
                        $procId,
                        (string) $lockedProcedure["title"],
                        mb_trim((string) ($_POST["notes"] ?? "")),
                        $amount,
                        $method ?: null,
                        $paid ? "efetivada" : "prevista",
                        $paid ? 1 : 0,
                        (int) $c["user"]["id"],
                    ],
                );
                $appointmentId = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_last_insert_id();
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::financial_sync_appointment(
                    $cid,
                    $appointmentId,
                    (int) $c["user"]["id"],
                    $paymentDestination,
                );
                \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_commit();
            } catch (Throwable $e) {
                if (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->inTransaction()) {
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                }
                error_log("[Prontoo appointment create] " . $e->getMessage());
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    $e instanceof RuntimeException && trim($e->getMessage()) !== ""
                        ? $e->getMessage()
                        : "Não foi possível agendar a consulta. Revise os horários e tente novamente.",
                    "bad",
                );
                $postRedirect();
            }
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("appointments_total");
            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "appointments");
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consulta_agendada", "consulta", $appointmentId, $_POST);
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Consulta agendada.");
            $postRedirect();
        }
        $agendaDoctor = $viewDoctor;
        if ($role === "medico" && isset($docs[$uid])) {
            $agendaDoctor = $uid;
        }
        if (count($docs) === 1) {
            $agendaDoctor = (int) array_key_first($docs);
        } elseif ($agendaDoctor <= 0 && count($docs) > 1) {
            $agendaDoctor = (int) array_key_first($docs);
        }
        $agendaView = (string) ($_GET["view"] ?? "diario");
        if (
            !in_array($agendaView, ["resumo", "diario", "semanal", "mensal"], true)
        ) {
            $agendaView = "diario";
        }
        $buildParams = function (
            ?string $d = null,
            ?int $doctor = null,
            ?string $view = null,
        ) use ($day, $agendaDoctor, $role, $agendaView): array {
    
            $params = ["d" => $d ?: $day];
            $doctor = $doctor ?? $agendaDoctor;
            if ($role !== "medico" && $doctor > 0) {
                $params["doctor"] = $doctor;
            }
            $view = $view ?? $agendaView;
            if ($view !== "" && $view !== "diario") {
                $params["view"] = $view;
            }
            return $params;
        };
        $returnHidden =
            '<input type="hidden" name="return_day" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($day) .
            '"><input type="hidden" name="return_doctor" value="' .
            (int) $agendaDoctor .
            '">';
        $formDoctorOptions =
            $role === "medico" && isset($docs[$uid])
                ? [$uid => $docs[$uid]]
                : $docs;
        $selected =
            $agendaDoctor > 0
                ? $agendaDoctor
                : (count($formDoctorOptions) === 1
                    ? (int) array_key_first($formDoctorOptions)
                    : null);
        $operationMode = (string) ($_GET["mode"] ?? "");
        if ($operationMode === "create") {
            if (!in_array($role, ["recepcionista", "gerente"], true)) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Seu cargo não possui operação de Agendamento Rápido.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("appointments", $buildParams());
            }
            $initialStart = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_normalize_local_input(
                (string) ($_GET["start"] ?? ""),
                $day,
                "08:00",
            );
            $initialDay = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_safe_day_from_local_input($initialStart, $day);
            $initialEnd = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_local_input_add_minutes($initialStart, 30);
            $initialDoctor =
                (int) ($_GET["doctor_user_id"] ??
                    ($_GET["doctor"] ?? ($selected ?: 0)));
            if ($initialDoctor <= 0 || !isset($formDoctorOptions[$initialDoctor])) {
                $initialDoctor = $selected ?: 0;
            }
            $cancelParams = $buildParams($initialDay, $initialDoctor);
            $createTitle = "Agendamento Rápido";
            $createSubtitle =
                "Confirme os horários sugeridos abaixo e selecione Profissional, Paciente e Procedimento.";
            $initialHour = substr($initialStart, 11, 5);
            $initialEndHour = substr($initialEnd, 11, 5);
            $doctorHint =
                $initialDoctor > 0
                    ? (string) ($formDoctorOptions[$initialDoctor] ??
                        \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for("medico", $cid))
                    : \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for("medico", $cid);
            $returnHiddenOperation =
                '<input type="hidden" name="return_day" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($initialDay) .
                '"><input type="hidden" name="return_doctor" value="' .
                (int) $initialDoctor .
                '">';
            $quickHeader =
                '<div class="agenda-quick-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
                '</span><div class="agenda-quick-hero-copy"><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($createTitle) .
                "</h2><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($createSubtitle) .
                '</p></div><a class="ghost small agenda-quick-back" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $cancelParams) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Agenda</span></a></div>";
            $quickSummary =
                '<div class="agenda-quick-summary" aria-label="Resumo do horário inferido"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_month") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($initialDay)) .
                "</b><small>Data</small></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($initialHour . "–" . $initialEndHour) .
                "</b><small>Horário</small></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("stethoscope") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($doctorHint) .
                "</b><small>Profissional</small></span></div>";
            $form =
                '<section class="form-panel agenda-route-form agenda-quick-form-panel">' .
                $quickHeader .
                $quickSummary .
                '<form method="post" class="compact agenda-create-form agenda-quick-form" data-appointment-create-form>' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                $returnHiddenOperation .
                '<input type="hidden" name="act" value="create"><fieldset class="agenda-quick-section agenda-quick-time"><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                '<span>Horário</span></legend><div class="agenda-quick-time-grid">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Início",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "start_at",
                        "datetime-local",
                        $initialStart,
                        "required data-appointment-start",
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Fim",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "end_at",
                        "datetime-local",
                        $initialEnd,
                        "required data-appointment-end",
                    ),
                ) .
                '</div></fieldset><fieldset class="agenda-quick-section agenda-quick-main"><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_calendar") .
                '<span>Dados do agendamento</span></legend><div class="agenda-quick-grid">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                    \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for("medico", $cid),
                    "doctor_user_id",
                    $formDoctorOptions,
                    $initialDoctor ?: null,
                    "required",
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                    "Paciente",
                    "patient_link_id",
                    $pats,
                    $prePatientId ?: null,
                    "required",
                ) .
                \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_select_html($cid, "reason", "", true) .
                "</div></fieldset>" .
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::appointment_payment_form_html($cid) .
                '<fieldset class="agenda-quick-section agenda-quick-notes"><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("notes") .
                "<span>Observações</span></legend>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Observações importantes",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("notes", "text", "", 'placeholder="Opcional"'),
                ) .
                '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $cancelParams) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                '<span>Desistir</span></a><button type="submit" class="primary">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
                "<span>Confirmar</span></button></div></form></section>";
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                $createTitle,
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                    $createTitle,
                    $createSubtitle,
                    '<a class="ghost small cmdlike" href="' .
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $cancelParams) .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_view_day") .
                        "<span>Agenda</span></a>",
                ) . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($form, "agenda-route-card agenda-quick-route-card"),
            );
            return;
        }
        if ($operationMode === "note") {
            $noteDay = (string) ($_GET["note_date"] ?? $day);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDay)) {
                $noteDay = $day;
            }
            $cancelParams = $buildParams($noteDay, $agendaDoctor);
            $roleOptions = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_options($cid, false);
            $noteTitle = "Nova Anotação";
            $noteSubtitle =
                "Registre uma anotação para a data escolhida na Agenda, com visibilidade controlada.";
            $form = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations04::agenda_note_form_html($cid, $noteDay, $c, $roleOptions);
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                $noteTitle,
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                    $noteTitle,
                    $noteSubtitle,
                    '<a class="ghost small cmdlike" href="' .
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $cancelParams) .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_view_day") .
                        "<span>Agenda</span></a>",
                ) . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($form, "agenda-route-card agenda-note-route-card"),
            );
            return;
        }
        if ($operationMode === "block") {
            if (!in_array($role, ["recepcionista", "medico", "gerente"], true)) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Seu cargo não possui operação de bloqueio de horário.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("appointments", $buildParams());
            }
            $initialStart = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_normalize_local_input(
                (string) ($_GET["start"] ?? ""),
                $day,
                "08:00",
            );
            $initialDay = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_safe_day_from_local_input($initialStart, $day);
            $initialEnd = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_local_input_add_minutes($initialStart, 30);
            $initialDoctor =
                (int) ($_GET["doctor_user_id"] ??
                    ($_GET["doctor"] ?? ($selected ?: 0)));
            if ($initialDoctor <= 0 || !isset($formDoctorOptions[$initialDoctor])) {
                $initialDoctor = $selected ?: 0;
            }
            $blockDefault = $initialDoctor > 0 ? $initialDoctor : null;
            $blockOptions =
                $role === "gerente"
                    ? ["" => "Todo consultório"] + $formDoctorOptions
                    : $formDoctorOptions;
            $cancelParams = $buildParams($initialDay, $initialDoctor);
            $returnHiddenOperation =
                '<input type="hidden" name="return_day" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($initialDay) .
                '"><input type="hidden" name="return_doctor" value="' .
                (int) $initialDoctor .
                '">';
            $blockTitle = "Bloqueio de Horário";
            $blockSubtitle =
                "Reserve um intervalo da Agenda quando ele não puder receber pacientes.";
            $initialHour = substr($initialStart, 11, 5);
            $initialEndHour = substr($initialEnd, 11, 5);
            $blockDoctorHint =
                $initialDoctor > 0
                    ? (string) ($formDoctorOptions[$initialDoctor] ??
                        \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for("medico", $cid))
                    : "Todo consultório";
            $blockHeader =
                '<div class="agenda-quick-hero agenda-block-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_busy") .
                '</span><div class="agenda-quick-hero-copy"><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($blockTitle) .
                "</h2><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($blockSubtitle) .
                '</p></div><a class="ghost small agenda-quick-back" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $cancelParams) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Agenda</span></a></div>";
            $blockSummary =
                '<div class="agenda-quick-summary agenda-block-summary" aria-label="Resumo do bloqueio"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_month") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($initialDay)) .
                "</b><small>Data</small></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($initialHour . "–" . $initialEndHour) .
                "</b><small>Horário</small></span><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_busy") .
                "<b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($blockDoctorHint) .
                "</b><small>Escopo</small></span></div>";
            $form =
                '<section class="form-panel agenda-route-form agenda-block-form-panel">' .
                $blockHeader .
                $blockSummary .
                '<form method="post" class="compact agenda-block-form agenda-quick-form agenda-structured-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                $returnHiddenOperation .
                '<input type="hidden" name="act" value="block"><fieldset class="agenda-quick-section agenda-block-scope"><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_busy") .
                "<span>Escopo</span></legend>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                    "Agenda bloqueada",
                    "doctor_user_id",
                    $blockOptions,
                    $blockDefault,
                ) .
                '</fieldset><fieldset class="agenda-quick-section agenda-quick-time"><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                '<span>Horário</span></legend><div class="agenda-quick-time-grid">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Início",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("start_at", "datetime-local", $initialStart, "required"),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Fim",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("end_at", "datetime-local", $initialEnd, "required"),
                ) .
                '</div></fieldset><fieldset class="agenda-quick-section agenda-quick-notes"><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("notes") .
                "<span>Motivo</span></legend>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Motivo do bloqueio",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "reason",
                        "text",
                        "",
                        'required placeholder="Ex.: reunião, intervalo, procedimento externo"',
                    ),
                ) .
                '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $cancelParams) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                '<span>Desistir</span></a><button type="submit" class="primary">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_busy") .
                "<span>Salvar bloqueio</span></button></div></form></section>";
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                $blockTitle,
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                    $blockTitle,
                    $blockSubtitle,
                    '<a class="ghost small cmdlike" href="' .
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $cancelParams) .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_view_day") .
                        "<span>Agenda</span></a>",
                ) . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($form, "agenda-route-card agenda-block-route-card"),
            );
            return;
        }
        $headActions = "";
        if (in_array($role, ["recepcionista", "gerente"], true)) {
            $headActions .=
                '<a class="primary small cmdlike" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $buildParams() + ["mode" => "create"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
                "<span>Agendar consulta</span></a>";
        }
        if (in_array($role, ["recepcionista", "medico", "gerente"], true)) {
            $headActions .=
                '<a class="ghost small cmdlike" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $buildParams() + ["mode" => "block"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_busy") .
                "<span>Bloquear horário</span></a>";
        }
        $headActions .=
            '<a class="ghost small cmdlike" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href(
                "appointments",
                $buildParams() + ["mode" => "note", "note_date" => $day],
            ) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("sticky_note_2") .
            "<span>Anotação</span></a>";
        if ($role === "assistente") {
            $headActions .=
                '<a class="ghost small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("tasks") .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("task_alt") .
                "<span>Tarefas da triagem</span></a>";
        }
        $prev = date("Y-m-d", strtotime($day . " -1 day"));
        $next = date("Y-m-d", strtotime($day . " +1 day"));
        [$dayStart, $dayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($day, $cid, $c);
        $rowSql =
            "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,notes,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_status,payment_method,payment_amount_cents,payment_confirmed_at,revenue_id FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?";
        $rowParams = [$cid, $dayStart, $dayEnd];
        if ($agendaDoctor > 0) {
            $rowSql .= " AND doctor_user_id=?";
            $rowParams[] = $agendaDoctor;
        }
        $rowSql .= " ORDER BY start_at ASC LIMIT 180";
        $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q($rowSql, $rowParams)->fetchAll();
        $blockSql =
            "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at<? AND end_at>?";
        $blockParams = [$cid, $dayEnd, $dayStart];
        if ($agendaDoctor > 0) {
            $blockSql .= " AND (doctor_user_id IS NULL OR doctor_user_id=?)";
            $blockParams[] = $agendaDoctor;
        }
        $blockSql .= " ORDER BY start_at ASC LIMIT 100";
        $blocks = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q($blockSql, $blockParams)->fetchAll();
        $patientLinks = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::scoped_patient_map(
            $cid,
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "patient_link_id"),
            "id,person_id",
        );
        $persons = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "pi_persons",
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids(array_values($patientLinks), "person_id"),
            "id,full_name",
        );
        $doctorIds = array_values(
            array_unique(
                array_merge(
                    \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "doctor_user_id"),
                    \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($blocks, "doctor_user_id"),
                ),
            ),
        );
        $doctorMap = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::scoped_user_map($cid, $doctorIds, "id,name");
        $creators = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::scoped_user_map(
            $cid,
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($blocks, "created_by"),
            "id,name",
        );
        $nowTs = time();
        $isDone = function (array $a): bool {
    
            return in_array(
                \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a),
                ["atendimento_concluido", "finalizado"],
                true,
            );
        };
        $isStarted = function (array $a) use ($isDone): bool {
    
            return !$isDone($a) && \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a) === "em_atendimento";
        };
        $isArrived = function (array $a) use ($isDone, $isStarted): bool {
    
            return !$isDone($a) &&
                !$isStarted($a) &&
                in_array(
                    \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a),
                    ["chegou", "em_preparo", "pronto_atendimento"],
                    true,
                );
        };
        $isLate = function (array $a) use ($nowTs): bool {
    
            $start = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["start_at"] ?? "") ?: 0;
            return in_array(
                \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a),
                ["agendado", "confirmado"],
                true,
            ) &&
                $start > 0 &&
                $start < $nowTs;
        };
        $statusMeta = function (array $a) use ($role): array {
    
            $m = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_meta($a, $role);
            return [$m["label"], $m["class"], $m["icon"]];
        };
        $patientName = function (array $a) use ($patientLinks, $persons): string {
    
            $pid = (int) ($a["patient_link_id"] ?? 0);
            $pl = $patientLinks[$pid] ?? [];
            $ps = $persons[(int) ($pl["person_id"] ?? 0)] ?? [];
            return (string) ($ps["full_name"] ?? "Paciente não identificado");
        };
        $appointmentActions = function (array $r, string $mode = "dropdown") use (
            $c,
            $cid,
            $role,
            $uid,
            $returnHidden,
            $pats,
            $docs,
            $doctorMap,
            $statusMeta,
            $patientName,
        ): string {
    
            $pid = (int) ($r["patient_link_id"] ?? 0);
            $id = (int) ($r["id"] ?? 0);
            $journeyActions = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_role_actions(
                $r,
                $role,
                null,
                $uid,
            );
            $quickItems = "";
            if ($id > 0 && $journeyActions) {
                foreach ($journeyActions as $act) {
                    $class = (string) ($act["class"] ?? "primary");
                    $confirm = !empty($act["danger"])
                        ? ' onsubmit="return confirm(\'Registrar esta exceção na jornada do paciente?\')"'
                        : "";
                    $realAct = (string) $act["act"];
                    $extra = "";
                    if ($realAct === "no_show_quick") {
                        $realAct = "no_show";
                        $extra =
                            '<input type="hidden" name="no_show_reason" value="Paciente não compareceu no horário agendado.">';
                    }
                    $quickItems .=
                        '<form method="post" class="agenda-row-action-form"' .
                        $confirm .
                        ">" .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                        $returnHidden .
                        '<input type="hidden" name="act" value="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($realAct) .
                        '"><input type="hidden" name="id" value="' .
                        $id .
                        '">' .
                        $extra .
                        '<button type="submit" class="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($class) .
                        ' small">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon((string) $act["icon"]) .
                        "<span>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $act["label"]) .
                        "</span></button></form>";
                }
            }
            if ($mode === "quick") {
                if ($quickItems === "") {
                    return '<button type="button" class="ghost small cmdlike agenda-row-actions-disabled" data-agenda-row-actions disabled aria-disabled="true">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("bolt") .
                        "<span>Ações</span></button>";
                }
                $modalId = "agenda-actions-modal-" . $id;
                return '<span class="agenda-row-actions-wrap" data-agenda-row-actions><button type="button" class="ghost small cmdlike agenda-row-actions-open" data-agenda-actions-open="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($modalId) .
                    '" aria-haspopup="dialog" aria-controls="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($modalId) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("bolt") .
                    '<span>Ações</span></button><dialog class="agenda-actions-modal" id="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($modalId) .
                    '" data-agenda-actions-modal aria-label="Ações do atendimento"><div class="agenda-actions-modal-card"><header class="agenda-actions-modal-head"><span>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("bolt") .
                    '<b>Ações</b></span><button type="button" class="icon-btn" data-agenda-actions-close aria-label="Fechar ações">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                    '</button></header><div class="agenda-actions-modal-list">' .
                    $quickItems .
                    "</div></div></dialog></span>";
            }
            $journeyHtml = "";
            if ($id > 0 && $journeyActions) {
                $journeyHtml =
                    '<div class="agenda-action-section agenda-action-section-journey"><span class="agenda-action-section-title">Jornada do paciente</span><div class="agenda-action-list">';
                foreach ($journeyActions as $act) {
                    $class = (string) ($act["class"] ?? "primary");
                    $title = (string) ($act["title"] ?? $act["label"]);
                    $confirm = !empty($act["danger"])
                        ? ' onsubmit="return confirm(\'Registrar esta exceção na jornada do paciente?\')"'
                        : "";
                    $realAct = (string) $act["act"];
                    $extra = "";
                    if ($realAct === "no_show_quick") {
                        $realAct = "no_show";
                        $extra =
                            '<input type="hidden" name="no_show_reason" value="Paciente não compareceu no horário agendado.">';
                    }
                    $journeyHtml .=
                        '<form method="post" class="agenda-action-item agenda-action-item-form"' .
                        $confirm .
                        ">" .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                        $returnHidden .
                        '<input type="hidden" name="act" value="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($realAct) .
                        '"><input type="hidden" name="id" value="' .
                        $id .
                        '">' .
                        $extra .
                        '<button type="submit" class="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($class) .
                        ' small">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon((string) $act["icon"]) .
                        "<span>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $act["label"]) .
                        "</span></button><small>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
                        "</small></form>";
                }
                $journeyHtml .= "</div></div>";
            } else {
                $vm = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_view_model($r, $role);
                $journeyHtml =
                    '<div class="agenda-action-section agenda-action-section-journey"><span class="agenda-action-section-title">Jornada do paciente</span><div class="agenda-action-empty">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("info") .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                        (string) ($vm["action"] ??
                            "Nenhuma ação da jornada para este cargo agora."),
                    ) .
                    "</span></div></div>";
            }
            $secondary = "";
            $secondaryItems = "";
            if ($pid > 0) {
                $secondaryItems .=
                    '<a class="agenda-action-item agenda-action-link" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => $pid]) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("folder_open") .
                    "<span>Abrir ficha</span></a>";
            }
            $editGuard = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                $r,
                "edit",
                $role,
                $uid,
            );
            $cancelGuard = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                $r,
                "cancel",
                $role,
                $uid,
            );
            if ($editGuard === "") {
                $editForm =
                    '<form method="post" class="agenda-edit-form agenda-edit-form-nested">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="update_appointment"><input type="hidden" name="id" value="' .
                    $id .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                        \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for("medico", $cid),
                        "doctor_user_id",
                        $docs,
                        (int) $r["doctor_user_id"],
                        "required",
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                        "Paciente",
                        "patient_link_id",
                        $pats,
                        (int) $r["patient_link_id"],
                        "required",
                    ) .
                    \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_select_html(
                        $cid,
                        "reason",
                        (string) ($r["reason"] ?? ""),
                    ) .
                    '<div class="two">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Início",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "start_at",
                            "datetime-local",
                            \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::datetime_local_value((string) $r["start_at"]),
                            "required",
                        ),
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Fim",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "end_at",
                            "datetime-local",
                            \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::datetime_local_value((string) $r["end_at"]),
                            "required",
                        ),
                    ) .
                    "</div>" .
                    \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::appointment_payment_form_html($cid, $r) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Motivo da alteração",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "change_reason",
                            "text",
                            "",
                            'placeholder="Ex.: remarcação solicitada, ajuste administrativo"',
                        ),
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Notas",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "notes",
                            "text",
                            (string) ($r["notes"] ?? ""),
                            'placeholder="Opcional"',
                        ),
                    ) .
                    '<div class="form-actions"><button type="submit" class="primary small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_calendar") .
                    "<span>Salvar edição</span></button></div></form>";
                $deleteForm =
                    '<form method="post" class="agenda-delete-form compact agenda-delete-form-nested">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="delete_appointment"><input type="hidden" name="id" value="' .
                    $id .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Motivo da exclusão",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "cancel_reason",
                            "text",
                            "",
                            'required placeholder="Ex.: paciente desmarcou"',
                        ),
                    ) .
                    '<button type="submit" class="danger small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
                    "<span>Cancelar</span></button></form>";
                $secondaryItems .=
                    '<details class="agenda-action-disclosure"><summary class="agenda-action-item agenda-action-link">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_calendar") .
                    "<span>Editar</span></summary>" .
                    $editForm .
                    "</details>";
            } elseif (
                \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "recepcionista") ||
                \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "gerente")
            ) {
                $secondaryItems .=
                    '<span class="agenda-action-item agenda-action-disabled" title="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($editGuard) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_calendar") .
                    "<span>Editar bloqueado</span></span>";
            }
            if ($cancelGuard === "") {
                $deleteForm =
                    '<form method="post" class="agenda-delete-form compact agenda-delete-form-nested">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="delete_appointment"><input type="hidden" name="id" value="' .
                    $id .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Motivo da exclusão",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "cancel_reason",
                            "text",
                            "",
                            'required placeholder="Ex.: paciente desmarcou"',
                        ),
                    ) .
                    '<button type="submit" class="danger small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
                    "<span>Cancelar</span></button></form>";
                $secondaryItems .=
                    '<details class="agenda-action-disclosure"><summary class="agenda-action-item agenda-action-link danger-link">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
                    "<span>Cancelar</span></summary>" .
                    $deleteForm .
                    "</details>";
            } elseif (
                \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "recepcionista") ||
                \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "gerente")
            ) {
                $secondaryItems .=
                    '<span class="agenda-action-item agenda-action-disabled" title="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cancelGuard) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
                    "<span>Cancelar bloqueado</span></span>";
            }
            if ($secondaryItems !== "") {
                $secondary =
                    '<div class="agenda-action-section agenda-action-section-secondary"><span class="agenda-action-section-title">Outras ações</span><div class="agenda-action-list agenda-action-list-secondary">' .
                    $secondaryItems .
                    "</div></div>";
            }
            if ($mode === "secondary_panel") {
                return $secondary !== ""
                    ? '<div class="agenda-action-panel agenda-action-panel-journey agenda-action-panel-inline agenda-action-panel-secondary-only"><div class="agenda-action-title">Outras ações</div>' .
                            $secondary .
                            "</div>"
                    : "";
            }
            $panel =
                '<div class="agenda-action-title">Ações disponíveis</div>' .
                $journeyHtml .
                $secondary;
            if ($mode === "panel") {
                return '<div class="agenda-action-panel agenda-action-panel-journey agenda-action-panel-inline">' .
                    $panel .
                    "</div>";
            }
            return '<details class="agenda-item-actions compact-actions agenda-journey-actions"><summary class="ghost small cmdlike">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("more_horiz") .
                '<span>Ações</span></summary><div class="agenda-action-panel agenda-action-panel-journey">' .
                $panel .
                "</div></details>";
        };
        $appointmentRow = function (array $r, string $mode = "full") use (
            $role,
            $doctorMap,
            $statusMeta,
            $patientName,
            $appointmentActions,
            $returnHidden,
            $cid,
        ): string {
    
            $startTs = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["start_at"]);
            $endTs = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($r["end_at"]);
            [$label, $class, $ico] = $statusMeta($r);
            $doctor = $doctorMap[(int) ($r["doctor_user_id"] ?? 0)]["name"] ?? "";
            $patient = $patientName($r);
            $reason = mb_trim((string) ($r["reason"] ?? "")) ?: "Consulta";
            $period =
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $r["start_at"]) .
                "–" .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $r["end_at"]);
            $timeLabel = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $r["start_at"]);
            $meta = trim(
                ($doctor !== ""
                    ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($doctor)
                    : "Profissional não definido") .
                    " · " .
                    $reason,
                " ·",
            );
            $quickActions = $appointmentActions($r, "quick");
            $secondaryActions = $appointmentActions($r, "secondary_panel");
            $financialHtml = function_exists(
                "financial_appointment_operational_chip_html",
            )
                ? \Prontoo\Runtime\Financial\FinancialRuntimeOperations09::financial_appointment_operational_chip_html($cid, $r, $role)
                : "";
            return '<article class="agenda-profile-row agenda-profile-row-' .
                $class .
                ' journey-row agenda-row-toggle" data-agenda-row><div class="agenda-row-summary" data-agenda-row-summary role="button" tabindex="0" aria-expanded="false" title="Abrir jornada"><span class="agenda-profile-time" title="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($period) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($timeLabel) .
                '</span><span class="agenda-profile-main agenda-profile-main-compact"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($patient) .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($meta) .
                '</small></span><span class="pill ' .
                $class .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ico) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                "</span>" .
                $financialHtml .
                $quickActions .
                '<button type="button" class="agenda-row-chevron" data-agenda-row-toggle aria-label="Expandir ou recolher agendamento">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("expand_more") .
                '</button></div><div class="agenda-row-expanded" data-agenda-row-expanded hidden>' .
                \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::appointment_journey_measure_html($r, $role, "") .
                $secondaryActions .
                "</div></article>";
        };
        $blockRow = function (array $b) use (
            $role,
            $uid,
            $returnHidden,
            $docs,
            $doctorMap,
            $creators,
            $cid,
        ): string {
    
            $startTs = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($b["start_at"]);
            $endTs = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($b["end_at"]);
            $blockDoctor = !empty($b["doctor_user_id"])
                ? (int) $b["doctor_user_id"]
                : null;
            $doctorLabel = $blockDoctor
                ? "Agenda de " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name(
                        $doctorMap[$blockDoctor]["name"] ??
                            \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_doctor_name($cid, $blockDoctor),
                    )
                : "Todo consultório";
            $creator = $creators[(int) ($b["created_by"] ?? 0)]["name"] ?? "";
            $period =
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $b["start_at"]) .
                "–" .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $b["end_at"]);
            $timeLabel = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $b["start_at"]);
            $canManage =
                $role === "gerente" || (int) ($b["created_by"] ?? 0) === $uid;
            $actions = "";
            if ($canManage) {
                $blockOptions =
                    $role === "gerente"
                        ? ["" => "Todo consultório"] + $docs
                        : $docs;
                $editForm =
                    '<form method="post" class="agenda-edit-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="update_block"><input type="hidden" name="id" value="' .
                    (int) $b["id"] .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                        "Agenda bloqueada",
                        "doctor_user_id",
                        $blockOptions,
                        $blockDoctor,
                    ) .
                    '<div class="two">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Início",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "start_at",
                            "datetime-local",
                            \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::datetime_local_value((string) $b["start_at"]),
                            "required",
                        ),
                    ) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Fim",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "end_at",
                            "datetime-local",
                            \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::datetime_local_value((string) $b["end_at"]),
                            "required",
                        ),
                    ) .
                    "</div>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Motivo",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("reason", "text", (string) $b["reason"], "required"),
                    ) .
                    '<div class="form-actions"><button type="button" class="ghost small" data-close-panel>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                    '<span>Fechar</span></button><button type="submit" class="primary small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_calendar") .
                    "<span>Alterar</span></button></div></form>";
                $deleteForm =
                    '<form method="post" class="agenda-delete-form compact">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="delete_block"><input type="hidden" name="id" value="' .
                    (int) $b["id"] .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Motivo da exclusão",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "cancel_reason",
                            "text",
                            "",
                            'required placeholder="Ex.: bloqueio não será mais necessário"',
                        ),
                    ) .
                    '<button type="submit" class="danger small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
                    "<span>Remover bloqueio</span></button></form>";
                $actions =
                    '<details class="agenda-item-actions compact-actions"><summary class="ghost small cmdlike">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("more_horiz") .
                    '<span>Ações</span></summary><div class="agenda-action-panel"><div class="agenda-action-title">Ações do bloqueio</div>' .
                    $editForm .
                    $deleteForm .
                    "</div></details>";
            }
            return '<article class="agenda-profile-row agenda-profile-row-block"><span class="agenda-profile-time" title="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($period) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($timeLabel) .
                '</span><span class="agenda-profile-main"><strong>Horário bloqueado</strong><small>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    trim(
                        $doctorLabel .
                            " · " .
                            (string) $b["reason"] .
                            ($creator !== ""
                                ? " · por " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($creator)
                                : ""),
                        " ·",
                    ),
                ) .
                '</small></span><span class="pill bad">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_busy") .
                "Bloqueio</span>" .
                $actions .
                "</article>";
        };
        $total = count($rows);
        $arrived = 0;
        $late = 0;
        $started = 0;
        $done = 0;
        $upcoming = 0;
        $nextAppt = null;
        foreach ($rows as $a) {
            $start = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["start_at"]) ?: 0;
            if ($isDone($a)) {
                $done++;
            } elseif ($isStarted($a)) {
                $started++;
            } elseif ($isArrived($a)) {
                $arrived++;
            } elseif ($isLate($a)) {
                $late++;
            }
            if (!$isDone($a) && $start >= $nowTs) {
                $upcoming++;
                if ($nextAppt === null) {
                    $nextAppt = $a;
                }
            }
        }
        $freeSlots = [];
        $workRanges = [];
        if ($agendaDoctor > 0) {
            $workRanges = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_ranges_for_day($cid, $agendaDoctor, $day);
            $busy = [];
            foreach ($rows as $a) {
                $busy[] = [
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["start_at"]) ?: 0,
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["end_at"]) ?: 0,
                ];
            }
            foreach ($blocks as $b) {
                $busy[] = [
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($b["start_at"]) ?: 0,
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($b["end_at"]) ?: 0,
                ];
            }
            foreach ($workRanges as $range) {
                for ($t = $range[0]; $t + 1800 <= $range[1]; $t += 1800) {
                    $slotEnd = $t + 1800;
                    $ok = true;
                    foreach ($busy as $bi) {
                        if ($bi[0] < $slotEnd && $bi[1] > $t) {
                            $ok = false;
                            break;
                        }
                    }
                    if ($ok) {
                        $freeSlots[] = date("H:i", $t);
                        if (count($freeSlots) >= 6) {
                            break 2;
                        }
                    }
                }
            }
        }
        $localTs = function (string $dt) use ($cid): int {
    
            $d = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($dt, $cid);
            return $d ? $d->getTimestamp() : (strtotime($dt) ?: 0);
        };
        $nextLabel = $nextAppt ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $nextAppt["start_at"]) : "—";
        $workRanges =
            $agendaDoctor > 0
                ? \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_ranges_for_day($cid, $agendaDoctor, $day)
                : [];
        $zone = new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone(null, $cid));
        $fmtLocal = function (int $ts) use ($zone): string {
    
            return new DateTimeImmutable("@" . $ts)
                ->setTimezone($zone)
                ->format("H:i");
        };
        if (!$workRanges) {
            $fallbackStart = new DateTimeImmutable(
                $day . " 08:00:00",
                $zone,
            )->getTimestamp();
            $fallbackEnd = new DateTimeImmutable(
                $day . " 17:00:00",
                $zone,
            )->getTimestamp();
            $workRanges = [[$fallbackStart, $fallbackEnd, "08:00", "17:00"]];
        }
        $workStartTs = (int) $workRanges[0][0];
        $workEndTs = (int) $workRanges[count($workRanges) - 1][1];
        $workStartLabel = (string) $workRanges[0][2];
        $workEndLabel = (string) $workRanges[count($workRanges) - 1][3];
        $totalMinutes = max(15, (int) ceil(($workEndTs - $workStartTs) / 60));
        $slotMinutes = 15;
        $slotCount = max(1, (int) ceil($totalMinutes / $slotMinutes));
        $busyMinutes = 0;
        $busy = [];
        foreach ($rows as $a) {
            $sTs = max($workStartTs, $localTs((string) $a["start_at"]));
            $eTs = min($workEndTs, $localTs((string) $a["end_at"]));
            if ($eTs > $sTs) {
                $busyMinutes += (int) ceil(($eTs - $sTs) / 60);
                $busy[] = [$sTs, $eTs];
            }
        }
        foreach ($blocks as $b) {
            $sTs = max($workStartTs, $localTs((string) $b["start_at"]));
            $eTs = min($workEndTs, $localTs((string) $b["end_at"]));
            if ($eTs > $sTs) {
                $busy[] = [$sTs, $eTs];
            }
        }
        $occupancyPct = min(
            100,
            max(0, (int) round(($busyMinutes / max(1, $totalMinutes)) * 100, 0, \RoundingMode::HalfAwayFromZero)),
        );
        $nextFree = "—";
        $cursor = max($workStartTs, time());
        $cursor =
            $cursor +
            (($slotMinutes * 60 - ($cursor % ($slotMinutes * 60))) %
                ($slotMinutes * 60));
        for (
            $t = $cursor;
            $t + $slotMinutes * 60 <= $workEndTs;
            $t += $slotMinutes * 60
        ) {
            $slotEnd = $t + $slotMinutes * 60;
            $ok = true;
            foreach ($busy as $bi) {
                if ($bi[0] < $slotEnd && $bi[1] > $t) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $nextFree = $fmtLocal($t);
                break;
            }
        }
        $metricCard = function (
            string $icon,
            string $value,
            string $label,
            string $kind,
        ): string {
    
            return '<article class="agenda-floating-card agenda-floating-card-' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($kind) .
                '" aria-label="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                ": " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value) .
                '"><span class="agenda-floating-icon" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($icon) .
                '</span><div class="agenda-floating-copy"><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value) .
                '</b><span class="agenda-floating-label">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                "</span></div></article>";
        };
        $floating =
            '<aside class="agenda-floating-kpis ds-fixed-agenda-kpis" data-ds-fixed-layer="agenda-kpis" aria-label="Resumo da agenda">' .
            $metricCard(
                "conditions",
                (string) $total,
                "Consultas",
                "appointments",
            ) .
            $metricCard("timelapse", $occupancyPct . "%", "Ocupação", "occupancy") .
            $metricCard("acute", $nextFree, "Horário livre", "free-time") .
            "</aside>";
        $slotRows = "";
        $slotCanCreate = in_array($role, ["recepcionista", "gerente"], true);
        for ($i = 0; $i <= $slotCount; $i++) {
            $ts = $workStartTs + $i * $slotMinutes * 60;
            $slotStart = $day . "T" . $fmtLocal($ts);
            $slotClass = "agenda-day-slot " . ($i % 4 === 0 ? "is-hour" : "");
            if ($slotCanCreate) {
                $slotParams = $buildParams($day, $agendaDoctor);
                $slotParams["mode"] = "create";
                $slotParams["quick"] = "1";
                $slotParams["start"] = $slotStart;
                $slotRows .=
                    '<a class="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($slotClass) .
                    '" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $slotParams) .
                    '" style="--slot-index:' .
                    $i .
                    '" data-agenda-slot="1" data-start="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($slotStart) .
                    '" aria-label="Cadastrar agendamento às ' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($ts)) .
                    '"><span>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($ts)) .
                    "</span></a>";
            } else {
                $slotRows .=
                    '<div class="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($slotClass) .
                    '" style="--slot-index:' .
                    $i .
                    '" data-agenda-slot="1" data-start="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($slotStart) .
                    '"><span>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($ts)) .
                    "</span></div>";
            }
        }
        $eventHtml = "";
        foreach ($rows as $r) {
            $sTs = $localTs((string) $r["start_at"]);
            $eTs = $localTs((string) $r["end_at"]);
            if ($eTs <= $workStartTs || $sTs >= $workEndTs) {
                continue;
            }
            $top = max(0, ($sTs - $workStartTs) / 60 / $slotMinutes);
            $height = max(
                1,
                (min($eTs, $workEndTs) - max($sTs, $workStartTs)) /
                    60 /
                    $slotMinutes,
            );
            [$label, $class, $ico] = $statusMeta($r);
            $patient = $patientName($r);
            $doctor = $doctorMap[(int) ($r["doctor_user_id"] ?? 0)]["name"] ?? "";
            $reason = mb_trim((string) ($r["reason"] ?? "")) ?: "Consulta";
            $period =
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $r["start_at"]) .
                "–" .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $r["end_at"]);
            $eventHtml .=
                '<article class="agenda-day-event agenda-day-event-' .
                $class .
                '" style="--event-top:' .
                $top .
                ";--event-height:" .
                $height .
                '" title="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($period . " · " . $patient) .
                '">' .
                '<span class="agenda-day-event-time">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($period) .
                "</span><strong>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($patient) .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    trim(
                        ($doctor !== "" ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($doctor) : "Profissional") .
                            " · " .
                            $reason,
                        " ·",
                    ),
                ) .
                "</small><em>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ico) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                "</em></article>";
        }
        foreach ($blocks as $b) {
            $sTs = $localTs((string) $b["start_at"]);
            $eTs = $localTs((string) $b["end_at"]);
            if ($eTs <= $workStartTs || $sTs >= $workEndTs) {
                continue;
            }
            $top = max(0, ($sTs - $workStartTs) / 60 / $slotMinutes);
            $height = max(
                1,
                (min($eTs, $workEndTs) - max($sTs, $workStartTs)) /
                    60 /
                    $slotMinutes,
            );
            $eventHtml .=
                '<article class="agenda-day-event agenda-day-event-block" style="--event-top:' .
                $top .
                ";--event-height:" .
                $height .
                '" title="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $b["reason"]) .
                '">' .
                '<span class="agenda-day-event-time">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $b["start_at"]) .
                        "–" .
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $b["end_at"]),
                ) .
                "</span><strong>Bloqueado</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $b["reason"]) .
                "</small><em>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_busy") .
                "Bloqueio</em></article>";
        }
        if ($eventHtml === "") {
            $eventHtml =
                '<div class="agenda-day-empty agenda-day-empty-icon" role="img" aria-label="Sem agendamentos no expediente" title="Sem agendamentos no expediente">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("no_sim") .
                "</div>";
        }
        $doctorFilter = "";
        $navPrev = date("Y-m-d", strtotime($day . " -1 day"));
        $navNext = date("Y-m-d", strtotime($day . " +1 day"));
        if ($agendaView === "semanal") {
            $navPrev = date("Y-m-d", strtotime($day . " -7 days"));
            $navNext = date("Y-m-d", strtotime($day . " +7 days"));
        } elseif ($agendaView === "mensal") {
            $navPrev = date("Y-m-d", strtotime($day . " -1 month"));
            $navNext = date("Y-m-d", strtotime($day . " +1 month"));
        }
        $navDoctorOptions = "";
        foreach ($docs as $id => $name) {
            if ($role === "medico" && (int) $id !== $uid) {
                continue;
            }
            $label = \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::agenda_crown_label_for_view($agendaView, $day, (string) $name);
            $navDoctorOptions .=
                '<option value="' .
                (int) $id .
                '" ' .
                ((int) $id === $agendaDoctor ? "selected" : "") .
                ">" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                "</option>";
        }
        $navDay =
            '<form method="get" class="agenda-crown-picker" aria-label="Selecionar dia e profissional">' .
            '<input type="hidden" name="r" value="appointments">' .
            '<input type="hidden" name="d" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($day) .
            '">' .
            ($agendaView !== "diario"
                ? '<input type="hidden" name="view" value="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($agendaView) . '">'
                : "") .
            '<a class="agenda-crown-arrow" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href(
                "appointments",
                $buildParams($navPrev, $agendaDoctor, $agendaView),
            ) .
            '" aria-label="Voltar">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("chevron_left") .
            "</a>" .
            '<select name="doctor" onchange="this.form.submit()" aria-label="Dia e profissional">' .
            $navDoctorOptions .
            "</select>" .
            '<a class="agenda-crown-arrow" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href(
                "appointments",
                $buildParams($navNext, $agendaDoctor, $agendaView),
            ) .
            '" aria-label="Avançar">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("chevron_right") .
            "</a>" .
            "</form>";
        $title = "Agenda";
        $subtitle = "";
        $agendaCreateUrl = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href(
            "appointments",
            $buildParams($day, $agendaDoctor, "diario") + ["mode" => "create"],
        );
        $agendaDayNote = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_note_card_html(
            \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_note_visible_for_day($cid, $day, $c),
            $c,
        );
        $agendaDay =
            $agendaDayNote .
            '<section class="agenda-day-shell agenda-crown-shell" style="--agenda-slots:' .
            $slotCount .
            ';--agenda-day-date:\'' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($day) .
            '\'" data-agenda-day="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($day) .
            '" data-slot-minutes="' .
            $slotMinutes .
            '" data-work-start="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($workStartTs)) .
            '" data-work-end="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($workEndTs)) .
            '">' .
            $floating .
            '<div class="agenda-day-scale" aria-hidden="true">' .
            $slotRows .
            '</div><div class="agenda-day-canvas" data-agenda-canvas data-slot-minutes="' .
            $slotMinutes .
            '" data-day="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($day) .
            '" data-work-start="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($workStartTs)) .
            '" data-agenda-create-url="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($agendaCreateUrl) .
            '">' .
            $eventHtml .
            "</div></section>";
        $renderRows = "";
        foreach ($rows as $r) {
            $renderRows .= $appointmentRow($r);
        }
        foreach ($blocks as $b) {
            $renderRows .= $blockRow($b);
        }
        $agendaSummary =
            '<section class="agenda-summary-view">' .
            $floating .
            '<div class="agenda-summary-grid"><article class="agenda-summary-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($total) .
            '</b><span>consultas no dia</span></article><article class="agenda-summary-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("how_to_reg") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($arrived) .
            '</b><span>chegaram</span></article><article class="agenda-summary-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("stethoscope") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($started) .
            '</b><span>em atendimento</span></article><article class="agenda-summary-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("task_alt") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($done) .
            '</b><span>finalizadas</span></article></div><div class="agenda-summary-list agenda-day-vertical-timeline">' .
            ($renderRows !== ""
                ? $renderRows
                : '<div class="empty">Nenhum agendamento ou bloqueio neste dia.</div>') .
            "</div></section>";
        $renderAgendaCanvas = function (
            string $renderDay,
            array $dayRows,
            array $dayBlocks,
            array $patientLinksMap,
            array $personsMap,
            array $doctorMapLocal,
        ) use (
            $cid,
            $role,
            $agendaDoctor,
            $buildParams,
            $slotMinutes,
            $statusMeta,
            $localTs,
            $fmtLocal,
        ): string {
    
            $ranges =
                $agendaDoctor > 0
                    ? \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_ranges_for_day($cid, $agendaDoctor, $renderDay)
                    : [];
            $zone = new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone(null, $cid));
            if (!$ranges) {
                $fallbackStart = new DateTimeImmutable(
                    $renderDay . " 08:00:00",
                    $zone,
                )->getTimestamp();
                $fallbackEnd = new DateTimeImmutable(
                    $renderDay . " 17:00:00",
                    $zone,
                )->getTimestamp();
                $ranges = [[$fallbackStart, $fallbackEnd, "08:00", "17:00"]];
            }
            $startTs = (int) $ranges[0][0];
            $endTs = (int) $ranges[count($ranges) - 1][1];
            $totalMinutes = max(15, (int) ceil(($endTs - $startTs) / 60));
            $slotCount = max(1, (int) ceil($totalMinutes / $slotMinutes));
            $slotRows = "";
            $canCreate = in_array($role, ["recepcionista", "gerente"], true);
            for ($i = 0; $i <= $slotCount; $i++) {
                $ts = $startTs + $i * $slotMinutes * 60;
                $slotStart = $renderDay . "T" . $fmtLocal($ts);
                $slotClass = "agenda-day-slot " . ($i % 4 === 0 ? "is-hour" : "");
                if ($canCreate) {
                    $slotParams = $buildParams($renderDay, $agendaDoctor, "diario");
                    $slotParams["mode"] = "create";
                    $slotParams["start"] = $slotStart;
                    $slotRows .=
                        '<a class="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($slotClass) .
                        '" href="' .
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $slotParams) .
                        '" style="--slot-index:' .
                        $i .
                        '" data-agenda-slot="1" data-start="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($slotStart) .
                        '" aria-label="Cadastrar agendamento às ' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($ts)) .
                        '"><span>' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($ts)) .
                        "</span></a>";
                } else {
                    $slotRows .=
                        '<div class="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($slotClass) .
                        '" style="--slot-index:' .
                        $i .
                        '" data-agenda-slot="1" data-start="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($slotStart) .
                        '"><span>' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($ts)) .
                        "</span></div>";
                }
            }
            $patientNameLocal = function (array $a) use (
                $patientLinksMap,
                $personsMap,
            ): string {
    
                $pid = (int) ($a["patient_link_id"] ?? 0);
                $pl = $patientLinksMap[$pid] ?? [];
                $ps = $personsMap[(int) ($pl["person_id"] ?? 0)] ?? [];
                return (string) ($ps["full_name"] ?? "Paciente não identificado");
            };
            $eventHtml = "";
            foreach ($dayRows as $r) {
                $sTs = $localTs((string) $r["start_at"]);
                $eTs = $localTs((string) $r["end_at"]);
                if ($eTs <= $startTs || $sTs >= $endTs) {
                    continue;
                }
                $top = max(0, ($sTs - $startTs) / 60 / $slotMinutes);
                $height = max(
                    1,
                    (min($eTs, $endTs) - max($sTs, $startTs)) / 60 / $slotMinutes,
                );
                [$label, $class, $ico] = $statusMeta($r);
                $patient = $patientNameLocal($r);
                $doctor =
                    $doctorMapLocal[(int) ($r["doctor_user_id"] ?? 0)]["name"] ??
                    "";
                $reason = mb_trim((string) ($r["reason"] ?? "")) ?: "Consulta";
                $period =
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $r["start_at"]) .
                    "–" .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $r["end_at"]);
                $eventHtml .=
                    '<article class="agenda-day-event agenda-day-event-' .
                    $class .
                    '" style="--event-top:' .
                    $top .
                    ";--event-height:" .
                    $height .
                    '" title="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($period . " · " . $patient) .
                    '">' .
                    '<span class="agenda-day-event-time">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($period) .
                    "</span><strong>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($patient) .
                    "</strong><small>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                        trim(
                            ($doctor !== ""
                                ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($doctor)
                                : "Profissional") .
                                " · " .
                                $reason,
                            " ·",
                        ),
                    ) .
                    "</small><em>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ico) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                    "</em></article>";
            }
            foreach ($dayBlocks as $b) {
                $sTs = $localTs((string) $b["start_at"]);
                $eTs = $localTs((string) $b["end_at"]);
                if ($eTs <= $startTs || $sTs >= $endTs) {
                    continue;
                }
                $top = max(0, ($sTs - $startTs) / 60 / $slotMinutes);
                $height = max(
                    1,
                    (min($eTs, $endTs) - max($sTs, $startTs)) / 60 / $slotMinutes,
                );
                $eventHtml .=
                    '<article class="agenda-day-event agenda-day-event-block" style="--event-top:' .
                    $top .
                    ";--event-height:" .
                    $height .
                    '" title="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $b["reason"]) .
                    '">' .
                    '<span class="agenda-day-event-time">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $b["start_at"]) .
                            "–" .
                            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $b["end_at"]),
                    ) .
                    "</span><strong>Bloqueado</strong><small>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $b["reason"]) .
                    "</small><em>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_busy") .
                    "Bloqueio</em></article>";
            }
            if ($eventHtml === "") {
                $eventHtml =
                    '<div class="agenda-day-empty agenda-day-empty-icon" role="img" aria-label="Sem agendamentos no expediente" title="Sem agendamentos no expediente">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("no_sim") .
                    "</div>";
            }
            $createUrl = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href(
                "appointments",
                $buildParams($renderDay, $agendaDoctor, "diario") + [
                    "mode" => "create",
                    "quick" => "1",
                ],
            );
            return '<section class="agenda-day-shell agenda-crown-shell agenda-week-day-shell" style="--agenda-slots:' .
                $slotCount .
                ';--agenda-day-date:\'' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($renderDay) .
                '\'" data-agenda-day="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($renderDay) .
                '" data-slot-minutes="' .
                $slotMinutes .
                '" data-work-start="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($startTs)) .
                '" data-work-end="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($endTs)) .
                '"><div class="agenda-day-scale" aria-hidden="true">' .
                $slotRows .
                '</div><div class="agenda-day-canvas" data-agenda-canvas data-slot-minutes="' .
                $slotMinutes .
                '" data-day="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($renderDay) .
                '" data-work-start="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fmtLocal($startTs)) .
                '" data-agenda-create-url="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($createUrl) .
                '">' .
                $eventHtml .
                "</div></section>";
        };
        $groupByDay = function (array $items) use ($cid): array {
    
            $out = [];
            foreach ($items as $it) {
                $dt = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local((string) ($it["start_at"] ?? ""), $cid);
                $key = $dt
                    ? $dt->format("Y-m-d")
                    : substr((string) ($it["start_at"] ?? ""), 0, 10);
                if ($key === "") {
                    continue;
                }
                $out[$key][] = $it;
            }
            return $out;
        };
        $monthLabels = ["SEG", "TER", "QUA", "QUI", "SEX", "SAB", "DOM"];
        $agendaWeek = "";
        if ($agendaView === "semanal") {
            $weekStart = date(
                "Y-m-d",
                strtotime($day . " -" . (int) date("w", strtotime($day)) . " days"),
            );
            $weekDays = [];
            for ($i = 0; $i < 7; $i++) {
                $weekDays[] = date(
                    "Y-m-d",
                    strtotime($weekStart . " +" . $i . " days"),
                );
            }
            [$weekRangeStart] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($weekDays[0], $cid, $c);
            [, $weekRangeEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($weekDays[6], $cid, $c);
            $weekSql =
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,notes,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_status,payment_method,payment_amount_cents,payment_confirmed_at,revenue_id FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?";
            $weekParams = [$cid, $weekRangeStart, $weekRangeEnd];
            if ($agendaDoctor > 0) {
                $weekSql .= " AND doctor_user_id=?";
                $weekParams[] = $agendaDoctor;
            }
            $weekSql .= " ORDER BY start_at ASC LIMIT 600";
            $weekRows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q($weekSql, $weekParams)->fetchAll();
            $weekBlockSql =
                "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at<? AND end_at>?";
            $weekBlockParams = [$cid, $weekRangeEnd, $weekRangeStart];
            if ($agendaDoctor > 0) {
                $weekBlockSql .=
                    " AND (doctor_user_id IS NULL OR doctor_user_id=?)";
                $weekBlockParams[] = $agendaDoctor;
            }
            $weekBlockSql .= " ORDER BY start_at ASC LIMIT 300";
            $weekBlocks = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q($weekBlockSql, $weekBlockParams)->fetchAll();
            $weekGroupedRows = $groupByDay($weekRows);
            $weekGroupedBlocks = $groupByDay($weekBlocks);
            $weekPatientLinks = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::scoped_patient_map(
                $cid,
                \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($weekRows, "patient_link_id"),
                "id,person_id",
            );
            $weekPersons = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
                "pi_persons",
                \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids(array_values($weekPatientLinks), "person_id"),
                "id,full_name",
            );
            $weekDoctorMap = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::scoped_user_map(
                $cid,
                array_values(
                    array_unique(
                        array_merge(
                            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($weekRows, "doctor_user_id"),
                            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($weekBlocks, "doctor_user_id"),
                        ),
                    ),
                ),
                "id,name",
            );
            $weekLabels = ["DOM", "SEG", "TER", "QUA", "QUI", "SEX", "SAB"];
            $agendaWeek =
                $floating .
                '<section class="agenda-week-view" aria-label="Agenda semanal"><div class="agenda-week-grid">';
            foreach ($weekDays as $i => $wd) {
                $agendaWeek .=
                    '<article class="agenda-week-column"><header><b>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($weekLabels[$i]) .
                    "</b><span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(date("d/m", strtotime($wd))) .
                    "</span></header>" .
                    $renderAgendaCanvas(
                        $wd,
                        $weekGroupedRows[$wd] ?? [],
                        $weekGroupedBlocks[$wd] ?? [],
                        $weekPatientLinks,
                        $weekPersons,
                        $weekDoctorMap,
                    ) .
                    "</article>";
            }
            $agendaWeek .= "</div></section>";
        }
        $agendaMonth = "";
        if ($agendaView === "mensal") {
            $monthFirst = date("Y-m-01", strtotime($day));
            $monthLast = date("Y-m-t", strtotime($day));
            [$monthRangeStart] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($monthFirst, $cid, $c);
            [, $monthRangeEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($monthLast, $cid, $c);
            $monthSql =
                "SELECT id,doctor_user_id,start_at,end_at,status FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?";
            $monthParams = [$cid, $monthRangeStart, $monthRangeEnd];
            if ($agendaDoctor > 0) {
                $monthSql .= " AND doctor_user_id=?";
                $monthParams[] = $agendaDoctor;
            }
            $monthSql .= " ORDER BY start_at ASC LIMIT 1500";
            $monthRows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q($monthSql, $monthParams)->fetchAll();
            $monthGrouped = $groupByDay($monthRows);
            $daysInMonth = (int) date("t", strtotime($monthFirst));
            $firstW = ((int) date("w", strtotime($monthFirst)) + 6) % 7;
            $agendaMonth =
                '<section class="agenda-month-view" aria-label="Agenda mensal"><div class="agenda-month-weekdays">';
            foreach ($monthLabels as $lab) {
                $agendaMonth .= "<span>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($lab) . "</span>";
            }
            $agendaMonth .= '</div><div class="agenda-month-grid">';
            for ($i = 0; $i < $firstW; $i++) {
                $agendaMonth .=
                    '<span class="agenda-month-blank" aria-hidden="true"></span>';
            }
            for ($dnum = 1; $dnum <= $daysInMonth; $dnum++) {
                $dstr = date(
                    "Y-m-d",
                    strtotime($monthFirst . " +" . ($dnum - 1) . " days"),
                );
                $ranges =
                    $agendaDoctor > 0
                        ? \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_ranges_for_day($cid, $agendaDoctor, $dstr)
                        : [];
                if (!$ranges) {
                    $totalM = 9 * 60;
                } else {
                    $totalM = 0;
                    foreach ($ranges as $rg) {
                        $totalM += max(
                            0,
                            (int) ceil(((int) $rg[1] - (int) $rg[0]) / 60),
                        );
                    }
                }
                $busyM = 0;
                foreach ($monthGrouped[$dstr] ?? [] as $mr) {
                    $sTs = $localTs((string) $mr["start_at"]);
                    $eTs = $localTs((string) $mr["end_at"]);
                    if ($eTs > $sTs) {
                        $busyM += (int) ceil(($eTs - $sTs) / 60);
                    }
                }
                $pct = min(
                    100,
                    max(0, (int) round(($busyM / max(1, $totalM)) * 100, 0, \RoundingMode::HalfAwayFromZero)),
                );
                $fillPct = min(100, max(0, $pct));
                $isToday = $dstr === \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c);
                $agendaMonth .=
                    '<a class="agenda-month-day' .
                    ($isToday ? " is-today" : "") .
                    '" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href(
                        "appointments",
                        $buildParams($dstr, $agendaDoctor, "diario"),
                    ) .
                    '" style="--agenda-month-fill:' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $fillPct) .
                    '%" data-occupancy="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $pct) .
                    '" aria-label="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($dstr) . " · " . $pct . "% de ocupação") .
                    '"><span class="agenda-month-number">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $dnum) .
                    '</span><span class="agenda-month-occupancy-value">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($pct . "%") .
                    "</span></a>";
            }
            $agendaMonth .= "</div></section>";
        }
        $agendaContent = match ($agendaView) {
            "resumo" => $agendaSummary,
            "semanal" => $agendaWeek,
            "mensal" => $agendaMonth,
            default => $agendaDay,
        };
        $body =
            '<div class="agenda-profile agenda-day-first agenda-crown-page agenda-view-' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($agendaView) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                $doctorFilter . $navDay,
                "agenda-card agenda-nav-card agenda-crown-nav-card",
            ) .
            $agendaContent .
            "</div>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Agenda", \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head($title, $subtitle, $headActions) . $body);
    
    }
}
