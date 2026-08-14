<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class PatientsSqlCatalog07
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.patients.07.page_patient.01' => (
                "SELECT id,clinic_id,person_id,phone,email,address,address_zip,address_number,address_neighborhood,address_complement,address_state,address_city,address_city_ibge,tags,notes,active,registration_needs_update,deleted_at,created_by,created_at,updated_at FROM pi_patients WHERE id=? AND clinic_id=?"
            ),
            'operational.patients.07.page_patient.02' => (
                "SELECT full_name,cpf,birth_date FROM pi_persons WHERE id=?"
            ),
            'operational.patients.07.page_patient.03' => (
                "SELECT id FROM pi_patient_guardians WHERE id=? AND clinic_id=? AND patient_link_id=? AND active=1"
            ),
            'operational.patients.07.page_patient.04' => (
                "SELECT id FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND cpf=? AND active=1 AND id<>? LIMIT 1"
            ),
            'operational.patients.07.page_patient.05' => (
                "UPDATE pi_patient_guardians SET is_primary=0,updated_at=NOW() WHERE clinic_id=? AND patient_link_id=?"
            ),
            'operational.patients.07.page_patient.06' => (
                "UPDATE pi_patient_guardians SET full_name=?,cpf=?,relationship=?,phone=?,email=?,document_note=?,notes=?,is_primary=?,updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?"
            ),
            'operational.patients.07.page_patient.07' => (
                "INSERT INTO pi_patient_guardians (clinic_id,patient_link_id,full_name,cpf,relationship,phone,email,document_note,notes,is_primary,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.patients.07.page_patient.08' => (
                "SELECT id,full_name FROM pi_patient_guardians WHERE id=? AND clinic_id=? AND patient_link_id=? AND active=1"
            ),
            'operational.patients.07.page_patient.09' => (
                "UPDATE pi_patient_guardians SET active=0,is_primary=0,updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?"
            ),
            'operational.patients.07.page_patient.10' => (
                "SELECT id FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY id ASC LIMIT 1"
            ),
            'operational.patients.07.page_patient.11' => (
                "UPDATE pi_patient_guardians SET is_primary=1,updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?"
            ),
            'operational.patients.07.page_patient.12' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? AND patient_link_id=?"
            ),
            'operational.patients.07.page_patient.13' => (
                "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), consultation_finished_at=COALESCE(consultation_finished_at,NOW()), status='atendimento_concluido', updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=? AND status='em_atendimento' AND consultation_finished_at IS NULL"
            ),
            'operational.patients.07.page_patient.14' => (
                "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','em_andamento','aguardando')"
            ),
            'operational.patients.07.page_patient.15' => (
                "SELECT id FROM pi_persons WHERE cpf=? AND id<>? LIMIT 1"
            ),
            'operational.patients.07.page_patient.16' => (
                "UPDATE pi_persons SET full_name=?,cpf=?,birth_date=?,updated_at=NOW() WHERE id=?"
            ),
            'operational.patients.07.page_patient.17' => (
                "UPDATE pi_patients SET phone=?,email=?,address=?,address_zip=?,address_number=?,address_neighborhood=?,address_complement=?,address_state=?,address_city=?,address_city_ibge=?,notes=?,registration_needs_update=0,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.patients.07.page_patient.18' => (
                "SELECT COUNT(*) FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND start_at>=NOW() AND status NOT IN ('cancelado')"
            ),
            'operational.patients.07.page_patient.19' => (
                "UPDATE pi_patients SET active=0,deleted_at=NOW(),deleted_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.patients.07.page_patient.20' => (
                "SELECT c.id,c.record_type,c.title,cc.content FROM pi_care c INNER JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id WHERE c.id=? AND c.clinic_id=? AND c.patient_link_id=? AND c.deleted_at IS NULL"
            ),
            'operational.patients.07.page_patient.21' => (
                "INSERT INTO pi_care_versions (care_id,clinic_id,patient_link_id,old_record_type,old_title,old_content,changed_by,change_reason,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.patients.07.page_patient.22' => (
                "UPDATE pi_care c INNER JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id SET c.record_type=?,c.title=?,cc.content=?,c.updated_at=NOW(),cc.updated_at=NOW() WHERE c.id=? AND c.clinic_id=? AND c.patient_link_id=? AND c.deleted_at IS NULL"
            ),
            'operational.patients.07.page_patient.23' => (
                "SELECT id,record_type,title FROM pi_care WHERE id=? AND clinic_id=? AND patient_link_id=? AND deleted_at IS NULL"
            ),
            'operational.patients.07.page_patient.24' => (
                "UPDATE pi_care SET deleted_at=NOW(),deleted_by=? WHERE id=? AND clinic_id=? AND patient_link_id=? AND deleted_at IS NULL"
            ),
            'operational.patients.07.page_patient.25' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? AND patient_link_id=?"
            ),
            'operational.patients.07.page_patient.26' => (
                "INSERT INTO pi_care (clinic_id,patient_link_id,appointment_id,record_type,title,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())"
            ),
            'operational.patients.07.page_patient.27' => (
                "INSERT INTO pi_care_content (care_id,clinic_id,content) VALUES (?,?,?)"
            ),
            'operational.patients.07.page_patient.28' => (
                "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), status='em_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=? AND status IN ('agendado','pronto_atendimento') AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"
            ),
            'operational.patients.07.page_patient.29' => (
                "SELECT id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? ORDER BY start_at DESC LIMIT 20"
            ),
            'operational.patients.07.page_patient.30' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? AND patient_link_id=?"
            ),
            'operational.patients.07.page_patient.31' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND doctor_user_id=? AND consultation_started_at IS NOT NULL AND consultation_finished_at IS NULL AND status NOT IN ('cancelado','nao_compareceu','reagendado','finalizado') ORDER BY start_at DESC LIMIT 1"
            ),
            'operational.patients.07.page_patient.32' => (
                "SELECT COUNT(*) total, MIN(start_at) first_at FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND status NOT IN ('cancelado')"
            ),
            'operational.patients.07.page_patient.33' => (
                "SELECT COUNT(*) FROM pi_financial_revenues WHERE clinic_id=? AND patient_link_id=? AND status='prevista'"
            ),
            'operational.patients.07.page_patient.34' => (
                "SELECT r.id,r.appointment_id,r.title,r.amount_cents,r.status,r.payment_method,r.expected_at,r.received_at,r.account_id,a.name account_name FROM pi_financial_revenues r LEFT JOIN pi_financial_accounts a ON a.id=r.account_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.patient_link_id=? ORDER BY FIELD(r.status,'prevista','efetivada','cancelada'), COALESCE(r.expected_at,r.received_at,r.created_at) DESC, r.id DESC LIMIT 160"
            ),
            'operational.patients.07.page_patient.35' => (
                "SELECT c.id,c.record_type,c.title,cc.content,c.created_by,c.created_at FROM pi_care c INNER JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id WHERE c.clinic_id=? AND c.patient_link_id=? AND c.deleted_at IS NULL AND c.record_type<>'receita' ORDER BY c.id DESC LIMIT 200"
            ),
            'operational.patients.07.page_patient.36' => (
                "SELECT id,start_at,status,reason FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND start_at>=DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND start_at<DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY start_at ASC LIMIT 20"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
