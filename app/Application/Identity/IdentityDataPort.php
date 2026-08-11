<?php
declare(strict_types=1);

namespace Prontoo\Application\Identity;

use Closure;

interface IdentityDataPort
{
    public function run(
        string $operation,
        array $parameters,
        array $context,
    ): IdentityDataResult;

    public function atomically(Closure $operation): mixed;

    public function lastInsertId(): int;

    public function ensure(string $contract): void;
}
