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
    
        $c = require_can("appointments");
        $cid = (int) $c["clinic_id"];
        agenda_notes_ensure_schema($cid);
        $docs = doctors($cid);
        $pats = patient_options($cid);
        $prePatientId = (int) ($_GET["patient_id"] ?? 0);
        if ($prePatientId > 0 && !isset($pats[$prePatientId])) {
            $prePatientId = 0;
        }
        $procedures = procedure_options($cid, true);
        $day = (string) ($_GET["d"] ?? app_today_in_timezone($cid, $c));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = app_today_in_timezone($cid, $c);
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
    
            redirect("appointments", $returnParams($targetDay, $targetDoctor));
        };
        $role = (string) ($c["role"] ?? "");
        $uid = (int) ($c["user"]["id"] ?? 0);
        $returnHidden =
            '<input type="hidden" name="return_day" value="' .
            e($day) .
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
                        ? one(
                            "SELECT id,note_date,created_by FROM pi_agenda_notes WHERE id=? AND clinic_id=? AND deleted_at IS NULL LIMIT 1",
                            [$noteId, $cid],
                        )
                        : null;
                if (!$note) {
                    flash("Anotação não encontrada ou já excluída.", "bad");
                    $postRedirect();
                }
                $noteDay = (string) ($note["note_date"] ?? $postDay);
                $creator = (int) ($note["created_by"] ?? 0);
                if ($creator <= 0 || $creator !== $uid) {
                    flash("Somente quem criou a anotação pode excluí-la.", "bad");
                    $redirectBack($noteDay, $postDoctor);
                }
                q(
                    "UPDATE pi_agenda_notes SET deleted_at=NOW(), deleted_by=?, updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND deleted_at IS NULL AND created_by=?",
                    [$uid, $uid, $noteId, $cid, $uid],
                );
                audit("agenda_anotacao_excluida", "agenda_note", $noteId, [
                    "note_date" => $noteDay,
                    "audit_body" =>
                        "Anotação da Agenda excluída logicamente pelo usuário que a criou.",
                ]);
                flash("Anotação excluída.");
                $redirectBack($noteDay, $postDoctor);
            }
            if ($act === "agenda_note") {
                $noteDate = (string) ($_POST["note_date"] ?? $postDay);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDate)) {
                    $noteDate = $postDay;
                }
                $content = mb_trim((string) ($_POST["content"] ?? ""));
                if ($content === "") {
                    flash("Informe o conteúdo da anotação.", "bad");
                    $postRedirect();
                }
                $content = mb_substr($content, 0, 4000);
                $visibility = (string) ($_POST["visibility_scope"] ?? "my_role");
                $roleOptions = clinic_role_options($cid, false);
                $targetScope = "clinic";
                $targetRole = null;
                if ($visibility === "clinic") {
                    $targetScope = "clinic";
                    $targetRole = null;
                } else {
                    $targetScope = "role";
                    $targetRole = (string) ($c["role"] ?? "");
                    if ($targetRole === "" || !isset($roleOptions[$targetRole])) {
                        flash(
                            "Não foi possível identificar seu cargo para restringir a anotação.",
                            "bad",
                        );
                        $postRedirect();
                    }
                }
                q(
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
                audit(
                    "agenda_anotacao_criada",
                    "agenda_note",
                    db_last_insert_id(),
                    [
                        "note_date" => $noteDate,
                        "target_scope" => $targetScope,
                        "target_role" => $targetRole,
                    ],
                );
                flash("Anotação registrada para a data escolhida.");
                $redirectBack($noteDate, $postDoctor);
            }
            if ($act === "confirm") {
                $id = (int) ($_POST["id"] ?? 0);
                $a = one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                $guard = $a
                    ? appointment_journey_hard_guard_message(
                        $a,
                        "confirm",
                        $role,
                        $uid,
                    )
                    : "Agendamento não encontrado.";
                if ($guard === "") {
                    $stmt = q(
                        "UPDATE pi_appointments SET status='confirmado', updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    audit("consulta_confirmada", "consulta", $id, [
                        "patient_link_id" => (int) ($a["patient_link_id"] ?? 0),
                        "audit_body" =>
                            "Presença do paciente confirmada pela Recepção. Próxima medida: registrar chegada quando ele estiver no consultório.",
                    ]);
                    flash(
                        "Presença confirmada. Próxima medida: registrar chegada no horário.",
                    );
                } else {
                    flash($guard, "bad");
                }
                $postRedirect();
            }
            if ($act === "arrived") {
                $id = (int) ($_POST["id"] ?? 0);
                $a = one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                $guard = $a
                    ? appointment_journey_hard_guard_message(
                        $a,
                        "arrived",
                        $role,
                        $uid,
                    )
                    : "Agendamento não encontrado.";
                if ($guard === "") {
                    $stmt = q(
                        "UPDATE pi_appointments SET status='chegou', arrived_at=COALESCE(arrived_at,NOW()), updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    workflow_on_patient_arrived(
                        $cid,
                        $id,
                        (int) ($a["patient_link_id"] ?? 0),
                    );
                    audit("paciente_chegou", "consulta", $id, [
                        "patient_link_id" => (int) ($a["patient_link_id"] ?? 0),
                        "audit_body" =>
                            "Paciente chegou ao consultório. A equipe de triagem recebeu tarefa automática para preparar o atendimento.",
                    ]);
                    flash(
                        "Chegada registrada. Próxima medida: Assistente inicia preparo/triagem.",
                    );
                } else {
                    flash($guard, "bad");
                }
                $postRedirect();
            }
            if ($act === "no_show") {
                $id = (int) ($_POST["id"] ?? 0);
                $reason = mb_trim((string) ($_POST["no_show_reason"] ?? ""));
                $a = one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                if (!$a) {
                    flash("Agendamento não encontrado.", "bad");
                    $postRedirect();
                }
                $guard = appointment_journey_hard_guard_message(
                    $a,
                    "no_show",
                    $role,
                    $uid,
                );
                if ($guard !== "") {
                    flash($guard, "bad");
                    $postRedirect();
                }
                $stmt = q(
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
                    flash(
                        "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                if (function_exists("financial_cancel_appointment_revenue")) {
                    financial_cancel_appointment_revenue(
                        $cid,
                        $id,
                        $uid,
                        $reason !== ""
                            ? $reason
                            : "Paciente não compareceu no horário agendado.",
                    );
                }
                audit("paciente_nao_compareceu", "consulta", $id, [
                    "patient_link_id" => (int) ($a["patient_link_id"] ?? 0),
                    "motivo" => $reason,
                    "audit_body" =>
                        "Ausência registrada. Próxima medida: Recepção decide contato, orientação ou remarcação.",
                ]);
                flash(
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
                $a = one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at,payment_status,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                if (!$a) {
                    flash("Agendamento não encontrado.", "bad");
                    $postRedirect();
                }
                $code = appointment_status_code($a);
                $patientId = (int) ($a["patient_link_id"] ?? 0);
                $uidNow = (int) ($c["user"]["id"] ?? 0);
                if ($act === "start_prepare") {
                    $guard = appointment_journey_hard_guard_message(
                        $a,
                        "start_prepare",
                        $role,
                        $uidNow,
                    );
                    if ($guard !== "") {
                        flash($guard, "bad");
                        $postRedirect();
                    }
                    $stmt = q(
                        "UPDATE pi_appointments SET status='em_preparo', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='chegou' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    q(
                        "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='em_andamento', t.started_by=COALESCE(t.started_by,?), t.started_at=COALESCE(t.started_at,NOW()), t.assigned_to=COALESCE(t.assigned_to,?), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='paciente_chegou' AND t.status IN ('aberta','aguardando')",
                        [$uidNow, $uidNow, $cid, $id],
                    );
                    audit("preparo_iniciado_atalho", "consulta", $id, [
                        "patient_link_id" => $patientId,
                        "audit_body" =>
                            "Assistente iniciou o preparo/triagem pelo atalho visual da jornada.",
                    ]);
                    flash(
                        "Preparo iniciado. Próxima medida: concluir preparo e liberar ao profissional.",
                    );
                    $postRedirect();
                }
                if ($act === "finish_prepare") {
                    $guard = appointment_journey_hard_guard_message(
                        $a,
                        "finish_prepare",
                        $role,
                        $uidNow,
                    );
                    if ($guard !== "") {
                        flash($guard, "bad");
                        $postRedirect();
                    }
                    $stmt = q(
                        "UPDATE pi_appointments SET status='pronto_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='em_preparo' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    q(
                        "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='paciente_chegou' AND t.status IN ('aberta','em_andamento','aguardando')",
                        [$uidNow, $cid, $id],
                    );
                    if (function_exists("create_workflow_task")) {
                        $name = $patientId
                            ? patient_display_name($patientId, $cid)
                            : "paciente";
                        $doctor =
                            (int) (val(
                                "SELECT doctor_user_id FROM pi_appointments WHERE id=? AND clinic_id=?",
                                [$id, $cid],
                            ) ?:
                            0);
                        create_workflow_task(
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
                    audit("preparo_concluido_atalho", "consulta", $id, [
                        "patient_link_id" => $patientId,
                        "audit_body" =>
                            "Assistente liberou o paciente para atendimento pelo atalho visual da jornada.",
                    ]);
                    flash(
                        "Paciente liberado. Próxima medida: Profissional inicia atendimento.",
                    );
                    $postRedirect();
                }
                if ($act === "start_consultation") {
                    $guard = appointment_journey_hard_guard_message(
                        $a,
                        "start_consultation",
                        $role,
                        $uidNow,
                    );
                    if ($guard !== "") {
                        flash($guard, "bad");
                        $postRedirect();
                    }
                    $stmt = q(
                        "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), status='em_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='pronto_atendimento' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL AND (doctor_user_id IS NULL OR doctor_user_id=?)",
                        [$id, $cid, $uidNow],
                    );
                    if ($stmt->rowCount() <= 0) {
                        flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    q(
                        "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='em_andamento', t.started_by=COALESCE(t.started_by,?), t.started_at=COALESCE(t.started_at,NOW()), t.assigned_to=COALESCE(t.assigned_to,?), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','aguardando')",
                        [$uidNow, $uidNow, $cid, $id],
                    );
                    audit("consulta_iniciada_atalho", "consulta", $id, [
                        "patient_link_id" => $patientId,
                        "audit_body" =>
                            "Profissional iniciou o atendimento pelo atalho visual da jornada.",
                    ]);
                    flash(
                        "Atendimento iniciado. A Ficha do Paciente ficará aberta durante o atendimento.",
                    );
                    if ($patientId > 0) {
                        redirect("patient", [
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
                    $guard = appointment_journey_hard_guard_message(
                        $a,
                        "finish_consultation",
                        $role,
                        $uidNow,
                    );
                    if ($guard !== "") {
                        flash($guard, "bad");
                        $postRedirect();
                    }
                    if (function_exists("workflow_on_appointment_finished")) {
                        workflow_on_appointment_finished(
                            $cid,
                            $id,
                            $patientId,
                            $uidNow,
                            "atalho_jornada",
                        );
                    } else {
                        $stmt = q(
                            "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), consultation_finished_at=COALESCE(consultation_finished_at,NOW()), status='atendimento_concluido', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='em_atendimento' AND consultation_finished_at IS NULL",
                            [$id, $cid],
                        );
                        if ($stmt->rowCount() <= 0) {
                            flash(
                                "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                                "bad",
                            );
                            $postRedirect();
                        }
                    }
                    q(
                        "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','em_andamento','aguardando')",
                        [$uidNow, $cid, $id],
                    );
                    try {
                        if (function_exists("financial_sync_appointment")) {
                            financial_sync_appointment($cid, $id, $uidNow);
                        }
                    } catch (Throwable $e) {
                        error_log(
                            "[Prontoo agenda financial sync after finish] " .
                                $e->getMessage(),
                        );
                    }
                    audit("consulta_concluida_atalho", "consulta", $id, [
                        "patient_link_id" => $patientId,
                        "audit_body" =>
                            "Profissional concluiu o atendimento pelo atalho visual da jornada.",
                    ]);
                    flash(
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
                    $guard = appointment_journey_hard_guard_message(
                        $a,
                        "finish_checkout",
                        $role,
                        $uidNow,
                    );
                    if ($guard !== "") {
                        flash($guard, "bad");
                        $postRedirect();
                    }
                    $stmt = q(
                        "UPDATE pi_appointments SET status='finalizado', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='atendimento_concluido' AND consultation_finished_at IS NOT NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    q(
                        "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='atendimento_finalizado' AND t.status IN ('aberta','em_andamento','aguardando')",
                        [$uidNow, $cid, $id],
                    );
                    audit("saida_finalizada_atalho", "consulta", $id, [
                        "patient_link_id" => $patientId,
                        "audit_body" =>
                            "Recepção finalizou a saída do paciente pelo atalho visual da jornada.",
                    ]);
                    flash("Jornada finalizada.");
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
                    flash(
                        "Selecione um profissional válido para o bloqueio ou use bloqueio do consultório inteiro.",
                        "bad",
                    );
                    $postRedirect();
                }
                if ($blockDoctor === null && !has_effective_role($c, "gerente")) {
                    flash(
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
                    flash(
                        "Informe início, fim posterior ao início e motivo do bloqueio.",
                        "bad",
                    );
                    $postRedirect();
                }
                $startAt = normalize_db_datetime($startAt);
                $endAt = normalize_db_datetime($endAt);
                $blockId = 0;
                try {
                    db_begin_transaction();
                    q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                    $conflict = agenda_conflict_message(
                        $cid,
                        $blockDoctor,
                        $startAt,
                        $endAt,
                        "bloqueio",
                    );
                    if ($conflict) {
                        db_rollback();
                        flash($conflict, "bad");
                        $postRedirect();
                    }
                    q(
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
                    $blockId = db_last_insert_id();
                    db_commit();
                } catch (Throwable $e) {
                    if (pdo()->inTransaction()) {
                        db_rollback();
                    }
                    error_log("[Prontoo agenda block] " . $e->getMessage());
                    flash(
                        "Não foi possível registrar o bloqueio. Revise os horários e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                clinic_metric_inc($cid, "agenda_blocks");
                audit("agenda_bloqueada", "bloqueio", $blockId, $_POST);
                flash("Horário bloqueado.");
                $postRedirect();
            }
            if ($act === "update_block") {
                $id = (int) ($_POST["id"] ?? 0);
                $b = one("SELECT id FROM pi_blocks WHERE id=? AND clinic_id=?", [
                    $id,
                    $cid,
                ]);
                if (!$b) {
                    flash("Bloqueio não encontrado.", "bad");
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
                    flash(
                        "Selecione um profissional válido para o bloqueio ou use bloqueio do consultório inteiro.",
                        "bad",
                    );
                    $postRedirect();
                }
                if ($blockDoctor === null && !has_effective_role($c, "gerente")) {
                    flash(
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
                    flash(
                        "Informe início, fim posterior ao início e motivo do bloqueio.",
                        "bad",
                    );
                    $postRedirect();
                }
                $startAt = normalize_db_datetime($startAt);
                $endAt = normalize_db_datetime($endAt);
                $hoursMessage =
                    $blockDoctor !== null
                        ? doctor_work_hours_conflict_message(
                            $cid,
                            $blockDoctor,
                            $startAt,
                            $endAt,
                        )
                        : "";
                if ($hoursMessage !== "") {
                    flash($hoursMessage, "bad");
                    $postRedirect();
                }
                try {
                    db_begin_transaction();
                    q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                    $conflict = agenda_conflict_message(
                        $cid,
                        $blockDoctor,
                        $startAt,
                        $endAt,
                        "alteração do bloqueio",
                        0,
                        $id,
                    );
                    if ($conflict) {
                        db_rollback();
                        flash($conflict, "bad");
                        $postRedirect();
                    }
                    q(
                        "UPDATE pi_blocks SET doctor_user_id=?, start_at=?, end_at=?, reason=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                        [$blockDoctor, $startAt, $endAt, $reason, $id, $cid],
                    );
                    db_commit();
                } catch (Throwable $e) {
                    if (pdo()->inTransaction()) {
                        db_rollback();
                    }
                    error_log("[Prontoo agenda block update] " . $e->getMessage());
                    flash(
                        "Não foi possível alterar o bloqueio. Revise os dados e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                audit("bloqueio_alterado", "bloqueio", $id, $_POST);
                flash("Bloqueio alterado.");
                $postRedirect();
            }
            if ($act === "delete_block" || $act === "unblock") {
                $id = (int) ($_POST["id"] ?? 0);
                $cancelReason = mb_trim((string) ($_POST["cancel_reason"] ?? ""));
                if ($cancelReason === "") {
                    flash("Informe o motivo da exclusão do bloqueio.", "bad");
                    $postRedirect();
                }
                $b = one(
                    "SELECT id FROM pi_blocks WHERE id=? AND clinic_id=? AND deleted_at IS NULL",
                    [$id, $cid],
                );
                if ($b) {
                    q(
                        "UPDATE pi_blocks SET deleted_at=NOW(),deleted_by=?,cancel_reason=?,updated_at=NOW() WHERE id=? AND clinic_id=? AND deleted_at IS NULL",
                        [(int) $c["user"]["id"], $cancelReason, $id, $cid],
                    );
                    audit("bloqueio_removido", "bloqueio", $id, [
                        "motivo" => $cancelReason,
                    ]);
                    flash("Bloqueio excluído da agenda e preservado no histórico.");
                } else {
                    flash("Bloqueio não encontrado.", "bad");
                }
                $postRedirect();
            }
            if ($act === "update_appointment") {
                $id = (int) ($_POST["id"] ?? 0);
                $a = one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                if (!$a) {
                    flash("Agendamento não encontrado.", "bad");
                    $postRedirect();
                }
                $guard = appointment_journey_hard_guard_message(
                    $a,
                    "update_appointment",
                    $role,
                    $uid,
                );
                if ($guard !== "") {
                    flash($guard, "bad");
                    $postRedirect();
                }
                $did = (int) ($_POST["doctor_user_id"] ?? 0);
                $startAt = (string) ($_POST["start_at"] ?? "");
                $endAt = (string) ($_POST["end_at"] ?? "");
                $patient = (int) ($_POST["patient_link_id"] ?? 0);
                if (!isset($docs[$did])) {
                    flash("Selecione um profissional válido.", "bad");
                    $postRedirect();
                }
                if (
                    $patient <= 0 ||
                    !isset($pats[$patient]) ||
                    $startAt === "" ||
                    $endAt === "" ||
                    strtotime($endAt) <= strtotime($startAt)
                ) {
                    flash(
                        "Informe paciente da clínica atual, início e fim posterior ao início.",
                        "bad",
                    );
                    $postRedirect();
                }
                if (
                    $blockReason = patient_appointment_registration_block_reason(
                        $cid,
                        $patient,
                    )
                ) {
                    flash($blockReason, "bad");
                    $postRedirect();
                }
                $startAt = normalize_db_datetime($startAt);
                $endAt = normalize_db_datetime($endAt);
                $hoursMessage = doctor_work_hours_conflict_message(
                    $cid,
                    $did,
                    $startAt,
                    $endAt,
                );
                if ($hoursMessage !== "") {
                    flash($hoursMessage, "bad");
                    $postRedirect();
                }
                $procId = appointment_procedure_id_from_post($cid);
                $durationMessage = appointment_min_duration_message(
                    $cid,
                    $procId,
                    $startAt,
                    $endAt,
                );
                if ($durationMessage) {
                    flash($durationMessage, "bad");
                    $postRedirect();
                }
                try {
                    db_begin_transaction();
                    q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                    $locked = one(
                        "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? FOR UPDATE",
                        [$id, $cid],
                    );
                    $lockedGuard = $locked
                        ? appointment_journey_hard_guard_message(
                            $locked,
                            "update_appointment",
                            $role,
                            $uid,
                        )
                        : "Agendamento não encontrado.";
                    if ($lockedGuard !== "") {
                        db_rollback();
                        flash($lockedGuard, "bad");
                        $postRedirect();
                    }
                    $conflict = agenda_conflict_message(
                        $cid,
                        $did,
                        $startAt,
                        $endAt,
                        "alteração do agendamento",
                        $id,
                        0,
                    );
                    if ($conflict) {
                        db_rollback();
                        flash($conflict, "bad");
                        $postRedirect();
                    }
                    [
                        $paid,
                        $method,
                        $amount,
                        $paymentDestination,
                        $paymentError,
                    ] = appointment_payment_post_context(
                        $cid,
                        $procId,
                        (int) ($a["procedure_id"] ?? 0) === (int) $procId
                    ? (int) ($a["payment_amount_cents"] ?? 0)
                    : 0,
                    );
                    if ($paymentError) {
                        flash($paymentError, "bad");
                        $postRedirect();
                    }
                    q(
                        "UPDATE pi_appointments SET patient_link_id=?, doctor_user_id=?, start_at=?, end_at=?, procedure_id=?, reason=?, notes=?, change_reason=?, payment_amount_cents=?, payment_method=?, payment_status=?, payment_confirmed_at=IF(?,COALESCE(payment_confirmed_at,NOW()),NULL), updated_at=NOW() WHERE id=? AND clinic_id=?",
                        [
                            $patient,
                            $did,
                            $startAt,
                            $endAt,
                            $procId,
                            procedure_reason_from_post($cid),
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
                    financial_sync_appointment(
                        $cid,
                        $id,
                        (int) $c["user"]["id"],
                        $paymentDestination,
                    );
                    db_commit();
                } catch (Throwable $e) {
                    if (pdo()->inTransaction()) {
                        db_rollback();
                    }
                    error_log("[Prontoo appointment update] " . $e->getMessage());
                    flash(
                        $e instanceof RuntimeException &&
                        trim($e->getMessage()) !== ""
                            ? $e->getMessage()
                            : "Não foi possível alterar o agendamento. Revise os horários e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                audit("consulta_alterada", "consulta", $id, $_POST);
                flash("Agendamento alterado.");
                $postRedirect();
            }
            if ($act === "delete_appointment") {
                $id = (int) ($_POST["id"] ?? 0);
                $cancelReason = mb_trim((string) ($_POST["cancel_reason"] ?? ""));
                if ($cancelReason === "") {
                    flash(
                        "Informe o motivo da exclusão/cancelamento do agendamento.",
                        "bad",
                    );
                    $postRedirect();
                }
                $a = one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                $guard = $a
                    ? appointment_journey_hard_guard_message(
                        $a,
                        "delete_appointment",
                        $role,
                        $uid,
                    )
                    : "Agendamento não encontrado.";
                if ($a && $guard === "") {
                    $stmt = q(
                        "UPDATE pi_appointments SET status='cancelado', cancel_reason=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                        [$cancelReason, $id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    if (function_exists("financial_cancel_appointment_revenue")) {
                        financial_cancel_appointment_revenue(
                            $cid,
                            $id,
                            $uid,
                            $cancelReason,
                        );
                    }
                    audit("consulta_excluida", "consulta", $id, [
                        "motivo" => $cancelReason,
                    ]);
                    flash(
                        "Agendamento excluído da agenda e preservado como cancelado no histórico.",
                    );
                } else {
                    flash($guard, "bad");
                }
                $postRedirect();
            }
            $did = (int) ($_POST["doctor_user_id"] ?? 0);
            $startAt = (string) ($_POST["start_at"] ?? "");
            $endAt = (string) ($_POST["end_at"] ?? "");
            $patient = (int) ($_POST["patient_link_id"] ?? 0);
            if (!isset($docs[$did])) {
                flash("Selecione um profissional válido.", "bad");
                $postRedirect();
            }
            if (
                $patient <= 0 ||
                !isset($pats[$patient]) ||
                $startAt === "" ||
                $endAt === "" ||
                strtotime($endAt) <= strtotime($startAt)
            ) {
                flash(
                    "Informe paciente da clínica atual, início e fim posterior ao início.",
                    "bad",
                );
                $postRedirect();
            }
            if (
                $blockReason = patient_appointment_registration_block_reason(
                    $cid,
                    $patient,
                )
            ) {
                flash($blockReason, "bad");
                $postRedirect();
            }
            $startAt = normalize_db_datetime($startAt);
            $endAt = normalize_db_datetime($endAt);
            $hoursMessage = doctor_work_hours_conflict_message(
                $cid,
                $did,
                $startAt,
                $endAt,
            );
            if ($hoursMessage !== "") {
                flash($hoursMessage, "bad");
                $postRedirect();
            }
            $procId = appointment_procedure_id_from_post($cid);
            if ($act === "create" && !$procId) {
                flash(
                    procedure_options($cid, true) === []
                        ? "Cadastre Procedimentos primeiro."
                        : "Selecione um procedimento cadastrado.",
                    "bad",
                );
                $postRedirect();
            }
            $appointmentId = 0;
            try {
                db_begin_transaction();
                q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                $lockedProcedure = one(
                    "SELECT id,title,duration_minutes FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1 FOR UPDATE",
                    [$procId, $cid],
                );
                if (!$lockedProcedure) {
                    db_rollback();
                    flash(
                        "O procedimento selecionado não está mais disponível. Escolha outro procedimento cadastrado.",
                        "bad",
                    );
                    $postRedirect();
                }
                $durationMessage = appointment_min_duration_message(
                    $cid,
                    $procId,
                    $startAt,
                    $endAt,
                );
                if ($durationMessage) {
                    db_rollback();
                    flash($durationMessage, "bad");
                    $postRedirect();
                }
                $conflict = agenda_conflict_message(
                    $cid,
                    $did,
                    $startAt,
                    $endAt,
                    "agendamento",
                );
                if ($conflict) {
                    db_rollback();
                    flash($conflict, "bad");
                    $postRedirect();
                }
                [
                    $paid,
                    $method,
                    $amount,
                    $paymentDestination,
                    $paymentError,
                ] = appointment_payment_post_context($cid, $procId, 0);
                if ($paymentError) {
                    flash($paymentError, "bad");
                    $postRedirect();
                }
                q(
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
                $appointmentId = db_last_insert_id();
                financial_sync_appointment(
                    $cid,
                    $appointmentId,
                    (int) $c["user"]["id"],
                    $paymentDestination,
                );
                db_commit();
            } catch (Throwable $e) {
                if (pdo()->inTransaction()) {
                    db_rollback();
                }
                error_log("[Prontoo appointment create] " . $e->getMessage());
                flash(
                    $e instanceof RuntimeException && trim($e->getMessage()) !== ""
                        ? $e->getMessage()
                        : "Não foi possível agendar a consulta. Revise os horários e tente novamente.",
                    "bad",
                );
                $postRedirect();
            }
            counter_inc("appointments_total");
            clinic_metric_inc($cid, "appointments");
            audit("consulta_agendada", "consulta", $appointmentId, $_POST);
            flash("Consulta agendada.");
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
            e($day) .
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
                flash(
                    "Seu cargo não possui operação de Agendamento Rápido.",
                    "bad",
                );
                redirect("appointments", $buildParams());
            }
            $initialStart = agenda_normalize_local_input(
                (string) ($_GET["start"] ?? ""),
                $day,
                "08:00",
            );
            $initialDay = agenda_safe_day_from_local_input($initialStart, $day);
            $initialEnd = agenda_local_input_add_minutes($initialStart, 30);
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
                        role_label_for("medico", $cid))
                    : role_label_for("medico", $cid);
            $returnHiddenOperation =
                '<input type="hidden" name="return_day" value="' .
                e($initialDay) .
                '"><input type="hidden" name="return_doctor" value="' .
                (int) $initialDoctor .
                '">';
            $quickHeader =
                '<div class="agenda-quick-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
                icon("event_available") .
                '</span><div class="agenda-quick-hero-copy"><h2>' .
                e($createTitle) .
                "</h2><p>" .
                e($createSubtitle) .
                '</p></div><a class="ghost small agenda-quick-back" href="' .
                href("appointments", $cancelParams) .
                '">' .
                icon("arrow_back") .
                "<span>Agenda</span></a></div>";
            $quickSummary =
                '<div class="agenda-quick-summary" aria-label="Resumo do horário inferido"><span>' .
                icon("calendar_month") .
                "<b>" .
                e(date_br($initialDay)) .
                "</b><small>Data</small></span><span>" .
                icon("schedule") .
                "<b>" .
                e($initialHour . "–" . $initialEndHour) .
                "</b><small>Horário</small></span><span>" .
                icon("stethoscope") .
                "<b>" .
                e($doctorHint) .
                "</b><small>Profissional</small></span></div>";
            $form =
                '<section class="form-panel agenda-route-form agenda-quick-form-panel">' .
                $quickHeader .
                $quickSummary .
                '<form method="post" class="compact agenda-create-form agenda-quick-form" data-appointment-create-form>' .
                csrf_field() .
                $returnHiddenOperation .
                '<input type="hidden" name="act" value="create"><fieldset class="agenda-quick-section agenda-quick-time"><legend>' .
                icon("schedule") .
                '<span>Horário</span></legend><div class="agenda-quick-time-grid">' .
                form_row(
                    "Início",
                    input(
                        "start_at",
                        "datetime-local",
                        $initialStart,
                        "required data-appointment-start",
                    ),
                ) .
                form_row(
                    "Fim",
                    input(
                        "end_at",
                        "datetime-local",
                        $initialEnd,
                        "required data-appointment-end",
                    ),
                ) .
                '</div></fieldset><fieldset class="agenda-quick-section agenda-quick-main"><legend>' .
                icon("edit_calendar") .
                '<span>Dados do agendamento</span></legend><div class="agenda-quick-grid">' .
                select_label(
                    role_label_for("medico", $cid),
                    "doctor_user_id",
                    $formDoctorOptions,
                    $initialDoctor ?: null,
                    "required",
                ) .
                select_label(
                    "Paciente",
                    "patient_link_id",
                    $pats,
                    $prePatientId ?: null,
                    "required",
                ) .
                procedure_select_html($cid, "reason", "", true) .
                "</div></fieldset>" .
                appointment_payment_form_html($cid) .
                '<fieldset class="agenda-quick-section agenda-quick-notes"><legend>' .
                icon("notes") .
                "<span>Observações</span></legend>" .
                form_row(
                    "Observações importantes",
                    input("notes", "text", "", 'placeholder="Opcional"'),
                ) .
                '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
                href("appointments", $cancelParams) .
                '">' .
                icon("close") .
                '<span>Desistir</span></a><button type="submit" class="primary">' .
                icon("event_available") .
                "<span>Confirmar</span></button></div></form></section>";
            page(
                $createTitle,
                page_head(
                    $createTitle,
                    $createSubtitle,
                    '<a class="ghost small cmdlike" href="' .
                        href("appointments", $cancelParams) .
                        '">' .
                        icon("calendar_view_day") .
                        "<span>Agenda</span></a>",
                ) . card($form, "agenda-route-card agenda-quick-route-card"),
            );
            return;
        }
        if ($operationMode === "note") {
            $noteDay = (string) ($_GET["note_date"] ?? $day);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDay)) {
                $noteDay = $day;
            }
            $cancelParams = $buildParams($noteDay, $agendaDoctor);
            $roleOptions = clinic_role_options($cid, false);
            $noteTitle = "Nova Anotação";
            $noteSubtitle =
                "Registre uma anotação para a data escolhida na Agenda, com visibilidade controlada.";
            $form = agenda_note_form_html($cid, $noteDay, $c, $roleOptions);
            page(
                $noteTitle,
                page_head(
                    $noteTitle,
                    $noteSubtitle,
                    '<a class="ghost small cmdlike" href="' .
                        href("appointments", $cancelParams) .
                        '">' .
                        icon("calendar_view_day") .
                        "<span>Agenda</span></a>",
                ) . card($form, "agenda-route-card agenda-note-route-card"),
            );
            return;
        }
        if ($operationMode === "block") {
            if (!in_array($role, ["recepcionista", "medico", "gerente"], true)) {
                flash(
                    "Seu cargo não possui operação de bloqueio de horário.",
                    "bad",
                );
                redirect("appointments", $buildParams());
            }
            $initialStart = agenda_normalize_local_input(
                (string) ($_GET["start"] ?? ""),
                $day,
                "08:00",
            );
            $initialDay = agenda_safe_day_from_local_input($initialStart, $day);
            $initialEnd = agenda_local_input_add_minutes($initialStart, 30);
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
                e($initialDay) .
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
                        role_label_for("medico", $cid))
                    : "Todo consultório";
            $blockHeader =
                '<div class="agenda-quick-hero agenda-block-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
                icon("event_busy") .
                '</span><div class="agenda-quick-hero-copy"><h2>' .
                e($blockTitle) .
                "</h2><p>" .
                e($blockSubtitle) .
                '</p></div><a class="ghost small agenda-quick-back" href="' .
                href("appointments", $cancelParams) .
                '">' .
                icon("arrow_back") .
                "<span>Agenda</span></a></div>";
            $blockSummary =
                '<div class="agenda-quick-summary agenda-block-summary" aria-label="Resumo do bloqueio"><span>' .
                icon("calendar_month") .
                "<b>" .
                e(date_br($initialDay)) .
                "</b><small>Data</small></span><span>" .
                icon("schedule") .
                "<b>" .
                e($initialHour . "–" . $initialEndHour) .
                "</b><small>Horário</small></span><span>" .
                icon("event_busy") .
                "<b>" .
                e($blockDoctorHint) .
                "</b><small>Escopo</small></span></div>";
            $form =
                '<section class="form-panel agenda-route-form agenda-block-form-panel">' .
                $blockHeader .
                $blockSummary .
                '<form method="post" class="compact agenda-block-form agenda-quick-form agenda-structured-form">' .
                csrf_field() .
                $returnHiddenOperation .
                '<input type="hidden" name="act" value="block"><fieldset class="agenda-quick-section agenda-block-scope"><legend>' .
                icon("event_busy") .
                "<span>Escopo</span></legend>" .
                select_label(
                    "Agenda bloqueada",
                    "doctor_user_id",
                    $blockOptions,
                    $blockDefault,
                ) .
                '</fieldset><fieldset class="agenda-quick-section agenda-quick-time"><legend>' .
                icon("schedule") .
                '<span>Horário</span></legend><div class="agenda-quick-time-grid">' .
                form_row(
                    "Início",
                    input("start_at", "datetime-local", $initialStart, "required"),
                ) .
                form_row(
                    "Fim",
                    input("end_at", "datetime-local", $initialEnd, "required"),
                ) .
                '</div></fieldset><fieldset class="agenda-quick-section agenda-quick-notes"><legend>' .
                icon("notes") .
                "<span>Motivo</span></legend>" .
                form_row(
                    "Motivo do bloqueio",
                    input(
                        "reason",
                        "text",
                        "",
                        'required placeholder="Ex.: reunião, intervalo, procedimento externo"',
                    ),
                ) .
                '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
                href("appointments", $cancelParams) .
                '">' .
                icon("close") .
                '<span>Desistir</span></a><button type="submit" class="primary">' .
                icon("event_busy") .
                "<span>Salvar bloqueio</span></button></div></form></section>";
            page(
                $blockTitle,
                page_head(
                    $blockTitle,
                    $blockSubtitle,
                    '<a class="ghost small cmdlike" href="' .
                        href("appointments", $cancelParams) .
                        '">' .
                        icon("calendar_view_day") .
                        "<span>Agenda</span></a>",
                ) . card($form, "agenda-route-card agenda-block-route-card"),
            );
            return;
        }
        $headActions = "";
        if (in_array($role, ["recepcionista", "gerente"], true)) {
            $headActions .=
                '<a class="primary small cmdlike" href="' .
                href("appointments", $buildParams() + ["mode" => "create"]) .
                '">' .
                icon("event_available") .
                "<span>Agendar consulta</span></a>";
        }
        if (in_array($role, ["recepcionista", "medico", "gerente"], true)) {
            $headActions .=
                '<a class="ghost small cmdlike" href="' .
                href("appointments", $buildParams() + ["mode" => "block"]) .
                '">' .
                icon("event_busy") .
                "<span>Bloquear horário</span></a>";
        }
        $headActions .=
            '<a class="ghost small cmdlike" href="' .
            href(
                "appointments",
                $buildParams() + ["mode" => "note", "note_date" => $day],
            ) .
            '">' .
            icon("sticky_note_2") .
            "<span>Anotação</span></a>";
        if ($role === "assistente") {
            $headActions .=
                '<a class="ghost small" href="' .
                href("tasks") .
                '">' .
                icon("task_alt") .
                "<span>Tarefas da triagem</span></a>";
        }
        $prev = date("Y-m-d", strtotime($day . " -1 day"));
        $next = date("Y-m-d", strtotime($day . " +1 day"));
        [$dayStart, $dayEnd] = app_local_day_utc_range($day, $cid, $c);
        $rowSql =
            "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,notes,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_status,payment_method,payment_amount_cents,payment_confirmed_at,revenue_id FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?";
        $rowParams = [$cid, $dayStart, $dayEnd];
        if ($agendaDoctor > 0) {
            $rowSql .= " AND doctor_user_id=?";
            $rowParams[] = $agendaDoctor;
        }
        $rowSql .= " ORDER BY start_at ASC LIMIT 180";
        $rows = q($rowSql, $rowParams)->fetchAll();
        $blockSql =
            "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at<? AND end_at>?";
        $blockParams = [$cid, $dayEnd, $dayStart];
        if ($agendaDoctor > 0) {
            $blockSql .= " AND (doctor_user_id IS NULL OR doctor_user_id=?)";
            $blockParams[] = $agendaDoctor;
        }
        $blockSql .= " ORDER BY start_at ASC LIMIT 100";
        $blocks = q($blockSql, $blockParams)->fetchAll();
        $patientLinks = scoped_patient_map(
            $cid,
            int_ids($rows, "patient_link_id"),
            "id,person_id",
        );
        $persons = fetch_map(
            "pi_persons",
            int_ids(array_values($patientLinks), "person_id"),
            "id,full_name",
        );
        $doctorIds = array_values(
            array_unique(
                array_merge(
                    int_ids($rows, "doctor_user_id"),
                    int_ids($blocks, "doctor_user_id"),
                ),
            ),
        );
        $doctorMap = scoped_user_map($cid, $doctorIds, "id,name");
        $creators = scoped_user_map(
            $cid,
            int_ids($blocks, "created_by"),
            "id,name",
        );
        $nowTs = time();
        $isDone = function (array $a): bool {
    
            return in_array(
                appointment_status_code($a),
                ["atendimento_concluido", "finalizado"],
                true,
            );
        };
        $isStarted = function (array $a) use ($isDone): bool {
    
            return !$isDone($a) && appointment_status_code($a) === "em_atendimento";
        };
        $isArrived = function (array $a) use ($isDone, $isStarted): bool {
    
            return !$isDone($a) &&
                !$isStarted($a) &&
                in_array(
                    appointment_status_code($a),
                    ["chegou", "em_preparo", "pronto_atendimento"],
                    true,
                );
        };
        $isLate = function (array $a) use ($nowTs): bool {
    
            $start = app_storage_timestamp($a["start_at"] ?? "") ?: 0;
            return in_array(
                appointment_status_code($a),
                ["agendado", "confirmado"],
                true,
            ) &&
                $start > 0 &&
                $start < $nowTs;
        };
        $statusMeta = function (array $a) use ($role): array {
    
            $m = appointment_journey_meta($a, $role);
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
            $journeyActions = appointment_journey_role_actions(
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
                        csrf_field() .
                        $returnHidden .
                        '<input type="hidden" name="act" value="' .
                        e($realAct) .
                        '"><input type="hidden" name="id" value="' .
                        $id .
                        '">' .
                        $extra .
                        '<button type="submit" class="' .
                        e($class) .
                        ' small">' .
                        icon((string) $act["icon"]) .
                        "<span>" .
                        e((string) $act["label"]) .
                        "</span></button></form>";
                }
            }
            if ($mode === "quick") {
                if ($quickItems === "") {
                    return '<button type="button" class="ghost small cmdlike agenda-row-actions-disabled" data-agenda-row-actions disabled aria-disabled="true">' .
                        icon("bolt") .
                        "<span>Ações</span></button>";
                }
                $modalId = "agenda-actions-modal-" . $id;
                return '<span class="agenda-row-actions-wrap" data-agenda-row-actions><button type="button" class="ghost small cmdlike agenda-row-actions-open" data-agenda-actions-open="' .
                    e($modalId) .
                    '" aria-haspopup="dialog" aria-controls="' .
                    e($modalId) .
                    '">' .
                    icon("bolt") .
                    '<span>Ações</span></button><dialog class="agenda-actions-modal" id="' .
                    e($modalId) .
                    '" data-agenda-actions-modal aria-label="Ações do atendimento"><div class="agenda-actions-modal-card"><header class="agenda-actions-modal-head"><span>' .
                    icon("bolt") .
                    '<b>Ações</b></span><button type="button" class="icon-btn" data-agenda-actions-close aria-label="Fechar ações">' .
                    icon("close") .
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
                        csrf_field() .
                        $returnHidden .
                        '<input type="hidden" name="act" value="' .
                        e($realAct) .
                        '"><input type="hidden" name="id" value="' .
                        $id .
                        '">' .
                        $extra .
                        '<button type="submit" class="' .
                        e($class) .
                        ' small">' .
                        icon((string) $act["icon"]) .
                        "<span>" .
                        e((string) $act["label"]) .
                        "</span></button><small>" .
                        e($title) .
                        "</small></form>";
                }
                $journeyHtml .= "</div></div>";
            } else {
                $vm = appointment_journey_view_model($r, $role);
                $journeyHtml =
                    '<div class="agenda-action-section agenda-action-section-journey"><span class="agenda-action-section-title">Jornada do paciente</span><div class="agenda-action-empty">' .
                    icon("info") .
                    "<span>" .
                    e(
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
                    href("patient", ["id" => $pid]) .
                    '">' .
                    icon("folder_open") .
                    "<span>Abrir ficha</span></a>";
            }
            $editGuard = appointment_journey_hard_guard_message(
                $r,
                "edit",
                $role,
                $uid,
            );
            $cancelGuard = appointment_journey_hard_guard_message(
                $r,
                "cancel",
                $role,
                $uid,
            );
            if ($editGuard === "") {
                $editForm =
                    '<form method="post" class="agenda-edit-form agenda-edit-form-nested">' .
                    csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="update_appointment"><input type="hidden" name="id" value="' .
                    $id .
                    '">' .
                    select_label(
                        role_label_for("medico", $cid),
                        "doctor_user_id",
                        $docs,
                        (int) $r["doctor_user_id"],
                        "required",
                    ) .
                    select_label(
                        "Paciente",
                        "patient_link_id",
                        $pats,
                        (int) $r["patient_link_id"],
                        "required",
                    ) .
                    procedure_select_html(
                        $cid,
                        "reason",
                        (string) ($r["reason"] ?? ""),
                    ) .
                    '<div class="two">' .
                    form_row(
                        "Início",
                        input(
                            "start_at",
                            "datetime-local",
                            datetime_local_value((string) $r["start_at"]),
                            "required",
                        ),
                    ) .
                    form_row(
                        "Fim",
                        input(
                            "end_at",
                            "datetime-local",
                            datetime_local_value((string) $r["end_at"]),
                            "required",
                        ),
                    ) .
                    "</div>" .
                    appointment_payment_form_html($cid, $r) .
                    form_row(
                        "Motivo da alteração",
                        input(
                            "change_reason",
                            "text",
                            "",
                            'placeholder="Ex.: remarcação solicitada, ajuste administrativo"',
                        ),
                    ) .
                    form_row(
                        "Notas",
                        input(
                            "notes",
                            "text",
                            (string) ($r["notes"] ?? ""),
                            'placeholder="Opcional"',
                        ),
                    ) .
                    '<div class="form-actions"><button type="submit" class="primary small">' .
                    icon("edit_calendar") .
                    "<span>Salvar edição</span></button></div></form>";
                $deleteForm =
                    '<form method="post" class="agenda-delete-form compact agenda-delete-form-nested">' .
                    csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="delete_appointment"><input type="hidden" name="id" value="' .
                    $id .
                    '">' .
                    form_row(
                        "Motivo da exclusão",
                        input(
                            "cancel_reason",
                            "text",
                            "",
                            'required placeholder="Ex.: paciente desmarcou"',
                        ),
                    ) .
                    '<button type="submit" class="danger small">' .
                    icon("delete") .
                    "<span>Cancelar</span></button></form>";
                $secondaryItems .=
                    '<details class="agenda-action-disclosure"><summary class="agenda-action-item agenda-action-link">' .
                    icon("edit_calendar") .
                    "<span>Editar</span></summary>" .
                    $editForm .
                    "</details>";
            } elseif (
                appointment_journey_role_matches($role, "recepcionista") ||
                appointment_journey_role_matches($role, "gerente")
            ) {
                $secondaryItems .=
                    '<span class="agenda-action-item agenda-action-disabled" title="' .
                    e($editGuard) .
                    '">' .
                    icon("edit_calendar") .
                    "<span>Editar bloqueado</span></span>";
            }
            if ($cancelGuard === "") {
                $deleteForm =
                    '<form method="post" class="agenda-delete-form compact agenda-delete-form-nested">' .
                    csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="delete_appointment"><input type="hidden" name="id" value="' .
                    $id .
                    '">' .
                    form_row(
                        "Motivo da exclusão",
                        input(
                            "cancel_reason",
                            "text",
                            "",
                            'required placeholder="Ex.: paciente desmarcou"',
                        ),
                    ) .
                    '<button type="submit" class="danger small">' .
                    icon("delete") .
                    "<span>Cancelar</span></button></form>";
                $secondaryItems .=
                    '<details class="agenda-action-disclosure"><summary class="agenda-action-item agenda-action-link danger-link">' .
                    icon("delete") .
                    "<span>Cancelar</span></summary>" .
                    $deleteForm .
                    "</details>";
            } elseif (
                appointment_journey_role_matches($role, "recepcionista") ||
                appointment_journey_role_matches($role, "gerente")
            ) {
                $secondaryItems .=
                    '<span class="agenda-action-item agenda-action-disabled" title="' .
                    e($cancelGuard) .
                    '">' .
                    icon("delete") .
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
                icon("more_horiz") .
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
    
            $startTs = app_storage_timestamp($r["start_at"]);
            $endTs = app_storage_timestamp($r["end_at"]);
            [$label, $class, $ico] = $statusMeta($r);
            $doctor = $doctorMap[(int) ($r["doctor_user_id"] ?? 0)]["name"] ?? "";
            $patient = $patientName($r);
            $reason = mb_trim((string) ($r["reason"] ?? "")) ?: "Consulta";
            $period =
                app_time_br((string) $r["start_at"]) .
                "–" .
                app_time_br((string) $r["end_at"]);
            $timeLabel = app_time_br((string) $r["start_at"]);
            $meta = trim(
                ($doctor !== ""
                    ? first_name($doctor)
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
                ? financial_appointment_operational_chip_html($cid, $r, $role)
                : "";
            return '<article class="agenda-profile-row agenda-profile-row-' .
                $class .
                ' journey-row agenda-row-toggle" data-agenda-row><div class="agenda-row-summary" data-agenda-row-summary role="button" tabindex="0" aria-expanded="false" title="Abrir jornada"><span class="agenda-profile-time" title="' .
                e($period) .
                '">' .
                e($timeLabel) .
                '</span><span class="agenda-profile-main agenda-profile-main-compact"><strong>' .
                e($patient) .
                "</strong><small>" .
                e($meta) .
                '</small></span><span class="pill ' .
                $class .
                '">' .
                icon($ico) .
                e($label) .
                "</span>" .
                $financialHtml .
                $quickActions .
                '<button type="button" class="agenda-row-chevron" data-agenda-row-toggle aria-label="Expandir ou recolher agendamento">' .
                icon("expand_more") .
                '</button></div><div class="agenda-row-expanded" data-agenda-row-expanded hidden>' .
                appointment_journey_measure_html($r, $role, "") .
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
    
            $startTs = app_storage_timestamp($b["start_at"]);
            $endTs = app_storage_timestamp($b["end_at"]);
            $blockDoctor = !empty($b["doctor_user_id"])
                ? (int) $b["doctor_user_id"]
                : null;
            $doctorLabel = $blockDoctor
                ? "Agenda de " .
                    first_name(
                        $doctorMap[$blockDoctor]["name"] ??
                            agenda_doctor_name($cid, $blockDoctor),
                    )
                : "Todo consultório";
            $creator = $creators[(int) ($b["created_by"] ?? 0)]["name"] ?? "";
            $period =
                app_time_br((string) $b["start_at"]) .
                "–" .
                app_time_br((string) $b["end_at"]);
            $timeLabel = app_time_br((string) $b["start_at"]);
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
                    csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="update_block"><input type="hidden" name="id" value="' .
                    (int) $b["id"] .
                    '">' .
                    select_label(
                        "Agenda bloqueada",
                        "doctor_user_id",
                        $blockOptions,
                        $blockDoctor,
                    ) .
                    '<div class="two">' .
                    form_row(
                        "Início",
                        input(
                            "start_at",
                            "datetime-local",
                            datetime_local_value((string) $b["start_at"]),
                            "required",
                        ),
                    ) .
                    form_row(
                        "Fim",
                        input(
                            "end_at",
                            "datetime-local",
                            datetime_local_value((string) $b["end_at"]),
                            "required",
                        ),
                    ) .
                    "</div>" .
                    form_row(
                        "Motivo",
                        input("reason", "text", (string) $b["reason"], "required"),
                    ) .
                    '<div class="form-actions"><button type="button" class="ghost small" data-close-panel>' .
                    icon("close") .
                    '<span>Fechar</span></button><button type="submit" class="primary small">' .
                    icon("edit_calendar") .
                    "<span>Alterar</span></button></div></form>";
                $deleteForm =
                    '<form method="post" class="agenda-delete-form compact">' .
                    csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="delete_block"><input type="hidden" name="id" value="' .
                    (int) $b["id"] .
                    '">' .
                    form_row(
                        "Motivo da exclusão",
                        input(
                            "cancel_reason",
                            "text",
                            "",
                            'required placeholder="Ex.: bloqueio não será mais necessário"',
                        ),
                    ) .
                    '<button type="submit" class="danger small">' .
                    icon("delete") .
                    "<span>Remover bloqueio</span></button></form>";
                $actions =
                    '<details class="agenda-item-actions compact-actions"><summary class="ghost small cmdlike">' .
                    icon("more_horiz") .
                    '<span>Ações</span></summary><div class="agenda-action-panel"><div class="agenda-action-title">Ações do bloqueio</div>' .
                    $editForm .
                    $deleteForm .
                    "</div></details>";
            }
            return '<article class="agenda-profile-row agenda-profile-row-block"><span class="agenda-profile-time" title="' .
                e($period) .
                '">' .
                e($timeLabel) .
                '</span><span class="agenda-profile-main"><strong>Horário bloqueado</strong><small>' .
                e(
                    trim(
                        $doctorLabel .
                            " · " .
                            (string) $b["reason"] .
                            ($creator !== ""
                                ? " · por " . first_name($creator)
                                : ""),
                        " ·",
                    ),
                ) .
                '</small></span><span class="pill bad">' .
                icon("event_busy") .
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
            $start = app_storage_timestamp($a["start_at"]) ?: 0;
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
            $workRanges = doctor_work_ranges_for_day($cid, $agendaDoctor, $day);
            $busy = [];
            foreach ($rows as $a) {
                $busy[] = [
                    app_storage_timestamp($a["start_at"]) ?: 0,
                    app_storage_timestamp($a["end_at"]) ?: 0,
                ];
            }
            foreach ($blocks as $b) {
                $busy[] = [
                    app_storage_timestamp($b["start_at"]) ?: 0,
                    app_storage_timestamp($b["end_at"]) ?: 0,
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
    
            $d = app_db_utc_to_local($dt, $cid);
            return $d ? $d->getTimestamp() : (strtotime($dt) ?: 0);
        };
        $nextLabel = $nextAppt ? app_time_br((string) $nextAppt["start_at"]) : "—";
        $workRanges =
            $agendaDoctor > 0
                ? doctor_work_ranges_for_day($cid, $agendaDoctor, $day)
                : [];
        $zone = new DateTimeZone(app_context_timezone(null, $cid));
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
                e($kind) .
                '" aria-label="' .
                e($label) .
                ": " .
                e($value) .
                '"><span class="agenda-floating-icon" aria-hidden="true">' .
                icon($icon) .
                '</span><div class="agenda-floating-copy"><b>' .
                e($value) .
                '</b><span class="agenda-floating-label">' .
                e($label) .
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
                    e($slotClass) .
                    '" href="' .
                    href("appointments", $slotParams) .
                    '" style="--slot-index:' .
                    $i .
                    '" data-agenda-slot="1" data-start="' .
                    e($slotStart) .
                    '" aria-label="Cadastrar agendamento às ' .
                    e($fmtLocal($ts)) .
                    '"><span>' .
                    e($fmtLocal($ts)) .
                    "</span></a>";
            } else {
                $slotRows .=
                    '<div class="' .
                    e($slotClass) .
                    '" style="--slot-index:' .
                    $i .
                    '" data-agenda-slot="1" data-start="' .
                    e($slotStart) .
                    '"><span>' .
                    e($fmtLocal($ts)) .
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
                app_time_br((string) $r["start_at"]) .
                "–" .
                app_time_br((string) $r["end_at"]);
            $eventHtml .=
                '<article class="agenda-day-event agenda-day-event-' .
                $class .
                '" style="--event-top:' .
                $top .
                ";--event-height:" .
                $height .
                '" title="' .
                e($period . " · " . $patient) .
                '">' .
                '<span class="agenda-day-event-time">' .
                e($period) .
                "</span><strong>" .
                e($patient) .
                "</strong><small>" .
                e(
                    trim(
                        ($doctor !== "" ? first_name($doctor) : "Profissional") .
                            " · " .
                            $reason,
                        " ·",
                    ),
                ) .
                "</small><em>" .
                icon($ico) .
                e($label) .
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
                e((string) $b["reason"]) .
                '">' .
                '<span class="agenda-day-event-time">' .
                e(
                    app_time_br((string) $b["start_at"]) .
                        "–" .
                        app_time_br((string) $b["end_at"]),
                ) .
                "</span><strong>Bloqueado</strong><small>" .
                e((string) $b["reason"]) .
                "</small><em>" .
                icon("event_busy") .
                "Bloqueio</em></article>";
        }
        if ($eventHtml === "") {
            $eventHtml =
                '<div class="agenda-day-empty agenda-day-empty-icon" role="img" aria-label="Sem agendamentos no expediente" title="Sem agendamentos no expediente">' .
                icon("no_sim") .
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
            $label = agenda_crown_label_for_view($agendaView, $day, (string) $name);
            $navDoctorOptions .=
                '<option value="' .
                (int) $id .
                '" ' .
                ((int) $id === $agendaDoctor ? "selected" : "") .
                ">" .
                e($label) .
                "</option>";
        }
        $navDay =
            '<form method="get" class="agenda-crown-picker" aria-label="Selecionar dia e profissional">' .
            '<input type="hidden" name="r" value="appointments">' .
            '<input type="hidden" name="d" value="' .
            e($day) .
            '">' .
            ($agendaView !== "diario"
                ? '<input type="hidden" name="view" value="' . e($agendaView) . '">'
                : "") .
            '<a class="agenda-crown-arrow" href="' .
            href(
                "appointments",
                $buildParams($navPrev, $agendaDoctor, $agendaView),
            ) .
            '" aria-label="Voltar">' .
            icon("chevron_left") .
            "</a>" .
            '<select name="doctor" onchange="this.form.submit()" aria-label="Dia e profissional">' .
            $navDoctorOptions .
            "</select>" .
            '<a class="agenda-crown-arrow" href="' .
            href(
                "appointments",
                $buildParams($navNext, $agendaDoctor, $agendaView),
            ) .
            '" aria-label="Avançar">' .
            icon("chevron_right") .
            "</a>" .
            "</form>";
        $title = "Agenda";
        $subtitle = "";
        $agendaCreateUrl = href(
            "appointments",
            $buildParams($day, $agendaDoctor, "diario") + ["mode" => "create"],
        );
        $agendaDayNote = agenda_note_card_html(
            agenda_note_visible_for_day($cid, $day, $c),
            $c,
        );
        $agendaDay =
            $agendaDayNote .
            '<section class="agenda-day-shell agenda-crown-shell" style="--agenda-slots:' .
            $slotCount .
            ';--agenda-day-date:\'' .
            e($day) .
            '\'" data-agenda-day="' .
            e($day) .
            '" data-slot-minutes="' .
            $slotMinutes .
            '" data-work-start="' .
            e($fmtLocal($workStartTs)) .
            '" data-work-end="' .
            e($fmtLocal($workEndTs)) .
            '">' .
            $floating .
            '<div class="agenda-day-scale" aria-hidden="true">' .
            $slotRows .
            '</div><div class="agenda-day-canvas" data-agenda-canvas data-slot-minutes="' .
            $slotMinutes .
            '" data-day="' .
            e($day) .
            '" data-work-start="' .
            e($fmtLocal($workStartTs)) .
            '" data-agenda-create-url="' .
            e($agendaCreateUrl) .
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
            icon("event_available") .
            "<b>" .
            n($total) .
            '</b><span>consultas no dia</span></article><article class="agenda-summary-card">' .
            icon("how_to_reg") .
            "<b>" .
            n($arrived) .
            '</b><span>chegaram</span></article><article class="agenda-summary-card">' .
            icon("stethoscope") .
            "<b>" .
            n($started) .
            '</b><span>em atendimento</span></article><article class="agenda-summary-card">' .
            icon("task_alt") .
            "<b>" .
            n($done) .
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
                    ? doctor_work_ranges_for_day($cid, $agendaDoctor, $renderDay)
                    : [];
            $zone = new DateTimeZone(app_context_timezone(null, $cid));
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
                        e($slotClass) .
                        '" href="' .
                        href("appointments", $slotParams) .
                        '" style="--slot-index:' .
                        $i .
                        '" data-agenda-slot="1" data-start="' .
                        e($slotStart) .
                        '" aria-label="Cadastrar agendamento às ' .
                        e($fmtLocal($ts)) .
                        '"><span>' .
                        e($fmtLocal($ts)) .
                        "</span></a>";
                } else {
                    $slotRows .=
                        '<div class="' .
                        e($slotClass) .
                        '" style="--slot-index:' .
                        $i .
                        '" data-agenda-slot="1" data-start="' .
                        e($slotStart) .
                        '"><span>' .
                        e($fmtLocal($ts)) .
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
                    app_time_br((string) $r["start_at"]) .
                    "–" .
                    app_time_br((string) $r["end_at"]);
                $eventHtml .=
                    '<article class="agenda-day-event agenda-day-event-' .
                    $class .
                    '" style="--event-top:' .
                    $top .
                    ";--event-height:" .
                    $height .
                    '" title="' .
                    e($period . " · " . $patient) .
                    '">' .
                    '<span class="agenda-day-event-time">' .
                    e($period) .
                    "</span><strong>" .
                    e($patient) .
                    "</strong><small>" .
                    e(
                        trim(
                            ($doctor !== ""
                                ? first_name($doctor)
                                : "Profissional") .
                                " · " .
                                $reason,
                            " ·",
                        ),
                    ) .
                    "</small><em>" .
                    icon($ico) .
                    e($label) .
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
                    e((string) $b["reason"]) .
                    '">' .
                    '<span class="agenda-day-event-time">' .
                    e(
                        app_time_br((string) $b["start_at"]) .
                            "–" .
                            app_time_br((string) $b["end_at"]),
                    ) .
                    "</span><strong>Bloqueado</strong><small>" .
                    e((string) $b["reason"]) .
                    "</small><em>" .
                    icon("event_busy") .
                    "Bloqueio</em></article>";
            }
            if ($eventHtml === "") {
                $eventHtml =
                    '<div class="agenda-day-empty agenda-day-empty-icon" role="img" aria-label="Sem agendamentos no expediente" title="Sem agendamentos no expediente">' .
                    icon("no_sim") .
                    "</div>";
            }
            $createUrl = href(
                "appointments",
                $buildParams($renderDay, $agendaDoctor, "diario") + [
                    "mode" => "create",
                    "quick" => "1",
                ],
            );
            return '<section class="agenda-day-shell agenda-crown-shell agenda-week-day-shell" style="--agenda-slots:' .
                $slotCount .
                ';--agenda-day-date:\'' .
                e($renderDay) .
                '\'" data-agenda-day="' .
                e($renderDay) .
                '" data-slot-minutes="' .
                $slotMinutes .
                '" data-work-start="' .
                e($fmtLocal($startTs)) .
                '" data-work-end="' .
                e($fmtLocal($endTs)) .
                '"><div class="agenda-day-scale" aria-hidden="true">' .
                $slotRows .
                '</div><div class="agenda-day-canvas" data-agenda-canvas data-slot-minutes="' .
                $slotMinutes .
                '" data-day="' .
                e($renderDay) .
                '" data-work-start="' .
                e($fmtLocal($startTs)) .
                '" data-agenda-create-url="' .
                e($createUrl) .
                '">' .
                $eventHtml .
                "</div></section>";
        };
        $groupByDay = function (array $items) use ($cid): array {
    
            $out = [];
            foreach ($items as $it) {
                $dt = app_db_utc_to_local((string) ($it["start_at"] ?? ""), $cid);
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
            [$weekRangeStart] = app_local_day_utc_range($weekDays[0], $cid, $c);
            [, $weekRangeEnd] = app_local_day_utc_range($weekDays[6], $cid, $c);
            $weekSql =
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,notes,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_status,payment_method,payment_amount_cents,payment_confirmed_at,revenue_id FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?";
            $weekParams = [$cid, $weekRangeStart, $weekRangeEnd];
            if ($agendaDoctor > 0) {
                $weekSql .= " AND doctor_user_id=?";
                $weekParams[] = $agendaDoctor;
            }
            $weekSql .= " ORDER BY start_at ASC LIMIT 600";
            $weekRows = q($weekSql, $weekParams)->fetchAll();
            $weekBlockSql =
                "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at<? AND end_at>?";
            $weekBlockParams = [$cid, $weekRangeEnd, $weekRangeStart];
            if ($agendaDoctor > 0) {
                $weekBlockSql .=
                    " AND (doctor_user_id IS NULL OR doctor_user_id=?)";
                $weekBlockParams[] = $agendaDoctor;
            }
            $weekBlockSql .= " ORDER BY start_at ASC LIMIT 300";
            $weekBlocks = q($weekBlockSql, $weekBlockParams)->fetchAll();
            $weekGroupedRows = $groupByDay($weekRows);
            $weekGroupedBlocks = $groupByDay($weekBlocks);
            $weekPatientLinks = scoped_patient_map(
                $cid,
                int_ids($weekRows, "patient_link_id"),
                "id,person_id",
            );
            $weekPersons = fetch_map(
                "pi_persons",
                int_ids(array_values($weekPatientLinks), "person_id"),
                "id,full_name",
            );
            $weekDoctorMap = scoped_user_map(
                $cid,
                array_values(
                    array_unique(
                        array_merge(
                            int_ids($weekRows, "doctor_user_id"),
                            int_ids($weekBlocks, "doctor_user_id"),
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
                    e($weekLabels[$i]) .
                    "</b><span>" .
                    e(date("d/m", strtotime($wd))) .
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
            [$monthRangeStart] = app_local_day_utc_range($monthFirst, $cid, $c);
            [, $monthRangeEnd] = app_local_day_utc_range($monthLast, $cid, $c);
            $monthSql =
                "SELECT id,doctor_user_id,start_at,end_at,status FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?";
            $monthParams = [$cid, $monthRangeStart, $monthRangeEnd];
            if ($agendaDoctor > 0) {
                $monthSql .= " AND doctor_user_id=?";
                $monthParams[] = $agendaDoctor;
            }
            $monthSql .= " ORDER BY start_at ASC LIMIT 1500";
            $monthRows = q($monthSql, $monthParams)->fetchAll();
            $monthGrouped = $groupByDay($monthRows);
            $daysInMonth = (int) date("t", strtotime($monthFirst));
            $firstW = ((int) date("w", strtotime($monthFirst)) + 6) % 7;
            $agendaMonth =
                '<section class="agenda-month-view" aria-label="Agenda mensal"><div class="agenda-month-weekdays">';
            foreach ($monthLabels as $lab) {
                $agendaMonth .= "<span>" . e($lab) . "</span>";
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
                        ? doctor_work_ranges_for_day($cid, $agendaDoctor, $dstr)
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
                $isToday = $dstr === app_today_in_timezone($cid, $c);
                $agendaMonth .=
                    '<a class="agenda-month-day' .
                    ($isToday ? " is-today" : "") .
                    '" href="' .
                    href(
                        "appointments",
                        $buildParams($dstr, $agendaDoctor, "diario"),
                    ) .
                    '" style="--agenda-month-fill:' .
                    e((string) $fillPct) .
                    '%" data-occupancy="' .
                    e((string) $pct) .
                    '" aria-label="' .
                    e(date_br($dstr) . " · " . $pct . "% de ocupação") .
                    '"><span class="agenda-month-number">' .
                    e((string) $dnum) .
                    '</span><span class="agenda-month-occupancy-value">' .
                    e($pct . "%") .
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
            e($agendaView) .
            '">' .
            card(
                $doctorFilter . $navDay,
                "agenda-card agenda-nav-card agenda-crown-nav-card",
            ) .
            $agendaContent .
            "</div>";
        page("Agenda", page_head($title, $subtitle, $headActions) . $body);
    
    }
}
