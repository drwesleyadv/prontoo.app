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
        $dir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path();
        $ok = is_dir($dir) && is_writable($dir);
        $free = function_exists("disk_free_space") ? @disk_free_space($dir) : false;
        return [
            "ok" => $ok,
            "free_bytes" => $free === false ? null : (float) $free,
        ];
    }

    public static function platform_health_exchange(callable $builder): array
    {
        $previous = \Prontoo\Infrastructure\Health\HealthEvidenceStore::readLatest();
        $context = [
            'storage' => self::platform_storage_status(),
            'performance' => self::platform_health_performance_context(),
            'maestro' => self::platform_health_maestro_context(),
            'previous' => $previous,
        ];
        $snapshot = $builder($context);
        if (!is_array($snapshot)) {
            throw new RuntimeException('Snapshot de saúde inválido.');
        }
        \Prontoo\Infrastructure\Health\HealthEvidenceStore::writeLatest($snapshot, $previous);
        return $snapshot;
    }

    private static function platform_health_performance_context(): array
    {
        try {
            $events = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_read_events();
        } catch (Throwable $error) {
            return ['available' => false, 'reason' => 'telemetry_unavailable'];
        }
        $nowUs = (int) floor(microtime(true) * 1000000);
        $windowUs = 15 * 60 * 1000000;
        $current = self::platform_health_event_window($events, $nowUs - $windowUs, $nowUs);
        $previous = self::platform_health_event_window($events, $nowUs - (2 * $windowUs), $nowUs - $windowUs);
        return [
            'available' => true,
            'observed_at' => gmdate('c'),
            'current' => $current,
            'previous' => $previous,
        ];
    }

    private static function platform_health_event_window(array $events, int $fromUs, int $toUs): array
    {
        $requests = 0;
        $failures = 0;
        $durationNs = 0;
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $finishedUs = (int) ($event['fim_unix_us'] ?? 0);
            if ($finishedUs < $fromUs || $finishedUs >= $toUs) {
                continue;
            }
            $requests++;
            $status = (int) ($event['status_http'] ?? 0);
            if ($status >= 500 || empty($event['sucesso'])) {
                $failures++;
            }
            $durationNs += max(0, (int) ($event['duracao_ns'] ?? 0));
        }
        return [
            'requests' => $requests,
            'failures' => $failures,
            'failure_rate' => $requests > 0 ? $failures / $requests : null,
            'average_ms' => $requests > 0 ? ($durationNs / $requests) / 1000000 : null,
        ];
    }

    private static function platform_health_maestro_context(): array
    {
        $path = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path('maestro/state/latest.json');
        if (!is_file($path)) {
            return ['available' => false, 'reason' => 'heartbeat_missing'];
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded)
                ? ['available' => true, 'state' => $decoded]
                : ['available' => false, 'reason' => 'heartbeat_invalid'];
        } catch (Throwable $error) {
            return ['available' => false, 'reason' => 'heartbeat_unreadable'];
        }
    }

    public static function admin_alerts_ensure_schema(): void
    {
        static $validated = false;
        if ($validated || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        if (!\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_table_exists("pi_admin_alerts")) {
            throw new RuntimeException(
                "Schema incompleto: alertas administrativos indisponíveis.",
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
