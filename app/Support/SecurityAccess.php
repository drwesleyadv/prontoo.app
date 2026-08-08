<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
function has_session_user(): bool
{
    return \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::has_session_user();
}
function boot_security(): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::boot_security();
}
function headers_secure(bool $public = false): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::headers_secure($public);
}
function posted_identity_document_error(
    array $data,
    string $prefix = "",
): ?string {
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::posted_identity_document_error($data, $prefix);
}
function enforce_posted_identity_documents(): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::enforce_posted_identity_documents();
}
function guard_request(): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::guard_request();
}
function security_rate_limit(
    string $bucket,
    int $limit,
    int $windowSeconds,
): bool {
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit($bucket, $limit, $windowSeconds);
}
function security_client_bucket(string $prefix): string
{
    return \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket($prefix);
}
function security_ip_bucket(string $prefix): string
{
    return \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_ip_bucket($prefix);
}
function security_value_bucket(string $prefix, string $value): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_value_bucket($prefix, $value);
}
function safe_val(string $sql, array $p = [], mixed $fallback = 0): mixed
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::safe_val($sql, $p, $fallback);
}
function csrf(): string
{
    return \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::csrf();
}
function csrf_field(): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field();
}
function check_csrf(): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::check_csrf();
}
function flash(?string $m = null, string $type = "ok"): ?array
{
    return \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($m, $type);
}
function password_common_rejected(string $s): bool
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_common_rejected($s);
}
function password_ok(string $s): bool
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_ok($s);
}
function password_hash_secure(string $password): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_hash_secure($password);
}

function mfa_meta_key(int $uid): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_meta_key($uid);
}

function mfa_crypto_key(): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_crypto_key();
}

function mfa_secret_encrypt(string $secret): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_secret_encrypt($secret);
}

function mfa_secret_decrypt(string $encrypted): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_secret_decrypt($encrypted);
}

function mfa_record_load(int $uid): ?array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_load($uid);
}

function mfa_record_save(int $uid, array $record): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_save($uid, $record);
}

function mfa_enrollment_state(int $uid): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state($uid);
}

function mfa_is_enrolled(int $uid): bool
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_is_enrolled($uid);
}

function mfa_base32_encode(string $bytes): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_base32_encode($bytes);
}

function mfa_base32_decode(string $value): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_base32_decode($value);
}

function mfa_totp_counter(?int $timestamp = null): int
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_counter($timestamp);
}

function mfa_totp_code(string $secret, int $counter): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_code($secret, $counter);
}

function mfa_totp_matching_counter(
    string $secret,
    string $code,
    int $lastCounter = -1,
): ?int {
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_matching_counter($secret, $code, $lastCounter);
}

function mfa_recovery_code_normalize(string $code): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_recovery_code_normalize($code);
}

function mfa_recovery_code_hash(string $code): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_recovery_code_hash($code);
}

function mfa_recovery_codes_generate(int $count = 10): array
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_recovery_codes_generate($count);
}

function mfa_enroll_user(
    int $uid,
    string $secret,
    string $firstCode,
): array {
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enroll_user($uid, $secret, $firstCode);
}

function mfa_record_verify_code(array &$record, string $code): bool
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_record_verify_code($record, $code);
}

function mfa_verify_user_code(int $uid, string $code): bool
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_verify_user_code($uid, $code);
}

function mfa_recovery_codes_regenerate(int $uid, string $currentCode): array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_recovery_codes_regenerate($uid, $currentCode);
}

function mfa_replace_user(
    int $uid,
    string $currentCode,
    string $newSecret,
    string $newCode,
): array {
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_replace_user($uid, $currentCode, $newSecret, $newCode);
}

function mfa_disable_user(int $uid, string $currentCode): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_disable_user($uid, $currentCode);
}

function mfa_totp_secret_generate(): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_secret_generate();
}

function mfa_otpauth_uri(string $account, string $secret): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_otpauth_uri($account, $secret);
}
function auth_generation_current(): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::auth_generation_current();
}

function user_auth_generation_key(int $uid): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::user_auth_generation_key($uid);
}

function user_auth_generation_current(int $uid): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_current($uid);
}

function user_auth_generation_ensure(int $uid): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_ensure($uid);
}

function user_auth_generation_rotate(int $uid): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($uid);
}
function session_harden_after_login(
    int $uid = 0,
    ?string $verifiedUserGeneration = null,
): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::session_harden_after_login($uid, $verifiedUserGeneration);
}
function security_session_generation_enforce(int $uid): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_session_generation_enforce($uid);
}
function device_cookie_name(): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_cookie_name();
}
function device_session_lifetime_seconds(): int
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_session_lifetime_seconds();
}
function device_session_cookie_ttl_seconds(): int
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_session_cookie_ttl_seconds();
}
function device_hash_is_valid(string $hash): bool
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_hash_is_valid($hash);
}
function device_secure_cookie(): bool
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_secure_cookie();
}
function device_cookie_set(string $value, int $expires): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_cookie_set($value, $expires);
}
function device_cookie_clear(): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_cookie_clear();
}

function security_clear_legacy_device_cookie(): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();
}

