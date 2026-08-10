<?php
declare(strict_types=1);

namespace Prontoo\Application\SecurityAccess;

interface SecurityAccessPersistencePort
{
    public function hasConfig(): bool;

    public function value(string $sql, array $params = []): mixed;

    public function secretKey(): string;
}
