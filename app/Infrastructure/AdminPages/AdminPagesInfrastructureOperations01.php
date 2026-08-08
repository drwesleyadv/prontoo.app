<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\AdminPages;

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

final class AdminPagesInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function platform_storage_status(): array
    
    {
    
        $dir = storage_path();
        $ok = is_dir($dir) && is_writable($dir);
        $free = function_exists("disk_free_space") ? @disk_free_space($dir) : false;
        return [
            "ok" => $ok,
            "free_bytes" => $free === false ? null : (float) $free,
        ];
    
    }

    public static function admin_alerts_ensure_schema(): void
    
    {
    
        static $validated = false;
        if ($validated || !has_cfg()) {
            return;
        }
        if (!db_table_exists("pi_admin_alerts")) {
            throw new RuntimeException(
                "Schema incompleto: alertas administrativos indisponíveis.",
            );
        }
        foreach (["sender_clinic_id", "source_scope"] as $column) {
            if (!db_column_exists("pi_admin_alerts", $column)) {
                throw new RuntimeException(
                    "Schema incompleto: pi_admin_alerts.{$column} ausente.",
                );
            }
        }
        $validated = true;
    
    }
}
