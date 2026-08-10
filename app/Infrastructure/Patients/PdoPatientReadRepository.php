<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Patients;

use PDO;
use Prontoo\Application\Patients\PatientReadPort;

final class PdoPatientReadRepository implements PatientReadPort
{
    public function appointmentRegistration(int $clinicId, int $patientId): ?array
    {
        $statement = self::pdo()->prepare(
            "SELECT pp.id,pp.clinic_id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,p.full_name,p.cpf,p.birth_date FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1 LIMIT 1",
        );
        $statement->execute([$patientId, $clinicId]);
        $patient = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($patient) ? $patient : null;
    }

    public function activeLegalGuardians(int $clinicId, int $patientId): array
    {
        $statement = self::pdo()->prepare(
            "SELECT id,full_name,cpf,relationship,phone,email,document_note,notes,is_primary,created_at,updated_at FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY is_primary DESC, id ASC",
        );
        $statement->execute([$clinicId, $patientId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasActiveLegalGuardian(int $clinicId, int $patientId): bool
    {
        $statement = self::pdo()->prepare(
            "SELECT COUNT(*) FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1",
        );
        $statement->execute([$clinicId, $patientId]);
        return (int) $statement->fetchColumn() > 0;
    }

    private static function pdo(): PDO
    {
        return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
    }
}
