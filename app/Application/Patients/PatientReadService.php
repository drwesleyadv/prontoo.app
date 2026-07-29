<?php
declare(strict_types=1);

namespace Prontoo\Application\Patients;

final class PatientReadService
{
    public function __construct(private PatientReadPort $port)
    {
    }

    public function appointmentRegistrationBlockReason(
        int $clinicId,
        int $patientId,
        callable $registrationComplete,
        callable $alertMessage,
    ): ?string {
        if ($clinicId <= 0 || $patientId <= 0) {
            return null;
        }
        $patient = $this->port->appointmentRegistration($clinicId, $patientId);
        if (!$patient) {
            return "Paciente não encontrado no consultório atual.";
        }
        if ($registrationComplete($patient)) {
            return null;
        }
        return (string) $alertMessage($patient);
    }

    public function legalGuardians(
        int $clinicId,
        int $patientId,
        callable $digits,
        callable $normalizeRelationship,
    ): array {
        if ($clinicId <= 0 || $patientId <= 0) {
            return [];
        }
        $rows = $this->port->activeLegalGuardians($clinicId, $patientId);
        foreach ($rows as &$row) {
            $row["id"] = (int) ($row["id"] ?? 0);
            $row["cpf"] = (string) $digits((string) ($row["cpf"] ?? ""));
            $row["relationship"] = (string) $normalizeRelationship(
                (string) ($row["relationship"] ?? "outro"),
            );
            $row["is_primary"] = (int) ($row["is_primary"] ?? 0);
        }
        unset($row);
        return $rows;
    }

    public function hasLegalGuardian(int $clinicId, int $patientId): bool
    {
        if ($clinicId <= 0 || $patientId <= 0) {
            return false;
        }
        return $this->port->hasActiveLegalGuardian($clinicId, $patientId);
    }
}
