<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Appointments;

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

final class AppointmentsInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function agenda_notes_ensure_schema(int $cid = 0): bool
    
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
        return $ready =
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_table_exists("pi_agenda_notes") &&
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_column_exists("pi_agenda_notes", "start_at") &&
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_column_exists("pi_agenda_notes", "end_at") &&
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_column_exists("pi_agenda_notes", "deleted_by") &&
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_column_exists("pi_agenda_notes", "deleted_at");
    
    }
}
