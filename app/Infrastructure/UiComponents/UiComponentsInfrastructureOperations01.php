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
            !is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'has_cfg']) ||
            !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg() ||
            !is_callable([\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::class, 'db_table_exists'])
        ) {
            return $ready = false;
        }
        return $ready = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_table_exists("pi_user_cmdbar_access");
    
    }
}
