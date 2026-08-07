<?php
declare(strict_types=1);
namespace Prontoo\Core\Temporal;
final class PiTime
{
    public const POLICY_VERSION = "pi-time-unix-utc-v2.3";
    private function __construct()
    {
    }

    public static function now(): int
    {
        return TemporalValuePolicy::now();
    }

    public static function todayUtcNoon(): int
    {
        return TemporalValuePolicy::todayUtcNoon();
    }

    public static function isTemporalColumn(string $column): bool
    {
        return TemporalSchemaPolicy::isTemporalColumn($column);
    }

    public static function isDateOnlyColumn(string $column): bool
    {
        return TemporalSchemaPolicy::isDateOnlyColumn($column);
    }

    public static function storageDefinitionFor(
        string $column,
        string $definition,
    ): string
    {
        return TemporalSchemaPolicy::storageDefinitionFor($column, $definition);
    }

    public static function rewriteColumnDefinition(
        string $column,
        string $definition,
    ): string
    {
        return TemporalSchemaPolicy::rewriteColumnDefinition($column, $definition);
    }

    public static function rewriteSchemaSql(string $sql): string
    {
        return TemporalSchemaPolicy::rewriteSchemaSql($sql);
    }

    public static function prepareRuntimeQuery(
        string $sql,
        array $params,
    ): array
    {
        return TemporalQueryNormalizer::prepareRuntimeQuery($sql, $params);
    }

    public static function rewriteTemporalFunctions(string $sql): string
    {
        return TemporalQueryNormalizer::rewriteTemporalFunctions($sql);
    }

    public static function toStorage(
        mixed $value,
        bool $dateOnly = false,
    ): mixed
    {
        return TemporalValuePolicy::toStorage($value, $dateOnly);
    }

    public static function dateOnlyToTimestamp(string $ymd): int
    {
        return TemporalValuePolicy::dateOnlyToTimestamp($ymd);
    }

    public static function toLocalDateTime(
        mixed $value,
        ?string $tz = null,
    ): ?\DateTimeImmutable
    {
        return TemporalValuePolicy::toLocalDateTime($value, $tz);
    }

    public static function fromLocalToUtcTimestamp(
        ?string $value,
        bool $dateOnly = false,
    ): string
    {
        return TemporalValuePolicy::fromLocalToUtcTimestamp($value, $dateOnly);
    }

}
