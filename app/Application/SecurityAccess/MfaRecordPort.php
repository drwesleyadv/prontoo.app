<?php
declare(strict_types=1);

namespace Prontoo\Application\SecurityAccess;

interface MfaRecordPort
{
    public function hasConfig(): bool;

    public function load(int $userId): ?string;

    public function save(int $userId, string $encodedRecord): void;

    public function delete(int $userId): void;

    public function secretKey(): string;
}
