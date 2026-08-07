<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Appointments;

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
        $vm = appointment_journey_view_model($a, $role);
        $elapsed = mb_trim((string) ($vm["elapsed"] ?? ""));
        $elapsed = $elapsed !== "" ? $elapsed : "Sem espera registrada";
        $moves =
            $returnHidden !== "" || $actionUrl !== ""
                ? appointment_journey_quick_actions_html(
                    $a,
                    $role,
                    $returnHidden,
                    $actionUrl,
                )
                : "";
        $facts =
            appointment_journey_fact_html(
                "account_circle",
                "Responsável",
                (string) $vm["owner"],
            ) .
            appointment_journey_fact_html("timer", "Na etapa há", $elapsed) .
            appointment_journey_fact_html(
                "arrow_forward",
                "Próxima medida",
                (string) $vm["action"],
            );
        return '<div class="journey-ux journey-ux--card journey-ux--' .
            e($variant) .
            " journey-ux--operable journey-ux--timeline-card is-" .
            e((string) $vm["class"]) .
            '"><div class="journey-ux-card-head"><span class="journey-ux-status">' .
            icon((string) $vm["icon"]) .
            "<b>" .
            e((string) $vm["label"]) .
            "</b><em>" .
            e((string) $vm["phase_label"]) .
            "</em></span></div>" .
            appointment_journey_steps_html($vm) .
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
    
        return appointment_journey_compact_html(
            $a,
            $role,
            (string) ($returnHidden ?? ""),
            $actionUrl,
        );
    
    }

    public static function delay_minutes_from_appointment(array $a): ?int
    
    {
    
        $scheduled = app_storage_timestamp($a["start_at"] ?? "");
        $started = app_storage_timestamp($a["consultation_started_at"] ?? "");
        if (!$scheduled || !$started) {
            return null;
        }
        return max(0, (int) floor(($started - $scheduled) / 60));
    
    }

    public static function appointment_patient_names(array $appointments, ?int $cid = null): array
    
    {
    
        $ids = int_ids($appointments, "patient_link_id");
        $patients = $cid
            ? scoped_patient_map($cid, $ids, "id,person_id")
            : fetch_map("pi_patients", $ids, "id,person_id");
        $persons = fetch_map(
            "pi_persons",
            int_ids(array_values($patients), "person_id"),
            "id,full_name",
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
    
            $where = "clinic_id=?";
            $params = [$cid];
            if ($activeOnly) {
                $where .= " AND active=1";
            }
            try {
                return q(
                    "SELECT id,title,category,description,duration_minutes,price_cents,payment_methods,pre_instructions,post_care,active FROM pi_procedures WHERE $where ORDER BY active DESC,title ASC LIMIT 400",
                    $params,
                )->fetchAll();
            } catch (Throwable $e) {
                error_log("[Prontoo procedure options] " . $e->getMessage());
                return [];
            }
        };
        if (function_exists("server_json_cache_remember")) {
            return server_json_cache_remember(
                "catalog",
                server_json_cache_safe_key("procedures", [$cid, $activeOnly]),
                server_json_cache_ttl("catalog"),
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
    
        $procedures = procedure_options($cid, true);
        $custom = !$registeredOnly && $selected !== "";
        $placeholder =
            $registeredOnly && $procedures === []
                ? "Cadastre Procedimentos primeiro"
                : "Selecione o procedimento";
        $h =
            '<select name="' .
            e($name) .
            '" data-procedure-select' .
            ($registeredOnly ? " required" : "") .
            '><option value="">' .
            e($placeholder) .
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
                        ? " · " . money_br((int) $p["price_cents"])
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
                e(money_br((int) $p["price_cents"])) .
                '" data-price-cents="' .
                (int) $p["price_cents"] .
                '" data-payment="' .
                e((string) $p["payment_methods"]) .
                '" data-summary="' .
                e($summary) .
                '">' .
                e(procedure_option_label($p)) .
                "</option>";
        }
        if ($custom) {
            $h .=
                '<option value="' .
                e($selected) .
                '" selected>' .
                e($selected) .
                "</option>";
        }
        if (!$registeredOnly) {
            $h .= '<option value="Outro">Outro / procedimento livre</option>';
        }
        $h .=
            '</select><small class="field-hint" data-procedure-summary></small>';
        return form_row("Procedimento", $h);
    
    }

    public static function procedure_reason_from_post(int $cid, string $field = "reason"): string
    
    {
    
        $v = mb_trim((string) ($_POST[$field] ?? ""));
        if (str_starts_with($v, "procedure:")) {
            $id = (int) substr($v, 10);
            $p = one(
                "SELECT title FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1",
                [$id, $cid],
            );
            if ($p) {
                return (string) $p["title"];
            }
        }
        return $v === "Outro" ? "" : $v;
    
    }

    public static function doctors(int $cid): array
    
    {
    
        $links = q(
            "SELECT user_id FROM pi_user_roles WHERE clinic_id=? AND role_code='medico' AND active=1 ORDER BY id ASC LIMIT 80",
            [$cid],
        )->fetchAll();
        $users = fetch_map(
            "pi_users",
            int_ids($links, "user_id"),
            "id,name,active",
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
    
        return app_local_to_db_utc($value, (int) (ctx()["clinic_id"] ?? 0));
    
    }

    public static function agenda_period_label(string $startAt, string $endAt): string
    
    {
    
        return dt_br($startAt) . " até " . dt_br($endAt);
    
    }

    public static function datetime_local_value(?string $value): string
    
    {
    
        return app_db_utc_to_local_input($value, (int) (ctx()["clinic_id"] ?? 0));
    
    }

    public static function agenda_doctor_name(int $cid, ?int $doctorId): string
    
    {
    
        if (!$doctorId) {
            return "todo o consultório";
        }
        $u = one(
            "SELECT u.name FROM pi_users u JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.clinic_id=? AND ur.role_code='medico' AND ur.active=1 WHERE u.id=? AND u.active=1 LIMIT 1",
            [$cid, $doctorId],
        );
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
            $msg = agenda_validate_period_message(
                $startAt,
                $endAt,
                $operation,
                $cid,
            )
        ) {
            return $msg;
        }
        $scope = $doctorId
            ? "agenda de " . agenda_doctor_name($cid, $doctorId)
            : "todo o consultório";
        $period = agenda_period_label($startAt, $endAt);
        $lock = pdo()->inTransaction() ? " FOR UPDATE" : "";
        $apptSql =
            "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado','nao_compareceu','reagendado') AND start_at < ? AND end_at > ?";
        $apptParams = [$cid, $endAt, $startAt];
        if ($ignoreAppointmentId > 0) {
            $apptSql .= " AND id<>?";
            $apptParams[] = $ignoreAppointmentId;
        }
        if ($doctorId !== null) {
            $apptSql .= " AND doctor_user_id=?";
            $apptParams[] = $doctorId;
        }
        $apptSql .= " ORDER BY start_at ASC LIMIT 1" . $lock;
        $appt = one($apptSql, $apptParams);
        if ($appt) {
            $patient = "";
            $pl = one(
                "SELECT p.full_name FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=?",
                [(int) ($appt["patient_link_id"] ?? 0), $cid],
            );
            if ($pl && !empty($pl["full_name"])) {
                $patient = " de " . (string) $pl["full_name"];
            }
            $doc = "";
            if (!empty($appt["doctor_user_id"])) {
                $doc =
                    " · profissional: " .
                    first_name(
                        agenda_doctor_name($cid, (int) $appt["doctor_user_id"]),
                    );
            }
            $status = mb_trim((string) ($appt["status"] ?? "agendado"));
            $reason = mb_trim((string) ($appt["reason"] ?? ""));
            $detail =
                "consulta" .
                ($patient !== "" ? $patient : "") .
                " das " .
                app_time_br((string) $appt["start_at"], $cid) .
                " às " .
                app_time_br((string) $appt["end_at"], $cid) .
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
        $blockSql =
            "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at < ? AND end_at > ?";
        $blockParams = [$cid, $endAt, $startAt];
        if ($ignoreBlockId > 0) {
            $blockSql .= " AND id<>?";
            $blockParams[] = $ignoreBlockId;
        }
        if ($doctorId !== null) {
            $blockSql .= " AND (doctor_user_id IS NULL OR doctor_user_id=?)";
            $blockParams[] = $doctorId;
        }
        $blockSql .= " ORDER BY start_at ASC LIMIT 1" . $lock;
        $block = one($blockSql, $blockParams);
        if ($block) {
            $creator = "";
            $u = one(
                "SELECT u.name FROM pi_users u WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) LIMIT 1",
                [(int) ($block["created_by"] ?? 0), $cid],
            );
            if ($u && !empty($u["name"])) {
                $creator = " · bloqueado por " . first_name((string) $u["name"]);
            }
            $doctorLabel = empty($block["doctor_user_id"])
                ? "todo o consultório"
                : "agenda de " .
                    first_name(
                        agenda_doctor_name($cid, (int) $block["doctor_user_id"]),
                    );
            $reason = mb_trim((string) ($block["reason"] ?? ""));
            $detail =
                "bloqueio de " .
                $doctorLabel .
                " das " .
                app_time_br((string) $block["start_at"], $cid) .
                " às " .
                app_time_br((string) $block["end_at"], $cid) .
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
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ||
            !agenda_notes_ensure_schema($cid)
        ) {
            return null;
        }
        try {
            $roles = agenda_note_role_codes($c);
            $params = [$cid, $day];
            $roleSql = "0=1";
            if ($roles) {
                $ph = implode(",", array_fill(0, count($roles), "?"));
                $roleSql = "(n.target_scope='role' AND n.target_role IN ($ph))";
                $params = array_merge($params, $roles);
            }
            $rows = q(
                "SELECT n.id,n.note_date,n.content,n.target_scope,n.target_role,n.created_by,n.updated_by,n.deleted_by,n.created_at,n.updated_at,n.deleted_at,u.name AS created_by_name FROM pi_agenda_notes n LEFT JOIN pi_users u ON u.id=n.created_by WHERE n.clinic_id=? AND n.note_date=? AND n.deleted_at IS NULL AND (n.target_scope IN ('clinic','all') OR $roleSql) ORDER BY n.id DESC LIMIT 1",
                $params,
            )->fetchAll();
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
            if (function_exists("ctx")) {
                $c = ctx();
            } else {
                $c = [];
            }
        }
        $uid = (int) ($c["user"]["id"] ?? 0);
        $creator = (int) ($note["created_by"] ?? 0);
        $creatorName = first_name((string) ($note["created_by_name"] ?? ""));
        $day = (string) ($note["note_date"] ?? "");
        $noteId = (int) ($note["id"] ?? 0);
        $side = "";
        if ($uid > 0 && $creator > 0 && $uid === $creator && $noteId > 0) {
            $side =
                '<form method="post" class="agenda-day-note-delete" onsubmit="return confirm(\'Excluir esta anotação?\');">' .
                csrf_field() .
                '<input type="hidden" name="act" value="agenda_note_delete"><input type="hidden" name="id" value="' .
                e((string) $noteId) .
                '"><input type="hidden" name="return_day" value="' .
                e($day) .
                '"><input type="hidden" name="return_doctor" value="' .
                (int) ($_GET["doctor"] ?? 0) .
                '"><button type="submit" class="ghost small danger">' .
                icon("delete") .
                "<span>Excluir</span></button></form>";
        } else {
            $side =
                '<span class="agenda-day-note-author" title="Incluída por ' .
                e($creatorName) .
                '">' .
                e($creatorName) .
                "</span>";
        }
        return '<section class="agenda-day-note-card" aria-label="Anotação da agenda"><span class="agenda-day-note-icon">' .
            icon("sticky_note_2") .
            '</span><div class="agenda-day-note-copy"><p>' .
            nl2br(e($content)) .
            '</p></div><div class="agenda-day-note-side">' .
            $side .
            "</div></section>";
    
    }
}
