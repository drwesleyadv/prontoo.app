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

final class ClinicConfigDomainOperations01
{
    private function __construct()
    {
    }

    public static function clinical_profession_options(): array
    
    {
    
        return [
            "Médico(a)" => "Médico(a)",
            "Cirurgião-dentista / Dentista" => "Cirurgião-dentista / Dentista",
            "Psicólogo(a)" => "Psicólogo(a)",
            "Fisioterapeuta" => "Fisioterapeuta",
            "Terapeuta ocupacional" => "Terapeuta ocupacional",
            "Fonoaudiólogo(a)" => "Fonoaudiólogo(a)",
            "Nutricionista" => "Nutricionista",
            "Assistente social" => "Assistente social",
            "Psicopedagogo(a)" => "Psicopedagogo(a)",
            "Optometrista" => "Optometrista",
            "Profissional de saúde" => "Profissional de saúde",
        ];
    
    }

    public static function normalize_profession(string $profession): string
    
    {
    
        $profession = mb_trim(preg_replace("/\s+/", " ", $profession) ?? "");
        if ($profession === "") {
            return "Médico(a)";
        }
        foreach (clinical_profession_options() as $key => $label) {
            if (
                mb_strtolower($profession, "UTF-8") ===
                    mb_strtolower((string) $key, "UTF-8") ||
                mb_strtolower($profession, "UTF-8") ===
                    mb_strtolower((string) $label, "UTF-8")
            ) {
                return (string) $key;
            }
        }
        $profession = mb_substr(strip_tags($profession), 0, 80, "UTF-8");
        return $profession !== "" ? $profession : "Médico(a)";
    
    }

    public static function clinic_icon_options(): array
    
    {
    
        $base = [
            "medical_services" => [
                "label" => "Medicina / clínica geral",
                "profession" => "Médico(a)",
            ],
            "stethoscope" => [
                "label" => "Consulta médica",
                "profession" => "Médico(a)",
            ],
            "dentistry" => [
                "label" => "Odontologia",
                "profession" => "Cirurgião-dentista / Dentista",
            ],
            "psychology" => [
                "label" => "Psicologia",
                "profession" => "Psicólogo(a)",
            ],
            "exercise" => [
                "label" => "Fisioterapia / movimento",
                "profession" => "Fisioterapeuta",
            ],
            "accessibility_new" => [
                "label" => "Terapia ocupacional",
                "profession" => "Terapeuta ocupacional",
            ],
            "hearing" => [
                "label" => "Fonoaudiologia",
                "profession" => "Fonoaudiólogo(a)",
            ],
            "restaurant" => [
                "label" => "Nutrição",
                "profession" => "Nutricionista",
            ],
            "groups" => [
                "label" => "Serviço social / equipe",
                "profession" => "Assistente social",
            ],
            "school" => [
                "label" => "Psicopedagogia",
                "profession" => "Psicopedagogo(a)",
            ],
            "visibility" => [
                "label" => "Optometria / visão",
                "profession" => "Optometrista",
            ],
            "local_hospital" => [
                "label" => "Saúde / consultório",
                "profession" => "",
            ],
            "health_and_safety" => [
                "label" => "Cuidado e segurança",
                "profession" => "",
            ],
            "clinical_notes" => [
                "label" => "Prontuário e documentos",
                "profession" => "",
            ],
            "vaccines" => [
                "label" => "Procedimentos e vacinação",
                "profession" => "",
            ],
            "spa" => ["label" => "Bem-estar", "profession" => ""],
            "monitor_heart" => [
                "label" => "Monitoramento clínico",
                "profession" => "",
            ],
        ];
        $health = function_exists("patient_health_icon_options")
            ? patient_health_icon_options()
            : [];
        foreach ($health as $icon => $label) {
            if (!isset($base[$icon])) {
                $base[$icon] = ["label" => (string) $label, "profession" => ""];
            }
        }
        $extra = [
            "home_health" => "Atenção domiciliar",
            "emergency" => "Emergência",
            "emergency_home" => "Urgência domiciliar",
            "cardiology" => "Cardiologia",
            "ecg_heart" => "ECG",
            "vital_signs" => "Sinais vitais",
            "bloodtype" => "Tipo sanguíneo",
            "labs" => "Exames laboratoriais",
            "science" => "Laboratório",
            "biotech" => "Biotecnologia",
            "genetics" => "Genética",
            "radiology" => "Radiologia",
            "surgical" => "Cirurgia",
            "healing" => "Curativo",
            "personal_injury" => "Lesão",
            "sick" => "Sintomas",
            "thermometer" => "Temperatura",
            "masks" => "Máscara",
            "allergies" => "Alergias",
            "dermatology" => "Dermatologia",
            "pulmonology" => "Pneumologia",
            "neurology" => "Neurologia",
            "orthopedics" => "Ortopedia",
            "gastroenterology" => "Gastroenterologia",
            "endocrinology" => "Endocrinologia",
            "nephrology" => "Nefrologia",
            "ophthalmology" => "Oftalmologia",
            "psychiatry" => "Psiquiatria",
            "cognition" => "Cognição",
            "pediatrics" => "Pediatria",
            "child_care" => "Cuidado infantil",
            "pregnant_woman" => "Gestação",
            "elderly" => "Idoso",
            "medication" => "Medicamento",
            "medication_liquid" => "Medicamento líquido",
            "pill" => "Comprimido",
            "prescriptions" => "Prescrições",
            "assignment" => "Ficha clínica",
            "assignment_ind" => "Identificação clínica",
            "description" => "Documento clínico",
            "note_alt" => "Anotação clínica",
            "event_note" => "Agenda clínica",
            "fact_check" => "Checklist clínico",
            "analytics" => "Indicadores",
            "query_stats" => "Acompanhamento",
        ];
        foreach ($extra as $icon => $label) {
            if (!isset($base[$icon])) {
                $base[$icon] = ["label" => $label, "profession" => ""];
            }
        }
        return $base;
    
    }

