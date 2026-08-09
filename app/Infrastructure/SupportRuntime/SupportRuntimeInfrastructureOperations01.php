<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SupportRuntime;

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

final class SupportRuntimeInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function prontoo_min_php_version(): string
    
    {
    
        return defined("PRONTOO_MIN_PHP_VERSION")
            ? (string) PRONTOO_MIN_PHP_VERSION
            : "8.4.0";
    
    }

    public static function prontoo_php_runtime_ok(?string $version = null): bool
    
    {
    
        $version = $version ?? PHP_VERSION;
        if (!preg_match('/^(\d+)\.(\d+)(?:\.|$)/', $version, $match)) {
            return false;
        }
        return (int) $match[1] === 8 &&
            (int) $match[2] === 4 &&
            version_compare($version, \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_min_php_version(), ">=");
    
    }

    public static function prontoo_php_runtime_message(?string $version = null): string
    
    {
    
        $version = $version ?? PHP_VERSION;
        return "PHP " .
            $version .
            " detectado; o Prontoo exige exclusivamente a família PHP 8.4, " .
            "a partir de " .
            \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_min_php_version() .
            ".";
    
    }

    public static function prontoo_ini_size_to_bytes(mixed $value): ?int
    
    {
    
        if ($value === null || $value === false) {
            return null;
        }
    
        $raw = mb_trim((string) $value);
        if ($raw === "") {
            return null;
        }
    
        if ($raw === "-1") {
            return null;
        }
    
        if (!preg_match('/^(-?\d+(?:\.\d+)?)\s*([kmg])?b?$/i', $raw, $match)) {
            return (int) $raw;
        }
    
        $number = (float) $match[1];
        $unit = strtolower((string) ($match[2] ?? ""));
        $multiplier = match ($unit) {
            "g" => 1024 * 1024 * 1024,
            "m" => 1024 * 1024,
            "k" => 1024,
            default => 1,
        };
    
        return (int) round($number * $multiplier, 0, \RoundingMode::HalfAwayFromZero);
    
    }

    public static function prontoo_memory_limit_meets(
        int $minimumBytes,
        mixed $memoryLimit = null,
    ): bool 
    {
    
        $bytes = \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_ini_size_to_bytes($memoryLimit ?? ini_get("memory_limit"));
        return $bytes === null || $bytes >= $minimumBytes;
    
    }

    public static function prontoo_memory_limit_label(mixed $memoryLimit = null): string
    
    {
    
        $raw = $memoryLimit ?? ini_get("memory_limit");
        $raw = mb_trim((string) $raw);
        return $raw !== "" ? $raw : "não informado";
    
    }

    public static function prontoo_runtime_attempt(
        callable $operation,
        mixed $fallback = false,
        string $label = "operação de arquivo",
        bool $logFailure = true,
    ): mixed 
    {
    
        $previous = set_error_handler(
            static function (
                int $severity,
                string $message,
                string $file,
                int $line,
            ): void {
    
                throw new ErrorException($message, 0, $severity, $file, $line);
            },
        );
        try {
            return $operation();
        } catch (Throwable $e) {
            if ($logFailure) {
                error_log("[Prontoo runtime] {$label}: " . $e->getMessage());
            }
            return $fallback;
        } finally {
            restore_error_handler();
        }
    
    }

    public static function prontoo_fs_mkdir(
        string $directory,
        int $mode = 0750,
        bool $recursive = true,
        bool $logFailure = true,
    ): bool 
    {
    
        if (is_dir($directory)) {
            return true;
        }
        return (bool) \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_runtime_attempt(
            static  fn(): bool => mkdir($directory, $mode, $recursive),
            false,
            "criação de diretório {$directory}",
            $logFailure,
        );
    
    }

    public static function prontoo_fs_read(
        string $path,
        bool $logFailure = true,
    ): ?string 
    {
    
        if (!is_file($path)) {
            return null;
        }
        $value = \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_runtime_attempt(
            static  fn(): string|false => file_get_contents($path),
            false,
            "leitura de {$path}",
            $logFailure,
        );
        return is_string($value) ? $value : null;
    
    }

    public static function prontoo_fs_write(
        string $path,
        string $contents,
        int $flags = LOCK_EX,
        bool $logFailure = true,
    ): int|false 
    {
    
        return \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_runtime_attempt(
            static  fn(): int|false => file_put_contents($path, $contents, $flags),
            false,
            "gravação de {$path}",
            $logFailure,
        );
    
    }

    public static function prontoo_fs_unlink(string $path, bool $logFailure = true): bool
    
    {
    
        if (!file_exists($path) && !is_link($path)) {
            return true;
        }
        return (bool) \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_runtime_attempt(
            static  fn(): bool => unlink($path),
            false,
            "exclusão de {$path}",
            $logFailure,
        );
    
    }

    public static function prontoo_fs_chmod(
        string $path,
        int $mode,
        bool $logFailure = true,
    ): bool 
    {
    
        if (!file_exists($path)) {
            return false;
        }
        return (bool) \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_runtime_attempt(
            static  fn(): bool => chmod($path, $mode),
            false,
            "permissão de {$path}",
            $logFailure,
        );
    
    }

    public static function prontoo_fs_rename(
        string $source,
        string $destination,
        bool $logFailure = true,
    ): bool 
    {
    
        return (bool) \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_runtime_attempt(
            static  fn(): bool => rename($source, $destination),
            false,
            "renomeação de {$source} para {$destination}",
            $logFailure,
        );
    
    }

    public static function prontoo_fs_fileperms(
        string $path,
        bool $logFailure = false,
    ): int|false 
    {
    
        return \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_runtime_attempt(
            static  fn(): int|false => fileperms($path),
            false,
            "leitura de permissões de {$path}",
            $logFailure,
        );
    
    }

    public static function prontoo_fs_move_upload(
        string $source,
        string $destination,
        bool $logFailure = true,
    ): bool 
    {
    
        return (bool) \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_runtime_attempt(
            static  fn(): bool => move_uploaded_file($source, $destination),
            false,
            "movimentação de upload para {$destination}",
            $logFailure,
        );
    
    }
}
