<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
function clinical_profession_options(): array
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinical_profession_options();
}
function normalize_profession(string $profession): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_profession($profession);
}
function clinic_icon_options(): array
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_icon_options();
}
function patient_health_icon_options(): array
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::patient_health_icon_options();
}
function clinic_accent_options(): array
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_accent_options();
}
function normalize_clinic_icon(?string $icon): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_clinic_icon($icon);
}
function normalize_accent_color(?string $color): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_accent_color($color);
}
function profession_default_icon(string $profession): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::profession_default_icon($profession);
}
function profession_default_color(string $profession): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::profession_default_color($profession);
}
function clinic_favicon_symbol_svg(string $icon, string $fill): string
{
    return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_favicon_symbol_svg($icon, $fill);
}
function clinic_favicon_href(string $icon, string $color): string
{
    return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_favicon_href($icon, $color);
}
function clinic_hex_rgb(string $hex): array
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_hex_rgb($hex);
}
function clinic_rgb_hex(array $rgb): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_rgb_hex($rgb);
}
function clinic_mix_hex(string $a, string $b, float $aWeight): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($a, $b, $aWeight);
}
function clinic_luminance(string $hex): float
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::clinic_luminance($hex);
}
function clinic_contrast_ratio(string $a, string $b): float
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::clinic_contrast_ratio($a, $b);
}
function clinic_contrast_text(string $bg): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::clinic_contrast_text($bg);
}
function clinic_theme_tokens(string $color, array $palette = []): array
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::clinic_theme_tokens($color, $palette);
}
function clinic_visual_from_values(
    ?string $icon = null,
    ?string $color = null,
    ?string $profession = null,
): array {
    return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_visual_from_values($icon, $color, $profession);
}
function clinic_visual(?int $clinicId = null, ?array $ctx = null): array
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_visual($clinicId, $ctx);
}
function clinic_icon_picker(string $current): string
{
    return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_icon_picker($current);
}
function clinic_color_picker(string $current): string
{
    return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_color_picker($current);
}
function role_icon_sets(): array
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::role_icon_sets();
}
function role_icon_options(?string $role = null): array
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::role_icon_options($role);
}
function role_icon_picker(
    string $field,
    string $current,
    string $role = "",
): string {
    return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::role_icon_picker($field, $current, $role);
}
function default_role_icon(string $role): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::default_role_icon($role);
}
function clinic_role_label_fields(int $cid): string
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_label_fields($cid);
}
function clinic_role_sector_fields(int $cid): string
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_sector_fields($cid);
}
function profession_select_fields(array $cl = []): string
{
    return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::profession_select_fields($cl);
}
function clinic_profession(int $clinicId): string
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_profession($clinicId);
}
function admin_model_clinic_meta_key(): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_meta_key();
}
function admin_model_clinic_id(): int
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_id();
}
function clinic_is_global_admin_owned(int $clinicId): bool
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_is_global_admin_owned($clinicId);
}
function ensure_global_admin_clinic_exempt(int $clinicId): void
{
    \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::ensure_global_admin_clinic_exempt($clinicId);
}
function admin_model_clinic_set(int $clinicId): void
{
    \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_set($clinicId);
}
function admin_model_clinic_exclude_sql(string $column = "clinic_id"): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_exclude_sql($column);
}
function admin_model_clinic_exclude_where(string $column = "clinic_id"): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_exclude_where($column);
}
function admin_model_clinic_count_note(): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::admin_model_clinic_count_note();
}
function open_incidents_count(): int
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::open_incidents_count();
}
function clinic_metric_inc(?int $clinicId, string $metric, int $by = 1): void
{
    \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($clinicId, $metric, $by);
}
function clinic_read_only_db(int $cid): bool
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_read_only_db($cid);
}
function clinic_context_required(?array $ctx = null): array
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_context_required($ctx);
}
function clinic_id_required(?array $ctx = null): int
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_id_required($ctx);
}
function seed_clinic_roles(int $clinicId): void
{
    \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::seed_clinic_roles($clinicId);
}

function clinic_roles(int $clinicId, bool $enabledOnly = false): array
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_roles($clinicId, $enabledOnly);
}
function clinic_role_icons(int $clinicId, bool $enabledOnly = false): array
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_icons($clinicId, $enabledOnly);
}
function clinic_role_options(int $clinicId, bool $enabledOnly = true): array
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_options($clinicId, $enabledOnly);
}
function role_label_for(string $role, ?int $clinicId = null): string
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($role, $clinicId);
}
function role_icon_for(string $role, ?int $clinicId = null): string
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_icon_for($role, $clinicId);
}
function is_responsible_doctor(array $c): bool
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::is_responsible_doctor($c);
}
function onboarding_pending(?array $c = null): bool
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::onboarding_pending($c);
}
function br_states(): array
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::br_states();
}
function valid_timezone(string $tz): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::valid_timezone($tz);
}
function timezone_from_location(string $uf, string $city = ""): string
{
    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::timezone_from_location($uf, $city);
}
function clinic_location_fields(array $cl = []): string
{
    return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_location_fields($cl);
}
function role_icon(string $r, ?int $clinicId = null): string
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_icon($r, $clinicId);
}
function clinic_choice_card(array $r): string
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations02::clinic_choice_card($r);
}
function clinic_options_global(): array
{
    return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations02::clinic_options_global();
}
function clinic_usage_icon(
    string $iconName,
    int $value,
    string $label,
    string $short,
): string {
    return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_usage_icon($iconName, $value, $label, $short);
}
