<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

use RuntimeException;

final class FinancialSqlCatalog17
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            "financial.17.admin_page.01" => (
                "INSERT INTO pi_financial_accounts (clinic_id,name,bank_name,account_type,opening_balance_cents,active,created_by,created_at) VALUES (?,?,?,?,0,1,?,NOW())"
            ),
            default => throw new RuntimeException('Operação SQL financeira desconhecida.'),
        };
    }
}
