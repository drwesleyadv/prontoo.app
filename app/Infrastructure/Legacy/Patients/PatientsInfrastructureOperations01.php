<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Legacy\Patients;

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

final class PatientsInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function patient_tabs_ensure_schema(): void
    
    {
    
        static $validated = false;
        if ($validated) {
            return;
        }
        if (!db_table_exists("pi_patient_tabs")) {
            throw new RuntimeException(
                "Schema incompleto: abas do paciente indisponíveis.",
            );
        }
        $validated = true;
    
    }

    public static function patient_guardians_ensure_schema(): void
    
    {
    
        static $validated = false;
        if ($validated) {
            return;
        }
        if (!db_table_exists("pi_patient_guardians")) {
            throw new RuntimeException(
                "Schema incompleto: responsáveis do paciente indisponíveis.",
            );
        }
        $validated = true;
    
    }
}
