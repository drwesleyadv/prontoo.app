<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

use InvalidArgumentException;
use RuntimeException;

final class PatientRevenueReceiptService
{
    public function __construct(private PatientRevenueReceiptPort $port)
    {
    }

    public function receive(
        int $clinicId,
        int $patientId,
        int $revenueId,
        int $userId,
        string $role,
    ): array {
        $role = strtolower(trim($role));
        if (!in_array($role, ['recepcionista', 'gerente'], true)) {
            throw new InvalidArgumentException('Este perfil não pode informar recebimentos do paciente.');
        }
        if ($clinicId <= 0 || $patientId <= 0 || $revenueId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Comando de recebimento do paciente inválido.');
        }
        $result = $this->port->receive(
            $clinicId,
            $patientId,
            $revenueId,
            $userId,
            $role,
        );
        $status = (string) ($result['status'] ?? '');
        if (!in_array($status, ['received', 'not_pending'], true)) {
            throw new RuntimeException('Resultado inválido ao informar recebimento do paciente.');
        }
        return [
            'status' => $status,
            'revenue_id' => max(0, (int) ($result['revenue_id'] ?? $revenueId)),
            'movement_id' => max(0, (int) ($result['movement_id'] ?? 0)),
            'amount_cents' => max(0, (int) ($result['amount_cents'] ?? 0)),
            'title' => (string) ($result['title'] ?? ''),
        ];
    }
}
