<?php
declare(strict_types=1);

namespace Prontoo\Domain\Appointments;

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

final class AppointmentsDomainOperations01
{
    private function __construct()
    {
    }

    public static function work_weekday_labels(): array
    
    {
    
        return [
            1 => "Segunda",
            2 => "Terça",
            3 => "Quarta",
            4 => "Quinta",
            5 => "Sexta",
            6 => "Sábado",
            0 => "Domingo",
        ];
    
    }

    public static function normalize_work_time(?string $v, string $fallback = ""): string
    
    {
    
        $v = mb_trim((string) $v);
        if (preg_match('/^\d{2}:\d{2}$/', $v)) {
            return $v . ":00";
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) {
            return $v;
        }
        return $fallback;
    
    }

    public static function work_time_short(?string $v): string
    
    {
    
        $v = (string) $v;
        return preg_match("/^\d{2}:\d{2}/", $v) ? substr($v, 0, 5) : "";
    
    }

    public static function default_work_hours_rows(): array
    
    {
    
        $out = [];
        foreach (\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::work_weekday_labels() as $wd => $label) {
            $out[$wd] = [
                "weekday" => $wd,
                "active" => in_array($wd, [1, 2, 3, 4, 5], true) ? 1 : 0,
                "start_time" => "08:00:00",
                "end_time" => "17:00:00",
            ];
        }
        return $out;
    
    }

    public static function appointment_status_code(array $appointment): string
    
    {
    
        $status = mb_strtolower(mb_trim((string) ($appointment["status"] ?? "")));
        $status = str_replace([" ", "-"], "_", $status);
        $allowed = [
            "agendado",
            "confirmado",
            "chegou",
            "em_preparo",
            "pronto_atendimento",
            "em_atendimento",
            "atendimento_concluido",
            "finalizado",
            "cancelado",
            "nao_compareceu",
            "reagendado",
        ];
    
        if (!in_array($status, $allowed, true)) {
            $status = "agendado";
        }
    
        if (
            !empty($appointment["consultation_finished_at"]) &&
            !in_array(
                $status,
                ["finalizado", "cancelado", "nao_compareceu", "reagendado"],
                true,
            )
        ) {
            return "atendimento_concluido";
        }
    
        if (
            !empty($appointment["consultation_started_at"]) &&
            !in_array(
                $status,
                [
                    "atendimento_concluido",
                    "finalizado",
                    "cancelado",
                    "nao_compareceu",
                    "reagendado",
                ],
                true,
            )
        ) {
            return "em_atendimento";
        }
    
        if (
            !empty($appointment["arrived_at"]) &&
            in_array($status, ["agendado", "confirmado"], true)
        ) {
            return "chegou";
        }
    
        return $status;
    
    }

    public static function appointment_is_cancelled(array $a): bool
    
