<?php
declare(strict_types=1);

namespace Prontoo\Core\Database;

final class SchemaHardening
{
    private function __construct() {

    }

    public static function run(): void
    {

        if (!\Prontoo\Core\Architecture\OperationGateway::has('schema_validate_complete')) {
            throw new \RuntimeException(
                "Validador do schema não foi carregado.",
            );
        }
        \Prontoo\Core\Architecture\OperationGateway::invoke('schema_validate_complete', );
    }
}
