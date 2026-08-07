<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization;

interface ActionDefinitionSource
{
    public function definitions(): array;
}
