<?php
declare(strict_types=1);
function clinical_profession_options(): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinical_profession_options
     * Responsabilidade: Implementa a responsabilidade “clinical profession options” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `normalize_profession`, `profession_select_fields`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function normalize_profession(string $profession): string
{
    /*
     * GUIA DE MANUTENÇÃO — normalize_profession
     * Responsabilidade: Transforma e normaliza “normalize profession” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_signup`, `page_onboarding`, `clinic_visual_from_values`, `profession_select_fields`, `clinic_profession`, `closure@app/Domain/Clinic/ClinicConfig.php:1159`, `page_settings`.
     * Dependências chamadas: `trim`, `preg_replace`, `clinical_profession_options`, `mb_strtolower`, `mb_substr`, `strip_tags`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $profession = trim(preg_replace("/\s+/", " ", $profession) ?? "");
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
function clinic_icon_options(): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_icon_options
     * Responsabilidade: Implementa a responsabilidade “clinic icon options” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `normalize_clinic_icon`, `profession_default_icon`, `clinic_icon_picker`.
     * Dependências chamadas: `function_exists`, `patient_health_icon_options`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function patient_health_icon_options(): array
{
    /*
     * GUIA DE MANUTENÇÃO — patient_health_icon_options
     * Responsabilidade: Implementa a responsabilidade “patient health icon options” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_icon_options`, `normalize_patient_tab_icon`, `patient_tab_icon_picker`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function clinic_accent_options(): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_accent_options
     * Responsabilidade: Implementa a responsabilidade “clinic accent options” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `normalize_accent_color`, `clinic_visual_from_values`, `clinic_color_picker`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function normalize_clinic_icon(?string $icon): string
{
    /*
     * GUIA DE MANUTENÇÃO — normalize_clinic_icon
     * Responsabilidade: Transforma e normaliza “normalize clinic icon” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_onboarding`, `clinic_favicon_href`, `clinic_visual_from_values`, `clinic_icon_picker`, `page_settings`.
     * Dependências chamadas: `preg_replace`, `array_key_exists`, `clinic_icon_options`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $icon = preg_replace("/[^a-z0-9_]+/i", "", (string) $icon) ?: "";
    return array_key_exists($icon, clinic_icon_options())
        ? $icon
        : "medical_services";
}
function normalize_accent_color(?string $color): string
{
    /*
     * GUIA DE MANUTENÇÃO — normalize_accent_color
     * Responsabilidade: Transforma e normaliza “normalize accent color” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_onboarding`, `clinic_favicon_href`, `clinic_theme_tokens`, `clinic_visual_from_values`, `clinic_color_picker`, `page_settings`.
     * Dependências chamadas: `strtolower`, `trim`, `array_key_exists`, `clinic_accent_options`, `preg_match`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $color = strtolower(trim((string) $color));
    if (array_key_exists($color, clinic_accent_options())) {
        return $color;
    }
    if (preg_match('/^#[0-9a-f]{6}$/', $color)) {
        return $color;
    }
    return "#334155";
}
function profession_default_icon(string $profession): string
{
    /*
     * GUIA DE MANUTENÇÃO — profession_default_icon
     * Responsabilidade: Implementa a responsabilidade “profession default icon” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_visual_from_values`.
     * Dependências chamadas: `clinic_icon_options`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    foreach (clinic_icon_options() as $icon => $meta) {
        if (($meta["profession"] ?? "") === $profession) {
            return $icon;
        }
    }
    return "medical_services";
}
function profession_default_color(string $profession): string
{
    /*
     * GUIA DE MANUTENÇÃO — profession_default_color
     * Responsabilidade: Implementa a responsabilidade “profession default color” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_visual_from_values`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return "#334155";
}
function clinic_favicon_symbol_svg(string $icon, string $fill): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_favicon_symbol_svg
     * Responsabilidade: Implementa a responsabilidade “clinic favicon symbol svg” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_favicon_href`.
     * Dependências chamadas: `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function clinic_favicon_href(string $icon, string $color): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_favicon_href
     * Responsabilidade: Implementa a responsabilidade “clinic favicon href” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_visual_from_values`.
     * Dependências chamadas: `normalize_clinic_icon`, `normalize_accent_color`, `clinic_favicon_symbol_svg`, `e`, `base64_encode`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function clinic_hex_rgb(string $hex): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_hex_rgb
     * Responsabilidade: Implementa a responsabilidade “clinic hex rgb” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_mix_hex`, `clinic_luminance`, `clinic_theme_tokens`.
     * Dependências chamadas: `ltrim`, `trim`, `strlen`, `preg_match`, `hexdec`, `substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function clinic_rgb_hex(array $rgb): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_rgb_hex
     * Responsabilidade: Implementa a responsabilidade “clinic rgb hex” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_mix_hex`.
     * Dependências chamadas: `sprintf`, `max`, `min`, `round`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return sprintf(
        "#%02x%02x%02x",
        max(0, min(255, (int) round($rgb[0] ?? 0))),
        max(0, min(255, (int) round($rgb[1] ?? 0))),
        max(0, min(255, (int) round($rgb[2] ?? 0))),
    );
}
function clinic_mix_hex(string $a, string $b, float $aWeight): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_mix_hex
     * Responsabilidade: Implementa a responsabilidade “clinic mix hex” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_theme_tokens`.
     * Dependências chamadas: `clinic_hex_rgb`, `max`, `min`, `clinic_rgb_hex`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ra = clinic_hex_rgb($a);
    $rb = clinic_hex_rgb($b);
    $w = max(0, min(1, $aWeight));
    return clinic_rgb_hex([
        $ra[0] * $w + $rb[0] * (1 - $w),
        $ra[1] * $w + $rb[1] * (1 - $w),
        $ra[2] * $w + $rb[2] * (1 - $w),
    ]);
}
function clinic_luminance(string $hex): float
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_luminance
     * Responsabilidade: Implementa a responsabilidade “clinic luminance” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_contrast_ratio`.
     * Dependências chamadas: `clinic_hex_rgb`, `pow`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $rgb = clinic_hex_rgb($hex);
    $vals = [];
    foreach ($rgb as $v) {
        $c = $v / 255;
        $vals[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $vals[0] + 0.7152 * $vals[1] + 0.0722 * $vals[2];
}
function clinic_contrast_ratio(string $a, string $b): float
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_contrast_ratio
     * Responsabilidade: Implementa a responsabilidade “clinic contrast ratio” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_contrast_text`, `clinic_theme_tokens`.
     * Dependências chamadas: `clinic_luminance`, `max`, `min`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $la = clinic_luminance($a) + 0.05;
    $lb = clinic_luminance($b) + 0.05;
    return max($la, $lb) / min($la, $lb);
}
function clinic_contrast_text(string $bg): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_contrast_text
     * Responsabilidade: Implementa a responsabilidade “clinic contrast text” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_theme_tokens`.
     * Dependências chamadas: `clinic_contrast_ratio`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $white = clinic_contrast_ratio($bg, "#ffffff");
    $ink = clinic_contrast_ratio($bg, "#17181c");
    return $white >= $ink ? "#ffffff" : "#17181c";
}
function clinic_theme_tokens(string $color, array $palette = []): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_theme_tokens
     * Responsabilidade: Implementa a responsabilidade “clinic theme tokens” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_visual_from_values`.
     * Dependências chamadas: `normalize_accent_color`, `clinic_hex_rgb`, `clinic_mix_hex`, `clinic_contrast_ratio`, `clinic_contrast_text`, `implode`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $base = normalize_accent_color($color);
    $rgb = clinic_hex_rgb($base);
    $strong = $palette["dark"] ?? clinic_mix_hex($base, "#000000", 0.82);
    if (clinic_contrast_ratio($strong, "#ffffff") < 4.5) {
        $strong = clinic_mix_hex($base, "#000000", 0.7);
    }
    $hover = clinic_mix_hex($base, "#000000", 0.88);
    $pressed = clinic_mix_hex($base, "#000000", 0.76);
    $surface = clinic_mix_hex($base, "#ffffff", 0.018);
    $surfaceElevated = clinic_mix_hex($base, "#ffffff", 0.01);
    $surfaceSoft = $palette["soft2"] ?? clinic_mix_hex($base, "#ffffff", 0.055);
    $bg = clinic_mix_hex($base, "#ffffff", 0.045);
    $soft = $palette["soft"] ?? clinic_mix_hex($base, "#ffffff", 0.105);
    $subtle = $palette["soft2"] ?? clinic_mix_hex($base, "#ffffff", 0.05);
    $muted = clinic_mix_hex($base, "#ffffff", 0.22);
    $border = clinic_mix_hex($base, "#ffffff", 0.34);
    $line = clinic_mix_hex($base, "#ffffff", 0.155);
    $lineStrong = clinic_mix_hex($base, "#ffffff", 0.245);
    $controlBorder = clinic_mix_hex($base, "#ffffff", 0.205);
    $surfaceTint = clinic_mix_hex($base, "#ffffff", 0.035);
    $focus = "rgba(" . $rgb[0] . "," . $rgb[1] . "," . $rgb[2] . ",.16)";
    $shadow = "rgba(" . $rgb[0] . "," . $rgb[1] . "," . $rgb[2] . ",.22)";
    $on = clinic_contrast_text($base);
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
function clinic_visual_from_values(
    ?string $icon = null,
    ?string $color = null,
    ?string $profession = null,
): array {
    /*
     * GUIA DE MANUTENÇÃO — clinic_visual_from_values
     * Responsabilidade: Implementa a responsabilidade “clinic visual from values” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_onboarding`, `clinic_visual`, `closure@app/Domain/Clinic/ClinicConfig.php:836`, `page_settings`.
     * Dependências chamadas: `normalize_profession`, `normalize_clinic_icon`, `profession_default_icon`, `normalize_accent_color`, `profession_default_color`, `clinic_accent_options`, `clinic_theme_tokens`, `clinic_favicon_href`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function clinic_visual(?int $clinicId = null, ?array $ctx = null): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_visual
     * Responsabilidade: Implementa a responsabilidade “clinic visual” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page`.
     * Dependências chamadas: `clinic_visual_from_values`, `has_cfg`, `one`, `db_schema_error_is_missing_table`, `stripos`, `->getMessage`, `error_log`, `function_exists`, `server_json_cache_remember`, `server_json_cache_safe_key`, `server_json_cache_ttl`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    $ctx = $ctx ?: [];
    if ($ctx && ($ctx["scope"] ?? "") === "clinic") {
        return clinic_visual_from_values(
            $ctx["clinic_icon"] ?? null,
            $ctx["accent_color"] ?? null,
            $ctx["responsible_profession"] ?? null,
        );
    }
    if ($clinicId && $clinicId > 0 && has_cfg()) {
        $loader = function () use ($clinicId): array {
            /*
             * GUIA DE MANUTENÇÃO — closure@app/Domain/Clinic/ClinicConfig.php:836
             * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
             * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
             * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
             * Dependências chamadas: `one`, `clinic_visual_from_values`, `db_schema_error_is_missing_table`, `stripos`, `->getMessage`, `error_log`.
             * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
             * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
             */
            try {
                $row = one(
                    "SELECT clinic_icon,accent_color,responsible_profession FROM pi_clinics WHERE id=?",
                    [$clinicId],
                );
                if ($row) {
                    return clinic_visual_from_values(
                        $row["clinic_icon"] ?? null,
                        $row["accent_color"] ?? null,
                        $row["responsible_profession"] ?? null,
                    );
                }
            } catch (Throwable $e) {
                if (
                    !db_schema_error_is_missing_table($e) &&
                    stripos($e->getMessage(), "Unknown column") === false
                ) {
                    error_log("[Prontoo clinic visual] " . $e->getMessage());
                }
            }
            return clinic_visual_from_values();
        };
        if (function_exists("server_json_cache_remember")) {
            return server_json_cache_remember(
                "clinic",
                server_json_cache_safe_key("visual", [$clinicId]),
                server_json_cache_ttl("clinic"),
                $loader,
                ["clinic:" . $clinicId],
            );
        }
        return $loader();
    }
    return clinic_visual_from_values();
}
function clinic_icon_picker(string $current): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_icon_picker
     * Responsabilidade: Implementa a responsabilidade “clinic icon picker” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_onboarding`, `page_settings`.
     * Dependências chamadas: `normalize_clinic_icon`, `clinic_icon_options`, `e`, `icon`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function clinic_color_picker(string $current): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_color_picker
     * Responsabilidade: Implementa a responsabilidade “clinic color picker” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_onboarding`, `page_settings`.
     * Dependências chamadas: `normalize_accent_color`, `clinic_accent_options`, `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function role_icon_sets(): array
{
    /*
     * GUIA DE MANUTENÇÃO — role_icon_sets
     * Responsabilidade: Implementa a responsabilidade “role icon sets” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `role_icon_options`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function role_icon_options(?string $role = null): array
{
    /*
     * GUIA DE MANUTENÇÃO — role_icon_options
     * Responsabilidade: Implementa a responsabilidade “role icon options” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `role_icon_picker`, `clinic_role_sector_fields`, `clinic_role_icons`, `closure@app/Domain/Clinic/ClinicConfig.php:1422`, `page_settings`.
     * Dependências chamadas: `role_icon_sets`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $sets = role_icon_sets();
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
function role_icon_picker(
    string $field,
    string $current,
    string $role = "",
): string {
    /*
     * GUIA DE MANUTENÇÃO — role_icon_picker
     * Responsabilidade: Implementa a responsabilidade “role icon picker” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_role_sector_fields`.
     * Dependências chamadas: `role_icon_options`, `default_role_icon`, `array_key_first`, `e`, `icon`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function default_role_icon(string $role): string
{
    /*
     * GUIA DE MANUTENÇÃO — default_role_icon
     * Responsabilidade: Implementa a responsabilidade “default role icon” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_onboarding`, `role_icon_picker`, `clinic_role_sector_fields`, `seed_clinic_roles`, `closure@app/Domain/Clinic/ClinicConfig.php:1352`, `clinic_role_icons`, `closure@app/Domain/Clinic/ClinicConfig.php:1422`, `role_icon_for` e mais 2.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return match ($role) {
        "recepcionista" => "support_agent",
        "assistente" => "clinical_notes",
        "medico" => "stethoscope",
        "gerente" => "admin_panel_settings",
        default => "groups",
    };
}
function clinic_role_label_fields(int $cid): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_role_label_fields
     * Responsabilidade: Monta a representação de interface associada a “clinic role label fields” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `seed_clinic_roles`, `clinic_roles`, `trim`, `form_row`, `input`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    seed_clinic_roles($cid);
    $labels = clinic_roles($cid, false);
    $defs = [
        "recepcionista" => "Recepção",
        "assistente" => "Assistente",
        "medico" => "Profissional",
        "gerente" => "Administrativo",
    ];
    $old = [
        "recepcionista" => "Atendimento",
        "assistente" => "Triagem",
        "medico" => "Médica",
        "gerente" => "Gestão",
    ];
    $html = '<div class="two">';
    foreach ($defs as $role => $label) {
        $value = trim((string) ($labels[$role] ?? $label));
        if (isset($old[$role]) && $value === $old[$role]) {
            $value = $label;
        }
        $html .= form_row(
            $label,
            input(
                "role_label[" . $role . "]",
                "text",
                $value,
                'maxlength="80" required',
            ),
        );
    }
    return $html . "</div>";
}
function clinic_role_sector_fields(int $cid): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_role_sector_fields
     * Responsabilidade: Implementa a responsabilidade “clinic role sector fields” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_settings`.
     * Dependências chamadas: `seed_clinic_roles`, `clinic_roles`, `q`, `->fetchAll`, `error_log`, `->getMessage`, `role_icon_options`, `trim`, `default_role_icon`, `array_key_first`, `e`, `icon` e mais 3.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    seed_clinic_roles($cid);
    $labels = clinic_roles($cid, false);
    $icons = [];
    try {
        $rows = q(
            "SELECT role_code,icon_name FROM pi_clinic_roles WHERE clinic_id=?",
            [$cid],
        )->fetchAll();
        foreach ($rows as $r) {
            $icons[(string) $r["role_code"]] = (string) ($r["icon_name"] ?? "");
        }
    } catch (Throwable $e) {
        error_log(
            "[Prontoo recoverable " . __FUNCTION__ . "] " . $e->getMessage(),
        );
    }
    $defs = [
        "recepcionista" => "Recepção",
        "assistente" => "Assistente",
        "medico" => "Profissional",
        "gerente" => "Administrativo",
    ];
    $descriptions = [
        "recepcionista" => "Entrada, acolhimento, cadastro e agenda.",
        "assistente" => "Apoio, preparo, conferência e documentos.",
        "medico" => "Atendimento profissional e registros clínicos.",
        "gerente" => "Gestão, permissões, equipe e financeiro.",
    ];
    $html =
        '<div class="sector-editor sector-editor-modern sector-editor-compact">';
    foreach ($defs as $role => $fallback) {
        $opts = role_icon_options($role);
        $label = trim((string) ($labels[$role] ?? $fallback));
        if ($label === "") {
            $label = $fallback;
        }
        $ico = trim((string) ($icons[$role] ?? default_role_icon($role)));
        if ($ico === "" || !isset($opts[$ico])) {
            $ico = default_role_icon($role);
        }
        if (!isset($opts[$ico])) {
            $ico = array_key_first($opts) ?: "groups";
        }
        $description = $descriptions[$role] ?? "Departamento do consultório.";
        $html .=
            '<section class="sector-card sector-card-' .
            e($role) .
            '"><div class="sector-card-top"><span class="sector-card-icon">' .
            icon($ico) .
            '</span><div class="sector-card-copy"><strong>' .
            e($fallback) .
            "</strong><small>" .
            e($description) .
            '</small></div><span class="sector-card-chip">' .
            icon("visibility") .
            '<span>Visível</span></span></div><div class="sector-card-fields">' .
            form_row(
                "Renomear",
                input(
                    "role_label[" . $role . "]",
                    "text",
                    $label,
                    'maxlength="80" required',
                ),
            ) .
            '<div class="sector-icon-field"><div class="sector-icon-field-head"><span>Ícone do departamento</span><small>Escolha apenas pelo símbolo.</small></div>' .
            role_icon_picker("role_icon[" . $role . "]", $ico, $role) .
            "</div></div></section>";
    }
    return $html . "</div>";
}
function profession_select_fields(array $cl = []): string
{
    /*
     * GUIA DE MANUTENÇÃO — profession_select_fields
     * Responsabilidade: Localiza, carrega ou resolve os dados de “profession select fields” para consumo pelas camadas superiores.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_signup`, `page_onboarding`, `page_settings`.
     * Dependências chamadas: `normalize_profession`, `select_label`, `clinical_profession_options`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function clinic_profession(int $clinicId): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_profession
     * Responsabilidade: Implementa a responsabilidade “clinic profession” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `val`, `trim`, `normalize_profession`, `db_schema_error_is_missing_table`, `stripos`, `->getMessage`, `error_log`, `function_exists`, `server_json_cache_remember`, `server_json_cache_safe_key`, `server_json_cache_ttl`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    if ($clinicId <= 0) {
        return "Profissional";
    }
    $loader = function () use ($clinicId): string {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Clinic/ClinicConfig.php:1159
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `val`, `trim`, `normalize_profession`, `db_schema_error_is_missing_table`, `stripos`, `->getMessage`, `error_log`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        try {
            $value = val(
                "SELECT responsible_profession FROM pi_clinics WHERE id=?",
                [$clinicId],
            );
            if ($value !== null && trim((string) $value) !== "") {
                return normalize_profession((string) $value);
            }
        } catch (Throwable $e) {
            if (
                !db_schema_error_is_missing_table($e) &&
                stripos($e->getMessage(), "Unknown column") === false
            ) {
                error_log("[Prontoo profession] " . $e->getMessage());
            }
        }
        return "Profissional";
    };
    if (function_exists("server_json_cache_remember")) {
        return (string) server_json_cache_remember(
            "clinic",
            server_json_cache_safe_key("profession", [$clinicId]),
            server_json_cache_ttl("clinic"),
            $loader,
            ["clinic:" . $clinicId],
        );
    }
    return $loader();
}
function admin_model_clinic_meta_key(): string
{
    /*
     * GUIA DE MANUTENÇÃO — admin_model_clinic_meta_key
     * Responsabilidade: Implementa a responsabilidade “admin model clinic meta key” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return "admin_global_exempt_clinic_id";
}
function admin_model_clinic_id(): int
{
    /*
     * GUIA DE MANUTENÇÃO — admin_model_clinic_id
     * Responsabilidade: Implementa a responsabilidade “admin model clinic id” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `platform_backend_selftest`, `page_admin_health`, `page_admin_integrity`.
     * Dependências chamadas: `.Core.Tenant.TenantRegistry::modelClinicId`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        return \Prontoo\Core\Tenant\TenantRegistry::modelClinicId();
    } catch (Throwable $e) {
        return 0;
    }
}
function clinic_is_global_admin_owned(int $clinicId): bool
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_is_global_admin_owned
     * Responsabilidade: Avalia ou impõe a regra “clinic is global admin owned”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_admin_clinics`.
     * Dependências chamadas: `safe_val`, `error_log`, `->getMessage`, `function_exists`, `server_json_cache_remember`, `server_json_cache_safe_key`, `server_json_cache_ttl`.
     * Efeitos colaterais: consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($clinicId <= 0) {
        return false;
    }
    $loader = function () use ($clinicId): bool {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Clinic/ClinicConfig.php:1206
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `safe_val`, `error_log`, `->getMessage`.
         * Efeitos colaterais: consulta dados persistidos; gera trilha de auditoria ou telemetria.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        try {
            return (int) safe_val(
                "SELECT COUNT(*) FROM pi_clinics c LEFT JOIN pi_users owner_user ON owner_user.id=c.owner_user_id LEFT JOIN pi_users manager_user ON manager_user.id=c.manager_user_id WHERE c.id=? AND (COALESCE(owner_user.is_global_admin,0)=1 OR COALESCE(manager_user.is_global_admin,0)=1)",
                [$clinicId],
                0,
            ) > 0;
        } catch (Throwable $e) {
            error_log("[Prontoo global admin clinic check] " . $e->getMessage());
            return false;
        }
    };
    if (function_exists("server_json_cache_remember")) {
        return (bool) server_json_cache_remember(
            "clinic",
            server_json_cache_safe_key("global_admin_owned", [$clinicId]),
            server_json_cache_ttl("clinic"),
            $loader,
            ["clinic:" . $clinicId],
        );
    }
    return $loader();
}
function ensure_global_admin_clinic_exempt(int $clinicId): void
{
    /*
     * GUIA DE MANUTENÇÃO — ensure_global_admin_clinic_exempt
     * Responsabilidade: Implementa a responsabilidade “ensure global admin clinic exempt” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return;
}
function admin_model_clinic_set(int $clinicId): void
{
    /*
     * GUIA DE MANUTENÇÃO — admin_model_clinic_set
     * Responsabilidade: Implementa a responsabilidade “admin model clinic set” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return;
}
function admin_model_clinic_exclude_sql(string $column = "clinic_id"): string
{
    /*
     * GUIA DE MANUTENÇÃO — admin_model_clinic_exclude_sql
     * Responsabilidade: Implementa a responsabilidade “admin model clinic exclude sql” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `admin_scope_guard_stats`, `admin_scope_guard_groups`, `platform_backend_selftest`, `admin_global_ops_finance_html`, `page_admin_health`, `page_admin_integrity`, `page_admin_painel`, `page_admin_clinics`.
     * Dependências chamadas: `.Core.Metrics.GlobalMetricScope::modelClinicSql`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        return \Prontoo\Core\Metrics\GlobalMetricScope::modelClinicSql($column);
    } catch (Throwable $e) {
        return "";
    }
}
function admin_model_clinic_exclude_where(string $column = "clinic_id"): string
{
    /*
     * GUIA DE MANUTENÇÃO — admin_model_clinic_exclude_where
     * Responsabilidade: Implementa a responsabilidade “admin model clinic exclude where” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `platform_backend_selftest`, `page_admin_health`, `page_admin_integrity`.
     * Dependências chamadas: `.Core.Metrics.GlobalMetricScope::modelClinicWhere`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        return \Prontoo\Core\Metrics\GlobalMetricScope::modelClinicWhere(
            $column,
        );
    } catch (Throwable $e) {
        return "1=1";
    }
}
function admin_model_clinic_count_note(): string
{
    /*
     * GUIA DE MANUTENÇÃO — admin_model_clinic_count_note
     * Responsabilidade: Implementa a responsabilidade “admin model clinic count note” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_admin_health`, `page_admin_integrity`.
     * Dependências chamadas: `.Core.Metrics.GlobalMetricScope::countNote`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return \Prontoo\Core\Metrics\GlobalMetricScope::countNote();
}
function open_incidents_count(): int
{
    /*
     * GUIA DE MANUTENÇÃO — open_incidents_count
     * Responsabilidade: Valida e executa a mutação “open incidents count”, preservando as invariantes do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `cached_val`.
     * Efeitos colaterais: consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        return (int) cached_val(
            "kpi_errors_open",
            60,
            "SELECT COUNT(*) FROM pi_error_events WHERE resolved_at IS NULL",
        );
    } catch (Throwable $e) {
        return 0;
    }
}
function clinic_metric_inc(?int $clinicId, string $metric, int $by = 1): void
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_metric_inc
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “clinic metric inc”.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`, `financial_notify_drawer_locked`, `financial_notify_opening_authorization_request`, `page_leads`, `maestro_create_action`, `page_patients`, `page_patient`, `save_team_member` e mais 5.
     * Dependências chamadas: `q`, `counter_key`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    if (!$clinicId) {
        return;
    }
    try {
        q(
            "INSERT INTO pi_clinic_daily_stats (clinic_id,day_date,metric_key,metric_value,updated_at) VALUES (?,CURDATE(),?,?,NOW()) ON DUPLICATE KEY UPDATE metric_value=metric_value+VALUES(metric_value), updated_at=NOW()",
            [$clinicId, counter_key($metric), $by],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo clinic_metric_inc] " . $e->getMessage());
    }
}
function clinic_read_only_db(int $cid): bool
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_read_only_db
     * Responsabilidade: Localiza, carrega ou resolve os dados de “clinic read only db” para consumo pelas camadas superiores.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `financial_ensure_default_accounts`, `financial_ensure_admin_safe`, `financial_cashier_requires_attention`, `maestro_run_rule_scoped`, `financial_cashier_requires_attention_light`.
     * Dependências chamadas: `array_key_exists`, `one`, `trim`, `function_exists`, `subscription_paid_is_active`, `app_date_only_end_timestamp`, `time`, `subscription_trial_is_active`, `app_storage_timestamp`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $cache = [];
    if ($cid <= 0) {
        return false;
    }
    if (array_key_exists($cid, $cache)) {
        return (bool) $cache[$cid];
    }
    try {
        $row = one(
            "SELECT subscription_status,paid_until,trial_ends_at FROM pi_clinics WHERE id=? LIMIT 1",
            [$cid],
        );
        if (!$row) {
            return $cache[$cid] = false;
        }
        $status = (string) ($row["subscription_status"] ?? "trial");
        if ($status === "exempt") {
            return $cache[$cid] = false;
        }
        if ($status === "read_only") {
            return $cache[$cid] = true;
        }
        if (
            $status === "active" &&
            trim((string) ($row["paid_until"] ?? "")) === ""
        ) {
            return $cache[$cid] = false;
        }
        $paidActive = function_exists("subscription_paid_is_active")
            ? subscription_paid_is_active($row["paid_until"] ?? null, $cid)
            : app_date_only_end_timestamp($row["paid_until"] ?? null, $cid) >= time();
        $trialActive =
            $status === "trial" &&
            (function_exists("subscription_trial_is_active")
                ? subscription_trial_is_active($row["trial_ends_at"] ?? null)
                : app_storage_timestamp($row["trial_ends_at"] ?? null, true) >=
                    time());
        return $cache[$cid] = !($paidActive || $trialActive);
    } catch (Throwable $e) {
        error_log("[Prontoo read-only db guard] " . $e->getMessage());
        return false;
    }
}
function clinic_context_required(?array $ctx = null): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_context_required
     * Responsabilidade: Implementa a responsabilidade “clinic context required” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_id_required`.
     * Dependências chamadas: `ctx`, `ProntooHttpError`.
     * Classes ou serviços instanciados: `ProntooHttpError`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ctx = $ctx ?: ctx();
    if (
        !$ctx ||
        ($ctx["scope"] ?? "") !== "clinic" ||
        (int) ($ctx["clinic_id"] ?? 0) <= 0
    ) {
        throw new ProntooHttpError(
            403,
            "Operação disponível apenas no contexto de um consultório.",
        );
    }
    return $ctx;
}
function clinic_id_required(?array $ctx = null): int
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_id_required
     * Responsabilidade: Implementa a responsabilidade “clinic id required” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `create_document_draft_from_template`.
     * Dependências chamadas: `clinic_context_required`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ctx = clinic_context_required($ctx);
    return (int) $ctx["clinic_id"];
}
function seed_clinic_roles(int $clinicId): void
{
    /*
     * GUIA DE MANUTENÇÃO — seed_clinic_roles
     * Responsabilidade: Implementa a responsabilidade “seed clinic roles” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_signup`, `page_onboarding`, `clinic_role_label_fields`, `clinic_role_sector_fields`, `clinic_roles`, `closure@app/Domain/Clinic/ClinicConfig.php:1377`, `clinic_role_icons`, `closure@app/Domain/Clinic/ClinicConfig.php:1422` e mais 2.
     * Dependências chamadas: `with_read_only_guard_disabled`, `in_array`, `q`, `default_role_icon`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    with_read_only_guard_disabled(static function () use ($clinicId): void {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Clinic/ClinicConfig.php:1352
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `in_array`, `q`, `default_role_icon`.
         * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados.
         * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
         */
        $position = 1;
        foreach (PRONTOO_ROLES as $code => $label) {
            $enabled = in_array($code, ["medico", "gerente"], true) ? 1 : 0;
            q(
                "INSERT INTO pi_clinic_roles (clinic_id,role_code,label,icon_name,enabled,sort_order) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE icon_name=IF(icon_name='',VALUES(icon_name),icon_name), sort_order=IF(sort_order IS NULL OR sort_order=0,VALUES(sort_order),sort_order)",
                [
                    $clinicId,
                    $code,
                    $label,
                    default_role_icon($code),
                    $enabled,
                    $position,
                ],
            );
            $position++;
        }
    });
}

