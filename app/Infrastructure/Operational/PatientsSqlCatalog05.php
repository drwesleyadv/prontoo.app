<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class PatientsSqlCatalog05
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.patients.05.page_patients.01' => (
                "SELECT id FROM pi_persons WHERE cpf=? LIMIT 1"
            ),
            'operational.patients.05.page_patients.02' => (
                "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=1 LIMIT 1"
            ),
            'operational.patients.05.page_patients.03' => (
                "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=0 LIMIT 1"
            ),
            'operational.patients.05.page_patients.04' => (
                "UPDATE pi_patients SET active=1,registration_needs_update=1,deleted_at=NULL,deleted_by=NULL,restored_at=NOW(),restored_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.patients.05.page_patients.05' => (
                "INSERT INTO pi_patients (clinic_id,person_id,phone,email,address,address_zip,address_number,address_neighborhood,address_complement,address_state,address_city,address_city_ibge,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE phone=VALUES(phone),email=VALUES(email),address=VALUES(address),address_zip=VALUES(address_zip),address_number=VALUES(address_number),address_neighborhood=VALUES(address_neighborhood),address_complement=VALUES(address_complement),address_state=VALUES(address_state),address_city=VALUES(address_city),address_city_ibge=VALUES(address_city_ibge),notes=VALUES(notes),active=1,registration_needs_update=0,deleted_at=NULL,deleted_by=NULL,updated_at=NOW()"
            ),
            'operational.patients.05.page_patients.06' => (
                "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=1 LIMIT 1"
            ),
            'operational.patients.05.page_patients.07' => (
                "SELECT pp.id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,pp.created_at,pp.updated_at,pp.registration_needs_update,p.full_name,p.cpf,p.birth_date,(SELECT COUNT(*) FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1) guardian_count " .
                                PatientDirectorySql::metrics($todayStart, $todayEnd) .
                                " FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1" .
                                PatientDirectorySql::filter($filter) .
                                PatientDirectorySql::search($searchKind) .
                                " ORDER BY " . PatientDirectorySql::order($filter, $searchKind) . PatientDirectorySql::limit($filter, $searchKind)
            ),
            'operational.patients.05.page_patients.08' => (
                "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND start_at>=? AND start_at<? AND status NOT IN ('cancelado','nao_compareceu')"
            ),
            'operational.patients.05.page_patients.09' => (
                "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND start_at>=? AND start_at<? AND status NOT IN ('cancelado','nao_compareceu')"
            ),
            'operational.patients.05.page_patients.10' => (
                "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND status IN ('cancelado','nao_compareceu') AND COALESCE(updated_at,created_at,start_at)>=DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
            'operational.patients.05.page_patients.11' => (
                "SELECT COUNT(*) FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1 AND ((p.birth_date IS NOT NULL AND p.birth_date>DATE_SUB(CURDATE(), INTERVAL 18 YEAR) AND NOT EXISTS (SELECT 1 FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1)) OR COALESCE(pp.phone,'')='' OR COALESCE(pp.updated_at,pp.created_at,0)<DATE_SUB(NOW(), INTERVAL 180 DAY))"
            ),
            'operational.patients.05.page_patients.12' => (
                "SELECT pp.id,p.full_name,p.cpf,p.birth_date FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
