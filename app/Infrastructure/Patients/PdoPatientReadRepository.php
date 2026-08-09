<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Patients;

use Prontoo\Application\Patients\PatientReadPort;

final class PdoPatientReadRepository implements PatientReadPort
{
    public function appointmentRegistration(int $clinicId, int $patientId): ?array
    {
        $patient = \Prontoo\Core\Architecture\OperationGateway::invoke('one', 
            "SELECT pp.id,pp.clinic_id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,p.full_name,p.cpf,p.birth_date FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1 LIMIT 1",
            [$patientId, $clinicId],
        );
        return is_array($patient) ? $patient : null;
    }

    public function activeLegalGuardians(int $clinicId, int $patientId): array
    {
        return \Prontoo\Core\Architecture\OperationGateway::invoke('q', 
            "SELECT id,full_name,cpf,relationship,phone,email,document_note,notes,is_primary,created_at,updated_at FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY is_primary DESC, id ASC",
            [$clinicId, $patientId],
        )->fetchAll();
    }

    public function hasActiveLegalGuardian(int $clinicId, int $patientId): bool
    {
        return (int) (\Prontoo\Core\Architecture\OperationGateway::invoke('val', 
            "SELECT COUNT(*) FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1",
            [$clinicId, $patientId],
        ) ?? 0) > 0;
    }
}
