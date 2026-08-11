<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class SupportTelemetrySqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.support_telemetry.01.telemetry_sequence_records_series_20d.01' => (
                OperationalSequenceSql::dailyMutationSeries()
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
