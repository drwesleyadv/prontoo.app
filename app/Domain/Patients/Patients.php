<?php
declare(strict_types=1);
require_once __DIR__ . '/PatientPure.php';

function patient_cpf_br(string $cpf): string
{
    $digits = function_exists('only_digits')
        ? only_digits($cpf)
        : preg_replace('/\D+/', '', $cpf);
    return \Prontoo\Domain\Patients\PatientPure::cpfBr($cpf, (string) $digits);
}
function normalize_patient_tab_icon(?string $icon): string
{

    $icon = preg_replace("/[^a-z0-9_]+/i", "", (string) $icon) ?: "";
    return array_key_exists($icon, patient_health_icon_options())
        ? $icon
        : "clinical_notes";
}
function patient_tab_label_clean(string $label): string
{
    return \Prontoo\Domain\Patients\PatientPure::cleanTabLabel($label);
}
function patient_tab_record_type(int $tabId): string
{
    return \Prontoo\Domain\Patients\PatientPure::tabRecordType($tabId);
}
function patient_tab_key(int $tabId): string
{
    return \Prontoo\Domain\Patients\PatientPure::tabKey($tabId);
}
function patient_tabs_ensure_schema(): void
{

    static $validated = false;
    if ($validated) {
        return;
    }
    if (!db_table_exists("pi_patient_tabs")) {
        throw new RuntimeException(
            "Schema incompleto: abas do paciente indisponíveis.",
        );
    }
    $validated = true;
}

function patient_extra_tabs(int $cid, int $patientId): array
{

    patient_tabs_ensure_schema();
    $rows = prontoo_patient_tab_active_rows($cid, $patientId);
    foreach ($rows as &$r) {
        $r["id"] = (int) $r["id"];
        $r["label"] = patient_tab_label_clean((string) ($r["label"] ?? ""));
        $r["icon_name"] = normalize_patient_tab_icon(
            (string) ($r["icon_name"] ?? ""),
        );
        $r["record_type"] = patient_tab_record_type((int) $r["id"]);
        $r["tab_key"] = patient_tab_key((int) $r["id"]);
    }
    unset($r);
    return $rows;
}
function patient_tab_map_by_type(array $tabs): array
{

    $out = [];
    foreach ($tabs as $t) {
        $out[(string) $t["record_type"]] = $t;
    }
    return $out;
}
function patient_tab_record_options(array $tabs): array
{

    $out = [];
    foreach ($tabs as $t) {
        $out[(string) $t["record_type"]] = $t["label"];
    }
    return $out;
}
function patient_record_type_label(string $type): string
{

    $type = trim($type);
    $base = [
        "note" => "Nota",
        "nota" => "Nota",
        "evolucao" => "Evolução",
        "evolution" => "Evolução",
        "triagem" => "Triagem",
        "anamnese" => "Anamnese",
        "exame" => "Exame",
        "conduta" => "Conduta",
        "retorno" => "Retorno",
        "orientacao" => "Orientação",
        "documento" => "Documento",
        "outro" => "Outro",
    ];
    if (isset($base[$type])) {
        return $base[$type];
    }
    if (str_starts_with($type, "tab_")) {
        $id = (int) substr($type, 4);
        if ($id > 0) {
            try {
                $label = prontoo_patient_tab_label_by_id($id);
                if (trim($label) !== "") {
                    return patient_tab_label_clean($label);
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo recoverable " .
                        __FUNCTION__ .
                        "] " .
                        $e->getMessage(),
                );
            }
        }
    }
    $fallback = trim(str_replace("_", " ", $type));
    return $fallback !== ""
        ? mb_convert_case($fallback, MB_CASE_TITLE, "UTF-8")
        : "Atividade";
}
function patient_tab_icon_picker(string $current = "clinical_notes"): string
{
    $current = normalize_patient_tab_icon($current);
    return prontoo_patient_tab_icon_picker(patient_health_icon_options(), $current);
}
function patient_guardians_ensure_schema(): void
{

    static $validated = false;
    if ($validated) {
        return;
    }
    if (!db_table_exists("pi_patient_guardians")) {
        throw new RuntimeException(
            "Schema incompleto: responsáveis do paciente indisponíveis.",
        );
    }
    $validated = true;
}

