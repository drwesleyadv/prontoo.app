<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
require_once __DIR__ . '/../Domain/Identity/IdentityDocumentValidator.php';

if (!function_exists("admin_choice_card")) {
    function admin_choice_card(): string
    {
        return \Prontoo\Presentation\AuthOnboarding\AdminChoiceCardOperation::render();
    }
}
function onboarding_tips_ensure_schema(): void
{
    \Prontoo\Infrastructure\AuthOnboarding\AuthOnboardingInfrastructureOperations01::onboarding_tips_ensure_schema();
}

function onboarding_tip_module_routes(): array
{
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::onboarding_tip_module_routes();
}
function onboarding_tip_key(array $c, string $route): string
{
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::onboarding_tip_key($c, $route);
}
function onboarding_tip_dismissed(array $c, string $route): bool
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::onboarding_tip_dismissed($c, $route);
}
function onboarding_tip_dismiss(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::onboarding_tip_dismiss();
}
function onboarding_tip_copy(array $c, string $route): ?array
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::onboarding_tip_copy($c, $route);
}
function onboarding_tip_html(array $c, string $route): string
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::onboarding_tip_html($c, $route);
}
function valid_cpf(string $cpf): bool
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf);
}
function valid_cnpj(string $cnpj): bool
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cnpj($cnpj);
}
function db_birth_date_input(null|string|int $birth): string
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::db_birth_date_input($birth);
}
function valid_birth_date(null|string|int $birth): bool
{
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($birth);
}
function login_last_credential_key(int $uid): string
{
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_last_credential_key($uid);
}
function login_last_credential_normalize(mixed $raw): ?array
{
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_last_credential_normalize($raw);
}
function login_last_credential_remember(
    int $uid,
    string $scope,
    ?int $clinicRoleId = null,
): void {
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_last_credential_remember($uid, $scope, $clinicRoleId);
}
function login_last_credential_from_meta(int $uid): ?array
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_last_credential_from_meta($uid);
}
function login_last_credential_from_devices(int $uid): ?array
{
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_last_credential_from_devices($uid);
}
function login_credential_match(
    int $uid,
    bool $isAdmin,
    array $choices,
    ?array $credential,
): ?array {
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_credential_match($uid, $isAdmin, $choices, $credential);
}
function login_resolve_user_credential(
    int $uid,
    bool $isAdmin,
    array $choices,
): ?array {
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_resolve_user_credential($uid, $isAdmin, $choices);
}
function login_apply_resolved_credential(
    int $uid,
    array $credential,
    ?string $verifiedUserGeneration = null,
    bool $redirectAfterLogin = true,
): string {
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_apply_resolved_credential($uid, $credential, $verifiedUserGeneration, $redirectAfterLogin);
}
function developer_first_login_clear_json_cache(
    int $uid,
    bool $knownDeveloper = false,
): bool
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::developer_first_login_clear_json_cache($uid, $knownDeveloper);
}

function mfa_pending_login_clear(): void
{
    \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::mfa_pending_login_clear();
}

function mfa_begin_pending_login(int $uid, array $credential): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::mfa_begin_pending_login($uid, $credential);
}

function mfa_pending_login_user(): ?array
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::mfa_pending_login_user();
}

function mfa_complete_pending_login(bool $redirectAfterLogin = true): string
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_complete_pending_login($redirectAfterLogin);
}

function mfa_attempt_limited(int $uid, string $purpose): bool
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_attempt_limited($uid, $purpose);
}

function page_mfa(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::page_mfa();
}

function page_global_reauth(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::page_global_reauth();
}
function page_login(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::page_login();
}
function login_key(string $cpf): array
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::login_key($cpf);
}

function login_bucket_keys(string $cpf): array
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::login_bucket_keys($cpf);
}

function login_locks_cleanup_maybe(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::login_locks_cleanup_maybe();
}
function login_lock(string $cpf): int
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::login_lock($cpf);
}
function login_fail(string $cpf): int
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::login_fail($cpf);
}
function login_clear(string $cpf): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::login_clear($cpf);
}
function mark_login_success(int $uid): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::mark_login_success($uid);
}
function login_session_remember(
    string $cpf,
    int $wait,
    string $message = "CPF ou senha não conferem.",
): void {
    \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_remember($cpf, $wait, $message);
}
function login_session_wait(): int
{
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_wait();
}
function login_session_forget(): void
{
    \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_forget();
}
function seconds_label(int $s): string
{
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::seconds_label($s);
}
function page_signup(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::page_signup();
}
function upsert_person(string $name, string $cpf, string $birth): int
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::upsert_person($name, $cpf, $birth);
}
function lock_person_user_identity(int $personId): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::lock_person_user_identity($personId);
}
function save_person_flexible(
    string $name,
    ?string $cpf = null,
    ?string $birth = null,
): int {
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::save_person_flexible($name, $cpf, $birth);
}
function phone_br(?string $phone): string
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($phone);
}
function page_person_lookup(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::page_person_lookup();
}
function page_logout(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::page_logout();
}
function page_profile(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations06::page_profile();
}
function page_switch(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::page_switch();
}
function page_onboarding(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::page_onboarding();
}
function person_autosuggest_datalist(
    int $cid,
    string $id = "prontoo_person_suggestions",
): string {
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::person_autosuggest_datalist($cid, $id);
}
function save_person_by_document(
    string $name,
    string $doc,
    ?string $birth = null,
): int {
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::save_person_by_document($name, $doc, $birth);
}

function login_telemetry_wave_values(array $series): array
{
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_telemetry_wave_values($series);
}

function login_telemetry_wave_path(
    array $values,
    float $maximum,
    int $width = 1000,
    int $height = 250,
): string {
    return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_telemetry_wave_path($values, $maximum, $width, $height);
}

function login_telemetry_wave_data(): array
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::login_telemetry_wave_data();
}

function login_telemetry_wave_html(): string
{
    return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::login_telemetry_wave_html();
}

function page_login_telemetry_wave(): void
{
    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::page_login_telemetry_wave();
}
