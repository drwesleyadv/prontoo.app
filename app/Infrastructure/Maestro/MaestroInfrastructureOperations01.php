<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Maestro;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class MaestroInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function maestro_global_physical_rollback(): void
    
    {
    
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        foreach (
            [
                "pi_maestro_rules",
                "pi_maestro_executions",
                "pi_maestro_job_runs",
                "pi_maestro_job_stats",
            ]
            as $table
        ) {
            if (!\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_table_exists($table)) {
                throw new RuntimeException("Schema incompleto: {$table} ausente.");
            }
        }
    
    }
}
