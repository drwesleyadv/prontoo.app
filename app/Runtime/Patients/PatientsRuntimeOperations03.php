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

final class PatientsRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function patient_directory_status(array $r): array
    
    {
    
        $guardians = (int) ($r["guardian_count"] ?? 0) > 0 ? [["id" => 1]] : [];
        $status = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_profile_status($r, $guardians);
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

    public static function patient_directory_card(array $r, int $cid = 0): string
    
    {
    
        $name = (string) ($r["full_name"] ?? "Paciente #" . ($r["id"] ?? ""));
        $birth = (string) ($r["birth_date"] ?? "");
        $birthLabel = $birth !== "" ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($birth) : "Nascimento não informado";
        $age = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_age_years($birth);
        $ageLabel = $age !== null ? $age . " anos" : "Idade não informada";
        $cpf = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? ""));
        if (trim($cpf) === "") {
            $cpf = "CPF não informado";
        }
        $phone = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($r["phone"] ?? ""));
        if (trim($phone) === "") {
            $phone = "Sem telefone";
        }
        [$level, $label, $message] = \Prontoo\Runtime\Patients\PatientsRuntimeOperations03::patient_directory_status($r);
        $timeRaw = mb_trim((string) ($r["today_appointment_start_at"] ?? ""));
        $timePill = "";
        $timeLabel = "";
        $journeyHtml = "";
        $journeySearch = "";
        if ($timeRaw !== "" && $timeRaw !== "0") {
            $timeLabel = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br($timeRaw, $cid);
            $apptMini = [
                "status" => (string) ($r["today_appointment_status"] ?? "agendado"),
                "start_at" => $timeRaw,
                "arrived_at" => $r["today_appointment_arrived_at"] ?? null,
                "consultation_started_at" =>
                    $r["today_appointment_started_at"] ?? null,
                "consultation_finished_at" =>
                    $r["today_appointment_finished_at"] ?? null,
            ];
            $role = (string) (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx()["role"] ?? "" ?: "");
            $jm = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_meta($apptMini, $role);
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
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($timeLabel) .
                    '</span></span><span class="pill ' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($jm["class"]) .
                    ' patient-journey-pill">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($jm["icon"]) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($jm["label"]) .
                    "</span>";
            }
            $journeyHtml = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_compact_html($apptMini, $role);
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
        $statusTitle = $message !== "" ? ' title="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($message) . '"' : "";
        $created = !empty($r["created_at"]) ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) $r["created_at"]) : "";
        $createdHtml =
            $created !== ""
                ? '<span class="patient-card-created">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                    "<span>Cadastrado em " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($created) .
                    "</span></span>"
                : "";
        $lastRaw = mb_trim((string) ($r["last_consultation_at"] ?? ""));
        $lastHtml = "";
        if ($lastRaw !== "" && $lastRaw !== "0") {
            $lastLabel = is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'app_date_br'])
                ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_date_br($lastRaw, $cid)
                : \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($lastRaw);
            if ($lastLabel !== "" && $lastLabel !== "—") {
                $lastHtml =
                    '<span class="patient-card-last-consultation" title="Última consulta registrada">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("stethoscope") .
                    "<span>Última consulta: " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($lastLabel) .
                    "</span></span>";
            }
        }
        return '<article class="patient-card-row ds-person-row ds-patient-row patient-status-' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($level) .
            '" data-patient-row data-patient-search="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($searchData) .
            '">' .
            '<span class="patient-card-avatar ds-person-avatar" aria-hidden="true">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("personal_injury") .
            "</span>" .
            '<div class="patient-card-main ds-person-main"><div class="patient-card-title ds-person-title">' .
            $timePill .
            "<strong>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
            '</strong><span class="pill patient-status-pill ds-status-pill ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($level) .
            '"' .
            $statusTitle .
            ">" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span></div>" .
            '<div class="patient-card-meta ds-person-meta"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("cake") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($birthLabel) .
            " · " .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($ageLabel) .
            "</span></span><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cpf) .
            "</span></span><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("call") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($phone) .
            "</span></span>" .
            $lastHtml .
            $createdHtml .
            $journeyHtml .
            "</div></div>" .
            '<div class="patient-card-actions ds-person-actions"><a class="primary small" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => (int) $r["id"]]) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("folder_open") .
            "<span>Abrir ficha</span></a></div>" .
            "</article>";
    
    }

    public static function patient_profile_overview(
        array $p,
        array $profileStatus,
        int $appointmentCount,
        int $documentCount,
        int $totalConsultations,
        string $firstConsultationLabel,
    ): string 
    {
    
        $name = mb_trim((string) ($p["full_name"] ?? "Paciente"));
        $birth = mb_trim((string) ($p["birth_date"] ?? ""));
        $age = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_age_years($birth);
        $birthLabel = $birth !== "" ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($birth) : "Nascimento não informado";
        if ($age !== null) {
            $birthLabel .= " · " . $age . " anos";
        }
        $cpfLabel = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) ($p["cpf"] ?? ""));
        if (trim($cpfLabel) === "") {
            $cpfLabel = "CPF não informado";
        }
        $phoneLabel = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($p["phone"] ?? ""));
        if (trim($phoneLabel) === "") {
            $phoneLabel = "Telefone não informado";
        }
        $emailLabel = mb_trim((string) ($p["email"] ?? ""));
        if ($emailLabel === "") {
            $emailLabel = "E-mail não informado";
        }
        $cityLabel = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::city_state_label(
            $p["address_city"] ?? "",
            $p["address_state"] ?? "",
        );
        if (trim($cityLabel) === "") {
            $cityLabel = "Cidade não informada";
        }
        $updated = !empty($p["updated_at"])
            ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_card_full_br((string) $p["updated_at"])
            : \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_card_full_br((string) ($p["created_at"] ?? ""));
        $level = (string) ($profileStatus["level"] ?? "ok");
        $label = (string) ($profileStatus["label"] ?? "Cadastro");
        $message = (string) ($profileStatus["message"] ?? "");
        $statusTitle = $message !== "" ? ' title="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($message) . '"' : "";
        return '<section class="patient-profile-overview ds-patient-profile" aria-label="Resumo da ficha do paciente">' .
            '<div class="patient-profile-id">' .
            '<span class="patient-profile-avatar" aria-hidden="true">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("personal_injury") .
            "</span>" .
            '<div class="patient-profile-copy"><span class="eyebrow">Ficha do paciente</span><h2>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
            "</h2><p>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($birthLabel) .
            " · " .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cpfLabel) .
            "</p></div>" .
            '<span class="pill patient-profile-status ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($level) .
            '"' .
            $statusTitle .
            ">" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span>" .
            "</div>" .
            '<div class="patient-profile-facts" aria-label="Dados principais">' .
            '<span class="patient-profile-fact">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("call") .
            "<small>Telefone</small><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($phoneLabel) .
            "</b></span>" .
            '<span class="patient-profile-fact">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("mail") .
            "<small>E-mail</small><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($emailLabel) .
            "</b></span>" .
            '<span class="patient-profile-fact">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("location_on") .
            "<small>Cidade</small><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cityLabel) .
            "</b></span>" .
            '<span class="patient-profile-fact">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("update") .
            "<small>Atualização</small><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($updated) .
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($firstConsultationLabel) .
            "</b></span>" .
            "</div>" .
            "</section>";
    
    }

    public static function patient_reception_story_time(null|string|int $value): string
    
    {
    
        $raw = mb_trim((string) ($value ?? ""));
        if ($raw === "") {
            return "data não informada";
        }
        $dt = null;
        try {
            if (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'app_db_utc_to_local'])) {
                $dt = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($raw);
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
        $months = is_callable([\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::class, 'prontoo_months_br'])
            ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::prontoo_months_br()
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

    public static function patient_reception_story_title(
        string $who,
        null|string|int $createdAt,
    ): string 
    {
    
        $who = trim($who);
        if ($who === "") {
            $who = "Recepção";
        }
        return "Falou com " .
            $who .
            " em " .
            \Prontoo\Runtime\Patients\PatientsRuntimeOperations03::patient_reception_story_time($createdAt) .
            ".";
    
    }

    public static function patient_reception_history_items(
        int $cid,
        int $patientId,
        array $p,
    ): array 
    {
    
        if ($cid <= 0 || $patientId <= 0) {
            return [];
        }
        if (is_callable([\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::class, 'ensure_lead_events_schema'])) {
            \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations03::ensure_lead_events_schema();
        }
        $personId = (int) ($p["person_id"] ?? 0);
        $phoneDigits = substr(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($p["phone"] ?? "")), 0, 11);
        try {
            $readModel = PatientComposition::receptionHistory(
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
                $who = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name(
                    $users[(int) ($lead["created_by"] ?? 0)]["name"] ?? "Recepção",
                );
                $body = mb_trim((string) ($lead["notes"] ?? ""));
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
                    $v = mb_trim((string) ($lead[$k] ?? ""));
                    if ($v !== "") {
                        if ($k === "phone" && is_callable([\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::class, 'phone_br'])) {
                            $v = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($v);
                        }
                        $metaParts[] = ["label" => $label, "value" => $v];
                    }
                }
                $stage = mb_trim((string) ($lead["stage"] ?? ""));
                if ($stage !== "") {
                    $metaParts[] = ["label" => "Etapa", "value" => $stage];
                }
                $items[] = [
                    "icon" => "forum",
                    "time" => "",
                    "title" => \Prontoo\Runtime\Patients\PatientsRuntimeOperations03::patient_reception_story_title(
                        $who,
                        (string) ($lead["created_at"] ?? ""),
                    ),
                    "body" => $body,
                    "meta" => "",
                    "html" => \Prontoo\Presentation\Patients\PatientsPresentationOperations01::patient_reception_meta_chips_html($metaParts),
                    "class" => "patient-reception-event patient-reception-story",
                ];
                continue;
            }
            foreach ($leadEvents as $ev) {
                $who = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name(
                    $users[(int) ($ev["created_by"] ?? 0)]["name"] ?? "Recepção",
                );
                $body = mb_trim((string) ($ev["body"] ?? ""));
                if ($body === "") {
                    $body = "Contato registrado sem observação adicional.";
                }
                $stageFrom = mb_trim((string) ($ev["stage_from"] ?? ""));
                $stageTo = mb_trim((string) ($ev["stage_to"] ?? ""));
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
                    $v = mb_trim((string) ($ev[$k] ?? ""));
                    if ($v !== "") {
                        if ($k === "phone" && is_callable([\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::class, 'phone_br'])) {
                            $v = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($v);
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
                    "title" => \Prontoo\Runtime\Patients\PatientsRuntimeOperations03::patient_reception_story_title(
                        $who,
                        (string) ($ev["created_at"] ?? ""),
                    ),
                    "body" => $body,
                    "meta" => "",
                    "html" => \Prontoo\Presentation\Patients\PatientsPresentationOperations01::patient_reception_meta_chips_html($metaParts),
                    "class" => "patient-reception-event patient-reception-story",
                ];
            }
        }
        return $items;
    
    }
}
