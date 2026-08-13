<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Database;

final class PdoQueryTelemetry
{
    private static int $count = 0;
    private static int $durationNs = 0;

    private function __construct()
    {
    }

    public static function reset(): void
    {
        self::$count = 0;
        self::$durationNs = 0;
    }

    public static function record(int $durationNs): void
    {
        self::$count++;
        self::$durationNs += max(0, $durationNs);
    }

    public static function snapshot(): array
    {
        return [
            "count" => self::$count,
            "duration_ns" => self::$durationNs,
            "average_ms" => self::$count > 0
                ? round((self::$durationNs / self::$count) / 1000000, 6, \RoundingMode::HalfAwayFromZero)
                : null,
        ];
    }
}
