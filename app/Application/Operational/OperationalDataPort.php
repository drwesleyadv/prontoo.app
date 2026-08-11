<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

use Closure;

interface OperationalDataPort
{
    public function run(
        string $operation,
        array $parameters,
        array $context,
    ): OperationalDataResult;

    public function atomically(Closure $operation): mixed;

    public function lastInsertId(): int;

}
