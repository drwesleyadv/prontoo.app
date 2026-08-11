<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class PatientsSqlCatalog06
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.patients.06.patient_appointment_docs_by_appointment.01' => (
                "SELECT id,appointment_id,title,type_key,document_status,issued_at FROM pi_documents WHERE clinic_id=? AND appointment_id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ") ORDER BY issued_at DESC,id DESC"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
