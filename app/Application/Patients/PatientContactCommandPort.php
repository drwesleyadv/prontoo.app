<?php
declare(strict_types=1);

namespace Prontoo\Application\Patients;

interface PatientContactCommandPort
{
    public function update(
        int $clinicId,
        int $patientId,
        int $userId,
        array $contact,
    ): array;
}
