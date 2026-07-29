<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

use Prontoo\Core\Invariant\Workflow\AppointmentWorkflow;

final class AppointmentContextInvariant
{
    private const TABLES = [
        "pi_appointments",
        "pi_blocks",
        "pi_agenda_notes",
        "pi_user_work_hours",
        "pi_procedures",
    ];

    private function __construct() {

    }

    public static function supports(string $table): bool
    {

        return in_array($table, self::TABLES, true);
    }

    public static function assertWrite(
        string $table,
        string $operation,
        string $sql,
        array $params,
        ?array $insert,
        int $clinicId,
    ): array {

        $workflow = AppointmentWorkflow::assertWrite(
            $table,
            $operation,
            $sql,
            $params,
            $insert,
            $clinicId,
        );
        return [
            "context" => "appointments",
            "checked" => true,
            "workflow" => $workflow,
        ];
    }
}
