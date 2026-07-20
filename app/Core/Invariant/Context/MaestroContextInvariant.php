<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

final class MaestroContextInvariant
{
    private const TABLES = ["pi_maestro_rules", "pi_maestro_executions"];

    private function __construct() {}

    public static function supports(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }

    public static function assertWrite(int $clinicId): array
    {
        if ($clinicId <= 0) {
            throw new \ProntooHttpError(
                500,
                "A rotina agendada não possui contexto de consultório demonstrável.",
            );
        }
        return [
            "context" => "maestro",
            "checked" => true,
            "clinic_id" => $clinicId,
        ];
    }
}
