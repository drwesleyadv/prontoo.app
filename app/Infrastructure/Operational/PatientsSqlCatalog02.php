<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class PatientsSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.patients.02.clinic_patient_exists.01' => (
                "SELECT id FROM pi_patients WHERE id=? AND clinic_id=?" .
                (!empty($activeOnly) ? " AND active=1 AND deleted_at IS NULL" : "") .
                " LIMIT 1"
            ),
            'operational.patients.02.patient_options.01' => (
                "SELECT id,person_id FROM pi_patients WHERE clinic_id=? AND active=1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 300"
            ),
            'operational.patients.02.patient_autosuggest_datalist.01' => (
                "SELECT pp.id,p.full_name,p.birth_date,p.cpf FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1 AND pp.deleted_at IS NULL ORDER BY pp.updated_at DESC, pp.id DESC LIMIT " .
                                $limit
            ),
            'operational.patients.02.patient_lookup_field.01' => (
                "SELECT p.full_name,p.birth_date FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1 LIMIT 1"
            ),
            'operational.patients.02.resolve_patient_lookup_id.01' => (
                "SELECT id FROM pi_patients WHERE id=? AND clinic_id=? AND active=1 LIMIT 1"
            ),
            'operational.patients.02.resolve_patient_lookup_id.02' => (
                "SELECT pp.id,p.full_name,p.birth_date,p.cpf FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1 ORDER BY p.full_name ASC LIMIT 1000"
            ),
            'operational.patients.02.patient_identity_by_cpf.01' => (
                "SELECT p.id,p.full_name,p.cpf,p.birth_date
                                 FROM pi_persons p
                                 WHERE p.cpf=?
                                   AND (
                                     EXISTS (SELECT 1 FROM pi_patients pat WHERE pat.person_id=p.id AND pat.clinic_id=?)
                                     OR EXISTS (SELECT 1 FROM pi_leads l WHERE l.person_id=p.id AND l.clinic_id=?)
                                     OR EXISTS (
                                       SELECT 1
                                       FROM pi_users u
                                       JOIN pi_user_roles ur ON ur.user_id=u.id
                                       WHERE u.person_id=p.id AND ur.clinic_id=?
                                     )
                                   )
                                 LIMIT 1"
            ),
            'operational.patients.02.patient_lookup_payload.01' => (
                "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=1 LIMIT 1"
            ),
            'operational.patients.02.patient_lookup_payload.02' => (
                "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=0 LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
