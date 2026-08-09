<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Financial;

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

final class FinancialInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function financial_daily_closing_ensure_schema(): void
    
    {
    
        static $validated = false;
        if ($validated) {
            return;
        }
        if (!\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_table_exists("pi_financial_daily_closings")) {
            throw new RuntimeException(
                "Schema incompleto: consolidação financeira diária indisponível.",
            );
        }
        $validated = true;
    
    }
}
