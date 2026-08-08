<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function financial_movement_write_guard(string $sql, array $params): void
{
    \Prontoo\Runtime\FinancialGuard\FinancialGuardRuntimeOperations01::financial_movement_write_guard($sql, $params);
}
function financial_cashier_requires_attention_light(array $c): bool
{
    return \Prontoo\Runtime\FinancialGuard\FinancialGuardRuntimeOperations01::financial_cashier_requires_attention_light($c);
}
