<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function telemetry_storage_dir(): string
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_storage_dir();
}

function telemetry_file(): string
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_file();
}

function telemetry_schema(): string
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_schema();
}

function telemetry_page_internal_routes(): array
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_page_internal_routes();
}

function telemetry_page_request_candidate(string $route): bool
{
    return \Prontoo\Runtime\Legacy\SupportTelemetry\SupportTelemetryRuntimeOperations01::telemetry_page_request_candidate($route);
}

function telemetry_page_response_candidate(int $statusCode): bool
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_page_response_candidate($statusCode);
}

function telemetry_retention_microseconds(): int
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_retention_microseconds();
}

function telemetry_comparison_microseconds(): int
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_comparison_microseconds();
}

function telemetry_cuiaba_tz(): DateTimeZone
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz();
}

function telemetry_day_axis_label(DateTimeImmutable $day): string
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_day_axis_label($day);
}

function telemetry_route_safe(string $route): string
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_route_safe($route);
}

function telemetry_prepare_storage(): bool
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_prepare_storage();
}

function telemetry_utc_from_unix_microseconds(int $unixMicroseconds): string
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_utc_from_unix_microseconds($unixMicroseconds);
}

function telemetry_route_start_marker(
    string $route,
    ?int $startedMonotonicNs = null,
    ?int $startedUnixUs = null,
): void {
    \Prontoo\Runtime\Legacy\SupportTelemetry\SupportTelemetryRuntimeOperations01::telemetry_route_start_marker($route, $startedMonotonicNs, $startedUnixUs);
}

function telemetry_route_identify(string $route): void
{
    \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_route_identify($route);
}

function telemetry_fatal_error(?array $error): ?string
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_fatal_error($error);
}

function telemetry_build_event(
    string $route,
    int $startedMonotonicNs,
    int $finishedMonotonicNs,
    int $startedUnixUs,
    int $finishedUnixUs,
    int $statusCode,
    ?string $fatalError = null,
    ?string $release = null,
    ?string $method = null,
    ?string $path = null,
    string $finishMarker = "front_controller_last_useful_line",
): array {
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_build_event($route, $startedMonotonicNs, $finishedMonotonicNs, $startedUnixUs, $finishedUnixUs, $statusCode, $fatalError, $release, $method, $path, $finishMarker);
}

function telemetry_normalize_event(array $event): ?array
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_normalize_event($event);
}

function telemetry_json_line(array $event): ?string
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_json_line($event);
}

function telemetry_append_event(array $event): bool
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_append_event($event);
}

function telemetry_read_events(?int $nowUnixUs = null): array
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_read_events($nowUnixUs);
}

function telemetry_prune(?int $nowUnixUs = null): int
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_prune($nowUnixUs);
}

function telemetry_route_finish_marker(bool $shutdownFallback = false): void
{
    \Prontoo\Runtime\Legacy\SupportTelemetry\SupportTelemetryRuntimeOperations01::telemetry_route_finish_marker($shutdownFallback);
}

function telemetry_percentage_variation(int|float $current, int|float $previous): ?float
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_percentage_variation($current, $previous);
}

function telemetry_nullable_percentage_variation(
    ?float $current,
    ?float $previous,
): ?float {
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_nullable_percentage_variation($current, $previous);
}

function telemetry_empty_period(): array
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_empty_period();
}

function telemetry_finalize_period(array $period): array
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_finalize_period($period);
}

function telemetry_comparative_summary(?int $nowUnixUs = null): array
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_comparative_summary($nowUnixUs);
}

function telemetry_route_performance_summary(int $hours = 240): array
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_route_performance_summary($hours);
}

function telemetry_route_requests_series_20d(?int $nowUnixUs = null): array
{
    return \Prontoo\Infrastructure\Legacy\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_route_requests_series_20d($nowUnixUs);
}

function telemetry_sequence_records_series_20d(?int $nowUnix = null): array
{
    return \Prontoo\Runtime\Legacy\SupportTelemetry\SupportTelemetryRuntimeOperations01::telemetry_sequence_records_series_20d($nowUnix);
}