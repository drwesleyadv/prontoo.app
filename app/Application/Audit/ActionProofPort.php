<?php
declare(strict_types=1);

namespace Prontoo\Application\Audit;

use Prontoo\Core\Invariant\Decision;

interface ActionProofPort
{
    public function write(string $route, array $context, Decision $decision): void;
}