    {
    
        return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a) === "cancelado";
    
    }

    public static function is_done_appointment(array $a): bool
    
    {
    
        return in_array(
            \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a),
            ["atendimento_concluido", "finalizado"],
            true,
        );
    
    }

    public static function appointment_is_terminal(array $a): bool
    
    {
    
        return in_array(
            \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a),
            ["finalizado", "cancelado", "nao_compareceu", "reagendado"],
            true,
        );
    
    }

    public static function appointment_journey_steps(): array
    
    {
    
        return [
            ["key" => "agendamento", "label" => "Agendamento", "icon" => "event"],
            ["key" => "chegada", "label" => "Chegada", "icon" => "how_to_reg"],
            ["key" => "preparo", "label" => "Preparo", "icon" => "clinical_notes"],
            [
                "key" => "atendimento",
                "label" => "Atendimento",
                "icon" => "stethoscope",
            ],
            ["key" => "saida", "label" => "Saída", "icon" => "task_alt"],
        ];
    
    }

    public static function appointment_journey_role_matches(string $role, string $expected): bool
    
    {
    
        $normalize = static  fn(string $value): string => str_replace(
            ["-", " "],
            "_",
            mb_strtolower(trim($value), "UTF-8"),
        );
        return $normalize($role) === $normalize($expected);
    
    }

    public static function format_minutes(?int $minutes): string
    
    {
    
        if ($minutes === null) {
            return "—";
        }
        if ($minutes < 60) {
            return $minutes . " min";
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h . "h" . str_pad((string) $m, 2, "0", STR_PAD_LEFT);
    
    }

    public static function agenda_day_short_label(string $day): string
    
    {
    
        $ts = strtotime($day . " 12:00:00");
        if (!$ts) {
            return $day;
        }
        $months = [
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
        return (int) date("d", $ts) .
            " de " .
            ($months[(int) date("n", $ts)] ?? date("m", $ts));
    
    }

    public static function agenda_month_name_br(string $day): string
    
    {
    
        $ts = strtotime($day . " 12:00:00");
        if (!$ts) {
            return $day;
        }
        $months = [
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
        return $months[(int) date("n", $ts)] ?? date("m", $ts);
    
    }

    public static function agenda_day_week_start(string $day): string
    
    {
    
        $ts = strtotime($day . " 12:00:00");
        if (!$ts) {
            return $day;
        }
        return date(
            "Y-m-d",
            strtotime(date("Y-m-d", $ts) . " -" . (int) date("w", $ts) . " days"),
        );
    
    }

    public static function agenda_week_range_label(string $day): string
    
    {
    
        $start = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_day_week_start($day);
        $end = date("Y-m-d", strtotime($start . " +6 days"));
        $startTs = strtotime($start . " 12:00:00");
        $endTs = strtotime($end . " 12:00:00");
        if (!$startTs || !$endTs) {
            return $day;
        }
        if (
            date("n", $startTs) === date("n", $endTs) &&
            date("Y", $startTs) === date("Y", $endTs)
        ) {
            return date("d", $startTs) .
                " de " .
                \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_month_name_br($start) .
                " a " .
                date("d", $endTs) .
                " de " .
                \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_month_name_br($end);
        }
        return date("d", $startTs) .
            " de " .
            \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_month_name_br($start) .
            " a " .
            date("d", $endTs) .
            " de " .
            \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_month_name_br($end);
    
    }

    public static function agenda_iso_local_value(string $day, string $hm): string
    
    {
    
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = date("Y-m-d");
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $hm)) {
            $hm = "08:00";
        }
        return $day . "T" . $hm;
    
    }

    public static function agenda_normalize_local_input(
        string $value,
        string $fallbackDay,
        string $fallbackHm = "08:00",
    ): string 
    {
    
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match("/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/", $value)) {
            return str_replace(" ", "T", substr($value, 0, 16));
        }
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_iso_local_value($fallbackDay, $value);
        }
        return \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_iso_local_value($fallbackDay, $fallbackHm);
    
    }

    public static function agenda_local_input_add_minutes(
        string $value,
        int $minutes = 30,
    ): string 
    {
    
        $dt = DateTimeImmutable::createFromFormat("Y-m-d\TH:i", $value);
        if (!$dt) {
            return $value;
        }
        return $dt
            ->modify(($minutes >= 0 ? "+" : "") . $minutes . " minutes")
            ->format("Y-m-d\TH:i");
    
    }

    public static function agenda_safe_day_from_local_input(
        string $value,
        string $fallbackDay,
    ): string 
    {
    
        return preg_match("/^\d{4}-\d{2}-\d{2}T/", $value)
            ? substr($value, 0, 10)
            : $fallbackDay;
    
    }

    public static function agenda_note_role_codes(array $c): array
    
    {
    
        $roles = (array) ($c["effective_roles"] ?? []);
        $active = (string) ($c["role"] ?? "");
        if ($active !== "" && !in_array($active, $roles, true)) {
            array_unshift($roles, $active);
        }
        $roles = array_values(
            array_unique(array_filter(array_map("strval", $roles))),
        );
        return $roles ?: ($active !== "" ? [$active] : []);
    
    }

    public static function agenda_note_visible_for_user(array $note, array $c): bool
    
    {
    
        $scope = (string) ($note["target_scope"] ?? "clinic");
        if ($scope === "clinic" || $scope === "all") {
            return true;
        }
        if ($scope === "role") {
            $target = (string) ($note["target_role"] ?? "");
            return $target !== "" &&
                in_array($target, \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_note_role_codes($c), true);
        }
        return false;
    
    }
}
