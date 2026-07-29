<?php
declare(strict_types=1);

namespace Prontoo\Application\Patients;

use InvalidArgumentException;
use RuntimeException;

final class PatientTabCommandService
{
    public function __construct(private PatientTabCommandPort $port)
    {
    }

    public function create(
        int $clinicId,
        int $patientId,
        string $label,
        string $iconName,
        int $userId,
    ): array {
        $label = trim($label);
        $iconName = trim($iconName);
        if ($clinicId <= 0 || $patientId <= 0 || $userId <= 0 || $label === '' || $iconName === '') {
            throw new InvalidArgumentException('Comando de aba do paciente inválido.');
        }
        $result = $this->port->createIfAbsent(
            $clinicId,
            $patientId,
            $label,
            $iconName,
            $userId,
        );
        $status = (string) ($result['status'] ?? '');
        if (!in_array($status, ['created', 'duplicate'], true)) {
            throw new RuntimeException('Resultado inválido ao criar aba do paciente.');
        }
        return [
            'status' => $status,
            'id' => max(0, (int) ($result['id'] ?? 0)),
            'sort_order' => max(0, (int) ($result['sort_order'] ?? 0)),
        ];
    }
}
