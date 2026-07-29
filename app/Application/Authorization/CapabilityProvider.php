<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization;

use Prontoo\Domain\Authorization\ActionContract;

interface CapabilityProvider
{
    public  function grants(
        ActionContract $contract,
        string $capability,
        array $context,
    ): bool;
}
