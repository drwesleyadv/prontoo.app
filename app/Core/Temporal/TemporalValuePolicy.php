<?php
declare(strict_types=1);
namespace Prontoo\Core\Temporal;
final class TemporalValuePolicy
{
    private function __construct()
    {
    }

    public static function now(): int
    {

        return time();
    }
    public static function todayUtcNoon(): int
    {

        return (int) \gmmktime(
            12,
            0,
            0,
            (int) \gmdate("n"),
            (int) \gmdate("j"),
            (int) \gmdate("Y"),
        );
    }
    public static function toStorage(
        mixed $value,
        bool $dateOnly = false,
    ): mixed {

        if ($value === null || $value === "") {
            return $value;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        $s = mb_trim((string) $value);
        if ($s === "") {
            return $value;
        }
        if (preg_match('/^-?\d+$/', $s)) {
            return (int) $s;
        }
        if (TemporalQueryNormalizer::looksDateOnlyValue($s) || $dateOnly) {
            return self::dateOnlyToTimestamp(substr($s, 0, 10));
        }
        try {
            $tz = new \DateTimeZone(TemporalQueryNormalizer::contextTimezone());
            $normalized = str_replace("T", " ", $s);
            $dt = new \DateTimeImmutable($normalized, $tz);
            return $dt->setTimezone(new \DateTimeZone("UTC"))->getTimestamp();
        } catch (\Throwable $e) {
            return $value;
        }
    }
    public static function dateOnlyToTimestamp(string $ymd): int
    {

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return 0;
        }
        $year = (int) substr($ymd, 0, 4);
        $month = (int) substr($ymd, 5, 2);
        $day = (int) substr($ymd, 8, 2);
        if (!checkdate($month, $day, $year)) {
            return 0;
        }
        return (int) \gmmktime(12, 0, 0, $month, $day, $year);
    }
    public static function toLocalDateTime(
        mixed $value,
        ?string $tz = null,
    ): ?\DateTimeImmutable {

        if ($value === null || $value === "") {
            return null;
        }
        try {
            if (
                is_int($value) ||
                (is_string($value) && preg_match('/^-?\d+$/', trim($value)))
            ) {
                return new \DateTimeImmutable("@" . (int) $value)->setTimezone(
                    new \DateTimeZone(
                        TemporalQueryNormalizer::safeTimezone($tz ?? TemporalQueryNormalizer::contextTimezone()),
                    ),
                );
            }
            return new \DateTimeImmutable(
                (string) $value,
                new \DateTimeZone("UTC"),
            )->setTimezone(
                new \DateTimeZone(
                    TemporalQueryNormalizer::safeTimezone($tz ?? TemporalQueryNormalizer::contextTimezone()),
                ),
            );
        } catch (\Throwable $e) {
            return null;
        }
    }
    public static function fromLocalToUtcTimestamp(
        ?string $value,
        bool $dateOnly = false,
    ): string {

        $v = self::toStorage($value, $dateOnly);
        return $v === null || $v === "" ? "" : (string) $v;
    }
}