function security_retire_persistent_devices_for_user(int $uid): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_retire_persistent_devices_for_user($uid);
}
function device_token_hash(string $token): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_token_hash($token);
}
function device_fallback_hash(): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_fallback_hash();
}
function device_client_hash_from_post(): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_client_hash_from_post();
}
function device_login_payload_from_post(): array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_login_payload_from_post();
}
function device_login_fields(): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_login_fields();
}
function device_cookie_pack(int $uid, string $deviceHash, string $token): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_cookie_pack($uid, $deviceHash, $token);
}
function device_cookie_unpack(): ?array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_cookie_unpack();
}
function device_session_context_payload(
    string $scope,
    ?int $clinicRoleId = null,
): array {
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_session_context_payload($scope, $clinicRoleId);
}
function device_session_remember_after_login(
    int $uid,
    string $scope,
    ?int $clinicRoleId = null,
    ?array $payload = null,
): void {
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_session_remember_after_login($uid, $scope, $clinicRoleId, $payload);
}
function device_session_update_current_context(
    string $scope,
    ?int $clinicRoleId = null,
): void {
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_session_update_current_context($scope, $clinicRoleId);
}
function device_session_enforce_current(int $uid): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_session_enforce_current($uid);
}
function device_session_auto_login(): bool
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_session_auto_login();
}
function device_session_revoke_current(): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::device_session_revoke_current();
}
function secure_session_destroy(): void
{
    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::secure_session_destroy();
}

function security_global_scope_verified(int $uid): bool
{
    return \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_global_scope_verified($uid);
}
function session_clinic_scope_id(): int
{
    return \Prontoo\Runtime\Tenant\SessionTenantAccess::clinicId();
}
function session_clinic_role_code(): string
{
    return \Prontoo\Runtime\Tenant\SessionTenantAccess::roleCode();
}
function tenant_scoped_tables(): array
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::tenant_scoped_tables();
}
function tenant_table_is_scoped(string $table): bool
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::tenant_table_is_scoped($table);
}
function sql_fingerprint(string $sql): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::sql_fingerprint($sql);
}
function scope_violation_detail_decode(mixed $details): array
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::scope_violation_detail_decode($details);
}
function scope_violation_detail_summary(mixed $details): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::scope_violation_detail_summary($details);
}
function scope_violation_safe_reason(string $detail): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::scope_violation_safe_reason($detail);
}
function scope_violation_sql_shape(string $sql): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::scope_violation_sql_shape($sql);
}
function scope_violation_evidence_payload(string $sql, string $detail): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::scope_violation_evidence_payload($sql, $detail);
}
function record_scope_violation(
    string $key,
    string $sql,
    string $detail = "",
): void {
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::record_scope_violation($key, $sql, $detail);
}
function read_only_post_allowed(string $route): bool
{
    return \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::read_only_post_allowed($route);
}
function read_only_write_allowed(): bool
{
    return \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::read_only_write_allowed();
}
function read_only_allowed_write_tables_for_request(): array
{
    return \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::read_only_allowed_write_tables_for_request();
}
function read_only_write_allowed_for_sql(string $sql): bool
{
    return \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::read_only_write_allowed_for_sql($sql);
}
function with_read_only_guard_disabled(callable $fn): mixed
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_read_only_guard_disabled($fn);
}
function with_scope_guard_disabled(callable $fn): mixed
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_disabled($fn);
}
function scope_guard_expected_clinic_id(): int
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::scope_guard_expected_clinic_id();
}
function scope_guard_active_clinic_id(): int
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::scope_guard_active_clinic_id();
}
function with_scope_guard_clinic(int $clinicId, callable $fn): mixed
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_clinic($clinicId, $fn);
}
function scope_guard_context_selftest(): array
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::scope_guard_context_selftest();
}
function sql_table_hit(string $norm, string $table): bool
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::sql_table_hit($norm, $table);
}
function sql_write_scope_guard(string $sql, array $params = []): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::sql_write_scope_guard($sql, $params);
}
function require_same_clinic_entity(
    int $cid,
    string $table,
    int $id,
    string $cols = "id",
): array {
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::require_same_clinic_entity($cid, $table, $id, $cols);
}
function prontoo_icon_matrix(): array
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_matrix();
}
function prontoo_icon_for(string $key, string $fallback = "monitoring"): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for($key, $fallback);
}
function prontoo_icon_for_route_label(
    string $route,
    string $label = "",
    array $params = [],
    string $fallback = "monitoring",
): string {
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for_route_label($route, $label, $params, $fallback);
}
function actions(): array
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::actions();
}
function role_actions(string $role): array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::role_actions($role);
}
function role_rank(string $role): int
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::role_rank($role);
}
function primary_role_from_codes(array $roles): string
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::primary_role_from_codes($roles);
}
function has_effective_role(array $c, string $role): bool
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, $role);
}
function role_actions_effective(array $roles): array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::role_actions_effective($roles);
}
function effective_allowed_modules_for_roles(int $cid, array $roles): array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::effective_allowed_modules_for_roles($cid, $roles);
}
function default_permissions(): array
{
    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::default_permissions();
}
function seed_permissions(int $clinicId): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::seed_permissions($clinicId);
}
function secret_key(): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key();
}
function billing_state(array $clinic): array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::billing_state($clinic);
}
function billing_notice(array $c): string
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::billing_notice($c);
}
function enforce_read_only(array $c, string $route): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::enforce_read_only($c, $route);
}
function ctx(): array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
}
function need_login(): array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
}
function can(string $action): bool
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can($action);
}
function secure_relogin_after_forbidden_action(string $action): void
{
    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::secure_relogin_after_forbidden_action($action);
}
function require_can(string $action): array
{
    return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can($action);
}
