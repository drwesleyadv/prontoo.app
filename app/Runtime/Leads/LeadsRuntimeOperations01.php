<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Leads;

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
use Prontoo\Domain\Leads\LeadsDomainOperations01;

final class LeadsRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function lead_phone_digits(string $phone): string
    
    {
    
        return substr(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($phone), 0, 11);
    
    }

    public static function lead_cpf_br(string $cpf): string
    
    {
    
        $d = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cpf);
        if (strlen($d) !== 11) {
            return $cpf;
        }
        return substr($d, 0, 3) .
            "." .
            substr($d, 3, 3) .
            "." .
            substr($d, 6, 3) .
            "-" .
            substr($d, 9, 2);
    
    }

    public static function lead_find_by_phone(
        int $cid,
        string $phoneDigits,
        int $excludeId = 0,
    ): ?array 
    {
    
        $phoneDigits = self::lead_phone_digits($phoneDigits);
        if ($cid <= 0 || $phoneDigits === "") {
            return null;
        }
        \Prontoo\Runtime\Operational\OperationalComposition::leads()->ensureSchema("lead_events");
        $params = [$cid, $phoneDigits, $phoneDigits];
        if ($excludeId > 0) {
            $params[] = $excludeId;
        }
        return \Prontoo\Runtime\Operational\OperationalComposition::leads()->row('operational.leads.01.lead_find_by_phone.01', $params, ['excludeLead' => $excludeId > 0]) ?:
            null;
    
    }

    public static function lead_event_create(
        int $cid,
        int $leadId,
        int $uid,
        string $stageFrom,
        string $stageTo,
        string $phone,
        string $source,
        string $interest,
        ?string $next,
        string $body,
        string $eventType = "contato",
    ): void 
    {
    
        if ($cid <= 0 || $leadId <= 0) {
            return;
        }
        \Prontoo\Runtime\Operational\OperationalComposition::leads()->ensureSchema("lead_events");
        $eventType = preg_replace("/[^a-z0-9_\-]/i", "", $eventType) ?: "contato";
        $stageFrom =
            trim($stageFrom) !== "" ? LeadsDomainOperations01::lead_stage_normalize($stageFrom) : "";
        $stageTo = trim($stageTo) !== "" ? LeadsDomainOperations01::lead_stage_normalize($stageTo) : "";
        $body = trim($body);
        if ($body === "") {
            $body =
                $eventType === "cadastro"
                    ? "Contato inicial registrado sem observação adicional."
                    : "Contato registrado sem observação adicional.";
        }
        \Prontoo\Runtime\Operational\OperationalComposition::leads()->result('operational.leads.01.lead_event_create.01', [
                $cid,
                $leadId,
                $eventType,
                $stageFrom,
                $stageTo,
                $phone,
                $source,
                $interest,
                $next,
                $body,
                $uid,
            ], []);
    
    }

    public static function lead_prepare_person_for_patient(
        array $lead,
        array $data,
        int $cid,
    ): int 
    {
    
        $leadId = (int) ($lead["id"] ?? 0);
        $name = mb_trim((string) ($data["name"] ?? ($lead["name"] ?? "")));
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($data["cpf"] ?? ""));
        $birth = mb_trim((string) ($data["birth_date"] ?? ""));
        if ($name === "") {
            throw new RuntimeException(
                "Informe o nome do interessado antes de tornar paciente.",
            );
        }
        if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            throw new RuntimeException(
                "Informe um CPF válido para tornar paciente.",
            );
        }
        if (!\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($birth)) {
            throw new RuntimeException(
                "Informe a data de nascimento para tornar paciente.",
            );
        }
        $pid = (int) ($lead["person_id"] ?? 0);
        $existingByCpf =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::leads()->scalar('operational.leads.01.lead_prepare_person_for_patient.01', [$cpf], []) ??
                0);
        if ($existingByCpf > 0 && $existingByCpf !== $pid) {
            $identity = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_identity_immutable_values(
                $existingByCpf,
                $cpf,
                $birth,
                true,
            );
            $birth = (string) $identity["birth_date"];
            $sig = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_value($cpf, $name, $birth);
            \Prontoo\Runtime\Operational\OperationalComposition::leads()->result('operational.leads.01.lead_prepare_person_for_patient.02', [$name, $sig, $cid, $existingByCpf], []);
            if ($leadId > 0) {
                \Prontoo\Runtime\Operational\OperationalComposition::leads()->result('operational.leads.01.lead_prepare_person_for_patient.03', [$existingByCpf, $name, $leadId, $cid], []);
            }
            return $existingByCpf;
        }
        if ($pid > 0) {
            $identity = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_identity_immutable_values($pid, $cpf, $birth, true);
            $cpf = (string) $identity["cpf"];
            $birth = (string) $identity["birth_date"];
            $sig = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_value($cpf, $name, $birth);
            \Prontoo\Runtime\Operational\OperationalComposition::leads()->result('operational.leads.01.lead_prepare_person_for_patient.04', [$name, $cpf, $birth, $sig, $cid, $pid], []);
            return $pid;
        }
        $pid = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::upsert_person($name, $cpf, $birth);
        if ($leadId > 0) {
            \Prontoo\Runtime\Operational\OperationalComposition::leads()->result('operational.leads.01.lead_prepare_person_for_patient.05', [$pid, $name, $leadId, $cid], []);
        }
        return $pid;
    
    }

    public static function lead_patient_by_cpf(int $cid, string $cpf): ?array
    
    {
    
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cpf);
        if ($cid <= 0 || !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            return null;
        }
        $row = \Prontoo\Runtime\Operational\OperationalComposition::leads()->row('operational.leads.01.lead_patient_by_cpf.01', [$cid, $cpf], []);
        return $row ?: null;
    
    }

    public static function lead_patient_by_phone(int $cid, string $phoneDigits): ?array
    
    {
    
        $phoneDigits = self::lead_phone_digits($phoneDigits);
        if ($cid <= 0 || strlen($phoneDigits) < 10) {
            return null;
        }
        $row = \Prontoo\Runtime\Operational\OperationalComposition::leads()->row('operational.leads.01.lead_patient_by_phone.01', [$cid, $phoneDigits], []);
        return $row ?: null;
    
    }

    public static function lead_history_html(
        array $items,
        array $users,
        array $stageLabels,
    ): string 
    {
    
        if (!$items) {
            return '<div class="lead-history lead-history-compact empty-history"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("history") .
                "</span><small>Sem ocorrências registradas.</small></div>";
        }
        $html =
            '<div class="lead-history lead-history-compact" aria-label="Histórico de ocorrências">';
        foreach ($items as $ev) {
            $who = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name(
                $users[(int) ($ev["created_by"] ?? 0)]["name"] ?? "Sistema",
            );
            $when = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br((string) ($ev["created_at"] ?? ""));
            $type = (string) ($ev["event_type"] ?? "contato");
            $body = mb_trim((string) ($ev["body"] ?? ""));
            if ($body === "") {
                $body = "Sem observações.";
            }
            $bodyShort =
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(mb_substr($body, 0, 220)) . (mb_strlen($body) > 220 ? "…" : "");
            $summary = "Falou com " . $who . " em " . $when . ".";
            $html .=
                "<article><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($type === "cadastro" ? "person_add" : "forum") .
                "</span><div><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($summary) .
                "</b><p>" .
                $bodyShort .
                "</p></div></article>";
        }
        return $html . "</div>";
    
    }

    public static function page_lead_lookup(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("leads");
        $cid = (int) $c["clinic_id"];
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        if (\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("lead_lookup"), 40, 300)) {
            http_response_code(429);
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => "Aguarde alguns instantes.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $phoneDigits = self::lead_phone_digits((string) ($_GET["phone"] ?? ""));
        if (strlen($phoneDigits) < 10) {
            echo json_encode(
                [
                    "ok" => true,
                    "found" => false,
                    "message" => "Informe o telefone para continuar.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $lead = self::lead_find_by_phone($cid, $phoneDigits);
        if (!$lead) {
            $patient = self::lead_patient_by_phone($cid, $phoneDigits);
            if ($patient) {
                echo json_encode(
                    [
                        "ok" => true,
                        "found" => true,
                        "patient_found" => true,
                        "already_patient" => true,
                        "patient_id" => (int) $patient["patient_id"],
                        "open_url" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", [
                            "id" => (int) $patient["patient_id"],
                        ]),
                        "message" =>
                            "Paciente já cadastrado localizado pelo telefone. Abra a ficha para registrar o atendimento.",
                        "name" => (string) ($patient["full_name"] ?? ""),
                        "phone" =>
                            (string) ($patient["phone"] ?? \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($phoneDigits)),
                        "source" => "Recepção",
                        "interest" => "Paciente cadastrado",
                        "stage" => "convertido",
                        "next_action_at" => "",
                        "notes" => "",
                    ],
                    JSON_UNESCAPED_UNICODE,
                );
                return;
            }
            echo json_encode(
                [
                    "ok" => true,
                    "found" => false,
                    "message" => "Telefone sem histórico. Continue o cadastro.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $next = "";
        if (!empty($lead["next_action_at"])) {
            $next = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local_input(
                (string) $lead["next_action_at"],
                $cid,
            );
        }
        echo json_encode(
            [
                "ok" => true,
                "found" => true,
                "message" =>
                    "Telefone já registrado. Recuperamos os dados para continuar o histórico de ocorrências.",
                "lead_id" => (int) $lead["id"],
                "name" => (string) ($lead["name"] ?? ""),
                "phone" => (string) ($lead["phone"] ?? \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($phoneDigits)),
                "source" => (string) ($lead["source"] ?? ""),
                "interest" => (string) ($lead["interest"] ?? ""),
                "stage" => LeadsDomainOperations01::lead_stage_normalize(
                    (string) ($lead["stage"] ?? "em_aberto"),
                ),
                "next_action_at" => $next,
                "notes" => (string) ($lead["notes"] ?? ""),
            ],
            JSON_UNESCAPED_UNICODE,
        );
    
    }

    public static function page_lead_patient_lookup(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("leads");
        $cid = (int) $c["clinic_id"];
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        if (
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("lead_patient_lookup"),
                60,
                300,
            )
        ) {
            http_response_code(429);
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => "Aguarde alguns instantes.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_GET["cpf"] ?? ""));
        if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => "Informe um CPF válido.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $person = \Prontoo\Runtime\Operational\OperationalComposition::leads()->row('operational.leads.01.page_lead_patient_lookup.01', [$cpf, $cid, $cid, $cid], []);
        if (!$person) {
            echo json_encode(
                [
                    "ok" => true,
                    "found" => false,
                    "already_patient" => false,
                    "message" => "CPF válido. Complete Nome Completo e Nascimento.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $patient = \Prontoo\Runtime\Operational\OperationalComposition::leads()->row('operational.leads.01.page_lead_patient_lookup.02', [$cid, (int) $person["id"]], []);
        echo json_encode(
            [
                "ok" => true,
                "found" => true,
                "already_patient" => (bool) $patient,
                "patient_id" => $patient ? (int) $patient["id"] : null,
                "open_url" => $patient
                    ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => (int) $patient["id"]])
                    : "",
                "message" => $patient
                    ? "Este interessado já era paciente. Ao concluir, o interesse será arquivado."
                    : "Dados encontrados e preenchidos automaticamente.",
                "name" => (string) ($person["full_name"] ?? ""),
                "cpf" => self::lead_cpf_br((string) ($person["cpf"] ?? "")),
                "birth_date" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(
                    $person["birth_date"] ?? "",
                ),
            ],
            JSON_UNESCAPED_UNICODE,
        );
    
    }
}
