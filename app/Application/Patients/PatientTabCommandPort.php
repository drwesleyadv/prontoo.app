<?php
declare(strict_types=1);

namespace Prontoo\Application\Patients;

interface PatientTabCommandPort
{
    public function createIfAbsent(
        int $clinicId,
        int $patientId,
        string $label,
        string $iconName,
        int $userId,
    ): array;
}
