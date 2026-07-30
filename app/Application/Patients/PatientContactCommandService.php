<?php
declare(strict_types=1);

namespace Prontoo\Application\Patients;

use InvalidArgumentException;
use RuntimeException;

final class PatientContactCommandService
{
    private const FIELDS = [
        'phone',
        'email',
        'address',
        'address_zip',
        'address_number',
        'address_neighborhood',
        'address_complement',
        'address_state',
        'address_city',
        'address_city_ibge',
    ];

    public function __construct(private PatientContactCommandPort $port)
    {
    }

    public function update(
        int $clinicId,
        int $patientId,
        int $userId,
        array $contact,
    ): array {
        if ($clinicId <= 0 || $patientId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Comando de contato do paciente inválido.');
        }
        $normalized = [];
        foreach (self::FIELDS as $field) {
            $normalized[$field] = trim((string) ($contact[$field] ?? ''));
        }
        $result = $this->port->update($clinicId, $patientId, $userId, $normalized);
        if ((string) ($result['status'] ?? '') !== 'updated') {
            throw new RuntimeException('Resultado inválido ao atualizar o contato do paciente.');
        }
        return ['status' => 'updated', 'patient_id' => $patientId];
    }
}
