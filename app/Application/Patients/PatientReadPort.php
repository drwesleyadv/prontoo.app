<?php
declare(strict_types=1);

namespace Prontoo\Application\Patients;

interface PatientReadPort
{
    public function appointmentRegistration(int $clinicId, int $patientId): ?array;

    public function activeLegalGuardians(int $clinicId, int $patientId): array;

    public function hasActiveLegalGuardian(int $clinicId, int $patientId): bool;
}
