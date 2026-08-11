<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog15
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.15.creditor_upsert_from_post.01" => (
                "INSERT INTO pi_financial_counterparties (clinic_id,person_id,kind,notes,active,created_by,created_at) VALUES (?,?,?,?,1,?,NOW()) ON DUPLICATE KEY UPDATE notes=VALUES(notes), active=1, updated_at=NOW()"
            ),
            "financial.15.creditor_upsert_from_post.02" => (
                "SELECT id FROM pi_financial_counterparties WHERE clinic_id=? AND person_id=? AND kind='credor' LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
