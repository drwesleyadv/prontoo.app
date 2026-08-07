<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Legacy\ClinicConfig;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class ClinicConfigPresentationOperations01
{
    private function __construct()
    {
    }

    public static function clinic_favicon_symbol_svg(string $icon, string $fill): string
    
    {
    
        $fill = e($fill);
        return match ($icon) {
            "stethoscope"
                => '<path d="M17.5 2a1.5 1.5 0 0 1 1.5 1.5v4.9a5.5 5.5 0 0 1-11 0V3.5A1.5 1.5 0 0 1 9.5 2H11v2H10v4.4a3.5 3.5 0 0 0 7 0V4h-1V2h1.5Z" fill="' .
                $fill .
                '"/><path d="M12 15.8a5 5 0 0 0 10 0V14a2.6 2.6 0 1 1 2 0v1.8a7 7 0 0 1-14 0v-2.1h2v2.1Zm11-5.1a.9.9 0 1 0 0 1.8.9.9 0 0 0 0-1.8Z" fill="' .
                $fill .
                '"/>',
            "medical_services"
                => '<path d="M8 4h16a3 3 0 0 1 3 3v17a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Zm10 7h-4v3h-3v4h3v3h4v-3h3v-4h-3v-3Z" fill="' .
                $fill .
                '"/><path d="M12 2h8v4h-2V4h-4v2h-2V2Z" fill="' .
                $fill .
                '"/>',
            "local_hospital"
                => '<path d="M6 5h20v22H6V5Zm12 5h-4v4h-4v4h4v4h4v-4h4v-4h-4v-4Z" fill="' .
                $fill .
                '"/>',
            "health_and_safety"
                => '<path d="M16 3 6 7v7c0 6.1 4.3 11.8 10 13 5.7-1.2 10-6.9 10-13V7L16 3Zm2 16h-4v-3h-3v-4h3V9h4v3h3v4h-3v3Z" fill="' .
                $fill .
                '"/>',
            "clinical_notes"
                => '<path d="M9 3h14a3 3 0 0 1 3 3v22H9a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3Zm2 7h10V8H11v2Zm0 5h10v-2H11v2Zm0 5h7v-2h-7v2Z" fill="' .
                $fill .
                '"/>',
            "dentistry"
                => '<path d="M11.5 3c1.3 0 2.4.5 3.2 1.1.8-.6 1.9-1.1 3.2-1.1 3 0 5.1 2.2 5.1 5.7 0 3.1-1.1 5.8-2.1 8.3-.7 1.7-1 3.5-1.3 5.4-.3 2.3-.8 5.6-2.8 5.6-1.5 0-1.6-2.2-1.8-4.3-.2-2-.5-4-1.4-4s-1.2 2-1.4 4c-.2 2.1-.3 4.3-1.8 4.3-2 0-2.5-3.3-2.8-5.6-.3-1.9-.6-3.7-1.3-5.4C5.2 14.5 4 11.8 4 8.7 4 5.2 6.1 3 9.1 3c.9 0 1.7.2 2.4.5Z" fill="' .
                $fill .
                '"/>',
            "psychology"
                => '<path d="M15 3c-4 0-7.2 3-7.5 6.9A6.5 6.5 0 0 0 5 15v2h3v5h5v-4h4.5c3.6 0 6.5-2.9 6.5-6.5v-1A7.5 7.5 0 0 0 16.5 3H15Zm-2 8.3a2.3 2.3 0 1 1-4.6 0 2.3 2.3 0 0 1 4.6 0Zm7 0a2.3 2.3 0 1 1-4.6 0 2.3 2.3 0 0 1 4.6 0ZM16.5 17h-6v-2h6v2Z" fill="' .
                $fill .
                '"/>',
            "exercise"
                => '<path d="M21.5 8.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0ZM11 7l3 2-2 4 3 3v8h-3v-6l-4-3.5L5.5 19 3 17.8l3.5-6.3L9 10l-4-1.7L6 6l5 1Zm7 7 5 1.6-.9 2.7-5.7-1.9-2.4-2.6 1.3-2.6L18 14Z" fill="' .
                $fill .
                '"/>',
            "accessibility_new"
                => '<path d="M16 7a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm-9 3 8-1h2l8 1-.3 2.5-6.2-.8V28H16v-8h-1.8l-2.6 8H9l3-10v-6.3l-4.7.8L7 10Z" fill="' .
                $fill .
                '"/>',
            "hearing"
                => '<path d="M16 4a7 7 0 0 0-7 7H6a10 10 0 1 1 17.4 6.7l-3.1 3.5a4.8 4.8 0 0 0-1.1 3.1A3.7 3.7 0 0 1 15.5 28 4.5 4.5 0 0 1 11 23.5h3A1.5 1.5 0 0 0 15.5 25c.4 0 .7-.3.7-.7 0-1.9.7-3.8 2-5.2l3-3.4A7 7 0 0 0 16 4Zm0 5a2 2 0 0 0-2 2h-3a5 5 0 1 1 8.7 3.4l-2 2.2a6.5 6.5 0 0 0-1.6 3.4h-3a9.5 9.5 0 0 1 2.4-5.5l2-2.2A2 2 0 0 0 16 9Z" fill="' .
                $fill .
                '"/>',
            "restaurant"
                => '<path d="M8 3h2v10H8V3Zm4 0h2v10h-2V3ZM6 3h2v10H6V3Zm0 12h8v13h-3v-9H9v9H6V15Zm15-12c3 3.5 4.5 7 4.5 10.5 0 3-1.3 5-3.5 5V28h-3V3h2Z" fill="' .
                $fill .
                '"/>',
            "groups"
                => '<path d="M11 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm10-1a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM3 26c.5-5 4-8 8-8s7.5 3 8 8H3Zm15.5 0c-.3-2.7-1.5-5-3.3-6.7 1.2-.8 2.8-1.3 4.8-1.3 3.6 0 6.5 2.8 7 8h-8.5Z" fill="' .
                $fill .
                '"/>',
            "school"
                => '<path d="M16 4 2 11l14 7 11-5.5V21h3V11L16 4Zm-8 12v5c2.2 2 5 3 8 3s5.8-1 8-3v-5l-8 4-8-4Z" fill="' .
                $fill .
                '"/>',
            "visibility"
                => '<path d="M16 7C9 7 4.1 11.1 2 16c2.1 4.9 7 9 14 9s11.9-4.1 14-9c-2.1-4.9-7-9-14-9Zm0 14a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-3a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" fill="' .
                $fill .
                '"/>',
            "vaccines"
                => '<path d="M21 3 29 11l-2 2-2-2-5 5 3 3-2 2-3-3-8 8H6v-4l8-8-3-3 2-2 3 3 5-5-2-2 2-2ZM6 26h8v2H6v-2Z" fill="' .
                $fill .
                '"/>',
            "spa"
                => '<path d="M16 18c0-5.5 3.5-10 8-12 1 5.5-2.2 10.5-8 12Zm0 0C10.2 16.5 7 11.5 8 6c4.5 2 8 6.5 8 12Zm0 2c4.5-3.3 9.5-3.3 14 0-3 4.5-8.4 6.4-14 2-5.6 4.4-11 2.5-14-2 4.5-3.3 9.5-3.3 14 0Z" fill="' .
                $fill .
                '"/>',
            "monitor_heart"
                => '<path d="M5 6h22a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Zm2 9h4l2-4 4 8 2-4h6v-3h-8l-1 2-3-6-4 8H7v-1Z" fill="' .
                $fill .
                '"/>',
            default
                => '<path d="M16 3a13 13 0 1 0 0 26 13 13 0 0 0 0-26Zm2 8h-4v3h-3v4h3v3h4v-3h3v-4h-3v-3Z" fill="' .
                $fill .
                '"/>',
        };
    
    }

    public static function clinic_favicon_href(string $icon, string $color): string
    
    {
    
        $icon = normalize_clinic_icon($icon);
        $color = normalize_accent_color($color);
        $fill = "#ffffff";
        $shape = clinic_favicon_symbol_svg($icon, $fill);
        $svg =
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="Ícone do consultório"><rect width="64" height="64" rx="10" fill="' .
            e($color) .
            '"/><g transform="scale(2)">' .
            $shape .
            "</g></svg>";
        return "data:image/svg+xml;base64," . base64_encode($svg);
    
    }

    public static function clinic_visual_from_values(
        ?string $icon = null,
        ?string $color = null,
        ?string $profession = null,
    ): array 
    {
    
        $profession = normalize_profession((string) ($profession ?: "Médico(a)"));
        $icon = normalize_clinic_icon(
            $icon ?: profession_default_icon($profession),
        );
        $color = normalize_accent_color(
            $color ?: profession_default_color($profession),
        );
        $palette = clinic_accent_options()[$color] ?? [];
        $tokens = clinic_theme_tokens($color, $palette);
        return [
            "icon" => $icon,
            "brand" => $tokens["brand"],
            "brand_dark" => $tokens["brand_dark"],
            "brand_soft" => $tokens["brand_soft"],
            "brand_soft_2" => $tokens["brand_soft_2"],
            "on_brand" => $tokens["on_brand"],
            "favicon" => clinic_favicon_href($icon, $tokens["brand"]),
            "css_vars" => $tokens["css_vars"],
        ];
    
    }

    public static function clinic_icon_picker(string $current): string
    
    {
    
        $current = normalize_clinic_icon($current);
        $html =
            '<div class="visual-option-grid clinic-icon-grid clinic-icon-grid-symbols" role="radiogroup" aria-label="Ícone do consultório">';
        foreach (clinic_icon_options() as $key => $meta) {
            $checked = $key === $current ? " checked" : "";
            $label = (string) ($meta["label"] ?? "Ícone");
            $html .=
                '<label class="visual-option clinic-icon-option icon-only-option" title="' .
                e($label) .
                '" aria-label="' .
                e($label) .
                '"><input type="radio" name="clinic_icon" value="' .
                e($key) .
                '"' .
                $checked .
                '><span class="visual-option-mark clinic-icon-option-mark">' .
                icon($key) .
                "</span></label>";
        }
        return $html . "</div>";
    
    }

    public static function clinic_color_picker(string $current): string
    
    {
    
        $current = normalize_accent_color($current);
        $html =
            '<div class="color-swatch-grid clinic-color-grid clinic-color-grid-tones" role="radiogroup" aria-label="Cor de destaque">';
        foreach (clinic_accent_options() as $key => $meta) {
            $checked = $key === $current ? " checked" : "";
            $label = (string) ($meta["label"] ?? "Cor de destaque");
            $dark = (string) ($meta["dark"] ?? $key);
            $soft = (string) ($meta["soft"] ?? $key);
            $soft2 = (string) ($meta["soft2"] ?? $soft);
            $html .=
                '<label class="color-swatch-option clinic-color-option tone-only-option" title="' .
                e($label) .
                '" aria-label="' .
                e($label) .
                '"><input type="radio" name="accent_color" value="' .
                e($key) .
                '"' .
                $checked .
                '><span class="clinic-color-preview" aria-hidden="true"><i style="background:' .
                e($dark) .
                '"></i><i style="background:' .
                e($key) .
                '"></i><i style="background:' .
                e($soft) .
                '"></i><i style="background:' .
                e($soft2) .
                '"></i></span></label>';
        }
        return $html . "</div>";
    
    }

    public static function role_icon_picker(
        string $field,
        string $current,
        string $role = "",
    ): string 
    {
    
        $opts = role_icon_options($role ?: null);
        $current = isset($opts[$current]) ? $current : default_role_icon($role);
        if (!isset($opts[$current])) {
            $current = array_key_first($opts) ?: "groups";
        }
        $html =
            '<div class="sector-icon-grid sector-icon-picker sector-icon-grid-symbols" role="radiogroup" aria-label="Ícone do departamento">';
        foreach ($opts as $key => $label) {
            $checked = $key === $current ? " checked" : "";
            $html .=
                '<label class="sector-icon-choice sector-option-card icon-only-option" title="' .
                e($label) .
                '" aria-label="' .
                e($label) .
                '"><input type="radio" name="' .
                e($field) .
                '" value="' .
                e($key) .
                '"' .
                $checked .
                '><span class="sector-option-symbol">' .
                icon($key) .
                "</span></label>";
        }
        return $html . "</div>";
    
    }

    public static function profession_select_fields(array $cl = []): string
    
    {
    
        $current = normalize_profession(
            (string) ($cl["responsible_profession"] ?? "Médico(a)"),
        );
        return select_label(
            "Qual sua profissão?",
            "responsible_profession",
            clinical_profession_options(),
            $current,
            "required",
        );
    
    }

    public static function clinic_location_fields(array $cl = []): string
    
    {
    
        $uf = (string) ($cl["address_state"] ?? "MT");
        $city = (string) ($cl["address_city"] ?? "");
        $cityIbge = (string) ($cl["address_city_ibge"] ?? "");
        $tz = (string) ($cl["timezone"] ?? timezone_from_location($uf, $city));
        $line = (string) ($cl["address_line"] ?? "");
        $cityOptions =
            $city !== ""
                ? '<option value="' .
                    e($city) .
                    '" data-ibge="' .
                    e($cityIbge) .
                    '" selected>' .
                    e($city) .
                    "</option>"
                : '<option value="">Escolha primeiro o estado</option>';
        $states = ["" => "Escolha o estado"] + br_states();
        return form_row(
            "Endereço",
            input(
                "address_line",
                "text",
                $line,
                'maxlength="180" placeholder="Rua, número, bairro e complemento"',
            ),
        ) .
            '<div class="two">' .
            select_label(
                "Estado de atuação",
                "address_state",
                $states,
                $uf,
                "required data-br-state",
            ) .
            form_row(
                "Cidade de atuação",
                '<select name="address_city" required data-br-city data-selected-city="' .
                    e($city) .
                    '">' .
                    $cityOptions .
                    '</select><input type="hidden" name="address_city_ibge" value="' .
                    e($cityIbge) .
                    '" data-br-city-ibge><input type="hidden" name="timezone" value="' .
                    e($tz) .
                    '" data-br-timezone>',
            ) .
            "</div>";
    
    }

    public static function clinic_usage_icon(
        string $iconName,
        int $value,
        string $label,
        string $short,
    ): string 
    {
    
        return '<span class="usage-cell" title="' .
            e($label . " nos últimos 30 dias: " . $value) .
            '" aria-label="' .
            e($label . " nos últimos 30 dias: " . $value) .
            '">' .
            icon($iconName) .
            "<b>" .
            e((string) $value) .
            "</b><small>" .
            e($short) .
            "</small></span>";
    
    }
}