function patient_guardian_relationship_options(): array
{
    return \Prontoo\Domain\Patients\PatientPure::guardianRelationshipOptions();
}
function normalize_guardian_relationship(string $v): string
{
    return \Prontoo\Domain\Patients\PatientPure::normalizeGuardianRelationship($v);
}
function patient_age_years(null|string|int $birth): ?int
{
    return \Prontoo\Domain\Patients\PatientPure::ageYears($birth);
}
function patient_is_minor(array $p): bool
{
    return \Prontoo\Domain\Patients\PatientPure::isMinor($p);
}
function patient_identity_complete(array $p): bool
{

    return trim((string) ($p["full_name"] ?? "")) !== "" &&
        valid_cpf(only_digits((string) ($p["cpf"] ?? ""))) &&
        valid_birth_date((string) ($p["birth_date"] ?? ""));
}
function patient_invoice_registration_missing_fields(array $p): array
{

    $missing = [];
    if (trim((string) ($p["full_name"] ?? "")) === "") {
        $missing[] = "nome completo";
    }
    if (!valid_cpf(only_digits((string) ($p["cpf"] ?? "")))) {
        $missing[] = "CPF";
    }
    if (!valid_birth_date((string) ($p["birth_date"] ?? ""))) {
        $missing[] = "nascimento";
    }
    if (trim((string) ($p["phone"] ?? "")) === "") {
        $missing[] = "telefone";
    }
    $email = trim((string) ($p["email"] ?? ""));
    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $missing[] = "e-mail";
    }
    if (strlen(only_digits((string) ($p["address_zip"] ?? ""))) !== 8) {
        $missing[] = "CEP";
    }
    if (trim((string) ($p["address"] ?? "")) === "") {
        $missing[] = "logradouro";
    }
    if (trim((string) ($p["address_number"] ?? "")) === "") {
        $missing[] = "número";
    }
    if (trim((string) ($p["address_neighborhood"] ?? "")) === "") {
        $missing[] = "bairro";
    }
    if (trim((string) ($p["address_city"] ?? "")) === "") {
        $missing[] = "cidade";
    }
    if (trim((string) ($p["address_state"] ?? "")) === "") {
        $missing[] = "UF";
    }
    return array_values(array_unique($missing));
}
function patient_invoice_registration_complete(array $p): bool
{

    return patient_invoice_registration_missing_fields($p) === [];
}
function patient_invoice_registration_alert_message(array $p): string
{

    $missing = patient_invoice_registration_missing_fields($p);
    return "Atualização cadastral obrigatória antes de agendar a próxima consulta" .
        ($missing ? ": informe " . implode(", ", $missing) . "." : ".");
}
function patient_appointment_registration_block_reason(
    int $cid,
    int $patientId,
): ?string {
    return prontoo_patient_appointment_registration_block_reason($cid, $patientId);
}
function patient_legal_guardians(int $cid, int $patientId): array
{
    if ($cid <= 0 || $patientId <= 0) {
        return [];
    }
    patient_guardians_ensure_schema();
    return prontoo_patient_legal_guardians($cid, $patientId);
}
function patient_primary_legal_guardian(int $cid, int $patientId): ?array
{

    $g = patient_legal_guardians($cid, $patientId);
    return $g[0] ?? null;
}
function patient_has_legal_guardian(int $cid, int $patientId): bool
{
    if ($cid <= 0 || $patientId <= 0) {
        return false;
    }
    patient_guardians_ensure_schema();
    return prontoo_patient_has_legal_guardian($cid, $patientId);
}
function patient_profile_status(array $p, array $guardians): array
{

    if (!patient_identity_complete($p)) {
        return [
            "level" => "bad",
            "label" => "Ficha incompleta",
            "message" =>
                "CPF, Nome Completo e Nascimento são obrigatórios para considerar o cadastro mínimo válido.",
        ];
    }
    if (!patient_invoice_registration_complete($p)) {
        return [
            "level" => "warn",
            "label" => "Atualização obrigatória",
            "message" => patient_invoice_registration_alert_message($p),
        ];
    }
    if (patient_is_minor($p) && !$guardians) {
        return [
            "level" => "warn",
            "label" => "Ficha incompleta",
            "message" =>
                "Paciente menor de idade sem Responsável Legal vinculado. A ficha básica permanece salva, mas documentos e atos sensíveis ficam bloqueados até a regularização.",
        ];
    }
    if (!empty($p["registration_needs_update"])) {
        return [
            "level" => "warn",
            "label" => "Revisar cadastro",
            "message" =>
                "Cadastro recuperado ou pendente de revisão administrativa.",
        ];
    }
    if (patient_is_minor($p)) {
        return [
            "level" => "ok",
            "label" => "Menor com responsável",
            "message" => "Paciente menor com Responsável Legal ativo na ficha.",
        ];
    }
    return [
        "level" => "ok",
        "label" => "Ficha completa",
        "message" =>
            "Cadastro mínimo válido para atendimento e emissão fiscal.",
    ];
}
function patient_sensitive_block_reason(int $cid, int $patientId): ?string
{

    if ($cid <= 0 || $patientId <= 0) {
        return null;
    }
    $p = one(
        "SELECT pp.id,pp.clinic_id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,pp.registration_needs_update,p.full_name,p.cpf,p.birth_date FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1",
        [$patientId, $cid],
    );
    if (!$p) {
        return null;
    }
    if (!patient_identity_complete($p)) {
        return "A ficha do paciente está incompleta. Informe CPF, Nome Completo e Nascimento antes de emitir documentos.";
    }
    if (!patient_invoice_registration_complete($p)) {
        return "A ficha fiscal do paciente está incompleta. " .
            patient_invoice_registration_alert_message($p);
    }
    if (patient_is_minor($p) && !patient_has_legal_guardian($cid, $patientId)) {
        return "Paciente menor de idade sem Responsável Legal vinculado. Cadastre o responsável legal antes de emitir documentos ou concluir atos sensíveis.";
    }
    return null;
}
function patient_guardian_form_html(
    array $guardian = [],
    string $submit = "Salvar responsável legal",
): string {

    $gid = (int) ($guardian["id"] ?? 0);
    $rel = normalize_guardian_relationship(
        (string) ($guardian["relationship"] ?? "mae"),
    );
    $isPrimary =
        $gid <= 0 || (int) ($guardian["is_primary"] ?? 0) === 1
            ? " checked"
            : "";
    return '<form method="post" class="compact patient-guardian-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="save_legal_guardian"><input type="hidden" name="guardian_id" value="' .
        $gid .
        '">' .
        form_row(
            "Nome completo do responsável legal",
            input(
                "guardian_name",
                "text",
                $guardian["full_name"] ?? "",
                'required autocomplete="name"',
            ),
        ) .
        '<div class="two">' .
        form_row(
            "CPF do responsável",
            input(
                "guardian_cpf",
                "text",
                mask($guardian["cpf"] ?? ""),
                'required inputmode="numeric" data-cpf-mask',
            ),
        ) .
        select_label(
            "Vínculo jurídico",
            "guardian_relationship",
            patient_guardian_relationship_options(),
            $rel,
            "required",
        ) .
        '</div><div class="two">' .
        form_row(
            "Telefone",
            input(
                "guardian_phone",
                "text",
                $guardian["phone"] ?? "",
                'inputmode="tel"',
            ),
        ) .
        form_row(
            "E-mail",
            input("guardian_email", "email", $guardian["email"] ?? ""),
        ) .
        "</div>" .
        form_row(
            "Documento ou observação de comprovação",
            input(
                "guardian_document_note",
                "text",
                $guardian["document_note"] ?? "",
                'maxlength="180" placeholder="Ex.: certidão, guarda, tutela, autorização"',
            ),
        ) .
        form_row(
            "Observações sobre guarda, restrição ou autorização",
            textarea("guardian_notes", $guardian["notes"] ?? "", 'rows="4"'),
        ) .
        '<label class="checkline"><input type="checkbox" name="guardian_primary" value="1"' .
        $isPrimary .
        "><span>Marcar como responsável principal</span></label>" .
        form_actions($submit) .
        "</form>";
}
function patient_legal_guardian_card(
    int $cid,
    int $patientId,
    array $p,
    array $guardians,
    bool $canManage = true,
): string {

    $minor = patient_is_minor($p);
    if (!$minor && !$guardians) {
        return "";
    }
    $status = patient_profile_status($p, $guardians);
    $relOpts = patient_guardian_relationship_options();
    $html =
        '<section class="patient-guardian-card ' .
        ($minor && !$guardians ? "is-pending" : "is-ok") .
        '"><div class="patient-section-title"><div><h2>' .
        icon("supervisor_account") .
        "<span>Responsável Legal</span></h2><p>" .
        ($minor
            ? "Obrigatório para paciente menor de idade."
            : "Vínculo administrativo registrado na ficha.") .
        '</p></div><span class="pill ' .
        e($status["level"]) .
        '">' .
        e($status["label"]) .
        "</span></div>";
    if ($minor && !$guardians) {
        $html .=
            '<div class="flash warn"><strong>Ficha incompleta.</strong> CPF, Nome Completo e Nascimento já permitem manter o cadastro salvo; o Responsável Legal precisa ser vinculado para liberar documentos e atos sensíveis.</div>';
    }
    if ($guardians) {
        $html .= '<div class="guardian-list">';
        foreach ($guardians as $g) {
            $rel = $relOpts[(string) $g["relationship"]] ?? "Responsável";
            $meta = $rel . " · CPF " . mask((string) $g["cpf"]);
            if (!empty($g["phone"])) {
                $meta .= " · " . phone_br((string) $g["phone"]);
            }
            if (!empty($g["email"])) {
                $meta .= " · " . (string) $g["email"];
            }
            $html .=
                '<article class="guardian-row"><div class="guardian-main"><span class="guardian-avatar">' .
                icon("family_restroom") .
                "</span><div><strong>" .
                e($g["full_name"]) .
                "</strong><small>" .
                e($meta) .
                "</small>" .
                (!empty($g["document_note"])
                    ? "<small>Documento: " . e($g["document_note"]) . "</small>"
                    : "") .
                (!empty($g["notes"]) ? "<p>" . e($g["notes"]) . "</p>" : "") .
                '</div></div><div class="guardian-actions">' .
                ((int) $g["is_primary"] === 1
                    ? '<span class="pill ok">Principal</span>'
                    : "");
            if ($canManage) {
                $html .=
                    '<details class="guardian-edit"><summary class="ghost small cmdlike">' .
                    action_summary_label("Alterar", "edit") .
                    "</summary>" .
                    patient_guardian_form_html($g, "Salvar responsável") .
                    '</details><form method="post" class="inline" onsubmit="return confirm(\'Remover este responsável legal da ficha?\')">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="delete_legal_guardian"><input type="hidden" name="guardian_id" value="' .
                    (int) $g["id"] .
                    '"><button type="submit" class="danger small">Remover</button></form>';
            }
            $html .= "</div></article>";
        }
        $html .= "</div>";
    }
    if ($canManage) {
        $html .=
            '<details class="patient-guardian-new" ' .
            (!$guardians && $minor ? "open" : "") .
            '><summary class="primary small cmdlike">' .
            action_summary_label(
                $guardians
                    ? "Adicionar responsável"
                    : "Cadastrar responsável legal",
                "person_add",
            ) .
            "</summary>" .
            patient_guardian_form_html([], "Salvar responsável legal") .
            "</details>";
    }
    return $html . "</section>";
}
function require_patient_in_clinic(int $cid, int $patientId): array
{

    $row = require_same_clinic_entity(
        $cid,
        "pi_patients",
        $patientId,
        "id,person_id,active,deleted_at",
    );
    if ((int) ($row["active"] ?? 0) !== 1 || !empty($row["deleted_at"])) {
        throw new ProntooHttpError(
            403,
            "Paciente não está ativo neste consultório.",
        );
    }
    return $row;
}
function patient_location_defaults(int $cid, array $p = []): array
{

    $uf = trim((string) ($p["address_state"] ?? ""));
    $city = trim((string) ($p["address_city"] ?? ""));
    $ibge = trim((string) ($p["address_city_ibge"] ?? ""));
    if ($uf === "" && $cid > 0) {
        $cl =
            one(
                "SELECT address_state,address_city,address_city_ibge FROM pi_clinics WHERE id=?",
                [$cid],
            ) ?:
            [];
        $uf = trim((string) ($cl["address_state"] ?? "MT"));
        if ($city === "") {
            $city = trim((string) ($cl["address_city"] ?? ""));
        }
        if ($ibge === "") {
            $ibge = trim((string) ($cl["address_city_ibge"] ?? ""));
        }
    }
    if ($uf === "") {
        $uf = "MT";
    }
    return [
        "address_state" => strtoupper($uf),
        "address_city" => $city,
        "address_city_ibge" => $ibge,
    ];
}
function patient_zip_input(array $p = []): string
{

    return form_row(
        "CEP",
        input(
            "address_zip",
            "text",
            mask_cep((string) ($p["address_zip"] ?? "")),
            'required inputmode="numeric" maxlength="9" autocomplete="postal-code" data-patient-cep',
        ),
    );
}
function patient_address_fields(int $cid, array $p = []): string
{

    $loc = patient_location_defaults($cid, $p);
    $uf = (string) $loc["address_state"];
    $city = (string) $loc["address_city"];
    $cityIbge = (string) $loc["address_city_ibge"];
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
    return '<section class="patient-address-block" data-patient-address-block>' .
        patient_zip_input($p) .
        '<div class="two">' .
        form_row(
            "Logradouro",
            input(
                "address",
                "text",
                $p["address"] ?? "",
                'required maxlength="255" autocomplete="address-line1" data-patient-address-street',
            ),
        ) .
        form_row(
            "Número",
            input(
                "address_number",
                "text",
                $p["address_number"] ?? "",
                'required maxlength="20" autocomplete="address-line2" data-patient-address-number',
            ),
        ) .
        '</div><div class="two">' .
        form_row(
            "Bairro",
            input(
                "address_neighborhood",
                "text",
                $p["address_neighborhood"] ?? "",
                'required maxlength="120" data-patient-address-neighborhood',
            ),
        ) .
        form_row(
            "Complemento",
            input(
                "address_complement",
                "text",
                $p["address_complement"] ?? "",
                'maxlength="120" autocomplete="address-line3" data-patient-address-complement',
            ),
        ) .
        '</div><div class="two patient-location-fields">' .
        select_label(
            "Estado",
            "address_state",
            $states,
            $uf,
            "required data-br-state data-patient-address-state",
        ) .
        form_row(
            "Cidade",
            '<select name="address_city" required data-br-city data-selected-city="' .
                e($city) .
                '" data-patient-address-city>' .
                $cityOptions .
                '</select><input type="hidden" name="address_city_ibge" value="' .
                e($cityIbge) .
                '" data-br-city-ibge data-patient-address-ibge>',
        ) .
        "</div></section>";
}
function patient_location_fields(int $cid, array $p = []): string
{

    return patient_address_fields($cid, $p);
}
function patient_location_from_post(int $cid): array
{

    $zip = only_digits((string) ($_POST["address_zip"] ?? ""));
    $street = trim((string) ($_POST["address"] ?? ""));
    $number = trim((string) ($_POST["address_number"] ?? ""));
    $neighborhood = trim((string) ($_POST["address_neighborhood"] ?? ""));
    $complement = trim((string) ($_POST["address_complement"] ?? ""));
    $uf = strtoupper(trim((string) ($_POST["address_state"] ?? "")));
    $city = trim((string) ($_POST["address_city"] ?? ""));
    $cityIbge = (int) ($_POST["address_city_ibge"] ?? 0);
    if (strlen($zip) !== 8) {
        throw new RuntimeException("Informe um CEP válido.");
    }
    if ($street === "") {
        throw new RuntimeException("Informe o logradouro do paciente.");
    }
    if ($number === "") {
        throw new RuntimeException("Informe o número do endereço do paciente.");
    }
    if ($neighborhood === "") {
        throw new RuntimeException("Informe o bairro do paciente.");
    }
    if (!isset(br_states()[$uf])) {
        throw new RuntimeException("Escolha um Estado válido.");
    }
    if ($city === "" || $cityIbge <= 0) {
        throw new RuntimeException("Escolha uma cidade da lista do IBGE.");
    }
    return [
        "address_zip" => $zip,
        "address" => $street,
        "address_number" => $number,
        "address_neighborhood" => $neighborhood,
        "address_complement" => $complement !== "" ? $complement : null,
        "address_state" => $uf,
        "address_city" => $city,
        "address_city_ibge" => $cityIbge,
    ];
}
function mask_cep(string $cep): string
{

    $d = only_digits($cep);
    if (strlen($d) !== 8) {
        return $cep;
    }
    return substr($d, 0, 5) . "-" . substr($d, 5);
}
function patient_invoice_contact_from_post(): array
{

    $email = trim((string) ($_POST["email"] ?? ""));
    $phone = phone_br((string) ($_POST["phone"] ?? ""));
    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException(
            "Informe um e-mail válido para emissão fiscal.",
        );
    }
    if (trim($phone) === "") {
        throw new RuntimeException("Informe um telefone para contato fiscal.");
    }
    return ["email" => $email, "phone" => $phone];
}
function clinic_patient_exists(
    int $cid,
    int $patientId,
    bool $activeOnly = true,
): bool {

    if ($cid <= 0 || $patientId <= 0) {
        return false;
    }
    $sql =
        "SELECT id FROM pi_patients WHERE id=? AND clinic_id=?" .
        ($activeOnly ? " AND active=1 AND deleted_at IS NULL" : "") .
        " LIMIT 1";
    return (bool) one($sql, [$patientId, $cid]);
}
function patient_options(int $cid): array
{

    static $memo = [];
    if (isset($memo[$cid])) {
        return $memo[$cid];
    }
    $base = q(
        "SELECT id,person_id FROM pi_patients WHERE clinic_id=? AND active=1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 300",
        [$cid],
    )->fetchAll();
    $persons = fetch_map(
        "pi_persons",
        int_ids($base, "person_id"),
        "id,full_name",
    );
    $o = [];
    foreach ($base as $r) {
        $ps = $persons[(int) $r["person_id"]] ?? [];
        $o[(int) $r["id"]] = $ps["full_name"] ?? "Paciente #" . (int) $r["id"];
    }
    asort($o, SORT_NATURAL | SORT_FLAG_CASE);
    return $memo[$cid] = $o;
}
function patient_autosuggest_datalist(
    int $cid,
    string $id = "prontoo_patient_suggestions",
): string {

    $limit = max(0, min(80, (int) ($_GET["patient_preload"] ?? 0)));
    if ($limit <= 0) {
        return '<datalist id="' . e($id) . '"></datalist>';
    }
    $rows = q(
        "SELECT pp.id,p.full_name,p.birth_date,p.cpf FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1 AND pp.deleted_at IS NULL ORDER BY pp.updated_at DESC, pp.id DESC LIMIT " .
            $limit,
        [$cid],
    )->fetchAll();
    $h = '<datalist id="' . e($id) . '">';
    foreach ($rows as $r) {
        $birth = !empty($r["birth_date"])
            ? date_br((string) $r["birth_date"])
            : "Nascimento não informado";
        $value = (string) $r["full_name"] . " · " . $birth;
        $label = !empty($r["cpf"]) ? "CPF " . mask((string) $r["cpf"]) : $birth;
        $h .=
            '<option value="' .
            e($value) .
            '" label="' .
            e($label) .
            '" data-patient-id="' .
            (int) $r["id"] .
            '" data-patient-name="' .
            e((string) $r["full_name"]) .
            '" data-birth="' .
            e(app_date_input_from_storage($r["birth_date"] ?? "")) .
            '" data-cpf="' .
            e(mask((string) ($r["cpf"] ?? ""))) .
            '"></option>';
    }
    return $h . "</datalist>";
}
function patient_lookup_field(
    int $cid,
    string $hiddenName = "patient_link_id",
    string $hiddenValue = "",
    string $inputName = "patient_search",
): string {

    $display = "";
    $pid = (int) $hiddenValue;
    if ($pid > 0) {
        $r = one(
            "SELECT p.full_name,p.birth_date FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1 LIMIT 1",
            [$pid, $cid],
        );
        if ($r) {
            $display =
                (string) $r["full_name"] .
                (!empty($r["birth_date"])
                    ? " · " . date_br((string) $r["birth_date"])
                    : " · Nascimento não informado");
        }
    }
    return '<input type="hidden" name="' .
        e($hiddenName) .
        '" value="' .
        e($hiddenValue) .
        '" data-patient-id-target><input type="search" name="' .
        e($inputName) .
        '" value="' .
        e($display) .
        '" list="prontoo_patient_suggestions" placeholder="Busque por nome, CPF ou nascimento" autocomplete="off" spellcheck="false" data-ds-lookup="patient" aria-label="Buscar paciente por nome, CPF ou nascimento" data-patient-document-suggest data-patient-suggest-url="' .
        e(href("patient_suggest")) .
        '" aria-autocomplete="list">';
}
function posted_patient_search_value(): string
{

    foreach ($_POST as $k => $v) {
        if (is_string($k) && str_starts_with($k, "patient_search")) {
            return trim((string) $v);
        }
    }
    return trim((string) ($_POST["patient_search"] ?? ""));
}
function resolve_patient_lookup_id(
    int $cid,
    int $postedId,
    string $search = "",
): int {

    if ($postedId > 0) {
        $ok = (int) val(
            "SELECT id FROM pi_patients WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
            [$postedId, $cid],
        );
        if ($ok > 0) {
            return $ok;
        }
    }
    $search = trim($search);
    if ($search === "") {
        return 0;
    }
    $clean = mb_strtolower($search, "UTF-8");
    $rows = q(
        "SELECT pp.id,p.full_name,p.birth_date,p.cpf FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1 ORDER BY p.full_name ASC LIMIT 1000",
        [$cid],
    )->fetchAll();
    $exact = [];
    $nameExact = [];
    $starts = [];
    foreach ($rows as $r) {
        $birth = !empty($r["birth_date"])
            ? date_br((string) $r["birth_date"])
            : "Nascimento não informado";
        $display = mb_strtolower(
            (string) $r["full_name"] . " · " . $birth,
            "UTF-8",
        );
        $name = mb_strtolower((string) $r["full_name"], "UTF-8");
        $cpf = only_digits((string) ($r["cpf"] ?? ""));
        if (
            $display === $clean ||
            ($cpf !== "" && only_digits($search) === $cpf)
        ) {
            $exact[] = (int) $r["id"];
        }
        if ($name === $clean) {
            $nameExact[] = (int) $r["id"];
        }
        if ($search !== "" && str_starts_with($name, $clean)) {
            $starts[] = (int) $r["id"];
        }
    }
    if (count($exact) === 1) {
        return $exact[0];
    }
    if (count($nameExact) === 1) {
        return $nameExact[0];
    }
    if (count($starts) === 1) {
        return $starts[0];
    }
    return 0;
}
function patient_identity_by_cpf(string $cpf, int $cid): ?array
{

    $cpf = only_digits($cpf);
    if (!valid_cpf($cpf)) {
        return null;
    }
    try {
        $row = one(
            "SELECT p.id,p.full_name,p.cpf,p.birth_date
             FROM pi_persons p
             WHERE p.cpf=?
               AND (
                 EXISTS (SELECT 1 FROM pi_patients pat WHERE pat.person_id=p.id AND pat.clinic_id=?)
                 OR EXISTS (SELECT 1 FROM pi_leads l WHERE l.person_id=p.id AND l.clinic_id=?)
                 OR EXISTS (
                   SELECT 1
                   FROM pi_users u
                   JOIN pi_user_roles ur ON ur.user_id=u.id
                   WHERE u.person_id=p.id AND ur.clinic_id=?
                 )
               )
             LIMIT 1",
            [$cpf, $cid, $cid, $cid],
        );
        return $row ?: null;
    } catch (Throwable $e) {
        error_log("[Prontoo patient CPF identity lookup] " . $e->getMessage());
        return null;
    }
}
function patient_lookup_payload(int $cid, string $cpf): array
{

    $cpf = only_digits($cpf);
    if (!valid_cpf($cpf)) {
        return [
            "ok" => false,
            "found" => false,
            "message" => "Informe um CPF válido.",
        ];
    }
    $p = patient_identity_by_cpf($cpf, $cid);
    if (!$p) {
        return [
            "ok" => true,
            "found" => false,
            "message" => "CPF válido. Nenhum cadastro anterior encontrado.",
        ];
    }
    $active = null;
    $deleted = null;
    try {
        $active = one(
            "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=1 LIMIT 1",
            [$cid, (int) $p["id"]],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo patient CPF active lookup] " . $e->getMessage());
    }
    try {
        $deleted = one(
            "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=0 LIMIT 1",
            [$cid, (int) $p["id"]],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo patient CPF deleted lookup] " . $e->getMessage());
    }
    $birth = function_exists("app_date_input_from_storage")
        ? app_date_input_from_storage($p["birth_date"] ?? "")
        : (preg_match("/^\d{4}-\d{2}-\d{2}/", (string) ($p["birth_date"] ?? ""))
            ? substr((string) $p["birth_date"], 0, 10)
            : "");
    $name = trim((string) ($p["full_name"] ?? ""));
    return [
        "ok" => true,
        "found" => true,
        "already_patient" => (bool) $active,
        "deleted_patient" => (bool) $deleted,
        "patient_id" => $active
            ? (int) $active["id"]
            : ($deleted
                ? (int) $deleted["id"]
                : null),
        "open_url" => $active
            ? href("patient", ["id" => (int) $active["id"]])
            : "",
        "message" => $active
            ? "Este paciente já está cadastrado. Os dados foram recuperados; abra a ficha existente se quiser consultar ou alterar."
            : ($deleted
                ? "Cadastro anterior encontrado como excluído. Os dados foram recuperados; ao salvar, o Prontoo reativará a ficha para revisão."
                : "Dados encontrados e preenchidos automaticamente."),
        "name" => $name,
        "cpf" => patient_cpf_br((string) ($p["cpf"] ?? "")),
        "birth_date" => $birth,
    ];
}
function page_patient_lookup(): void
{

    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("X-Robots-Tag: noindex, nofollow");
    }
    try {
        $c = ctx();
        if (!$c) {
            http_response_code(401);
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" =>
                        "Sessão expirada. Entre novamente para verificar o CPF.",
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            return;
        }
        if (
            ($c["scope"] ?? "") !== "clinic" ||
            (int) ($c["clinic_id"] ?? 0) <= 0
        ) {
            http_response_code(403);
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" =>
                        "Busca de CPF disponível apenas dentro de um consultório.",
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            return;
        }
        if (!can("patients")) {
            http_response_code(403);
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" =>
                        "Sua credencial atual não permite verificar pacientes.",
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            return;
        }
        $cid = (int) $c["clinic_id"];
        $uid = (int) ($c["user"]["id"] ?? 0);
        if (
            $uid <= 0 ||
            security_rate_limit(
                "patient_lookup_c" . $cid . "_u" . $uid,
                6,
                60,
            )
        ) {
            if (!headers_sent()) {
                header("Retry-After: 60");
            }
            http_response_code(429);
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "rate_limited" => true,
                    "message" =>
                        "Limite de 6 consultas por minuto atingido. Aguarde um minuto antes de tentar novamente.",
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            return;
        }
        $cpf = only_digits((string) ($_GET["cpf"] ?? ""));
        echo json_encode(
            patient_lookup_payload($cid, $cpf),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        return;
    } catch (Throwable $e) {
        error_log(
            "[Prontoo patient_lookup endpoint] " .
                $e->getMessage() .
                " in " .
                $e->getFile() .
                ":" .
                $e->getLine(),
        );
        http_response_code(500);
        echo json_encode(
            [
                "ok" => false,
                "found" => false,
                "message" =>
                    "Não foi possível verificar o CPF agora. Tente novamente em instantes.",
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        return;
    }
}
function patient_directory_filter_options(): array
{

    return [
        "today" => "Hoje",
        "week" => "Essa semana",
        "dropouts" => "Desistentes",
        "incomplete" => "Cadastro Incompleto",
    ];
}
function patient_directory_filter_default(): string
{

    return "today";
}
function patient_directory_filter_icons(): array
{

    return [
        "today" => "today",
        "week" => "calendar_month",
        "dropouts" => "event_busy",
        "incomplete" => "fact_check",
    ];
}
function patient_directory_cancel_statuses(): array
{

    return ["cancelado", "nao_compareceu"];
}
function patient_week_utc_range(int $cid): array
{

    $today = app_today_in_timezone($cid);
    $zone = new DateTimeZone(app_context_timezone(null, $cid));
    $dt = new DateTimeImmutable($today . " 00:00:00", $zone);
    $weekday = (int) $dt->format("N");
    $start = $dt->modify("-" . ($weekday - 1) . " days");
    $end = $start->modify("+7 days");
    return [
        (string) $start->setTimezone(new DateTimeZone("UTC"))->getTimestamp(),
        (string) $end->setTimezone(new DateTimeZone("UTC"))->getTimestamp(),
    ];
}
function patient_today_utc_range(int $cid): array
{

    return app_local_day_utc_range(app_today_in_timezone($cid), $cid);
}
function patient_directory_filter_where(
    string $filter,
    array &$params,
    string $patientAlias = "pp",
    string $personAlias = "p",
): string {

    $filter = array_key_exists($filter, patient_directory_filter_options())
        ? $filter
        : patient_directory_filter_default();
    $cid = (int) ($params[0] ?? 0);
    if ($filter === "today") {
        [$start, $end] = patient_today_utc_range($cid);
        $params[] = $start;
        $params[] = $end;
        return " AND EXISTS (SELECT 1 FROM pi_appointments pa WHERE pa.clinic_id={$patientAlias}.clinic_id AND pa.patient_link_id={$patientAlias}.id AND pa.start_at>=? AND pa.start_at<? AND pa.status NOT IN ('cancelado','nao_compareceu'))";
    }
    if ($filter === "week") {
        [$start, $end] = patient_week_utc_range($cid);
        $params[] = $start;
        $params[] = $end;
        return " AND EXISTS (SELECT 1 FROM pi_appointments pa WHERE pa.clinic_id={$patientAlias}.clinic_id AND pa.patient_link_id={$patientAlias}.id AND pa.start_at>=? AND pa.start_at<? AND pa.status NOT IN ('cancelado','nao_compareceu'))";
    }
    if ($filter === "dropouts") {
        return " AND EXISTS (SELECT 1 FROM pi_appointments pa WHERE pa.clinic_id={$patientAlias}.clinic_id AND pa.patient_link_id={$patientAlias}.id AND pa.status IN ('cancelado','nao_compareceu') AND COALESCE(pa.updated_at,pa.created_at,pa.start_at)>=DATE_SUB(NOW(), INTERVAL 30 DAY))";
    }
    if ($filter === "incomplete") {
        return " AND (({$personAlias}.birth_date IS NOT NULL AND {$personAlias}.birth_date>DATE_SUB(CURDATE(), INTERVAL 18 YEAR) AND NOT EXISTS (SELECT 1 FROM pi_patient_guardians pg WHERE pg.clinic_id={$patientAlias}.clinic_id AND pg.patient_link_id={$patientAlias}.id AND pg.active=1)) OR COALESCE({$patientAlias}.phone,'')='' OR COALESCE({$patientAlias}.updated_at,{$patientAlias}.created_at,0)<DATE_SUB(NOW(), INTERVAL 180 DAY))";
    }
    return "";
}
function patient_directory_select_metrics_sql(int $cid): string
{

    [$todayStart, $todayEnd] = patient_today_utc_range($cid);
    return ",(SELECT MIN(pa.start_at) FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>=" .
        (int) $todayStart .
        " AND pa.start_at<" .
        (int) $todayEnd .
        " AND pa.status NOT IN ('cancelado','nao_compareceu')) today_appointment_start_at,(SELECT pa.status FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>=" .
        (int) $todayStart .
        " AND pa.start_at<" .
        (int) $todayEnd .
        " AND pa.status NOT IN ('cancelado','nao_compareceu') ORDER BY pa.start_at ASC LIMIT 1) today_appointment_status,(SELECT pa.arrived_at FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>=" .
        (int) $todayStart .
        " AND pa.start_at<" .
        (int) $todayEnd .
        " AND pa.status NOT IN ('cancelado','nao_compareceu') ORDER BY pa.start_at ASC LIMIT 1) today_appointment_arrived_at,(SELECT pa.consultation_started_at FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>=" .
        (int) $todayStart .
        " AND pa.start_at<" .
        (int) $todayEnd .
        " AND pa.status NOT IN ('cancelado','nao_compareceu') ORDER BY pa.start_at ASC LIMIT 1) today_appointment_started_at,(SELECT pa.consultation_finished_at FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at>=" .
        (int) $todayStart .
        " AND pa.start_at<" .
        (int) $todayEnd .
        " AND pa.status NOT IN ('cancelado','nao_compareceu') ORDER BY pa.start_at ASC LIMIT 1) today_appointment_finished_at,(SELECT MAX(COALESCE(pa.consultation_finished_at,pa.consultation_started_at,pa.start_at)) FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.start_at<=NOW() AND (pa.consultation_finished_at IS NOT NULL OR pa.consultation_started_at IS NOT NULL OR pa.status IN ('atendimento_concluido','finalizado','em_atendimento')) AND pa.status NOT IN ('cancelado','nao_compareceu')) last_consultation_at,(SELECT MAX(COALESCE(pa.updated_at,pa.created_at,pa.start_at)) FROM pi_appointments pa WHERE pa.clinic_id=pp.clinic_id AND pa.patient_link_id=pp.id AND pa.status IN ('cancelado','nao_compareceu')) dropout_at";
}
function patient_directory_order_sql(string $filter): string
{

    $filter = array_key_exists($filter, patient_directory_filter_options())
        ? $filter
        : patient_directory_filter_default();
    return match ($filter) {
        "today"
            => "today_appointment_start_at ASC, p.full_name ASC, pp.id DESC",
        "week" => "p.full_name ASC, pp.id DESC",
        "dropouts" => "dropout_at DESC, p.full_name ASC, pp.id DESC",
        "incomplete" => "p.full_name ASC, pp.id DESC",
        default => "p.full_name ASC, pp.id DESC",
    };
}
function patient_directory_status(array $r): array
{

    $guardians = (int) ($r["guardian_count"] ?? 0) > 0 ? [["id" => 1]] : [];
    $status = patient_profile_status($r, $guardians);
    $level = in_array(
        (string) ($status["level"] ?? ""),
        ["ok", "warn", "bad"],
        true,
    )
        ? (string) $status["level"]
        : "ok";
    return [
        $level,
        (string) ($status["label"] ?? "Ficha completa"),
        (string) ($status["message"] ?? ""),
    ];
}
function patient_directory_card(array $r, int $cid = 0): string
{

    $name = (string) ($r["full_name"] ?? "Paciente #" . ($r["id"] ?? ""));
    $birth = (string) ($r["birth_date"] ?? "");
    $birthLabel = $birth !== "" ? date_br($birth) : "Nascimento não informado";
    $age = patient_age_years($birth);
    $ageLabel = $age !== null ? $age . " anos" : "Idade não informada";
    $cpf = mask((string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? ""));
    if (trim($cpf) === "") {
        $cpf = "CPF não informado";
    }
    $phone = phone_br((string) ($r["phone"] ?? ""));
    if (trim($phone) === "") {
        $phone = "Sem telefone";
    }
    [$level, $label, $message] = patient_directory_status($r);
    $timeRaw = trim((string) ($r["today_appointment_start_at"] ?? ""));
    $timePill = "";
    $timeLabel = "";
    $journeyHtml = "";
    $journeySearch = "";
    if ($timeRaw !== "" && $timeRaw !== "0") {
        $timeLabel = app_time_br($timeRaw, $cid);
        $apptMini = [
            "status" => (string) ($r["today_appointment_status"] ?? "agendado"),
            "start_at" => $timeRaw,
            "arrived_at" => $r["today_appointment_arrived_at"] ?? null,
            "consultation_started_at" =>
                $r["today_appointment_started_at"] ?? null,
            "consultation_finished_at" =>
                $r["today_appointment_finished_at"] ?? null,
        ];
        $role = (string) (ctx()["role"] ?? "" ?: "");
        $jm = appointment_journey_meta($apptMini, $role);
        $journeySearch =
            $jm["label"] .
            " " .
            $jm["phase_label"] .
            " " .
            $jm["owner"] .
            " " .
            $jm["action"];
        if ($timeLabel !== "" && $timeLabel !== "--:--") {
            $timePill =
                '<span class="patient-schedule-pill" title="Horário do agendamento de hoje">' .
                icon("schedule") .
                "<span>" .
                e($timeLabel) .
                '</span></span><span class="pill ' .
                e($jm["class"]) .
                ' patient-journey-pill">' .
                icon($jm["icon"]) .
                e($jm["label"]) .
                "</span>";
        }
        $journeyHtml = appointment_journey_compact_html($apptMini, $role);
    }
    $searchData =
        $timeLabel .
        " " .
        $journeySearch .
        " " .
        $name .
        " " .
        $birthLabel .
        " " .
        $ageLabel .
        " " .
        $cpf .
        " " .
        $phone .
        " " .
        (string) ($r["email"] ?? "") .
        " " .
        $label;
    $statusTitle = $message !== "" ? ' title="' . e($message) . '"' : "";
    $created = !empty($r["created_at"]) ? dt_br((string) $r["created_at"]) : "";
    $createdHtml =
        $created !== ""
            ? '<span class="patient-card-created">' .
                icon("schedule") .
                "<span>Cadastrado em " .
                e($created) .
                "</span></span>"
            : "";
    $lastRaw = trim((string) ($r["last_consultation_at"] ?? ""));
    $lastHtml = "";
    if ($lastRaw !== "" && $lastRaw !== "0") {
        $lastLabel = function_exists("app_date_br")
            ? app_date_br($lastRaw, $cid)
            : date_br($lastRaw);
        if ($lastLabel !== "" && $lastLabel !== "—") {
            $lastHtml =
                '<span class="patient-card-last-consultation" title="Última consulta registrada">' .
                icon("stethoscope") .
                "<span>Última consulta: " .
                e($lastLabel) .
                "</span></span>";
        }
    }
    return '<article class="patient-card-row ds-person-row ds-patient-row patient-status-' .
        e($level) .
        '" data-patient-row data-patient-search="' .
        e($searchData) .
        '">' .
        '<span class="patient-card-avatar ds-person-avatar" aria-hidden="true">' .
        icon("personal_injury") .
        "</span>" .
        '<div class="patient-card-main ds-person-main"><div class="patient-card-title ds-person-title">' .
        $timePill .
        "<strong>" .
        e($name) .
        '</strong><span class="pill patient-status-pill ds-status-pill ' .
        e($level) .
        '"' .
        $statusTitle .
        ">" .
        e($label) .
        "</span></div>" .
        '<div class="patient-card-meta ds-person-meta"><span>' .
        icon("cake") .
        "<span>" .
        e($birthLabel) .
        " · " .
        e($ageLabel) .
        "</span></span><span>" .
        icon("badge") .
        "<span>" .
        e($cpf) .
        "</span></span><span>" .
        icon("call") .
        "<span>" .
        e($phone) .
        "</span></span>" .
        $lastHtml .
        $createdHtml .
        $journeyHtml .
        "</div></div>" .
        '<div class="patient-card-actions ds-person-actions"><a class="primary small" href="' .
        href("patient", ["id" => (int) $r["id"]]) .
        '">' .
        icon("folder_open") .
        "<span>Abrir ficha</span></a></div>" .
        "</article>";
}
function patient_profile_overview(
    array $p,
    array $profileStatus,
    int $appointmentCount,
    int $documentCount,
    int $totalConsultations,
    string $firstConsultationLabel,
): string {

    $name = trim((string) ($p["full_name"] ?? "Paciente"));
    $birth = trim((string) ($p["birth_date"] ?? ""));
    $age = patient_age_years($birth);
    $birthLabel = $birth !== "" ? date_br($birth) : "Nascimento não informado";
    if ($age !== null) {
        $birthLabel .= " · " . $age . " anos";
    }
    $cpfLabel = mask((string) ($p["cpf"] ?? ""));
    if (trim($cpfLabel) === "") {
        $cpfLabel = "CPF não informado";
    }
    $phoneLabel = phone_br((string) ($p["phone"] ?? ""));
    if (trim($phoneLabel) === "") {
        $phoneLabel = "Telefone não informado";
    }
    $emailLabel = trim((string) ($p["email"] ?? ""));
    if ($emailLabel === "") {
        $emailLabel = "E-mail não informado";
    }
    $cityLabel = city_state_label(
        $p["address_city"] ?? "",
        $p["address_state"] ?? "",
    );
    if (trim($cityLabel) === "") {
        $cityLabel = "Cidade não informada";
    }
    $updated = !empty($p["updated_at"])
        ? dt_card_full_br((string) $p["updated_at"])
        : dt_card_full_br((string) ($p["created_at"] ?? ""));
    $level = (string) ($profileStatus["level"] ?? "ok");
    $label = (string) ($profileStatus["label"] ?? "Cadastro");
    $message = (string) ($profileStatus["message"] ?? "");
    $statusTitle = $message !== "" ? ' title="' . e($message) . '"' : "";
    return '<section class="patient-profile-overview ds-patient-profile" aria-label="Resumo da ficha do paciente">' .
        '<div class="patient-profile-id">' .
        '<span class="patient-profile-avatar" aria-hidden="true">' .
        icon("personal_injury") .
        "</span>" .
        '<div class="patient-profile-copy"><span class="eyebrow">Ficha do paciente</span><h2>' .
        e($name) .
        "</h2><p>" .
        e($birthLabel) .
        " · " .
        e($cpfLabel) .
        "</p></div>" .
        '<span class="pill patient-profile-status ' .
        e($level) .
        '"' .
        $statusTitle .
        ">" .
        e($label) .
        "</span>" .
        "</div>" .
        '<div class="patient-profile-facts" aria-label="Dados principais">' .
        '<span class="patient-profile-fact">' .
        icon("call") .
        "<small>Telefone</small><b>" .
        e($phoneLabel) .
        "</b></span>" .
        '<span class="patient-profile-fact">' .
        icon("mail") .
        "<small>E-mail</small><b>" .
        e($emailLabel) .
        "</b></span>" .
        '<span class="patient-profile-fact">' .
        icon("location_on") .
        "<small>Cidade</small><b>" .
        e($cityLabel) .
        "</b></span>" .
        '<span class="patient-profile-fact">' .
        icon("update") .
        "<small>Atualização</small><b>" .
        e($updated) .
        "</b></span>" .
        "</div>" .
        '<div class="patient-profile-metrics" aria-label="Resumo operacional">' .
        '<span class="patient-profile-metric"><small>Agendamentos</small><b>' .
        (int) $appointmentCount .
        "</b></span>" .
        '<span class="patient-profile-metric"><small>Documentos</small><b>' .
        (int) $documentCount .
        "</b></span>" .
        '<span class="patient-profile-metric"><small>Consultas</small><b>' .
        (int) $totalConsultations .
        "</b></span>" .
        '<span class="patient-profile-metric is-wide"><small>Primeira consulta</small><b>' .
        e($firstConsultationLabel) .
        "</b></span>" .
        "</div>" .
        "</section>";
}
function patient_reception_story_time(null|string|int $value): string
{

    $raw = trim((string) ($value ?? ""));
    if ($raw === "") {
        return "data não informada";
    }
    $dt = null;
    try {
        if (function_exists("app_db_utc_to_local")) {
            $dt = app_db_utc_to_local($raw);
        }
    } catch (Throwable $e) {
        $dt = null;
    }
    if (!$dt) {
        try {
            $dt = preg_match('/^-?\d+$/', $raw)
                ? new DateTimeImmutable("@" . (int) $raw)->setTimezone(
                    new DateTimeZone(date_default_timezone_get() ?: "UTC"),
                )
                : new DateTimeImmutable($raw);
        } catch (Throwable $e) {
            $dt = null;
        }
    }
    if (!$dt) {
        return $raw;
    }
    $months = function_exists("prontoo_months_br")
        ? prontoo_months_br()
        : [
            1 => "Janeiro",
            2 => "Fevereiro",
            3 => "Março",
            4 => "Abril",
            5 => "Maio",
            6 => "Junho",
            7 => "Julho",
            8 => "Agosto",
            9 => "Setembro",
            10 => "Outubro",
            11 => "Novembro",
            12 => "Dezembro",
        ];
    $month = $months[(int) $dt->format("n")] ?? $dt->format("m");
    return $dt->format("d") . " de " . $month . ", às " . $dt->format("H\hi");
}
function patient_reception_story_title(
    string $who,
    null|string|int $createdAt,
): string {

    $who = trim($who);
    if ($who === "") {
        $who = "Recepção";
    }
    return "Falou com " .
        $who .
        " em " .
        patient_reception_story_time($createdAt) .
        ".";
}
function patient_reception_meta_chips_html(array $parts): string
{

    $icons = [
        "Telefone" => "call",
        "Origem" => "conversion_path",
        "Interesse" => "interests",
        "Etapa" => "route",
    ];
    $classes = [
        "Telefone" => "phone",
        "Origem" => "source",
        "Interesse" => "interest",
        "Etapa" => "stage",
    ];
    $h = "";
    foreach ($parts as $part) {
        if (!is_array($part)) {
            continue;
        }
        $label = trim((string) ($part["label"] ?? ""));
        $value = trim((string) ($part["value"] ?? ""));
        if ($label === "" || $value === "") {
            continue;
        }
        $iconName = $icons[$label] ?? "label";
        $class = preg_replace(
            "/[^a-z0-9_-]/i",
            "",
            (string) ($classes[$label] ?? strtolower($label)),
        );
        $h .=
            '<span class="patient-reception-tag patient-reception-tag-' .
            e($class) .
            '" aria-label="' .
            e($label . ": " . $value) .
            '" title="' .
            e($label . ": " . $value) .
            '">' .
            icon($iconName) .
            "<b>" .
            e($value) .
            "</b></span>";
    }
    return $h !== ""
        ? '<div class="patient-reception-tags" aria-label="Detalhes do atendimento">' .
                $h .
                "</div>"
        : "";
}
function patient_reception_history_items(
    int $cid,
    int $patientId,
    array $p,
): array {

    if ($cid <= 0 || $patientId <= 0) {
        return [];
    }
    if (function_exists("ensure_lead_events_schema")) {
        ensure_lead_events_schema();
    }
    $personId = (int) ($p["person_id"] ?? 0);
    $phoneDigits = substr(only_digits((string) ($p["phone"] ?? "")), 0, 11);
    try {
        $readModel = prontoo_patient_reception_history_read_model(
            $cid,
            $patientId,
            $personId,
            $phoneDigits,
        );
    } catch (Throwable $e) {
        error_log("[Prontoo patient reception history] " . $e->getMessage());
        return [];
    }
    $leads = (array) ($readModel["leads"] ?? []);
    $events = (array) ($readModel["events"] ?? []);
    $users = (array) ($readModel["users"] ?? []);
    if (!$leads) {
        return [];
    }
    $items = [];
    foreach ($leads as $lead) {
        $lid = (int) $lead["id"];
        $leadEvents = $events[$lid] ?? [];
        if (!$leadEvents) {
            $who = first_name(
                $users[(int) ($lead["created_by"] ?? 0)]["name"] ?? "Recepção",
            );
            $body = trim((string) ($lead["notes"] ?? ""));
            if ($body === "") {
                $body = "Contato registrado sem observação adicional.";
            }
            $metaParts = [];
            foreach (
                [
                    "phone" => "Telefone",
                    "source" => "Origem",
                    "interest" => "Interesse",
                ]
                as $k => $label
            ) {
                $v = trim((string) ($lead[$k] ?? ""));
                if ($v !== "") {
                    if ($k === "phone" && function_exists("phone_br")) {
                        $v = phone_br($v);
                    }
                    $metaParts[] = ["label" => $label, "value" => $v];
                }
            }
            $stage = trim((string) ($lead["stage"] ?? ""));
            if ($stage !== "") {
                $metaParts[] = ["label" => "Etapa", "value" => $stage];
            }
            $items[] = [
                "icon" => "forum",
                "time" => "",
                "title" => patient_reception_story_title(
                    $who,
                    (string) ($lead["created_at"] ?? ""),
                ),
                "body" => $body,
                "meta" => "",
                "html" => patient_reception_meta_chips_html($metaParts),
                "class" => "patient-reception-event patient-reception-story",
            ];
            continue;
        }
        foreach ($leadEvents as $ev) {
            $who = first_name(
                $users[(int) ($ev["created_by"] ?? 0)]["name"] ?? "Recepção",
            );
            $body = trim((string) ($ev["body"] ?? ""));
            if ($body === "") {
                $body = "Contato registrado sem observação adicional.";
            }
            $stageFrom = trim((string) ($ev["stage_from"] ?? ""));
            $stageTo = trim((string) ($ev["stage_to"] ?? ""));
            $stage =
                $stageFrom !== "" || $stageTo !== ""
                    ? trim($stageFrom . " → " . $stageTo, " →")
                    : "";
            $metaParts = [];
            foreach (
                [
                    "phone" => "Telefone",
                    "source" => "Origem",
                    "interest" => "Interesse",
                ]
                as $k => $label
            ) {
                $v = trim((string) ($ev[$k] ?? ""));
                if ($v !== "") {
                    if ($k === "phone" && function_exists("phone_br")) {
                        $v = phone_br($v);
                    }
                    $metaParts[] = ["label" => $label, "value" => $v];
                }
            }
            if ($stage !== "") {
                $metaParts[] = ["label" => "Etapa", "value" => $stage];
            }
            $items[] = [
                "icon" => "forum",
                "time" => "",
                "title" => patient_reception_story_title(
                    $who,
                    (string) ($ev["created_at"] ?? ""),
                ),
                "body" => $body,
                "meta" => "",
                "html" => patient_reception_meta_chips_html($metaParts),
                "class" => "patient-reception-event patient-reception-story",
            ];
        }
    }
    return $items;
}
function patient_reception_history_panel(array $items): string
{

    $count = count($items);
    return '<section class="patient-panel patient-panel-atendimentos" role="tabpanel"><div class="patient-section-title"><div><h2>Atendimentos</h2><p>Histórico de ocorrências registradas pela recepção antes ou durante o vínculo com o paciente.</p></div><span>' .
        (int) $count .
        ' ocorrência(s)</span></div><div class="patient-reception-history">' .
        ($items
            ? timeline($items, "")
            : '<div class="empty patient-empty-cta"><span class="empty-icon">' .
                icon("forum") .
                "</span><h3>Sem ocorrências registradas.</h3><p>Paciente cadastrado diretamente ou ainda sem contato registrado pela recepção. Quando o telefone aparecer em novo contato, a ficha será localizada automaticamente.</p></div>") .
        "</div></section>";
}
function page_patient_suggest(): void
{

    $c = need_login();
    if (($c["scope"] ?? "") !== "clinic") {
        throw new ProntooHttpError(
            403,
            "Busca disponível apenas no consultório.",
        );
    }
    if (!can("patients") && !can("documents")) {
        throw new ProntooHttpError(403, "Sem permissão para buscar pacientes.");
    }
    $cid = (int) $c["clinic_id"];
    $q = trim((string) ($_GET["q"] ?? ""));
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    if ($q === "") {
        echo json_encode(["ok" => true, "items" => []], JSON_UNESCAPED_UNICODE);
        return;
    }
    $limit = max(1, min(80, (int) ($_GET["limit"] ?? 12)));
    $digits = only_digits($q);
    $params = [$cid];
    $where = "pp.clinic_id=? AND pp.active=1 AND pp.deleted_at IS NULL";
    $like = "%" . $q . "%";
    $dateLike = "%" . str_replace("/", "-", $q) . "%";
    if ($digits !== "") {
        $where .=
            " AND (p.full_name LIKE ? OR p.cpf LIKE ? OR pp.phone LIKE ? OR DATE_FORMAT(p.birth_date,'%d/%m/%Y') LIKE ? OR p.birth_date LIKE ?)";
        $params[] = $like;
        $params[] = "%" . $digits . "%";
        $params[] = "%" . $digits . "%";
        $params[] = $like;
        $params[] = $dateLike;
    } else {
        $where .=
            " AND (p.full_name LIKE ? OR pp.email LIKE ? OR DATE_FORMAT(p.birth_date,'%d/%m/%Y') LIKE ? OR p.birth_date LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $dateLike;
    }
    $metrics = patient_directory_select_metrics_sql($cid);
    $order = "p.full_name ASC, pp.id DESC";
    $rows = q(
        "SELECT pp.id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,pp.created_at,pp.updated_at,pp.registration_needs_update,p.full_name,p.birth_date,p.cpf,(SELECT COUNT(*) FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1) guardian_count $metrics FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE $where ORDER BY $order LIMIT " .
            (int) $limit,
        $params,
    )->fetchAll();
    $items = [];
    foreach ($rows as $r) {
        $birth = !empty($r["birth_date"])
            ? date_br((string) $r["birth_date"])
            : "Nascimento não informado";
        $age = patient_age_years((string) ($r["birth_date"] ?? ""));
        [$level, $label, $message] = patient_directory_status($r);
        $timeRaw = trim((string) ($r["today_appointment_start_at"] ?? ""));
        $timeLabel =
            $timeRaw !== "" && $timeRaw !== "0"
                ? app_time_br($timeRaw, $cid)
                : "";
        $lastRaw = trim((string) ($r["last_consultation_at"] ?? ""));
        $lastLabel =
            $lastRaw !== "" && $lastRaw !== "0"
                ? (function_exists("app_date_br")
                    ? app_date_br($lastRaw, $cid)
                    : date_br($lastRaw))
                : "";
        if ($lastLabel === "—") {
            $lastLabel = "";
        }
        $items[] = [
            "id" => (int) $r["id"],
            "name" => (string) $r["full_name"],
            "birth" => $birth,
            "birth_raw" => (string) ($r["birth_date"] ?? ""),
            "age" => $age !== null ? $age . " anos" : "Idade não informada",
            "cpf" => mask((string) ($r["cpf"] ?? "")),
            "phone" => phone_br((string) ($r["phone"] ?? "")) ?: "Sem telefone",
            "status_level" => $level,
            "status_label" => $label,
            "status_message" => $message,
            "today_appointment_time" => $timeLabel,
            "last_consultation_date" => $lastLabel,
            "value" => (string) $r["full_name"] . " · " . $birth,
            "open_url" => href("patient", ["id" => (int) $r["id"]]),
        ];
    }
    echo json_encode(["ok" => true, "items" => $items], JSON_UNESCAPED_UNICODE);
    return;
}
function page_patients(): void
{

    $c = require_can("patients");
    $cid = (int) $c["clinic_id"];
    patient_guardians_ensure_schema();
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $cpf = only_digits((string) ($_POST["cpf"] ?? ""));
        $birth = (string) ($_POST["birth_date"] ?? "");
        $name = trim((string) ($_POST["name"] ?? ""));
        if (valid_cpf($cpf) && ($name === "" || !valid_birth_date($birth))) {
            $identity = patient_identity_by_cpf($cpf, $cid);
            if ($identity) {
                if ($name === "") {
                    $name = trim((string) ($identity["full_name"] ?? ""));
                }
                if (!valid_birth_date($birth)) {
                    $birth = app_date_input_from_storage(
                        $identity["birth_date"] ?? "",
                    );
                }
            }
        }
        if ($name === "") {
            flash("Informe o nome completo do paciente.", "bad");
            redirect("patients");
        }
        if (!valid_cpf($cpf)) {
            flash("Este CPF não existe.", "bad");
            redirect("patients");
        }
        if (!valid_birth_date($birth)) {
            flash("Informe nascimento plausível do paciente.", "bad");
            redirect("patients");
        }
        try {
            $existingPerson = (int) val(
                "SELECT id FROM pi_persons WHERE cpf=? LIMIT 1",
                [$cpf],
            );
            if ($existingPerson > 0) {
                $active = one(
                    "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=1 LIMIT 1",
                    [$cid, $existingPerson],
                );
                if ($active) {
                    flash(
                        "Este paciente já está cadastrado. Deseja abrir o cadastro dele?",
                        "warn",
                    );
                    redirect("patients", [
                        "existing_patient" => (int) $active["id"],
                    ]);
                }
                $deleted = one(
                    "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=0 LIMIT 1",
                    [$cid, $existingPerson],
                );
                if ($deleted) {
                    q(
                        "UPDATE pi_patients SET active=1,registration_needs_update=1,deleted_at=NULL,deleted_by=NULL,restored_at=NOW(),restored_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?",
                        [(int) $c["user"]["id"], (int) $deleted["id"], $cid],
                    );
                    audit(
                        "paciente_recuperado",
                        "paciente",
                        (int) $deleted["id"],
                        [
                            "patient_name" => $name,
                            "campos" => ["Recuperação de cadastro"],
                            "audit_body" =>
                                "Cadastro anteriormente excluído foi recuperado com os dados já existentes no banco. Atualização cadastral solicitada.",
                        ],
                    );
                    flash(
                        "Paciente recuperado com os dados já existentes. Revise e atualize o cadastro.",
                        "warn",
                    );
                    redirect("patient", ["id" => (int) $deleted["id"]]);
                }
            }
            $pid = upsert_person($name, $cpf, $birth);
            $loc = patient_location_from_post($cid);
            $contact = patient_invoice_contact_from_post();
            q(
                "INSERT INTO pi_patients (clinic_id,person_id,phone,email,address,address_zip,address_number,address_neighborhood,address_complement,address_state,address_city,address_city_ibge,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE phone=VALUES(phone),email=VALUES(email),address=VALUES(address),address_zip=VALUES(address_zip),address_number=VALUES(address_number),address_neighborhood=VALUES(address_neighborhood),address_complement=VALUES(address_complement),address_state=VALUES(address_state),address_city=VALUES(address_city),address_city_ibge=VALUES(address_city_ibge),notes=VALUES(notes),active=1,registration_needs_update=0,deleted_at=NULL,deleted_by=NULL,updated_at=NOW()",
                [
                    $cid,
                    $pid,
                    $contact["phone"],
                    $contact["email"],
                    $loc["address"],
                    $loc["address_zip"],
                    $loc["address_number"],
                    $loc["address_neighborhood"],
                    $loc["address_complement"],
                    $loc["address_state"],
                    $loc["address_city"],
                    $loc["address_city_ibge"],
                    trim((string) ($_POST["notes"] ?? "")),
                    (int) $c["user"]["id"],
                ],
            );
            $link = one(
                "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=1 LIMIT 1",
                [$cid, $pid],
            );
            $patientLinkId = (int) ($link["id"] ?? 0);
            counter_inc("patient_writes_total");
            clinic_metric_inc($cid, "patient_writes");
            audit(
                "paciente_salvo",
                "paciente",
                $patientLinkId ?: $pid,
                array_merge($_POST, [
                    "patient_name" => $name,
                    "acao_paciente" => "cadastrou",
                ]),
            );
            if (
                patient_age_years($birth) !== null &&
                patient_age_years($birth) < 18 &&
                $patientLinkId > 0
            ) {
                flash(
                    "Paciente menor salvo como ficha incompleta. CPF, Nome Completo e Nascimento já foram registrados; cadastre o Responsável Legal para liberar documentos e atos sensíveis.",
                    "warn",
                );
                redirect("patient", ["id" => $patientLinkId]);
            }
            flash("Paciente salvo.");
        } catch (Throwable $e) {
            error_log("[Prontoo patients] " . $e->getMessage());
            flash(
                $e->getMessage() === "Este CPF não existe."
                    ? "Este CPF não existe."
                    : "Não foi possível salvar o paciente. Revise os dados informados.",
                "bad",
            );
        }
        redirect("patients");
    }
    $search = trim((string) ($_GET["q"] ?? ""));
    $filter = (string) ($_GET["f"] ?? patient_directory_filter_default());
    if (!array_key_exists($filter, patient_directory_filter_options())) {
        $filter = patient_directory_filter_default();
    }
    $searchMode = $search !== "";
    $params = [$cid];
    $where = "pp.clinic_id=? AND pp.active=1";
    if (!$searchMode) {
        $where .= patient_directory_filter_where($filter, $params, "pp", "p");
    }
    if ($searchMode) {
        $like = "%" . $search . "%";
        $digits = only_digits($search);
        $dateLike = "%" . str_replace("/", "-", $search) . "%";
        if ($digits !== "") {
            $where .=
                " AND (p.full_name LIKE ? OR p.cpf LIKE ? OR pp.phone LIKE ? OR DATE_FORMAT(p.birth_date,'%d/%m/%Y') LIKE ? OR p.birth_date LIKE ?)";
            $params[] = $like;
            $params[] = "%" . $digits . "%";
            $params[] = "%" . $digits . "%";
            $params[] = $like;
            $params[] = $dateLike;
        } else {
            $where .=
                " AND (p.full_name LIKE ? OR pp.email LIKE ? OR DATE_FORMAT(p.birth_date,'%d/%m/%Y') LIKE ? OR p.birth_date LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $dateLike;
        }
    }
    $metrics = patient_directory_select_metrics_sql($cid);
    $order = $searchMode
        ? "p.full_name ASC, pp.id DESC"
        : patient_directory_order_sql($filter);
    $base = q(
        "SELECT pp.id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,pp.created_at,pp.updated_at,pp.registration_needs_update,p.full_name,p.cpf,p.birth_date,(SELECT COUNT(*) FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1) guardian_count $metrics FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE $where ORDER BY $order LIMIT 120",
        $params,
    )->fetchAll();
    [$todayStart, $todayEnd] = patient_today_utc_range($cid);
    [$weekStart, $weekEnd] = patient_week_utc_range($cid);
    $todayCount =
        (int) (val(
            "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND start_at>=? AND start_at<? AND status NOT IN ('cancelado','nao_compareceu')",
            [$cid, $todayStart, $todayEnd],
        ) ?? 0);
    $weekCount =
        (int) (val(
            "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND start_at>=? AND start_at<? AND status NOT IN ('cancelado','nao_compareceu')",
            [$cid, $weekStart, $weekEnd],
        ) ?? 0);
    $dropoutCount =
        (int) (val(
            "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND status IN ('cancelado','nao_compareceu') AND COALESCE(updated_at,created_at,start_at)>=DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$cid],
        ) ?? 0);
    $incompleteCount =
        (int) (val(
            "SELECT COUNT(*) FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1 AND ((p.birth_date IS NOT NULL AND p.birth_date>DATE_SUB(CURDATE(), INTERVAL 18 YEAR) AND NOT EXISTS (SELECT 1 FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1)) OR COALESCE(pp.phone,'')='' OR COALESCE(pp.updated_at,pp.created_at,0)<DATE_SUB(NOW(), INTERVAL 180 DAY))",
            [$cid],
        ) ?? 0);
    $statHtml =
        '<section class="patient-directory-overview kpis kpi-info-strip" aria-label="Resumo de pacientes"><div class="patient-kpi-card kpi-card ' .
        ($todayCount > 0 ? "is-total" : "is-muted") .
        '">' .
        icon("today") .
        "<p><b>" .
        number_format($todayCount, 0, ",", ".") .
        '</b><span>Hoje</span></p></div><div class="patient-kpi-card kpi-card ' .
        ($weekCount > 0 ? "is-ok" : "is-muted") .
        '">' .
        icon("calendar_month") .
        "<p><b>" .
        number_format($weekCount, 0, ",", ".") .
        '</b><span>Essa semana</span></p></div><div class="patient-kpi-card kpi-card ' .
        ($dropoutCount > 0 ? "is-warn warn" : "is-ok") .
        '">' .
        icon("event_busy") .
        "<p><b>" .
        number_format($dropoutCount, 0, ",", ".") .
        '</b><span>Desistentes</span></p></div><div class="patient-kpi-card kpi-card ' .
        ($incompleteCount > 0 ? "is-bad warn" : "is-ok") .
        '">' .
        icon("fact_check") .
        "<p><b>" .
        number_format($incompleteCount, 0, ",", ".") .
        "</b><span>Cadastro incompleto</span></p></div></section>";
    $filterIcons = patient_directory_filter_icons();
    $normalFilters = "";
    foreach (patient_directory_filter_options() as $key => $label) {
        $paramsLink = ["f" => $key];
        $normalFilters .=
            '<a class="patient-filter-chip ' .
            (!$searchMode && $filter === $key ? "active" : "") .
            '" href="' .
            href("patients", $paramsLink) .
            '">' .
            icon($filterIcons[$key] ?? "filter_list") .
            "<span>" .
            e($label) .
            "</span></a>";
    }
    $filters = $normalFilters;
    if ($searchMode) {
        $foundCount = count($base);
        $filters =
            '<span class="patient-filter-chip active is-active patient-filter-found" aria-current="page">' .
            icon("manage_search") .
            "<span>Encontrados</span><small>" .
            number_format($foundCount, 0, ",", ".") .
            "</small></span>" .
            $normalFilters;
    }
    $rows = "";
    foreach ($base as $r) {
        $rows .= patient_directory_card($r, $cid);
    }
    $empty =
        $search !== ""
            ? "Nenhum paciente encontrado para esta busca."
            : "Nenhum paciente neste filtro.";
    $list =
        $rows !== ""
            ? '<div class="patient-directory-list ds-person-list ds-patient-list">' .
                $rows .
                "</div>"
            : '<div class="empty patient-directory-empty">' .
                icon("manage_search") .
                "<strong>" .
                $empty .
                "</strong><span>Altere a busca ou escolha outro filtro para ampliar os resultados.</span></div>";
    $clear =
        $search !== "" || $filter !== patient_directory_filter_default()
            ? '<a class="ghost small" href="' .
                href("patients", ["f" => patient_directory_filter_default()]) .
                '">' .
                icon("close") .
                "<span>Limpar</span></a>"
            : "";
    $existingOpen = "";
    $existingId = (int) ($_GET["existing_patient"] ?? 0);
    if ($existingId > 0) {
        $ex = one(
            "SELECT pp.id,p.full_name,p.cpf,p.birth_date FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1",
            [$existingId, $cid],
        );
        if ($ex) {
            $existingOpen =
                '<div class="flash warn patient-existing-card"><strong>Este paciente já está cadastrado.</strong><span>Deseja abrir o cadastro de ' .
                e($ex["full_name"]) .
                '?</span><a class="primary small" href="' .
                href("patient", ["id" => (int) $ex["id"]]) .
                '">Abrir cadastro</a></div>';
        }
    }
    $suggestUrl = href("patient_suggest");
    $searchBar =
        '<section class="patient-directory-search ds-search-block"><form method="get" class="patient-search-bar" role="search" data-patient-live-search="patients"><input type="hidden" name="r" value="patients"><input type="hidden" name="f" value="' .
        e($searchMode ? patient_directory_filter_default() : $filter) .
        '"><label class="search-field"><input name="q" type="search" value="' .
        e($search) .
        '" placeholder="Nome, CPF, telefone ou nascimento" autocomplete="off" aria-label="Buscar paciente por nome, CPF, telefone ou nascimento" data-patient-live-input data-patient-suggest-url="' .
        e($suggestUrl) .
        '"></label><button class="primary small" type="submit">' .
        icon("search") .
        "<span>Busca rápida</span></button>" .
        $clear .
        "</form></section>";
    $filterList =
        '<nav class="patient-filter-chips ds-filter-list-chips" aria-label="Filtros de pacientes" data-patient-filter-nav>' .
        $filters .
        "</nav><template data-patient-filter-base>" .
        $normalFilters .
        '</template><div data-patient-live-results="patients">' .
        $list .
        "</div>";
    $suggest = person_autosuggest_datalist($cid);
    $patientSaveLocked = $existingOpen !== "";
    $patientCancelParams = [];
    if ($search !== "") {
        $patientCancelParams["q"] = $search;
    } elseif ($filter !== patient_directory_filter_default()) {
        $patientCancelParams["f"] = $filter;
    }
    $patientCancelUrl = href("patients", $patientCancelParams);
    $patientSaveActions =
        '<div class="form-actions"><a class="ghost" href="' .
        e($patientCancelUrl) .
        '">' .
        icon("close") .
        '<span>Cancelar</span></a><button type="submit" class="primary">' .
        icon(form_submit_icon("Salvar paciente")) .
        "<span>Salvar paciente</span></button></div>";
    if ($patientSaveLocked) {
        $patientSaveActions = str_replace(
            '<button type="submit"',
            '<button type="submit" disabled aria-disabled="true"',
            $patientSaveActions,
        );
    }
    $newPatientForm =
        '<form method="post" class="compact patient-cpf-first-form" data-patient-existing-lock="' .
        ($patientSaveLocked ? "1" : "0") .
        '">' .
        csrf_field() .
        form_row(
            "CPF",
            input(
                "cpf",
                "text",
                "",
                'required inputmode="numeric" maxlength="14" data-cpf-mask data-patient-cpf-lookup="' .
                    e(href("patient_lookup")) .
                    '" autocomplete="off"',
            ),
        ) .
        form_row(
            "Nome completo do paciente",
            input(
                "name",
                "text",
                "",
                'required autocomplete="name" list="prontoo_person_suggestions" data-person-autosuggest data-patient-cpf-name',
            ),
        ) .
        form_row(
            "Nascimento",
            input("birth_date", "date", "", "required data-patient-cpf-birth"),
        ) .
        '<div class="flash info patient-minor-form-note"><strong>Cadastro mínimo:</strong> CPF, Nome Completo e Nascimento. Se for menor de idade, a ficha será salva como incompleta até vincular Responsável Legal.</div>' .
        '<div class="two">' .
        form_row(
            "Telefone",
            input(
                "phone",
                "text",
                "",
                'required autocomplete="tel" inputmode="tel"',
            ),
        ) .
        form_row(
            "E-mail",
            input("email", "email", "", 'required autocomplete="email"'),
        ) .
        "</div>" .
        patient_address_fields($cid, []) .
        form_row("Observações importantes:", textarea("notes")) .
        $suggest .
        $patientSaveActions .
        "</form>";
    if (isset($_GET["new"]) && (string) $_GET["new"] !== "0") {
        page(
            "Novo Paciente",
            page_head("Novo Paciente", "") .
                card($newPatientForm, "patient-new-screen-card"),
        );
        return;
    }
    $newPatientParams = ["new" => 1];
    if ($search !== "") {
        $newPatientParams["q"] = $search;
    } elseif ($filter !== patient_directory_filter_default()) {
        $newPatientParams["f"] = $filter;
    }
    $headAction =
        '<a class="primary small cmdlike" href="' .
        href("patients", $newPatientParams) .
        '">' .
        action_summary_label("Novo Paciente", "person_add") .
        "</a>";
    page(
        "Pacientes",
        page_head(
            "Pacientes",
            "Lista clínica para localizar, conferir e abrir fichas com rapidez.",
            $headAction,
        ) .
            $existingOpen .
            $statHtml .
            card($searchBar, "patient-search-card ds-search-card") .
            card(
                $filterList,
                "patient-list-card patient-directory-card ds-filter-list-block",
            ),
    );
}
function patient_appointment_duration_label(
    null|string|int $startAt,
    null|string|int $endAt,
): string {

    $s = app_parse_db_utc($startAt);
    $e = app_parse_db_utc($endAt);
    if (!$s || !$e || $e <= $s) {
        return "—";
    }
    $minutes = (int) max(
        1,
        round(($e->getTimestamp() - $s->getTimestamp()) / 60),
    );
    if ($minutes < 60) {
        return $minutes . " min";
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . "h" . ($m > 0 ? sprintf("%02d", $m) : "");
}
function patient_appointment_elapsed_until_now_label(
    null|string|int $startAt,
): string {

    $s = app_parse_db_utc($startAt);
    if (!$s) {
        return "—";
    }
    $minutes = (int) max(1, floor((time() - $s->getTimestamp()) / 60));
    if ($minutes < 60) {
        return $minutes . " min em andamento";
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . "h" . ($m > 0 ? sprintf("%02d", $m) : "") . " em andamento";
}
function patient_appointment_code(array $a): string
{

    return function_exists("appointment_status_code")
        ? appointment_status_code($a)
        : mb_strtolower(trim((string) ($a["status"] ?? "")));
}
function patient_appointment_not_started_label(
    array $a,
    int $cid = 0,
    array $context = [],
): string {

    $scheduled =
        $cid > 0
            ? app_db_utc_to_local($a["start_at"] ?? null, $cid, $context)
            : app_parse_db_utc($a["start_at"] ?? null);
    $code = patient_appointment_code($a);
    if ($scheduled && $scheduled->getTimestamp() > time()) {
        return "não iniciado";
    }
    if (
        in_array(
            $code,
            [
                "agendado",
                "confirmado",
                "chegou",
                "em_preparo",
                "pronto_atendimento",
                "reagendado",
                "cancelado",
                "nao_compareceu",
            ],
            true,
        )
    ) {
        return "não iniciado";
    }
    return "não registrado";
}
function patient_appointment_real_duration_label(
    array $a,
    int $cid = 0,
    array $context = [],
): string {

    $started = $a["consultation_started_at"] ?? null;
    $finished = $a["consultation_finished_at"] ?? null;
    if (!empty($started) && !empty($finished)) {
        return patient_appointment_duration_label($started, $finished);
    }
    $code = patient_appointment_code($a);
    if (!empty($started) && $code === "em_atendimento") {
        return patient_appointment_elapsed_until_now_label($started);
    }
    if (empty($started)) {
        return patient_appointment_not_started_label($a, $cid, $context) ===
            "não iniciado"
            ? "não iniciado"
            : "—";
    }
    return "—";
}
function patient_appointment_status_title(array $a): string
{

    $code = patient_appointment_code($a);
    return match ($code) {
        "atendimento_concluido", "finalizado" => "Consulta realizada",
        "em_atendimento" => "Consulta em atendimento",
        "cancelado" => "Consulta cancelada",
        "nao_compareceu" => "Não compareceu",
        "reagendado" => "Consulta reagendada",
        "confirmado" => "Consulta confirmada",
        "chegou" => "Paciente chegou",
        "em_preparo" => "Em preparo",
        "pronto_atendimento" => "Pronta para atendimento",
        default => "Consulta agendada",
    };
}
function patient_appointment_icon(array $a): string
{

    $code = patient_appointment_code($a);
    return match ($code) {
        "atendimento_concluido", "finalizado" => "event_available",
        "em_atendimento" => "stethoscope",
        "cancelado" => "event_busy",
        "nao_compareceu" => "person_cancel",
        "reagendado" => "event_repeat",
        "confirmado" => "event_available",
        "chegou" => "how_to_reg",
        default => "event",
    };
}
function patient_appointment_status_class(array $a): string
{

    $code = patient_appointment_code($a);
    $safe = preg_replace('/[^a-z0-9_\t -]/i', "", $code) ?: "agendado";
    return "patient-appointment-didactic patient-appointment-status-" .
        str_replace("_", "-", $safe);
}
function patient_document_type_human_label(?string $typeKey): string
{

    $key = mb_strtolower(trim((string) $typeKey));
    if ($key === "") {
        return "Documento";
    }
    if (function_exists("document_type_options")) {
        try {
            $opts = document_type_options();
            if (isset($opts[$key]) && trim((string) $opts[$key]) !== "") {
                return (string) $opts[$key];
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    $map = [
        "receita" => "Receita",
        "prescricao" => "Prescrição",
        "atestado" => "Atestado",
        "declaracao" => "Declaração",
        "declaracao_comparecimento" => "Declaração de comparecimento",
        "recibo" => "Recibo",
        "encaminhamento" => "Encaminhamento",
        "solicitacao_exame" => "Solicitação de exame",
        "laudo" => "Laudo",
        "relatorio" => "Relatório",
        "orientacao" => "Orientação",
    ];
    return $map[$key] ??
        mb_convert_case(str_replace("_", " ", $key), MB_CASE_TITLE, "UTF-8");
}
function patient_appointment_docs_by_appointment(
    int $cid,
    array $appointmentIds,
): array {

    $ids = array_values(
        array_unique(
            array_filter(
                array_map("intval", $appointmentIds),
                static  fn($v) => $v > 0,
            ),
        ),
    );
    if (
        !$ids ||
        !function_exists("db_table_exists") ||
        !db_table_exists("pi_documents")
    ) {
        return [];
    }
    $ph = implode(",", array_fill(0, count($ids), "?"));
    $params = array_merge([$cid], $ids);
    try {
        $rows = q(
            "SELECT id,appointment_id,title,type_key,document_status,issued_at FROM pi_documents WHERE clinic_id=? AND appointment_id IN ($ph) ORDER BY issued_at DESC,id DESC",
            $params,
        )->fetchAll();
    } catch (Throwable $e) {
        error_log("[Prontoo patient appointment docs] " . $e->getMessage());
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $aid = (int) ($r["appointment_id"] ?? 0);
        if ($aid <= 0) {
            continue;
        }
        $out[$aid][] = $r;
    }
    return $out;
}
function patient_appointment_docs_label(array $docs): string
{

    if (!$docs) {
        return "Documentos gerados: não houve geração de documentos nesta consulta.";
    }
    $labels = [];
    foreach ($docs as $d) {
        $title = trim((string) ($d["title"] ?? ""));
        if ($title === "") {
            $title = patient_document_type_human_label($d["type_key"] ?? null);
        }
        $status = trim((string) ($d["document_status"] ?? ""));
        $statusLabel =
            $status !== "" && function_exists("document_status_label")
                ? document_status_label($status)
                : ($status !== ""
                    ? ucfirst(str_replace("_", " ", $status))
                    : "");
        $labels[] =
            mb_substr($title, 0, 80, "UTF-8") .
            ($statusLabel !== "" ? " (" . $statusLabel . ")" : "");
    }
    $total = count($labels);
    if ($total > 6) {
        $labels = array_merge(array_slice($labels, 0, 6), [
            "+" . ($total - 6) . " documento(s)",
        ]);
    }
    return "Documentos gerados: " . implode("; ", $labels) . ".";
}
function patient_appointment_real_start_label(
    array $a,
    int $cid,
    array $context = [],
): string {

    $started = app_db_utc_to_local(
        $a["consultation_started_at"] ?? null,
        $cid,
        $context,
    );
    if ($started) {
        return $started->format("H:i");
    }
    return patient_appointment_not_started_label($a, $cid, $context);
}
function patient_appointment_real_end_label(
    array $a,
    int $cid,
    array $context = [],
): string {

    $started = app_db_utc_to_local(
        $a["consultation_started_at"] ?? null,
        $cid,
        $context,
    );
    $finished = app_db_utc_to_local(
        $a["consultation_finished_at"] ?? null,
        $cid,
        $context,
    );
    if ($finished) {
        return $finished->format("H:i");
    }
    if ($started && patient_appointment_code($a) === "em_atendimento") {
        return "em andamento";
    }
    if (!$started) {
        return patient_appointment_not_started_label($a, $cid, $context);
    }
    return "não registrado";
}
function patient_appointment_first_line(
    array $a,
    int $cid,
    array $context = [],
): string {

    $scheduled = app_db_utc_to_local($a["start_at"] ?? null, $cid, $context);
    $date = $scheduled ? $scheduled->format("d/m/Y") : "—";
    return "Data do Agendamento: " .
        $date .
        " · Horário Início: " .
        patient_appointment_real_start_label($a, $cid, $context) .
        " · Horário do Fim: " .
        patient_appointment_real_end_label($a, $cid, $context) .
        " · Duração: " .
        patient_appointment_real_duration_label($a, $cid, $context);
}
function patient_appointment_first_line_html(
    array $a,
    int $cid,
    array $context = [],
): string {

    $scheduled = app_db_utc_to_local($a["start_at"] ?? null, $cid, $context);
    $chips = [
        ["Data do Agendamento", $scheduled ? $scheduled->format("d/m/Y") : "—"],
        [
            "Horário Início",
            patient_appointment_real_start_label($a, $cid, $context),
        ],
        [
            "Horário do Fim",
            patient_appointment_real_end_label($a, $cid, $context),
        ],
        [
            "Duração",
            patient_appointment_real_duration_label($a, $cid, $context),
        ],
    ];
    $h = '<span class="patient-appointment-line">';
    foreach ($chips as [$label, $value]) {
        $h .=
            '<span class="patient-appointment-chip"><span>' .
            e($label) .
            ":</span> " .
            e($value) .
            "</span>";
    }
    return $h . "</span>";
}
function patient_appointment_options_from_rows(array $appts): array
{

    $opts = ["" => "Sem vínculo com agendamento"];
    foreach ($appts as $a) {
        $id = (int) ($a["id"] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $label =
            dt_br($a["start_at"] ?? "") .
            " · " .
            ((string) ($a["reason"] ?? "") ?: "Consulta") .
            " · " .
            ((string) ($a["status"] ?? "") ?: "agendado");
        $opts[$id] = $label;
    }
    return $opts;
}
function patient_default_document_appointment(array $appts): ?int
{

    foreach ($appts as $a) {
        $code = patient_appointment_code($a);
        if (
            (int) ($a["id"] ?? 0) > 0 &&
            !empty($a["consultation_started_at"]) &&
            empty($a["consultation_finished_at"]) &&
            $code === "em_atendimento"
        ) {
            return (int) $a["id"];
        }
    }
    foreach ($appts as $a) {
        if (
            (int) ($a["id"] ?? 0) > 0 &&
            app_storage_timestamp($a["start_at"] ?? "") >= strtotime("today") &&
            app_storage_timestamp($a["start_at"] ?? "") < strtotime("tomorrow")
        ) {
            return (int) $a["id"];
        }
    }
    return null;
}
function patient_appointment_timeline_items(
    int $cid,
    array $appointments,
    array $context = [],
): array {

    $ids = [];
    foreach ($appointments as $a) {
        $ids[] = (int) ($a["id"] ?? 0);
    }
    $docsByAppointment = patient_appointment_docs_by_appointment($cid, $ids);
    $items = [];
    foreach ($appointments as $a) {
        $aid = (int) ($a["id"] ?? 0);
        $reason = trim((string) ($a["reason"] ?? ""));
        $items[] = [
            "icon" => patient_appointment_icon($a),
            "class" => patient_appointment_status_class($a),
            "time" => patient_appointment_first_line($a, $cid, $context),
            "time_html" => patient_appointment_first_line_html(
                $a,
                $cid,
                $context,
            ),
            "title" => patient_appointment_status_title($a),
            "body" =>
                $reason !== "" ? "Motivo: " . $reason : "Motivo não informado.",
            "meta" => patient_appointment_docs_label(
                $docsByAppointment[$aid] ?? [],
            ),
        ];
    }
    return $items;
}
function page_patient(): void
{

    $c = require_can("patients");
    $cid = (int) $c["clinic_id"];
    $id = (int) ($_GET["id"] ?? 0);
    patient_tabs_ensure_schema();
    patient_guardians_ensure_schema();
    $p = one(
        "SELECT id,clinic_id,person_id,phone,email,address,address_zip,address_number,address_neighborhood,address_complement,address_state,address_city,address_city_ibge,tags,notes,active,registration_needs_update,deleted_at,created_by,created_at,updated_at FROM pi_patients WHERE id=? AND clinic_id=?",
        [$id, $cid],
    );
    if ($p) {
        $ps =
            one("SELECT full_name,cpf,birth_date FROM pi_persons WHERE id=?", [
                (int) $p["person_id"],
            ]) ?:
            [];
        $p += $ps;
    }
    if (!$p) {
        http_response_code(404);
        page("Paciente", '<div class="empty">Paciente não encontrado.</div>');
        return;
    }
    if ((int) ($p["active"] ?? 1) !== 1) {
        page(
            "Paciente excluído",
            '<div class="empty"><h2>Cadastro excluído</h2><p>Este paciente foi marcado como excluído e só pode ser restaurado pelo Administrador.</p><p><a class="ghost" href="' .
                href("patients") .
                '">' .
                icon("arrow_back") .
                "<span>Voltar para Pacientes</span></a></p></div>",
        );
        return;
    }
    $role = (string) ($c["role"] ?? "");
    $canEditPatient = $role === "medico";
    $canUpdatePatientContact = in_array(
        $role,
        ["recepcionista", "medico"],
        true,
    );
    $canManageGuardian = can("patients");
    $guardians = patient_legal_guardians($cid, $id);
    $profileStatus = patient_profile_status($p, $guardians);
    $guardianCard = patient_legal_guardian_card(
        $cid,
        $id,
        $p,
        $guardians,
        $canManageGuardian,
    );
    $extraTabs = patient_extra_tabs($cid, $id);
    $extraTypeOptions = patient_tab_record_options($extraTabs);
    $extraTypeMap = patient_tab_map_by_type($extraTabs);
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "record");
        $type = (string) ($_POST["record_type"] ?? "");
        $title = trim((string) ($_POST["title"] ?? ""));
        $content = trim((string) ($_POST["content"] ?? ""));
        if ($act === "save_legal_guardian") {
            if (!$canManageGuardian) {
                flash(
                    "Este perfil não tem permissão para alterar o responsável legal.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            $guardianId = (int) ($_POST["guardian_id"] ?? 0);
            $gName = trim((string) ($_POST["guardian_name"] ?? ""));
            $gCpf = only_digits((string) ($_POST["guardian_cpf"] ?? ""));
            $gRel = normalize_guardian_relationship(
                (string) ($_POST["guardian_relationship"] ?? "outro"),
            );
            $gPhone = phone_br((string) ($_POST["guardian_phone"] ?? ""));
            $gEmail = trim((string) ($_POST["guardian_email"] ?? ""));
            $gDoc = trim((string) ($_POST["guardian_document_note"] ?? ""));
            $gNotes = trim((string) ($_POST["guardian_notes"] ?? ""));
            $makePrimary =
                !empty($_POST["guardian_primary"]) ||
                !patient_has_legal_guardian($cid, $id);
            if ($gName === "") {
                flash("Informe o nome completo do responsável legal.", "bad");
                redirect("patient", ["id" => $id]);
            }
            if (!valid_cpf($gCpf)) {
                flash("Informe um CPF válido para o responsável legal.", "bad");
                redirect("patient", ["id" => $id]);
            }
            if ($guardianId > 0) {
                $owned = one(
                    "SELECT id FROM pi_patient_guardians WHERE id=? AND clinic_id=? AND patient_link_id=? AND active=1",
                    [$guardianId, $cid, $id],
                );
                if (!$owned) {
                    flash("Responsável legal não encontrado.", "bad");
                    redirect("patient", ["id" => $id]);
                }
            }
            $dup =
                (int) (val(
                    "SELECT id FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND cpf=? AND active=1 AND id<>? LIMIT 1",
                    [$cid, $id, $gCpf, $guardianId],
                ) ?? 0);
            if ($dup > 0) {
                flash(
                    "Este CPF já está vinculado como responsável deste paciente.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            if ($makePrimary) {
                q(
                    "UPDATE pi_patient_guardians SET is_primary=0,updated_at=NOW() WHERE clinic_id=? AND patient_link_id=?",
                    [$cid, $id],
                );
            }
            if ($guardianId > 0) {
                q(
                    "UPDATE pi_patient_guardians SET full_name=?,cpf=?,relationship=?,phone=?,email=?,document_note=?,notes=?,is_primary=?,updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?",
                    [
                        $gName,
                        $gCpf,
                        $gRel,
                        $gPhone,
                        $gEmail,
                        $gDoc,
                        $gNotes,
                        $makePrimary ? 1 : 0,
                        $guardianId,
                        $cid,
                        $id,
                    ],
                );
            } else {
                q(
                    "INSERT INTO pi_patient_guardians (clinic_id,patient_link_id,full_name,cpf,relationship,phone,email,document_note,notes,is_primary,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
                    [
                        $cid,
                        $id,
                        $gName,
                        $gCpf,
                        $gRel,
                        $gPhone,
                        $gEmail,
                        $gDoc,
                        $gNotes,
                        $makePrimary ? 1 : 0,
                        (int) $c["user"]["id"],
                    ],
                );
                $guardianId = db_last_insert_id();
            }
            audit("responsavel_legal_salvo", "paciente", $id, [
                "patient_name" => (string) $p["full_name"],
                "responsavel" => $gName,
                "vinculo" => $gRel,
                "campos" => ["Responsável Legal"],
                "audit_body" =>
                    "Responsável Legal vinculado ou atualizado na ficha do paciente.",
            ]);
            flash("Responsável Legal salvo.");
            redirect("patient", ["id" => $id]);
        }
        if ($act === "delete_legal_guardian") {
            if (!$canManageGuardian) {
                flash(
                    "Este perfil não tem permissão para remover responsável legal.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            $guardianId = (int) ($_POST["guardian_id"] ?? 0);
            $old = one(
                "SELECT id,full_name FROM pi_patient_guardians WHERE id=? AND clinic_id=? AND patient_link_id=? AND active=1",
                [$guardianId, $cid, $id],
            );
            if ($old) {
                q(
                    "UPDATE pi_patient_guardians SET active=0,is_primary=0,updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?",
                    [$guardianId, $cid, $id],
                );
                $next =
                    (int) (val(
                        "SELECT id FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY id ASC LIMIT 1",
                        [$cid, $id],
                    ) ?? 0);
                if ($next > 0) {
                    q(
                        "UPDATE pi_patient_guardians SET is_primary=1,updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?",
                        [$next, $cid, $id],
                    );
                }
                audit("responsavel_legal_removido", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "responsavel" => (string) $old["full_name"],
                    "campos" => ["Responsável Legal"],
                    "audit_body" =>
                        "Responsável Legal removido da ficha do paciente.",
                ]);
                flash(
                    patient_is_minor($p)
                        ? "Responsável removido. Como o paciente é menor, a ficha volta a ficar incompleta até novo vínculo."
                        : "Responsável removido.",
                    "warn",
                );
            }
            redirect("patient", ["id" => $id]);
        }
        if ($act === "issue_patient_document") {
            if (!can("documents")) {
                flash(
                    "Este perfil não tem permissão para emitir documentos.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            $block = patient_sensitive_block_reason($cid, $id);
            if ($block !== null) {
                flash($block, "bad");
                redirect("patient", ["id" => $id]);
            }
            try {
                $docAppointmentId = (int) ($_POST["appointment_id"] ?? 0);
                $docId = create_document_draft_from_template(
                    $c,
                    (int) ($_POST["template_id"] ?? 0),
                    $id,
                    $docAppointmentId,
                );
                flash(
                    "Rascunho criado pela Ficha do Paciente. Revise, pré-visualize e confirme a emissão.",
                );
                redirect("documents", ["doc" => $docId]);
            } catch (Throwable $e) {
                flash(
                    app_public_error_message(
                        $e,
                        "Não foi possível concluir a operação na ficha do paciente.",
                    ),
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
        }
        if ($act === "update_patient_contact") {
            if (!$canUpdatePatientContact) {
                flash(
                    "Este perfil não tem permissão para atualizar dados de contato do paciente.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            $contact = patient_invoice_contact_from_post();
            $loc = patient_location_from_post($cid);
            prontoo_update_patient_contact_command(
                $cid,
                $id,
                (int) $c["user"]["id"],
                [
                    "phone" => $contact["phone"],
                    "email" => $contact["email"],
                    "address" => $loc["address"],
                    "address_zip" => $loc["address_zip"],
                    "address_number" => $loc["address_number"],
                    "address_neighborhood" => $loc["address_neighborhood"],
                    "address_complement" => $loc["address_complement"],
                    "address_state" => $loc["address_state"],
                    "address_city" => $loc["address_city"],
                    "address_city_ibge" => $loc["address_city_ibge"],
                ],
            );
            audit("paciente_contato_atualizado", "paciente", $id, [
                "patient_name" => (string) $p["full_name"],
                "campos" => [
                    "Telefone",
                    "E-mail",
                    "CEP",
                    "Endereço",
                    "Número",
                    "Bairro",
                    "Cidade",
                    "Estado",
                ],
                "audit_body" =>
                    "Telefone, e-mail e endereço fiscal do paciente foram atualizados pela equipe de recepção.",
            ]);
            flash("Dados de contato atualizados.");
            redirect("patient", ["id" => $id]);
        }
        if ($act === "patient_revenue_receive") {
            if (!in_array($role, ["recepcionista", "gerente"], true)) {
                flash(
                    "Este perfil não pode informar recebimentos do paciente.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            ensure_financial_operational_schema();
            $rid = (int) ($_POST["revenue_id"] ?? 0);
            try {
                $uid = (int) $c["user"]["id"];
                $receipt = prontoo_receive_patient_revenue_command(
                    $cid,
                    $id,
                    $rid,
                    $uid,
                    $role,
                );
                if ((string) ($receipt["status"] ?? "") !== "received") {
                    flash("Escolha uma cobrança pendente do paciente.", "bad");
                    redirect("patient", ["id" => $id]);
                }
                audit("receita_recebida", "financeiro", $rid, [
                    "patient_link_id" => $id,
                    "patient_name" => (string) $p["full_name"],
                    "valor" => (int) ($receipt["amount_cents"] ?? 0),
                    "title" => (string) ($receipt["title"] ?? ""),
                    "audit_body" =>
                        "Recebimento do paciente informado pela ficha do paciente e registrado no local financeiro do usuário responsável.",
                ]);
                flash("Recebimento informado.");
            } catch (Throwable $e) {
                flash(
                    app_public_error_message(
                        $e,
                        "Não foi possível concluir a operação na ficha do paciente.",
                    ),
                    "bad",
                );
            }
            redirect("patient", ["id" => $id]);
        }
        if ($act === "finish_active_appointment") {
            $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
            $returnDay = (string) ($_POST["return_day"] ?? "");
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnDay)) {
                $returnDay = app_today_in_timezone($cid, $c);
            }
            $returnDoctor = (int) ($_POST["return_doctor"] ?? 0);
            $returnParams = ["d" => $returnDay];
            if ($returnDoctor > 0) {
                $returnParams["doctor"] = $returnDoctor;
            }
            $isDoctorRole = function_exists("appointment_journey_role_matches")
                ? appointment_journey_role_matches($role, "medico")
                : $role === "medico";
            if (!$isDoctorRole) {
                flash(
                    "Apenas o ambiente Profissional pode concluir o atendimento.",
                    "bad",
                );
                redirect("patient", [
                    "id" => $id,
                    "appointment_id" => $appointmentId,
                    "return_day" => $returnDay,
                    "return_doctor" => $returnDoctor,
                ]);
            }
            $appt = one(
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? AND patient_link_id=?",
                [$appointmentId, $cid, $id],
            );
            if (!$appt) {
                flash("Atendimento ativo não encontrado.", "bad");
                redirect("patient", ["id" => $id]);
            }
            if (
                (int) ($appt["doctor_user_id"] ?? 0) > 0 &&
                (int) $appt["doctor_user_id"] !== (int) $c["user"]["id"]
            ) {
                flash(
                    "Este atendimento está vinculado a outro profissional.",
                    "bad",
                );
                redirect("patient", [
                    "id" => $id,
                    "appointment_id" => $appointmentId,
                    "return_day" => $returnDay,
                    "return_doctor" => $returnDoctor,
                ]);
            }
            $apptCode = function_exists("appointment_status_code")
                ? appointment_status_code($appt)
                : ((string) ($appt["status"] ?? ""));
            if (
                $apptCode !== "em_atendimento" &&
                empty($appt["consultation_started_at"])
            ) {
                flash("Este atendimento ainda não está em execução.", "bad");
                redirect("patient", [
                    "id" => $id,
                    "appointment_id" => $appointmentId,
                    "return_day" => $returnDay,
                    "return_doctor" => $returnDoctor,
                ]);
            }
            if (function_exists("workflow_on_appointment_finished")) {
                workflow_on_appointment_finished(
                    $cid,
                    $appointmentId,
                    $id,
                    (int) $c["user"]["id"],
                    "ficha_atendimento",
                );
            } else {
                $stmt = q(
                    "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), consultation_finished_at=COALESCE(consultation_finished_at,NOW()), status='atendimento_concluido', updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=? AND status='em_atendimento' AND consultation_finished_at IS NULL",
                    [$appointmentId, $cid, $id],
                );
                if ($stmt->rowCount() <= 0) {
                    flash(
                        "A jornada foi atualizada por outra ação. Reabra a ficha e tente novamente.",
                        "bad",
                    );
                    redirect("patient", ["id" => $id]);
                }
            }
            q(
                "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','em_andamento','aguardando')",
                [(int) $c["user"]["id"], $cid, $appointmentId],
            );
            audit("consulta_concluida_ficha", "consulta", $appointmentId, [
                "patient_name" => (string) $p["full_name"],
                "patient_link_id" => $id,
                "audit_body" =>
                    "Profissional concluiu o atendimento pela Ficha do Paciente e retornou ao Painel da Agenda.",
            ]);
            flash(
                "Atendimento concluído. Próxima medida: Recepção finaliza a saída do paciente.",
            );
            redirect("appointments", $returnParams);
        }
        if (!$canEditPatient) {
            flash(
                "Apenas o profissional responsável pode alterar dados da Ficha do Paciente.",
                "bad",
            );
            redirect("patient", ["id" => $id]);
        }
        if ($act === "create_patient_tab") {
            $label = patient_tab_label_clean(
                (string) ($_POST["tab_label"] ?? ""),
            );
            $iconName = normalize_patient_tab_icon(
                (string) ($_POST["tab_icon"] ?? "clinical_notes"),
            );
            if ($label === "") {
                flash("Informe o nome da nova aba.", "bad");
                redirect("patient", ["id" => $id]);
            }
            $command = prontoo_create_patient_tab_command(
                $cid,
                $id,
                $label,
                $iconName,
                (int) $c["user"]["id"],
            );
            if ((string) ($command["status"] ?? "") === "duplicate") {
                flash("Este paciente já possui uma aba com esse nome.", "bad");
                redirect("patient", ["id" => $id]);
            }
            $tabId = (int) ($command["id"] ?? 0);
            audit("aba_paciente_criada", "paciente", $id, [
                "patient_name" => (string) $p["full_name"],
                "titulo" => $label,
                "icone" => $iconName,
                "campos" => ["Nova aba da ficha"],
                "audit_body" =>
                    "Uma aba extra foi criada na ficha deste paciente.",
            ]);
            flash(
                "Aba criada. Agora ela aparece na ficha e pode receber anotações.",
            );
            redirect("patient", ["id" => $id]);
        }
        if ($act === "update_patient") {
            $name = trim((string) ($_POST["full_name"] ?? ""));
            $cpf = only_digits((string) ($_POST["cpf"] ?? ""));
            $birth = trim((string) ($_POST["birth_date"] ?? ""));
            if ($name === "") {
                flash(
                    "Informe o nome completo para atualizar o cadastro.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            try {
                $identity = person_identity_immutable_values(
                    (int) $p["person_id"],
                    $cpf,
                    $birth,
                    true,
                );
                $cpf = (string) $identity["cpf"];
                $birth = (string) $identity["birth_date"];
            } catch (Throwable $immutableError) {
                flash(
                    app_public_error_message(
                        $immutableError,
                        "CPF e data de nascimento não podem ser alterados por esta operação.",
                    ),
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            $cpfOwner =
                (int) (val(
                    "SELECT id FROM pi_persons WHERE cpf=? AND id<>? LIMIT 1",
                    [$cpf, (int) $p["person_id"]],
                ) ?? 0);
            if ($cpfOwner > 0) {
                flash("Este CPF já pertence a outra pessoa cadastrada.", "bad");
                redirect("patient", ["id" => $id]);
            }
            $nameChanged = $name !== trim((string) ($p["full_name"] ?? ""));
            if (
                $nameChanged &&
                person_has_other_clinic_links((int) $p["person_id"], $cid)
            ) {
                flash(
                    "Esta pessoa também possui vínculo com outra clínica. Para proteger o isolamento entre clínicas, o nome não pode ser alterado por esta ficha. Atualize apenas telefone, e-mail, endereço e notas locais.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            q(
                "UPDATE pi_persons SET full_name=?,cpf=?,birth_date=?,updated_at=NOW() WHERE id=?",
                [$name, $cpf, $birth, (int) $p["person_id"]],
            );
            person_signature_refresh((int) $p["person_id"]);
            $loc = patient_location_from_post($cid);
            $contact = patient_invoice_contact_from_post();
            q(
                "UPDATE pi_patients SET phone=?,email=?,address=?,address_zip=?,address_number=?,address_neighborhood=?,address_complement=?,address_state=?,address_city=?,address_city_ibge=?,notes=?,registration_needs_update=0,updated_at=NOW() WHERE id=? AND clinic_id=?",
                [
                    $contact["phone"],
                    $contact["email"],
                    $loc["address"],
                    $loc["address_zip"],
                    $loc["address_number"],
                    $loc["address_neighborhood"],
                    $loc["address_complement"],
                    $loc["address_state"],
                    $loc["address_city"],
                    $loc["address_city_ibge"],
                    trim((string) ($_POST["notes"] ?? "")),
                    $id,
                    $cid,
                ],
            );
            audit("paciente_salvo", "paciente", $id, [
                "patient_name" => $name,
                "acao_paciente" => "alterou",
                "campos" => [
                    "Nome",
                    "Telefone",
                    "E-mail",
                    "CEP",
                    "Endereço",
                    "Número",
                    "Bairro",
                    "Cidade",
                    "Estado",
                    "Observações",
                ],
                "audit_body" =>
                    "Os dados cadastrais e fiscais do prontuário foram atualizados sem alterar CPF ou nascimento.",
            ]);
            flash("Ficha atualizada.");
            redirect("patient", ["id" => $id]);
        }
        if ($act === "delete_patient") {
            $future =
                (int) (val(
                    "SELECT COUNT(*) FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND start_at>=NOW() AND status NOT IN ('cancelado')",
                    [$cid, $id],
                ) ?? 0);
            if ($future > 0) {
                flash(
                    "Não é possível excluir este paciente enquanto houver agendamento futuro ativo. Cancele ou reagende antes.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            q(
                "UPDATE pi_patients SET active=0,deleted_at=NOW(),deleted_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?",
                [(int) $c["user"]["id"], $id, $cid],
            );
            audit("paciente_excluido", "paciente", $id, [
                "patient_name" => (string) $p["full_name"],
                "campos" => ["Exclusão lógica do paciente"],
                "audit_body" =>
                    "Cadastro do paciente foi marcado como excluído e permanece preservado para restauração pelo Administrador.",
            ]);
            flash(
                "Paciente marcado como excluído. O Administrador poderá restaurar o cadastro.",
                "warn",
            );
            redirect("patients");
        }
        if ($act === "update_care") {
            $careId = (int) ($_POST["care_id"] ?? 0);
            $oldCare = one(
                "SELECT c.id,c.record_type,c.title,cc.content FROM pi_care c INNER JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id WHERE c.id=? AND c.clinic_id=? AND c.patient_link_id=? AND c.deleted_at IS NULL",
                [$careId, $cid, $id],
            );
            if (!$oldCare) {
                flash("Anotação não encontrada.", "bad");
                redirect("patient", ["id" => $id]);
            }
            $allowedUpdate = $extraTypeOptions;
            if (!array_key_exists($type, $allowedUpdate) || $content === "") {
                flash(
                    "Informe uma aba válida e o conteúdo da anotação clínica.",
                    "bad",
                );
                redirect("patient", ["id" => $id]);
            }
            q(
                "INSERT INTO pi_care_versions (care_id,clinic_id,patient_link_id,old_record_type,old_title,old_content,changed_by,change_reason,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                [
                    $careId,
                    $cid,
                    $id,
                    $oldCare["record_type"] ?? null,
                    $oldCare["title"] ?? null,
                    $oldCare["content"] ?? null,
                    (int) $c["user"]["id"],
                    trim((string) ($_POST["change_reason"] ?? "")),
                ],
            );
            q(
                "UPDATE pi_care c INNER JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id SET c.record_type=?,c.title=?,cc.content=?,c.updated_at=NOW(),cc.updated_at=NOW() WHERE c.id=? AND c.clinic_id=? AND c.patient_link_id=? AND c.deleted_at IS NULL",
                [$type, $title, $content, $careId, $cid, $id],
            );
            $changed = [];
            foreach (
                [
                    "record_type" => "Tipo",
                    "title" => "Título",
                    "content" => "Conteúdo",
                ]
                as $k => $label
            ) {
                if (
                    (string) ($oldCare[$k] ?? "") !==
                    (string) ($k === "record_type"
                        ? $type
                        : ($k === "title"
                            ? $title
                            : $content))
                ) {
                    $changed[] = $label;
                }
            }
            audit("prontuario_alterado", "paciente", $id, [
                "patient_name" => (string) $p["full_name"],
                "tipo" => $type,
                "titulo" => $title,
                "campos" => $changed ?: ["Anotação clínica"],
                "audit_body" => audit_change_body(
                    $changed ?: ["Anotação clínica"],
                ),
            ]);
            flash("Anotação atualizada.");
            redirect("patient", ["id" => $id]);
        }
        if ($act === "delete_care") {
            $careId = (int) ($_POST["care_id"] ?? 0);
            $oldCare = one(
                "SELECT id,record_type,title FROM pi_care WHERE id=? AND clinic_id=? AND patient_link_id=? AND deleted_at IS NULL",
                [$careId, $cid, $id],
            );
            if ($oldCare) {
                q(
                    "UPDATE pi_care SET deleted_at=NOW(),deleted_by=? WHERE id=? AND clinic_id=? AND patient_link_id=? AND deleted_at IS NULL",
                    [(int) $c["user"]["id"], $careId, $cid, $id],
                );
                audit("prontuario_alterado", "paciente", $id, [
                    "patient_name" => (string) $p["full_name"],
                    "tipo" => (string) $oldCare["record_type"],
                    "titulo" => (string) $oldCare["title"],
                    "campos" => ["Exclusão lógica de anotação"],
                    "audit_body" =>
                        "Uma anotação do prontuário foi marcada como excluída e permanece preservada para restauração pelo Administrador.",
                ]);
                flash(
                    "Anotação marcada como excluída. O Administrador poderá restaurar.",
                    "warn",
                );
            }
            redirect("patient", ["id" => $id]);
        }
        if (!array_key_exists($type, $extraTypeOptions)) {
            flash(
                "Crie ou escolha uma aba extra deste paciente antes de salvar a anotação.",
                "bad",
            );
            redirect("patient", ["id" => $id]);
        }
        if ($content === "") {
            flash("Informe o conteúdo da anotação clínica.", "bad");
            redirect("patient", ["id" => $id]);
        }
        $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
        $appointmentForAudit = null;
        if ($appointmentId > 0) {
            $appointmentForAudit = one(
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? AND patient_link_id=?",
                [$appointmentId, $cid, $id],
            );
            if (!$appointmentForAudit) {
                $appointmentId = 0;
                $appointmentForAudit = null;
            }
        }
        q(
            "INSERT INTO pi_care (clinic_id,patient_link_id,appointment_id,record_type,title,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())",
            [
                $cid,
                $id,
                $appointmentId > 0 ? $appointmentId : null,
                $type,
                $title,
                (int) $c["user"]["id"],
            ],
        );
        $careId = db_last_insert_id();
        q(
            "INSERT INTO pi_care_content (care_id,clinic_id,content) VALUES (?,?,?)",
            [$careId, $cid, $content],
        );
        if (
            $appointmentForAudit &&
            empty($appointmentForAudit["consultation_started_at"])
        ) {
            q(
                "UPDATE pi_appointments SET consultation_started_at=NOW(), status=IF(status='agendado','em_atendimento',status), updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?",
                [$appointmentId, $cid, $id],
            );
            $scheduled = app_storage_timestamp(
                $appointmentForAudit["start_at"],
            );
            $delay = $scheduled
                ? max(0, (int) floor((time() - $scheduled) / 60))
                : null;
            audit("consulta_iniciada", "consulta", $appointmentId, [
                "patient_name" => (string) $p["full_name"],
                "patient_link_id" => $id,
                "doctor_user_id" =>
                    $appointmentForAudit["doctor_user_id"] ?? null,
                "start_at" => $appointmentForAudit["start_at"] ?? null,
                "end_at" => $appointmentForAudit["end_at"] ?? null,
                "reason" => $appointmentForAudit["reason"] ?? null,
                "audit_body" =>
                    "A consulta foi iniciada" .
                    ($delay !== null
                        ? " com atraso registrado de " .
                            format_minutes($delay) .
                            " em relação ao horário agendado."
                        : "."),
            ]);
        }
        if (
            $appointmentForAudit &&
            $role === "medico" &&
            !empty($_POST["finish_appointment"]) &&
            empty($appointmentForAudit["consultation_finished_at"])
        ) {
            workflow_on_appointment_finished(
                $cid,
                $appointmentId,
                $id,
                (int) $c["user"]["id"],
                "anotacao_clinica",
            );
            audit("consulta_concluida", "consulta", $appointmentId, [
                "patient_name" => (string) $p["full_name"],
                "patient_link_id" => $id,
                "doctor_user_id" =>
                    $appointmentForAudit["doctor_user_id"] ?? null,
                "start_at" => $appointmentForAudit["start_at"] ?? null,
                "end_at" => $appointmentForAudit["end_at"] ?? null,
                "reason" => $appointmentForAudit["reason"] ?? null,
                "audit_body" =>
                    "O profissional concluiu o atendimento e o horário real de fim foi registrado. A Recepção recebeu recado automático para finalizar a saída do paciente.",
            ]);
        }
        counter_inc("care_records_total");
        clinic_metric_inc($cid, "care_records");
        $changedFields = ["Aba da ficha"];
        if ($title !== "") {
            $changedFields[] = "Título";
        }
        $changedFields[] = "Conteúdo";
        audit("prontuario_alterado", "paciente", $id, [
            "patient_name" => (string) $p["full_name"],
            "tipo" => $type,
            "titulo" => $title,
            "campos" => $changedFields,
            "audit_body" => audit_change_body($changedFields),
        ]);
        $_SESSION["skip_patient_view_audit_" . (int) $id] = 1;
        flash("Anotação incluída.");
        redirect("patient", ["id" => $id]);
    }
    $appts = q(
        "SELECT id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? ORDER BY start_at DESC LIMIT 20",
        [$cid, $id],
    )->fetchAll();
    $apptItems = patient_appointment_timeline_items($cid, $appts, $c);
    $patientDocOptions = can("documents")
        ? approved_document_template_options($c)
        : [];
    $patientDocItems = patient_document_timeline_items($c, $id, 80);
    $patientDocCount = count($patientDocItems);
    $patientDocForm = "";
    if (can("documents")) {
        $docEmitParams = ["emit" => 1, "patient_id" => $id];
        $docEmitAppointmentId = (int) ($_GET["appointment_id"] ?? 0);
        if ($docEmitAppointmentId > 0) {
            $docEmitParams["appointment_id"] = $docEmitAppointmentId;
        }
        $patientDocForm =
            '<div class="patient-empty-action patient-doc-create patient-doc-external-cta"><a class="primary small cmdlike" href="' .
            href("documents", $docEmitParams) .
            '">' .
            action_summary_label("Emitir Primeiro Documento", "description") .
            "</a></div>";
    }
    $activeAppointmentId = (int) ($_GET["appointment_id"] ?? 0);
    $activeAppointment = null;
    if ($role === "medico") {
        if ($activeAppointmentId > 0) {
            $activeAppointment = one(
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? AND patient_link_id=?",
                [$activeAppointmentId, $cid, $id],
            );
        }
        if (!$activeAppointment) {
            $activeAppointment = one(
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND doctor_user_id=? AND consultation_started_at IS NOT NULL AND consultation_finished_at IS NULL AND status NOT IN ('cancelado','nao_compareceu','reagendado','finalizado') ORDER BY start_at DESC LIMIT 1",
                [$cid, $id, (int) $c["user"]["id"]],
            );
        }
        if ($activeAppointment) {
            $activeCode = function_exists("appointment_status_code")
                ? appointment_status_code($activeAppointment)
                : (string) ($activeAppointment["status"] ?? "");
            if ($activeCode !== "em_atendimento") {
                $activeAppointment = null;
            }
        }
    }
    $activeAppointmentPanel = "";
    if ($activeAppointment) {
        $activeReturnDay = (string) ($_GET["return_day"] ?? "");
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $activeReturnDay)) {
            $activeReturnDay = substr(
                (string) ($activeAppointment["start_at"] ?? ""),
                0,
                10,
            );
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $activeReturnDay)) {
            $activeReturnDay = app_today_in_timezone($cid, $c);
        }
        $activeReturnDoctor =
            (int) ($_GET["return_doctor"] ??
                ((int) ($activeAppointment["doctor_user_id"] ?? 0)));
        $activeAppointmentPanel =
            '<section class="patient-active-consultation" aria-label="Atendimento em andamento"><div class="patient-active-consultation-copy"><span class="eyebrow">Atendimento em andamento</span><h2>' .
            e((string) ($activeAppointment["reason"] ?: "Consulta")) .
            "</h2><p>A ficha permanece aberta durante o atendimento. Registre as anotações necessárias e conclua quando terminar.</p><small>" .
            e(dt_br($activeAppointment["start_at"])) .
            " · " .
            e(dt_br($activeAppointment["end_at"])) .
            '</small></div><form method="post" class="patient-active-consultation-actions">' .
            csrf_field() .
            '<input type="hidden" name="act" value="finish_active_appointment"><input type="hidden" name="appointment_id" value="' .
            (int) $activeAppointment["id"] .
            '"><input type="hidden" name="return_day" value="' .
            e($activeReturnDay) .
            '"><input type="hidden" name="return_doctor" value="' .
            (int) $activeReturnDoctor .
            '"><button class="primary" type="submit">' .
            icon("task_alt") .
            '<span>Concluir atendimento e voltar ao Painel</span></button><a class="ghost small" href="' .
            href("appointments", [
                "d" => $activeReturnDay,
                "doctor" => $activeReturnDoctor,
            ]) .
            '">' .
            icon("space_dashboard") .
            "<span>Voltar ao Painel</span></a></form></section>";
    }
    $consultStats = one(
        "SELECT COUNT(*) total, MIN(start_at) first_at FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND status NOT IN ('cancelado')",
        [$cid, $id],
    ) ?: ["total" => 0, "first_at" => null];
    $firstConsultationLabel = !empty($consultStats["first_at"])
        ? dt_card_full_br($consultStats["first_at"])
        : "Ainda não agendada";
    $totalConsultations = (int) ($consultStats["total"] ?? 0);
    $patientSummaryCards =
        '<div class="patient-profile-summary-cards patient-profile-footer-cards" aria-label="Resumo do cadastro do paciente"><article>' .
        icon("person_add") .
        "<span>Cadastro</span><b>" .
        e(dt_card_full_br($p["created_at"])) .
        "</b></article><article>" .
        icon("update") .
        "<span>Atualização</span><b>" .
        e(dt_card_full_br($p["updated_at"] ?: $p["created_at"])) .
        "</b></article><article>" .
        icon("event_available") .
        "<span>Primeira Consulta</span><b>" .
        e($firstConsultationLabel) .
        "</b></article><article>" .
        icon("monitoring") .
        "<span>Total de Consultas</span><b>" .
        (int) $totalConsultations .
        "</b></article></div>";
    $patientProfileOverview = patient_profile_overview(
        $p,
        $profileStatus,
        count($appts),
        (int) $patientDocCount,
        $totalConsultations,
        $firstConsultationLabel,
    );
    $patientReceptionItems = patient_reception_history_items($cid, $id, $p);
    $patientReceptionCount = count($patientReceptionItems);
    $patientReceptionPanel = patient_reception_history_panel(
        $patientReceptionItems,
    );
    $agendaEmptyAction = can("appointments")
        ? '<div class="empty patient-empty-cta"><a class="primary small" href="' .
            href("appointments", ["patient_id" => $id]) .
            '">' .
            icon("event_available") .
            "<span>Agendar Primeira Consulta</span></a></div>"
        : '<div class="empty">Nenhum agendamento encontrado para este paciente.</div>';
    ensure_financial_operational_schema();
    financial_ensure_default_accounts($cid, (int) $c["user"]["id"]);
    $pendingPatientRevenueCount =
        (int) (val(
            "SELECT COUNT(*) FROM pi_financial_revenues WHERE clinic_id=? AND patient_link_id=? AND status='prevista'",
            [$cid, $id],
        ) ?? 0);
    $showPatientFinance =
        $role === "gerente" ||
        ($role === "recepcionista" && $pendingPatientRevenueCount > 0);
    $patientFinanceRows = $showPatientFinance
        ? q(
            "SELECT r.id,r.appointment_id,r.title,r.amount_cents,r.status,r.payment_method,r.expected_at,r.received_at,r.account_id,a.name account_name FROM pi_financial_revenues r LEFT JOIN pi_financial_accounts a ON a.id=r.account_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.patient_link_id=? ORDER BY FIELD(r.status,'prevista','efetivada','cancelada'), COALESCE(r.expected_at,r.received_at,r.created_at) DESC, r.id DESC LIMIT 160",
            [$cid, $id],
        )->fetchAll()
        : [];
    $patientAccountOptions = $showPatientFinance
        ? financial_account_label_options($cid, "Conta de recebimento")
        : [];
    $patientFinanceList = "";
    foreach ($patientFinanceRows as $fr) {
        $date =
            $fr["status"] === "efetivada"
                ? $fr["received_at"] ?? ""
                : $fr["expected_at"] ?? "";
        $methodKey = (string) ($fr["payment_method"] ?? "");
        $methodLabel =
            $methodKey !== ""
                ? payment_methods_options()[$methodKey] ?? $methodKey
                : "";
        $patientFinanceList .=
            '<article class="finance-row finance-action-row patient-finance-row"><div><strong>' .
            e($fr["title"] ?: "Cobrança do paciente") .
            "</strong><small>" .
            e(
                ($date ? date_br($date) : "sem data") .
                    " · " .
                    ($methodLabel !== "" ? $methodLabel . " · " : "") .
                    ($fr["account_name"] ?: "sem conta"),
            ) .
            "</small></div><b>" .
            money_br((int) $fr["amount_cents"]) .
            "</b>" .
            financial_status_pill(
                (string) $fr["status"],
                (string) ($fr["expected_at"] ?? ""),
                "revenue",
            );
        if (
            $fr["status"] === "prevista" &&
            in_array($role, ["recepcionista", "gerente"], true)
        ) {
            $patientFinanceList .=
                '<form method="post" class="finance-inline-form patient-finance-receive">' .
                csrf_field() .
                '<input type="hidden" name="act" value="patient_revenue_receive"><input type="hidden" name="revenue_id" value="' .
                (int) $fr["id"] .
                '"><button class="ghost small" type="submit">' .
                icon("paid") .
                "<span>Receber</span></button></form>";
        }
        $patientFinanceList .= "</article>";
    }
    $prefix = "pt" . (int) $id . "_";
    $financePanel = $showPatientFinance
        ? '<section class="patient-panel patient-panel-financeiro" role="tabpanel"><div class="patient-section-title"><div><h2>Financeiro</h2><p>' .
            ($role === "recepcionista"
                ? "Cobranças a receber deste paciente. Informe a entrada e a conta de destino."
                : "Pagamentos e cobranças vinculados a este paciente.") .
            "</p></div><span>" .
            (int) count($patientFinanceRows) .
            " item(ns)</span></div>" .
            ($patientFinanceList ?:
                '<div class="empty">Nenhum pagamento vinculado a este paciente.</div>') .
            "</section>"
        : "";
    $financeInput = $showPatientFinance
        ? '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'financeiro">'
        : "";
    $financeNav = $showPatientFinance
        ? '<label for="' .
            $prefix .
            'financeiro">' .
            icon("payments") .
            "<span><b>Financeiro</b></span>" .
            ($pendingPatientRevenueCount > 0
                ? "<em>" . (int) $pendingPatientRevenueCount . "</em>"
                : "") .
            "</label>"
        : "";
    $age = "";
    if (!empty($p["birth_date"])) {
        $ts = app_storage_timestamp($p["birth_date"]);
        if ($ts) {
            $age =
                (string) max(0, (int) floor((time() - $ts) / 31557600)) .
                " anos";
        }
    }
    if (!$canEditPatient) {
        $patientHeadActions =
            '<a class="ghost small" href="' .
            href("patients") .
            '">' .
            icon("arrow_back") .
            "<span>Voltar para Pacientes</span></a>";
        $hero =
            page_head(
                (string) $p["full_name"],
                "Ficha do paciente",
                $patientHeadActions,
            ) . $patientProfileOverview;
        $dados =
            '<div class="patient-data-list"><div><span>Nome completo</span><b>' .
            e($p["full_name"]) .
            "</b></div><div><span>CPF</span><b>" .
            e(mask($p["cpf"] ?? "")) .
            "</b></div><div><span>Nascimento</span><b>" .
            date_br($p["birth_date"]) .
            ($age ? " · " . e($age) : "") .
            "</b></div><div><span>Telefone</span><b>" .
            e($p["phone"] ?: "não informado") .
            "</b></div><div><span>E-mail</span><b>" .
            e($p["email"] ?: "não informado") .
            "</b></div><div><span>Endereço</span><b>" .
            e($p["address"] ?: "não informado") .
            "</b></div><div><span>Cidade/Estado</span><b>" .
            e(
                city_state_label(
                    $p["address_city"] ?? "",
                    $p["address_state"] ?? "",
                ) ?:
                "não informado",
            ) .
            "</b></div></div>";
        $patientContactEdit = $canUpdatePatientContact
            ? prontoo_patient_contact_edit_form($p, $cid)
            : "";
        $dataPanel =
            '<section class="patient-panel patient-panel-cadastro" role="tabpanel"><h2>Cadastro</h2><p class="muted-copy">Dados de identificação e contato do paciente.</p>' .
            $dados .
            $guardianCard .
            $patientContactEdit .
            $patientSummaryCards .
            "</section>";
        $agendaPanel =
            '<section class="patient-panel patient-panel-agenda" role="tabpanel"><div class="patient-section-title"><div><h2>Agenda</h2><p>Agendamentos vinculados a este paciente.</p></div><span>' .
            count($appts) .
            " item(ns)</span></div>" .
            ($apptItems ? timeline($apptItems, "") : $agendaEmptyAction) .
            "</section>";
        $docsPanel =
            '<section class="patient-panel patient-panel-documentos" role="tabpanel"><div class="patient-section-title"><div><h2>Documentos</h2><p>Últimos documentos emitidos para este paciente a partir dos modelos do módulo Documentos.</p></div><span>' .
            (int) $patientDocCount .
            " documento(s)</span></div>" .
            $patientDocForm .
            ($patientDocItems
                ? '<hr class="soft-sep">' . timeline($patientDocItems, "")
                : "") .
            "</section>";
        $tabs =
            '<section class="patient-tabs-shell patient-tabs-records" aria-label="Paciente em abas"><input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'cadastro" checked><input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'agenda"><input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'documentos"><input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            'atendimentos">' .
            $financeInput .
            '<aside class="patient-tab-nav" aria-label="Seções do paciente"><label for="' .
            $prefix .
            'cadastro">' .
            icon("badge") .
            '<span><b>Cadastro</b></span></label><label for="' .
            $prefix .
            'agenda">' .
            icon("calendar_month") .
            "<span><b>Agenda</b></span><em>" .
            (int) count($appts) .
            '</em></label><label for="' .
            $prefix .
            'documentos">' .
            icon("description") .
            "<span><b>Documentos</b></span><em>" .
            (int) $patientDocCount .
            '</em></label><label for="' .
            $prefix .
            'atendimentos">' .
            icon("forum") .
            "<span><b>Atendimentos</b></span><em>" .
            (int) $patientReceptionCount .
            "</em></label>" .
            $financeNav .
            '</aside><div class="patient-tab-panels">' .
            $dataPanel .
            $agendaPanel .
            $docsPanel .
            $patientReceptionPanel .
            $financePanel .
            "</div></section>";
        page($p["full_name"], $hero . $activeAppointmentPanel . $tabs);
        return;
    }
    $viewAuditKey = "skip_patient_view_audit_" . (int) $id;
    if (!empty($_SESSION[$viewAuditKey])) {
        unset($_SESSION[$viewAuditKey]);
    } else {
        audit("prontuario_visualizado", "paciente", $id, [
            "patient_name" => (string) $p["full_name"],
            "audit_body" => "Nenhuma informação foi alterada.",
        ]);
    }
    $rows = q(
        "SELECT c.id,c.record_type,c.title,cc.content,c.created_by,c.created_at FROM pi_care c INNER JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id WHERE c.clinic_id=? AND c.patient_link_id=? AND c.deleted_at IS NULL AND c.record_type<>'receita' ORDER BY c.id DESC LIMIT 200",
        [$cid, $id],
    )->fetchAll();
    $authors = fetch_map("pi_users", int_ids($rows, "created_by"), "id,name");
    $recordItems = [];
    $counts = [];
    foreach ($extraTabs as $tab) {
        $recordItems[(string) $tab["record_type"]] = [];
        $counts[(string) $tab["record_type"]] = 0;
    }
    foreach ($rows as $r) {
        $rt = (string) $r["record_type"];
        $tab = $extraTypeMap[$rt] ?? null;
        if (!$tab) {
            continue;
        }
        $label = (string) $tab["label"];
        $ic = (string) $tab["icon_name"];
        $editOptions = $extraTypeOptions;
        $html =
            '<details class="care-edit"><summary class="ghost small cmdlike">' .
            action_summary_label("Alterar", "edit") .
            '</summary><form method="post" class="compact">' .
            csrf_field() .
            '<input type="hidden" name="act" value="update_care"><input type="hidden" name="care_id" value="' .
            (int) $r["id"] .
            '">' .
            select_label("Tipo", "record_type", $editOptions, $rt, "required") .
            form_row("Título", input("title", "text", $r["title"] ?? "")) .
            form_row(
                "Conteúdo",
                textarea("content", $r["content"], 'required rows="6"'),
            ) .
            form_actions("Salvar anotação") .
            '</form><form method="post" class="inline danger-zone" onsubmit="return confirm(\'Excluir esta anotação?\')">' .
            csrf_field() .
            '<input type="hidden" name="act" value="delete_care"><input type="hidden" name="care_id" value="' .
            (int) $r["id"] .
            '"><button class="danger small" type="submit">' .
            icon("delete") .
            "<span>Excluir</span></button></form></details>";
        $item = [
            "icon" => $ic,
            "time" => dt_br($r["created_at"]),
            "title" =>
                ($r["title"] ?: $label) .
                " · " .
                first_name(
                    $authors[(int) ($r["created_by"] ?? 0)]["name"] ?? "",
                ),
            "body" => $r["content"],
            "meta" => $label,
            "html" => $html,
        ];
        $counts[$rt]++;
        $recordItems[$rt][] = $item;
    }
    $apptLinkRows = q(
        "SELECT id,start_at,status,reason FROM pi_appointments WHERE clinic_id=? AND patient_link_id=? AND start_at>=DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND start_at<DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY start_at ASC LIMIT 20",
        [$cid, $id],
    )->fetchAll();
    $apptOptions = ["" => "Sem vínculo com agendamento"];
    $defaultAppointment = null;
    foreach ($apptLinkRows as $a) {
        $label =
            dt_br($a["start_at"]) .
            " · " .
            ($a["reason"] ?? "" ?: "Consulta") .
            " · " .
            ($a["status"] ?? "" ?: "agendado");
        $apptOptions[(int) $a["id"]] = $label;
        if (
            $defaultAppointment === null &&
            app_storage_timestamp($a["start_at"]) >= strtotime("today") &&
            app_storage_timestamp($a["start_at"]) < strtotime("tomorrow")
        ) {
            $defaultAppointment = (int) $a["id"];
        }
    }
    $annotationForm = function (
        ?string $selectedType = null,
        string $submit = "Salvar anotação",
    ) use (
        $apptOptions,
        $defaultAppointment,
        $extraTypeOptions,
        $role,
    ): string {

        if (!$extraTypeOptions) {
            return '<div class="empty">Crie uma aba extra para este paciente antes de registrar anotações clínicas. Cada aba criada passa a aparecer como Tipo da anotação.</div>';
        }
        if (
            $selectedType === null ||
            !array_key_exists($selectedType, $extraTypeOptions)
        ) {
            $selectedType = array_key_first($extraTypeOptions);
        }
        return '<form method="post" class="compact patient-record-form">' .
            csrf_field() .
            select_label(
                "Consulta vinculada",
                "appointment_id",
                $apptOptions,
                $defaultAppointment,
            ) .
            ($role === "medico"
                ? '<label class="checkline"><input type="checkbox" name="finish_appointment" value="1"><span>Concluir atendimento ao salvar esta anotação</span></label>'
                : "") .
            select_label(
                "Tipo",
                "record_type",
                $extraTypeOptions,
                $selectedType,
                "required",
            ) .
            form_row("Título", input("title")) .
            form_row("Conteúdo", textarea("content", "", 'required rows="7"')) .
            form_actions($submit) .
            "</form>";
    };
    $hero =
        '<div class="patient-hero patient-hero-tabs"><div><span class="eyebrow">Ficha do paciente</span><h1>' .
        e($p["full_name"]) .
        "</h1><p>Nascimento: " .
        date_br($p["birth_date"]) .
        ($age ? " · " . $age : "") .
        " · CPF " .
        e(mask($p["cpf"] ?? "")) .
        '</p></div><div class="patient-hero-actions"><a class="ghost small" href="' .
        href("patients") .
        '">' .
        icon("arrow_back") .
        '<span>Voltar para Pacientes</span></a><label class="primary small patient-new-tab-btn" for="' .
        $prefix .
        'novaanotacao">' .
        icon("edit_note") .
        "<span>Nova anotação</span></label></div></div>";
    $dados =
        '<div class="patient-data-list">' .
        "<div><span>Nome completo</span><b>" .
        e($p["full_name"]) .
        "</b></div>" .
        "<div><span>CPF</span><b>" .
        e(mask($p["cpf"] ?? "")) .
        "</b></div>" .
        "<div><span>Nascimento</span><b>" .
        date_br($p["birth_date"]) .
        ($age ? " · " . e($age) : "") .
        "</b></div>" .
        "<div><span>Telefone</span><b>" .
        e($p["phone"] ?: "não informado") .
        "</b></div>" .
        "<div><span>E-mail</span><b>" .
        e($p["email"] ?: "não informado") .
        "</b></div>" .
        "<div><span>CEP</span><b>" .
        e(mask_cep((string) ($p["address_zip"] ?? "")) ?: "não informado") .
        "</b></div><div><span>Endereço</span><b>" .
        e(
            trim(
                ($p["address"] ?: "") .
                    " " .
                    ($p["address_number"] ? ", " . $p["address_number"] : ""),
            ) ?:
            "não informado",
        ) .
        "</b></div><div><span>Bairro</span><b>" .
        e($p["address_neighborhood"] ?: "não informado") .
        "</b></div><div><span>Cidade/Estado</span><b>" .
        e(
            city_state_label(
                $p["address_city"] ?? "",
                $p["address_state"] ?? "",
            ) ?:
            "não informado",
        ) .
        "</b></div>" .
        "</div>";
    $patientContactEdit = $canUpdatePatientContact
        ? prontoo_patient_contact_edit_form($p, $cid)
        : "";
    $patientCpfField =
        trim((string) ($p["cpf"] ?? "")) !== ""
            ? '<input type="text" value="' .
                e(mask($p["cpf"] ?? "")) .
                '" readonly aria-readonly="true" tabindex="-1" autocomplete="off">'
            : input(
                "cpf",
                "text",
                mask($p["cpf"] ?? ""),
                'required inputmode="numeric" data-cpf-mask',
            );
    $patientBirthField =
        trim((string) ($p["birth_date"] ?? "")) !== ""
            ? '<input type="date" value="' .
                e(app_date_input_from_storage($p["birth_date"] ?? "")) .
                '" readonly aria-readonly="true" tabindex="-1" autocomplete="off">'
            : input("birth_date", "date", $p["birth_date"], "required");
    $patientEdit =
        '<details class="patient-edit"><summary class="primary small cmdlike">' .
        action_summary_label("Alterar cadastro", "edit") .
        '</summary><form method="post" class="compact patient-record-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="update_patient">' .
        form_row(
            "Nome completo",
            input("full_name", "text", $p["full_name"], "required"),
        ) .
        '<div class="two">' .
        form_row("CPF", $patientCpfField) .
        form_row("Nascimento", $patientBirthField) .
        "</div>" .
        '<div class="two">' .
        form_row(
            "Telefone",
            input("phone", "text", $p["phone"], 'required inputmode="tel"'),
        ) .
        form_row("E-mail", input("email", "email", $p["email"], "required")) .
        "</div>" .
        patient_address_fields($cid, $p) .
        form_row(
            "Observações importantes:",
            textarea("notes", $p["notes"], 'rows="5"'),
        ) .
        form_actions("Salvar cadastro") .
        '</form></details><form method="post" class="inline danger-zone" onsubmit="return confirm(\'Marcar este paciente como excluído? O Administrador poderá restaurar.\')">' .
        csrf_field() .
        '<input type="hidden" name="act" value="delete_patient"><button class="danger small" type="submit">' .
        icon("delete") .
        "<span>Excluir paciente</span></button></form>";
    $patientUpdateNotice = !empty($p["registration_needs_update"])
        ? '<div class="flash warn">Cadastro recuperado. Revise e atualize os dados cadastrais.</div>'
        : "";
    $patientStatusNotice =
        $profileStatus["level"] !== "ok"
            ? '<div class="flash ' .
                e($profileStatus["level"]) .
                '"><strong>' .
                e($profileStatus["label"]) .
                ".</strong> " .
                e($profileStatus["message"]) .
                "</div>"
            : "";
    $dataPanel =
        '<section class="patient-panel patient-panel-cadastro" role="tabpanel"><h2>Cadastro</h2><p class="muted-copy">Dados fixos da ficha do paciente. Esta aba não pode ser removida.</p>' .
        $dados .
        $patientStatusNotice .
        $patientUpdateNotice .
        $guardianCard .
        $patientEdit .
        $patientSummaryCards .
        "</section>";
    $agendaPanel =
        '<section class="patient-panel patient-panel-agenda" role="tabpanel"><div class="patient-section-title"><div><h2>Agenda</h2><p>Agendamentos vinculados a este paciente. Esta aba é fixa.</p></div><span>' .
        count($appts) .
        " item(ns)</span></div>" .
        ($apptItems ? timeline($apptItems, "") : $agendaEmptyAction) .
        "</section>";
    $docsPanel =
        '<section class="patient-panel patient-panel-documentos" role="tabpanel"><div class="patient-section-title"><div><h2>Documentos</h2><p>Crie recibos, atestados, receitas e declarações a partir de modelos aprovados. Esta aba é fixa.</p></div><span>' .
        (int) $patientDocCount .
        " documento(s)</span></div>" .
        $patientDocForm .
        ($patientDocItems
            ? '<hr class="soft-sep">' . timeline($patientDocItems, "")
            : "") .
        "</section>";
    $extraInputs = "";
    $extraNav = "";
    $extraPanels = "";
    foreach ($extraTabs as $tab) {
        $key = (string) $tab["tab_key"];
        $rt = (string) $tab["record_type"];
        $count = (int) ($counts[$rt] ?? 0);
        $extraInputs .=
            '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
            $prefix .
            $key .
            '">';
        $extraNav .=
            '<label for="' .
            $prefix .
            $key .
            '">' .
            icon((string) $tab["icon_name"]) .
            "<span><b>" .
            e($tab["label"]) .
            "</b></span><em>" .
            $count .
            "</em></label>";
        $extraPanels .=
            '<section class="patient-panel patient-panel-' .
            $key .
            '" role="tabpanel"><div class="patient-section-title"><div><h2>' .
            e($tab["label"]) .
            "</h2><p>Anotações desta aba, em ordem de inclusão.</p></div><span>" .
            $count .
            ' anotação(ões)</span></div><details class="patient-inline-note"><summary class="ghost small cmdlike">' .
            action_summary_label("Nova anotação nesta aba", "edit_note") .
            "</summary>" .
            $annotationForm($rt, "Salvar em " . (string) $tab["label"]) .
            "</details>" .
            timeline($recordItems[$rt] ?? [], "Nenhuma anotação nesta aba.") .
            "</section>";
    }
    $newRecordPanel =
        '<section class="patient-panel patient-panel-novaanotacao" role="tabpanel"><h2>Nova anotação</h2><p class="muted-copy">O campo Tipo agora usa as abas extras criadas para este paciente específico.</p>' .
        $annotationForm(null) .
        "</section>";
    $newTabPanel =
        '<section class="patient-panel patient-panel-novaaba" role="tabpanel"><h2>Nova Aba</h2><p class="muted-copy">Crie uma área própria da ficha deste paciente. Depois disso, ela aparecerá na navegação e no campo Tipo das novas anotações.</p><form method="post" class="compact patient-record-form patient-extra-tab-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="create_patient_tab">' .
        form_row(
            "Nome exibido da aba",
            input(
                "tab_label",
                "text",
                "",
                'required maxlength="60" placeholder="Ex.: Exames, Dor, Evolução familiar"',
            ),
        ) .
        '<div class="settings-section full"><h3>Ícone da aba</h3>' .
        patient_tab_icon_picker("clinical_notes") .
        "</div>" .
        form_actions("Criar aba") .
        "</form></section>";
    $tabs =
        '<section class="patient-tabs-shell patient-tabs-records patient-crown-record" aria-label="Ficha do paciente em abas">' .
        '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
        $prefix .
        'cadastro" checked>' .
        '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
        $prefix .
        'agenda">' .
        '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
        $prefix .
        'documentos">' .
        '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
        $prefix .
        'atendimentos">' .
        $financeInput .
        $extraInputs .
        '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
        $prefix .
        'novaanotacao">' .
        '<input class="patient-tab-radio" type="radio" name="patient_tab" id="' .
        $prefix .
        'novaaba">' .
        '<aside class="patient-tab-nav" aria-label="Seções da ficha">' .
        '<label for="' .
        $prefix .
        'cadastro">' .
        icon("badge") .
        "<span><b>Cadastro</b></span></label>" .
        '<label for="' .
        $prefix .
        'agenda">' .
        icon("calendar_month") .
        "<span><b>Agenda</b></span><em>" .
        (int) count($appts) .
        "</em></label>" .
        '<label for="' .
        $prefix .
        'documentos">' .
        icon("description") .
        "<span><b>Documentos</b></span><em>" .
        (int) $patientDocCount .
        "</em></label>" .
        '<label for="' .
        $prefix .
        'atendimentos">' .
        icon("forum") .
        "<span><b>Atendimentos</b></span><em>" .
        (int) $patientReceptionCount .
        "</em></label>" .
        $financeNav .
        $extraNav .
        '<label class="patient-tab-add-new" for="' .
        $prefix .
        'novaaba">' .
        icon("add_circle") .
        "<span><b>Nova Aba</b></span></label>" .
        '</aside><div class="patient-tab-panels">' .
        $dataPanel .
        $agendaPanel .
        $docsPanel .
        $patientReceptionPanel .
        $financePanel .
        $extraPanels .
        $newRecordPanel .
        $newTabPanel .
        "</div></section>";
    $patientHeadActions =
        '<span class="pill ' .
        e($profileStatus["level"]) .
        '">' .
        e($profileStatus["label"]) .
        '</span><a class="ghost small" href="' .
        href("patients") .
        '">' .
        icon("arrow_back") .
        '<span>Voltar para Pacientes</span></a><label class="primary small patient-new-tab-btn" for="' .
        $prefix .
        'novaanotacao">' .
        icon("edit_note") .
        "<span>Nova anotação</span></label>";
    page(
        $p["full_name"],
        page_head(
            (string) $p["full_name"],
            "Ficha do paciente",
            $patientHeadActions,
        ) .
            $patientProfileOverview .
            $activeAppointmentPanel .
            $tabs,
    );
}