    public static function patient_health_icon_options(): array
    
    {
    
        return [
            "clinical_notes" => "Prontuário clínico",
            "medical_services" => "Serviço médico",
            "stethoscope" => "Estetoscópio",
            "health_and_safety" => "Saúde e segurança",
            "local_hospital" => "Hospital",
            "home_health" => "Atenção domiciliar",
            "emergency" => "Emergência",
            "emergency_home" => "Urgência domiciliar",
            "monitor_heart" => "Monitor cardíaco",
            "cardiology" => "Cardiologia",
            "ecg_heart" => "ECG",
            "vital_signs" => "Sinais vitais",
            "bloodtype" => "Tipo sanguíneo",
            "vaccines" => "Vacinas",
            "syringe" => "Injeção",
            "medication" => "Medicamento",
            "medication_liquid" => "Medicamento líquido",
            "pill" => "Comprimido",
            "prescriptions" => "Prescrições",
            "labs" => "Exames laboratoriais",
            "science" => "Laboratório",
            "biotech" => "Biotecnologia",
            "genetics" => "Genética",
            "radiology" => "Radiologia",
            "surgical" => "Cirurgia",
            "healing" => "Curativo",
            "personal_injury" => "Lesão",
            "sick" => "Sintomas",
            "thermometer" => "Temperatura",
            "masks" => "Máscara",
            "allergies" => "Alergias",
            "dermatology" => "Dermatologia",
            "pulmonology" => "Pneumologia",
            "neurology" => "Neurologia",
            "orthopedics" => "Ortopedia",
            "gastroenterology" => "Gastroenterologia",
            "endocrinology" => "Endocrinologia",
            "nephrology" => "Nefrologia",
            "ophthalmology" => "Oftalmologia",
            "visibility" => "Visão",
            "dentistry" => "Odontologia",
            "psychology" => "Psicologia",
            "psychiatry" => "Psiquiatria",
            "cognition" => "Cognição",
            "pediatrics" => "Pediatria",
            "child_care" => "Cuidado infantil",
            "pregnant_woman" => "Gestação",
            "elderly" => "Idoso",
            "accessibility_new" => "Reabilitação",
            "exercise" => "Movimento",
            "hearing" => "Audição",
            "restaurant" => "Nutrição",
            "spa" => "Bem-estar",
            "water_drop" => "Hidratação",
            "sleep_score" => "Sono",
            "self_improvement" => "Respiração e cuidado",
            "assignment" => "Ficha e avaliação",
            "assignment_ind" => "Identificação clínica",
            "description" => "Documento clínico",
            "note_alt" => "Anotação",
            "event_note" => "Agenda clínica",
            "calendar_month" => "Calendário",
            "fact_check" => "Checklist",
            "analytics" => "Indicadores",
            "query_stats" => "Acompanhamento",
        ];
    
    }

