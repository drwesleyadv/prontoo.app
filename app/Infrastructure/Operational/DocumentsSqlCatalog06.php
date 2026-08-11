<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class DocumentsSqlCatalog06
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.documents.06.page_procedures.01' => (
                "SELECT id,active FROM pi_procedures WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.06.page_procedures.02' => (
                "UPDATE pi_procedures SET active=?,updated_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.06.page_procedures.03' => (
                "SELECT id FROM pi_procedures WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.06.page_procedures.04' => (
                "UPDATE pi_procedures SET title=?,category=?,description=?,duration_minutes=?,price_cents=?,payment_methods=?,pre_instructions=?,post_care=?,updated_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.06.page_procedures.05' => (
                "INSERT INTO pi_procedures (clinic_id,title,category,description,duration_minutes,price_cents,payment_methods,pre_instructions,post_care,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.documents.06.page_procedures.06' => (
                "SELECT id,title,category,description,duration_minutes,price_cents,payment_methods,pre_instructions,post_care,active FROM pi_procedures WHERE id=? AND clinic_id=?"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
