<?php
declare(strict_types=1);

namespace Prontoo\Application\SecurityAccess;

use JsonException;
use RuntimeException;

final class MfaRecordService
{
    public function __construct(private MfaRecordPort $port)
    {
    }

    public function hasConfig(): bool
    {
        return $this->port->hasConfig();
    }

    public function load(int $userId): ?array
    {
        if ($userId <= 0 || !$this->port->hasConfig()) {
            return null;
        }
        $raw = $this->port->load($userId);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        try {
            $record = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Cadastro MFA corrompido.', 0, $error);
        }
        if (!is_array($record) || (int) ($record['v'] ?? 0) !== 1 || empty($record['secret'])) {
            throw new RuntimeException('Cadastro MFA incompleto.');
        }
        return $record;
    }

    public function save(int $userId, array $record): void
    {
        if ($userId <= 0 || (int) ($record['v'] ?? 0) !== 1 || empty($record['secret'])) {
            throw new RuntimeException('Cadastro MFA incompleto.');
        }
        $this->port->save(
            $userId,
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    public function delete(int $userId): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Usuário MFA inválido.');
        }
        $this->port->delete($userId);
    }

    public function secretKey(): string
    {
        return $this->port->secretKey();
    }
}