function clinic_roles(int $clinicId, bool $enabledOnly = false): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_roles
     * Responsabilidade: Implementa a responsabilidade “clinic roles” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_onboarding`, `clinic_role_label_fields`, `clinic_role_sector_fields`, `clinic_role_options`, `role_label_for`.
     * Dependências chamadas: `q`, `->fetchAll`, `seed_clinic_roles`, `function_exists`, `server_json_cache_remember`, `server_json_cache_safe_key`, `server_json_cache_ttl`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($clinicId <= 0) {
        return $enabledOnly ? [] : PRONTOO_ROLES;
    }
    $loader = function () use ($clinicId, $enabledOnly): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Clinic/ClinicConfig.php:1377
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `q`, `->fetchAll`, `seed_clinic_roles`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $rows = q(
            "SELECT role_code,label,enabled FROM pi_clinic_roles WHERE clinic_id=? ORDER BY sort_order, role_code",
            [$clinicId],
        )->fetchAll();
        if (!$rows) {
            seed_clinic_roles($clinicId);
            $rows = q(
                "SELECT role_code,label,enabled FROM pi_clinic_roles WHERE clinic_id=? ORDER BY sort_order, role_code",
                [$clinicId],
            )->fetchAll();
        }
        $out = [];
        foreach ($rows as $r) {
            if (!$enabledOnly || (int) $r["enabled"]) {
                $out[(string) $r["role_code"]] = (string) $r["label"];
            }
        }
        foreach (PRONTOO_ROLES as $role => $label) {
            if (!isset($out[$role]) && !$enabledOnly) {
                $out[$role] = $label;
            }
        }
        return $out;
    };
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "clinic",
            server_json_cache_safe_key("roles", [$clinicId, $enabledOnly]),
            server_json_cache_ttl("clinic"),
            $loader,
            ["clinic:" . $clinicId, "table:pi_clinic_roles"],
        );
    }
    return $loader();
}
function clinic_role_icons(int $clinicId, bool $enabledOnly = false): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_role_icons
     * Responsabilidade: Implementa a responsabilidade “clinic role icons” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `role_icon_for`.
     * Dependências chamadas: `array_keys`, `default_role_icon`, `seed_clinic_roles`, `q`, `->fetchAll`, `error_log`, `->getMessage`, `role_icon_options`, `trim`, `function_exists`, `server_json_cache_remember`, `server_json_cache_safe_key` e mais 1.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $fallback = [];
    foreach (array_keys(PRONTOO_ROLES) as $role) {
        $fallback[$role] = default_role_icon($role);
    }
    if ($clinicId <= 0) {
        return $enabledOnly ? [] : $fallback;
    }
    $loader = function () use ($clinicId, $enabledOnly, $fallback): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Clinic/ClinicConfig.php:1422
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `seed_clinic_roles`, `q`, `->fetchAll`, `error_log`, `->getMessage`, `role_icon_options`, `trim`, `default_role_icon`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        seed_clinic_roles($clinicId);
        try {
            $rows = q(
                "SELECT role_code,icon_name,enabled FROM pi_clinic_roles WHERE clinic_id=? ORDER BY sort_order, role_code",
                [$clinicId],
            )->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo clinic role icons] " . $e->getMessage());
            return $enabledOnly ? [] : $fallback;
        }
        $out = [];
        foreach ($rows as $r) {
            $role = (string) ($r["role_code"] ?? "");
            if ($role === "" || ($enabledOnly && !(int) ($r["enabled"] ?? 0))) {
                continue;
            }
            $opts = role_icon_options($role);
            $iconName = trim((string) ($r["icon_name"] ?? ""));
            if ($iconName === "" || !isset($opts[$iconName])) {
                $iconName = default_role_icon($role);
            }
            $out[$role] = $iconName;
        }
        foreach ($fallback as $role => $iconName) {
            if (!isset($out[$role]) && !$enabledOnly) {
                $out[$role] = $iconName;
            }
        }
        return $out;
    };
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "clinic",
            server_json_cache_safe_key("role_icons", [$clinicId, $enabledOnly]),
            server_json_cache_ttl("clinic"),
            $loader,
            ["clinic:" . $clinicId, "table:pi_clinic_roles"],
        );
    }
    return $loader();
}
function clinic_role_options(int $clinicId, bool $enabledOnly = true): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_role_options
     * Responsabilidade: Implementa a responsabilidade “clinic role options” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_note_form_html`, `page_appointments`, `maestro_save_rule`, `page_maestro`, `maestro_create_action`, `workflow_valid_role`, `create_workflow_task`, `page_tasks`.
     * Dependências chamadas: `clinic_roles`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return clinic_roles($clinicId, $enabledOnly);
}
function role_label_for(string $role, ?int $clinicId = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — role_label_for
     * Responsabilidade: Monta a representação de interface associada a “role label for” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `onboarding_tip_copy`, `page_profile`, `agenda_note_form_html`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3468`, `activity_environment_label`, `audit_enrich_context`, `audit` e mais 20.
     * Dependências chamadas: `clinic_roles`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($clinicId && $clinicId > 0) {
        $roles = clinic_roles($clinicId, false);
        if (isset($roles[$role])) {
            return (string) $roles[$role];
        }
    }
    return PRONTOO_ROLES[$role] ?? $role;
}
function role_icon_for(string $role, ?int $clinicId = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — role_icon_for
     * Responsabilidade: Implementa a responsabilidade “role icon for” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `role_icon`.
     * Dependências chamadas: `clinic_role_icons`, `default_role_icon`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($clinicId && $clinicId > 0) {
        $icons = clinic_role_icons($clinicId, false);
        if (isset($icons[$role])) {
            return $icons[$role];
        }
    }
    return default_role_icon($role);
}
function is_responsible_doctor(array $c): bool
{
    /*
     * GUIA DE MANUTENÇÃO — is_responsible_doctor
     * Responsabilidade: Avalia ou impõe a regra “is responsible doctor”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_onboarding`, `onboarding_pending`.
     * Dependências chamadas: `one`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (($c["scope"] ?? "") !== "clinic") {
        return false;
    }
    return (bool) one(
        "SELECT id FROM pi_clinics WHERE id=? AND owner_user_id=?",
        [(int) $c["clinic_id"], (int) $c["user"]["id"]],
    );
}
function onboarding_pending(?array $c = null): bool
{
    /*
     * GUIA DE MANUTENÇÃO — onboarding_pending
     * Responsabilidade: Implementa a responsabilidade “onboarding pending” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `prontoo_run`.
     * Dependências chamadas: `ctx`, `is_responsible_doctor`, `val`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $c ??= ctx();
    if (!$c || ($c["scope"] ?? "") !== "clinic" || !is_responsible_doctor($c)) {
        return false;
    }
    return (int) val("SELECT onboarding_done FROM pi_clinics WHERE id=?", [
        (int) $c["clinic_id"],
    ]) !== 1;
}
function br_states(): array
{
    /*
     * GUIA DE MANUTENÇÃO — br_states
     * Responsabilidade: Implementa a responsabilidade “br states” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_signup`, `page_onboarding`, `clinic_location_fields`, `page_settings`, `patient_address_fields`, `patient_location_from_post`, `person_common_profile_fields_html`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function valid_timezone(string $tz): string
{
    /*
     * GUIA DE MANUTENÇÃO — valid_timezone
     * Responsabilidade: Implementa a responsabilidade “valid timezone” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `ctx`.
     * Dependências chamadas: `in_array`, `timezone_identifiers_list`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return in_array($tz, timezone_identifiers_list(), true)
        ? $tz
        : "America/Cuiaba";
}
function timezone_from_location(string $uf, string $city = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — timezone_from_location
     * Responsabilidade: Implementa a responsabilidade “timezone from location” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_signup`, `page_onboarding`, `clinic_location_fields`, `page_settings`.
     * Dependências chamadas: `strtoupper`, `trim`, `mb_strtolower`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function clinic_location_fields(array $cl = []): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_location_fields
     * Responsabilidade: Implementa a responsabilidade “clinic location fields” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_signup`, `page_onboarding`, `page_settings`.
     * Dependências chamadas: `timezone_from_location`, `e`, `br_states`, `form_row`, `input`, `select_label`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function role_icon(string $r, ?int $clinicId = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — role_icon
     * Responsabilidade: Implementa a responsabilidade “role icon” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: `page_profile`, `clinic_choice_card`, `page_permissions`, `page`, `page_head_icon_name`.
     * Dependências chamadas: `role_icon_for`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return role_icon_for($r, $clinicId) ?: "badge";
}
function clinic_choice_card(array $r): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_choice_card
     * Responsabilidade: Monta a representação de interface associada a “clinic choice card” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `trim`, `role_label_for`, `function_exists`, `role_icon`, `icon`, `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $place = trim(
        (string) ($r["address_city"] ?? "") .
            (!empty($r["address_state"]) ? " / " . $r["address_state"] : ""),
    );
    $clinic = trim((string) ($r["display_name"] ?? "Consultório"));
    $roleCode = (string) ($r["role_code"] ?? "");
    $role = role_label_for($roleCode, (int) $r["clinic_id"]);
    $ico = function_exists("role_icon")
        ? role_icon($roleCode, (int) $r["clinic_id"])
        : "badge";
    return '<button class="clinic-choice credential-choice" type="submit" name="clinic_role_id" value="' .
        (int) $r["id"] .
        '"><span class="credential-icon">' .
        icon($ico) .
        '</span><span class="credential-main"><span class="credential-role">' .
        e($role) .
        '</span><span class="credential-context"><span>' .
        e($clinic !== "" ? $clinic : "Consultório") .
        "</span><small>" .
        e($place !== "" ? $place : "Cidade não informada") .
        '</small></span></span><span class="credential-enter">' .
        icon("login") .
        "</span></button>";
}
function clinic_options_global(): array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_options_global
     * Responsabilidade: Implementa a responsabilidade “clinic options global” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `q`, `->fetchAll`, `function_exists`, `server_json_cache_remember`, `server_json_cache_safe_key`, `server_json_cache_ttl`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    $loader = function (): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Clinic/ClinicConfig.php:1656
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `q`, `->fetchAll`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $rows = q(
            "SELECT id,display_name FROM pi_clinics ORDER BY id DESC LIMIT 500",
        )->fetchAll();
        $options = ["" => "Sem consultório específico"];
        foreach ($rows as $row) {
            $options[(int) $row["id"]] = (string) $row["display_name"];
        }
        return $options;
    };
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "clinic",
            server_json_cache_safe_key("global_options", [PRONTOO_SCHEMA_REV]),
            server_json_cache_ttl("clinic"),
            $loader,
            ["table:pi_clinics"],
        );
    }
    return $loader();
}
function clinic_usage_icon(
    string $iconName,
    int $value,
    string $label,
    string $short,
): string {
    /*
     * GUIA DE MANUTENÇÃO — clinic_usage_icon
     * Responsabilidade: Implementa a responsabilidade “clinic usage icon” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/ClinicConfig.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `e`, `icon`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
