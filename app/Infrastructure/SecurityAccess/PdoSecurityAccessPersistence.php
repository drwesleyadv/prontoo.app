<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SecurityAccess;

use Prontoo\Application\SecurityAccess\SecurityAccessPersistencePort;
use Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01;
use Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01;

final class PdoSecurityAccessPersistence implements SecurityAccessPersistencePort
{
    public function hasConfig(): bool
    {
        return SupportFoundationInfrastructureOperations01::has_cfg();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $statement = DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        return $value === false ? null : $value;
    }

    public function secretKey(): string
    {
        try {
            $value = $this->value("SELECT meta_value FROM pi_meta WHERE meta_key='app_secret'");
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        } catch (\Throwable) {
        }
        return (string) (SupportFoundationInfrastructureOperations01::cfg()['secret'] ?? 'prontoo');
    }
}
