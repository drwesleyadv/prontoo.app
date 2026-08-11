<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

use Closure;

interface FinancialDataPort
{
    public function run(
        string $operation,
        array $parameters,
        array $context,
    ): FinancialDataResult;

    public function atomically(Closure $operation): mixed;

    public function lastInsertId(): int;

    public function ensure(string $contract): void;
}
