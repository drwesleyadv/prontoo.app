<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Patients;

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

final class PatientsRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function patient_directory_status(array $r): array
    
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

    public static function patient_directory_card(array $r, int $cid = 0): string
    
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
        $timeRaw = mb_trim((string) ($r["today_appointment_start_at"] ?? ""));
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
        $lastRaw = mb_trim((string) ($r["last_consultation_at"] ?? ""));
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
        $emailLabel = mb_trim((string) ($p["email"] ?? ""));
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

    public static function patient_reception_story_time(null|string|int $value): string
    
    {
    
        $raw = mb_trim((string) ($value ?? ""));
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
            patient_reception_story_time($createdAt) .
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
                        if ($k === "phone" && function_exists("phone_br")) {
                            $v = phone_br($v);
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
}
