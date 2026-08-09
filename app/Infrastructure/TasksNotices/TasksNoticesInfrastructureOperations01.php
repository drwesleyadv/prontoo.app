<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\TasksNotices;

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

final class TasksNoticesInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function readonly_support_alerts_ensure_schema(): void
    
    {
    
        static $validated = false;
        if ($validated || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        if (!\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_table_exists("pi_admin_alerts")) {
            throw new RuntimeException(
                "Schema incompleto: mensagens de suporte indisponíveis.",
            );
        }
        foreach (["sender_clinic_id", "source_scope"] as $column) {
            if (!\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_column_exists("pi_admin_alerts", $column)) {
                throw new RuntimeException(
                    "Schema incompleto: pi_admin_alerts.{$column} ausente.",
                );
            }
        }
        $validated = true;
    
    }
}
