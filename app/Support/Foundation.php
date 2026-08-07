<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
@date_default_timezone_set("UTC");
function app_root(): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_root();
}
function cfg_file(): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg_file();
}
function has_cfg(): bool
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg();
}
function now(): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::now();
}
function app_timezone_safe(string $tz): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_safe($tz);
}
function app_global_admin_timezone(int $userId = 0): string
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_global_admin_timezone($userId);
}
function app_force_utc_runtime(): void
{
    \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_force_utc_runtime();
}
function app_timezone_offset_string(string $tz, ?int $timestamp = null): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_offset_string($tz, $timestamp);
}
function app_timezone_offset_minutes(string $tz, ?int $timestamp = null): int
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_offset_minutes($tz, $timestamp);
}
function app_apply_request_timezone(string $tz): void
{
    \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_apply_request_timezone($tz);
}
function app_context_timezone(?array $context = null, int $clinicId = 0): string
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone($context, $clinicId);
}
function app_now_utc(): DateTimeImmutable
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_now_utc();
}
function app_now_in_timezone(
    int $clinicId = 0,
    ?array $context = null,
): DateTimeImmutable {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_now_in_timezone($clinicId, $context);
}
function app_today_in_timezone(
    int $clinicId = 0,
    ?array $context = null,
): string {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($clinicId, $context);
}
function app_month_in_timezone(
    int $clinicId = 0,
    ?array $context = null,
    ?DateTimeImmutable $nowUtc = null,
): string {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_month_in_timezone($clinicId, $context, $nowUtc);
}
function app_local_month_utc_range(
    string $month,
    int $clinicId = 0,
    ?array $context = null,
): array {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_month_utc_range($month, $clinicId, $context);
}
function app_parse_db_utc(null|string|int $value): ?DateTimeImmutable
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_parse_db_utc($value);
}
function app_db_utc_to_local(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): ?DateTimeImmutable {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($value, $clinicId, $context);
}
function app_local_to_db_utc(
    ?string $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_to_db_utc($value, $clinicId, $context);
}
function app_storage_timestamp(
    null|string|int $value,
    bool $endOfDay = false,
): int {
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($value, $endOfDay);
}
function app_date_input_from_storage(null|string|int $value): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($value);
}
function app_db_utc_to_local_input(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local_input($value, $clinicId, $context);
}
function app_local_day_utc_range(
    string $day,
    int $clinicId = 0,
    ?array $context = null,
): array {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($day, $clinicId, $context);
}
function app_date_only_end_timestamp(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): int {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_date_only_end_timestamp($value, $clinicId, $context);
}
function app_time_br(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($value, $clinicId, $context);
}
function app_date_br(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_date_br($value, $clinicId, $context);
}
function app_datetime_br(
    null|string|int $value,
    int $clinicId = 0,
    ?array $context = null,
): string {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_datetime_br($value, $clinicId, $context);
}
function patient_display_name(int $patientLinkId, ?int $cid = null): string
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::patient_display_name($patientLinkId, $cid);
}
function maintenance_active(): bool
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::maintenance_active();
}
function page_maintenance_notice(): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::page_maintenance_notice();
}
function only_digits(string $s): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($s);
}
function cpf_br(?string $cpf): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::cpf_br($cpf);
}
function app_config_string(string $key, string $default = ""): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_config_string($key, $default);
}
function app_is_production(): bool
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_is_production();
}

function app_canonical_host(): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_canonical_host();
}
function request_host_raw(): string
{
    return \Prontoo\Presentation\Legacy\SupportFoundation\SupportFoundationPresentationOperations01::request_host_raw();
}
function app_enforce_canonical_host(): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_enforce_canonical_host();
}

