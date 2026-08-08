<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
function default_monthly_price_cents(): int
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents();
}
function default_trial_days(): int
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days();
}
function trial_period_label(int $days): string
{
    return \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::trial_period_label($days);
}
function subscription_pix_key(): string
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_pix_key();
}
function normalize_subscription_pix_key(string $key): string
{
    return \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::normalize_subscription_pix_key($key);
}
function subscription_time_ts(
    null|string|int $value,
    bool $endOfDay = false,
): int {
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_time_ts($value, $endOfDay);
}
function subscription_trial_end_from_start(
    null|string|int $start = null,
    ?int $days = null,
): int {
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_trial_end_from_start($start, $days);
}
function subscription_trial_is_active(null|string|int $trialEnd): bool
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_trial_is_active($trialEnd);
}
function subscription_paid_is_active(
    null|string|int $paidUntil,
    int $clinicId = 0,
    ?array $context = null,
): bool {
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_paid_is_active($paidUntil, $clinicId, $context);
}
function ensure_clinic_trial_active(
    int $clinicId,
    bool $onlyIfOnboardingPending = true,
): void {
    \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::ensure_clinic_trial_active($clinicId, $onlyIfOnboardingPending);
}
function clinic_subscription_kind(array $cl): string
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::clinic_subscription_kind($cl);
}
function clinic_subscription_action_label(string $kind): string
{
    return \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::clinic_subscription_action_label($kind);
}
function clinic_subscription_status_card(array $cl): string
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::clinic_subscription_status_card($cl);
}
function subscription_payment_proof_guard(int $cid, int $uid): void
{
    \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_payment_proof_guard($cid, $uid);
}
function subscription_payment_proof_validate_image(
    string $tmp,
    string $mime,
): array {
    return \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_payment_proof_validate_image($tmp, $mime);
}
function subscription_payment_proof_reencode_image(
    string $tmp,
    string $dest,
    string $mime,
): bool {
    return \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_payment_proof_reencode_image($tmp, $dest, $mime);
}
function subscription_payment_proof_validate_pdf(string $tmp): void
{
    \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_proof_validate_pdf($tmp);
}
function subscription_payment_proof_storage(int $cid, bool $image = false): array
{
    return \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_proof_storage($cid, $image);
}

function subscription_payment_proof_upload(int $cid, int $uid): ?string
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_payment_proof_upload($cid, $uid);
}
function subscription_payment_proof_absolute_path(?string $proofPath): ?string
{
    return \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_proof_absolute_path($proofPath);
}
function subscription_payment_delete_proof(?string $proofPath): bool
{
    return \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_delete_proof($proofPath);
}
function subscription_payment_proof_view_link(array $payment): string
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_payment_proof_view_link($payment);
}
function page_admin_payment_proof(): void
{
    \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::page_admin_payment_proof();
}
function clinic_subscription_pending_payment(int $cid): ?array
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::clinic_subscription_pending_payment($cid);
}
function subscription_payment_is_proof_review(array $payment): bool
{
    return \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_payment_is_proof_review($payment);
}
function subscription_trust_release_until(): string
{
    return \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_trust_release_until();
}
function subscription_renewal_until(array $cl): string
{
    return \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_renewal_until($cl);
}
function later_date(?string $a, ?string $b): string
{
    return \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::later_date($a, $b);
}
function clinic_subscription_cta(array $cl, string $tab = "assinatura"): string
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations02::clinic_subscription_cta($cl, $tab);
}
function clinic_subscription_rejected_notice(
    int $cid,
    bool $proofRejected = false,
): void {
    \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations02::clinic_subscription_rejected_notice($cid, $proofRejected);
}
function clinic_subscription_register_claim(
    int $cid,
    int $uid,
    array $cl,
): string {
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations02::clinic_subscription_register_claim($cid, $uid, $cl);
}
function clinic_settings_nav(string $tab): string
{
    return \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations02::clinic_settings_nav($tab);
}
function page_settings(): void
{
    \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations03::page_settings();
}
