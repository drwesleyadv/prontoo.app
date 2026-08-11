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

final class PatientsRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function patient_location_from_post(int $cid): array
    
    {
    
        $zip = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_POST["address_zip"] ?? ""));
        $street = mb_trim((string) ($_POST["address"] ?? ""));
        $number = mb_trim((string) ($_POST["address_number"] ?? ""));
        $neighborhood = mb_trim((string) ($_POST["address_neighborhood"] ?? ""));
        $complement = mb_trim((string) ($_POST["address_complement"] ?? ""));
        $uf = strtoupper(mb_trim((string) ($_POST["address_state"] ?? "")));
        $city = mb_trim((string) ($_POST["address_city"] ?? ""));
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
        if (!isset(\Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::br_states()[$uf])) {
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

    public static function mask_cep(string $cep): string
    
    {
    
        $d = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cep);
        if (strlen($d) !== 8) {
            return $cep;
        }
        return substr($d, 0, 5) . "-" . substr($d, 5);
    
    }

    public static function patient_invoice_contact_from_post(): array
    
    {
    
        $email = mb_trim((string) ($_POST["email"] ?? ""));
        $phone = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($_POST["phone"] ?? ""));
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

    public static function clinic_patient_exists(
        int $cid,
        int $patientId,
        bool $activeOnly = true,
    ): bool 
    {
    
        if ($cid <= 0 || $patientId <= 0) {
            return false;
        }
        return (bool) \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.02.clinic_patient_exists.01', [$patientId, $cid], ['activeOnly' => $activeOnly]);
    
    }

    public static function patient_options(int $cid): array
    
    {
    
        static $memo = [];
        if (isset($memo[$cid])) {
            return $memo[$cid];
        }
        $base = \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.02.patient_options.01', [$cid], [])->fetchAll();
        $persons = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "persons_name",
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($base, "person_id"),
        );
        $o = [];
        foreach ($base as $r) {
            $ps = $persons[(int) $r["person_id"]] ?? [];
            $o[(int) $r["id"]] = $ps["full_name"] ?? "Paciente #" . (int) $r["id"];
        }
        asort($o, SORT_NATURAL | SORT_FLAG_CASE);
        return $memo[$cid] = $o;
    
    }

    public static function patient_autosuggest_datalist(
        int $cid,
        string $id = "prontoo_patient_suggestions",
    ): string 
    {
    
        $limit = max(0, min(80, (int) ($_GET["patient_preload"] ?? 0)));
        if ($limit <= 0) {
            return '<datalist id="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($id) . '"></datalist>';
        }
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.02.patient_autosuggest_datalist.01', [$cid], ['limit' => $limit])->fetchAll();
        $h = '<datalist id="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($id) . '">';
        foreach ($rows as $r) {
            $birth = !empty($r["birth_date"])
                ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["birth_date"])
                : "Nascimento não informado";
            $value = (string) $r["full_name"] . " · " . $birth;
            $label = !empty($r["cpf"]) ? "CPF " . \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) $r["cpf"]) : $birth;
            $h .=
                '<option value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value) .
                '" label="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                '" data-patient-id="' .
                (int) $r["id"] .
                '" data-patient-name="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $r["full_name"]) .
                '" data-birth="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($r["birth_date"] ?? "")) .
                '" data-cpf="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) ($r["cpf"] ?? ""))) .
                '"></option>';
        }
        return $h . "</datalist>";
    
    }

    public static function patient_lookup_field(
        int $cid,
        string $hiddenName = "patient_link_id",
        string $hiddenValue = "",
        string $inputName = "patient_search",
    ): string 
    {
    
        $display = "";
        $pid = (int) $hiddenValue;
        if ($pid > 0) {
            $r = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.02.patient_lookup_field.01', [$pid, $cid], []);
            if ($r) {
                $display =
                    (string) $r["full_name"] .
                    (!empty($r["birth_date"])
                        ? " · " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["birth_date"])
                        : " · Nascimento não informado");
            }
        }
        return '<input type="hidden" name="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($hiddenName) .
            '" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($hiddenValue) .
            '" data-patient-id-target><input type="search" name="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($inputName) .
            '" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($display) .
            '" list="prontoo_patient_suggestions" placeholder="Busque por nome, CPF ou nascimento" autocomplete="off" spellcheck="false" data-ds-lookup="patient" aria-label="Buscar paciente por nome, CPF ou nascimento" data-patient-document-suggest data-patient-suggest-url="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient_suggest")) .
            '" aria-autocomplete="list">';
    
    }

    public static function resolve_patient_lookup_id(
        int $cid,
        int $postedId,
        string $search = "",
    ): int 
    {
    
        if ($postedId > 0) {
            $ok = (int) \Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.02.resolve_patient_lookup_id.01', [$postedId, $cid], []);
            if ($ok > 0) {
                return $ok;
            }
        }
        $search = trim($search);
        if ($search === "") {
            return 0;
        }
        $clean = mb_strtolower($search, "UTF-8");
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.02.resolve_patient_lookup_id.02', [$cid], [])->fetchAll();
        $exact = [];
        $nameExact = [];
        $starts = [];
        foreach ($rows as $r) {
            $birth = !empty($r["birth_date"])
                ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br((string) $r["birth_date"])
                : "Nascimento não informado";
            $display = mb_strtolower(
                (string) $r["full_name"] . " · " . $birth,
                "UTF-8",
            );
            $name = mb_strtolower((string) $r["full_name"], "UTF-8");
            $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($r["cpf"] ?? ""));
            if (
                $display === $clean ||
                ($cpf !== "" && \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($search) === $cpf)
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

    public static function patient_identity_by_cpf(string $cpf, int $cid): ?array
    
    {
    
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cpf);
        if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            return null;
        }
        try {
            $row = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.02.patient_identity_by_cpf.01', [$cpf, $cid, $cid, $cid], []);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log("[Prontoo patient CPF identity lookup] " . $e->getMessage());
            return null;
        }
    
    }

    public static function patient_lookup_payload(int $cid, string $cpf): array
    
    {
    
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cpf);
        if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            return [
                "ok" => false,
                "found" => false,
                "message" => "Informe um CPF válido.",
            ];
        }
        $p = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_identity_by_cpf($cpf, $cid);
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
            $active = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.02.patient_lookup_payload.01', [$cid, (int) $p["id"]], []);
        } catch (Throwable $e) {
            error_log("[Prontoo patient CPF active lookup] " . $e->getMessage());
        }
        try {
            $deleted = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.02.patient_lookup_payload.02', [$cid, (int) $p["id"]], []);
        } catch (Throwable $e) {
            error_log("[Prontoo patient CPF deleted lookup] " . $e->getMessage());
        }
        $birth = is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'app_date_input_from_storage'])
            ? \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($p["birth_date"] ?? "")
            : (preg_match("/^\d{4}-\d{2}-\d{2}/", (string) ($p["birth_date"] ?? ""))
                ? substr((string) $p["birth_date"], 0, 10)
                : "");
        $name = mb_trim((string) ($p["full_name"] ?? ""));
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
                ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => (int) $active["id"]])
                : "",
            "message" => $active
                ? "Este paciente já está cadastrado. Os dados foram recuperados; abra a ficha existente se quiser consultar ou alterar."
                : ($deleted
                    ? "Cadastro anterior encontrado como excluído. Os dados foram recuperados; ao salvar, o Prontoo reativará a ficha para revisão."
                    : "Dados encontrados e preenchidos automaticamente."),
            "name" => $name,
            "cpf" => \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_cpf_br((string) ($p["cpf"] ?? "")),
            "birth_date" => $birth,
        ];
    
    }

    public static function page_patient_lookup(): void
    
    {
    
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("X-Robots-Tag: noindex, nofollow");
        }
        try {
            $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
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
            if (!\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("patients")) {
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
                \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
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
            $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_GET["cpf"] ?? ""));
            echo json_encode(
                \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_lookup_payload($cid, $cpf),
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

    public static function patient_week_utc_range(int $cid): array
    
    {
    
        $today = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid);
        $zone = new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone(null, $cid));
        $dt = new DateTimeImmutable($today . " 00:00:00", $zone);
        $weekday = (int) $dt->format("N");
        $start = $dt->modify("-" . ($weekday - 1) . " days");
        $end = $start->modify("+7 days");
        return [
            (string) $start->setTimezone(new DateTimeZone("UTC"))->getTimestamp(),
            (string) $end->setTimezone(new DateTimeZone("UTC"))->getTimestamp(),
        ];
    
    }

    public static function patient_today_utc_range(int $cid): array
    
    {
    
        return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid), $cid);
    
    }

}
