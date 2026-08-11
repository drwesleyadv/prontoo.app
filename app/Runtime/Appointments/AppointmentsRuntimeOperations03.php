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

final class AppointmentsRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function appointment_journey_card_html(
        array $a,
        string $role = "",
        string $variant = "default",
        ?string $returnHidden = "",
        string $actionUrl = "",
    ): string 
    {
    
        $returnHidden = (string) ($returnHidden ?? "");
        $vm = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_view_model($a, $role);
        $elapsed = mb_trim((string) ($vm["elapsed"] ?? ""));
        $elapsed = $elapsed !== "" ? $elapsed : "Sem espera registrada";
        $moves =
            $returnHidden !== "" || $actionUrl !== ""
                ? \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_quick_actions_html(
                    $a,
                    $role,
                    $returnHidden,
                    $actionUrl,
                )
                : "";
        $facts =
            \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::appointment_journey_fact_html(
                "account_circle",
                "Responsável",
                (string) $vm["owner"],
            ) .
            \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::appointment_journey_fact_html("timer", "Na etapa há", $elapsed) .
            \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::appointment_journey_fact_html(
                "arrow_forward",
                "Próxima medida",
                (string) $vm["action"],
            );
        return '<div class="journey-ux journey-ux--card journey-ux--' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($variant) .
            " journey-ux--operable journey-ux--timeline-card is-" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $vm["class"]) .
            '"><div class="journey-ux-card-head"><span class="journey-ux-status">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon((string) $vm["icon"]) .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $vm["label"]) .
            "</b><em>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $vm["phase_label"]) .
            "</em></span></div>" .
            \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::appointment_journey_steps_html($vm) .
            '<div class="journey-ux-facts">' .
            $facts .
            "</div>" .
            $moves .
            "</div>";
    
    }

    public static function appointment_journey_measure_html(
        array $a,
        string $role = "",
        ?string $returnHidden = "",
        string $actionUrl = "",
    ): string 
    {
    
        return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_compact_html(
            $a,
            $role,
            (string) ($returnHidden ?? ""),
            $actionUrl,
        );
    
    }

    public static function delay_minutes_from_appointment(array $a): ?int
    
    {
    
        $scheduled = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["start_at"] ?? "");
        $started = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($a["consultation_started_at"] ?? "");
        if (!$scheduled || !$started) {
            return null;
        }
        return max(0, (int) floor(($started - $scheduled) / 60));
    
    }

    public static function appointment_patient_names(array $appointments, ?int $cid = null): array
    
    {
    
        $ids = \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($appointments, "patient_link_id");
        $patients = $cid
            ? \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::scoped_patient_map($cid, $ids)
            : \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map("patients_person", $ids);
        $persons = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "persons_name",
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids(array_values($patients), "person_id"),
        );
        $out = [];
        foreach ($appointments as $a) {
            $pid = (int) ($a["patient_link_id"] ?? 0);
            $pl = $patients[$pid] ?? [];
            $ps = $persons[(int) ($pl["person_id"] ?? 0)] ?? [];
            $out[$pid] = (string) ($ps["full_name"] ?? "Paciente não identificado");
        }
        return $out;
    
    }

    public static function procedure_options(int $cid, bool $activeOnly = true): array
    
    {
    
        if ($cid <= 0) {
            return [];
        }
        $loader = function () use ($cid, $activeOnly): array {
            try {
                return \Prontoo\Runtime\Operational\OperationalComposition::appointments()->result('operational.appointments.03.procedure_options.01', [$cid], ['activeOnly' => $activeOnly])->fetchAll();
            } catch (Throwable $e) {
                error_log("[Prontoo procedure options] " . $e->getMessage());
                return [];
            }
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "catalog",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("procedures", [$cid, $activeOnly]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("catalog"),
                $loader,
                ["clinic:" . $cid, "table:pi_procedures"],
            );
        }
        return $loader();
    
    }

    public static function procedure_select_html(
        int $cid,
        string $name = "reason",
        string $selected = "",
        bool $registeredOnly = false,
    ): string 
    {
    
        $procedures = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_options($cid, true);
        $custom = !$registeredOnly && $selected !== "";
        $placeholder =
            $registeredOnly && $procedures === []
                ? "Cadastre Procedimentos primeiro"
                : "Selecione o procedimento";
        $h =
            '<select name="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
            '" data-procedure-select' .
            ($registeredOnly ? " required" : "") .
            '><option value="">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($placeholder) .
            "</option>";
        foreach ($procedures as $p) {
            $val = "procedure:" . (int) $p["id"];
            $sel =
                $selected === $val || $selected === (string) $p["title"]
                    ? " selected"
                    : "";
            if ($sel) {
                $custom = false;
            }
            $pre = mb_trim((string) ($p["pre_instructions"] ?? ""));
            $post = mb_trim((string) ($p["post_care"] ?? ""));
            $desc = mb_trim((string) ($p["description"] ?? ""));
            $summary = trim(
                ($desc !== "" ? $desc . " · " : "") .
                    ((int) $p["duration_minutes"]) .
                    " min" .
                    ((int) $p["price_cents"] > 0
                        ? " · " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $p["price_cents"])
                        : "") .
                    (mb_trim((string) $p["payment_methods"]) !== ""
                        ? " · " . mb_trim((string) $p["payment_methods"])
                        : "") .
                    ($pre !== "" ? " · Pré: " . $pre : "") .
                    ($post !== "" ? " · Pós: " . $post : ""),
            );
            $h .=
                '<option value="' .
                $val .
                '"' .
                $sel .
                ' data-duration="' .
                (int) $p["duration_minutes"] .
                '" data-price="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $p["price_cents"])) .
                '" data-price-cents="' .
                (int) $p["price_cents"] .
                '" data-payment="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $p["payment_methods"]) .
                '" data-summary="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($summary) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::procedure_option_label($p)) .
                "</option>";
        }
        if ($custom) {
            $h .=
                '<option value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($selected) .
                '" selected>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($selected) .
                "</option>";
        }
        if (!$registeredOnly) {
            $h .= '<option value="Outro">Outro / procedimento livre</option>';
        }
        $h .=
            '</select><small class="field-hint" data-procedure-summary></small>';
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Procedimento", $h);
    
    }

    public static function procedure_reason_from_post(int $cid, string $field = "reason"): string
    
    {
    
        $v = mb_trim((string) ($_POST[$field] ?? ""));
        if (str_starts_with($v, "procedure:")) {
            $id = (int) substr($v, 10);
            $p = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->row('operational.appointments.03.procedure_reason_from_post.01', [$id, $cid], []);
            if ($p) {
                return (string) $p["title"];
            }
        }
        return $v === "Outro" ? "" : $v;
    
    }

    public static function doctors(int $cid): array
    
    {
    
        $links = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->result('operational.appointments.03.doctors.01', [$cid], [])->fetchAll();
        $users = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "users_status",
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($links, "user_id"),
        );
        $o = [];
        foreach ($links as $r) {
            $u = $users[(int) $r["user_id"]] ?? [];
            if ($u && (int) $u["active"] === 1) {
                $o[(int) $u["id"]] = $u["name"];
            }
        }
        asort($o, SORT_NATURAL | SORT_FLAG_CASE);
        return $o;
    
    }

    public static function normalize_db_datetime(string $value): string
    
    {
    
        return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_to_db_utc($value, (int) (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx()["clinic_id"] ?? 0));
    
    }

    public static function agenda_period_label(string $startAt, string $endAt): string
    
    {
    
        return \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($startAt) . " até " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($endAt);
    
    }

    public static function datetime_local_value(?string $value): string
    
    {
    
        return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local_input($value, (int) (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx()["clinic_id"] ?? 0));
    
    }

    public static function agenda_doctor_name(int $cid, ?int $doctorId): string
    
    {
    
        if (!$doctorId) {
            return "todo o consultório";
        }
        $u = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->row('operational.appointments.03.agenda_doctor_name.01', [$cid, $doctorId], []);
        return $u && !empty($u["name"])
            ? (string) $u["name"]
            : "profissional selecionado";
    
    }

    public static function agenda_conflict_message(
        int $cid,
        ?int $doctorId,
        string $startAt,
        string $endAt,
        string $operation,
        int $ignoreAppointmentId = 0,
        int $ignoreBlockId = 0,
    ): ?string 
    {
    
        $doctorId = $doctorId && $doctorId > 0 ? $doctorId : null;
        if (
            $msg = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::agenda_validate_period_message(
                $startAt,
                $endAt,
                $operation,
                $cid,
            )
        ) {
            return $msg;
        }
        $scope = $doctorId
            ? "agenda de " . \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_doctor_name($cid, $doctorId)
            : "todo o consultório";
        $period = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_period_label($startAt, $endAt);
        $lock = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->inTransaction();
        $apptParams = [$cid, $endAt, $startAt];
        if ($ignoreAppointmentId > 0) {
            $apptParams[] = $ignoreAppointmentId;
        }
        if ($doctorId !== null) {
            $apptParams[] = $doctorId;
        }
        $appt = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->row('operational.appointments.03.agenda_conflict_message.01', $apptParams, [
            'ignoreAppointment' => $ignoreAppointmentId > 0,
            'doctorScoped' => $doctorId !== null,
            'lockRows' => $lock,
        ]);
        if ($appt) {
            $patient = "";
            $pl = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->row('operational.appointments.03.agenda_conflict_message.02', [(int) ($appt["patient_link_id"] ?? 0), $cid], []);
            if ($pl && !empty($pl["full_name"])) {
                $patient = " de " . (string) $pl["full_name"];
            }
            $doc = "";
            if (!empty($appt["doctor_user_id"])) {
                $doc =
                    " · profissional: " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name(
                        \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_doctor_name($cid, (int) $appt["doctor_user_id"]),
                    );
            }
            $status = mb_trim((string) ($appt["status"] ?? "agendado"));
            $reason = mb_trim((string) ($appt["reason"] ?? ""));
            $detail =
                "consulta" .
                ($patient !== "" ? $patient : "") .
                " das " .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $appt["start_at"], $cid) .
                " às " .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $appt["end_at"], $cid) .
                $doc .
                ($status !== "" ? " · status: " . $status : "") .
                ($reason !== "" ? " · motivo: " . $reason : "");
            return "Não foi possível concluir o " .
                $operation .
                ": o período informado (" .
                $period .
                ") na " .
                $scope .
                " se sobrepõe a uma " .
                $detail .
                ". Ajuste o horário, escolha outro profissional disponível ou remova/reagende o conflito antes de concluir a operação.";
        }
        $blockParams = [$cid, $endAt, $startAt];
        if ($ignoreBlockId > 0) {
            $blockParams[] = $ignoreBlockId;
        }
        if ($doctorId !== null) {
            $blockParams[] = $doctorId;
        }
        $block = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->row('operational.appointments.03.agenda_conflict_message.03', $blockParams, [
            'ignoreBlock' => $ignoreBlockId > 0,
            'doctorScoped' => $doctorId !== null,
            'lockRows' => $lock,
        ]);
        if ($block) {
            $creator = "";
            $u = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->row('operational.appointments.03.agenda_conflict_message.04', [(int) ($block["created_by"] ?? 0), $cid], []);
            if ($u && !empty($u["name"])) {
                $creator = " · bloqueado por " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) $u["name"]);
            }
            $doctorLabel = empty($block["doctor_user_id"])
                ? "todo o consultório"
                : "agenda de " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name(
                        \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_doctor_name($cid, (int) $block["doctor_user_id"]),
                    );
            $reason = mb_trim((string) ($block["reason"] ?? ""));
            $detail =
                "bloqueio de " .
                $doctorLabel .
                " das " .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $block["start_at"], $cid) .
                " às " .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $block["end_at"], $cid) .
                ($reason !== "" ? " · motivo: " . $reason : "") .
                $creator;
            return "Não foi possível concluir o " .
                $operation .
                ": o período informado (" .
                $period .
                ") na " .
                $scope .
                " se sobrepõe a um " .
                $detail .
                ". Ajuste o horário, escolha outro profissional disponível ou remova o bloqueio antes de concluir a operação.";
        }
        return null;
    
    }

    public static function agenda_note_visible_for_day(int $cid, string $day, array $c): ?array
    
    {
    
        if (
            $cid <= 0 ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)
        ) {
            return null;
        }
        \Prontoo\Runtime\Operational\OperationalComposition::appointments()->ensureSchema("agenda_notes");
        try {
            $roles = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_note_role_codes($c);
            $params = [$cid, $day];
            if ($roles) {
                $params = array_merge($params, $roles);
            }
            $rows = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->result('operational.appointments.03.agenda_note_visible_for_day.01', $params, ['roleCount' => count($roles)])->fetchAll();
            return $rows[0] ?? null;
        } catch (Throwable $e) {
            error_log("[Prontoo agenda note day] " . $e->getMessage());
            return null;
        }
    
    }

    public static function agenda_note_card_html(?array $note, ?array $c = null): string
    
    {
    
        if (!$note) {
            return "";
        }
        $content = mb_trim((string) ($note["content"] ?? ""));
        if ($content === "") {
            return "";
        }
        if (!is_array($c) || !$c) {
            if (is_callable([\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::class, 'ctx'])) {
                $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
            } else {
                $c = [];
            }
        }
        $uid = (int) ($c["user"]["id"] ?? 0);
        $creator = (int) ($note["created_by"] ?? 0);
        $creatorName = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) ($note["created_by_name"] ?? ""));
        $day = (string) ($note["note_date"] ?? "");
        $noteId = (int) ($note["id"] ?? 0);
        $side = "";
        if ($uid > 0 && $creator > 0 && $uid === $creator && $noteId > 0) {
            $side =
                '<form method="post" class="agenda-day-note-delete" onsubmit="return confirm(\'Excluir esta anotação?\');">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="agenda_note_delete"><input type="hidden" name="id" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $noteId) .
                '"><input type="hidden" name="return_day" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($day) .
                '"><input type="hidden" name="return_doctor" value="' .
                (int) ($_GET["doctor"] ?? 0) .
                '"><button type="submit" class="ghost small danger">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
                "<span>Excluir</span></button></form>";
        } else {
            $side =
                '<span class="agenda-day-note-author" title="Incluída por ' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($creatorName) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($creatorName) .
                "</span>";
        }
        return '<section class="agenda-day-note-card" aria-label="Anotação da agenda"><span class="agenda-day-note-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("sticky_note_2") .
            '</span><div class="agenda-day-note-copy"><p>' .
            nl2br(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($content)) .
            '</p></div><div class="agenda-day-note-side">' .
            $side .
            "</div></section>";
    
    }
}
