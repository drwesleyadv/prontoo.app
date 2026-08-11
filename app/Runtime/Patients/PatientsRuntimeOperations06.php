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

final class PatientsRuntimeOperations06
{
    private function __construct()
    {
    }

    public static function patient_appointment_real_duration_label(
        array $a,
        int $cid = 0,
        array $context = [],
    ): string 
    {
    
        $started = $a["consultation_started_at"] ?? null;
        $finished = $a["consultation_finished_at"] ?? null;
        if (!empty($started) && !empty($finished)) {
            return \Prontoo\Runtime\Patients\PatientsRuntimeOperations05::patient_appointment_duration_label($started, $finished);
        }
        $code = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_code($a);
        if (!empty($started) && $code === "em_atendimento") {
            return \Prontoo\Runtime\Patients\PatientsRuntimeOperations05::patient_appointment_elapsed_until_now_label($started);
        }
        if (empty($started)) {
            return \Prontoo\Runtime\Patients\PatientsRuntimeOperations05::patient_appointment_not_started_label($a, $cid, $context) ===
                "não iniciado"
                ? "não iniciado"
                : "—";
        }
        return "—";
    
    }

    public static function patient_appointment_docs_by_appointment(
        int $cid,
        array $appointmentIds,
    ): array 
    {
    
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
            !is_callable([\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::class, 'db_table_exists']) ||
            !\Prontoo\Runtime\Operational\OperationalComposition::patients()->tableExists("pi_documents")
        ) {
            return [];
        }
        $params = array_merge([$cid], $ids);
        try {
            $rows = \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.06.patient_appointment_docs_by_appointment.01', $params, ['itemCount' => count($ids)])->fetchAll();
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

    public static function patient_appointment_real_start_label(
        array $a,
        int $cid,
        array $context = [],
    ): string 
    {
    
        $started = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local(
            $a["consultation_started_at"] ?? null,
            $cid,
            $context,
        );
        if ($started) {
            return $started->format("H:i");
        }
        return \Prontoo\Runtime\Patients\PatientsRuntimeOperations05::patient_appointment_not_started_label($a, $cid, $context);
    
    }

    public static function patient_appointment_real_end_label(
        array $a,
        int $cid,
        array $context = [],
    ): string 
    {
    
        $started = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local(
            $a["consultation_started_at"] ?? null,
            $cid,
            $context,
        );
        $finished = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local(
            $a["consultation_finished_at"] ?? null,
            $cid,
            $context,
        );
        if ($finished) {
            return $finished->format("H:i");
        }
        if ($started && \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_code($a) === "em_atendimento") {
            return "em andamento";
        }
        if (!$started) {
            return \Prontoo\Runtime\Patients\PatientsRuntimeOperations05::patient_appointment_not_started_label($a, $cid, $context);
        }
        return "não registrado";
    
    }

    public static function patient_appointment_first_line(
        array $a,
        int $cid,
        array $context = [],
    ): string 
    {
    
        $scheduled = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($a["start_at"] ?? null, $cid, $context);
        $date = $scheduled ? $scheduled->format("d/m/Y") : "—";
        return "Data do Agendamento: " .
            $date .
            " · Horário Início: " .
            \Prontoo\Runtime\Patients\PatientsRuntimeOperations06::patient_appointment_real_start_label($a, $cid, $context) .
            " · Horário do Fim: " .
            \Prontoo\Runtime\Patients\PatientsRuntimeOperations06::patient_appointment_real_end_label($a, $cid, $context) .
            " · Duração: " .
            \Prontoo\Runtime\Patients\PatientsRuntimeOperations06::patient_appointment_real_duration_label($a, $cid, $context);
    
    }

    public static function patient_appointment_first_line_html(
        array $a,
        int $cid,
        array $context = [],
    ): string 
    {
    
        $scheduled = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($a["start_at"] ?? null, $cid, $context);
        $chips = [
            ["Data do Agendamento", $scheduled ? $scheduled->format("d/m/Y") : "—"],
            [
                "Horário Início",
                \Prontoo\Runtime\Patients\PatientsRuntimeOperations06::patient_appointment_real_start_label($a, $cid, $context),
            ],
            [
                "Horário do Fim",
                \Prontoo\Runtime\Patients\PatientsRuntimeOperations06::patient_appointment_real_end_label($a, $cid, $context),
            ],
            [
                "Duração",
                \Prontoo\Runtime\Patients\PatientsRuntimeOperations06::patient_appointment_real_duration_label($a, $cid, $context),
            ],
        ];
        $h = '<span class="patient-appointment-line">';
        foreach ($chips as [$label, $value]) {
            $h .=
                '<span class="patient-appointment-chip"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                ":</span> " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value) .
                "</span>";
        }
        return $h . "</span>";
    
    }

    public static function patient_appointment_options_from_rows(array $appts): array
    
    {
    
        $opts = ["" => "Sem vínculo com agendamento"];
        foreach ($appts as $a) {
            $id = (int) ($a["id"] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label =
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($a["start_at"] ?? "") .
                " · " .
                ((string) ($a["reason"] ?? "") ?: "Consulta") .
                " · " .
                ((string) ($a["status"] ?? "") ?: "agendado");
            $opts[$id] = $label;
        }
        return $opts;
    
    }

    public static function patient_default_document_appointment(array $appts): ?int
    
    {
    
        foreach ($appts as $a) {
            $code = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_code($a);
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
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["start_at"] ?? "") >= strtotime("today") &&
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["start_at"] ?? "") < strtotime("tomorrow")
            ) {
                return (int) $a["id"];
            }
        }
        return null;
    
    }

    public static function patient_appointment_timeline_items(
        int $cid,
        array $appointments,
        array $context = [],
    ): array 
    {
    
        $ids = [];
        foreach ($appointments as $a) {
            $ids[] = (int) ($a["id"] ?? 0);
        }
        $docsByAppointment = \Prontoo\Runtime\Patients\PatientsRuntimeOperations06::patient_appointment_docs_by_appointment($cid, $ids);
        $items = [];
        foreach ($appointments as $a) {
            $aid = (int) ($a["id"] ?? 0);
            $reason = mb_trim((string) ($a["reason"] ?? ""));
            $items[] = [
                "icon" => \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_icon($a),
                "class" => \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_status_class($a),
                "time" => \Prontoo\Runtime\Patients\PatientsRuntimeOperations06::patient_appointment_first_line($a, $cid, $context),
                "time_html" => \Prontoo\Runtime\Patients\PatientsRuntimeOperations06::patient_appointment_first_line_html(
                    $a,
                    $cid,
                    $context,
                ),
                "title" => \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_status_title($a),
                "body" =>
                    $reason !== "" ? "Motivo: " . $reason : "Motivo não informado.",
                "meta" => \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_docs_label(
                    $docsByAppointment[$aid] ?? [],
                ),
            ];
        }
        return $items;
    
    }
}