    public static function clinic_accent_options(): array
    
    {
    
        return [
            PRONTOO_DEFAULT_ACCENT_COLOR => [
                "label" => "Verde Prontoo",
                "dark" => "#1d583f",
                "soft" => "#d9f0df",
                "soft2" => "#f6fbf7",
                "profession" => "",
            ],
            "#064e3b" => [
                "label" => "Verde mineral escuro",
                "dark" => "#043b2d",
                "soft" => "#e4f4ee",
                "soft2" => "#f3fbf7",
                "profession" => "",
            ],
            "#0f766e" => [
                "label" => "Verde mineral",
                "dark" => "#0b5e58",
                "soft" => "#e5f5f2",
                "soft2" => "#f4fbfa",
                "profession" => "Médico(a)",
            ],
            "#5ba892" => [
                "label" => "Verde mineral claro",
                "dark" => "#227d6c",
                "soft" => "#edf8f5",
                "soft2" => "#f8fcfb",
                "profession" => "",
            ],
            "#0f3f5f" => [
                "label" => "Azul clínico escuro",
                "dark" => "#0a3049",
                "soft" => "#e8f2f7",
                "soft2" => "#f5fafe",
                "profession" => "",
            ],
            "#2563eb" => [
                "label" => "Azul clínico",
                "dark" => "#1d4ed8",
                "soft" => "#eff6ff",
                "soft2" => "#f8fbff",
                "profession" => "Psicólogo(a)",
            ],
            "#60a5fa" => [
                "label" => "Azul clínico claro",
                "dark" => "#2563eb",
                "soft" => "#eff6ff",
                "soft2" => "#f8fbff",
                "profession" => "",
            ],
            "#5b1624" => [
                "label" => "Vinho escuro",
                "dark" => "#43101a",
                "soft" => "#fdecef",
                "soft2" => "#fff7f8",
                "profession" => "Cirurgião-dentista / Dentista",
            ],
            "#8a2432" => [
                "label" => "Vinho",
                "dark" => "#6f1d2a",
                "soft" => "#fff0f2",
                "soft2" => "#fff8f9",
                "profession" => "",
            ],
            "#b64b5a" => [
                "label" => "Vinho claro",
                "dark" => "#8a2432",
                "soft" => "#fff1f3",
                "soft2" => "#fff9fa",
                "profession" => "",
            ],
            "#4c1d95" => [
                "label" => "Violeta discreto escuro",
                "dark" => "#38166f",
                "soft" => "#f2efff",
                "soft2" => "#faf8ff",
                "profession" => "Terapeuta ocupacional",
            ],
            "#6d3bc3" => [
                "label" => "Violeta discreto",
                "dark" => "#53309a",
                "soft" => "#f4f1ff",
                "soft2" => "#fbfaff",
                "profession" => "",
            ],
            "#9b7ae5" => [
                "label" => "Violeta discreto claro",
                "dark" => "#6d3bc3",
                "soft" => "#f6f3ff",
                "soft2" => "#fcfbff",
                "profession" => "",
            ],
            "#7c2d12" => [
                "label" => "Terracota escuro",
                "dark" => "#5f220e",
                "soft" => "#fff1e8",
                "soft2" => "#fff8f3",
                "profession" => "",
            ],
            "#c2410c" => [
                "label" => "Terracota",
                "dark" => "#9a3412",
                "soft" => "#fff3ea",
                "soft2" => "#fff9f5",
                "profession" => "",
            ],
            "#e07a3f" => [
                "label" => "Terracota claro",
                "dark" => "#c2410c",
                "soft" => "#fff4ed",
                "soft2" => "#fffbf7",
                "profession" => "",
            ],
            "#713f12" => [
                "label" => "Âmbar terroso escuro",
                "dark" => "#57300e",
                "soft" => "#fff6df",
                "soft2" => "#fffaf0",
                "profession" => "Fonoaudiólogo(a)",
            ],
            "#b7791f" => [
                "label" => "Âmbar terroso",
                "dark" => "#925d18",
                "soft" => "#fff8e7",
                "soft2" => "#fffdf5",
                "profession" => "",
            ],
            "#d6a84f" => [
                "label" => "Âmbar terroso claro",
                "dark" => "#b7791f",
                "soft" => "#fff9ec",
                "soft2" => "#fffdf7",
                "profession" => "",
            ],
            "#365314" => [
                "label" => "Oliva escuro",
                "dark" => "#293f0f",
                "soft" => "#edf5e3",
                "soft2" => "#f8fbf2",
                "profession" => "Nutricionista",
            ],
            "#6b8e23" => [
                "label" => "Oliva",
                "dark" => "#54701c",
                "soft" => "#f0f6e8",
                "soft2" => "#fbfdf7",
                "profession" => "",
            ],
            "#a3b76a" => [
                "label" => "Oliva claro",
                "dark" => "#6b8e23",
                "soft" => "#f3f8eb",
                "soft2" => "#fcfdf8",
                "profession" => "",
            ],
            "#0f3f46" => [
                "label" => "Petróleo escuro",
                "dark" => "#0a3035",
                "soft" => "#e5f2f3",
                "soft2" => "#f4fafb",
                "profession" => "",
            ],
            "#14747c" => [
                "label" => "Petróleo",
                "dark" => "#0f5f66",
                "soft" => "#e7f6f7",
                "soft2" => "#f5fbfc",
                "profession" => "",
            ],
            "#5aa6ad" => [
                "label" => "Petróleo claro",
                "dark" => "#14747c",
                "soft" => "#edf8f9",
                "soft2" => "#f8fcfd",
                "profession" => "",
            ],
            "#111827" => [
                "label" => "Grafite escuro",
                "dark" => "#030712",
                "soft" => "#f1f5f9",
                "soft2" => "#f8fafc",
                "profession" => "",
            ],
            "#334155" => [
                "label" => "Grafite",
                "dark" => "#1f2937",
                "soft" => "#f1f5f9",
                "soft2" => "#f8fafc",
                "profession" => "",
            ],
            "#64748b" => [
                "label" => "Grafite claro",
                "dark" => "#334155",
                "soft" => "#f1f5f9",
                "soft2" => "#f8fafc",
                "profession" => "",
            ],
        ];
    
    }

