<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AppointmentsSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.appointments.01.user_work_hours.01' => (
                "SELECT weekday,active,start_time,end_time FROM pi_user_work_hours WHERE clinic_id=? AND user_id=? ORDER BY FIELD(weekday,1,2,3,4,5,6,0)"
            ),
            'operational.appointments.01.save_user_work_hours.01' => (
                "INSERT INTO pi_user_work_hours (clinic_id,user_id,weekday,active,start_time,end_time,updated_by,updated_at) VALUES (?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE active=VALUES(active),start_time=VALUES(start_time),end_time=VALUES(end_time),updated_by=VALUES(updated_by),updated_at=NOW()"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
