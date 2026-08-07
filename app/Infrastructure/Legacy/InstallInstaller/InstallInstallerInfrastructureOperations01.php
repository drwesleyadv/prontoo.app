<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Legacy\InstallInstaller;

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

    public static function install_state(): string
    
    {
    
        $cfg = has_cfg();
        $lock = is_file(storage_path("install.lock"));
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
    
        $perms = prontoo_fs_fileperms($path);
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
            "mode" => file_exists($path) ? install_path_mode($path) : "n/d",
            "parent" => $parent,
            "parent_exists" => is_dir($parent),
            "parent_writable" => is_writable($parent),
            "parent_mode" => is_dir($parent) ? install_path_mode($parent) : "n/d",
        ];
    
    }

    public static function install_write_failure_log(string $report): void
    
    {
    
        $file = storage_path("install-error-" . gmdate("Ymd-His") . ".log");
        prontoo_fs_write($file, $report . "\n");
        prontoo_fs_chmod($file, 0640);
    
    }
}
