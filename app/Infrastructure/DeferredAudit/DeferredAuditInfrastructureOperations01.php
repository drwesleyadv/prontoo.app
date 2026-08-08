<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\DeferredAudit;

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

final class DeferredAuditInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function maestro_deferred_storage_dir(): string
    
    {
        return storage_path("maestro-deferred");
    
    }

    public static function maestro_deferred_storage_dirs(string $type = "audit"): array
    
    {
        return array_values(array_unique([
            maestro_deferred_storage_dir(),
            storage_path("logs/maestro-deferred-emergency"),
        ]));
    
    }

    public static function maestro_deferred_canonicalize(mixed $value): mixed
    
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map("maestro_deferred_canonicalize", $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = maestro_deferred_canonicalize($item);
        }
        return $value;
    
    }

    public static function maestro_deferred_spool_write(string $dir, string $id, string $json): bool
    
    {
        $temporary = null;
        try {
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            if (!is_dir($dir) || !is_writable($dir)) {
                return false;
            }
            if (function_exists("security_storage_deny_file")) {
                \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);
            }
            $temporary = tempnam($dir, ".pending-");
            if (
                !is_string($temporary) ||
                @file_put_contents($temporary, $json) !== strlen($json)
            ) {
                return false;
            }
            @chmod($temporary, 0640);
            if (!@rename($temporary, $dir . "/" . $id . ".json")) {
                return false;
            }
            $temporary = null;
            return true;
        } catch (Throwable $error) {
            error_log("[Prontoo Maestro spool] " . $error->getMessage());
            return false;
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                @unlink($temporary);
            }
        }
    
    }

    public static function maestro_deferred_policy_allows(array $envelope): bool
    
    {
        $payload = (array) ($envelope["payload"] ?? []);
        $event = mb_substr((string) ($payload["event"] ?? ""), 0, 80);
        return $event !== "" && (!function_exists("audit_should_write") || audit_should_write($event));
    
    }

    public static function maestro_deferred_restore_scope(array $envelope): array
    
    {
        $payload = (array) ($envelope["payload"] ?? []);
        $context = isset($payload["context"]) && is_array($payload["context"])
            ? $payload["context"]
            : [];
        $proof = isset($payload["proof_context"]) && is_array($payload["proof_context"])
            ? $payload["proof_context"]
            : [];
        $clinicId = (int) ($context["clinic_id"] ?? ($proof["clinic_id"] ?? 0));
        if ($clinicId > 0) {
            $context["clinic_id"] = $clinicId;
        }
        $payload["context"] = $context;
        $envelope["payload"] = $payload;
        return $envelope;
    
    }

    public static function maestro_deferred_retry_metadata(string $file): array
    
    {
        $name = basename($file);
        $attempt = 0;
        $nextAt = 0;
        if (preg_match('/\.retry-(\d+)(?:-at-(\d+))?\.json$/', $name, $match) === 1) {
            $attempt = max(0, (int) ($match[1] ?? 0));
            $nextAt = max(0, (int) ($match[2] ?? 0));
        }
        return ["attempt" => $attempt, "next_at" => $nextAt];
    
    }

    public static function maestro_deferred_retry_delay_seconds(int $attempt): int
    
    {
        $schedule = [600, 1800, 3600, 10800, 21600, 43200, 86400, 172800];
        return $schedule[max(0, min(count($schedule) - 1, $attempt - 1))];
    
    }

    public static function maestro_deferred_dead_letter(
        string $claimed,
        string $dir,
        string $id,
        string $reason,
    ): bool 
    {
        $deadDir = $dir . "/dead-letter";
        if (!is_dir($deadDir)) {
            @mkdir($deadDir, 0750, true);
        }
        if (!is_dir($deadDir) || !is_writable($deadDir)) {
            return false;
        }
        $safeReason = preg_replace('/[^a-z0-9_\-]/i', "_", $reason) ?: "error";
        $target = $deadDir . "/" . $id . "." . $safeReason . "." . time() . ".json";
        return @rename($claimed, $target);
    
    }

    public static function maestro_deferred_state_write(array $stats): void
    
    {
        $stateDir = maestro_deferred_storage_dir() . "/state";
        if (!is_dir($stateDir)) {
            @mkdir($stateDir, 0750, true);
        }
        if (!is_dir($stateDir) || !is_writable($stateDir)) {
            return;
        }
        @file_put_contents(
            $stateDir . "/deferred-work.json",
            json_encode(
                [
                    "updated_at_utc" => gmdate("Y-m-d H:i:s"),
                    "status" => $stats["status"] ?? "unknown",
                    "stats" => $stats,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
            LOCK_EX,
        );
    
    }
}