    public static function normalize_clinic_icon(?string $icon): string
    
    {
    
        $icon = preg_replace("/[^a-z0-9_]+/i", "", (string) $icon) ?: "";
        return array_key_exists($icon, clinic_icon_options())
            ? $icon
            : "medical_services";
    
    }

    public static function normalize_accent_color(?string $color): string
    
    {
    
        $color = strtolower(mb_trim((string) $color));
        if (array_key_exists($color, clinic_accent_options())) {
            return $color;
        }
        if (preg_match('/^#[0-9a-f]{6}$/', $color)) {
            return $color;
        }
        return "#334155";
    
    }

    public static function profession_default_icon(string $profession): string
    
    {
    
        foreach (clinic_icon_options() as $icon => $meta) {
            if (($meta["profession"] ?? "") === $profession) {
                return $icon;
            }
        }
        return "medical_services";
    
    }

    public static function profession_default_color(string $profession): string
    
    {
    
        return "#334155";
    
    }

    public static function clinic_hex_rgb(string $hex): array
    
    {
    
        $hex = ltrim(trim($hex), "#");
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = "0f766e";
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    
    }

    public static function clinic_rgb_hex(array $rgb): string
    
    {
    
        return sprintf(
            "#%02x%02x%02x",
            max(0, min(255, (int) round($rgb[0] ?? 0, 0, \RoundingMode::HalfAwayFromZero))),
            max(0, min(255, (int) round($rgb[1] ?? 0, 0, \RoundingMode::HalfAwayFromZero))),
            max(0, min(255, (int) round($rgb[2] ?? 0, 0, \RoundingMode::HalfAwayFromZero))),
        );
    
    }

    public static function clinic_mix_hex(string $a, string $b, float $aWeight): string
    
    {
    
        $ra = clinic_hex_rgb($a);
        $rb = clinic_hex_rgb($b);
        $w = max(0, min(1, $aWeight));
        return clinic_rgb_hex([
            $ra[0] * $w + $rb[0] * (1 - $w),
            $ra[1] * $w + $rb[1] * (1 - $w),
            $ra[2] * $w + $rb[2] * (1 - $w),
        ]);
    
    }
}
