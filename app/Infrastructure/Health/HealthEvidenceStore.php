<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Health;

use Throwable;

final class HealthEvidenceStore
{
    private const MAX_TRANSITION_BYTES = 524288;
    private const KEEP_TRANSITIONS = 240;

    private function __construct()
    {
    }

    public static function readLatest(): array
    {
        $path = self::latestPath();
        if (!is_file($path)) {
            return [];
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $error) {
            error_log('[Prontoo health evidence read] ' . $error->getMessage());
            return [];
        }
    }

    public static function writeLatest(array $snapshot, array $previous = []): bool
    {
        $dir = self::directory();
        if (!self::prepareDirectory($dir)) {
            return false;
        }
        try {
            $encoded = json_encode(
                $snapshot,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            );
            $path = self::latestPath();
            $tmp = $path . '.tmp.' . getmypid();
            if (file_put_contents($tmp, $encoded, LOCK_EX) === false) {
                @unlink($tmp);
                return false;
            }
            @chmod($tmp, 0640);
            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                return false;
            }
            self::appendTransitions($previous, $snapshot);
            return true;
        } catch (Throwable $error) {
            error_log('[Prontoo health evidence write] ' . $error->getMessage());
            return false;
        }
    }

    private static function directory(): string
    {
        return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path('health');
    }

    private static function latestPath(): string
    {
        return self::directory() . '/evidence-latest.json';
    }

    private static function transitionsPath(): string
    {
        return self::directory() . '/transitions.jsonl';
    }

    private static function prepareDirectory(string $dir): bool
    {
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return false;
        }
        @chmod($dir, 0750);
        if (is_callable([\Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::class, 'security_storage_deny_file'])) {
            \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);
        }
        return is_writable($dir);
    }

    private static function appendTransitions(array $previous, array $current): void
    {
        $events = self::transitionEvents($previous, $current);
        if ($events === []) {
            return;
        }
        $path = self::transitionsPath();
        foreach ($events as $event) {
            $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($line)) {
                @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        }
        @chmod($path, 0640);
        self::pruneTransitions($path);
    }

    private static function transitionEvents(array $previous, array $current): array
    {
        if ($previous === []) {
            return [];
        }
        $at = (string) ($current['observed_at'] ?? gmdate('c'));
        $events = [];
        $pairs = [
            ['global', 'platform', (string) ($previous['state'] ?? ''), (string) ($current['state'] ?? '')],
        ];
        foreach ((array) ($current['dimensions'] ?? []) as $id => $dimension) {
            $pairs[] = [
                'dimension',
                (string) $id,
                (string) (($previous['dimensions'][$id]['state'] ?? '')),
                (string) (($dimension['state'] ?? '')),
            ];
        }
        foreach ((array) ($current['signals'] ?? []) as $id => $signal) {
            $pairs[] = [
                'signal',
                (string) $id,
                (string) (($previous['signals'][$id]['state'] ?? '')),
                (string) (($signal['state'] ?? '')),
            ];
        }
        foreach ($pairs as [$type, $id, $from, $to]) {
            if ($from === '' || $to === '' || $from === $to) {
                continue;
            }
            $events[] = [
                'schema' => 'prontoo.health-transition.v1',
                'observed_at' => $at,
                'type' => $type,
                'id' => $id,
                'from' => $from,
                'to' => $to,
            ];
        }
        return $events;
    }

    private static function pruneTransitions(string $path): void
    {
        $size = @filesize($path);
        if (!is_int($size) || $size <= self::MAX_TRANSITION_BYTES) {
            return;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }
        $lines = array_slice($lines, -self::KEEP_TRANSITIONS);
        @file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    }
}
