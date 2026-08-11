<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AppointmentsSqlCatalog06
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.appointments.06.appointment_procedure_id_from_post.01' => (
                "SELECT id FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1"
            ),
            'operational.appointments.06.appointment_procedure_price_cents.01' => (
                "SELECT price_cents FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1"
            ),
            'operational.appointments.06.appointment_min_duration_message.01' => (
                "SELECT title,duration_minutes FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
