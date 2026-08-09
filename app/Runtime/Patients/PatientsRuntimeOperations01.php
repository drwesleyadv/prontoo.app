<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Patients;

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
use Prontoo\Runtime\Patients\PatientComposition;

final class PatientsRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function patient_cpf_br(string $cpf): string
    
    {
        $digits = is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'only_digits'])
            ? \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cpf)
            : preg_replace('/\D+/', '', $cpf);
        return \Prontoo\Domain\Patients\PatientPure::cpfBr($cpf, (string) $digits);
    
    }

    public static function patient_record_type_label(string $type): string
    {
        $customTabLabel = null;
        $normalized = trim($type);
        if (str_starts_with($normalized, "tab_")) {
            $tabId = (int) substr($normalized, 4);
            if ($tabId > 0) {
                try {
                    $customTabLabel = PatientComposition::tabLabel($tabId);
                } catch (Throwable $error) {
                    error_log("[Prontoo recoverable " . __FUNCTION__ . "] " . $error->getMessage());
                }
            }
        }
        return \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_record_type_label(
            $type,
            $customTabLabel,
        );
    }

    public static function patient_extra_tabs(int $cid, int $patientId): array
    
    {
    
        \Prontoo\Infrastructure\Patients\PatientsInfrastructureOperations01::patient_tabs_ensure_schema();
        $rows = PatientComposition::activeTabs($cid, $patientId);
        foreach ($rows as &$r) {
            $r["id"] = (int) $r["id"];
            $r["label"] = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_tab_label_clean((string) ($r["label"] ?? ""));
            $r["icon_name"] = \Prontoo\Domain\Patients\PatientsDomainOperations01::normalize_patient_tab_icon(
                (string) ($r["icon_name"] ?? ""),
            );
            $r["record_type"] = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_tab_record_type((int) $r["id"]);
            $r["tab_key"] = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_tab_key((int) $r["id"]);
        }
        unset($r);
        return $rows;
    
    }

    public static function patient_identity_complete(array $p): bool
    
    {
    
        return mb_trim((string) ($p["full_name"] ?? "")) !== "" &&
            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($p["cpf"] ?? ""))) &&
            \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date((string) ($p["birth_date"] ?? ""));
    
    }

    public static function patient_invoice_registration_missing_fields(array $p): array
    
    {
    
        $missing = [];
        if (mb_trim((string) ($p["full_name"] ?? "")) === "") {
            $missing[] = "nome completo";
        }
        if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($p["cpf"] ?? "")))) {
            $missing[] = "CPF";
        }
        if (!\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date((string) ($p["birth_date"] ?? ""))) {
            $missing[] = "nascimento";
        }
        if (mb_trim((string) ($p["phone"] ?? "")) === "") {
            $missing[] = "telefone";
        }
        $email = mb_trim((string) ($p["email"] ?? ""));
        if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $missing[] = "e-mail";
        }
        if (strlen(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($p["address_zip"] ?? ""))) !== 8) {
            $missing[] = "CEP";
        }
        if (mb_trim((string) ($p["address"] ?? "")) === "") {
            $missing[] = "logradouro";
        }
        if (mb_trim((string) ($p["address_number"] ?? "")) === "") {
            $missing[] = "número";
        }
        if (mb_trim((string) ($p["address_neighborhood"] ?? "")) === "") {
            $missing[] = "bairro";
        }
        if (mb_trim((string) ($p["address_city"] ?? "")) === "") {
            $missing[] = "cidade";
        }
        if (mb_trim((string) ($p["address_state"] ?? "")) === "") {
            $missing[] = "UF";
        }
        return array_values(array_unique($missing));
    
    }

    public static function patient_invoice_registration_complete(array $p): bool
    
    {
    
        return \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_invoice_registration_missing_fields($p) === [];
    
    }

    public static function patient_invoice_registration_alert_message(array $p): string
    
    {
    
        $missing = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_invoice_registration_missing_fields($p);
        return "Atualização cadastral obrigatória antes de agendar a próxima consulta" .
            ($missing ? ": informe " . implode(", ", $missing) . "." : ".");
    
    }

    public static function patient_legal_guardians(int $cid, int $patientId): array
    
    {
        if ($cid <= 0 || $patientId <= 0) {
            return [];
        }
        \Prontoo\Infrastructure\Patients\PatientsInfrastructureOperations01::patient_guardians_ensure_schema();
        return PatientComposition::legalGuardians($cid, $patientId);
    
    }

    public static function patient_primary_legal_guardian(int $cid, int $patientId): ?array
    
    {
    
        $g = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_legal_guardians($cid, $patientId);
        return $g[0] ?? null;
    
    }

    public static function patient_has_legal_guardian(int $cid, int $patientId): bool
    
    {
        if ($cid <= 0 || $patientId <= 0) {
            return false;
        }
        \Prontoo\Infrastructure\Patients\PatientsInfrastructureOperations01::patient_guardians_ensure_schema();
        return PatientComposition::hasLegalGuardian($cid, $patientId);
    
    }

    public static function patient_profile_status(array $p, array $guardians): array
    
    {
    
        if (!\Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_identity_complete($p)) {
            return [
                "level" => "bad",
                "label" => "Ficha incompleta",
                "message" =>
                    "CPF, Nome Completo e Nascimento são obrigatórios para considerar o cadastro mínimo válido.",
            ];
        }
        if (!\Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_invoice_registration_complete($p)) {
            return [
                "level" => "warn",
                "label" => "Atualização obrigatória",
                "message" => \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_invoice_registration_alert_message($p),
            ];
        }
        if (\Prontoo\Domain\Patients\PatientsDomainOperations01::patient_is_minor($p) && !$guardians) {
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
        if (\Prontoo\Domain\Patients\PatientsDomainOperations01::patient_is_minor($p)) {
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

    public static function patient_sensitive_block_reason(int $cid, int $patientId): ?string
    
    {
    
        if ($cid <= 0 || $patientId <= 0) {
            return null;
        }
        $p = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
            "SELECT pp.id,pp.clinic_id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,pp.registration_needs_update,p.full_name,p.cpf,p.birth_date FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1",
            [$patientId, $cid],
        );
        if (!$p) {
            return null;
        }
        if (!\Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_identity_complete($p)) {
            return "A ficha do paciente está incompleta. Informe CPF, Nome Completo e Nascimento antes de emitir documentos.";
        }
        if (!\Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_invoice_registration_complete($p)) {
            return "A ficha fiscal do paciente está incompleta. " .
                \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_invoice_registration_alert_message($p);
        }
        if (\Prontoo\Domain\Patients\PatientsDomainOperations01::patient_is_minor($p) && !\Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_has_legal_guardian($cid, $patientId)) {
            return "Paciente menor de idade sem Responsável Legal vinculado. Cadastre o responsável legal antes de emitir documentos ou concluir atos sensíveis.";
        }
        return null;
    
    }

    public static function patient_guardian_form_html(
        array $guardian = [],
        string $submit = "Salvar responsável legal",
    ): string 
    {
    
        $gid = (int) ($guardian["id"] ?? 0);
        $rel = \Prontoo\Domain\Patients\PatientsDomainOperations01::normalize_guardian_relationship(
            (string) ($guardian["relationship"] ?? "mae"),
        );
        $isPrimary =
            $gid <= 0 || (int) ($guardian["is_primary"] ?? 0) === 1
                ? " checked"
                : "";
        return '<form method="post" class="compact patient-guardian-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="save_legal_guardian"><input type="hidden" name="guardian_id" value="' .
            $gid .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome completo do responsável legal",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "guardian_name",
                    "text",
                    $guardian["full_name"] ?? "",
                    'required autocomplete="name"',
                ),
            ) .
            '<div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF do responsável",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "guardian_cpf",
                    "text",
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($guardian["cpf"] ?? ""),
                    'required inputmode="numeric" data-cpf-mask',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Vínculo jurídico",
                "guardian_relationship",
                \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_guardian_relationship_options(),
                $rel,
                "required",
            ) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Telefone",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "guardian_phone",
                    "text",
                    $guardian["phone"] ?? "",
                    'inputmode="tel"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "E-mail",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("guardian_email", "email", $guardian["email"] ?? ""),
            ) .
            "</div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Documento ou observação de comprovação",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "guardian_document_note",
                    "text",
                    $guardian["document_note"] ?? "",
                    'maxlength="180" placeholder="Ex.: certidão, guarda, tutela, autorização"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Observações sobre guarda, restrição ou autorização",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("guardian_notes", $guardian["notes"] ?? "", 'rows="4"'),
            ) .
            '<label class="checkline"><input type="checkbox" name="guardian_primary" value="1"' .
            $isPrimary .
            "><span>Marcar como responsável principal</span></label>" .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions($submit) .
            "</form>";
    
    }

    public static function patient_legal_guardian_card(
        int $cid,
        int $patientId,
        array $p,
        array $guardians,
        bool $canManage = true,
    ): string 
    {
    
        $minor = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_is_minor($p);
        if (!$minor && !$guardians) {
            return "";
        }
        $status = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_profile_status($p, $guardians);
        $relOpts = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_guardian_relationship_options();
        $html =
            '<section class="patient-guardian-card ' .
            ($minor && !$guardians ? "is-pending" : "is-ok") .
            '"><div class="patient-section-title"><div><h2>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("supervisor_account") .
            "<span>Responsável Legal</span></h2><p>" .
            ($minor
                ? "Obrigatório para paciente menor de idade."
                : "Vínculo administrativo registrado na ficha.") .
            '</p></div><span class="pill ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($status["level"]) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($status["label"]) .
            "</span></div>";
        if ($minor && !$guardians) {
            $html .=
                '<div class="flash warn"><strong>Ficha incompleta.</strong> CPF, Nome Completo e Nascimento já permitem manter o cadastro salvo; o Responsável Legal precisa ser vinculado para liberar documentos e atos sensíveis.</div>';
        }
        if ($guardians) {
            $html .= '<div class="guardian-list">';
            foreach ($guardians as $g) {
                $rel = $relOpts[(string) $g["relationship"]] ?? "Responsável";
                $meta = $rel . " · CPF " . \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) $g["cpf"]);
                if (!empty($g["phone"])) {
                    $meta .= " · " . \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) $g["phone"]);
                }
                if (!empty($g["email"])) {
                    $meta .= " · " . (string) $g["email"];
                }
                $html .=
                    '<article class="guardian-row"><div class="guardian-main"><span class="guardian-avatar">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("family_restroom") .
                    "</span><div><strong>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($g["full_name"]) .
                    "</strong><small>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($meta) .
                    "</small>" .
                    (!empty($g["document_note"])
                        ? "<small>Documento: " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($g["document_note"]) . "</small>"
                        : "") .
                    (!empty($g["notes"]) ? "<p>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($g["notes"]) . "</p>" : "") .
                    '</div></div><div class="guardian-actions">' .
                    ((int) $g["is_primary"] === 1
                        ? '<span class="pill ok">Principal</span>'
                        : "");
                if ($canManage) {
                    $html .=
                        '<details class="guardian-edit"><summary class="ghost small cmdlike">' .
                        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Alterar", "edit") .
                        "</summary>" .
                        \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_guardian_form_html($g, "Salvar responsável") .
                        '</details><form method="post" class="inline" onsubmit="return confirm(\'Remover este responsável legal da ficha?\')">' .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
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
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label(
                    $guardians
                        ? "Adicionar responsável"
                        : "Cadastrar responsável legal",
                    "person_add",
                ) .
                "</summary>" .
                \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_guardian_form_html([], "Salvar responsável legal") .
                "</details>";
        }
        return $html . "</section>";
    
    }

    public static function require_patient_in_clinic(int $cid, int $patientId): array
    
    {
    
        $row = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::require_same_clinic_entity(
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

    public static function patient_location_defaults(int $cid, array $p = []): array
    
    {
    
        $uf = mb_trim((string) ($p["address_state"] ?? ""));
        $city = mb_trim((string) ($p["address_city"] ?? ""));
        $ibge = mb_trim((string) ($p["address_city_ibge"] ?? ""));
        if ($uf === "" && $cid > 0) {
            $cl =
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                    "SELECT address_state,address_city,address_city_ibge FROM pi_clinics WHERE id=?",
                    [$cid],
                ) ?:
                [];
            $uf = mb_trim((string) ($cl["address_state"] ?? "MT"));
            if ($city === "") {
                $city = mb_trim((string) ($cl["address_city"] ?? ""));
            }
            if ($ibge === "") {
                $ibge = mb_trim((string) ($cl["address_city_ibge"] ?? ""));
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

    public static function patient_zip_input(array $p = []): string
    
    {
    
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
            "CEP",
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                "address_zip",
                "text",
                \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::mask_cep((string) ($p["address_zip"] ?? "")),
                'required inputmode="numeric" maxlength="9" autocomplete="postal-code" data-patient-cep',
            ),
        );
    
    }

    public static function patient_address_fields(int $cid, array $p = []): string
    
    {
    
        $loc = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_location_defaults($cid, $p);
        $uf = (string) $loc["address_state"];
        $city = (string) $loc["address_city"];
        $cityIbge = (string) $loc["address_city_ibge"];
        $cityOptions =
            $city !== ""
                ? '<option value="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($city) .
                    '" data-ibge="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cityIbge) .
                    '" selected>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($city) .
                    "</option>"
                : '<option value="">Escolha primeiro o estado</option>';
        $states = ["" => "Escolha o estado"] + \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::br_states();
        return '<section class="patient-address-block" data-patient-address-block>' .
            \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_zip_input($p) .
            '<div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Logradouro",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "address",
                    "text",
                    $p["address"] ?? "",
                    'required maxlength="255" autocomplete="address-line1" data-patient-address-street',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Número",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "address_number",
                    "text",
                    $p["address_number"] ?? "",
                    'required maxlength="20" autocomplete="address-line2" data-patient-address-number',
                ),
            ) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Bairro",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "address_neighborhood",
                    "text",
                    $p["address_neighborhood"] ?? "",
                    'required maxlength="120" data-patient-address-neighborhood',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Complemento",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "address_complement",
                    "text",
                    $p["address_complement"] ?? "",
                    'maxlength="120" autocomplete="address-line3" data-patient-address-complement',
                ),
            ) .
            '</div><div class="two patient-location-fields">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Estado",
                "address_state",
                $states,
                $uf,
                "required data-br-state data-patient-address-state",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Cidade",
                '<select name="address_city" required data-br-city data-selected-city="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($city) .
                    '" data-patient-address-city>' .
                    $cityOptions .
                    '</select><input type="hidden" name="address_city_ibge" value="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cityIbge) .
                    '" data-br-city-ibge data-patient-address-ibge>',
            ) .
            "</div></section>";
    
    }

    public static function patient_location_fields(int $cid, array $p = []): string
    
    {
    
        return \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_address_fields($cid, $p);
    
    }
}
