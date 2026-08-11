<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

interface OperationalDatabaseContextPort
{
    public function inTransaction(): bool;
}
