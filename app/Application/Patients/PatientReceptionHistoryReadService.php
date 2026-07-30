<?php
declare(strict_types=1);

namespace Prontoo\Application\Patients;

use InvalidArgumentException;

final class PatientReceptionHistoryReadService
{
    public function __construct(private PatientReceptionHistoryReadPort $port)
    {
    }

    public function read(
        int $clinicId,
        int $patientId,
        int $personId,
        string $phoneDigits,
    ): array {
        $phoneDigits = substr(preg_replace('/\D+/', '', $phoneDigits) ?? '', 0, 11);
        if ($clinicId <= 0 || $patientId <= 0) {
            throw new InvalidArgumentException('Escopo de histórico da recepção inválido.');
        }
        if ($personId <= 0 && $phoneDigits === '') {
            return ['leads' => [], 'events' => [], 'users' => []];
        }
        $model = $this->port->read($clinicId, $patientId, max(0, $personId), $phoneDigits);
        return [
            'leads' => array_values(is_array($model['leads'] ?? null) ? $model['leads'] : []),
            'events' => is_array($model['events'] ?? null) ? $model['events'] : [],
            'users' => is_array($model['users'] ?? null) ? $model['users'] : [],
        ];
    }
}
