<?php
declare(strict_types=1);

namespace Prontoo\Core\Database;

final class SchemaHardening
{
    private function __construct() {

    }

    public static function run(): void
    {

        if (!\function_exists("schema_validate_complete")) {
            throw new \RuntimeException(
                "Validador do schema não foi carregado.",
            );
        }
        \schema_validate_complete();
    }
}
