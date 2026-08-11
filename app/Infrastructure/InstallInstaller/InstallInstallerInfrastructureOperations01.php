<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\InstallInstaller;

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

final class InstallInstallerInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function install_open_database(array $context, bool $strictMode = false): PDO
    {
        $pdo = PDO::connect(
            "mysql:host=" . (string) ($context["db_host"] ?? "") . ";dbname=" . (string) ($context["db_name"] ?? "") . ";charset=utf8mb4",
            (string) ($context["db_user"] ?? ""),
            (string) ($context["db_pass"] ?? ""),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_assert_mysql_runtime($pdo);
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_apply_mysql_session_contract($pdo, $strictMode);
        return $pdo;
    }

    public static function install_database_probe(array $context, bool $strictMode = false): array
    {
        $probe = self::install_open_database($context, $strictMode);
        $version = (string) $probe->query("SELECT VERSION()")?->fetchColumn();
        $database = (string) $probe->query("SELECT DATABASE()")?->fetchColumn();
        $tableCount = (int) $probe
            ->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")
            ?->fetchColumn();
        $names = [];
        if ($tableCount > 0) {
            $statement = $probe->query(
                "SELECT TABLE_NAME AS table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY TABLE_NAME LIMIT 30",
            );
            foreach (($statement?->fetchAll(PDO::FETCH_ASSOC) ?: []) as $record) {
                $row = [];
                foreach ($record as $key => $value) {
                    if (is_string($key)) {
                        $row[strtolower($key)] = $value;
                    }
                }
                $name = mb_trim((string) ($row["table_name"] ?? ""));
                if ($name !== "") {
                    $names[] = $name;
                }
            }
        }
        return [
            'version' => $version,
            'database' => $database,
            'table_count' => $tableCount,
            'table_names' => $names,
        ];
    }

    public static function assert_runtime_sequence_contract(): void
    {
        \Prontoo\Infrastructure\Database\SeqContract::assert(
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo(),
        );
    }

    public static function runtime_database_in_transaction(): bool
    {
        return \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->inTransaction();
    }

    public static function prepare_fresh_database(): void
    {
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::install_fresh_schema();
    }

    public static function revert_failed_commissioning(): void
    {
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::schema_cleanup_failed_install(
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::prontoo_schema_table_names(),
        );
    }

    public static function install_state(): string
    
    {
    
        $cfg = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg();
        $lock = is_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("install.lock"));
        if ($cfg && $lock) {
            return "installed";
        }
        if ($cfg && !$lock) {
            return "config_without_lock";
        }
        if (!$cfg && $lock) {
            return "lock_without_config";
        }
        return "fresh";
    
    }

    public static function install_path_mode(string $path): string
    
    {
    
        $perms = \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_fileperms($path);
        return $perms === false ? "n/d" : substr(sprintf("%o", $perms), -4);
    
    }

    public static function install_path_report(string $label, string $path): array
    
    {
    
        $parent = dirname($path);
        return [
            "label" => $label,
            "path" => $path,
            "realpath" => realpath($path) ?: "n/d",
            "exists" => file_exists($path),
            "is_dir" => is_dir($path),
            "is_file" => is_file($path),
            "writable" => is_writable($path),
            "mode" => file_exists($path) ? \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_path_mode($path) : "n/d",
            "parent" => $parent,
            "parent_exists" => is_dir($parent),
            "parent_writable" => is_writable($parent),
            "parent_mode" => is_dir($parent) ? \Prontoo\Infrastructure\InstallInstaller\InstallInstallerInfrastructureOperations01::install_path_mode($parent) : "n/d",
        ];
    
    }

    public static function install_write_failure_log(string $report): void
    
    {
    
        $file = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("install-error-" . gmdate("Ymd-His") . ".log");
        \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_write($file, $report . "\n");
        \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_chmod($file, 0640);
    
    }
}
