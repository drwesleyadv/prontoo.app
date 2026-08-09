<?php
declare(strict_types=1);

namespace Prontoo\Domain\ClinicConfig;

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

final class ClinicConfigDomainOperations02
{
    private function __construct()
    {
    }

    public static function clinic_luminance(string $hex): float
    
    {
    
        $rgb = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_hex_rgb($hex);
        $vals = [];
        foreach ($rgb as $v) {
            $c = $v / 255;
            $vals[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $vals[0] + 0.7152 * $vals[1] + 0.0722 * $vals[2];
    
    }

    public static function clinic_contrast_ratio(string $a, string $b): float
    
    {
    
        $la = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::clinic_luminance($a) + 0.05;
        $lb = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::clinic_luminance($b) + 0.05;
        return max($la, $lb) / min($la, $lb);
    
    }

    public static function clinic_contrast_text(string $bg): string
    
    {
    
        $white = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::clinic_contrast_ratio($bg, "#ffffff");
        $ink = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::clinic_contrast_ratio($bg, "#17181c");
        return $white >= $ink ? "#ffffff" : "#17181c";
    
    }

    public static function clinic_theme_tokens(string $color, array $palette = []): array
    
    {
    
        $base = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_accent_color($color);
        $rgb = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_hex_rgb($base);
        $strong = $palette["dark"] ?? \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#000000", 0.82);
        if (\Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::clinic_contrast_ratio($strong, "#ffffff") < 4.5) {
            $strong = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#000000", 0.7);
        }
        $hover = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#000000", 0.88);
        $pressed = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#000000", 0.76);
        $surface = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.018);
        $surfaceElevated = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.01);
        $surfaceSoft = $palette["soft2"] ?? \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.055);
        $bg = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.045);
        $soft = $palette["soft"] ?? \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.105);
        $subtle = $palette["soft2"] ?? \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.05);
        $muted = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.22);
        $border = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.34);
        $line = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.155);
        $lineStrong = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.245);
        $controlBorder = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.205);
        $surfaceTint = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::clinic_mix_hex($base, "#ffffff", 0.035);
        $focus = "rgba(" . $rgb[0] . "," . $rgb[1] . "," . $rgb[2] . ",.16)";
        $shadow = "rgba(" . $rgb[0] . "," . $rgb[1] . "," . $rgb[2] . ",.22)";
        $on = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::clinic_contrast_text($base);
        $clockInk = "#ffffff";
        $css =
            "--clinic-accent:" .
            $base .
            ";--clinic-accent-rgb:" .
            $rgb[0] .
            "," .
            $rgb[1] .
            "," .
            $rgb[2] .
            ";--clinic-accent-strong:" .
            $strong .
            ";--clinic-accent-dark:" .
            $strong .
            ";--clinic-accent-hover:" .
            $hover .
            ";--clinic-accent-pressed:" .
            $pressed .
            ";--clinic-accent-soft:" .
            $soft .
            ";--clinic-accent-subtle:" .
            $subtle .
            ";--clinic-accent-muted:" .
            $muted .
            ";--clinic-accent-border:" .
            $border .
            ";--clinic-accent-focus:" .
            $focus .
            ";--clinic-accent-shadow:" .
            $shadow .
            ";--clinic-on-accent:" .
            $on .
            ";--clinic-surface-tint:" .
            $surfaceTint .
            ";--clinic-clock-bg:" .
            $base .
            ";--clinic-clock-border:" .
            $strong .
            ";--clinic-clock-ink:" .
            $clockInk .
            ";--bg:" .
            $bg .
            ";--surface:" .
            $surface .
            ";--surface-elevated:" .
            $surfaceElevated .
            ";--surface-raised:" .
            $surfaceSoft .
            ";--surface-soft:" .
            $surfaceSoft .
            ";--line:" .
            $line .
            ";--line-strong:" .
            $lineStrong .
            ";--control-border:" .
            $controlBorder .
            ";--brand:" .
            $base .
            ";--brand-rgb:" .
            $rgb[0] .
            "," .
            $rgb[1] .
            "," .
            $rgb[2] .
            ";--brand-dark:" .
            $strong .
            ";--brand-soft:" .
            $soft .
            ";--brand-soft-2:" .
            $subtle .
            ";--brand-border:" .
            $border .
            ";--brand-focus:" .
            $focus .
            ";--brand-shadow:" .
            $shadow .
            ";--on-brand:" .
            $on .
            ";";
        $css .=
            "--md-sys-color-primary:" .
            $base .
            ";--md-sys-color-on-primary:" .
            $on .
            ";--md-sys-color-primary-container:" .
            $soft .
            ";--md-sys-color-on-primary-container:" .
            $strong .
            ";--md-sys-color-secondary:" .
            $strong .
            ";--md-sys-color-on-secondary:#ffffff;--md-sys-color-secondary-container:" .
            $subtle .
            ";--md-sys-color-on-secondary-container:" .
            $strong .
            ";--md-sys-color-tertiary:" .
            $hover .
            ";--md-sys-color-on-tertiary:#ffffff;--md-sys-color-tertiary-container:" .
            $muted .
            ";--md-sys-color-on-tertiary-container:" .
            $strong .
            ";--md-sys-color-surface:" .
            $surface .
            ";--md-sys-color-surface-dim:" .
            $bg .
            ";--md-sys-color-surface-bright:" .
            $surfaceElevated .
            ";--md-sys-color-surface-container-lowest:#ffffff;--md-sys-color-surface-container-low:" .
            $surfaceElevated .
            ";--md-sys-color-surface-container:" .
            $surface .
            ";--md-sys-color-surface-container-high:" .
            $surfaceSoft .
            ";--md-sys-color-surface-container-highest:" .
            $subtle .
            ";--md-sys-color-surface-variant:" .
            $surfaceSoft .
            ";--md-sys-color-on-surface:#17181c;--md-sys-color-on-surface-variant:#6b7280;--md-sys-color-outline:" .
            $lineStrong .
            ";--md-sys-color-outline-variant:" .
            $line .
            ";--md-sys-color-inverse-surface:#111827;--md-sys-color-inverse-on-surface:#f8fafc;--md-sys-color-inverse-primary:" .
            $muted .
            ";--md-sys-color-shadow:rgba(15,23,42,.18);--md-sys-color-scrim:rgba(15,23,42,.36);--md-sys-state-hover:rgba(" .
            $rgb[0] .
            "," .
            $rgb[1] .
            "," .
            $rgb[2] .
            ",.08);--md-sys-state-focus:rgba(" .
            $rgb[0] .
            "," .
            $rgb[1] .
            "," .
            $rgb[2] .
            ",.12);--md-sys-state-pressed:rgba(" .
            $rgb[0] .
            "," .
            $rgb[1] .
            "," .
            $rgb[2] .
            ",.12);";
        return [
            "brand" => $base,
            "brand_dark" => $strong,
            "brand_soft" => $soft,
            "brand_soft_2" => $subtle,
            "on_brand" => $on,
            "brand_rgb" => implode(",", $rgb),
            "css_vars" => $css,
        ];
    
    }

    public static function role_icon_sets(): array
    
    {
    
        return [
            "recepcionista" => [
                "support_agent" => "Atendimento ao paciente",
                "headset_mic" => "Contato telefônico",
                "call" => "Ligação",
                "contact_phone" => "Cadastro e contato",
                "person_add" => "Novo interessado",
                "event_available" => "Confirmação de agenda",
                "assignment_ind" => "Identificação",
                "badge" => "Recepção",
                "record_voice_over" => "Comunicação",
                "front_hand" => "Acolhimento",
            ],
            "assistente" => [
                "clinical_notes" => "Anotações clínicas",
                "checklist" => "Lista de preparo",
                "assignment_turned_in" => "Tarefa conferida",
                "fact_check" => "Conferência",
                "medical_information" => "Informações clínicas",
                "lab_profile" => "Ficha de apoio",
                "quick_reference_all" => "Consulta rápida",
                "task_alt" => "Apoio concluído",
                "pending_actions" => "Pendências",
                "description" => "Documentos de apoio",
            ],
            "medico" => [
                "stethoscope" => "Atendimento profissional",
                "medical_services" => "Serviço clínico",
                "health_and_safety" => "Cuidado em saúde",
                "cardiology" => "Avaliação clínica",
                "prescriptions" => "Prescrição",
                "clinical_notes" => "Prontuário",
                "local_hospital" => "Ambiente clínico",
                "healing" => "Tratamento",
                "monitor_heart" => "Sinais vitais",
                "medical_information" => "Informação médica",
            ],
            "gerente" => [
                "business_center" => "Administração",
                "admin_panel_settings" => "Gestão de acesso",
                "manage_accounts" => "Colaboradores",
                "point_of_sale" => "Caixa",
                "payments" => "Financeiro",
                "request_quote" => "Cobranças",
                "monitoring" => "Indicadores",
                "groups" => "Equipe",
                "admin_panel_settings" => "Permissões",
            ],
        ];
    
    }

    public static function role_icon_options(?string $role = null): array
    
    {
    
        $sets = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::role_icon_sets();
        if ($role !== null && isset($sets[$role])) {
            return $sets[$role];
        }
        $all = [];
        foreach ($sets as $opts) {
            foreach ($opts as $k => $v) {
                $all[$k] = $v;
            }
        }
        return $all ?: ["groups" => "Equipe"];
    
    }

    public static function default_role_icon(string $role): string
    
    {
    
        return match ($role) {
            "recepcionista" => "support_agent",
            "assistente" => "clinical_notes",
            "medico" => "stethoscope",
            "gerente" => "admin_panel_settings",
            default => "groups",
        };
    
    }

    public static function admin_model_clinic_meta_key(): string
    
    {
    
        return "admin_global_exempt_clinic_id";
    
    }

    public static function admin_model_clinic_id(): int
    
    {
    
        try {
            return \Prontoo\Core\Tenant\TenantRegistry::modelClinicId();
        } catch (Throwable $e) {
            return 0;
        }
    
    }

    public static function ensure_global_admin_clinic_exempt(int $clinicId): void
    
    {
    
        return;
    
    }

    public static function admin_model_clinic_set(int $clinicId): void
    
    {
    
        return;
    
    }

    public static function admin_model_clinic_exclude_sql(string $column = "clinic_id"): string
    
    {
    
        try {
            return \Prontoo\Core\Metrics\GlobalMetricScope::modelClinicSql($column);
        } catch (Throwable $e) {
            return "";
        }
    
    }

    public static function admin_model_clinic_exclude_where(string $column = "clinic_id"): string
    
    {
    
        try {
            return \Prontoo\Core\Metrics\GlobalMetricScope::modelClinicWhere(
                $column,
            );
        } catch (Throwable $e) {
            return "1=1";
        }
    
    }

    public static function admin_model_clinic_count_note(): string
    
    {
    
        return \Prontoo\Core\Metrics\GlobalMetricScope::countNote();
    
    }

    public static function br_states(): array
    
    {
    
        return [
            "AC" => "Acre",
            "AL" => "Alagoas",
            "AP" => "Amapá",
            "AM" => "Amazonas",
            "BA" => "Bahia",
            "CE" => "Ceará",
            "DF" => "Distrito Federal",
            "ES" => "Espírito Santo",
            "GO" => "Goiás",
            "MA" => "Maranhão",
            "MT" => "Mato Grosso",
            "MS" => "Mato Grosso do Sul",
            "MG" => "Minas Gerais",
            "PA" => "Pará",
            "PB" => "Paraíba",
            "PR" => "Paraná",
            "PE" => "Pernambuco",
            "PI" => "Piauí",
            "RJ" => "Rio de Janeiro",
            "RN" => "Rio Grande do Norte",
            "RS" => "Rio Grande do Sul",
            "RO" => "Rondônia",
            "RR" => "Roraima",
            "SC" => "Santa Catarina",
            "SP" => "São Paulo",
            "SE" => "Sergipe",
            "TO" => "Tocantins",
        ];
    
    }

    public static function valid_timezone(string $tz): string
    
    {
    
        return in_array($tz, timezone_identifiers_list(), true)
            ? $tz
            : "America/Cuiaba";
    
    }

    public static function timezone_from_location(string $uf, string $city = ""): string
    
    {
    
        $uf = strtoupper(trim($uf));
        $cityNorm = mb_strtolower(trim($city));
        $exceptions = [
            "PE|fernando de noronha" => "America/Noronha",
            "AM|eirunepé" => "America/Eirunepe",
            "AM|envira" => "America/Eirunepe",
            "AM|guajará" => "America/Eirunepe",
            "AM|ipixuna" => "America/Eirunepe",
            "AM|itamarati" => "America/Eirunepe",
            "AM|jutaí" => "America/Eirunepe",
            "AM|tabatinga" => "America/Eirunepe",
            "AM|benjamin constant" => "America/Eirunepe",
            "AM|atalaia do norte" => "America/Eirunepe",
        ];
        if (isset($exceptions[$uf . "|" . $cityNorm])) {
            return $exceptions[$uf . "|" . $cityNorm];
        }
        return match ($uf) {
            "AC" => "America/Rio_Branco",
            "AM" => "America/Manaus",
            "RO" => "America/Porto_Velho",
            "RR" => "America/Boa_Vista",
            "MT" => "America/Cuiaba",
            "MS" => "America/Campo_Grande",
            default => "America/Sao_Paulo",
        };
    
    }
}
