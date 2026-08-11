<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use Closure;
use Prontoo\Application\Identity\IdentityDataPort;
use Prontoo\Application\Identity\IdentityDataResult;
use RuntimeException;

final class PdoIdentityDataRepository implements IdentityDataPort
{
    public function __construct(private Closure $guardedQueryExecutor)
    {
    }

    public function run(
        string $operation,
        array $parameters,
        array $context,
    ): IdentityDataResult {
        if (
            $operation === 'identity.auth05.lock_person_user_identity.01'
            && !\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->inTransaction()
        ) {
            throw new RuntimeException('Não foi possível iniciar a gravação segura do usuário.');
        }
        $sql = $this->statement($operation, $context);
        $statement = ($this->guardedQueryExecutor)($sql, $parameters);
        if (!is_object($statement) || !method_exists($statement, 'fetchAll')) {
            throw new RuntimeException('Executor de identidade retornou resultado inválido.');
        }
        $rows = preg_match('/^\s*(?:SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $sql) === 1
            ? $statement->fetchAll()
            : [];
        $affectedRows = method_exists($statement, 'rowCount')
            ? (int) $statement->rowCount()
            : 0;
        if (method_exists($statement, 'closeCursor')) {
            $statement->closeCursor();
        }
        if (
            in_array($operation, [
                'identity.security02.user_auth_generation_ensure.03',
            ], true)
            && is_callable([\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::class, 'server_json_cache_clear_categories'])
        ) {
            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories([
                'meta',
                'context',
            ]);
        }
        return new IdentityDataResult(is_array($rows) ? $rows : [], $affectedRows);
    }

    public function atomically(Closure $operation): mixed
    {
        return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_tx($operation);
    }

    public function lastInsertId(): int
    {
        return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_last_insert_id();
    }

    public function ensure(string $contract): void
    {
        match ($contract) {
            'onboarding_tips' => \Prontoo\Infrastructure\AuthOnboarding\AuthOnboardingInfrastructureOperations01::onboarding_tips_ensure_schema(),
            default => throw new RuntimeException('Contrato de identidade desconhecido.'),
        };
    }

    private function statement(string $operation, array $context): string
    {
        $parts = explode('.', $operation);
        return match ((string) ($parts[1] ?? '')) {
            'security01' => IdentitySecuritySqlCatalog01::statement($operation, $context),
            'security02' => IdentitySecuritySqlCatalog02::statement($operation, $context),
            'security03' => IdentitySecuritySqlCatalog03::statement($operation, $context),
            'security04' => IdentitySecuritySqlCatalog04::statement($operation, $context),
            'auth01' => IdentityAuthSqlCatalog01::statement($operation, $context),
            'auth02' => IdentityAuthSqlCatalog02::statement($operation, $context),
            'auth03' => IdentityAuthSqlCatalog03::statement($operation, $context),
            'auth04' => IdentityAuthSqlCatalog04::statement($operation, $context),
            'auth05' => IdentityAuthSqlCatalog05::statement($operation, $context),
            'auth06' => IdentityAuthSqlCatalog06::statement($operation, $context),
            'auth07' => IdentityAuthSqlCatalog07::statement($operation, $context),
            'permissions01' => IdentityPermissionsSqlCatalog01::statement($operation, $context),
            'permissions02' => IdentityPermissionsSqlCatalog02::statement($operation, $context),
            'permissions03' => IdentityPermissionsSqlCatalog03::statement($operation, $context),
            'permissions04' => IdentityPermissionsSqlCatalog04::statement($operation, $context),
            'permissions05' => IdentityPermissionsSqlCatalog05::statement($operation, $context),
            default => throw new RuntimeException('Catálogo de identidade desconhecido.'),
        };
    }
}
