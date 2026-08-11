<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

interface OperationalSchemaPort
{
    public function ensure(string $contract): void;

    public function tableExists(string $table): bool;

    public function columnExists(string $table, string $column): bool;
}
