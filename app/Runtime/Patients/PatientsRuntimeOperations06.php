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

    public static function patient_appointment_real_start_label(
        array $a,
        int $cid,
        array $context = [],
    ): string 
    {
    
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

    public static function patient_appointment_real_end_label(
        array $a,
        int $cid,
        array $context = [],
    ): string 
    {
    
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

    public static function patient_appointment_first_line(
        array $a,
        int $cid,
        array $context = [],
    ): string 
    {
    
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

    public static function patient_appointment_first_line_html(
        array $a,
        int $cid,
        array $context = [],
    ): string 
    {
    
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

    public static function patient_appointment_options_from_rows(array $appts): array
    
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

    public static function patient_default_document_appointment(array $appts): ?int
    
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
        $docsByAppointment = patient_appointment_docs_by_appointment($cid, $ids);
        $items = [];
        foreach ($appointments as $a) {
            $aid = (int) ($a["id"] ?? 0);
            $reason = mb_trim((string) ($a["reason"] ?? ""));
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
}