function is_https(): bool
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::is_https();
}
function request_host(): string
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::request_host();
}
function base_path(): string
{
    return \Prontoo\Presentation\Legacy\SupportFoundation\SupportFoundationPresentationOperations01::base_path();
}
function base_url(string $suffix = ""): string
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::base_url($suffix);
}
function href(string $route, array $params = []): string
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::href($route, $params);
}
function redirect(string $route, array $params = []): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::redirect($route, $params);
}
function route(): string
{
    return \Prontoo\Presentation\Legacy\SupportFoundation\SupportFoundationPresentationOperations01::route();
}
function app_debug(): bool
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_debug();
}
function app_public_error_message(
    Throwable $error,
    string $fallback = "Não foi possível concluir esta ação.",
): string {
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message($error, $fallback);
}

function app_fail(Throwable $e, int $status = 500): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::app_fail($e, $status);
}
function runtime_self_check(): void
{
    \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::runtime_self_check();
}
function cfg(): array
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg();
}
function storage_path(string $path = ""): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path($path);
}
function cache_path(string $key): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::cache_path($key);
}
function cache_get(string $key, int $ttl): mixed
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::cache_get($key, $ttl);
}
function cache_set(string $key, mixed $value): mixed
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::cache_set($key, $value);
}
function cache_remember(string $key, int $ttl, callable $fn): mixed
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::cache_remember($key, $ttl, $fn);
}
function cached_val(string $key, int $ttl, string $sql, array $p = []): mixed
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val($key, $ttl, $sql, $p);
}
function bounded_limit(int $n, int $max = PRONTOO_HOT_LIST_LIMIT): int
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::bounded_limit($n, $max);
}
function counter_key(string $name): string
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::counter_key($name);
}
function counter_inc(string $name, int $by = 1): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc($name, $by);
}
function counter_get(string $name, int $fallback = 0): int
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations01::counter_get($name, $fallback);
}
function prontoo_login_selftest_light(): array
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::prontoo_login_selftest_light();
}
function page_login_autotest(): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::page_login_autotest();
}
function person_signature_value(
    string $identity,
    string $name,
    ?string $date,
): string {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_value($identity, $name, $date);
}
function person_signature_sync(int $personId, bool $verify = false): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_sync($personId, $verify);
}
function person_signature_refresh(int $personId): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_refresh($personId);
}
function person_signature_refresh_verified(int $personId): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_refresh_verified($personId);
}
function person_identity_immutable_values(
    int $personId,
    ?string $cpfInput = null,
    ?string $birthInput = null,
    bool $requireMissing = false,
): array {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::person_identity_immutable_values($personId, $cpfInput, $birthInput, $requireMissing);
}
function count_recent_or_counter(
    string $counter,
    string $sql,
    array $p = [],
    int $ttl = PRONTOO_DASHBOARD_COUNTER_TTL,
): int {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::count_recent_or_counter($counter, $sql, $p, $ttl);
}
function meta_cache_ttl(string $key): int
{
    return \Prontoo\Infrastructure\Legacy\SupportFoundation\SupportFoundationInfrastructureOperations01::meta_cache_ttl($key);
}
function meta_get(string $key, mixed $default = null): mixed
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get($key, $default);
}
function meta_set(string $key, mixed $value): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set($key, $value);
}
function clinic_signup_blocked(): bool
{
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::clinic_signup_blocked();
}
function log_runtime_error(Throwable $e, int $status = 500): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::log_runtime_error($e, $status);
}
function person_common_profile_schema_ready(): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_schema_ready();
}
function person_common_profile_from_array(
    array $data,
    string $prefix = "",
): array {
    return \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_from_array($data, $prefix);
}
function person_common_profile_update(int $personId, array $profile): void
{
    \Prontoo\Runtime\Legacy\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_update($personId, $profile);
}
function person_common_profile_fields_html(
    int $cid,
    array $p = [],
    string $prefix = "",
    bool $includeEmail = true,
    bool $includePhone = true,
): string {
    return \Prontoo\Presentation\Legacy\SupportFoundation\SupportFoundationPresentationOperations01::person_common_profile_fields_html($cid, $p, $prefix, $includeEmail, $includePhone);
}
