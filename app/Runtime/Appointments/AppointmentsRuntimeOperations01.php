<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Appointments;

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

final class AppointmentsRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function user_work_hours(int $cid, int $uid): array
    
    {
    
        $defaults = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::default_work_hours_rows();
        if ($cid <= 0 || $uid <= 0) {
            return $defaults;
        }
        $loader = function () use ($cid, $uid, $defaults): array {
    
            $hours = $defaults;
            try {
                $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "SELECT weekday,active,start_time,end_time FROM pi_user_work_hours WHERE clinic_id=? AND user_id=? ORDER BY FIELD(weekday,1,2,3,4,5,6,0)",
                    [$cid, $uid],
                )->fetchAll();
                foreach ($rows as $r) {
                    $wd = (int) ($r["weekday"] ?? -1);
                    if (!array_key_exists($wd, $hours)) {
                        continue;
                    }
                    $hours[$wd] = [
                        "weekday" => $wd,
                        "active" => (int) ($r["active"] ?? 0),
                        "start_time" => (string) ($r["start_time"] ?? "08:00:00"),
                        "end_time" => (string) ($r["end_time"] ?? "17:00:00"),
                    ];
                }
            } catch (Throwable $e) {
                error_log("[Prontoo work hours] " . $e->getMessage());
            }
            return $hours;
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "work_hours",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("hours", [$cid, $uid]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("work_hours"),
                $loader,
                ["clinic:" . $cid, "user:" . $uid],
            );
        }
        return $loader();
    
    }

    public static function save_user_work_hours(int $cid, int $uid, array $data): void
    
    {
    
        if ($cid <= 0 || $uid <= 0) {
            return;
        }
        \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::require_user_in_clinic($cid, $uid, ["medico"]);
        $labels = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::work_weekday_labels();
        $active = (array) ($data["work_active"] ?? []);
        $starts = (array) ($data["work_start"] ?? []);
        $ends = (array) ($data["work_end"] ?? []);
        foreach ($labels as $wd => $label) {
            $isActive = isset($active[$wd]) ? 1 : 0;
            $start = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::normalize_work_time(
                (string) ($starts[$wd] ?? "08:00"),
                "08:00:00",
            );
            $end = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::normalize_work_time(
                (string) ($ends[$wd] ?? "17:00"),
                "17:00:00",
            );
            if (
                $isActive &&
                strtotime("2000-01-01 " . $end) <= strtotime("2000-01-01 " . $start)
            ) {
                throw new RuntimeException(
                    "O expediente de " .
                        $label .
                        " precisa terminar depois do início.",
                );
            }
            \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "INSERT INTO pi_user_work_hours (clinic_id,user_id,weekday,active,start_time,end_time,updated_by,updated_at) VALUES (?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE active=VALUES(active),start_time=VALUES(start_time),end_time=VALUES(end_time),updated_by=VALUES(updated_by),updated_at=NOW()",
                [
                    $cid,
                    $uid,
                    $wd,
                    $isActive,
                    $start,
                    $end,
                    (int) ($_SESSION["uid"] ?? 0) ?: null,
                ],
            );
        }
    
    }

    public static function work_hours_form_html(int $cid, int $uid = 0): string
    
    {
    
        $hours = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::user_work_hours($cid, $uid);
        $h =
            '<details class="work-hours-panel" open><summary>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
            '<span>Expediente do profissional</span></summary><p class="muted-copy">Usado pela Agenda para limitar os horários que a equipe pode marcar para este profissional.</p><div class="work-hours-grid">';
        foreach (\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::work_weekday_labels() as $wd => $label) {
            $r = $hours[$wd] ?? [
                "active" => 0,
                "start_time" => "08:00:00",
                "end_time" => "17:00:00",
            ];
            $checked = !empty($r["active"]) ? " checked" : "";
            $h .=
                '<label class="work-day-row"><span class="work-day-name"><input type="checkbox" name="work_active[' .
                $wd .
                ']" value="1"' .
                $checked .
                "> " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                '</span><span class="work-hour-fields"><input type="time" name="work_start[' .
                $wd .
                ']" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::work_time_short((string) $r["start_time"])) .
                '"><em>até</em><input type="time" name="work_end[' .
                $wd .
                ']" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::work_time_short((string) $r["end_time"])) .
                '"></span></label>';
        }
        return $h . "</div></details>";
    
    }

    public static function doctor_work_ranges_for_day(int $cid, int $doctorId, string $day): array
    
    {
    
        if (
            $cid <= 0 ||
            $doctorId <= 0 ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)
        ) {
            return [];
        }
        $zone = new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone(null, $cid));
        $fmtLocal = function (int $ts) use ($zone): string {
    
            return new DateTimeImmutable("@" . $ts)
                ->setTimezone($zone)
                ->format("H:i");
        };
        $wd = (int) new DateTimeImmutable($day . " 00:00:00", $zone)->format("w");
        $hours = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::user_work_hours($cid, $doctorId);
        $r = $hours[$wd] ?? null;
        if (!$r || empty($r["active"])) {
            return [];
        }
        $start = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::work_time_short((string) $r["start_time"]);
        $end = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::work_time_short((string) $r["end_time"]);
        if ($start === "" || $end === "") {
            return [];
        }
        $s = new DateTimeImmutable(
            $day . " " . $start . ":00",
            $zone,
        )->getTimestamp();
        $e = new DateTimeImmutable(
            $day . " " . $end . ":00",
            $zone,
        )->getTimestamp();
        return $s && $e && $e > $s ? [[$s, $e, $start, $end]] : [];
    
    }

    public static function doctor_work_hours_label(int $cid, int $doctorId, string $day): string
    
    {
    
        $ranges = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_ranges_for_day($cid, $doctorId, $day);
        if (!$ranges) {
            return "Sem expediente cadastrado para este dia.";
        }
        $parts = [];
        foreach ($ranges as $r) {
            $parts[] = $r[2] . "–" . $r[3];
        }
        return "Expediente: " . implode(", ", $parts);
    
    }

    public static function appointment_within_doctor_hours(
        int $cid,
        int $doctorId,
        string $startAt,
        string $endAt,
    ): bool 
    {
    
        $ls = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($startAt, $cid);
        $le = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($endAt, $cid);
        if (!$ls || !$le || $le <= $ls) {
            return false;
        }
        $date = $ls->format("Y-m-d");
        $s = $ls->getTimestamp();
        $e = $le->getTimestamp();
        foreach (\Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_ranges_for_day($cid, $doctorId, $date) as $range) {
            if ($s >= $range[0] && $e <= $range[1]) {
                return true;
            }
        }
        return false;
    
    }

    public static function doctor_work_hours_conflict_message(
        int $cid,
        int $doctorId,
        string $startAt,
        string $endAt,
    ): string 
    {
    
        if (\Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_within_doctor_hours($cid, $doctorId, $startAt, $endAt)) {
            return "";
        }
        $day = substr($startAt, 0, 10);
        return "Este horário está fora do expediente do profissional. " .
            \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::doctor_work_hours_label($cid, $doctorId, $day);
    
    }

    public static function agenda_validate_period_message(
        string $startAt,
        string $endAt,
        string $operation,
        int $cid = 0,
    ): ?string 
    {
    
        $sUtc = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_parse_db_utc($startAt);
        $eUtc = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_parse_db_utc($endAt);
        $sLoc = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($startAt, $cid);
        $eLoc = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($endAt, $cid);
        if (!$sUtc || !$eUtc || !$sLoc || !$eLoc || $eUtc <= $sUtc) {
            return "Não foi possível concluir o " .
                $operation .
                ": informe início e fim posterior ao início.";
        }
        if ($sUtc->getTimestamp() < time() - 60) {
            return "Não foi possível concluir o " .
                $operation .
                ": a agenda não aceita registro ou alteração em horário passado.";
        }
        return null;
    
    }

    public static function appointment_journey_hard_guard_message(
        array $a,
        string $action,
        string $role = "",
        int $uid = 0,
        ?int $nowTs = null,
    ): string 
    {
    
        $nowTs = $nowTs ?? time();
        $code = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a);
        $startTs = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp((string) ($a["start_at"] ?? "")) ?: 0;
        $arrived = !empty($a["arrived_at"]);
        $started =
            !empty($a["consultation_started_at"]) || $code === "em_atendimento";
        $finished =
            !empty($a["consultation_finished_at"]) ||
            in_array($code, ["atendimento_concluido", "finalizado"], true);
        $doctor = (int) ($a["doctor_user_id"] ?? 0);
        $terminal = in_array(
            $code,
            ["finalizado", "cancelado", "nao_compareceu", "reagendado"],
            true,
        );
        $isReception =
            \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "recepcionista") ||
            \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "gerente");
        $isAssistant = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "assistente");
        $isDoctor = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "medico");
        $doctorOwnerOk = $doctor <= 0 || ($uid > 0 && $doctor === $uid);
        $stageLabel =
            [
                "agendado" => "agendado",
                "confirmado" => "confirmado",
                "chegou" => "com chegada registrada",
                "em_preparo" => "em preparo",
                "pronto_atendimento" => "pronto para atendimento",
                "em_atendimento" => "em atendimento",
                "atendimento_concluido" => "com atendimento concluído",
                "finalizado" => "finalizado",
                "cancelado" => "cancelado",
                "nao_compareceu" => "com ausência registrada",
                "reagendado" => "reagendado",
            ][$code] ?? $code;
        $prefix = "Este agendamento está " . $stageLabel . ". ";
        switch ($action) {
            case "confirm":
                if (!$isReception) {
                    return "A confirmação de presença é responsabilidade da Recepção ou do Administrativo.";
                }
                if ($code !== "agendado" || $arrived || $terminal) {
                    return $prefix .
                        "Só é possível confirmar antes da chegada do paciente.";
                }
                return "";
            case "arrived":
                if (!$isReception) {
                    return "O registro de chegada é responsabilidade da Recepção ou do Administrativo.";
                }
                if (
                    !in_array($code, ["agendado", "confirmado"], true) ||
                    $arrived ||
                    $terminal
                ) {
                    return $prefix .
                        "A chegada só pode ser registrada antes do preparo ou atendimento.";
                }
                return "";
            case "no_show":
                if (!$isReception) {
                    return "O não comparecimento é responsabilidade da Recepção ou do Administrativo.";
                }
                if (
                    !in_array($code, ["agendado", "confirmado"], true) ||
                    $arrived ||
                    $terminal
                ) {
                    return $prefix .
                        "Não comparecimento só se aplica antes da chegada do paciente.";
                }
                if ($startTs > 0 && $startTs > $nowTs) {
                    return "Ainda não é possível marcar ausência antes do horário inicial da consulta.";
                }
                return "";
            case "start_prepare":
                if (!$isAssistant) {
                    return "Apenas o ambiente Assistente pode iniciar o preparo.";
                }
                if ($code !== "chegou") {
                    return $prefix .
                        "O preparo só pode iniciar depois que a Recepção registrar a chegada.";
                }
                return "";
            case "finish_prepare":
                if (!$isAssistant) {
                    return "Apenas o ambiente Assistente pode liberar o atendimento.";
                }
                if ($code !== "em_preparo") {
                    return $prefix .
                        "Para liberar atendimento, primeiro inicie o preparo.";
                }
                return "";
            case "start_consultation":
                if (!$isDoctor) {
                    return "Apenas o ambiente Profissional pode iniciar atendimento.";
                }
                if (!$doctorOwnerOk) {
                    return "Apenas o profissional responsável por este agendamento pode iniciar o atendimento.";
                }
                if ($code !== "pronto_atendimento") {
                    return $prefix .
                        "O atendimento só pode iniciar depois que o Assistente liberar o paciente.";
                }
                return "";
            case "finish_consultation":
                if (!$isDoctor) {
                    return "Apenas o ambiente Profissional pode concluir atendimento.";
                }
                if (!$doctorOwnerOk) {
                    return "Apenas o profissional responsável por este agendamento pode concluir o atendimento.";
                }
                if ($code !== "em_atendimento") {
                    return $prefix .
                        "Só é possível concluir um atendimento em andamento.";
                }
                return "";
            case "finish_checkout":
                if (!$isReception) {
                    return "A saída é responsabilidade da Recepção ou do Administrativo.";
                }
                if ($code !== "atendimento_concluido") {
                    return $prefix .
                        "A saída só pode ser finalizada depois da conclusão clínica.";
                }
                $amount = (int) ($a["payment_amount_cents"] ?? 0);
                $payStatus = mb_strtolower(
                    mb_trim((string) ($a["payment_status"] ?? "")),
                );
                $paid =
                    !empty($a["payment_confirmed_at"]) ||
                    in_array(
                        $payStatus,
                        [
                            "efetivada",
                            "recebido",
                            "pago",
                            "isento",
                            "cortesia",
                            "sem_cobranca",
                        ],
                        true,
                    );
                if ($amount > 0 && !$paid) {
                    return "Há pagamento pendente neste atendimento. Registre o recebimento na Recepção antes de finalizar a saída.";
                }
                return "";
            case "update_appointment":
            case "edit":
                if (!$isReception) {
                    return "A edição do agendamento é responsabilidade da Recepção ou do Administrativo.";
                }
                if (
                    !in_array($code, ["agendado", "confirmado"], true) ||
                    $arrived ||
                    $started ||
                    $finished ||
                    $terminal
                ) {
                    return $prefix .
                        "Depois que a jornada começa, não altere o agendamento; movimente a jornada ou registre uma exceção.";
                }
                if ($startTs > 0 && $startTs < $nowTs) {
                    return "Agendamento com horário já iniciado não deve ser editado; registre chegada, ausência ou remarcação.";
                }
                return "";
            case "delete_appointment":
            case "cancel":
                if (!$isReception) {
                    return "O cancelamento é responsabilidade da Recepção ou do Administrativo.";
                }
                if (
                    !in_array($code, ["agendado", "confirmado"], true) ||
                    $arrived ||
                    $started ||
                    $finished ||
                    $terminal
                ) {
                    return $prefix .
                        "Não é permitido cancelar depois que o paciente entrou na jornada. Use a movimentação adequada da Jornada do Paciente.";
                }
                if ($startTs > 0 && $startTs < $nowTs) {
                    return "Agendamento com horário já iniciado não deve ser cancelado; registre não comparecimento ou movimente a jornada.";
                }
                return "";
        }
        return "";
    
    }

    public static function appointment_journey_can(
        array $a,
        string $action,
        string $role = "",
        int $uid = 0,
        ?int $nowTs = null,
    ): bool 
    {
    
        return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
            $a,
            $action,
            $role,
            $uid,
            $nowTs,
        ) === "";
    
    }

    public static function appointment_journey_elapsed_label(
        array $a,
        string $code,
        int $nowTs,
    ): string 
    {
    
        $pick = function (array $keys) use ($a): int {
    
            foreach ($keys as $k) {
                $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp((string) ($a[$k] ?? ""));
                if ($ts > 0) {
                    return $ts;
                }
            }
            return 0;
        };
        $ts = 0;
        if (in_array($code, ["agendado", "confirmado", "atrasado"], true)) {
            $ts = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp((string) ($a["start_at"] ?? "")) ?: 0;
        } elseif (
            in_array($code, ["chegou", "em_preparo", "pronto_atendimento"], true)
        ) {
            $ts = $pick(["arrived_at", "start_at"]);
        } elseif ($code === "em_atendimento") {
            $ts = $pick(["consultation_started_at", "arrived_at", "start_at"]);
        } elseif (in_array($code, ["atendimento_concluido", "finalizado"], true)) {
            $ts = $pick([
                "consultation_finished_at",
                "consultation_started_at",
                "arrived_at",
                "start_at",
            ]);
        } elseif (
            in_array($code, ["nao_compareceu", "cancelado", "reagendado"], true)
        ) {
            $ts = $pick(["updated_at", "start_at"]);
        }
        if ($ts <= 0) {
            return "";
        }
        $mins = (int) floor(max(0, $nowTs - $ts) / 60);
        if ($mins < 1) {
            return "Agora";
        }
        if ($code === "atrasado") {
            return "Atrasado há " . \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::format_minutes($mins);
        }
        if (in_array($code, ["finalizado", "cancelado", "reagendado"], true)) {
            return "";
        }
        if ($code === "nao_compareceu") {
            return "Ausência registrada há " . \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::format_minutes($mins);
        }
        return "Na etapa há " . \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::format_minutes($mins);
    
    }
}
