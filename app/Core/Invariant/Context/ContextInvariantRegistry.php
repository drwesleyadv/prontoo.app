<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

final class ContextInvariantRegistry
{
    private function __construct() {
        








    }

    public static function assertWrite(
        string $table,
        string $operation,
        string $sql,
        array $params,
        ?array $insert,
        int $clinicId,
    ): array {
        








        if (AppointmentContextInvariant::supports($table)) {
            return AppointmentContextInvariant::assertWrite(
                $table,
                $operation,
                $sql,
                $params,
                $insert,
                $clinicId,
            );
        }
        if (PeopleContextInvariant::supports($table)) {
            return PeopleContextInvariant::assertWrite($table);
        }
        if (TaskContextInvariant::supports($table)) {
            return TaskContextInvariant::assertWrite(
                $table,
                $operation,
                $sql,
                $params,
                $insert,
            );
        }
        if (DocumentContextInvariant::supports($table)) {
            return DocumentContextInvariant::assertWrite($table);
        }
        if (FinancialContextInvariant::supports($table)) {
            return FinancialContextInvariant::assertWrite($table);
        }
        if (MaestroContextInvariant::supports($table)) {
            return MaestroContextInvariant::assertWrite($clinicId);
        }
        if (AccessContextInvariant::supports($table)) {
            return AccessContextInvariant::assertWrite($table);
        }
        return [
            "context" => "generic",
            "checked" => true,
            "entity_table" => $table,
        ];
    }

    public static function logicSelfTest(): array
    {
        








        $cases = [
            "appointments" => AppointmentContextInvariant::supports("pi_appointments"),
            "people" => PeopleContextInvariant::supports("pi_patients"),
            "tasks" => TaskContextInvariant::supports("pi_tasks"),
            "documents" => DocumentContextInvariant::supports("pi_documents"),
            "financial" => FinancialContextInvariant::supports("pi_financial_revenues"),
            "maestro" => MaestroContextInvariant::supports("pi_maestro_rules"),
            "access" => AccessContextInvariant::supports("pi_permissions"),
        ];
        $failed = array_keys(array_filter($cases, static  fn(bool $ok): bool => !$ok));
        return [
            "ok" => $failed === [],
            "passed" => count($cases) - count($failed),
            "total" => count($cases),
            "failed" => $failed,
        ];
    }
}
