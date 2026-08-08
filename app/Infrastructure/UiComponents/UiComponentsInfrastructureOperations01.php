<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\UiComponents;

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

final class UiComponentsInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function cmdbar_access_schema_ready(): bool
    
    {
    
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (
            !function_exists("has_cfg") ||
            !has_cfg() ||
            !function_exists("db_table_exists")
        ) {
            return $ready = false;
        }
        return $ready = db_table_exists("pi_user_cmdbar_access");
    
    }
}
