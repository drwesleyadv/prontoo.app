<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class ClinicConfigSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.clinic_config.02.clinic_options_global.01' => (
                "SELECT id,display_name FROM pi_clinics ORDER BY id DESC LIMIT 500"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
