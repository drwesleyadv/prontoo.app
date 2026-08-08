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
            !function_exists("has_cfg") ||
            !has_cfg() ||
            !function_exists("db_table_exists")
        ) {
            return $ready = false;
        }
        return $ready =
            db_table_exists("pi_agenda_notes") &&
            db_column_exists("pi_agenda_notes", "deleted_by") &&
            db_column_exists("pi_agenda_notes", "deleted_at");
    
    }
}
