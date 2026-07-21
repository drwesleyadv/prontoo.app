<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

final class FinancialContextInvariant
{
    private function __construct() {}

    public static function supports(string $table): bool
    {
        return str_starts_with($table, "pi_financial_") ||
            in_array(
                $table,
                ["pi_cash_sessions", "pi_cash_closing_reviews", "pi_subscription_payments"],
                true,
            );
    }

    public static function assertWrite(string $table): array
    {
        return [
            "context" => "financial",
            "checked" => true,
            "entity_table" => $table,
        ];
    }
}
