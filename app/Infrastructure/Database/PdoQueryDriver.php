<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Database;

use PDOStatement;
use Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01;

final class PdoQueryDriver
{
    private function __construct()
    {
    }

    public static function inTransaction(): bool
    {
        return DatabaseSchemaInfrastructureOperations01::pdo()->inTransaction();
    }

    public static function execute(string $sql, array $parameters): PDOStatement
    {
        $statement = DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    public static function lastInsertId(): int
    {
        return (int) DatabaseSchemaInfrastructureOperations01::pdo()->lastInsertId();
    }
}
