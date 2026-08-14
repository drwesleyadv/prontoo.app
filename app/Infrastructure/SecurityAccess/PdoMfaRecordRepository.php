<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SecurityAccess;

use Closure;
use Prontoo\Application\SecurityAccess\MfaRecordPort;

final class PdoMfaRecordRepository implements MfaRecordPort
{
    public function __construct(private Closure $guardedQueryExecutor)
    {
    }

    public function hasConfig(): bool
    {
        return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg();
    }

    public function load(int $userId): ?string
    {
        $statement = ($this->guardedQueryExecutor)(
            'SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1',
            [\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_meta_key($userId)],
        );
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        return is_string($value) ? $value : null;
    }

    public function save(int $userId, string $encodedRecord): void
    {
        ($this->guardedQueryExecutor)(
            'INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value), updated_at=NOW()',
            [\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_meta_key($userId), $encodedRecord],
        );
        $this->invalidate();
    }

    public function delete(int $userId): void
    {
        ($this->guardedQueryExecutor)(
            'DELETE FROM pi_meta WHERE meta_key=?',
            [\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_meta_key($userId)],
        );
        $this->invalidate();
    }

    public function secretKey(): string
    {
        $secret = '';
        try {
            $statement = ($this->guardedQueryExecutor)(
                "SELECT meta_value FROM pi_meta WHERE meta_key='app_secret'",
                [],
            );
            $value = $statement->fetchColumn();
            $statement->closeCursor();
            if (is_string($value)) {
                $secret = trim($value);
            }
        } catch (\Throwable) {
        }
        if (strlen($secret) < 32) {
            $config = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()
                ? \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg()
                : [];
            $secret = trim((string) ($config['secret'] ?? ''));
        }
        if (strlen($secret) < 32) {
            throw new \RuntimeException(
                'Segredo criptográfico da instalação indisponível para o MFA.',
            );
        }
        return $secret;
    }

    private function invalidate(): void
    {
        if (is_callable([\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::class, 'server_json_cache_clear_categories'])) {
            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories([
                'meta',
                'context',
            ]);
        }
    }
}
