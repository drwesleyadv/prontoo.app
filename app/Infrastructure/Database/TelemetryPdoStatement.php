<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Database;

use PDOStatement;

final class TelemetryPdoStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $startedNs = hrtime(true);
        try {
            return parent::execute($params);
        } finally {
            PdoQueryTelemetry::record(max(0, hrtime(true) - $startedNs));
        }
    }
}
