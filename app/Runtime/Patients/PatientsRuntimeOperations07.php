<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Patients;

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
use Prontoo\Runtime\Financial\FinancialComposition;
use Prontoo\Runtime\Patients\PatientComposition;
use Prontoo\Runtime\Patients\PatientViewComposition;

final class PatientsRuntimeOperations07
{
    private function __construct()
    {
    }

    public static function page_patient(): void
    
    {
    
        $c = require_can("patients");
        $cid = (int) $c["clinic_id"];
        $id = (int) ($_GET["id"] ?? 0);
        patient_tabs_ensure_schema();
        patient_guardians_ensure_schema();
        $p = one(
            "SELECT id,clinic_id,person_id,phone,email,address,address_zip,address_number,address_neighborhood,address_complement,address_state,address_city,address_city_ibge,tags,notes,active,registration_needs_update,deleted_at,created_by,created_at,updated_at FROM pi_patients WHERE id=? AND clinic_id=?",
            [$id, $cid],
        );
        if ($p) {
            $ps =
                one("SELECT full_name,cpf,birth_date FROM pi_persons WHERE id=?", [
                    (int) $p["person_id"],
                ]) ?:
                [];
            $p += $ps;
        }
        if (!$p) {
            http_response_code(404);
            page("Paciente", '<div class="empty">Paciente não encontrado.</div>');
            return;
        }
        if ((int) ($p["active"] ?? 1) !== 1) {
            page(
                "Paciente excluído",
                '<div class="empty"><h2>Cadastro excluído</h2><p>Este paciente foi marcado como excluído e só pode ser restaurado pelo Administrador.</p><p><a class="ghost" href="' .
                    href("patients") .
                    '">' .
                    icon("arrow_back") .
                    "<span>Voltar para Pacientes</span></a></p></div>",
            );
            return;
        }
        $role = (string) ($c["role"] ?? "");
        $canEditPatient = $role === "medico";
        $canUpdatePatientContact = in_array(
            $role,
            ["recepcionista", "medico"],
            true,
        );
        $canManageGuardian = can("patients");
        $guardians = patient_legal_guardians($cid, $id);
        $profileStatus = patient_profile_status($p, $guardians);
        $guardianCard = patient_legal_guardian_card(
            $cid,
            $id,
            $p,
            $guardians,
            $canManageGuardian,
        );
        $extraTabs = patient_extra_tabs($cid, $id);
        $extraTypeOptions = patient_tab_record_options($extraTabs);
        $extraTypeMap = patient_tab_map_by_type($extraTabs);
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "record");
            $type = (string) ($_POST["record_type"] ?? "");
            $title = mb_trim((string) ($_POST["title"] ?? ""));
            $content = mb_trim((string) ($_POST["content"] ?? ""));
            if ($act === "save_legal_guardian") {
                if (!$canManageGuardian) {
                    flash(
                        "Este perfil não tem permissão para alterar o responsável legal.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                $guardianId = (int) ($_POST["guardian_id"] ?? 0);
                $gName = mb_trim((string) ($_POST["guardian_name"] ?? ""));
                $gCpf = only_digits((string) ($_POST["guardian_cpf"] ?? ""));
                $gRel = normalize_guardian_relationship(
                    (string) ($_POST["guardian_relationship"] ?? "outro"),
                );
                $gPhone = phone_br((string) ($_POST["guardian_phone"] ?? ""));
                $gEmail = mb_trim((string) ($_POST["guardian_email"] ?? ""));
                $gDoc = mb_trim((string) ($_POST["guardian_document_note"] ?? ""));
                $gNotes = mb_trim((string) ($_POST["guardian_notes"] ?? ""));
                $makePrimary =
                    !empty($_POST["guardian_primary"]) ||
                    !patient_has_legal_guardian($cid, $id);
                if ($gName === "") {
                    flash("Informe o nome completo do responsável legal.", "bad");
                    redirect("patient", ["id" => $id]);
                }
                if (!valid_cpf($gCpf)) {
                    flash("Informe um CPF válido para o responsável legal.", "bad");
                    redirect("patient", ["id" => $id]);
                }
                if ($guardianId > 0) {
                    $owned = one(
                        "SELECT id FROM pi_patient_guardians WHERE id=? AND clinic_id=? AND patient_link_id=? AND active=1",
                        [$guardianId, $cid, $id],
                    );
                    if (!$owned) {
                        flash("Responsável legal não encontrado.", "bad");
                        redirect("patient", ["id" => $id]);
                    }
                }
                $dup =
                    (int) (val(
                        "SELECT id FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND cpf=? AND active=1 AND id<>? LIMIT 1",
                        [$cid, $id, $gCpf, $guardianId],
                    ) ?? 0);
                if ($dup > 0) {
                    flash(
                        "Este CPF já está vinculado como responsável deste paciente.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                if ($makePrimary) {
                    q(
                        "UPDATE pi_patient_guardians SET is_primary=0,updated_at=NOW() WHERE clinic_id=? AND patient_link_id=?",
                        [$cid, $id],
                    );
                }
                if ($guardianId > 0) {
                    q(
                        "UPDATE pi_patient_guardians SET full_name=?,cpf=?,relationship=?,phone=?,email=?,document_note=?,notes=?,is_primary=?,updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?",
                        [
                            $gName,
                            $gCpf,
                            $gRel,
                            $gPhone,
                            $gEmail,
                            $gDoc,
                            $gNotes,
                            $makePrimary ? 1 : 0,
                            $guardianId,
                            $cid,
                            $id,
                        ],
                    );
                } else {
                    q(
                        "INSERT INTO pi_patient_guardians (clinic_id,patient_link_id,full_name,cpf,relationship,phone,email,document_note,notes,is_primary,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
                        [
                            $cid,
                            $id,
                            $gName,
                            $gCpf,
                            $gRel,
                            $gPhone,
                            $gEmail,
                            $gDoc,
                            $gNotes,
                            $makePrimary ? 1 : 0,
                            (int) $c["user"]["id"],
                        ],
                    );
                    $guardianId = db_last_insert_id();
                }
                audit("responsavel_legal_salvo", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "responsavel" => $gName,
                    "vinculo" => $gRel,
                    "campos" => ["Responsável Legal"],
                    "audit_body" =>
                        "Responsável Legal vinculado ou atualizado na ficha do paciente.",
                ]);
                flash("Responsável Legal salvo.");
                redirect("patient", ["id" => $id]);
            }
            if ($act === "delete_legal_guardian") {
                if (!$canManageGuardian) {
                    flash(
                        "Este perfil não tem permissão para remover responsável legal.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                $guardianId = (int) ($_POST["guardian_id"] ?? 0);
                $old = one(
                    "SELECT id,full_name FROM pi_patient_guardians WHERE id=? AND clinic_id=? AND patient_link_id=? AND active=1",
                    [$guardianId, $cid, $id],
                );
                if ($old) {
                    q(
                        "UPDATE pi_patient_guardians SET active=0,is_primary=0,updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?",
                        [$guardianId, $cid, $id],
                    );
                    $next =
                        (int) (val(
                            "SELECT id FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY id ASC LIMIT 1",
                            [$cid, $id],
                        ) ?? 0);
                    if ($next > 0) {
                        q(
                            "UPDATE pi_patient_guardians SET is_primary=1,updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?",
                            [$next, $cid, $id],
                        );
                    }
                    audit("responsavel_legal_removido", "paciente", $id, [
                        "patient_name" => (string) $p["full_name"],
                        "responsavel" => (string) $old["full_name"],
                        "campos" => ["Responsável Legal"],
                        "audit_body" =>
                            "Responsável Legal removido da ficha do paciente.",
                    ]);
                    flash(
                        patient_is_minor($p)
                            ? "Responsável removido. Como o paciente é menor, a ficha volta a ficar incompleta até novo vínculo."
                            : "Responsável removido.",
                        "warn",
                    );
                }
                redirect("patient", ["id" => $id]);
            }
            if ($act === "issue_patient_document") {
                if (!can("documents")) {
                    flash(
                        "Este perfil não tem permissão para emitir documentos.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                $block = patient_sensitive_block_reason($cid, $id);
                if ($block !== null) {
                    flash($block, "bad");
                    redirect("patient", ["id" => $id]);
                }
                try {
                    $docAppointmentId = (int) ($_POST["appointment_id"] ?? 0);
                    $docId = create_document_draft_from_template(
                        $c,
                        (int) ($_POST["template_id"] ?? 0),
                        $id,
                        $docAppointmentId,
                    );
                    flash(
                        "Rascunho criado pela Ficha do Paciente. Revise, pré-visualize e confirme a emissão.",
                    );
                    redirect("documents", ["doc" => $docId]);
                } catch (Throwable $e) {
                    flash(
                        app_public_error_message(
                            $e,
                            "Não foi possível concluir a operação na ficha do paciente.",
                        ),
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
            }
            if ($act === "update_patient_contact") {
                if (!$canUpdatePatientContact) {
                    flash(
                        "Este perfil não tem permissão para atualizar dados de contato do paciente.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                $contact = patient_invoice_contact_from_post();
                $loc = patient_location_from_post($cid);
                PatientComposition::updateContact(
                    $cid,
                    $id,
                    (int) $c["user"]["id"],
                    [
                        "phone" => $contact["phone"],
                        "email" => $contact["email"],
                        "address" => $loc["address"],
                        "address_zip" => $loc["address_zip"],
                        "address_number" => $loc["address_number"],
                        "address_neighborhood" => $loc["address_neighborhood"],
                        "address_complement" => $loc["address_complement"],
                        "address_state" => $loc["address_state"],
                        "address_city" => $loc["address_city"],
                        "address_city_ibge" => $loc["address_city_ibge"],
                    ],
                );
                audit("paciente_contato_atualizado", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "campos" => [
                        "Telefone",
                        "E-mail",
                        "CEP",
                        "Endereço",
                        "Número",
                        "Bairro",
                        "Cidade",
                        "Estado",
                    ],
                    "audit_body" =>
                        "Telefone, e-mail e endereço fiscal do paciente foram atualizados pela equipe de recepção.",
                ]);
                flash("Dados de contato atualizados.");
                redirect("patient", ["id" => $id]);
            }
            if ($act === "patient_revenue_receive") {
                if (!in_array($role, ["recepcionista", "gerente"], true)) {
                    flash(
                        "Este perfil não pode informar recebimentos do paciente.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                ensure_financial_operational_schema();
                $rid = (int) ($_POST["revenue_id"] ?? 0);
                try {
                    $uid = (int) $c["user"]["id"];
                    $receipt = FinancialComposition::receivePatientRevenue(
                        $cid,
                        $id,
                        $rid,
                        $uid,
                        $role,
                    );
                    if ((string) ($receipt["status"] ?? "") !== "received") {
                        flash("Escolha uma cobrança pendente do paciente.", "bad");
                        redirect("patient", ["id" => $id]);
                    }
                    audit("receita_recebida", "financeiro", $rid, [
                        "patient_link_id" => $id,
                        "patient_name" => (string) $p["full_name"],
                        "valor" => (int) ($receipt["amount_cents"] ?? 0),
                        "title" => (string) ($receipt["title"] ?? ""),
                        "audit_body" =>
                            "Recebimento do paciente informado pela ficha do paciente e registrado no local financeiro do usuário responsável.",
                    ]);
                    flash("Recebimento informado.");
                } catch (Throwable $e) {
                    flash(
                        app_public_error_message(
                            $e,
                            "Não foi possível concluir a operação na ficha do paciente.",
                        ),
                        "bad",
                    );
                }
                redirect("patient", ["id" => $id]);
            }
            if ($act === "finish_active_appointment") {
                $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
                $returnDay = (string) ($_POST["return_day"] ?? "");
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnDay)) {
                    $returnDay = app_today_in_timezone($cid, $c);
                }
                $returnDoctor = (int) ($_POST["return_doctor"] ?? 0);
                $returnParams = ["d" => $returnDay];
                if ($returnDoctor > 0) {
                    $returnParams["doctor"] = $returnDoctor;
                }
                $isDoctorRole = function_exists("appointment_journey_role_matches")
                    ? appointment_journey_role_matches($role, "medico")
                    : $role === "medico";
                if (!$isDoctorRole) {
                    flash(
                        "Apenas o ambiente Profissional pode concluir o atendimento.",
                        "bad",
                    );
                    redirect("patient", [
                        "id" => $id,
                        "appointment_id" => $appointmentId,
                        "return_day" => $returnDay,
                        "return_doctor" => $returnDoctor,
                    ]);
                }
                $appt = one(
                    "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? AND patient_link_id=?",
                    [$appointmentId, $cid, $id],
                );
                if (!$appt) {
                    flash("Atendimento ativo não encontrado.", "bad");
                    redirect("patient", ["id" => $id]);
                }
                if (
                    (int) ($appt["doctor_user_id"] ?? 0) > 0 &&
                    (int) $appt["doctor_user_id"] !== (int) $c["user"]["id"]
                ) {
                    flash(
                        "Este atendimento está vinculado a outro profissional.",
                        "bad",
                    );
                    redirect("patient", [
                        "id" => $id,
                        "appointment_id" => $appointmentId,
                        "return_day" => $returnDay,
                        "return_doctor" => $returnDoctor,
                    ]);
                }
                $apptCode = function_exists("appointment_status_code")
                    ? appointment_status_code($appt)
                    : ((string) ($appt["status"] ?? ""));
                if (
                    $apptCode !== "em_atendimento" &&
                    empty($appt["consultation_started_at"])
                ) {
                    flash("Este atendimento ainda não está em execução.", "bad");
                    redirect("patient", [
                        "id" => $id,
                        "appointment_id" => $appointmentId,
                        "return_day" => $returnDay,
                        "return_doctor" => $returnDoctor,
                    ]);
                }
                if (function_exists("workflow_on_appointment_finished")) {
                    workflow_on_appointment_finished(
                        $cid,
                        $appointmentId,
                        $id,
                        (int) $c["user"]["id"],
                        "ficha_atendimento",
                    );
                } else {
                    $stmt = q(
                        "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), consultation_finished_at=COALESCE(consultation_finished_at,NOW()), status='atendimento_concluido', updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=? AND status='em_atendimento' AND consultation_finished_at IS NULL",
                        [$appointmentId, $cid, $id],
                    );
                    if ($stmt->rowCount() <= 0) {
                        flash(
                            "A jornada foi atualizada por outra ação. Reabra a ficha e tente novamente.",
                            "bad",
                        );
                        redirect("patient", ["id" => $id]);
                    }
                }
                q(
                    "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','em_andamento','aguardando')",
                    [(int) $c["user"]["id"], $cid, $appointmentId],
                );
                audit("consulta_concluida_ficha", "consulta", $appointmentId, [
                    "patient_name" => (string) $p["full_name"],
                    "patient_link_id" => $id,
                    "audit_body" =>
                        "Profissional concluiu o atendimento pela Ficha do Paciente e retornou ao Painel da Agenda.",
                ]);
                flash(
                    "Atendimento concluído. Próxima medida: Recepção finaliza a saída do paciente.",
                );
                redirect("appointments", $returnParams);
            }
            if (!$canEditPatient) {
                flash(
                    "Apenas o profissional responsável pode alterar dados da Ficha do Paciente.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            if ($act === "create_patient_tab") {
                $label = patient_tab_label_clean(
                    (string) ($_POST["tab_label"] ?? ""),
                );
                $iconName = normalize_patient_tab_icon(
                    (string) ($_POST["tab_icon"] ?? "clinical_notes"),
                );
                if ($label === "") {
                    flash("Informe o nome da nova aba.", "bad");
                    redirect("patient", ["id" => $id]);
                }
                $command = PatientComposition::createTab(
                    $cid,
                    $id,
                    $label,
                    $iconName,
                    (int) $c["user"]["id"],
                );
                if ((string) ($command["status"] ?? "") === "duplicate") {
                    flash("Este paciente já possui uma aba com esse nome.", "bad");
                    redirect("patient", ["id" => $id]);
                }
                $tabId = (int) ($command["id"] ?? 0);
                audit("aba_paciente_criada", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "titulo" => $label,
                    "icone" => $iconName,
                    "campos" => ["Nova aba da ficha"],
                    "audit_body" =>
                        "Uma aba extra foi criada na ficha deste paciente.",
                ]);
                flash(
                    "Aba criada. Agora ela aparece na ficha e pode receber anotações.",
                );
                redirect("patient", ["id" => $id]);
            }
            if ($act === "update_patient") {
                $name = mb_trim((string) ($_POST["full_name"] ?? ""));
                $cpf = only_digits((string) ($_POST["cpf"] ?? ""));
                $birth = mb_trim((string) ($_POST["birth_date"] ?? ""));
                if ($name === "") {
                    flash(
                        "Informe o nome completo para atualizar o cadastro.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                try {
                    $identity = person_identity_immutable_values(
                        (int) $p["person_id"],
                        $cpf,
                        $birth,
                        true,
                    );
                    $cpf = (string) $identity["cpf"];
                    $birth = (string) $identity["birth_date"];
                } catch (Throwable $immutableError) {
                    flash(
                        app_public_error_message(
                            $immutableError,
                            "CPF e data de nascimento não podem ser alterados por esta operação.",
                        ),
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                $cpfOwner =
                    (int) (val(
                        "SELECT id FROM pi_persons WHERE cpf=? AND id<>? LIMIT 1",
                        [$cpf, (int) $p["person_id"]],
                    ) ?? 0);
                if ($cpfOwner > 0) {
                    flash("Este CPF já pertence a outra pessoa cadastrada.", "bad");
                    redirect("patient", ["id" => $id]);
                }
                $nameChanged = $name !== mb_trim((string) ($p["full_name"] ?? ""));
                if (
                    $nameChanged &&
                    person_has_other_clinic_links((int) $p["person_id"], $cid)
                ) {
                    flash(
                        "Esta pessoa também possui vínculo com outra clínica. Para proteger o isolamento entre clínicas, o nome não pode ser alterado por esta ficha. Atualize apenas telefone, e-mail, endereço e notas locais.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                q(
                    "UPDATE pi_persons SET full_name=?,cpf=?,birth_date=?,updated_at=NOW() WHERE id=?",
                    [$name, $cpf, $birth, (int) $p["person_id"]],
                );
                person_signature_refresh((int) $p["person_id"]);
                $loc = patient_location_from_post($cid);
                $contact = patient_invoice_contact_from_post();
                q(
                    "UPDATE pi_patients SET phone=?,email=?,address=?,address_zip=?,address_number=?,address_neighborhood=?,address_complement=?,address_state=?,address_city=?,address_city_ibge=?,notes=?,registration_needs_update=0,updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [
                        $contact["phone"],
                        $contact["email"],
                        $loc["address"],
                        $loc["address_zip"],
                        $loc["address_number"],
                        $loc["address_neighborhood"],
                        $loc["address_complement"],
                        $loc["address_state"],
                        $loc["address_city"],
                        $loc["address_city_ibge"],
                        mb_trim((string) ($_POST["notes"] ?? "")),
                        $id,
                        $cid,
                    ],
                );
                audit("paciente_salvo", "paciente", $id, [
                    "patient_name" => $name,
                    "acao_paciente" => "alterou",
                    "campos" => [
                        "Nome",
                        "Telefone",
                        "E-mail",
                        "CEP",
                        "Endereço",
                        "Número",
                        "Bairro",
                        "Cidade",
                        "Estado",
                        "Observações",
                    ],
                    "audit_body" =>
                        "Os dados cadastrais e fiscais do prontuário foram atualizados sem alterar CPF ou nascimento.",
                ]);
                flash("Ficha atualizada.");
                redirect("patient", ["id" => $id]);
            }
            if ($act === "delete_patient") {
                $future =
                    (int) (val(
                        "SELECT COUNT(*) FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND start_at>=NOW() AND status NOT IN ('cancelado')",
                        [$cid, $id],
                    ) ?? 0);
                if ($future > 0) {
                    flash(
                        "Não é possível excluir este paciente enquanto houver agendamento futuro ativo. Cancele ou reagende antes.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                q(
                    "UPDATE pi_patients SET active=0,deleted_at=NOW(),deleted_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [(int) $c["user"]["id"], $id, $cid],
                );
                audit("paciente_excluido", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "campos" => ["Exclusão lógica do paciente"],
                    "audit_body" =>
                        "Cadastro do paciente foi marcado como excluído e permanece preservado para restauração pelo Administrador.",
                ]);
                flash(
                    "Paciente marcado como excluído. O Administrador poderá restaurar o cadastro.",
                    "warn",
                );
                redirect("patients");
            }
            if ($act === "update_care") {
                $careId = (int) ($_POST["care_id"] ?? 0);
                $oldCare = one(
                    "SELECT c.id,c.record_type,c.title,cc.content FROM pi_care c INNER JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id WHERE c.id=? AND c.clinic_id=? AND c.patient_link_id=? AND c.deleted_at IS NULL",
                    [$careId, $cid, $id],
                );
                if (!$oldCare) {
                    flash("Anotação não encontrada.", "bad");
                    redirect("patient", ["id" => $id]);
                }
                $allowedUpdate = $extraTypeOptions;
                if (!array_key_exists($type, $allowedUpdate) || $content === "") {
                    flash(
                        "Informe uma aba válida e o conteúdo da anotação clínica.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
                q(
                    "INSERT INTO pi_care_versions (care_id,clinic_id,patient_link_id,old_record_type,old_title,old_content,changed_by,change_reason,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                    [
                        $careId,
                        $cid,
                        $id,
                        $oldCare["record_type"] ?? null,
                        $oldCare["title"] ?? null,
                        $oldCare["content"] ?? null,
                        (int) $c["user"]["id"],
                        mb_trim((string) ($_POST["change_reason"] ?? "")),
                    ],
                );
                q(
                    "UPDATE pi_care c INNER JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id SET c.record_type=?,c.title=?,cc.content=?,c.updated_at=NOW(),cc.updated_at=NOW() WHERE c.id=? AND c.clinic_id=? AND c.patient_link_id=? AND c.deleted_at IS NULL",
                    [$type, $title, $content, $careId, $cid, $id],
                );
                $changed = [];
                foreach (
                    [
                        "record_type" => "Tipo",
                        "title" => "Título",
                        "content" => "Conteúdo",
                    ]
                    as $k => $label
                ) {
                    if (
                        (string) ($oldCare[$k] ?? "") !==
                        (string) ($k === "record_type"
                            ? $type
                            : ($k === "title"
                                ? $title
                                : $content))
                    ) {
                        $changed[] = $label;
                    }
                }
                audit("prontuario_alterado", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "tipo" => $type,
                    "titulo" => $title,
                    "campos" => $changed ?: ["Anotação clínica"],
                    "audit_body" => audit_change_body(
                        $changed ?: ["Anotação clínica"],
                    ),
                ]);
                flash("Anotação atualizada.");
                redirect("patient", ["id" => $id]);
            }
            if ($act === "delete_care") {
                $careId = (int) ($_POST["care_id"] ?? 0);
                $oldCare = one(
                    "SELECT id,record_type,title FROM pi_care WHERE id=? AND clinic_id=? AND patient_link_id=? AND deleted_at IS NULL",
                    [$careId, $cid, $id],
                );
                if ($oldCare) {
                    q(
                        "UPDATE pi_care SET deleted_at=NOW(),deleted_by=? WHERE id=? AND clinic_id=? AND patient_link_id=? AND deleted_at IS NULL",
                        [(int) $c["user"]["id"], $careId, $cid, $id],
                    );
                    audit("prontuario_alterado", "paciente", $id, [
                        "patient_name" => (string) $p["full_name"],
                        "tipo" => (string) $oldCare["record_type"],
                        "titulo" => (string) $oldCare["title"],
                        "campos" => ["Exclusão lógica de anotação"],
                        "audit_body" =>
                            "Uma anotação do prontuário foi marcada como excluída e permanece preservada para restauração pelo Administrador.",
                    ]);
                    flash(
                        "Anotação marcada como excluída. O Administrador poderá restaurar.",
                        "warn",
                    );
                }
                redirect("patient", ["id" => $id]);
            }
            if (!array_key_exists($type, $extraTypeOptions)) {
                flash(
                    "Crie ou escolha uma aba extra deste paciente antes de salvar a anotação.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            if ($content === "") {
                flash("Informe o conteúdo da anotação clínica.", "bad");
                redirect("patient", ["id" => $id]);
            }
            $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
            $appointmentForAudit = null;
            if ($appointmentId > 0) {
                $appointmentForAudit = one(
                    "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? AND patient_link_id=?",
                    [$appointmentId, $cid, $id],
                );
                if (!$appointmentForAudit) {
                    $appointmentId = 0;
                    $appointmentForAudit = null;
                }
            }
            q(
                "INSERT INTO pi_care (clinic_id,patient_link_id,appointment_id,record_type,title,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    $id,
                    $appointmentId > 0 ? $appointmentId : null,
                    $type,
                    $title,
                    (int) $c["user"]["id"],
                ],
            );
            $careId = db_last_insert_id();
            q(
                "INSERT INTO pi_care_content (care_id,clinic_id,content) VALUES (?,?,?)",
                [$careId, $cid, $content],
            );
            if (
                $appointmentForAudit &&
                empty($appointmentForAudit["consultation_started_at"])
            ) {
                q(
                    "UPDATE pi_appointments SET consultation_started_at=NOW(), status=IF(status='agendado','em_atendimento',status), updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?",
                    [$appointmentId, $cid, $id],
                );
                $scheduled = app_storage_timestamp(
                    $appointmentForAudit["start_at"],
                );
                $delay = $scheduled
                    ? max(0, (int) floor((time() - $scheduled) / 60))
                    : null;
                audit("consulta_iniciada", "consulta", $appointmentId, [
                    "patient_name" => (string) $p["full_name"],
                    "patient_link_id" => $id,
                    "doctor_user_id" =>
                        $appointmentForAudit["doctor_user_id"] ?? null,
                    "start_at" => $appointmentForAudit["start_at"] ?? null,
                    "end_at" => $appointmentForAudit["end_at"] ?? null,
                    "reason" => $appointmentForAudit["reason"] ?? null,
                    "audit_body" =>
                        "A consulta foi iniciada" .
                        ($delay !== null
                            ? " com atraso registrado de " .
                                format_minutes($delay) .
                                " em relação ao horário agendado."
                            : "."),
                ]);
            }
            if (
                $appointmentForAudit &&
                $role === "medico" &&
                !empty($_POST["finish_appointment"]) &&
                empty($appointmentForAudit["consultation_finished_at"])
            ) {
                workflow_on_appointment_finished(
                    $cid,
                    $appointmentId,
                    $id,
                    (int) $c["user"]["id"],
                    "anotacao_clinica",
                );
                audit("consulta_concluida", "consulta", $appointmentId, [
                    "patient_name" => (string) $p["full_name"],
                    "patient_link_id" => $id,
                    "doctor_user_id" =>
                        $appointmentForAudit["doctor_user_id"] ?? null,
                    "start_at" => $appointmentForAudit["start_at"] ?? null,
                    "end_at" => $appointmentForAudit["end_at"] ?? null,
                    "reason" => $appointmentForAudit["reason"] ?? null,
                    "audit_body" =>
                        "O profissional concluiu o atendimento e o horário real de fim foi registrado. A Recepção recebeu recado automático para finalizar a saída do paciente.",
                ]);
            }
            counter_inc("care_records_total");
            clinic_metric_inc($cid, "care_records");
            $changedFields = ["Aba da ficha"];
            if ($title !== "") {
                $changedFields[] = "Título";
            }
            $changedFields[] = "Conteúdo";
            audit("prontuario_alterado", "paciente", $id, [
                "patient_name" => (string) $p["full_name"],
                "tipo" => $type,
                "titulo" => $title,
                "campos" => $changedFields,
                "audit_body" => audit_change_body($changedFields),
            ]);
            $_SESSION["skip_patient_view_audit_" . (int) $id] = 1;
            flash("Anotação incluída.");
            redirect("patient", ["id" => $id]);
        }
        $appts = q(
            "SELECT id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? ORDER BY start_at DESC LIMIT 20",
            [$cid, $id],
        )->fetchAll();
        $apptItems = patient_appointment_timeline_items($cid, $appts, $c);
        $patientDocOptions = can("documents")
            ? approved_document_template_options($c)
            : [];
        $patientDocItems = patient_document_timeline_items($c, $id, 80);
        $patientDocCount = count($patientDocItems);
        $patientDocForm = "";
        if (can("documents")) {
            $docEmitParams = ["emit" => 1, "patient_id" => $id];
            $docEmitAppointmentId = (int) ($_GET["appointment_id"] ?? 0);
            if ($docEmitAppointmentId > 0) {
                $docEmitParams["appointment_id"] = $docEmitAppointmentId;
            }
            $patientDocForm =
                '<div class="patient-empty-action patient-doc-create patient-doc-external-cta"><a class="primary small cmdlike" href="' .
                href("documents", $docEmitParams) .
                '">' .
                action_summary_label("Emitir Primeiro Documento", "description") .
                "</a></div>";
        }
        $activeAppointmentId = (int) ($_GET["appointment_id"] ?? 0);
        $activeAppointment = null;
        if ($role === "medico") {
            if ($activeAppointmentId > 0) {
                $activeAppointment = one(
                    "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? AND patient_link_id=?",
                    [$activeAppointmentId, $cid, $id],
                );
            }
            if (!$activeAppointment) {
                $activeAppointment = one(
                    "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND doctor_user_id=? AND consultation_started_at IS NOT NULL AND consultation_finished_at IS NULL AND status NOT IN ('cancelado','nao_compareceu','reagendado','finalizado') ORDER BY start_at DESC LIMIT 1",
                    [$cid, $id, (int) $c["user"]["id"]],
                );
            }
            if ($activeAppointment) {
                $activeCode = function_exists("appointment_status_code")
                    ? appointment_status_code($activeAppointment)
                    : (string) ($activeAppointment["status"] ?? "");
                if ($activeCode !== "em_atendimento") {
                    $activeAppointment = null;
                }
            }
        }
        $activeAppointmentPanel = "";
        if ($activeAppointment) {
            $activeReturnDay = (string) ($_GET["return_day"] ?? "");
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $activeReturnDay)) {
                $activeReturnDay = substr(
                    (string) ($activeAppointment["start_at"] ?? ""),
                    0,
                    10,
                );
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $activeReturnDay)) {
                $activeReturnDay = app_today_in_timezone($cid, $c);
            }
            $activeReturnDoctor =
                (int) ($_GET["return_doctor"] ??
                    ((int) ($activeAppointment["doctor_user_id"] ?? 0)));
            $activeAppointmentPanel =
                '<section class="patient-active-consultation" aria-label="Atendimento em andamento"><div class="patient-active-consultation-copy"><span class="eyebrow">Atendimento em andamento</span><h2>' .
                e((string) ($activeAppointment["reason"] ?: "Consulta")) .
                "</h2><p>A ficha permanece aberta durante o atendimento. Registre as anotações necessárias e conclua quando terminar.</p><small>" .
                e(dt_br($activeAppointment["start_at"])) .
                " · " .
                e(dt_br($activeAppointment["end_at"])) .
                '</small></div><form method="post" class="patient-active-consultation-actions">' .
                csrf_field() .
                '<input type="hidden" name="act" value="finish_active_appointment"><input type="hidden" name="appointment_id" value="' .
                (int) $activeAppointment["id"] .
                '"><input type="hidden" name="return_day" value="' .
                e($activeReturnDay) .
                '"><input type="hidden" name="return_doctor" value="' .
                (int) $activeReturnDoctor .
                '"><button class="primary" type="submit">' .
                icon("task_alt") .
                '<span>Concluir atendimento e voltar ao Painel</span></button><a class="ghost small" href="' .
                href("appointments", [
                    "d" => $activeReturnDay,
                    "doctor" => $activeReturnDoctor,
                ]) .
                '">' .
                icon("space_dashboard") .
                "<span>Voltar ao Painel</span></a></form></section>";
        }
        $consultStats = one(
            "SELECT COUNT(*) total, MIN(start_at) first_at FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND status NOT IN ('cancelado')",
            [$cid, $id],
        ) ?: ["total" => 0, "first_at" => null];
        $firstConsultationLabel = !empty($consultStats["first_at"])
            ? dt_card_full_br($consultStats["first_at"])
            : "Ainda não agendada";
        $totalConsultations = (int) ($consultStats["total"] ?? 0);
        $patientSummaryCards =
            '<div class="patient-profile-summary-cards patient-profile-footer-cards" aria-label="Resumo do cadastro do paciente"><article>' .
            icon("person_add") .
            "<span>Cadastro</span><b>" .
            e(dt_card_full_br($p["created_at"])) .
            "</b></article><article>" .
            icon("update") .
            "<span>Atualização</span><b>" .
            e(dt_card_full_br($p["updated_at"] ?: $p["created_at"])) .
            "</b></article><article>" .
            icon("event_available") .
            "<span>Primeira Consulta</span><b>" .
            e($firstConsultationLabel) .
            "</b></article><article>" .
            icon("monitoring") .
            "<span>Total de Consultas</span><b>" .
            (int) $totalConsultations .
            "</b></article></div>";
        $patientProfileOverview = patient_profile_overview(
            $p,
            $profileStatus,
            count($appts),
            (int) $patientDocCount,
            $totalConsultations,
            $firstConsultationLabel,
        );
        $patientReceptionItems = patient_reception_history_items($cid, $id, $p);
        $patientReceptionCount = count($patientReceptionItems);
        $patientReceptionPanel = patient_reception_history_panel(
            $patientReceptionItems,
        );
        $agendaEmptyAction = can("appointments")
            ? '<div class="empty patient-empty-cta"><a class="primary small" href="' .
                href("appointments", ["patient_id" => $id]) .
                '">' .
                icon("event_available") .
                "<span>Agendar Primeira Consulta</span></a></div>"
            : '<div class="empty">Nenhum agendamento encontrado para este paciente.</div>';
        ensure_financial_operational_schema();
        financial_ensure_default_accounts($cid, (int) $c["user"]["id"]);
        $pendingPatientRevenueCount =
            (int) (val(
                "SELECT COUNT(*) FROM pi_financial_revenues WHERE clinic_id=? AND patient_link_id=? AND status='prevista'",
                [$cid, $id],
            ) ?? 0);
        $showPatientFinance =
            $role === "gerente" ||
            ($role === "recepcionista" && $pendingPatientRevenueCount > 0);
        $patientFinanceRows = $showPatientFinance
            ? q(
                "SELECT r.id,r.appointment_id,r.title,r.amount_cents,r.status,r.payment_method,r.expected_at,r.received_at,r.account_id,a.name account_name FROM pi_financial_revenues r LEFT JOIN pi_financial_accounts a ON a.id=r.account_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.patient_link_id=? ORDER BY FIELD(r.status,'prevista','efetivada','cancelada'), COALESCE(r.expected_at,r.received_at,r.created_at) DESC, r.id DESC LIMIT 160",
                [$cid, $id],
            )->fetchAll()
            : [];
        $patientAccountOptions = $showPatientFinance
            ? financial_account_label_options($cid, "Conta de recebimento")
            : [];
        $patientFinanceList = "";
        foreach ($patientFinanceRows as $fr) {
            $date =
                $fr["status"] === "efetivada"
                    ? $fr["received_at"] ?? ""
                    : $fr["expected_at"] ?? "";
            $methodKey = (string) ($fr["payment_method"] ?? "");
            $methodLabel =
                $methodKey !== ""
                    ? payment_methods_options()[$methodKey] ?? $methodKey
                    : "";
            $patientFinanceList .=
                '<article class="finance-row finance-action-row patient-finance-row"><div><strong>' .
                e($fr["title"] ?: "Cobrança do paciente") .
                "</strong><small>" .
                e(
                    ($date ? date_br($date) : "sem data") .
                        " · " .
                        ($methodLabel !== "" ? $methodLabel . " · " : "") .
                        ($fr["account_name"] ?: "sem conta"),
                ) .
                "</small></div><b>" .
                money_br((int) $fr["amount_cents"]) .
                "</b>" .
                financial_status_pill(
                    (string) $fr["status"],
                    (string) ($fr["expected_at"] ?? ""),
                    "revenue",
                );
            if (
                $fr["status"] === "prevista" &&
                in_array($role, ["recepcionista", "gerente"], true)
            ) {
                $patientFinanceList .=
                    '<form method="post" class="finance-inline-form patient-finance-receive">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="patient_revenue_receive"><input type="hidden" name="revenue_id" value="' .
                    (int) $fr["id"] .
                    '"><button class="ghost small" type="submit">' .
                    icon("paid") .
                    "<span>Receber</span></button></form>";
            }
            $patientFinanceList .= "</article>";
        }
        $prefix = "pt" . (int) $id . "_";
        $financePanel = $showPatientFinance
            ? '<section class="patient-panel patient-panel-financeiro" role="tabpanel"><div class="patient-section-title"><div><h2>Financeiro</h2><p>' .
                ($role === "recepcionista"
                    ? "Cobranças a receber deste paciente. Informe a entrada e a conta de destino."
                    : "Pagamentos e cobranças vinculados a este paciente.") .
                "</p></div><span>" .
                (int) count($patientFinanceRows) .
                " item(ns)</span></div>" .
                ($patientFinanceList ?:
                    '<div class="empty">Nenhum pagamento vinculado a este paciente.</div>') .
                "</section>"
            : "";
        $financeInput = $showPatientFinance
            ? '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
                $prefix .
                'financeiro">'
            : "";
        $financeNav = $showPatientFinance
            ? '<label for="' .
                $prefix .
                'financeiro">' .
                icon("payments") .
                "<span><b>Financeiro</b></span>" .
                ($pendingPatientRevenueCount > 0
                    ? "<em>" . (int) $pendingPatientRevenueCount . "</em>"
                    : "") .
                "</label>"
            : "";
        $age = "";
        if (!empty($p["birth_date"])) {
            $ts = app_storage_timestamp($p["birth_date"]);
            if ($ts) {
                $age =
                    (string) max(0, (int) floor((time() - $ts) / 31557600)) .
                    " anos";
            }
        }
        if (!$canEditPatient) {
            $patientHeadActions =
                '<a class="ghost small" href="' .
                href("patients") .
                '">' .
                icon("arrow_back") .
                "<span>Voltar para Pacientes</span></a>";
            $hero =
                page_head(
                    (string) $p["full_name"],
                    "Ficha do paciente",
                    $patientHeadActions,
                ) . $patientProfileOverview;
            $dados =
                '<div class="patient-data-list"><div><span>Nome completo</span><b>' .
                e($p["full_name"]) .
                "</b></div><div><span>CPF</span><b>" .
                e(mask($p["cpf"] ?? "")) .
                "</b></div><div><span>Nascimento</span><b>" .
                date_br($p["birth_date"]) .
                ($age ? " · " . e($age) : "") .
                "</b></div><div><span>Telefone</span><b>" .
                e($p["phone"] ?: "não informado") .
                "</b></div><div><span>E-mail</span><b>" .
                e($p["email"] ?: "não informado") .
                "</b></div><div><span>Endereço</span><b>" .
                e($p["address"] ?: "não informado") .
                "</b></div><div><span>Cidade/Estado</span><b>" .
                e(
                    city_state_label(
                        $p["address_city"] ?? "",
                        $p["address_state"] ?? "",
                    ) ?:
                    "não informado",
                ) .
                "</b></div></div>";
            $patientContactEdit = $canUpdatePatientContact
                ? PatientViewComposition::contactEditForm($p, $cid)
                : "";
            $dataPanel =
                '<section class="patient-panel patient-panel-cadastro" role="tabpanel"><h2>Cadastro</h2><p class="muted-copy">Dados de identificação e contato do paciente.</p>' .
                $dados .
                $guardianCard .
                $patientContactEdit .
                $patientSummaryCards .
                "</section>";
            $agendaPanel =
                '<section class="patient-panel patient-panel-agenda" role="tabpanel"><div class="patient-section-title"><div><h2>Agenda</h2><p>Agendamentos vinculados a este paciente.</p></div><span>' .
                count($appts) .
                " item(ns)</span></div>" .
                ($apptItems ? timeline($apptItems, "") : $agendaEmptyAction) .
                "</section>";
            $docsPanel =
                '<section class="patient-panel patient-panel-documentos" role="tabpanel"><div class="patient-section-title"><div><h2>Documentos</h2><p>Últimos documentos emitidos para este paciente a partir dos modelos do módulo Documentos.</p></div><span>' .
                (int) $patientDocCount .
                " documento(s)</span></div>" .
                $patientDocForm .
                ($patientDocItems
                    ? '<hr class="soft-sep">' . timeline($patientDocItems, "")
                    : "") .
                "</section>";
            $tabs =
                '<section class="patient-tabs-shell patient-tabs-records" aria-label="Paciente em abas"><input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
                $prefix .
                'cadastro" checked><input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
                $prefix .
                'agenda"><input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
                $prefix .
                'documentos"><input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
                $prefix .
                'atendimentos">' .
                $financeInput .
                '<aside class="patient-tab-nav" aria-label="Seções do paciente"><label for="' .
                $prefix .
                'cadastro">' .
                icon("badge") .
                '<span><b>Cadastro</b></span></label><label for="' .
                $prefix .
                'agenda">' .
                icon("calendar_month") .
                "<span><b>Agenda</b></span><em>" .
                (int) count($appts) .
                '</em></label><label for="' .
                $prefix .
                'documentos">' .
                icon("description") .
                "<span><b>Documentos</b></span><em>" .
                (int) $patientDocCount .
                '</em></label><label for="' .
                $prefix .
                'atendimentos">' .
                icon("forum") .
                "<span><b>Atendimentos</b></span><em>" .
                (int) $patientReceptionCount .
                "</em></label>" .
                $financeNav .
                '</aside><div class="patient-tab-panels">' .
                $dataPanel .
                $agendaPanel .
                $docsPanel .
                $patientReceptionPanel .
                $financePanel .
                "</div></section>";
            page($p["full_name"], $hero . $activeAppointmentPanel . $tabs);
            return;
        }
        $viewAuditKey = "skip_patient_view_audit_" . (int) $id;
        if (!empty($_SESSION[$viewAuditKey])) {
            unset($_SESSION[$viewAuditKey]);
        } else {
            audit("prontuario_visualizado", "paciente", $id, [
                "patient_name" => (string) $p["full_name"],
                "audit_body" => "Nenhuma informação foi alterada.",
            ]);
        }
        $rows = q(
            "SELECT c.id,c.record_type,c.title,cc.content,c.created_by,c.created_at FROM pi_care c INNER JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id WHERE c.clinic_id=? AND c.patient_link_id=? AND c.deleted_at IS NULL AND c.record_type<>'receita' ORDER BY c.id DESC LIMIT 200",
            [$cid, $id],
        )->fetchAll();
        $authors = fetch_map("pi_users", int_ids($rows, "created_by"), "id,name");
        $recordItems = [];
        $counts = [];
        foreach ($extraTabs as $tab) {
            $recordItems[(string) $tab["record_type"]] = [];
            $counts[(string) $tab["record_type"]] = 0;
        }
        foreach ($rows as $r) {
            $rt = (string) $r["record_type"];
            $tab = $extraTypeMap[$rt] ?? null;
            if (!$tab) {
                continue;
            }
            $label = (string) $tab["label"];
            $ic = (string) $tab["icon_name"];
            $editOptions = $extraTypeOptions;
            $html =
                '<details class="care-edit"><summary class="ghost small cmdlike">' .
                action_summary_label("Alterar", "edit") .
                '</summary><form method="post" class="compact">' .
                csrf_field() .
                '<input type="hidden" name="act" value="update_care"><input type="hidden" name="care_id" value="' .
                (int) $r["id"] .
                '">' .
                select_label("Tipo", "record_type", $editOptions, $rt, "required") .
                form_row("Título", input("title", "text", $r["title"] ?? "")) .
                form_row(
                    "Conteúdo",
                    textarea("content", $r["content"], 'required rows="6"'),
                ) .
                form_actions("Salvar anotação") .
                '</form><form method="post" class="inline danger-zone" onsubmit="return confirm(\'Excluir esta anotação?\')">' .
                csrf_field() .
                '<input type="hidden" name="act" value="delete_care"><input type="hidden" name="care_id" value="' .
                (int) $r["id"] .
                '"><button class="danger small" type="submit">' .
                icon("delete") .
                "<span>Excluir</span></button></form></details>";
            $item = [
                "icon" => $ic,
                "time" => dt_br($r["created_at"]),
                "title" =>
                    ($r["title"] ?: $label) .
                    " · " .
                    first_name(
                        $authors[(int) ($r["created_by"] ?? 0)]["name"] ?? "",
                    ),
                "body" => $r["content"],
                "meta" => $label,
                "html" => $html,
            ];
            $counts[$rt]++;
            $recordItems[$rt][] = $item;
        }
        $apptLinkRows = q(
            "SELECT id,start_at,status,reason FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND start_at>=DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND start_at<DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY start_at ASC LIMIT 20",
            [$cid, $id],
        )->fetchAll();
        $apptOptions = ["" => "Sem vínculo com agendamento"];
        $defaultAppointment = null;
        foreach ($apptLinkRows as $a) {
            $label =
                dt_br($a["start_at"]) .
                " · " .
                ($a["reason"] ?? "" ?: "Consulta") .
                " · " .
                ($a["status"] ?? "" ?: "agendado");
            $apptOptions[(int) $a["id"]] = $label;
            if (
                $defaultAppointment === null &&
                app_storage_timestamp($a["start_at"]) >= strtotime("today") &&
                app_storage_timestamp($a["start_at"]) < strtotime("tomorrow")
            ) {
                $defaultAppointment = (int) $a["id"];
            }
        }
        $annotationForm = function (
            ?string $selectedType = null,
            string $submit = "Salvar anotação",
        ) use (
            $apptOptions,
            $defaultAppointment,
            $extraTypeOptions,
            $role,
        ): string {
    
            if (!$extraTypeOptions) {
                return '<div class="empty">Crie uma aba extra para este paciente antes de registrar anotações clínicas. Cada aba criada passa a aparecer como Tipo da anotação.</div>';
            }
            if (
                $selectedType === null ||
                !array_key_exists($selectedType, $extraTypeOptions)
            ) {
                $selectedType = array_key_first($extraTypeOptions);
            }
            return '<form method="post" class="compact patient-record-form">' .
                csrf_field() .
                select_label(
                    "Consulta vinculada",
                    "appointment_id",
                    $apptOptions,
                    $defaultAppointment,
                ) .
                ($role === "medico"
                    ? '<label class="checkline"><input type="checkbox" name="finish_appointment" value="1"><span>Concluir atendimento ao salvar esta anotação</span></label>'
                    : "") .
                select_label(
                    "Tipo",
                    "record_type",
                    $extraTypeOptions,
                    $selectedType,
                    "required",
                ) .
                form_row("Título", input("title")) .
                form_row("Conteúdo", textarea("content", "", 'required rows="7"')) .
                form_actions($submit) .
                "</form>";
        };
        $hero =
            '<div class="patient-hero patient-hero-tabs"><div><span class="eyebrow">Ficha do paciente</span><h1>' .
            e($p["full_name"]) .
            "</h1><p>Nascimento: " .
            date_br($p["birth_date"]) .
            ($age ? " · " . $age : "") .
            " · CPF " .
            e(mask($p["cpf"] ?? "")) .
            '</p></div><div class="patient-hero-actions"><a class="ghost small" href="' .
            href("patients") .
            '">' .
            icon("arrow_back") .
            '<span>Voltar para Pacientes</span></a><label class="primary small patient-new-tab-btn" for="' .
            $prefix .
            'novaanotacao">' .
            icon("edit_note") .
            "<span>Nova anotação</span></label></div></div>";
        $dados =
            '<div class="patient-data-list">' .
            "<div><span>Nome completo</span><b>" .
            e($p["full_name"]) .
            "</b></div>" .
            "<div><span>CPF</span><b>" .
            e(mask($p["cpf"] ?? "")) .
            "</b></div>" .
            "<div><span>Nascimento</span><b>" .
            date_br($p["birth_date"]) .
            ($age ? " · " . e($age) : "") .
            "</b></div>" .
            "<div><span>Telefone</span><b>" .
            e($p["phone"] ?: "não informado") .
            "</b></div>" .
            "<div><span>E-mail</span><b>" .
            e($p["email"] ?: "não informado") .
            "</b></div>" .
            "<div><span>CEP</span><b>" .
            e(mask_cep((string) ($p["address_zip"] ?? "")) ?: "não informado") .
            "</b></div><div><span>Endereço</span><b>" .
            e(
                trim(
                    ($p["address"] ?: "") .
                        " " .
                        ($p["address_number"] ? ", " . $p["address_number"] : ""),
                ) ?:
                "não informado",
            ) .
            "</b></div><div><span>Bairro</span><b>" .
            e($p["address_neighborhood"] ?: "não informado") .
            "</b></div><div><span>Cidade/Estado</span><b>" .
            e(
                city_state_label(
                    $p["address_city"] ?? "",
                    $p["address_state"] ?? "",
                ) ?:
                "não informado",
            ) .
            "</b></div>" .
            "</div>";
        $patientContactEdit = $canUpdatePatientContact
            ? PatientViewComposition::contactEditForm($p, $cid)
            : "";
        $patientCpfField =
            mb_trim((string) ($p["cpf"] ?? "")) !== ""
                ? '<input type="text" value="' .
                    e(mask($p["cpf"] ?? "")) .
                    '" readonly aria-readonly="true" tabindex="-1" autocomplete="off">'
                : input(
                    "cpf",
                    "text",
                    mask($p["cpf"] ?? ""),
                    'required inputmode="numeric" data-cpf-mask',
                );
        $patientBirthField =
            mb_trim((string) ($p["birth_date"] ?? "")) !== ""
                ? '<input type="date" value="' .
                    e(app_date_input_from_storage($p["birth_date"] ?? "")) .
                    '" readonly aria-readonly="true" tabindex="-1" autocomplete="off">'
                : input("birth_date", "date", $p["birth_date"], "required");
        $patientEdit =
            '<details class="patient-edit"><summary class="primary small cmdlike">' .
            action_summary_label("Alterar cadastro", "edit") .
            '</summary><form method="post" class="compact patient-record-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="update_patient">' .
            form_row(
                "Nome completo",
                input("full_name", "text", $p["full_name"], "required"),
            ) .
            '<div class="two">' .
            form_row("CPF", $patientCpfField) .
            form_row("Nascimento", $patientBirthField) .
            "</div>" .
            '<div class="two">' .
            form_row(
                "Telefone",
                input("phone", "text", $p["phone"], 'required inputmode="tel"'),
            ) .
            form_row("E-mail", input("email", "email", $p["email"], "required")) .
            "</div>" .
            patient_address_fields($cid, $p) .
            form_row(
                "Observações importantes:",
                textarea("notes", $p["notes"], 'rows="5"'),
            ) .
            form_actions("Salvar cadastro") .
            '</form></details><form method="post" class="inline danger-zone" onsubmit="return confirm(\'Marcar este paciente como excluído? O Administrador poderá restaurar.\')">' .
            csrf_field() .
            '<input type="hidden" name="act" value="delete_patient"><button class="danger small" type="submit">' .
            icon("delete") .
            "<span>Excluir paciente</span></button></form>";
        $patientUpdateNotice = !empty($p["registration_needs_update"])
            ? '<div class="flash warn">Cadastro recuperado. Revise e atualize os dados cadastrais.</div>'
            : "";
        $patientStatusNotice =
            $profileStatus["level"] !== "ok"
                ? '<div class="flash ' .
                    e($profileStatus["level"]) .
                    '"><strong>' .
                    e($profileStatus["label"]) .
                    ".</strong> " .
                    e($profileStatus["message"]) .
                    "</div>"
                : "";
        $dataPanel =
            '<section class="patient-panel patient-panel-cadastro" role="tabpanel"><h2>Cadastro</h2><p class="muted-copy">Dados fixos da ficha do paciente. Esta aba não pode ser removida.</p>' .
            $dados .
            $patientStatusNotice .
            $patientUpdateNotice .
            $guardianCard .
            $patientEdit .
            $patientSummaryCards .
            "</section>";
        $agendaPanel =
            '<section class="patient-panel patient-panel-agenda" role="tabpanel"><div class="patient-section-title"><div><h2>Agenda</h2><p>Agendamentos vinculados a este paciente. Esta aba é fixa.</p></div><span>' .
            count($appts) .
            " item(ns)</span></div>" .
            ($apptItems ? timeline($apptItems, "") : $agendaEmptyAction) .
            "</section>";
        $docsPanel =
            '<section class="patient-panel patient-panel-documentos" role="tabpanel"><div class="patient-section-title"><div><h2>Documentos</h2><p>Crie recibos, atestados, receitas e declarações a partir de modelos aprovados. Esta aba é fixa.</p></div><span>' .
            (int) $patientDocCount .
            " documento(s)</span></div>" .
            $patientDocForm .
            ($patientDocItems
                ? '<hr class="soft-sep">' . timeline($patientDocItems, "")
                : "") .
            "</section>";
        $extraInputs = "";
        $extraNav = "";
        $extraPanels = "";
        foreach ($extraTabs as $tab) {
            $key = (string) $tab["tab_key"];
            $rt = (string) $tab["record_type"];
            $count = (int) ($counts[$rt] ?? 0);
            $extraInputs .=
                '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
                $prefix .
                $key .
                '">';
            $extraNav .=
                '<label for="' .
                $prefix .
                $key .
                '">' .
                icon((string) $tab["icon_name"]) .
                "<span><b>" .
                e($tab["label"]) .
                "</b></span><em>" .
                $count .
                "</em></label>";
            $extraPanels .=
                '<section class="patient-panel patient-panel-' .
                $key .
                '" role="tabpanel"><div class="patient-section-title"><div><h2>' .
                e($tab["label"]) .
                "</h2><p>Anotações desta aba, em ordem de inclusão.</p></div><span>" .
                $count .
                ' anotação(ões)</span></div><details class="patient-inline-note"><summary class="ghost small cmdlike">' .
                action_summary_label("Nova anotação nesta aba", "edit_note") .
                "</summary>" .
                $annotationForm($rt, "Salvar em " . (string) $tab["label"]) .
                "</details>" .
                timeline($recordItems[$rt] ?? [], "Nenhuma anotação nesta aba.") .
                "</section>";
        }
        $newRecordPanel =
            '<section class="patient-panel patient-panel-novaanotacao" role="tabpanel"><h2>Nova anotação</h2><p class="muted-copy">O campo Tipo agora usa as abas extras criadas para este paciente específico.</p>' .
            $annotationForm(null) .
            "</section>";
        $newTabPanel =
            '<section class="patient-panel patient-panel-novaaba" role="tabpanel"><h2>Nova Aba</h2><p class="muted-copy">Crie uma área própria da ficha deste paciente. Depois disso, ela aparecerá na navegação e no campo Tipo das novas anotações.</p><form method="post" class="compact patient-record-form patient-extra-tab-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="create_patient_tab">' .
            form_row(
                "Nome exibido da aba",
                input(
                    "tab_label",
                    "text",
                    "",
                    'required maxlength="60" placeholder="Ex.: Exames, Dor, Evolução familiar"',
                ),
            ) .
            '<div class="settings-section full"><h3>Ícone da aba</h3>' .
            patient_tab_icon_picker("clinical_notes") .
            "</div>" .
            form_actions("Criar aba") .
            "</form></section>";
        $tabs =
            '<section class="patient-tabs-shell patient-tabs-records patient-crown-record" aria-label="Ficha do paciente em abas">' .
            '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'cadastro" checked>' .
            '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'agenda">' .
            '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'documentos">' .
            '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'atendimentos">' .
            $financeInput .
            $extraInputs .
            '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'novaanotacao">' .
            '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'novaaba">' .
            '<aside class="patient-tab-nav" aria-label="Seções da ficha">' .
            '<label for="' .
            $prefix .
            'cadastro">' .
            icon("badge") .
            "<span><b>Cadastro</b></span></label>" .
            '<label for="' .
            $prefix .
            'agenda">' .
            icon("calendar_month") .
            "<span><b>Agenda</b></span><em>" .
            (int) count($appts) .
            "</em></label>" .
            '<label for="' .
            $prefix .
            'documentos">' .
            icon("description") .
            "<span><b>Documentos</b></span><em>" .
            (int) $patientDocCount .
            "</em></label>" .
            '<label for="' .
            $prefix .
            'atendimentos">' .
            icon("forum") .
            "<span><b>Atendimentos</b></span><em>" .
            (int) $patientReceptionCount .
            "</em></label>" .
            $financeNav .
            $extraNav .
            '<label class="patient-tab-add-new" for="' .
            $prefix .
            'novaaba">' .
            icon("add_circle") .
            "<span><b>Nova Aba</b></span></label>" .
            '</aside><div class="patient-tab-panels">' .
            $dataPanel .
            $agendaPanel .
            $docsPanel .
            $patientReceptionPanel .
            $financePanel .
            $extraPanels .
            $newRecordPanel .
            $newTabPanel .
            "</div></section>";
        $patientHeadActions =
            '<span class="pill ' .
            e($profileStatus["level"]) .
            '">' .
            e($profileStatus["label"]) .
            '</span><a class="ghost small" href="' .
            href("patients") .
            '">' .
            icon("arrow_back") .
            '<span>Voltar para Pacientes</span></a><label class="primary small patient-new-tab-btn" for="' .
            $prefix .
            'novaanotacao">' .
            icon("edit_note") .
            "<span>Nova anotação</span></label>";
        page(
            $p["full_name"],
            page_head(
                (string) $p["full_name"],
                "Ficha do paciente",
                $patientHeadActions,
            ) .
                $patientProfileOverview .
                $activeAppointmentPanel .
                $tabs,
        );
    
    }
}
