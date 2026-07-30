<?php
declare(strict_types=1);

namespace Prontoo\Application\Patients;

interface PatientReceptionHistoryReadPort
{
    public function read(
        int $clinicId,
        int $patientId,
        int $personId,
        string $phoneDigits,
    ): array;
}
