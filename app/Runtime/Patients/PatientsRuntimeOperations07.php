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
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("patients");
        $cid = (int) $c["clinic_id"];
        $id = (int) ($_GET["id"] ?? 0);
        \Prontoo\Runtime\Operational\OperationalComposition::patients()->ensureSchema("patient_tabs");
        \Prontoo\Runtime\Operational\OperationalComposition::patients()->ensureSchema("patient_guardians");
        $p = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.01', [$id, $cid], []);
        if ($p) {
            $ps =
                \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.02', [
                    (int) $p["person_id"],
                ], []) ?:
                [];
            $p += $ps;
        }
        if (!$p) {
            http_response_code(404);
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Paciente", '<div class="empty">Paciente não encontrado.</div>');
            return;
        }
        if ((int) ($p["active"] ?? 1) !== 1) {
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Paciente excluído",
                '<div class="empty"><h2>Cadastro excluído</h2><p>Este paciente foi marcado como excluído e só pode ser restaurado pelo Administrador.</p><p><a class="ghost" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patients") .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
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
        $canManageGuardian = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("patients");
        $guardians = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_legal_guardians($cid, $id);
        $profileStatus = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_profile_status($p, $guardians);
        $guardianCard = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_legal_guardian_card(
            $cid,
            $id,
            $p,
            $guardians,
            $canManageGuardian,
        );
        $extraTabs = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_extra_tabs($cid, $id);
        $extraTypeOptions = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_tab_record_options($extraTabs);
        $extraTypeMap = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_tab_map_by_type($extraTabs);
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "record");
            $type = (string) ($_POST["record_type"] ?? "");
            $title = mb_trim((string) ($_POST["title"] ?? ""));
            $content = mb_trim((string) ($_POST["content"] ?? ""));
            if ($act === "save_legal_guardian") {
                if (!$canManageGuardian) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Este perfil não tem permissão para alterar o responsável legal.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                $guardianId = (int) ($_POST["guardian_id"] ?? 0);
                $gName = mb_trim((string) ($_POST["guardian_name"] ?? ""));
                $gCpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_POST["guardian_cpf"] ?? ""));
                $gRel = \Prontoo\Domain\Patients\PatientsDomainOperations01::normalize_guardian_relationship(
                    (string) ($_POST["guardian_relationship"] ?? "outro"),
                );
                $gPhone = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($_POST["guardian_phone"] ?? ""));
                $gEmail = mb_trim((string) ($_POST["guardian_email"] ?? ""));
                $gDoc = mb_trim((string) ($_POST["guardian_document_note"] ?? ""));
                $gNotes = mb_trim((string) ($_POST["guardian_notes"] ?? ""));
                $makePrimary =
                    !empty($_POST["guardian_primary"]) ||
                    !\Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_has_legal_guardian($cid, $id);
                if ($gName === "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o nome completo do responsável legal.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($gCpf)) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe um CPF válido para o responsável legal.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                if ($guardianId > 0) {
                    $owned = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.03', [$guardianId, $cid, $id], []);
                    if (!$owned) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Responsável legal não encontrado.", "bad");
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                    }
                }
                $dup =
                    (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.07.page_patient.04', [$cid, $id, $gCpf, $guardianId], []) ?? 0);
                if ($dup > 0) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Este CPF já está vinculado como responsável deste paciente.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                if ($makePrimary) {
                    \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.05', [$cid, $id], []);
                }
                if ($guardianId > 0) {
                    \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.06', [
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
                        ], []);
                } else {
                    \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.07', [
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
                        ], []);
                    $guardianId = \Prontoo\Runtime\Operational\OperationalComposition::patients()->lastInsertId();
                }
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("responsavel_legal_salvo", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "responsavel" => $gName,
                    "vinculo" => $gRel,
                    "campos" => ["Responsável Legal"],
                    "audit_body" =>
                        "Responsável Legal vinculado ou atualizado na ficha do paciente.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Responsável Legal salvo.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            if ($act === "delete_legal_guardian") {
                if (!$canManageGuardian) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Este perfil não tem permissão para remover responsável legal.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                $guardianId = (int) ($_POST["guardian_id"] ?? 0);
                $old = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.08', [$guardianId, $cid, $id], []);
                if ($old) {
                    \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.09', [$guardianId, $cid, $id], []);
                    $next =
                        (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.07.page_patient.10', [$cid, $id], []) ?? 0);
                    if ($next > 0) {
                        \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.11', [$next, $cid, $id], []);
                    }
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("responsavel_legal_removido", "paciente", $id, [
                        "patient_name" => (string) $p["full_name"],
                        "responsavel" => (string) $old["full_name"],
                        "campos" => ["Responsável Legal"],
                        "audit_body" =>
                            "Responsável Legal removido da ficha do paciente.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_is_minor($p)
                            ? "Responsável removido. Como o paciente é menor, a ficha volta a ficar incompleta até novo vínculo."
                            : "Responsável removido.",
                        "warn",
                    );
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            if ($act === "issue_patient_document") {
                if (!\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("documents")) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Este perfil não tem permissão para emitir documentos.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                $block = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_sensitive_block_reason($cid, $id);
                if ($block !== null) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($block, "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                try {
                    $docAppointmentId = (int) ($_POST["appointment_id"] ?? 0);
                    $docId = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations01::create_document_draft_from_template(
                        $c,
                        (int) ($_POST["template_id"] ?? 0),
                        $id,
                        $docAppointmentId,
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Rascunho criado pela Ficha do Paciente. Revise, pré-visualize e confirme a emissão.",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("documents", ["doc" => $docId]);
                } catch (Throwable $e) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                            $e,
                            "Não foi possível concluir a operação na ficha do paciente.",
                        ),
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
            }
            if ($act === "update_patient_contact") {
                if (!$canUpdatePatientContact) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Este perfil não tem permissão para atualizar dados de contato do paciente.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                $contact = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_invoice_contact_from_post();
                $loc = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_location_from_post($cid);
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
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("paciente_contato_atualizado", "paciente", $id, [
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
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Dados de contato atualizados.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            if ($act === "patient_revenue_receive") {
                if (!in_array($role, ["recepcionista", "gerente"], true)) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Este perfil não pode informar recebimentos do paciente.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                \Prontoo\Runtime\Operational\OperationalComposition::patients()->ensureSchema("financial_operational");
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
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Escolha uma cobrança pendente do paciente.", "bad");
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                    }
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("receita_recebida", "financeiro", $rid, [
                        "patient_link_id" => $id,
                        "patient_name" => (string) $p["full_name"],
                        "valor" => (int) ($receipt["amount_cents"] ?? 0),
                        "title" => (string) ($receipt["title"] ?? ""),
                        "audit_body" =>
                            "Recebimento do paciente informado pela ficha do paciente e registrado no local financeiro do usuário responsável.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Recebimento informado.");
                } catch (Throwable $e) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                            $e,
                            "Não foi possível concluir a operação na ficha do paciente.",
                        ),
                        "bad",
                    );
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            if ($act === "finish_active_appointment") {
                $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
                $returnDay = (string) ($_POST["return_day"] ?? "");
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnDay)) {
                    $returnDay = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c);
                }
                $returnDoctor = (int) ($_POST["return_doctor"] ?? 0);
                $returnParams = ["d" => $returnDay];
                if ($returnDoctor > 0) {
                    $returnParams["doctor"] = $returnDoctor;
                }
                $isDoctorRole = is_callable([\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::class, 'appointment_journey_role_matches'])
                    ? \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "medico")
                    : $role === "medico";
                if (!$isDoctorRole) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Apenas o ambiente Profissional pode concluir o atendimento.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", [
                        "id" => $id,
                        "appointment_id" => $appointmentId,
                        "return_day" => $returnDay,
                        "return_doctor" => $returnDoctor,
                    ]);
                }
                $appt = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.12', [$appointmentId, $cid, $id], []);
                if (!$appt) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Atendimento ativo não encontrado.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                if (
                    (int) ($appt["doctor_user_id"] ?? 0) > 0 &&
                    (int) $appt["doctor_user_id"] !== (int) $c["user"]["id"]
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Este atendimento está vinculado a outro profissional.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", [
                        "id" => $id,
                        "appointment_id" => $appointmentId,
                        "return_day" => $returnDay,
                        "return_doctor" => $returnDoctor,
                    ]);
                }
                $apptCode = is_callable([\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::class, 'appointment_status_code'])
                    ? \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($appt)
                    : ((string) ($appt["status"] ?? ""));
                if (
                    $apptCode !== "em_atendimento" &&
                    empty($appt["consultation_started_at"])
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Este atendimento ainda não está em execução.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", [
                        "id" => $id,
                        "appointment_id" => $appointmentId,
                        "return_day" => $returnDay,
                        "return_doctor" => $returnDoctor,
                    ]);
                }
                if (is_callable([\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::class, 'workflow_on_appointment_finished'])) {
                    \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::workflow_on_appointment_finished(
                        $cid,
                        $appointmentId,
                        $id,
                        (int) $c["user"]["id"],
                        "ficha_atendimento",
                    );
                } else {
                    $stmt = \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.13', [$appointmentId, $cid, $id], []);
                    if ($stmt->rowCount() <= 0) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "A jornada foi atualizada por outra ação. Reabra a ficha e tente novamente.",
                            "bad",
                        );
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                    }
                }
                \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.14', [(int) $c["user"]["id"], $cid, $appointmentId], []);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consulta_concluida_ficha", "consulta", $appointmentId, [
                    "patient_name" => (string) $p["full_name"],
                    "patient_link_id" => $id,
                    "audit_body" =>
                        "Profissional concluiu o atendimento pela Ficha do Paciente e retornou ao Painel da Agenda.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Atendimento concluído. Próxima medida: Recepção finaliza a saída do paciente.",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("appointments", $returnParams);
            }
            if (!$canEditPatient) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Apenas o profissional responsável pode alterar dados da Ficha do Paciente.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            if ($act === "create_patient_tab") {
                $label = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_tab_label_clean(
                    (string) ($_POST["tab_label"] ?? ""),
                );
                $iconName = \Prontoo\Domain\Patients\PatientsDomainOperations01::normalize_patient_tab_icon(
                    (string) ($_POST["tab_icon"] ?? "clinical_notes"),
                );
                if ($label === "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o nome da nova aba.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                $command = PatientComposition::createTab(
                    $cid,
                    $id,
                    $label,
                    $iconName,
                    (int) $c["user"]["id"],
                );
                if ((string) ($command["status"] ?? "") === "duplicate") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Este paciente já possui uma aba com esse nome.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                $tabId = (int) ($command["id"] ?? 0);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("aba_paciente_criada", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "titulo" => $label,
                    "icone" => $iconName,
                    "campos" => ["Nova aba da ficha"],
                    "audit_body" =>
                        "Uma aba extra foi criada na ficha deste paciente.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Aba criada. Agora ela aparece na ficha e pode receber anotações.",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            if ($act === "update_patient") {
                $name = mb_trim((string) ($_POST["full_name"] ?? ""));
                $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_POST["cpf"] ?? ""));
                $birth = mb_trim((string) ($_POST["birth_date"] ?? ""));
                if ($name === "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Informe o nome completo para atualizar o cadastro.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                try {
                    $identity = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_identity_immutable_values(
                        (int) $p["person_id"],
                        $cpf,
                        $birth,
                        true,
                    );
                    $cpf = (string) $identity["cpf"];
                    $birth = (string) $identity["birth_date"];
                } catch (Throwable $immutableError) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                            $immutableError,
                            "CPF e data de nascimento não podem ser alterados por esta operação.",
                        ),
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                $cpfOwner =
                    (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.07.page_patient.15', [$cpf, (int) $p["person_id"]], []) ?? 0);
                if ($cpfOwner > 0) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Este CPF já pertence a outra pessoa cadastrada.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                $nameChanged = $name !== mb_trim((string) ($p["full_name"] ?? ""));
                if (
                    $nameChanged &&
                    \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::person_has_other_clinic_links((int) $p["person_id"], $cid)
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Esta pessoa também possui vínculo com outra clínica. Para proteger o isolamento entre clínicas, o nome não pode ser alterado por esta ficha. Atualize apenas telefone, e-mail, endereço e notas locais.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.16', [$name, $cpf, $birth, (int) $p["person_id"]], []);
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_refresh((int) $p["person_id"]);
                $loc = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_location_from_post($cid);
                $contact = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_invoice_contact_from_post();
                \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.17', [
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
                    ], []);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("paciente_salvo", "paciente", $id, [
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
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Ficha atualizada.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            if ($act === "delete_patient") {
                $future =
                    (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.07.page_patient.18', [$cid, $id], []) ?? 0);
                if ($future > 0) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Não é possível excluir este paciente enquanto houver agendamento futuro ativo. Cancele ou reagende antes.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.19', [(int) $c["user"]["id"], $id, $cid], []);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("paciente_excluido", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "campos" => ["Exclusão lógica do paciente"],
                    "audit_body" =>
                        "Cadastro do paciente foi marcado como excluído e permanece preservado para restauração pelo Administrador.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Paciente marcado como excluído. O Administrador poderá restaurar o cadastro.",
                    "warn",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patients");
            }
            if ($act === "update_care") {
                $careId = (int) ($_POST["care_id"] ?? 0);
                $oldCare = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.20', [$careId, $cid, $id], []);
                if (!$oldCare) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Anotação não encontrada.", "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                $allowedUpdate = $extraTypeOptions;
                if (!array_key_exists($type, $allowedUpdate) || $content === "") {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Informe uma aba válida e o conteúdo da anotação clínica.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
                }
                \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.21', [
                        $careId,
                        $cid,
                        $id,
                        $oldCare["record_type"] ?? null,
                        $oldCare["title"] ?? null,
                        $oldCare["content"] ?? null,
                        (int) $c["user"]["id"],
                        mb_trim((string) ($_POST["change_reason"] ?? "")),
                    ], []);
                \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.22', [$type, $title, $content, $careId, $cid, $id], []);
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
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("prontuario_alterado", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "tipo" => $type,
                    "titulo" => $title,
                    "campos" => $changed ?: ["Anotação clínica"],
                    "audit_body" => \Prontoo\Domain\AuditActivity\AuditCopyPolicy::audit_change_body(
                        $changed ?: ["Anotação clínica"],
                    ),
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Anotação atualizada.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            if ($act === "delete_care") {
                $careId = (int) ($_POST["care_id"] ?? 0);
                $oldCare = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.23', [$careId, $cid, $id], []);
                if ($oldCare) {
                    \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.24', [(int) $c["user"]["id"], $careId, $cid, $id], []);
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("prontuario_alterado", "paciente", $id, [
                        "patient_name" => (string) $p["full_name"],
                        "tipo" => (string) $oldCare["record_type"],
                        "titulo" => (string) $oldCare["title"],
                        "campos" => ["Exclusão lógica de anotação"],
                        "audit_body" =>
                            "Uma anotação do prontuário foi marcada como excluída e permanece preservada para restauração pelo Administrador.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Anotação marcada como excluída. O Administrador poderá restaurar.",
                        "warn",
                    );
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            if (!array_key_exists($type, $extraTypeOptions)) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Crie ou escolha uma aba extra deste paciente antes de salvar a anotação.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            if ($content === "") {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o conteúdo da anotação clínica.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
            }
            $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
            $appointmentForAudit = null;
            if ($appointmentId > 0) {
                $appointmentForAudit = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.25', [$appointmentId, $cid, $id], []);
                if (!$appointmentForAudit) {
                    $appointmentId = 0;
                    $appointmentForAudit = null;
                }
            }
            \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.26', [
                    $cid,
                    $id,
                    $appointmentId > 0 ? $appointmentId : null,
                    $type,
                    $title,
                    (int) $c["user"]["id"],
                ], []);
            $careId = \Prontoo\Runtime\Operational\OperationalComposition::patients()->lastInsertId();
            \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.27', [$careId, $cid, $content], []);
            if (
                $appointmentForAudit &&
                empty($appointmentForAudit["consultation_started_at"])
            ) {
                \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.28', [$appointmentId, $cid, $id], []);
                $scheduled = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp(
                    $appointmentForAudit["start_at"],
                );
                $delay = $scheduled
                    ? max(0, (int) floor((time() - $scheduled) / 60))
                    : null;
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consulta_iniciada", "consulta", $appointmentId, [
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
                                \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::format_minutes($delay) .
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
                \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::workflow_on_appointment_finished(
                    $cid,
                    $appointmentId,
                    $id,
                    (int) $c["user"]["id"],
                    "anotacao_clinica",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("consulta_concluida", "consulta", $appointmentId, [
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
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("care_records_total");
            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "care_records");
            $changedFields = ["Aba da ficha"];
            if ($title !== "") {
                $changedFields[] = "Título";
            }
            $changedFields[] = "Conteúdo";
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("prontuario_alterado", "paciente", $id, [
                "patient_name" => (string) $p["full_name"],
                "tipo" => $type,
                "titulo" => $title,
                "campos" => $changedFields,
                "audit_body" => \Prontoo\Domain\AuditActivity\AuditCopyPolicy::audit_change_body($changedFields),
            ]);
            $_SESSION["skip_patient_view_audit_" . (int) $id] = 1;
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Anotação incluída.");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $id]);
        }
        $appts = \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.29', [$cid, $id], [])->fetchAll();
        $apptItems = \Prontoo\Runtime\Patients\PatientsRuntimeOperations06::patient_appointment_timeline_items($cid, $appts, $c);
        $patientDocOptions = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("documents")
            ? \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::approved_document_template_options($c)
            : [];
        $patientDocItems = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::patient_document_timeline_items($c, $id, 80);
        $patientDocCount = count($patientDocItems);
        $patientDocForm = "";
        if (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("documents")) {
            $docEmitParams = ["emit" => 1, "patient_id" => $id];
            $docEmitAppointmentId = (int) ($_GET["appointment_id"] ?? 0);
            if ($docEmitAppointmentId > 0) {
                $docEmitParams["appointment_id"] = $docEmitAppointmentId;
            }
            $patientDocForm =
                '<div class="patient-empty-action patient-doc-create patient-doc-external-cta"><a class="primary small cmdlike" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("documents", $docEmitParams) .
                '">' .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Emitir Primeiro Documento", "description") .
                "</a></div>";
        }
        $activeAppointmentId = (int) ($_GET["appointment_id"] ?? 0);
        $activeAppointment = null;
        if ($role === "medico") {
            if ($activeAppointmentId > 0) {
                $activeAppointment = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.30', [$activeAppointmentId, $cid, $id], []);
            }
            if (!$activeAppointment) {
                $activeAppointment = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.31', [$cid, $id, (int) $c["user"]["id"]], []);
            }
            if ($activeAppointment) {
                $activeCode = is_callable([\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::class, 'appointment_status_code'])
                    ? \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($activeAppointment)
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
                $activeReturnDay = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c);
            }
            $activeReturnDoctor =
                (int) ($_GET["return_doctor"] ??
                    ((int) ($activeAppointment["doctor_user_id"] ?? 0)));
            $activeAppointmentPanel =
                '<section class="patient-active-consultation" aria-label="Atendimento em andamento"><div class="patient-active-consultation-copy"><span class="eyebrow">Atendimento em andamento</span><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($activeAppointment["reason"] ?: "Consulta")) .
                "</h2><p>A ficha permanece aberta durante o atendimento. Registre as anotações necessárias e conclua quando terminar.</p><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($activeAppointment["start_at"])) .
                " · " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($activeAppointment["end_at"])) .
                '</small></div><form method="post" class="patient-active-consultation-actions">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="finish_active_appointment"><input type="hidden" name="appointment_id" value="' .
                (int) $activeAppointment["id"] .
                '"><input type="hidden" name="return_day" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($activeReturnDay) .
                '"><input type="hidden" name="return_doctor" value="' .
                (int) $activeReturnDoctor .
                '"><button class="primary" type="submit">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("task_alt") .
                '<span>Concluir atendimento e voltar ao Painel</span></button><a class="ghost small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", [
                    "d" => $activeReturnDay,
                    "doctor" => $activeReturnDoctor,
                ]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("space_dashboard") .
                "<span>Voltar ao Painel</span></a></form></section>";
        }
        $consultStats = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.07.page_patient.32', [$cid, $id], []) ?: ["total" => 0, "first_at" => null];
        $firstConsultationLabel = !empty($consultStats["first_at"])
            ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_card_full_br($consultStats["first_at"])
            : "Ainda não agendada";
        $totalConsultations = (int) ($consultStats["total"] ?? 0);
        $patientSummaryCards =
            '<div class="patient-profile-summary-cards patient-profile-footer-cards" aria-label="Resumo do cadastro do paciente"><article>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("person_add") .
            "<span>Cadastro</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_card_full_br($p["created_at"])) .
            "</b></article><article>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("update") .
            "<span>Atualização</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_card_full_br($p["updated_at"] ?: $p["created_at"])) .
            "</b></article><article>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
            "<span>Primeira Consulta</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($firstConsultationLabel) .
            "</b></article><article>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("monitoring") .
            "<span>Total de Consultas</span><b>" .
            (int) $totalConsultations .
            "</b></article></div>";
        $patientProfileOverview = \Prontoo\Runtime\Patients\PatientsRuntimeOperations03::patient_profile_overview(
            $p,
            $profileStatus,
            count($appts),
            (int) $patientDocCount,
            $totalConsultations,
            $firstConsultationLabel,
        );
        $patientReceptionItems = \Prontoo\Runtime\Patients\PatientsRuntimeOperations03::patient_reception_history_items($cid, $id, $p);
        $patientReceptionCount = count($patientReceptionItems);
        $patientReceptionPanel = \Prontoo\Presentation\Patients\PatientsPresentationOperations01::patient_reception_history_panel(
            $patientReceptionItems,
        );
        $agendaEmptyAction = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("appointments")
            ? '<div class="empty patient-empty-cta"><a class="primary small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", ["patient_id" => $id]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
                "<span>Agendar Primeira Consulta</span></a></div>"
            : '<div class="empty">Nenhum agendamento encontrado para este paciente.</div>';
        \Prontoo\Runtime\Operational\OperationalComposition::patients()->ensureSchema("financial_operational");
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_ensure_default_accounts($cid, (int) $c["user"]["id"]);
        $pendingPatientRevenueCount =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.07.page_patient.33', [$cid, $id], []) ?? 0);
        $showPatientFinance =
            $role === "gerente" ||
            ($role === "recepcionista" && $pendingPatientRevenueCount > 0);
        $patientFinanceRows = $showPatientFinance
            ? \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.34', [$cid, $id], [])->fetchAll()
            : [];
        $patientAccountOptions = $showPatientFinance
            ? \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_account_label_options($cid, "Conta de recebimento")
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
                    ? \Prontoo\Domain\Financial\FinancialDomainOperations01::payment_methods_options()[$methodKey] ?? $methodKey
                    : "";
            $patientFinanceList .=
                '<article class="finance-row finance-action-row patient-finance-row"><div><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fr["title"] ?: "Cobrança do paciente") .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    ($date ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($date) : "sem data") .
                        " · " .
                        ($methodLabel !== "" ? $methodLabel . " · " : "") .
                        ($fr["account_name"] ?: "sem conta"),
                ) .
                "</small></div><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $fr["amount_cents"]) .
                "</b>" .
                \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_status_pill(
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
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="patient_revenue_receive"><input type="hidden" name="revenue_id" value="' .
                    (int) $fr["id"] .
                    '"><button class="ghost small" type="submit">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("paid") .
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
                "<span><b>Financeiro</b></span>" .
                ($pendingPatientRevenueCount > 0
                    ? "<em>" . (int) $pendingPatientRevenueCount . "</em>"
                    : "") .
                "</label>"
            : "";
        $age = "";
        if (!empty($p["birth_date"])) {
            $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($p["birth_date"]);
            if ($ts) {
                $age =
                    (string) max(0, (int) floor((time() - $ts) / 31557600)) .
                    " anos";
            }
        }
        if (!$canEditPatient) {
            $patientHeadActions =
                '<a class="ghost small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patients") .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Voltar para Pacientes</span></a>";
            $hero =
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                    (string) $p["full_name"],
                    "Ficha do paciente",
                    $patientHeadActions,
                ) . $patientProfileOverview;
            $dados =
                '<div class="patient-data-list"><div><span>Nome completo</span><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($p["full_name"]) .
                "</b></div><div><span>CPF</span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($p["cpf"] ?? "")) .
                "</b></div><div><span>Nascimento</span><b>" .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($p["birth_date"]) .
                ($age ? " · " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($age) : "") .
                "</b></div><div><span>Telefone</span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($p["phone"] ?: "não informado") .
                "</b></div><div><span>E-mail</span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($p["email"] ?: "não informado") .
                "</b></div><div><span>Endereço</span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($p["address"] ?: "não informado") .
                "</b></div><div><span>Cidade/Estado</span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::city_state_label(
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
                ($apptItems ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($apptItems, "") : $agendaEmptyAction) .
                "</section>";
            $docsPanel =
                '<section class="patient-panel patient-panel-documentos" role="tabpanel"><div class="patient-section-title"><div><h2>Documentos</h2><p>Últimos documentos emitidos para este paciente a partir dos modelos do módulo Documentos.</p></div><span>' .
                (int) $patientDocCount .
                " documento(s)</span></div>" .
                $patientDocForm .
                ($patientDocItems
                    ? '<hr class="soft-sep">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($patientDocItems, "")
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
                '<span><b>Cadastro</b></span></label><label for="' .
                $prefix .
                'agenda">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_month") .
                "<span><b>Agenda</b></span><em>" .
                (int) count($appts) .
                '</em></label><label for="' .
                $prefix .
                'documentos">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("description") .
                "<span><b>Documentos</b></span><em>" .
                (int) $patientDocCount .
                '</em></label><label for="' .
                $prefix .
                'atendimentos">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("forum") .
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
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page($p["full_name"], $hero . $activeAppointmentPanel . $tabs);
            return;
        }
        $viewAuditKey = "skip_patient_view_audit_" . (int) $id;
        if (!empty($_SESSION[$viewAuditKey])) {
            unset($_SESSION[$viewAuditKey]);
        } else {
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("prontuario_visualizado", "paciente", $id, [
                "patient_name" => (string) $p["full_name"],
                "audit_body" => "Nenhuma informação foi alterada.",
            ]);
        }
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.35', [$cid, $id], [])->fetchAll();
        $authors = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map("users_name", \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($rows, "created_by"));
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
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Alterar", "edit") .
                '</summary><form method="post" class="compact">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="update_care"><input type="hidden" name="care_id" value="' .
                (int) $r["id"] .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label("Tipo", "record_type", $editOptions, $rt, "required") .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Título", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("title", "text", $r["title"] ?? "")) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Conteúdo",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("content", $r["content"], 'required rows="6"'),
                ) .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Salvar anotação") .
                '</form><form method="post" class="inline danger-zone" onsubmit="return confirm(\'Excluir esta anotação?\')">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="delete_care"><input type="hidden" name="care_id" value="' .
                (int) $r["id"] .
                '"><button class="danger small" type="submit">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
                "<span>Excluir</span></button></form></details>";
            $item = [
                "icon" => $ic,
                "time" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["created_at"]),
                "title" =>
                    ($r["title"] ?: $label) .
                    " · " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name(
                        $authors[(int) ($r["created_by"] ?? 0)]["name"] ?? "",
                    ),
                "body" => $r["content"],
                "meta" => $label,
                "html" => $html,
            ];
            $counts[$rt]++;
            $recordItems[$rt][] = $item;
        }
        $apptLinkRows = \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.07.page_patient.36', [$cid, $id], [])->fetchAll();
        $apptOptions = ["" => "Sem vínculo com agendamento"];
        $defaultAppointment = null;
        foreach ($apptLinkRows as $a) {
            $label =
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($a["start_at"]) .
                " · " .
                ($a["reason"] ?? "" ?: "Consulta") .
                " · " .
                ($a["status"] ?? "" ?: "agendado");
            $apptOptions[(int) $a["id"]] = $label;
            if (
                $defaultAppointment === null &&
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["start_at"]) >= strtotime("today") &&
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["start_at"]) < strtotime("tomorrow")
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
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                    "Consulta vinculada",
                    "appointment_id",
                    $apptOptions,
                    $defaultAppointment,
                ) .
                ($role === "medico"
                    ? '<label class="checkline"><input type="checkbox" name="finish_appointment" value="1"><span>Concluir atendimento ao salvar esta anotação</span></label>'
                    : "") .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                    "Tipo",
                    "record_type",
                    $extraTypeOptions,
                    $selectedType,
                    "required",
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Título", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("title")) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Conteúdo", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("content", "", 'required rows="7"')) .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions($submit) .
                "</form>";
        };
        $hero =
            '<div class="patient-hero patient-hero-tabs"><div><span class="eyebrow">Ficha do paciente</span><h1>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($p["full_name"]) .
            "</h1><p>Nascimento: " .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($p["birth_date"]) .
            ($age ? " · " . $age : "") .
            " · CPF " .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($p["cpf"] ?? "")) .
            '</p></div><div class="patient-hero-actions"><a class="ghost small" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patients") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            '<span>Voltar para Pacientes</span></a><label class="primary small patient-new-tab-btn" for="' .
            $prefix .
            'novaanotacao">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_note") .
            "<span>Nova anotação</span></label></div></div>";
        $dados =
            '<div class="patient-data-list">' .
            "<div><span>Nome completo</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($p["full_name"]) .
            "</b></div>" .
            "<div><span>CPF</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($p["cpf"] ?? "")) .
            "</b></div>" .
            "<div><span>Nascimento</span><b>" .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($p["birth_date"]) .
            ($age ? " · " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($age) : "") .
            "</b></div>" .
            "<div><span>Telefone</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($p["phone"] ?: "não informado") .
            "</b></div>" .
            "<div><span>E-mail</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($p["email"] ?: "não informado") .
            "</b></div>" .
            "<div><span>CEP</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\Patients\PatientsRuntimeOperations02::mask_cep((string) ($p["address_zip"] ?? "")) ?: "não informado") .
            "</b></div><div><span>Endereço</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                trim(
                    ($p["address"] ?: "") .
                        " " .
                        ($p["address_number"] ? ", " . $p["address_number"] : ""),
                ) ?:
                "não informado",
            ) .
            "</b></div><div><span>Bairro</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($p["address_neighborhood"] ?: "não informado") .
            "</b></div><div><span>Cidade/Estado</span><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::city_state_label(
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
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($p["cpf"] ?? "")) .
                    '" readonly aria-readonly="true" tabindex="-1" autocomplete="off">'
                : \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "cpf",
                    "text",
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($p["cpf"] ?? ""),
                    'required inputmode="numeric" data-cpf-mask',
                );
        $patientBirthField =
            mb_trim((string) ($p["birth_date"] ?? "")) !== ""
                ? '<input type="date" value="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($p["birth_date"] ?? "")) .
                    '" readonly aria-readonly="true" tabindex="-1" autocomplete="off">'
                : \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("birth_date", "date", $p["birth_date"], "required");
        $patientEdit =
            '<details class="patient-edit"><summary class="primary small cmdlike">' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Alterar cadastro", "edit") .
            '</summary><form method="post" class="compact patient-record-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="update_patient">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome completo",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("full_name", "text", $p["full_name"], "required"),
            ) .
            '<div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("CPF", $patientCpfField) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Nascimento", $patientBirthField) .
            "</div>" .
            '<div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Telefone",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("phone", "text", $p["phone"], 'required inputmode="tel"'),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("E-mail", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("email", "email", $p["email"], "required")) .
            "</div>" .
            \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_address_fields($cid, $p) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Observações importantes:",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("notes", $p["notes"], 'rows="5"'),
            ) .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Salvar cadastro") .
            '</form></details><form method="post" class="inline danger-zone" onsubmit="return confirm(\'Marcar este paciente como excluído? O Administrador poderá restaurar.\')">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="delete_patient"><button class="danger small" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
            "<span>Excluir paciente</span></button></form>";
        $patientUpdateNotice = !empty($p["registration_needs_update"])
            ? '<div class="flash warn">Cadastro recuperado. Revise e atualize os dados cadastrais.</div>'
            : "";
        $patientStatusNotice =
            $profileStatus["level"] !== "ok"
                ? '<div class="flash ' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($profileStatus["level"]) .
                    '"><strong>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($profileStatus["label"]) .
                    ".</strong> " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($profileStatus["message"]) .
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
            ($apptItems ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($apptItems, "") : $agendaEmptyAction) .
            "</section>";
        $docsPanel =
            '<section class="patient-panel patient-panel-documentos" role="tabpanel"><div class="patient-section-title"><div><h2>Documentos</h2><p>Crie recibos, atestados, receitas e declarações a partir de modelos aprovados. Esta aba é fixa.</p></div><span>' .
            (int) $patientDocCount .
            " documento(s)</span></div>" .
            $patientDocForm .
            ($patientDocItems
                ? '<hr class="soft-sep">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($patientDocItems, "")
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon((string) $tab["icon_name"]) .
                "<span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($tab["label"]) .
                "</b></span><em>" .
                $count .
                "</em></label>";
            $extraPanels .=
                '<section class="patient-panel patient-panel-' .
                $key .
                '" role="tabpanel"><div class="patient-section-title"><div><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($tab["label"]) .
                "</h2><p>Anotações desta aba, em ordem de inclusão.</p></div><span>" .
                $count .
                ' anotação(ões)</span></div><details class="patient-inline-note"><summary class="ghost small cmdlike">' .
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Nova anotação nesta aba", "edit_note") .
                "</summary>" .
                $annotationForm($rt, "Salvar em " . (string) $tab["label"]) .
                "</details>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($recordItems[$rt] ?? [], "Nenhuma anotação nesta aba.") .
                "</section>";
        }
        $newRecordPanel =
            '<section class="patient-panel patient-panel-novaanotacao" role="tabpanel"><h2>Nova anotação</h2><p class="muted-copy">O campo Tipo agora usa as abas extras criadas para este paciente específico.</p>' .
            $annotationForm(null) .
            "</section>";
        $newTabPanel =
            '<section class="patient-panel patient-panel-novaaba" role="tabpanel"><h2>Nova Aba</h2><p class="muted-copy">Crie uma área própria da ficha deste paciente. Depois disso, ela aparecerá na navegação e no campo Tipo das novas anotações.</p><form method="post" class="compact patient-record-form patient-extra-tab-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="create_patient_tab">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome exibido da aba",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "tab_label",
                    "text",
                    "",
                    'required maxlength="60" placeholder="Ex.: Exames, Dor, Evolução familiar"',
                ),
            ) .
            '<div class="settings-section full"><h3>Ícone da aba</h3>' .
            \Prontoo\Runtime\Patients\PatientViewComposition::tabIconPicker("clinical_notes") .
            "</div>" .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Criar aba") .
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
            "<span><b>Cadastro</b></span></label>" .
            '<label for="' .
            $prefix .
            'agenda">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_month") .
            "<span><b>Agenda</b></span><em>" .
            (int) count($appts) .
            "</em></label>" .
            '<label for="' .
            $prefix .
            'documentos">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("description") .
            "<span><b>Documentos</b></span><em>" .
            (int) $patientDocCount .
            "</em></label>" .
            '<label for="' .
            $prefix .
            'atendimentos">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("forum") .
            "<span><b>Atendimentos</b></span><em>" .
            (int) $patientReceptionCount .
            "</em></label>" .
            $financeNav .
            $extraNav .
            '<label class="patient-tab-add-new" for="' .
            $prefix .
            'novaaba">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("add_circle") .
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($profileStatus["level"]) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($profileStatus["label"]) .
            '</span><a class="ghost small" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patients") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            '<span>Voltar para Pacientes</span></a><label class="primary small patient-new-tab-btn" for="' .
            $prefix .
            'novaanotacao">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_note") .
            "<span>Nova anotação</span></label>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            $p["full_name"],
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
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
