<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

final class PatientCareCommandService
{
    public function __construct(private OperationalUseCaseService $data)
    {
    }

    public function createClinicalNote(
        int $clinicId,
        int $patientId,
        ?int $appointmentId,
        string $recordType,
        string $title,
        string $content,
        int $createdByUserId,
        bool $startConsultation,
    ): array {
        return (array) $this->data->atomic(function () use (
            $clinicId,
            $patientId,
            $appointmentId,
            $recordType,
            $title,
            $content,
            $createdByUserId,
            $startConsultation,
        ): array {
            $this->data->result(
                'operational.patients.07.page_patient.26',
                [
                    $clinicId,
                    $patientId,
                    $appointmentId,
                    $recordType,
                    $title,
                    $createdByUserId,
                ],
            );
            $careId = $this->data->lastInsertId();
            $this->data->result(
                'operational.patients.07.page_patient.27',
                [$careId, $clinicId, $content],
            );
            $consultationStarted = false;
            if ($startConsultation && $appointmentId !== null && $appointmentId > 0) {
                $consultationStarted = $this->data->result(
                    'operational.patients.07.page_patient.28',
                    [$appointmentId, $clinicId, $patientId],
                )->rowCount() > 0;
            }
            return [
                'care_id' => $careId,
                'consultation_started' => $consultationStarted,
            ];
        });
    }
}
