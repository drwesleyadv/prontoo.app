<?php
declare(strict_types=1);
function work_weekday_labels(): array
{
    /*
     * GUIA DE MANUTENÇÃO — work_weekday_labels
     * Responsabilidade: Implementa a responsabilidade “work weekday labels” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `default_work_hours_rows`, `save_user_work_hours`, `work_hours_form_html`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function normalize_work_time(?string $v, string $fallback = ""): string
{
    /*
     * GUIA DE MANUTENÇÃO — normalize_work_time
     * Responsabilidade: Transforma e normaliza “normalize work time” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `save_user_work_hours`.
     * Dependências chamadas: `trim`, `preg_match`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $v = trim((string) $v);
    if (preg_match('/^\d{2}:\d{2}$/', $v)) {
        return $v . ":00";
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) {
        return $v;
    }
    return $fallback;
}
function work_time_short(?string $v): string
{
    /*
     * GUIA DE MANUTENÇÃO — work_time_short
     * Responsabilidade: Implementa a responsabilidade “work time short” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `work_hours_form_html`, `doctor_work_ranges_for_day`.
     * Dependências chamadas: `preg_match`, `substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $v = (string) $v;
    return preg_match("/^\d{2}:\d{2}/", $v) ? substr($v, 0, 5) : "";
}
function default_work_hours_rows(): array
{
    /*
     * GUIA DE MANUTENÇÃO — default_work_hours_rows
     * Responsabilidade: Implementa a responsabilidade “default work hours rows” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `user_work_hours`.
     * Dependências chamadas: `work_weekday_labels`, `in_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $out = [];
    foreach (work_weekday_labels() as $wd => $label) {
        $out[$wd] = [
            "weekday" => $wd,
            "active" => in_array($wd, [1, 2, 3, 4, 5], true) ? 1 : 0,
            "start_time" => "08:00:00",
            "end_time" => "17:00:00",
        ];
    }
    return $out;
}
function user_work_hours(int $cid, int $uid): array
{
    /*
     * GUIA DE MANUTENÇÃO — user_work_hours
     * Responsabilidade: Implementa a responsabilidade “user work hours” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `work_hours_form_html`, `doctor_work_ranges_for_day`.
     * Dependências chamadas: `default_work_hours_rows`, `q`, `->fetchAll`, `array_key_exists`, `error_log`, `->getMessage`, `function_exists`, `server_json_cache_remember`, `server_json_cache_safe_key`, `server_json_cache_ttl`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $defaults = default_work_hours_rows();
    if ($cid <= 0 || $uid <= 0) {
        return $defaults;
    }
    $loader = function () use ($cid, $uid, $defaults): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:50
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `q`, `->fetchAll`, `array_key_exists`, `error_log`, `->getMessage`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $hours = $defaults;
        try {
            $rows = q(
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
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "work_hours",
            server_json_cache_safe_key("hours", [$cid, $uid]),
            server_json_cache_ttl("work_hours"),
            $loader,
            ["clinic:" . $cid, "user:" . $uid],
        );
    }
    return $loader();
}
function save_user_work_hours(int $cid, int $uid, array $data): void
{
    /*
     * GUIA DE MANUTENÇÃO — save_user_work_hours
     * Responsabilidade: Valida e executa a mutação “save user work hours”, preservando as invariantes do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_users`, `page_user`.
     * Dependências chamadas: `require_user_in_clinic`, `work_weekday_labels`, `normalize_work_time`, `strtotime`, `RuntimeException`, `q`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; lê ou altera a sessão; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    if ($cid <= 0 || $uid <= 0) {
        return;
    }
    require_user_in_clinic($cid, $uid, ["medico"]);
    $labels = work_weekday_labels();
    $active = (array) ($data["work_active"] ?? []);
    $starts = (array) ($data["work_start"] ?? []);
    $ends = (array) ($data["work_end"] ?? []);
    foreach ($labels as $wd => $label) {
        $isActive = isset($active[$wd]) ? 1 : 0;
        $start = normalize_work_time(
            (string) ($starts[$wd] ?? "08:00"),
            "08:00:00",
        );
        $end = normalize_work_time(
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
        q(
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
function work_hours_form_html(int $cid, int $uid = 0): string
{
    /*
     * GUIA DE MANUTENÇÃO — work_hours_form_html
     * Responsabilidade: Monta a representação de interface associada a “work hours form html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_users`, `page_user`.
     * Dependências chamadas: `user_work_hours`, `icon`, `work_weekday_labels`, `e`, `work_time_short`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $hours = user_work_hours($cid, $uid);
    $h =
        '<details class="work-hours-panel" open><summary>' .
        icon("schedule") .
        '<span>Expediente do profissional</span></summary><p class="muted-copy">Usado pela Agenda para limitar os horários que a equipe pode marcar para este profissional.</p><div class="work-hours-grid">';
    foreach (work_weekday_labels() as $wd => $label) {
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
            e($label) .
            '</span><span class="work-hour-fields"><input type="time" name="work_start[' .
            $wd .
            ']" value="' .
            e(work_time_short((string) $r["start_time"])) .
            '"><em>até</em><input type="time" name="work_end[' .
            $wd .
            ']" value="' .
            e(work_time_short((string) $r["end_time"])) .
            '"></span></label>';
    }
    return $h . "</div></details>";
}
function doctor_work_ranges_for_day(int $cid, int $doctorId, string $day): array
{
    /*
     * GUIA DE MANUTENÇÃO — doctor_work_ranges_for_day
     * Responsabilidade: Implementa a responsabilidade “doctor work ranges for day” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `doctor_work_hours_label`, `appointment_within_doctor_hours`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:4397`.
     * Dependências chamadas: `preg_match`, `DateTimeZone`, `app_context_timezone`, `DateTimeImmutable`, `->setTimezone`, `->format`, `user_work_hours`, `work_time_short`, `->getTimestamp`.
     * Classes ou serviços instanciados: `DateTimeZone`, `DateTimeImmutable`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (
        $cid <= 0 ||
        $doctorId <= 0 ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)
    ) {
        return [];
    }
    $zone = new DateTimeZone(app_context_timezone(null, $cid));
    $fmtLocal = function (int $ts) use ($zone): string {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:172
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `DateTimeImmutable`, `->setTimezone`, `->format`.
         * Classes ou serviços instanciados: `DateTimeImmutable`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return new DateTimeImmutable("@" . $ts)
            ->setTimezone($zone)
            ->format("H:i");
    };
    $wd = (int) new DateTimeImmutable($day . " 00:00:00", $zone)->format("w");
    $hours = user_work_hours($cid, $doctorId);
    $r = $hours[$wd] ?? null;
    if (!$r || empty($r["active"])) {
        return [];
    }
    $start = work_time_short((string) $r["start_time"]);
    $end = work_time_short((string) $r["end_time"]);
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
function doctor_work_hours_label(int $cid, int $doctorId, string $day): string
{
    /*
     * GUIA DE MANUTENÇÃO — doctor_work_hours_label
     * Responsabilidade: Monta a representação de interface associada a “doctor work hours label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `doctor_work_hours_conflict_message`.
     * Dependências chamadas: `doctor_work_ranges_for_day`, `implode`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ranges = doctor_work_ranges_for_day($cid, $doctorId, $day);
    if (!$ranges) {
        return "Sem expediente cadastrado para este dia.";
    }
    $parts = [];
    foreach ($ranges as $r) {
        $parts[] = $r[2] . "–" . $r[3];
    }
    return "Expediente: " . implode(", ", $parts);
}
function appointment_within_doctor_hours(
    int $cid,
    int $doctorId,
    string $startAt,
    string $endAt,
): bool {
    /*
     * GUIA DE MANUTENÇÃO — appointment_within_doctor_hours
     * Responsabilidade: Implementa a responsabilidade “appointment within doctor hours” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `doctor_work_hours_conflict_message`.
     * Dependências chamadas: `app_db_utc_to_local`, `->format`, `->getTimestamp`, `doctor_work_ranges_for_day`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ls = app_db_utc_to_local($startAt, $cid);
    $le = app_db_utc_to_local($endAt, $cid);
    if (!$ls || !$le || $le <= $ls) {
        return false;
    }
    $date = $ls->format("Y-m-d");
    $s = $ls->getTimestamp();
    $e = $le->getTimestamp();
    foreach (doctor_work_ranges_for_day($cid, $doctorId, $date) as $range) {
        if ($s >= $range[0] && $e <= $range[1]) {
            return true;
        }
    }
    return false;
}
function doctor_work_hours_conflict_message(
    int $cid,
    int $doctorId,
    string $startAt,
    string $endAt,
): string {
    /*
     * GUIA DE MANUTENÇÃO — doctor_work_hours_conflict_message
     * Responsabilidade: Implementa a responsabilidade “doctor work hours conflict message” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `appointment_within_doctor_hours`, `substr`, `doctor_work_hours_label`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (appointment_within_doctor_hours($cid, $doctorId, $startAt, $endAt)) {
        return "";
    }
    $day = substr($startAt, 0, 10);
    return "Este horário está fora do expediente do profissional. " .
        doctor_work_hours_label($cid, $doctorId, $day);
}
function agenda_validate_period_message(
    string $startAt,
    string $endAt,
    string $operation,
    int $cid = 0,
): ?string {
    /*
     * GUIA DE MANUTENÇÃO — agenda_validate_period_message
     * Responsabilidade: Avalia ou impõe a regra “agenda validate period message”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_conflict_message`.
     * Dependências chamadas: `app_parse_db_utc`, `app_db_utc_to_local`, `->getTimestamp`, `time`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $sUtc = app_parse_db_utc($startAt);
    $eUtc = app_parse_db_utc($endAt);
    $sLoc = app_db_utc_to_local($startAt, $cid);
    $eLoc = app_db_utc_to_local($endAt, $cid);
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
function appointment_status_code(array $appointment): string
{
    /*
     * GUIA DE MANUTENÇÃO — appointment_status_code
     * Responsabilidade: Implementa a responsabilidade “appointment status code” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_is_cancelled`, `is_done_appointment`, `appointment_is_terminal`, `appointment_journey_hard_guard_message`, `appointment_journey_view_model`, `appointment_journey_view_code`, `appointment_journey_role_actions`, `page_appointments` e mais 8.
     * Dependências chamadas: `mb_strtolower`, `trim`, `str_replace`, `in_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $status = mb_strtolower(trim((string) ($appointment["status"] ?? "")));
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

function appointment_is_cancelled(array $a): bool
{
    /*
     * GUIA DE MANUTENÇÃO — appointment_is_cancelled
     * Responsabilidade: Avalia ou impõe a regra “appointment is cancelled”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `appointment_status_code`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return appointment_status_code($a) === "cancelado";
}
function is_done_appointment(array $a): bool
{
    /*
     * GUIA DE MANUTENÇÃO — is_done_appointment
     * Responsabilidade: Avalia ou impõe a regra “is done appointment”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_medico_painel`.
     * Dependências chamadas: `in_array`, `appointment_status_code`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return in_array(
        appointment_status_code($a),
        ["atendimento_concluido", "finalizado"],
        true,
    );
}
function appointment_is_terminal(array $a): bool
{
    /*
     * GUIA DE MANUTENÇÃO — appointment_is_terminal
     * Responsabilidade: Avalia ou impõe a regra “appointment is terminal”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_triagem_painel`.
     * Dependências chamadas: `in_array`, `appointment_status_code`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return in_array(
        appointment_status_code($a),
        ["finalizado", "cancelado", "nao_compareceu", "reagendado"],
        true,
    );
}
function appointment_journey_hard_guard_message(
    array $a,
    string $action,
    string $role = "",
    int $uid = 0,
    ?int $nowTs = null,
): string {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_hard_guard_message
     * Responsabilidade: Avalia ou impõe a regra “appointment journey hard guard message”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_can`, `appointment_journey_role_actions`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3468`.
     * Dependências chamadas: `time`, `appointment_status_code`, `app_storage_timestamp`, `in_array`, `appointment_journey_role_matches`, `mb_strtolower`, `trim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $nowTs = $nowTs ?? time();
    $code = appointment_status_code($a);
    $startTs = app_storage_timestamp((string) ($a["start_at"] ?? "")) ?: 0;
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
        appointment_journey_role_matches($role, "recepcionista") ||
        appointment_journey_role_matches($role, "gerente");
    $isAssistant = appointment_journey_role_matches($role, "assistente");
    $isDoctor = appointment_journey_role_matches($role, "medico");
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
                trim((string) ($a["payment_status"] ?? "")),
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
function appointment_journey_can(
    array $a,
    string $action,
    string $role = "",
    int $uid = 0,
    ?int $nowTs = null,
): bool {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_can
     * Responsabilidade: Avalia ou impõe a regra “appointment journey can”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `appointment_journey_hard_guard_message`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return appointment_journey_hard_guard_message(
        $a,
        $action,
        $role,
        $uid,
        $nowTs,
    ) === "";
}
function appointment_journey_elapsed_label(
    array $a,
    string $code,
    int $nowTs,
): string {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_elapsed_label
     * Responsabilidade: Monta a representação de interface associada a “appointment journey elapsed label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_view_model`.
     * Dependências chamadas: `app_storage_timestamp`, `in_array`, `floor`, `max`, `format_minutes`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $pick = function (array $keys) use ($a): int {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:561
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `app_storage_timestamp`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        foreach ($keys as $k) {
            $ts = app_storage_timestamp((string) ($a[$k] ?? ""));
            if ($ts > 0) {
                return $ts;
            }
        }
        return 0;
    };
    $ts = 0;
    if (in_array($code, ["agendado", "confirmado", "atrasado"], true)) {
        $ts = app_storage_timestamp((string) ($a["start_at"] ?? "")) ?: 0;
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
        return "Atrasado há " . format_minutes($mins);
    }
    if (in_array($code, ["finalizado", "cancelado", "reagendado"], true)) {
        return "";
    }
    if ($code === "nao_compareceu") {
        return "Ausência registrada há " . format_minutes($mins);
    }
    return "Na etapa há " . format_minutes($mins);
}
function appointment_journey_steps(): array
{
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_steps
     * Responsabilidade: Implementa a responsabilidade “appointment journey steps” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_view_model`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function appointment_journey_view_model(
    array $a,
    string $role = "",
    ?int $nowTs = null,
): array {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_view_model
     * Responsabilidade: Monta a representação de interface associada a “appointment journey view model” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_meta`, `appointment_journey_compact_html`, `appointment_journey_card_html`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3468`.
     * Dependências chamadas: `time`, `appointment_status_code`, `app_storage_timestamp`, `in_array`, `appointment_journey_steps`, `appointment_journey_elapsed_label`, `appointment_journey_role_actions`, `appointment_journey_role_hint`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $nowTs = $nowTs ?? time();
    $technical = appointment_status_code($a);
    $code = $technical;
    $start = app_storage_timestamp((string) ($a["start_at"] ?? "")) ?: 0;
    if (
        in_array($code, ["agendado", "confirmado"], true) &&
        $start > 0 &&
        $start < $nowTs
    ) {
        $code = "atrasado";
    }
    $map = [
        "agendado" => [
            "Agendamento",
            "agendamento",
            "Agendado",
            "neutral",
            "event",
            "Recepção",
            "Confirmar ou aguardar chegada.",
        ],
        "confirmado" => [
            "Agendamento",
            "agendamento",
            "Confirmado",
            "neutral",
            "event_available",
            "Recepção",
            "Registrar chegada.",
        ],
        "atrasado" => [
            "Agendamento",
            "agendamento",
            "Atrasado",
            "bad",
            "warning",
            "Recepção",
            "Registrar chegada ou ausência.",
        ],
        "chegou" => [
            "Chegada",
            "chegada",
            "Paciente chegou",
            "warn",
            "how_to_reg",
            "Assistente",
            "Iniciar preparo.",
        ],
        "em_preparo" => [
            "Preparo",
            "preparo",
            "Em preparo",
            "warn",
            "clinical_notes",
            "Assistente",
            "Concluir preparo.",
        ],
        "pronto_atendimento" => [
            "Preparo",
            "preparo",
            "Pronto para atendimento",
            "info",
            "chair",
            "Profissional",
            "Iniciar atendimento.",
        ],
        "em_atendimento" => [
            "Atendimento",
            "atendimento",
            "Em atendimento",
            "info",
            "stethoscope",
            "Profissional",
            "Concluir atendimento.",
        ],
        "atendimento_concluido" => [
            "Saída",
            "saida",
            "Atendimento concluído",
            "warn",
            "task_alt",
            "Recepção",
            "Finalizar saída.",
        ],
        "finalizado" => [
            "Saída",
            "saida",
            "Finalizado",
            "ok",
            "done_all",
            "Jornada concluída",
            "Jornada concluída.",
        ],
        "cancelado" => [
            "Exceção",
            "excecao",
            "Cancelado",
            "bad",
            "event_busy",
            "Recepção/Admin",
            "Histórico preservado.",
        ],
        "nao_compareceu" => [
            "Exceção",
            "excecao",
            "Não compareceu",
            "bad",
            "person_off",
            "Recepção",
            "Avaliar remarcação.",
        ],
        "reagendado" => [
            "Exceção",
            "excecao",
            "Reagendado",
            "neutral",
            "event_repeat",
            "Recepção",
            "Acompanhar novo horário.",
        ],
    ];
    $m = $map[$code] ?? $map["agendado"];
    $order = [
        "agendamento" => 0,
        "chegada" => 1,
        "preparo" => 2,
        "atendimento" => 3,
        "saida" => 4,
    ];
    $phaseIndex = $order[$m[1]] ?? -1;
    $steps = [];
    foreach (appointment_journey_steps() as $idx => $step) {
        $state = "upcoming";
        if ($phaseIndex < 0) {
            $state = "muted";
        } elseif ($idx < $phaseIndex) {
            $state = "done";
        } elseif ($idx === $phaseIndex) {
            $state = "current";
        }
        $step["state"] = $state;
        $steps[] = $step;
    }
    return [
        "code" => $code,
        "technical_code" => $technical,
        "phase_label" => $m[0],
        "phase" => $m[1],
        "phase_index" => $phaseIndex,
        "label" => $m[2],
        "class" => $m[3],
        "icon" => $m[4],
        "owner" => $m[5],
        "action" => $m[6],
        "elapsed" => appointment_journey_elapsed_label($a, $code, $nowTs),
        "steps" => $steps,
        "role" => $role,
        "role_actions" => appointment_journey_role_actions($a, $role, $nowTs),
        "role_hint" => appointment_journey_role_hint($a, $role, $nowTs),
    ];
}
function appointment_journey_meta(
    array $a,
    string $role = "",
    ?int $nowTs = null,
): array {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_meta
     * Responsabilidade: Implementa a responsabilidade “appointment journey meta” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3458`, `patient_directory_card`, `page_medico_painel`, `page_recepcao_painel`, `closure@app/Pages/Dashboards.php:239`, `page_triagem_painel`, `closure@app/Pages/Dashboards.php:532`.
     * Dependências chamadas: `appointment_journey_view_model`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return appointment_journey_view_model($a, $role, $nowTs);
}
function appointment_journey_view_code(array $a, ?int $nowTs = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_view_code
     * Responsabilidade: Monta a representação de interface associada a “appointment journey view code” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_role_hint`, `appointment_journey_role_actions`.
     * Dependências chamadas: `time`, `appointment_status_code`, `app_storage_timestamp`, `in_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $nowTs = $nowTs ?? time();
    $code = appointment_status_code($a);
    $start = app_storage_timestamp((string) ($a["start_at"] ?? "")) ?: 0;
    if (
        in_array($code, ["agendado", "confirmado"], true) &&
        $start > 0 &&
        $start < $nowTs
    ) {
        return "atrasado";
    }
    return $code;
}
function appointment_journey_role_matches(string $role, string $expected): bool
{
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_role_matches
     * Responsabilidade: Implementa a responsabilidade “appointment journey role matches” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_hard_guard_message`, `appointment_journey_role_actions`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3468`, `financial_appointment_operational_chip_html`, `page_patient`.
     * Dependências chamadas: `str_replace`, `mb_strtolower`, `trim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $normalize = static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de domínio e regras de negócio. Dependências diretas: `str_replace`, `mb_strtolower`, `trim`. Efeitos: transformação local sem efeito externo detectado. */ fn(string $value): string => str_replace(
        ["-", " "],
        "_",
        mb_strtolower(trim($value), "UTF-8"),
    );
    return $normalize($role) === $normalize($expected);
}

function appointment_journey_role_hint(
    array $a,
    string $role = "",
    ?int $nowTs = null,
): array {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_role_hint
     * Responsabilidade: Implementa a responsabilidade “appointment journey role hint” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_view_model`.
     * Dependências chamadas: `appointment_journey_view_code`, `in_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $vmCode = appointment_journey_view_code($a, $nowTs);
    $role = (string) $role;
    $map = [
        "recepcionista" => [
            "label" => "Entrada e saída",
            "icon" => "support_agent",
            "hint" => "Movimente chegada, ausência e saída administrativa.",
        ],
        "assistente" => [
            "label" => "Preparo",
            "icon" => "clinical_notes",
            "hint" =>
                "Assuma a preparação e libere o paciente para o profissional.",
        ],
        "medico" => [
            "label" => "Atendimento",
            "icon" => "stethoscope",
            "hint" =>
                "Inicie e conclua a etapa clínica quando o paciente estiver liberado.",
        ],
        "gerente" => [
            "label" => "Supervisão",
            "icon" => "monitoring",
            "hint" =>
                "Acompanhe gargalos e exceções sem tomar a fila da equipe.",
        ],
    ];
    $base = $map[$role] ?? [
        "label" => "Jornada",
        "icon" => "route",
        "hint" => "Acompanhe a próxima medida da jornada.",
    ];
    if (
        in_array(
            $vmCode,
            ["finalizado", "cancelado", "nao_compareceu", "reagendado"],
            true,
        )
    ) {
        $base["hint"] =
            "Jornada encerrada ou em exceção; acompanhar apenas se houver pendência administrativa.";
    }
    return $base;
}
function appointment_journey_role_actions(
    array $a,
    string $role = "",
    ?int $nowTs = null,
    int $uid = 0,
): array {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_role_actions
     * Responsabilidade: Implementa a responsabilidade “appointment journey role actions” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_view_model`, `appointment_journey_quick_actions_html`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3468`.
     * Dependências chamadas: `time`, `appointment_status_code`, `appointment_journey_view_code`, `app_storage_timestamp`, `appointment_journey_role_matches`, `in_array`, `appointment_journey_hard_guard_message`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $nowTs = $nowTs ?? time();
    $role = (string) $role;
    $technical = appointment_status_code($a);
    $display = appointment_journey_view_code($a, $nowTs);
    $start = app_storage_timestamp((string) ($a["start_at"] ?? "")) ?: 0;
    $actions = [];
    $add = function (
        string $act,
        string $label,
        string $icon,
        string $class = "primary",
        string $title = "",
        bool $danger = false,
    ) use (&$actions): void {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:881
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $actions[] = [
            "act" => $act,
            "label" => $label,
            "icon" => $icon,
            "class" => $class,
            "title" => $title,
            "danger" => $danger,
        ];
    };
    if (appointment_journey_role_matches($role, "recepcionista")) {
        if ($technical === "agendado" && empty($a["arrived_at"])) {
            $add(
                "confirm",
                "Confirmar",
                "event_available",
                "ghost",
                "Confirmar presença",
            );
        }
        if (
            in_array($technical, ["agendado", "confirmado"], true) &&
            empty($a["arrived_at"])
        ) {
            $add(
                "arrived",
                "Chegou",
                "how_to_reg",
                "primary",
                "Registrar chegada",
            );
        }
        if (
            $display === "atrasado" &&
            empty($a["arrived_at"]) &&
            $start > 0 &&
            $start < $nowTs
        ) {
            $add(
                "no_show_quick",
                "Não veio",
                "person_off",
                "danger",
                "Marcar ausência",
                true,
            );
        }
        if ($technical === "atendimento_concluido") {
            $add(
                "finish_checkout",
                "Finalizar",
                "logout",
                "primary",
                "Encerrar jornada administrativa",
            );
        }
    } elseif (appointment_journey_role_matches($role, "assistente")) {
        if ($technical === "chegou") {
            $add(
                "start_prepare",
                "Iniciar",
                "play_arrow",
                "primary",
                "Assumir preparo/triagem",
            );
        }
        if ($technical === "em_preparo") {
            $add(
                "finish_prepare",
                "Concluir",
                "task_alt",
                "primary",
                "Concluir preparo e avisar profissional",
            );
        }
    } elseif (appointment_journey_role_matches($role, "medico")) {
        if ($technical === "pronto_atendimento") {
            $add(
                "start_consultation",
                "Iniciar",
                "play_arrow",
                "primary",
                "Abrir atendimento clínico",
            );
        }
        if ($technical === "em_atendimento") {
            $add(
                "finish_consultation",
                "Concluir",
                "task_alt",
                "primary",
                "Encerrar etapa clínica",
            );
        }
    }
    $filtered = [];
    foreach ($actions as $candidate) {
        $real = (string) ($candidate["act"] ?? "");
        if ($real === "no_show_quick") {
            $real = "no_show";
        }
        if (
            $real !== "" &&
            appointment_journey_hard_guard_message(
                $a,
                $real,
                $role,
                $uid,
                $nowTs,
            ) === ""
        ) {
            $filtered[] = $candidate;
        }
    }
    return $filtered;
}
function appointment_journey_quick_actions_html(
    array $a,
    string $role = "",
    ?string $returnHidden = "",
    string $actionUrl = "",
    int $uid = 0,
): string {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_quick_actions_html
     * Responsabilidade: Monta a representação de interface associada a “appointment journey quick actions html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_compact_html`, `appointment_journey_card_html`.
     * Dependências chamadas: `appointment_journey_role_actions`, `e`, `csrf_field`, `icon`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $returnHidden = (string) ($returnHidden ?? "");
    $actions = appointment_journey_role_actions($a, $role, null, $uid);
    if (!$actions) {
        return "";
    }
    $id = (int) ($a["id"] ?? 0);
    if ($id <= 0) {
        return "";
    }
    $actionAttr = $actionUrl !== "" ? ' action="' . e($actionUrl) . '"' : "";
    $html =
        '<span class="journey-ux-quick" aria-label="Ações rápidas da jornada">';
    foreach ($actions as $act) {
        $class = (string) ($act["class"] ?? "primary");
        $title = (string) ($act["title"] ?? $act["label"]);
        $confirm = !empty($act["danger"])
            ? ' onsubmit="return confirm(\'Registrar esta exceção na jornada do paciente?\')"'
            : "";
        $realAct = (string) $act["act"];
        $extra = "";
        if ($realAct === "no_show_quick") {
            $realAct = "no_show";
            $extra =
                '<input type="hidden" name="no_show_reason" value="Paciente não compareceu no horário agendado.">';
        }
        $html .=
            '<form method="post" class="inline journey-ux-move"' .
            $actionAttr .
            $confirm .
            ">" .
            csrf_field() .
            $returnHidden .
            '<input type="hidden" name="act" value="' .
            e($realAct) .
            '"><input type="hidden" name="id" value="' .
            $id .
            '">' .
            $extra .
            '<button type="submit" class="small ' .
            e($class) .
            '" title="' .
            e($title) .
            '">' .
            icon((string) $act["icon"]) .
            "<span>" .
            e((string) $act["label"]) .
            "</span></button></form>";
    }
    return $html . "</span>";
}
function appointment_journey_steps_html(array $vm): string
{
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_steps_html
     * Responsabilidade: Monta a representação de interface associada a “appointment journey steps html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_compact_html`, `appointment_journey_card_html`.
     * Dependências chamadas: `e`, `icon`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $html = '<span class="journey-ux-rail" aria-label="Jornada do paciente">';
    foreach ($vm["steps"] ?? [] as $step) {
        $state = (string) ($step["state"] ?? "upcoming");
        $html .=
            '<span class="journey-ux-step is-' .
            e($state) .
            '" title="' .
            e((string) $step["label"]) .
            '"><span class="journey-ux-dot">' .
            icon((string) $step["icon"]) .
            '</span><span class="journey-ux-step-label">' .
            e((string) $step["label"]) .
            "</span></span>";
    }
    return $html . "</span>";
}
function appointment_journey_fact_html(
    string $iconName,
    string $label,
    string $value,
): string {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_fact_html
     * Responsabilidade: Monta a representação de interface associada a “appointment journey fact html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_compact_html`, `appointment_journey_card_html`.
     * Dependências chamadas: `trim`, `icon`, `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $value = trim($value);
    if ($value === "") {
        $value = "—";
    }
    return '<span class="journey-ux-fact">' .
        icon($iconName) .
        "<b>" .
        e($label) .
        "</b><em>" .
        e($value) .
        "</em></span>";
}
function appointment_journey_compact_html(
    array $a,
    string $role = "",
    ?string $returnHidden = "",
    string $actionUrl = "",
): string {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_compact_html
     * Responsabilidade: Monta a representação de interface associada a “appointment journey compact html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_measure_html`, `patient_directory_card`, `page_recepcao_painel`, `closure@app/Pages/Dashboards.php:249`, `page_triagem_painel`, `closure@app/Pages/Dashboards.php:532`.
     * Dependências chamadas: `appointment_journey_view_model`, `trim`, `appointment_journey_quick_actions_html`, `appointment_journey_fact_html`, `e`, `appointment_journey_steps_html`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $returnHidden = (string) ($returnHidden ?? "");
    $vm = appointment_journey_view_model($a, $role);
    $elapsed = trim((string) ($vm["elapsed"] ?? ""));
    $elapsed = $elapsed !== "" ? $elapsed : "Agora";
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
    return '<span class="journey-ux journey-ux--compact journey-ux--operable journey-ux--timeline-card journey-ux--lean is-' .
        e((string) $vm["class"]) .
        '" title="' .
        e((string) $vm["label"] . " · " . (string) $vm["phase_label"]) .
        '">' .
        appointment_journey_steps_html($vm) .
        '<span class="journey-ux-facts">' .
        $facts .
        "</span>" .
        $moves .
        "</span>";
}
function appointment_journey_card_html(
    array $a,
    string $role = "",
    string $variant = "default",
    ?string $returnHidden = "",
    string $actionUrl = "",
): string {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_card_html
     * Responsabilidade: Monta a representação de interface associada a “appointment journey card html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `appointment_journey_view_model`, `trim`, `appointment_journey_quick_actions_html`, `appointment_journey_fact_html`, `e`, `icon`, `appointment_journey_steps_html`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $returnHidden = (string) ($returnHidden ?? "");
    $vm = appointment_journey_view_model($a, $role);
    $elapsed = trim((string) ($vm["elapsed"] ?? ""));
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
function appointment_journey_measure_html(
    array $a,
    string $role = "",
    ?string $returnHidden = "",
    string $actionUrl = "",
): string {
    /*
     * GUIA DE MANUTENÇÃO — appointment_journey_measure_html
     * Responsabilidade: Monta a representação de interface associada a “appointment journey measure html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3790`.
     * Dependências chamadas: `appointment_journey_compact_html`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return appointment_journey_compact_html(
        $a,
        $role,
        (string) ($returnHidden ?? ""),
        $actionUrl,
    );
}
function delay_minutes_from_appointment(array $a): ?int
{
    /*
     * GUIA DE MANUTENÇÃO — delay_minutes_from_appointment
     * Responsabilidade: Implementa a responsabilidade “delay minutes from appointment” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_medico_painel`.
     * Dependências chamadas: `app_storage_timestamp`, `max`, `floor`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $scheduled = app_storage_timestamp($a["start_at"] ?? "");
    $started = app_storage_timestamp($a["consultation_started_at"] ?? "");
    if (!$scheduled || !$started) {
        return null;
    }
    return max(0, (int) floor(($started - $scheduled) / 60));
}
function format_minutes(?int $minutes): string
{
    /*
     * GUIA DE MANUTENÇÃO — format_minutes
     * Responsabilidade: Transforma e normaliza “format minutes” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_journey_elapsed_label`, `document_context_select_options`, `document_issue_context`, `page_patient`, `page_medico_painel`.
     * Dependências chamadas: `intdiv`, `str_pad`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function appointment_patient_names(array $appointments, ?int $cid = null): array
{
    /*
     * GUIA DE MANUTENÇÃO — appointment_patient_names
     * Responsabilidade: Implementa a responsabilidade “appointment patient names” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_medico_painel`, `page_recepcao_painel`, `page_triagem_painel`.
     * Dependências chamadas: `int_ids`, `scoped_patient_map`, `fetch_map`, `array_values`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function procedure_options(int $cid, bool $activeOnly = true): array
{
    /*
     * GUIA DE MANUTENÇÃO — procedure_options
     * Responsabilidade: Implementa a responsabilidade “procedure options” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `procedure_select_html`, `page_appointments`, `document_context_select_options`, `page_procedures`.
     * Dependências chamadas: `q`, `->fetchAll`, `error_log`, `->getMessage`, `function_exists`, `server_json_cache_remember`, `server_json_cache_safe_key`, `server_json_cache_ttl`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($cid <= 0) {
        return [];
    }
    $loader = function () use ($cid, $activeOnly): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:1248
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `q`, `->fetchAll`, `error_log`, `->getMessage`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
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
function procedure_option_label(array $p): string
{
    /*
     * GUIA DE MANUTENÇÃO — procedure_option_label
     * Responsabilidade: Monta a representação de interface associada a “procedure option label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `procedure_select_html`.
     * Dependências chamadas: `trim`, `money_br`, `implode`, `array_filter`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $parts = [trim((string) $p["title"])];
    $dur = (int) ($p["duration_minutes"] ?? 0);
    if ($dur > 0) {
        $parts[] = $dur . " min";
    }
    $price = (int) ($p["price_cents"] ?? 0);
    if ($price > 0) {
        $parts[] = money_br($price);
    }
    $pay = trim((string) ($p["payment_methods"] ?? ""));
    if ($pay !== "") {
        $parts[] = $pay;
    }
    return implode(" · ", array_filter($parts));
}
function procedure_select_html(
    int $cid,
    string $name = "reason",
    string $selected = "",
    bool $registeredOnly = false,
): string {
    /*
     * GUIA DE MANUTENÇÃO — procedure_select_html
     * Responsabilidade: Localiza, carrega ou resolve os dados de “procedure select html” para consumo pelas camadas superiores.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3468`.
     * Dependências chamadas: `procedure_options`, `e`, `trim`, `money_br`, `procedure_option_label`, `form_row`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
        $pre = trim((string) ($p["pre_instructions"] ?? ""));
        $post = trim((string) ($p["post_care"] ?? ""));
        $desc = trim((string) ($p["description"] ?? ""));
        $summary = trim(
            ($desc !== "" ? $desc . " · " : "") .
                ((int) $p["duration_minutes"]) .
                " min" .
                ((int) $p["price_cents"] > 0
                    ? " · " . money_br((int) $p["price_cents"])
                    : "") .
                (trim((string) $p["payment_methods"]) !== ""
                    ? " · " . trim((string) $p["payment_methods"])
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
function procedure_reason_from_post(int $cid, string $field = "reason"): string
{
    /*
     * GUIA DE MANUTENÇÃO — procedure_reason_from_post
     * Responsabilidade: Implementa a responsabilidade “procedure reason from post” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `trim`, `str_starts_with`, `substr`, `one`.
     * Estado externo lido: `$_POST`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $v = trim((string) ($_POST[$field] ?? ""));
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
function doctors(int $cid): array
{
    /*
     * GUIA DE MANUTENÇÃO — doctors
     * Responsabilidade: Implementa a responsabilidade “doctors” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`, `page_gerente_painel`.
     * Dependências chamadas: `q`, `->fetchAll`, `fetch_map`, `int_ids`, `asort`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function normalize_db_datetime(string $value): string
{
    /*
     * GUIA DE MANUTENÇÃO — normalize_db_datetime
     * Responsabilidade: Transforma e normaliza “normalize db datetime” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `app_local_to_db_utc`, `ctx`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return app_local_to_db_utc($value, (int) (ctx()["clinic_id"] ?? 0));
}
function agenda_period_label(string $startAt, string $endAt): string
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_period_label
     * Responsabilidade: Monta a representação de interface associada a “agenda period label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_conflict_message`.
     * Dependências chamadas: `dt_br`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return dt_br($startAt) . " até " . dt_br($endAt);
}
function datetime_local_value(?string $value): string
{
    /*
     * GUIA DE MANUTENÇÃO — datetime_local_value
     * Responsabilidade: Implementa a responsabilidade “datetime local value” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3468`, `closure@app/Domain/Appointments/Appointments.php:3850`.
     * Dependências chamadas: `app_db_utc_to_local_input`, `ctx`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return app_db_utc_to_local_input($value, (int) (ctx()["clinic_id"] ?? 0));
}
function agenda_doctor_name(int $cid, ?int $doctorId): string
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_doctor_name
     * Responsabilidade: Implementa a responsabilidade “agenda doctor name” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_conflict_message`, `page_appointments`, `closure@app/Domain/Appointments/Appointments.php:3850`.
     * Dependências chamadas: `one`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function agenda_conflict_message(
    int $cid,
    ?int $doctorId,
    string $startAt,
    string $endAt,
    string $operation,
    int $ignoreAppointmentId = 0,
    int $ignoreBlockId = 0,
): ?string {
    /*
     * GUIA DE MANUTENÇÃO — agenda_conflict_message
     * Responsabilidade: Implementa a responsabilidade “agenda conflict message” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `agenda_validate_period_message`, `agenda_doctor_name`, `agenda_period_label`, `pdo`, `->inTransaction`, `one`, `first_name`, `trim`, `app_time_br`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
        $status = trim((string) ($appt["status"] ?? "agendado"));
        $reason = trim((string) ($appt["reason"] ?? ""));
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
        $reason = trim((string) ($block["reason"] ?? ""));
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
function agenda_day_short_label(string $day): string
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_day_short_label
     * Responsabilidade: Monta a representação de interface associada a “agenda day short label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_crown_label_for_view`.
     * Dependências chamadas: `strtotime`, `date`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function agenda_month_name_br(string $day): string
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_month_name_br
     * Responsabilidade: Implementa a responsabilidade “agenda month name br” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_week_range_label`, `agenda_crown_label_for_view`.
     * Dependências chamadas: `strtotime`, `date`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function agenda_day_week_start(string $day): string
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_day_week_start
     * Responsabilidade: Implementa a responsabilidade “agenda day week start” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_week_range_label`.
     * Dependências chamadas: `strtotime`, `date`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ts = strtotime($day . " 12:00:00");
    if (!$ts) {
        return $day;
    }
    return date(
        "Y-m-d",
        strtotime(date("Y-m-d", $ts) . " -" . (int) date("w", $ts) . " days"),
    );
}
function agenda_week_range_label(string $day): string
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_week_range_label
     * Responsabilidade: Monta a representação de interface associada a “agenda week range label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_crown_label_for_view`.
     * Dependências chamadas: `agenda_day_week_start`, `date`, `strtotime`, `agenda_month_name_br`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $start = agenda_day_week_start($day);
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
            agenda_month_name_br($start) .
            " a " .
            date("d", $endTs) .
            " de " .
            agenda_month_name_br($end);
    }
    return date("d", $startTs) .
        " de " .
        agenda_month_name_br($start) .
        " a " .
        date("d", $endTs) .
        " de " .
        agenda_month_name_br($end);
}
function agenda_crown_label_for_view(
    string $view,
    string $day,
    string $doctorName,
): string {
    /*
     * GUIA DE MANUTENÇÃO — agenda_crown_label_for_view
     * Responsabilidade: Monta a representação de interface associada a “agenda crown label for view” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `first_name`, `agenda_week_range_label`, `agenda_month_name_br`, `agenda_day_short_label`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $name = first_name($doctorName);
    return match ($view) {
        "semanal" => agenda_week_range_label($day) . " de " . $name,
        "mensal" => agenda_month_name_br($day) . " de " . $name,
        default => agenda_day_short_label($day) . " de " . $name,
    };
}
function agenda_iso_local_value(string $day, string $hm): string
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_iso_local_value
     * Responsabilidade: Implementa a responsabilidade “agenda iso local value” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_normalize_local_input`.
     * Dependências chamadas: `preg_match`, `date`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        $day = date("Y-m-d");
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $hm)) {
        $hm = "08:00";
    }
    return $day . "T" . $hm;
}
function agenda_normalize_local_input(
    string $value,
    string $fallbackDay,
    string $fallbackHm = "08:00",
): string {
    /*
     * GUIA DE MANUTENÇÃO — agenda_normalize_local_input
     * Responsabilidade: Transforma e normaliza “agenda normalize local input” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `trim`, `preg_match`, `str_replace`, `substr`, `agenda_iso_local_value`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $value = trim($value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match("/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/", $value)) {
        return str_replace(" ", "T", substr($value, 0, 16));
    }
    if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        return agenda_iso_local_value($fallbackDay, $value);
    }
    return agenda_iso_local_value($fallbackDay, $fallbackHm);
}
function agenda_local_input_add_minutes(
    string $value,
    int $minutes = 30,
): string {
    /*
     * GUIA DE MANUTENÇÃO — agenda_local_input_add_minutes
     * Responsabilidade: Implementa a responsabilidade “agenda local input add minutes” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `DateTimeImmutable::createFromFormat`, `->modify`, `->format`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $dt = DateTimeImmutable::createFromFormat("Y-m-d\TH:i", $value);
    if (!$dt) {
        return $value;
    }
    return $dt
        ->modify(($minutes >= 0 ? "+" : "") . $minutes . " minutes")
        ->format("Y-m-d\TH:i");
}
function agenda_safe_day_from_local_input(
    string $value,
    string $fallbackDay,
): string {
    /*
     * GUIA DE MANUTENÇÃO — agenda_safe_day_from_local_input
     * Responsabilidade: Implementa a responsabilidade “agenda safe day from local input” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `preg_match`, `substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return preg_match("/^\d{4}-\d{2}-\d{2}T/", $value)
        ? substr($value, 0, 10)
        : $fallbackDay;
}
function agenda_notes_ensure_schema(int $cid = 0): bool
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_notes_ensure_schema
     * Responsabilidade: Opera a etapa “agenda notes ensure schema” do contrato de banco e instalação, restrita às janelas autorizadas.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_note_visible_for_day`, `page_appointments`.
     * Dependências chamadas: `function_exists`, `has_cfg`, `db_table_exists`, `db_column_exists`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (
        !function_exists("has_cfg") ||
        !has_cfg() ||
        !function_exists("db_table_exists")
    ) {
        return $ready = false;
    }
    return $ready =
        db_table_exists("pi_agenda_notes") &&
        db_column_exists("pi_agenda_notes", "deleted_by") &&
        db_column_exists("pi_agenda_notes", "deleted_at");
}

function agenda_note_role_codes(array $c): array
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_note_role_codes
     * Responsabilidade: Implementa a responsabilidade “agenda note role codes” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `agenda_note_visible_for_user`, `agenda_note_visible_for_day`.
     * Dependências chamadas: `in_array`, `array_unshift`, `array_values`, `array_unique`, `array_filter`, `array_map`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
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
function agenda_note_visible_for_user(array $note, array $c): bool
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_note_visible_for_user
     * Responsabilidade: Implementa a responsabilidade “agenda note visible for user” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `in_array`, `agenda_note_role_codes`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $scope = (string) ($note["target_scope"] ?? "clinic");
    if ($scope === "clinic" || $scope === "all") {
        return true;
    }
    if ($scope === "role") {
        $target = (string) ($note["target_role"] ?? "");
        return $target !== "" &&
            in_array($target, agenda_note_role_codes($c), true);
    }
    return false;
}
function agenda_note_visible_for_day(int $cid, string $day, array $c): ?array
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_note_visible_for_day
     * Responsabilidade: Implementa a responsabilidade “agenda note visible for day” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `preg_match`, `agenda_notes_ensure_schema`, `agenda_note_role_codes`, `implode`, `array_fill`, `count`, `array_merge`, `q`, `->fetchAll`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
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
function agenda_note_card_html(?array $note, ?array $c = null): string
{
    /*
     * GUIA DE MANUTENÇÃO — agenda_note_card_html
     * Responsabilidade: Monta a representação de interface associada a “agenda note card html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `trim`, `is_array`, `function_exists`, `ctx`, `first_name`, `csrf_field`, `e`, `icon`, `nl2br`.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!$note) {
        return "";
    }
    $content = trim((string) ($note["content"] ?? ""));
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
function agenda_note_form_html(
    int $cid,
    string $day,
    array $c,
    array $roleOptions,
): string {
    /*
     * GUIA DE MANUTENÇÃO — agenda_note_form_html
     * Responsabilidade: Monta a representação de interface associada a “agenda note form html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `role_label_for`, `clinic_role_options`, `href`, `e`, `icon`, `date_br`, `select_label`, `csrf_field`, `form_row`, `input`, `textarea`.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $activeRole = (string) ($c["role"] ?? "");
    $activeRoleLabel =
        $activeRole !== "" ? role_label_for($activeRole, $cid) : "meu cargo";
    $scopeOptions = ["my_role" => "Meu Cargo", "clinic" => "Toda Clínica"];
    $roleOptions = $roleOptions ?: clinic_role_options($cid, false);
    $back = href("appointments", ["d" => $day]);
    $returnHidden =
        '<input type="hidden" name="return_day" value="' .
        e($day) .
        '"><input type="hidden" name="return_doctor" value="' .
        (int) ($_GET["doctor"] ?? 0) .
        '">';
    $hero =
        '<div class="agenda-quick-hero agenda-note-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
        icon("sticky_note_2") .
        '</span><div class="agenda-quick-hero-copy"><h2>Nova Anotação</h2><p>Registre uma orientação discreta para a data escolhida na Agenda.</p></div><a class="ghost small agenda-quick-back" href="' .
        $back .
        '">' .
        icon("arrow_back") .
        "<span>Agenda</span></a></div>";
    $summary =
        '<div class="agenda-quick-summary agenda-note-summary" aria-label="Resumo da anotação"><span>' .
        icon("calendar_month") .
        "<b>" .
        e(date_br($day)) .
        "</b><small>Data da Agenda</small></span><span>" .
        icon("visibility") .
        "<b>Acesso controlado</b><small>Rodapé da anotação</small></span></div>";
    $visibility =
        '<footer class="agenda-note-visibility"><div class="agenda-note-visibility-title">' .
        icon("visibility") .
        '<span>Quem pode ver</span></div><div class="agenda-note-visibility-grid agenda-note-visibility-grid--simple">' .
        select_label(
            "Visibilidade",
            "visibility_scope",
            $scopeOptions,
            "my_role",
            "required",
        ) .
        '</div><p class="muted-copy">Em “Meu Cargo”, apenas usuários do seu cargo visualizam. Em “Toda Clínica”, todos os usuários da clínica podem visualizar.</p></footer>';
    return '<section class="form-panel agenda-route-form agenda-note-form-panel">' .
        $hero .
        $summary .
        '<form method="post" class="compact agenda-note-form agenda-structured-form">' .
        csrf_field() .
        $returnHidden .
        '<input type="hidden" name="act" value="agenda_note"><fieldset class="agenda-quick-section agenda-note-content"><legend>' .
        icon("sticky_note_2") .
        "<span>Conteúdo</span></legend>" .
        form_row("Data", input("note_date", "date", $day, "required")) .
        form_row(
            "Conteúdo",
            textarea(
                "content",
                "",
                'required maxlength="4000" rows="8" placeholder="Escreva a anotação para esta data."',
            ),
        ) .
        $visibility .
        '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
        $back .
        '">' .
        icon("close") .
        '<span>Desistir</span></a><button type="submit" class="primary">' .
        icon("save") .
        "<span>Salvar anotação</span></button></div></form></section>";
}
function page_appointments(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_appointments
     * Responsabilidade: Coordena a rota e renderiza a tela “page appointments”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `require_can`, `agenda_notes_ensure_schema`, `doctors`, `patient_options`, `procedure_options`, `app_today_in_timezone`, `preg_match`, `count`, `array_key_first`, `redirect`, `e`, `one` e mais 95.
     * Classes ou serviços instanciados: `DateTimeZone`, `DateTimeImmutable`.
     * Estado externo lido: `$_GET`, `$_SERVER`, `$_POST`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; produz conteúdo de saída; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     * Cuidado 3: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    $c = require_can("appointments");
    $cid = (int) $c["clinic_id"];
    agenda_notes_ensure_schema($cid);
    $docs = doctors($cid);
    $pats = patient_options($cid);
    $prePatientId = (int) ($_GET["patient_id"] ?? 0);
    if ($prePatientId > 0 && !isset($pats[$prePatientId])) {
        $prePatientId = 0;
    }
    $procedures = procedure_options($cid, true);
    $day = (string) ($_GET["d"] ?? app_today_in_timezone($cid, $c));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        $day = app_today_in_timezone($cid, $c);
    }
    $viewDoctor = (int) ($_GET["doctor"] ?? 0);
    if (count($docs) === 1) {
        $viewDoctor = (int) array_key_first($docs);
    } elseif (
        count($docs) > 1 &&
        $viewDoctor > 0 &&
        !isset($docs[$viewDoctor])
    ) {
        $viewDoctor = 0;
    } elseif ($viewDoctor > 0 && !isset($docs[$viewDoctor])) {
        $viewDoctor = 0;
    }
    $returnParams = function (?string $d = null, ?int $doctor = null) use (
        $day,
        $viewDoctor,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:1916
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $d = $d ?: $day;
        $doctor = $doctor ?? $viewDoctor;
        $params = ["d" => $d];
        if ($doctor > 0) {
            $params["doctor"] = $doctor;
        }
        return $params;
    };
    $redirectBack = function (
        ?string $targetDay = null,
        ?int $targetDoctor = null,
    ) use ($returnParams): void {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:1928
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `redirect`.
         * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP.
         * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
         */
        redirect("appointments", $returnParams($targetDay, $targetDoctor));
    };
    $role = (string) ($c["role"] ?? "");
    $uid = (int) ($c["user"]["id"] ?? 0);
    $returnHidden =
        '<input type="hidden" name="return_day" value="' .
        e($day) .
        '"><input type="hidden" name="return_doctor" value="' .
        (int) $viewDoctor .
        '">';
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "create");
        $postDay = (string) ($_POST["return_day"] ?? $day);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDay)) {
            $postDay = $day;
        }
        $postDoctor = (int) ($_POST["return_doctor"] ?? $viewDoctor);
        if ($postDoctor > 0 && !isset($docs[$postDoctor])) {
            $postDoctor = $viewDoctor;
        }
        $postRedirect = function () use (
            $postDay,
            $postDoctor,
            $redirectBack,
        ): void {
            /*
             * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:1952
             * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
             * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
             * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
             * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
             * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
             * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
             */
            $redirectBack($postDay, $postDoctor);
        };
        if ($act === "agenda_note_delete") {
            $noteId = (int) ($_POST["id"] ?? 0);
            $note =
                $noteId > 0
                    ? one(
                        "SELECT id,note_date,created_by FROM pi_agenda_notes WHERE id=? AND clinic_id=? AND deleted_at IS NULL LIMIT 1",
                        [$noteId, $cid],
                    )
                    : null;
            if (!$note) {
                flash("Anotação não encontrada ou já excluída.", "bad");
                $postRedirect();
            }
            $noteDay = (string) ($note["note_date"] ?? $postDay);
            $creator = (int) ($note["created_by"] ?? 0);
            if ($creator <= 0 || $creator !== $uid) {
                flash("Somente quem criou a anotação pode excluí-la.", "bad");
                $redirectBack($noteDay, $postDoctor);
            }
            q(
                "UPDATE pi_agenda_notes SET deleted_at=NOW(), deleted_by=?, updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND deleted_at IS NULL AND created_by=?",
                [$uid, $uid, $noteId, $cid, $uid],
            );
            audit("agenda_anotacao_excluida", "agenda_note", $noteId, [
                "note_date" => $noteDay,
                "audit_body" =>
                    "Anotação da Agenda excluída logicamente pelo usuário que a criou.",
            ]);
            flash("Anotação excluída.");
            $redirectBack($noteDay, $postDoctor);
        }
        if ($act === "agenda_note") {
            $noteDate = (string) ($_POST["note_date"] ?? $postDay);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDate)) {
                $noteDate = $postDay;
            }
            $content = trim((string) ($_POST["content"] ?? ""));
            if ($content === "") {
                flash("Informe o conteúdo da anotação.", "bad");
                $postRedirect();
            }
            $content = mb_substr($content, 0, 4000);
            $visibility = (string) ($_POST["visibility_scope"] ?? "my_role");
            $roleOptions = clinic_role_options($cid, false);
            $targetScope = "clinic";
            $targetRole = null;
            if ($visibility === "clinic") {
                $targetScope = "clinic";
                $targetRole = null;
            } else {
                $targetScope = "role";
                $targetRole = (string) ($c["role"] ?? "");
                if ($targetRole === "" || !isset($roleOptions[$targetRole])) {
                    flash(
                        "Não foi possível identificar seu cargo para restringir a anotação.",
                        "bad",
                    );
                    $postRedirect();
                }
            }
            q(
                "INSERT INTO pi_agenda_notes (clinic_id,note_date,content,target_scope,target_role,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())",
                [
                    $cid,
                    $noteDate,
                    $content,
                    $targetScope,
                    $targetRole,
                    (int) $c["user"]["id"],
                    (int) $c["user"]["id"],
                ],
            );
            audit(
                "agenda_anotacao_criada",
                "agenda_note",
                db_last_insert_id(),
                [
                    "note_date" => $noteDate,
                    "target_scope" => $targetScope,
                    "target_role" => $targetRole,
                ],
            );
            flash("Anotação registrada para a data escolhida.");
            $redirectBack($noteDate, $postDoctor);
        }
        if ($act === "confirm") {
            $id = (int) ($_POST["id"] ?? 0);
            $a = one(
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                [$id, $cid],
            );
            $guard = $a
                ? appointment_journey_hard_guard_message(
                    $a,
                    "confirm",
                    $role,
                    $uid,
                )
                : "Agendamento não encontrado.";
            if ($guard === "") {
                $stmt = q(
                    "UPDATE pi_appointments SET status='confirmado', updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                    [$id, $cid],
                );
                if ($stmt->rowCount() <= 0) {
                    flash(
                        "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                audit("consulta_confirmada", "consulta", $id, [
                    "patient_link_id" => (int) ($a["patient_link_id"] ?? 0),
                    "audit_body" =>
                        "Presença do paciente confirmada pela Recepção. Próxima medida: registrar chegada quando ele estiver no consultório.",
                ]);
                flash(
                    "Presença confirmada. Próxima medida: registrar chegada no horário.",
                );
            } else {
                flash($guard, "bad");
            }
            $postRedirect();
        }
        if ($act === "arrived") {
            $id = (int) ($_POST["id"] ?? 0);
            $a = one(
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                [$id, $cid],
            );
            $guard = $a
                ? appointment_journey_hard_guard_message(
                    $a,
                    "arrived",
                    $role,
                    $uid,
                )
                : "Agendamento não encontrado.";
            if ($guard === "") {
                $stmt = q(
                    "UPDATE pi_appointments SET status='chegou', arrived_at=COALESCE(arrived_at,NOW()), updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                    [$id, $cid],
                );
                if ($stmt->rowCount() <= 0) {
                    flash(
                        "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                workflow_on_patient_arrived(
                    $cid,
                    $id,
                    (int) ($a["patient_link_id"] ?? 0),
                );
                audit("paciente_chegou", "consulta", $id, [
                    "patient_link_id" => (int) ($a["patient_link_id"] ?? 0),
                    "audit_body" =>
                        "Paciente chegou ao consultório. A equipe de triagem recebeu tarefa automática para preparar o atendimento.",
                ]);
                flash(
                    "Chegada registrada. Próxima medida: Assistente inicia preparo/triagem.",
                );
            } else {
                flash($guard, "bad");
            }
            $postRedirect();
        }
        if ($act === "no_show") {
            $id = (int) ($_POST["id"] ?? 0);
            $reason = trim((string) ($_POST["no_show_reason"] ?? ""));
            $a = one(
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                [$id, $cid],
            );
            if (!$a) {
                flash("Agendamento não encontrado.", "bad");
                $postRedirect();
            }
            $guard = appointment_journey_hard_guard_message(
                $a,
                "no_show",
                $role,
                $uid,
            );
            if ($guard !== "") {
                flash($guard, "bad");
                $postRedirect();
            }
            $stmt = q(
                "UPDATE pi_appointments SET status='nao_compareceu', cancel_reason=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                [
                    $reason !== ""
                        ? $reason
                        : "Paciente não compareceu no horário agendado.",
                    $id,
                    $cid,
                ],
            );
            if ($stmt->rowCount() <= 0) {
                flash(
                    "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                    "bad",
                );
                $postRedirect();
            }
            if (function_exists("financial_cancel_appointment_revenue")) {
                financial_cancel_appointment_revenue(
                    $cid,
                    $id,
                    $uid,
                    $reason !== ""
                        ? $reason
                        : "Paciente não compareceu no horário agendado.",
                );
            }
            audit("paciente_nao_compareceu", "consulta", $id, [
                "patient_link_id" => (int) ($a["patient_link_id"] ?? 0),
                "motivo" => $reason,
                "audit_body" =>
                    "Ausência registrada. Próxima medida: Recepção decide contato, orientação ou remarcação.",
            ]);
            flash(
                "Não comparecimento registrado. Próxima medida: orientar contato ou remarcação.",
            );
            $postRedirect();
        }
        if (
            in_array(
                $act,
                [
                    "start_prepare",
                    "finish_prepare",
                    "start_consultation",
                    "finish_consultation",
                    "finish_checkout",
                ],
                true,
            )
        ) {
            $id = (int) ($_POST["id"] ?? 0);
            $a = one(
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at,payment_status,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?",
                [$id, $cid],
            );
            if (!$a) {
                flash("Agendamento não encontrado.", "bad");
                $postRedirect();
            }
            $code = appointment_status_code($a);
            $patientId = (int) ($a["patient_link_id"] ?? 0);
            $uidNow = (int) ($c["user"]["id"] ?? 0);
            if ($act === "start_prepare") {
                $guard = appointment_journey_hard_guard_message(
                    $a,
                    "start_prepare",
                    $role,
                    $uidNow,
                );
                if ($guard !== "") {
                    flash($guard, "bad");
                    $postRedirect();
                }
                $stmt = q(
                    "UPDATE pi_appointments SET status='em_preparo', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='chegou' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                    [$id, $cid],
                );
                if ($stmt->rowCount() <= 0) {
                    flash(
                        "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                q(
                    "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='em_andamento', t.started_by=COALESCE(t.started_by,?), t.started_at=COALESCE(t.started_at,NOW()), t.assigned_to=COALESCE(t.assigned_to,?), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='paciente_chegou' AND t.status IN ('aberta','aguardando')",
                    [$uidNow, $uidNow, $cid, $id],
                );
                audit("preparo_iniciado_atalho", "consulta", $id, [
                    "patient_link_id" => $patientId,
                    "audit_body" =>
                        "Assistente iniciou o preparo/triagem pelo atalho visual da jornada.",
                ]);
                flash(
                    "Preparo iniciado. Próxima medida: concluir preparo e liberar ao profissional.",
                );
                $postRedirect();
            }
            if ($act === "finish_prepare") {
                $guard = appointment_journey_hard_guard_message(
                    $a,
                    "finish_prepare",
                    $role,
                    $uidNow,
                );
                if ($guard !== "") {
                    flash($guard, "bad");
                    $postRedirect();
                }
                $stmt = q(
                    "UPDATE pi_appointments SET status='pronto_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='em_preparo' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                    [$id, $cid],
                );
                if ($stmt->rowCount() <= 0) {
                    flash(
                        "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                q(
                    "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='paciente_chegou' AND t.status IN ('aberta','em_andamento','aguardando')",
                    [$uidNow, $cid, $id],
                );
                if (function_exists("create_workflow_task")) {
                    $name = $patientId
                        ? patient_display_name($patientId, $cid)
                        : "paciente";
                    $doctor =
                        (int) (val(
                            "SELECT doctor_user_id FROM pi_appointments WHERE id=? AND clinic_id=?",
                            [$id, $cid],
                        ) ?:
                        0);
                    create_workflow_task(
                        $cid,
                        "Atender " . $name,
                        "Preparativos concluídos. O paciente está pronto para iniciar o atendimento.",
                        $doctor ?: null,
                        $patientId ?: null,
                        $id,
                        "preparo_concluido",
                        "consulta",
                        (string) $id,
                        null,
                        $doctor ? "user" : "role",
                        $doctor ? null : "medico",
                    );
                }
                audit("preparo_concluido_atalho", "consulta", $id, [
                    "patient_link_id" => $patientId,
                    "audit_body" =>
                        "Assistente liberou o paciente para atendimento pelo atalho visual da jornada.",
                ]);
                flash(
                    "Paciente liberado. Próxima medida: Profissional inicia atendimento.",
                );
                $postRedirect();
            }
            if ($act === "start_consultation") {
                $guard = appointment_journey_hard_guard_message(
                    $a,
                    "start_consultation",
                    $role,
                    $uidNow,
                );
                if ($guard !== "") {
                    flash($guard, "bad");
                    $postRedirect();
                }
                $stmt = q(
                    "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), status='em_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='pronto_atendimento' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL AND (doctor_user_id IS NULL OR doctor_user_id=?)",
                    [$id, $cid, $uidNow],
                );
                if ($stmt->rowCount() <= 0) {
                    flash(
                        "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                q(
                    "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='em_andamento', t.started_by=COALESCE(t.started_by,?), t.started_at=COALESCE(t.started_at,NOW()), t.assigned_to=COALESCE(t.assigned_to,?), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','aguardando')",
                    [$uidNow, $uidNow, $cid, $id],
                );
                audit("consulta_iniciada_atalho", "consulta", $id, [
                    "patient_link_id" => $patientId,
                    "audit_body" =>
                        "Profissional iniciou o atendimento pelo atalho visual da jornada.",
                ]);
                flash(
                    "Atendimento iniciado. A Ficha do Paciente ficará aberta durante o atendimento.",
                );
                if ($patientId > 0) {
                    redirect("patient", [
                        "id" => $patientId,
                        "appointment_id" => $id,
                        "from_appointments" => 1,
                        "return_day" => $postDay,
                        "return_doctor" => $postDoctor,
                    ]);
                }
                $postRedirect();
            }
            if ($act === "finish_consultation") {
                $guard = appointment_journey_hard_guard_message(
                    $a,
                    "finish_consultation",
                    $role,
                    $uidNow,
                );
                if ($guard !== "") {
                    flash($guard, "bad");
                    $postRedirect();
                }
                if (function_exists("workflow_on_appointment_finished")) {
                    workflow_on_appointment_finished(
                        $cid,
                        $id,
                        $patientId,
                        $uidNow,
                        "atalho_jornada",
                    );
                } else {
                    $stmt = q(
                        "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), consultation_finished_at=COALESCE(consultation_finished_at,NOW()), status='atendimento_concluido', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='em_atendimento' AND consultation_finished_at IS NULL",
                        [$id, $cid],
                    );
                    if ($stmt->rowCount() <= 0) {
                        flash(
                            "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                            "bad",
                        );
                        $postRedirect();
                    }
                }
                q(
                    "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','em_andamento','aguardando')",
                    [$uidNow, $cid, $id],
                );
                try {
                    if (function_exists("financial_sync_appointment")) {
                        financial_sync_appointment($cid, $id, $uidNow);
                    }
                } catch (Throwable $e) {
                    error_log(
                        "[Prontoo agenda financial sync after finish] " .
                            $e->getMessage(),
                    );
                }
                audit("consulta_concluida_atalho", "consulta", $id, [
                    "patient_link_id" => $patientId,
                    "audit_body" =>
                        "Profissional concluiu o atendimento pelo atalho visual da jornada.",
                ]);
                flash(
                    (int) ($a["payment_amount_cents"] ?? 0) > 0 &&
                    !in_array(
                        mb_strtolower(
                            trim((string) ($a["payment_status"] ?? "")),
                        ),
                        [
                            "efetivada",
                            "recebido",
                            "pago",
                            "isento",
                            "cortesia",
                            "sem_cobranca",
                        ],
                        true,
                    )
                        ? "Atendimento concluído. Próxima medida: Recepção recebe o pagamento e finaliza a saída."
                        : "Atendimento concluído. Próxima medida: Recepção finaliza saída.",
                );
                $postRedirect();
            }
            if ($act === "finish_checkout") {
                $guard = appointment_journey_hard_guard_message(
                    $a,
                    "finish_checkout",
                    $role,
                    $uidNow,
                );
                if ($guard !== "") {
                    flash($guard, "bad");
                    $postRedirect();
                }
                $stmt = q(
                    "UPDATE pi_appointments SET status='finalizado', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='atendimento_concluido' AND consultation_finished_at IS NOT NULL",
                    [$id, $cid],
                );
                if ($stmt->rowCount() <= 0) {
                    flash(
                        "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                q(
                    "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='atendimento_finalizado' AND t.status IN ('aberta','em_andamento','aguardando')",
                    [$uidNow, $cid, $id],
                );
                audit("saida_finalizada_atalho", "consulta", $id, [
                    "patient_link_id" => $patientId,
                    "audit_body" =>
                        "Recepção finalizou a saída do paciente pelo atalho visual da jornada.",
                ]);
                flash("Jornada finalizada.");
                $postRedirect();
            }
        }
        if ($act === "block") {
            $startAt = (string) ($_POST["start_at"] ?? "");
            $endAt = (string) ($_POST["end_at"] ?? "");
            $reason = trim((string) ($_POST["reason"] ?? ""));
            $rawDoctor = (string) ($_POST["doctor_user_id"] ?? "");
            $blockDoctor =
                $rawDoctor !== "" && (int) $rawDoctor > 0
                    ? (int) $rawDoctor
                    : null;
            if ($blockDoctor !== null && !isset($docs[$blockDoctor])) {
                flash(
                    "Selecione um profissional válido para o bloqueio ou use bloqueio do consultório inteiro.",
                    "bad",
                );
                $postRedirect();
            }
            if ($blockDoctor === null && !has_effective_role($c, "gerente")) {
                flash(
                    "Bloqueio de todo o consultório exige ambiente Administrativo. Bloqueie apenas a agenda de um profissional ou acione o Administrativo.",
                    "bad",
                );
                $postRedirect();
            }
            if (
                $startAt === "" ||
                $endAt === "" ||
                strtotime($endAt) <= strtotime($startAt) ||
                $reason === ""
            ) {
                flash(
                    "Informe início, fim posterior ao início e motivo do bloqueio.",
                    "bad",
                );
                $postRedirect();
            }
            $startAt = normalize_db_datetime($startAt);
            $endAt = normalize_db_datetime($endAt);
            $blockId = 0;
            try {
                db_begin_transaction();
                q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                $conflict = agenda_conflict_message(
                    $cid,
                    $blockDoctor,
                    $startAt,
                    $endAt,
                    "bloqueio",
                );
                if ($conflict) {
                    db_rollback();
                    flash($conflict, "bad");
                    $postRedirect();
                }
                q(
                    "INSERT INTO pi_blocks (clinic_id,doctor_user_id,start_at,end_at,reason,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())",
                    [
                        $cid,
                        $blockDoctor,
                        $startAt,
                        $endAt,
                        $reason,
                        (int) $c["user"]["id"],
                    ],
                );
                $blockId = db_last_insert_id();
                db_commit();
            } catch (Throwable $e) {
                if (pdo()->inTransaction()) {
                    db_rollback();
                }
                error_log("[Prontoo agenda block] " . $e->getMessage());
                flash(
                    "Não foi possível registrar o bloqueio. Revise os horários e tente novamente.",
                    "bad",
                );
                $postRedirect();
            }
            clinic_metric_inc($cid, "agenda_blocks");
            audit("agenda_bloqueada", "bloqueio", $blockId, $_POST);
            flash("Horário bloqueado.");
            $postRedirect();
        }
        if ($act === "update_block") {
            $id = (int) ($_POST["id"] ?? 0);
            $b = one("SELECT id FROM pi_blocks WHERE id=? AND clinic_id=?", [
                $id,
                $cid,
            ]);
            if (!$b) {
                flash("Bloqueio não encontrado.", "bad");
                $postRedirect();
            }
            $startAt = (string) ($_POST["start_at"] ?? "");
            $endAt = (string) ($_POST["end_at"] ?? "");
            $reason = trim((string) ($_POST["reason"] ?? ""));
            $rawDoctor = (string) ($_POST["doctor_user_id"] ?? "");
            $blockDoctor =
                $rawDoctor !== "" && (int) $rawDoctor > 0
                    ? (int) $rawDoctor
                    : null;
            if ($blockDoctor !== null && !isset($docs[$blockDoctor])) {
                flash(
                    "Selecione um profissional válido para o bloqueio ou use bloqueio do consultório inteiro.",
                    "bad",
                );
                $postRedirect();
            }
            if ($blockDoctor === null && !has_effective_role($c, "gerente")) {
                flash(
                    "Bloqueio de todo o consultório exige ambiente Administrativo. Bloqueie apenas a agenda de um profissional ou acione o Administrativo.",
                    "bad",
                );
                $postRedirect();
            }
            if (
                $startAt === "" ||
                $endAt === "" ||
                strtotime($endAt) <= strtotime($startAt) ||
                $reason === ""
            ) {
                flash(
                    "Informe início, fim posterior ao início e motivo do bloqueio.",
                    "bad",
                );
                $postRedirect();
            }
            $startAt = normalize_db_datetime($startAt);
            $endAt = normalize_db_datetime($endAt);
            $hoursMessage =
                $blockDoctor !== null
                    ? doctor_work_hours_conflict_message(
                        $cid,
                        $blockDoctor,
                        $startAt,
                        $endAt,
                    )
                    : "";
            if ($hoursMessage !== "") {
                flash($hoursMessage, "bad");
                $postRedirect();
            }
            try {
                db_begin_transaction();
                q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                $conflict = agenda_conflict_message(
                    $cid,
                    $blockDoctor,
                    $startAt,
                    $endAt,
                    "alteração do bloqueio",
                    0,
                    $id,
                );
                if ($conflict) {
                    db_rollback();
                    flash($conflict, "bad");
                    $postRedirect();
                }
                q(
                    "UPDATE pi_blocks SET doctor_user_id=?, start_at=?, end_at=?, reason=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [$blockDoctor, $startAt, $endAt, $reason, $id, $cid],
                );
                db_commit();
            } catch (Throwable $e) {
                if (pdo()->inTransaction()) {
                    db_rollback();
                }
                error_log("[Prontoo agenda block update] " . $e->getMessage());
                flash(
                    "Não foi possível alterar o bloqueio. Revise os dados e tente novamente.",
                    "bad",
                );
                $postRedirect();
            }
            audit("bloqueio_alterado", "bloqueio", $id, $_POST);
            flash("Bloqueio alterado.");
            $postRedirect();
        }
        if ($act === "delete_block" || $act === "unblock") {
            $id = (int) ($_POST["id"] ?? 0);
            $cancelReason = trim((string) ($_POST["cancel_reason"] ?? ""));
            if ($cancelReason === "") {
                flash("Informe o motivo da exclusão do bloqueio.", "bad");
                $postRedirect();
            }
            $b = one(
                "SELECT id FROM pi_blocks WHERE id=? AND clinic_id=? AND deleted_at IS NULL",
                [$id, $cid],
            );
            if ($b) {
                q(
                    "UPDATE pi_blocks SET deleted_at=NOW(),deleted_by=?,cancel_reason=?,updated_at=NOW() WHERE id=? AND clinic_id=? AND deleted_at IS NULL",
                    [(int) $c["user"]["id"], $cancelReason, $id, $cid],
                );
                audit("bloqueio_removido", "bloqueio", $id, [
                    "motivo" => $cancelReason,
                ]);
                flash("Bloqueio excluído da agenda e preservado no histórico.");
            } else {
                flash("Bloqueio não encontrado.", "bad");
            }
            $postRedirect();
        }
        if ($act === "update_appointment") {
            $id = (int) ($_POST["id"] ?? 0);
            $a = one(
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?",
                [$id, $cid],
            );
            if (!$a) {
                flash("Agendamento não encontrado.", "bad");
                $postRedirect();
            }
            $guard = appointment_journey_hard_guard_message(
                $a,
                "update_appointment",
                $role,
                $uid,
            );
            if ($guard !== "") {
                flash($guard, "bad");
                $postRedirect();
            }
            $did = (int) ($_POST["doctor_user_id"] ?? 0);
            $startAt = (string) ($_POST["start_at"] ?? "");
            $endAt = (string) ($_POST["end_at"] ?? "");
            $patient = (int) ($_POST["patient_link_id"] ?? 0);
            if (!isset($docs[$did])) {
                flash("Selecione um profissional válido.", "bad");
                $postRedirect();
            }
            if (
                $patient <= 0 ||
                !isset($pats[$patient]) ||
                $startAt === "" ||
                $endAt === "" ||
                strtotime($endAt) <= strtotime($startAt)
            ) {
                flash(
                    "Informe paciente da clínica atual, início e fim posterior ao início.",
                    "bad",
                );
                $postRedirect();
            }
            if (
                $blockReason = patient_appointment_registration_block_reason(
                    $cid,
                    $patient,
                )
            ) {
                flash($blockReason, "bad");
                $postRedirect();
            }
            $startAt = normalize_db_datetime($startAt);
            $endAt = normalize_db_datetime($endAt);
            $hoursMessage = doctor_work_hours_conflict_message(
                $cid,
                $did,
                $startAt,
                $endAt,
            );
            if ($hoursMessage !== "") {
                flash($hoursMessage, "bad");
                $postRedirect();
            }
            $procId = appointment_procedure_id_from_post($cid);
            $durationMessage = appointment_min_duration_message(
                $cid,
                $procId,
                $startAt,
                $endAt,
            );
            if ($durationMessage) {
                flash($durationMessage, "bad");
                $postRedirect();
            }
            try {
                db_begin_transaction();
                q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
                $locked = one(
                    "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? FOR UPDATE",
                    [$id, $cid],
                );
                $lockedGuard = $locked
                    ? appointment_journey_hard_guard_message(
                        $locked,
                        "update_appointment",
                        $role,
                        $uid,
                    )
                    : "Agendamento não encontrado.";
                if ($lockedGuard !== "") {
                    db_rollback();
                    flash($lockedGuard, "bad");
                    $postRedirect();
                }
                $conflict = agenda_conflict_message(
                    $cid,
                    $did,
                    $startAt,
                    $endAt,
                    "alteração do agendamento",
                    $id,
                    0,
                );
                if ($conflict) {
                    db_rollback();
                    flash($conflict, "bad");
                    $postRedirect();
                }
                [
                    $paid,
                    $method,
                    $amount,
                    $paymentDestination,
                    $paymentError,
                ] = appointment_payment_post_context(
                    $cid,
                    $procId,
                    (int) ($a["payment_amount_cents"] ?? 0),
                );
                if ($paymentError) {
                    flash($paymentError, "bad");
                    $postRedirect();
                }
                q(
                    "UPDATE pi_appointments SET patient_link_id=?, doctor_user_id=?, start_at=?, end_at=?, procedure_id=?, reason=?, notes=?, change_reason=?, payment_amount_cents=?, payment_method=?, payment_status=?, payment_confirmed_at=IF(?,COALESCE(payment_confirmed_at,NOW()),NULL), updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [
                        $patient,
                        $did,
                        $startAt,
                        $endAt,
                        $procId,
                        procedure_reason_from_post($cid),
                        trim((string) ($_POST["notes"] ?? "")),
                        trim((string) ($_POST["change_reason"] ?? "")),
                        $amount,
                        $method ?: null,
                        $paid ? "efetivada" : "prevista",
                        $paid ? 1 : 0,
                        $id,
                        $cid,
                    ],
                );
                financial_sync_appointment(
                    $cid,
                    $id,
                    (int) $c["user"]["id"],
                    $paymentDestination,
                );
                db_commit();
            } catch (Throwable $e) {
                if (pdo()->inTransaction()) {
                    db_rollback();
                }
                error_log("[Prontoo appointment update] " . $e->getMessage());
                flash(
                    $e instanceof RuntimeException &&
                    trim($e->getMessage()) !== ""
                        ? $e->getMessage()
                        : "Não foi possível alterar o agendamento. Revise os horários e tente novamente.",
                    "bad",
                );
                $postRedirect();
            }
            audit("consulta_alterada", "consulta", $id, $_POST);
            flash("Agendamento alterado.");
            $postRedirect();
        }
        if ($act === "delete_appointment") {
            $id = (int) ($_POST["id"] ?? 0);
            $cancelReason = trim((string) ($_POST["cancel_reason"] ?? ""));
            if ($cancelReason === "") {
                flash(
                    "Informe o motivo da exclusão/cancelamento do agendamento.",
                    "bad",
                );
                $postRedirect();
            }
            $a = one(
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?",
                [$id, $cid],
            );
            $guard = $a
                ? appointment_journey_hard_guard_message(
                    $a,
                    "delete_appointment",
                    $role,
                    $uid,
                )
                : "Agendamento não encontrado.";
            if ($a && $guard === "") {
                $stmt = q(
                    "UPDATE pi_appointments SET status='cancelado', cancel_reason=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL",
                    [$cancelReason, $id, $cid],
                );
                if ($stmt->rowCount() <= 0) {
                    flash(
                        "A jornada foi atualizada por outra ação. Reabra o Painel e tente novamente.",
                        "bad",
                    );
                    $postRedirect();
                }
                if (function_exists("financial_cancel_appointment_revenue")) {
                    financial_cancel_appointment_revenue(
                        $cid,
                        $id,
                        $uid,
                        $cancelReason,
                    );
                }
                audit("consulta_excluida", "consulta", $id, [
                    "motivo" => $cancelReason,
                ]);
                flash(
                    "Agendamento excluído da agenda e preservado como cancelado no histórico.",
                );
            } else {
                flash($guard, "bad");
            }
            $postRedirect();
        }
        $did = (int) ($_POST["doctor_user_id"] ?? 0);
        $startAt = (string) ($_POST["start_at"] ?? "");
        $endAt = (string) ($_POST["end_at"] ?? "");
        $patient = (int) ($_POST["patient_link_id"] ?? 0);
        if (!isset($docs[$did])) {
            flash("Selecione um profissional válido.", "bad");
            $postRedirect();
        }
        if (
            $patient <= 0 ||
            !isset($pats[$patient]) ||
            $startAt === "" ||
            $endAt === "" ||
            strtotime($endAt) <= strtotime($startAt)
        ) {
            flash(
                "Informe paciente da clínica atual, início e fim posterior ao início.",
                "bad",
            );
            $postRedirect();
        }
        if (
            $blockReason = patient_appointment_registration_block_reason(
                $cid,
                $patient,
            )
        ) {
            flash($blockReason, "bad");
            $postRedirect();
        }
        $startAt = normalize_db_datetime($startAt);
        $endAt = normalize_db_datetime($endAt);
        $hoursMessage = doctor_work_hours_conflict_message(
            $cid,
            $did,
            $startAt,
            $endAt,
        );
        if ($hoursMessage !== "") {
            flash($hoursMessage, "bad");
            $postRedirect();
        }
        $procId = appointment_procedure_id_from_post($cid);
        if ($act === "create" && !$procId) {
            flash(
                procedure_options($cid, true) === []
                    ? "Cadastre Procedimentos primeiro."
                    : "Selecione um procedimento cadastrado.",
                "bad",
            );
            $postRedirect();
        }
        $appointmentId = 0;
        try {
            db_begin_transaction();
            q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
            $lockedProcedure = one(
                "SELECT id,title,duration_minutes FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1 FOR UPDATE",
                [$procId, $cid],
            );
            if (!$lockedProcedure) {
                db_rollback();
                flash(
                    "O procedimento selecionado não está mais disponível. Escolha outro procedimento cadastrado.",
                    "bad",
                );
                $postRedirect();
            }
            $durationMessage = appointment_min_duration_message(
                $cid,
                $procId,
                $startAt,
                $endAt,
            );
            if ($durationMessage) {
                db_rollback();
                flash($durationMessage, "bad");
                $postRedirect();
            }
            $conflict = agenda_conflict_message(
                $cid,
                $did,
                $startAt,
                $endAt,
                "agendamento",
            );
            if ($conflict) {
                db_rollback();
                flash($conflict, "bad");
                $postRedirect();
            }
            [
                $paid,
                $method,
                $amount,
                $paymentDestination,
                $paymentError,
            ] = appointment_payment_post_context($cid, $procId, 0);
            if ($paymentError) {
                flash($paymentError, "bad");
                $postRedirect();
            }
            q(
                "INSERT INTO pi_appointments (clinic_id,patient_link_id,doctor_user_id,start_at,end_at,status,procedure_id,reason,notes,payment_amount_cents,payment_method,payment_status,payment_confirmed_at,created_by,created_at) VALUES (?,?,?,?,?,'agendado',?,?,?,?,?,?,IF(?,NOW(),NULL),?,NOW())",
                [
                    $cid,
                    $patient,
                    $did,
                    $startAt,
                    $endAt,
                    $procId,
                    (string) $lockedProcedure["title"],
                    trim((string) ($_POST["notes"] ?? "")),
                    $amount,
                    $method ?: null,
                    $paid ? "efetivada" : "prevista",
                    $paid ? 1 : 0,
                    (int) $c["user"]["id"],
                ],
            );
            $appointmentId = db_last_insert_id();
            financial_sync_appointment(
                $cid,
                $appointmentId,
                (int) $c["user"]["id"],
                $paymentDestination,
            );
            db_commit();
        } catch (Throwable $e) {
            if (pdo()->inTransaction()) {
                db_rollback();
            }
            error_log("[Prontoo appointment create] " . $e->getMessage());
            flash(
                $e instanceof RuntimeException && trim($e->getMessage()) !== ""
                    ? $e->getMessage()
                    : "Não foi possível agendar a consulta. Revise os horários e tente novamente.",
                "bad",
            );
            $postRedirect();
        }
        counter_inc("appointments_total");
        clinic_metric_inc($cid, "appointments");
        audit("consulta_agendada", "consulta", $appointmentId, $_POST);
        flash("Consulta agendada.");
        $postRedirect();
    }
    $agendaDoctor = $viewDoctor;
    if ($role === "medico" && isset($docs[$uid])) {
        $agendaDoctor = $uid;
    }
    if (count($docs) === 1) {
        $agendaDoctor = (int) array_key_first($docs);
    } elseif ($agendaDoctor <= 0 && count($docs) > 1) {
        $agendaDoctor = (int) array_key_first($docs);
    }
    $agendaView = (string) ($_GET["view"] ?? "diario");
    if (
        !in_array($agendaView, ["resumo", "diario", "semanal", "mensal"], true)
    ) {
        $agendaView = "diario";
    }
    $buildParams = function (
        ?string $d = null,
        ?int $doctor = null,
        ?string $view = null,
    ) use ($day, $agendaDoctor, $role, $agendaView): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:3022
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $params = ["d" => $d ?: $day];
        $doctor = $doctor ?? $agendaDoctor;
        if ($role !== "medico" && $doctor > 0) {
            $params["doctor"] = $doctor;
        }
        $view = $view ?? $agendaView;
        if ($view !== "" && $view !== "diario") {
            $params["view"] = $view;
        }
        return $params;
    };
    $returnHidden =
        '<input type="hidden" name="return_day" value="' .
        e($day) .
        '"><input type="hidden" name="return_doctor" value="' .
        (int) $agendaDoctor .
        '">';
    $formDoctorOptions =
        $role === "medico" && isset($docs[$uid])
            ? [$uid => $docs[$uid]]
            : $docs;
    $selected =
        $agendaDoctor > 0
            ? $agendaDoctor
            : (count($formDoctorOptions) === 1
                ? (int) array_key_first($formDoctorOptions)
                : null);
    $operationMode = (string) ($_GET["mode"] ?? "");
    if ($operationMode === "create") {
        if (!in_array($role, ["recepcionista", "gerente"], true)) {
            flash(
                "Seu cargo não possui operação de Agendamento Rápido.",
                "bad",
            );
            redirect("appointments", $buildParams());
        }
        $initialStart = agenda_normalize_local_input(
            (string) ($_GET["start"] ?? ""),
            $day,
            "08:00",
        );
        $initialDay = agenda_safe_day_from_local_input($initialStart, $day);
        $initialEnd = agenda_local_input_add_minutes($initialStart, 30);
        $initialDoctor =
            (int) ($_GET["doctor_user_id"] ??
                ($_GET["doctor"] ?? ($selected ?: 0)));
        if ($initialDoctor <= 0 || !isset($formDoctorOptions[$initialDoctor])) {
            $initialDoctor = $selected ?: 0;
        }
        $cancelParams = $buildParams($initialDay, $initialDoctor);
        $createTitle = "Agendamento Rápido";
        $createSubtitle =
            "Confirme os horários sugeridos abaixo e selecione Profissional, Paciente e Procedimento.";
        $initialHour = substr($initialStart, 11, 5);
        $initialEndHour = substr($initialEnd, 11, 5);
        $doctorHint =
            $initialDoctor > 0
                ? (string) ($formDoctorOptions[$initialDoctor] ??
                    role_label_for("medico", $cid))
                : role_label_for("medico", $cid);
        $returnHiddenOperation =
            '<input type="hidden" name="return_day" value="' .
            e($initialDay) .
            '"><input type="hidden" name="return_doctor" value="' .
            (int) $initialDoctor .
            '">';
        $quickHeader =
            '<div class="agenda-quick-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
            icon("event_available") .
            '</span><div class="agenda-quick-hero-copy"><h2>' .
            e($createTitle) .
            "</h2><p>" .
            e($createSubtitle) .
            '</p></div><a class="ghost small agenda-quick-back" href="' .
            href("appointments", $cancelParams) .
            '">' .
            icon("arrow_back") .
            "<span>Agenda</span></a></div>";
        $quickSummary =
            '<div class="agenda-quick-summary" aria-label="Resumo do horário inferido"><span>' .
            icon("calendar_month") .
            "<b>" .
            e(date_br($initialDay)) .
            "</b><small>Data</small></span><span>" .
            icon("schedule") .
            "<b>" .
            e($initialHour . "–" . $initialEndHour) .
            "</b><small>Horário</small></span><span>" .
            icon("stethoscope") .
            "<b>" .
            e($doctorHint) .
            "</b><small>Profissional</small></span></div>";
        $form =
            '<section class="form-panel agenda-route-form agenda-quick-form-panel">' .
            $quickHeader .
            $quickSummary .
            '<form method="post" class="compact agenda-create-form agenda-quick-form" data-appointment-create-form>' .
            csrf_field() .
            $returnHiddenOperation .
            '<input type="hidden" name="act" value="create"><fieldset class="agenda-quick-section agenda-quick-time"><legend>' .
            icon("schedule") .
            '<span>Horário</span></legend><div class="agenda-quick-time-grid">' .
            form_row(
                "Início",
                input(
                    "start_at",
                    "datetime-local",
                    $initialStart,
                    "required data-appointment-start",
                ),
            ) .
            form_row(
                "Fim",
                input(
                    "end_at",
                    "datetime-local",
                    $initialEnd,
                    "required data-appointment-end",
                ),
            ) .
            '</div></fieldset><fieldset class="agenda-quick-section agenda-quick-main"><legend>' .
            icon("edit_calendar") .
            '<span>Dados do agendamento</span></legend><div class="agenda-quick-grid">' .
            select_label(
                role_label_for("medico", $cid),
                "doctor_user_id",
                $formDoctorOptions,
                $initialDoctor ?: null,
                "required",
            ) .
            select_label(
                "Paciente",
                "patient_link_id",
                $pats,
                $prePatientId ?: null,
                "required",
            ) .
            procedure_select_html($cid, "reason", "", true) .
            "</div></fieldset>" .
            appointment_payment_form_html($cid) .
            '<fieldset class="agenda-quick-section agenda-quick-notes"><legend>' .
            icon("notes") .
            "<span>Observações</span></legend>" .
            form_row(
                "Observações importantes",
                input("notes", "text", "", 'placeholder="Opcional"'),
            ) .
            '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
            href("appointments", $cancelParams) .
            '">' .
            icon("close") .
            '<span>Desistir</span></a><button type="submit" class="primary">' .
            icon("event_available") .
            "<span>Confirmar</span></button></div></form></section>";
        page(
            $createTitle,
            page_head(
                $createTitle,
                $createSubtitle,
                '<a class="ghost small cmdlike" href="' .
                    href("appointments", $cancelParams) .
                    '">' .
                    icon("calendar_view_day") .
                    "<span>Agenda</span></a>",
            ) . card($form, "agenda-route-card agenda-quick-route-card"),
        );
        return;
    }
    if ($operationMode === "note") {
        $noteDay = (string) ($_GET["note_date"] ?? $day);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDay)) {
            $noteDay = $day;
        }
        $cancelParams = $buildParams($noteDay, $agendaDoctor);
        $roleOptions = clinic_role_options($cid, false);
        $noteTitle = "Nova Anotação";
        $noteSubtitle =
            "Registre uma anotação para a data escolhida na Agenda, com visibilidade controlada.";
        $form = agenda_note_form_html($cid, $noteDay, $c, $roleOptions);
        page(
            $noteTitle,
            page_head(
                $noteTitle,
                $noteSubtitle,
                '<a class="ghost small cmdlike" href="' .
                    href("appointments", $cancelParams) .
                    '">' .
                    icon("calendar_view_day") .
                    "<span>Agenda</span></a>",
            ) . card($form, "agenda-route-card agenda-note-route-card"),
        );
        return;
    }
    if ($operationMode === "block") {
        if (!in_array($role, ["recepcionista", "medico", "gerente"], true)) {
            flash(
                "Seu cargo não possui operação de bloqueio de horário.",
                "bad",
            );
            redirect("appointments", $buildParams());
        }
        $initialStart = agenda_normalize_local_input(
            (string) ($_GET["start"] ?? ""),
            $day,
            "08:00",
        );
        $initialDay = agenda_safe_day_from_local_input($initialStart, $day);
        $initialEnd = agenda_local_input_add_minutes($initialStart, 30);
        $initialDoctor =
            (int) ($_GET["doctor_user_id"] ??
                ($_GET["doctor"] ?? ($selected ?: 0)));
        if ($initialDoctor <= 0 || !isset($formDoctorOptions[$initialDoctor])) {
            $initialDoctor = $selected ?: 0;
        }
        $blockDefault = $initialDoctor > 0 ? $initialDoctor : null;
        $blockOptions =
            $role === "gerente"
                ? ["" => "Todo consultório"] + $formDoctorOptions
                : $formDoctorOptions;
        $cancelParams = $buildParams($initialDay, $initialDoctor);
        $returnHiddenOperation =
            '<input type="hidden" name="return_day" value="' .
            e($initialDay) .
            '"><input type="hidden" name="return_doctor" value="' .
            (int) $initialDoctor .
            '">';
        $blockTitle = "Bloqueio de Horário";
        $blockSubtitle =
            "Reserve um intervalo da Agenda quando ele não puder receber pacientes.";
        $initialHour = substr($initialStart, 11, 5);
        $initialEndHour = substr($initialEnd, 11, 5);
        $blockDoctorHint =
            $initialDoctor > 0
                ? (string) ($formDoctorOptions[$initialDoctor] ??
                    role_label_for("medico", $cid))
                : "Todo consultório";
        $blockHeader =
            '<div class="agenda-quick-hero agenda-block-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
            icon("event_busy") .
            '</span><div class="agenda-quick-hero-copy"><h2>' .
            e($blockTitle) .
            "</h2><p>" .
            e($blockSubtitle) .
            '</p></div><a class="ghost small agenda-quick-back" href="' .
            href("appointments", $cancelParams) .
            '">' .
            icon("arrow_back") .
            "<span>Agenda</span></a></div>";
        $blockSummary =
            '<div class="agenda-quick-summary agenda-block-summary" aria-label="Resumo do bloqueio"><span>' .
            icon("calendar_month") .
            "<b>" .
            e(date_br($initialDay)) .
            "</b><small>Data</small></span><span>" .
            icon("schedule") .
            "<b>" .
            e($initialHour . "–" . $initialEndHour) .
            "</b><small>Horário</small></span><span>" .
            icon("event_busy") .
            "<b>" .
            e($blockDoctorHint) .
            "</b><small>Escopo</small></span></div>";
        $form =
            '<section class="form-panel agenda-route-form agenda-block-form-panel">' .
            $blockHeader .
            $blockSummary .
            '<form method="post" class="compact agenda-block-form agenda-quick-form agenda-structured-form">' .
            csrf_field() .
            $returnHiddenOperation .
            '<input type="hidden" name="act" value="block"><fieldset class="agenda-quick-section agenda-block-scope"><legend>' .
            icon("event_busy") .
            "<span>Escopo</span></legend>" .
            select_label(
                "Agenda bloqueada",
                "doctor_user_id",
                $blockOptions,
                $blockDefault,
            ) .
            '</fieldset><fieldset class="agenda-quick-section agenda-quick-time"><legend>' .
            icon("schedule") .
            '<span>Horário</span></legend><div class="agenda-quick-time-grid">' .
            form_row(
                "Início",
                input("start_at", "datetime-local", $initialStart, "required"),
            ) .
            form_row(
                "Fim",
                input("end_at", "datetime-local", $initialEnd, "required"),
            ) .
            '</div></fieldset><fieldset class="agenda-quick-section agenda-quick-notes"><legend>' .
            icon("notes") .
            "<span>Motivo</span></legend>" .
            form_row(
                "Motivo do bloqueio",
                input(
                    "reason",
                    "text",
                    "",
                    'required placeholder="Ex.: reunião, intervalo, procedimento externo"',
                ),
            ) .
            '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
            href("appointments", $cancelParams) .
            '">' .
            icon("close") .
            '<span>Desistir</span></a><button type="submit" class="primary">' .
            icon("event_busy") .
            "<span>Salvar bloqueio</span></button></div></form></section>";
        page(
            $blockTitle,
            page_head(
                $blockTitle,
                $blockSubtitle,
                '<a class="ghost small cmdlike" href="' .
                    href("appointments", $cancelParams) .
                    '">' .
                    icon("calendar_view_day") .
                    "<span>Agenda</span></a>",
            ) . card($form, "agenda-route-card agenda-block-route-card"),
        );
        return;
    }
    $headActions = "";
    if (in_array($role, ["recepcionista", "gerente"], true)) {
        $headActions .=
            '<a class="primary small cmdlike" href="' .
            href("appointments", $buildParams() + ["mode" => "create"]) .
            '">' .
            icon("event_available") .
            "<span>Agendar consulta</span></a>";
    }
    if (in_array($role, ["recepcionista", "medico", "gerente"], true)) {
        $headActions .=
            '<a class="ghost small cmdlike" href="' .
            href("appointments", $buildParams() + ["mode" => "block"]) .
            '">' .
            icon("event_busy") .
            "<span>Bloquear horário</span></a>";
    }
    $headActions .=
        '<a class="ghost small cmdlike" href="' .
        href(
            "appointments",
            $buildParams() + ["mode" => "note", "note_date" => $day],
        ) .
        '">' .
        icon("sticky_note_2") .
        "<span>Anotação</span></a>";
    if ($role === "assistente") {
        $headActions .=
            '<a class="ghost small" href="' .
            href("tasks") .
            '">' .
            icon("task_alt") .
            "<span>Tarefas da triagem</span></a>";
    }
    $prev = date("Y-m-d", strtotime($day . " -1 day"));
    $next = date("Y-m-d", strtotime($day . " +1 day"));
    [$dayStart, $dayEnd] = app_local_day_utc_range($day, $cid, $c);
    $rowSql =
        "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,notes,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_status,payment_method,payment_amount_cents,payment_confirmed_at,revenue_id FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?";
    $rowParams = [$cid, $dayStart, $dayEnd];
    if ($agendaDoctor > 0) {
        $rowSql .= " AND doctor_user_id=?";
        $rowParams[] = $agendaDoctor;
    }
    $rowSql .= " ORDER BY start_at ASC LIMIT 180";
    $rows = q($rowSql, $rowParams)->fetchAll();
    $blockSql =
        "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at<? AND end_at>?";
    $blockParams = [$cid, $dayEnd, $dayStart];
    if ($agendaDoctor > 0) {
        $blockSql .= " AND (doctor_user_id IS NULL OR doctor_user_id=?)";
        $blockParams[] = $agendaDoctor;
    }
    $blockSql .= " ORDER BY start_at ASC LIMIT 100";
    $blocks = q($blockSql, $blockParams)->fetchAll();
    $patientLinks = scoped_patient_map(
        $cid,
        int_ids($rows, "patient_link_id"),
        "id,person_id",
    );
    $persons = fetch_map(
        "pi_persons",
        int_ids(array_values($patientLinks), "person_id"),
        "id,full_name",
    );
    $doctorIds = array_values(
        array_unique(
            array_merge(
                int_ids($rows, "doctor_user_id"),
                int_ids($blocks, "doctor_user_id"),
            ),
        ),
    );
    $doctorMap = scoped_user_map($cid, $doctorIds, "id,name");
    $creators = scoped_user_map(
        $cid,
        int_ids($blocks, "created_by"),
        "id,name",
    );
    $nowTs = time();
    $isDone = function (array $a): bool {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:3429
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `in_array`, `appointment_status_code`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return in_array(
            appointment_status_code($a),
            ["atendimento_concluido", "finalizado"],
            true,
        );
    };
    $isStarted = function (array $a) use ($isDone): bool {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:3436
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `appointment_status_code`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return !$isDone($a) && appointment_status_code($a) === "em_atendimento";
    };
    $isArrived = function (array $a) use ($isDone, $isStarted): bool {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:3439
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `in_array`, `appointment_status_code`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return !$isDone($a) &&
            !$isStarted($a) &&
            in_array(
                appointment_status_code($a),
                ["chegou", "em_preparo", "pronto_atendimento"],
                true,
            );
    };
    $isLate = function (array $a) use ($nowTs): bool {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:3448
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `app_storage_timestamp`, `in_array`, `appointment_status_code`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $start = app_storage_timestamp($a["start_at"] ?? "") ?: 0;
        return in_array(
            appointment_status_code($a),
            ["agendado", "confirmado"],
            true,
        ) &&
            $start > 0 &&
            $start < $nowTs;
    };
    $statusMeta = function (array $a) use ($role): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:3458
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `appointment_journey_meta`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $m = appointment_journey_meta($a, $role);
        return [$m["label"], $m["class"], $m["icon"]];
    };
    $patientName = function (array $a) use ($patientLinks, $persons): string {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:3462
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $pid = (int) ($a["patient_link_id"] ?? 0);
        $pl = $patientLinks[$pid] ?? [];
        $ps = $persons[(int) ($pl["person_id"] ?? 0)] ?? [];
        return (string) ($ps["full_name"] ?? "Paciente não identificado");
    };
    $appointmentActions = function (array $r, string $mode = "dropdown") use (
        $c,
        $cid,
        $role,
        $uid,
        $returnHidden,
        $pats,
        $docs,
        $doctorMap,
        $statusMeta,
        $patientName,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:3468
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `appointment_journey_role_actions`, `csrf_field`, `e`, `icon`, `appointment_journey_view_model`, `href`, `appointment_journey_hard_guard_message`, `select_label`, `role_label_for`, `procedure_select_html`, `form_row`, `input` e mais 3.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $pid = (int) ($r["patient_link_id"] ?? 0);
        $id = (int) ($r["id"] ?? 0);
        $journeyActions = appointment_journey_role_actions(
            $r,
            $role,
            null,
            $uid,
        );
        $quickItems = "";
        if ($id > 0 && $journeyActions) {
            foreach ($journeyActions as $act) {
                $class = (string) ($act["class"] ?? "primary");
                $confirm = !empty($act["danger"])
                    ? ' onsubmit="return confirm(\'Registrar esta exceção na jornada do paciente?\')"'
                    : "";
                $realAct = (string) $act["act"];
                $extra = "";
                if ($realAct === "no_show_quick") {
                    $realAct = "no_show";
                    $extra =
                        '<input type="hidden" name="no_show_reason" value="Paciente não compareceu no horário agendado.">';
                }
                $quickItems .=
                    '<form method="post" class="agenda-row-action-form"' .
                    $confirm .
                    ">" .
                    csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="' .
                    e($realAct) .
                    '"><input type="hidden" name="id" value="' .
                    $id .
                    '">' .
                    $extra .
                    '<button type="submit" class="' .
                    e($class) .
                    ' small">' .
                    icon((string) $act["icon"]) .
                    "<span>" .
                    e((string) $act["label"]) .
                    "</span></button></form>";
            }
        }
        if ($mode === "quick") {
            if ($quickItems === "") {
                return '<button type="button" class="ghost small cmdlike agenda-row-actions-disabled" data-agenda-row-actions disabled aria-disabled="true">' .
                    icon("bolt") .
                    "<span>Ações</span></button>";
            }
            $modalId = "agenda-actions-modal-" . $id;
            return '<span class="agenda-row-actions-wrap" data-agenda-row-actions><button type="button" class="ghost small cmdlike agenda-row-actions-open" data-agenda-actions-open="' .
                e($modalId) .
                '" aria-haspopup="dialog" aria-controls="' .
                e($modalId) .
                '">' .
                icon("bolt") .
                '<span>Ações</span></button><dialog class="agenda-actions-modal" id="' .
                e($modalId) .
                '" data-agenda-actions-modal aria-label="Ações do atendimento"><div class="agenda-actions-modal-card"><header class="agenda-actions-modal-head"><span>' .
                icon("bolt") .
                '<b>Ações</b></span><button type="button" class="icon-btn" data-agenda-actions-close aria-label="Fechar ações">' .
                icon("close") .
                '</button></header><div class="agenda-actions-modal-list">' .
                $quickItems .
                "</div></div></dialog></span>";
        }
        $journeyHtml = "";
        if ($id > 0 && $journeyActions) {
            $journeyHtml =
                '<div class="agenda-action-section agenda-action-section-journey"><span class="agenda-action-section-title">Jornada do paciente</span><div class="agenda-action-list">';
            foreach ($journeyActions as $act) {
                $class = (string) ($act["class"] ?? "primary");
                $title = (string) ($act["title"] ?? $act["label"]);
                $confirm = !empty($act["danger"])
                    ? ' onsubmit="return confirm(\'Registrar esta exceção na jornada do paciente?\')"'
                    : "";
                $realAct = (string) $act["act"];
                $extra = "";
                if ($realAct === "no_show_quick") {
                    $realAct = "no_show";
                    $extra =
                        '<input type="hidden" name="no_show_reason" value="Paciente não compareceu no horário agendado.">';
                }
                $journeyHtml .=
                    '<form method="post" class="agenda-action-item agenda-action-item-form"' .
                    $confirm .
                    ">" .
                    csrf_field() .
                    $returnHidden .
                    '<input type="hidden" name="act" value="' .
                    e($realAct) .
                    '"><input type="hidden" name="id" value="' .
                    $id .
                    '">' .
                    $extra .
                    '<button type="submit" class="' .
                    e($class) .
                    ' small">' .
                    icon((string) $act["icon"]) .
                    "<span>" .
                    e((string) $act["label"]) .
                    "</span></button><small>" .
                    e($title) .
                    "</small></form>";
            }
            $journeyHtml .= "</div></div>";
        } else {
            $vm = appointment_journey_view_model($r, $role);
            $journeyHtml =
                '<div class="agenda-action-section agenda-action-section-journey"><span class="agenda-action-section-title">Jornada do paciente</span><div class="agenda-action-empty">' .
                icon("info") .
                "<span>" .
                e(
                    (string) ($vm["action"] ??
                        "Nenhuma ação da jornada para este cargo agora."),
                ) .
                "</span></div></div>";
        }
        $secondary = "";
        $secondaryItems = "";
        if ($pid > 0) {
            $secondaryItems .=
                '<a class="agenda-action-item agenda-action-link" href="' .
                href("patient", ["id" => $pid]) .
                '">' .
                icon("folder_open") .
                "<span>Abrir ficha</span></a>";
        }
        $editGuard = appointment_journey_hard_guard_message(
            $r,
            "edit",
            $role,
            $uid,
        );
        $cancelGuard = appointment_journey_hard_guard_message(
            $r,
            "cancel",
            $role,
            $uid,
        );
        if ($editGuard === "") {
            $editForm =
                '<form method="post" class="agenda-edit-form agenda-edit-form-nested">' .
                csrf_field() .
                $returnHidden .
                '<input type="hidden" name="act" value="update_appointment"><input type="hidden" name="id" value="' .
                $id .
                '">' .
                select_label(
                    role_label_for("medico", $cid),
                    "doctor_user_id",
                    $docs,
                    (int) $r["doctor_user_id"],
                    "required",
                ) .
                select_label(
                    "Paciente",
                    "patient_link_id",
                    $pats,
                    (int) $r["patient_link_id"],
                    "required",
                ) .
                procedure_select_html(
                    $cid,
                    "reason",
                    (string) ($r["reason"] ?? ""),
                ) .
                '<div class="two">' .
                form_row(
                    "Início",
                    input(
                        "start_at",
                        "datetime-local",
                        datetime_local_value((string) $r["start_at"]),
                        "required",
                    ),
                ) .
                form_row(
                    "Fim",
                    input(
                        "end_at",
                        "datetime-local",
                        datetime_local_value((string) $r["end_at"]),
                        "required",
                    ),
                ) .
                "</div>" .
                appointment_payment_form_html($cid, $r) .
                form_row(
                    "Motivo da alteração",
                    input(
                        "change_reason",
                        "text",
                        "",
                        'placeholder="Ex.: remarcação solicitada, ajuste administrativo"',
                    ),
                ) .
                form_row(
                    "Notas",
                    input(
                        "notes",
                        "text",
                        (string) ($r["notes"] ?? ""),
                        'placeholder="Opcional"',
                    ),
                ) .
                '<div class="form-actions"><button type="submit" class="primary small">' .
                icon("edit_calendar") .
                "<span>Salvar edição</span></button></div></form>";
            $deleteForm =
                '<form method="post" class="agenda-delete-form compact agenda-delete-form-nested">' .
                csrf_field() .
                $returnHidden .
                '<input type="hidden" name="act" value="delete_appointment"><input type="hidden" name="id" value="' .
                $id .
                '">' .
                form_row(
                    "Motivo da exclusão",
                    input(
                        "cancel_reason",
                        "text",
                        "",
                        'required placeholder="Ex.: paciente desmarcou"',
                    ),
                ) .
                '<button type="submit" class="danger small">' .
                icon("delete") .
                "<span>Cancelar</span></button></form>";
            $secondaryItems .=
                '<details class="agenda-action-disclosure"><summary class="agenda-action-item agenda-action-link">' .
                icon("edit_calendar") .
                "<span>Editar</span></summary>" .
                $editForm .
                "</details>";
        } elseif (
            appointment_journey_role_matches($role, "recepcionista") ||
            appointment_journey_role_matches($role, "gerente")
        ) {
            $secondaryItems .=
                '<span class="agenda-action-item agenda-action-disabled" title="' .
                e($editGuard) .
                '">' .
                icon("edit_calendar") .
                "<span>Editar bloqueado</span></span>";
        }
        if ($cancelGuard === "") {
            $deleteForm =
                '<form method="post" class="agenda-delete-form compact agenda-delete-form-nested">' .
                csrf_field() .
                $returnHidden .
                '<input type="hidden" name="act" value="delete_appointment"><input type="hidden" name="id" value="' .
                $id .
                '">' .
                form_row(
                    "Motivo da exclusão",
                    input(
                        "cancel_reason",
                        "text",
                        "",
                        'required placeholder="Ex.: paciente desmarcou"',
                    ),
                ) .
                '<button type="submit" class="danger small">' .
                icon("delete") .
                "<span>Cancelar</span></button></form>";
            $secondaryItems .=
                '<details class="agenda-action-disclosure"><summary class="agenda-action-item agenda-action-link danger-link">' .
                icon("delete") .
                "<span>Cancelar</span></summary>" .
                $deleteForm .
                "</details>";
        } elseif (
            appointment_journey_role_matches($role, "recepcionista") ||
            appointment_journey_role_matches($role, "gerente")
        ) {
            $secondaryItems .=
                '<span class="agenda-action-item agenda-action-disabled" title="' .
                e($cancelGuard) .
                '">' .
                icon("delete") .
                "<span>Cancelar bloqueado</span></span>";
        }
        if ($secondaryItems !== "") {
            $secondary =
                '<div class="agenda-action-section agenda-action-section-secondary"><span class="agenda-action-section-title">Outras ações</span><div class="agenda-action-list agenda-action-list-secondary">' .
                $secondaryItems .
                "</div></div>";
        }
        if ($mode === "secondary_panel") {
            return $secondary !== ""
                ? '<div class="agenda-action-panel agenda-action-panel-journey agenda-action-panel-inline agenda-action-panel-secondary-only"><div class="agenda-action-title">Outras ações</div>' .
                        $secondary .
                        "</div>"
                : "";
        }
        $panel =
            '<div class="agenda-action-title">Ações disponíveis</div>' .
            $journeyHtml .
            $secondary;
        if ($mode === "panel") {
            return '<div class="agenda-action-panel agenda-action-panel-journey agenda-action-panel-inline">' .
                $panel .
                "</div>";
        }
        return '<details class="agenda-item-actions compact-actions agenda-journey-actions"><summary class="ghost small cmdlike">' .
            icon("more_horiz") .
            '<span>Ações</span></summary><div class="agenda-action-panel agenda-action-panel-journey">' .
            $panel .
            "</div></details>";
    };
    $appointmentRow = function (array $r, string $mode = "full") use (
        $role,
        $doctorMap,
        $statusMeta,
        $patientName,
        $appointmentActions,
        $returnHidden,
        $cid,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:3790
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `app_storage_timestamp`, `trim`, `app_time_br`, `first_name`, `function_exists`, `financial_appointment_operational_chip_html`, `e`, `icon`, `appointment_journey_measure_html`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $startTs = app_storage_timestamp($r["start_at"]);
        $endTs = app_storage_timestamp($r["end_at"]);
        [$label, $class, $ico] = $statusMeta($r);
        $doctor = $doctorMap[(int) ($r["doctor_user_id"] ?? 0)]["name"] ?? "";
        $patient = $patientName($r);
        $reason = trim((string) ($r["reason"] ?? "")) ?: "Consulta";
        $period =
            app_time_br((string) $r["start_at"]) .
            "–" .
            app_time_br((string) $r["end_at"]);
        $timeLabel = app_time_br((string) $r["start_at"]);
        $meta = trim(
            ($doctor !== ""
                ? first_name($doctor)
                : "Profissional não definido") .
                " · " .
                $reason,
            " ·",
        );
        $quickActions = $appointmentActions($r, "quick");
        $secondaryActions = $appointmentActions($r, "secondary_panel");
        $financialHtml = function_exists(
            "financial_appointment_operational_chip_html",
        )
            ? financial_appointment_operational_chip_html($cid, $r, $role)
            : "";
        return '<article class="agenda-profile-row agenda-profile-row-' .
            $class .
            ' journey-row agenda-row-toggle" data-agenda-row><div class="agenda-row-summary" data-agenda-row-summary role="button" tabindex="0" aria-expanded="false" title="Abrir jornada"><span class="agenda-profile-time" title="' .
            e($period) .
            '">' .
            e($timeLabel) .
            '</span><span class="agenda-profile-main agenda-profile-main-compact"><strong>' .
            e($patient) .
            "</strong><small>" .
            e($meta) .
            '</small></span><span class="pill ' .
            $class .
            '">' .
            icon($ico) .
            e($label) .
            "</span>" .
            $financialHtml .
            $quickActions .
            '<button type="button" class="agenda-row-chevron" data-agenda-row-toggle aria-label="Expandir ou recolher agendamento">' .
            icon("expand_more") .
            '</button></div><div class="agenda-row-expanded" data-agenda-row-expanded hidden>' .
            appointment_journey_measure_html($r, $role, "") .
            $secondaryActions .
            "</div></article>";
    };
    $blockRow = function (array $b) use (
        $role,
        $uid,
        $returnHidden,
        $docs,
        $doctorMap,
        $creators,
        $cid,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:3850
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `app_storage_timestamp`, `first_name`, `agenda_doctor_name`, `app_time_br`, `csrf_field`, `select_label`, `form_row`, `input`, `datetime_local_value`, `icon`, `e`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $startTs = app_storage_timestamp($b["start_at"]);
        $endTs = app_storage_timestamp($b["end_at"]);
        $blockDoctor = !empty($b["doctor_user_id"])
            ? (int) $b["doctor_user_id"]
            : null;
        $doctorLabel = $blockDoctor
            ? "Agenda de " .
                first_name(
                    $doctorMap[$blockDoctor]["name"] ??
                        agenda_doctor_name($cid, $blockDoctor),
                )
            : "Todo consultório";
        $creator = $creators[(int) ($b["created_by"] ?? 0)]["name"] ?? "";
        $period =
            app_time_br((string) $b["start_at"]) .
            "–" .
            app_time_br((string) $b["end_at"]);
        $timeLabel = app_time_br((string) $b["start_at"]);
        $canManage =
            $role === "gerente" || (int) ($b["created_by"] ?? 0) === $uid;
        $actions = "";
        if ($canManage) {
            $blockOptions =
                $role === "gerente"
                    ? ["" => "Todo consultório"] + $docs
                    : $docs;
            $editForm =
                '<form method="post" class="agenda-edit-form">' .
                csrf_field() .
                $returnHidden .
                '<input type="hidden" name="act" value="update_block"><input type="hidden" name="id" value="' .
                (int) $b["id"] .
                '">' .
                select_label(
                    "Agenda bloqueada",
                    "doctor_user_id",
                    $blockOptions,
                    $blockDoctor,
                ) .
                '<div class="two">' .
                form_row(
                    "Início",
                    input(
                        "start_at",
                        "datetime-local",
                        datetime_local_value((string) $b["start_at"]),
                        "required",
                    ),
                ) .
                form_row(
                    "Fim",
                    input(
                        "end_at",
                        "datetime-local",
                        datetime_local_value((string) $b["end_at"]),
                        "required",
                    ),
                ) .
                "</div>" .
                form_row(
                    "Motivo",
                    input("reason", "text", (string) $b["reason"], "required"),
                ) .
                '<div class="form-actions"><button type="button" class="ghost small" data-close-panel>' .
                icon("close") .
                '<span>Fechar</span></button><button type="submit" class="primary small">' .
                icon("edit_calendar") .
                "<span>Alterar</span></button></div></form>";
            $deleteForm =
                '<form method="post" class="agenda-delete-form compact">' .
                csrf_field() .
                $returnHidden .
                '<input type="hidden" name="act" value="delete_block"><input type="hidden" name="id" value="' .
                (int) $b["id"] .
                '">' .
                form_row(
                    "Motivo da exclusão",
                    input(
                        "cancel_reason",
                        "text",
                        "",
                        'required placeholder="Ex.: bloqueio não será mais necessário"',
                    ),
                ) .
                '<button type="submit" class="danger small">' .
                icon("delete") .
                "<span>Remover bloqueio</span></button></form>";
            $actions =
                '<details class="agenda-item-actions compact-actions"><summary class="ghost small cmdlike">' .
                icon("more_horiz") .
                '<span>Ações</span></summary><div class="agenda-action-panel"><div class="agenda-action-title">Ações do bloqueio</div>' .
                $editForm .
                $deleteForm .
                "</div></details>";
        }
        return '<article class="agenda-profile-row agenda-profile-row-block"><span class="agenda-profile-time" title="' .
            e($period) .
            '">' .
            e($timeLabel) .
            '</span><span class="agenda-profile-main"><strong>Horário bloqueado</strong><small>' .
            e(
                trim(
                    $doctorLabel .
                        " · " .
                        (string) $b["reason"] .
                        ($creator !== ""
                            ? " · por " . first_name($creator)
                            : ""),
                    " ·",
                ),
            ) .
            '</small></span><span class="pill bad">' .
            icon("event_busy") .
            "Bloqueio</span>" .
            $actions .
            "</article>";
    };
    $total = count($rows);
    $arrived = 0;
    $late = 0;
    $started = 0;
    $done = 0;
    $upcoming = 0;
    $nextAppt = null;
    foreach ($rows as $a) {
        $start = app_storage_timestamp($a["start_at"]) ?: 0;
        if ($isDone($a)) {
            $done++;
        } elseif ($isStarted($a)) {
            $started++;
        } elseif ($isArrived($a)) {
            $arrived++;
        } elseif ($isLate($a)) {
            $late++;
        }
        if (!$isDone($a) && $start >= $nowTs) {
            $upcoming++;
            if ($nextAppt === null) {
                $nextAppt = $a;
            }
        }
    }
    $freeSlots = [];
    $workRanges = [];
    if ($agendaDoctor > 0) {
        $workRanges = doctor_work_ranges_for_day($cid, $agendaDoctor, $day);
        $busy = [];
        foreach ($rows as $a) {
            $busy[] = [
                app_storage_timestamp($a["start_at"]) ?: 0,
                app_storage_timestamp($a["end_at"]) ?: 0,
            ];
        }
        foreach ($blocks as $b) {
            $busy[] = [
                app_storage_timestamp($b["start_at"]) ?: 0,
                app_storage_timestamp($b["end_at"]) ?: 0,
            ];
        }
        foreach ($workRanges as $range) {
            for ($t = $range[0]; $t + 1800 <= $range[1]; $t += 1800) {
                $slotEnd = $t + 1800;
                $ok = true;
                foreach ($busy as $bi) {
                    if ($bi[0] < $slotEnd && $bi[1] > $t) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    $freeSlots[] = date("H:i", $t);
                    if (count($freeSlots) >= 6) {
                        break 2;
                    }
                }
            }
        }
    }
    $localTs = function (string $dt) use ($cid): int {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:4037
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `app_db_utc_to_local`, `->getTimestamp`, `strtotime`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $d = app_db_utc_to_local($dt, $cid);
        return $d ? $d->getTimestamp() : (strtotime($dt) ?: 0);
    };
    $nextLabel = $nextAppt ? app_time_br((string) $nextAppt["start_at"]) : "—";
    $workRanges =
        $agendaDoctor > 0
            ? doctor_work_ranges_for_day($cid, $agendaDoctor, $day)
            : [];
    $zone = new DateTimeZone(app_context_timezone(null, $cid));
    $fmtLocal = function (int $ts) use ($zone): string {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:4047
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `DateTimeImmutable`, `->setTimezone`, `->format`.
         * Classes ou serviços instanciados: `DateTimeImmutable`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return new DateTimeImmutable("@" . $ts)
            ->setTimezone($zone)
            ->format("H:i");
    };
    if (!$workRanges) {
        $fallbackStart = new DateTimeImmutable(
            $day . " 08:00:00",
            $zone,
        )->getTimestamp();
        $fallbackEnd = new DateTimeImmutable(
            $day . " 17:00:00",
            $zone,
        )->getTimestamp();
        $workRanges = [[$fallbackStart, $fallbackEnd, "08:00", "17:00"]];
    }
    $workStartTs = (int) $workRanges[0][0];
    $workEndTs = (int) $workRanges[count($workRanges) - 1][1];
    $workStartLabel = (string) $workRanges[0][2];
    $workEndLabel = (string) $workRanges[count($workRanges) - 1][3];
    $totalMinutes = max(15, (int) ceil(($workEndTs - $workStartTs) / 60));
    $slotMinutes = 15;
    $slotCount = max(1, (int) ceil($totalMinutes / $slotMinutes));
    $busyMinutes = 0;
    $busy = [];
    foreach ($rows as $a) {
        $sTs = max($workStartTs, $localTs((string) $a["start_at"]));
        $eTs = min($workEndTs, $localTs((string) $a["end_at"]));
        if ($eTs > $sTs) {
            $busyMinutes += (int) ceil(($eTs - $sTs) / 60);
            $busy[] = [$sTs, $eTs];
        }
    }
    foreach ($blocks as $b) {
        $sTs = max($workStartTs, $localTs((string) $b["start_at"]));
        $eTs = min($workEndTs, $localTs((string) $b["end_at"]));
        if ($eTs > $sTs) {
            $busy[] = [$sTs, $eTs];
        }
    }
    $occupancyPct = min(
        100,
        max(0, (int) round(($busyMinutes / max(1, $totalMinutes)) * 100)),
    );
    $nextFree = "—";
    $cursor = max($workStartTs, time());
    $cursor =
        $cursor +
        (($slotMinutes * 60 - ($cursor % ($slotMinutes * 60))) %
            ($slotMinutes * 60));
    for (
        $t = $cursor;
        $t + $slotMinutes * 60 <= $workEndTs;
        $t += $slotMinutes * 60
    ) {
        $slotEnd = $t + $slotMinutes * 60;
        $ok = true;
        foreach ($busy as $bi) {
            if ($bi[0] < $slotEnd && $bi[1] > $t) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            $nextFree = $fmtLocal($t);
            break;
        }
    }
    $metricCard = function (
        string $icon,
        string $value,
        string $label,
        string $kind,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:4115
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `e`, `icon`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return '<article class="agenda-floating-card agenda-floating-card-' .
            e($kind) .
            '" aria-label="' .
            e($label) .
            ": " .
            e($value) .
            '"><span class="agenda-floating-icon" aria-hidden="true">' .
            icon($icon) .
            '</span><div class="agenda-floating-copy"><b>' .
            e($value) .
            '</b><span class="agenda-floating-label">' .
            e($label) .
            "</span></div></article>";
    };
    $floating =
        '<aside class="agenda-floating-kpis ds-fixed-agenda-kpis" data-ds-fixed-layer="agenda-kpis" aria-label="Resumo da agenda">' .
        $metricCard(
            "conditions",
            (string) $total,
            "Consultas",
            "appointments",
        ) .
        $metricCard("timelapse", $occupancyPct . "%", "Ocupação", "occupancy") .
        $metricCard("acute", $nextFree, "Horário livre", "free-time") .
        "</aside>";
    $slotRows = "";
    $slotCanCreate = in_array($role, ["recepcionista", "gerente"], true);
    for ($i = 0; $i <= $slotCount; $i++) {
        $ts = $workStartTs + $i * $slotMinutes * 60;
        $slotStart = $day . "T" . $fmtLocal($ts);
        $slotClass = "agenda-day-slot " . ($i % 4 === 0 ? "is-hour" : "");
        if ($slotCanCreate) {
            $slotParams = $buildParams($day, $agendaDoctor);
            $slotParams["mode"] = "create";
            $slotParams["quick"] = "1";
            $slotParams["start"] = $slotStart;
            $slotRows .=
                '<a class="' .
                e($slotClass) .
                '" href="' .
                href("appointments", $slotParams) .
                '" style="--slot-index:' .
                $i .
                '" data-agenda-slot="1" data-start="' .
                e($slotStart) .
                '" aria-label="Cadastrar agendamento às ' .
                e($fmtLocal($ts)) .
                '"><span>' .
                e($fmtLocal($ts)) .
                "</span></a>";
        } else {
            $slotRows .=
                '<div class="' .
                e($slotClass) .
                '" style="--slot-index:' .
                $i .
                '" data-agenda-slot="1" data-start="' .
                e($slotStart) .
                '"><span>' .
                e($fmtLocal($ts)) .
                "</span></div>";
        }
    }
    $eventHtml = "";
    foreach ($rows as $r) {
        $sTs = $localTs((string) $r["start_at"]);
        $eTs = $localTs((string) $r["end_at"]);
        if ($eTs <= $workStartTs || $sTs >= $workEndTs) {
            continue;
        }
        $top = max(0, ($sTs - $workStartTs) / 60 / $slotMinutes);
        $height = max(
            1,
            (min($eTs, $workEndTs) - max($sTs, $workStartTs)) /
                60 /
                $slotMinutes,
        );
        [$label, $class, $ico] = $statusMeta($r);
        $patient = $patientName($r);
        $doctor = $doctorMap[(int) ($r["doctor_user_id"] ?? 0)]["name"] ?? "";
        $reason = trim((string) ($r["reason"] ?? "")) ?: "Consulta";
        $period =
            app_time_br((string) $r["start_at"]) .
            "–" .
            app_time_br((string) $r["end_at"]);
        $eventHtml .=
            '<article class="agenda-day-event agenda-day-event-' .
            $class .
            '" style="--event-top:' .
            $top .
            ";--event-height:" .
            $height .
            '" title="' .
            e($period . " · " . $patient) .
            '">' .
            '<span class="agenda-day-event-time">' .
            e($period) .
            "</span><strong>" .
            e($patient) .
            "</strong><small>" .
            e(
                trim(
                    ($doctor !== "" ? first_name($doctor) : "Profissional") .
                        " · " .
                        $reason,
                    " ·",
                ),
            ) .
            "</small><em>" .
            icon($ico) .
            e($label) .
            "</em></article>";
    }
    foreach ($blocks as $b) {
        $sTs = $localTs((string) $b["start_at"]);
        $eTs = $localTs((string) $b["end_at"]);
        if ($eTs <= $workStartTs || $sTs >= $workEndTs) {
            continue;
        }
        $top = max(0, ($sTs - $workStartTs) / 60 / $slotMinutes);
        $height = max(
            1,
            (min($eTs, $workEndTs) - max($sTs, $workStartTs)) /
                60 /
                $slotMinutes,
        );
        $eventHtml .=
            '<article class="agenda-day-event agenda-day-event-block" style="--event-top:' .
            $top .
            ";--event-height:" .
            $height .
            '" title="' .
            e((string) $b["reason"]) .
            '">' .
            '<span class="agenda-day-event-time">' .
            e(
                app_time_br((string) $b["start_at"]) .
                    "–" .
                    app_time_br((string) $b["end_at"]),
            ) .
            "</span><strong>Bloqueado</strong><small>" .
            e((string) $b["reason"]) .
            "</small><em>" .
            icon("event_busy") .
            "Bloqueio</em></article>";
    }
    if ($eventHtml === "") {
        $eventHtml =
            '<div class="agenda-day-empty agenda-day-empty-icon" role="img" aria-label="Sem agendamentos no expediente" title="Sem agendamentos no expediente">' .
            icon("no_sim") .
            "</div>";
    }
    $doctorFilter = "";
    $navPrev = date("Y-m-d", strtotime($day . " -1 day"));
    $navNext = date("Y-m-d", strtotime($day . " +1 day"));
    if ($agendaView === "semanal") {
        $navPrev = date("Y-m-d", strtotime($day . " -7 days"));
        $navNext = date("Y-m-d", strtotime($day . " +7 days"));
    } elseif ($agendaView === "mensal") {
        $navPrev = date("Y-m-d", strtotime($day . " -1 month"));
        $navNext = date("Y-m-d", strtotime($day . " +1 month"));
    }
    $navDoctorOptions = "";
    foreach ($docs as $id => $name) {
        if ($role === "medico" && (int) $id !== $uid) {
            continue;
        }
        $label = agenda_crown_label_for_view($agendaView, $day, (string) $name);
        $navDoctorOptions .=
            '<option value="' .
            (int) $id .
            '" ' .
            ((int) $id === $agendaDoctor ? "selected" : "") .
            ">" .
            e($label) .
            "</option>";
    }
    $navDay =
        '<form method="get" class="agenda-crown-picker" aria-label="Selecionar dia e profissional">' .
        '<input type="hidden" name="r" value="appointments">' .
        '<input type="hidden" name="d" value="' .
        e($day) .
        '">' .
        ($agendaView !== "diario"
            ? '<input type="hidden" name="view" value="' . e($agendaView) . '">'
            : "") .
        '<a class="agenda-crown-arrow" href="' .
        href(
            "appointments",
            $buildParams($navPrev, $agendaDoctor, $agendaView),
        ) .
        '" aria-label="Voltar">' .
        icon("chevron_left") .
        "</a>" .
        '<select name="doctor" onchange="this.form.submit()" aria-label="Dia e profissional">' .
        $navDoctorOptions .
        "</select>" .
        '<a class="agenda-crown-arrow" href="' .
        href(
            "appointments",
            $buildParams($navNext, $agendaDoctor, $agendaView),
        ) .
        '" aria-label="Avançar">' .
        icon("chevron_right") .
        "</a>" .
        "</form>";
    $title = "Agenda";
    $subtitle = "";
    $agendaCreateUrl = href(
        "appointments",
        $buildParams($day, $agendaDoctor, "diario") + ["mode" => "create"],
    );
    $agendaDayNote = agenda_note_card_html(
        agenda_note_visible_for_day($cid, $day, $c),
        $c,
    );
    $agendaDay =
        $agendaDayNote .
        '<section class="agenda-day-shell agenda-crown-shell" style="--agenda-slots:' .
        $slotCount .
        ';--agenda-day-date:\'' .
        e($day) .
        '\'" data-agenda-day="' .
        e($day) .
        '" data-slot-minutes="' .
        $slotMinutes .
        '" data-work-start="' .
        e($fmtLocal($workStartTs)) .
        '" data-work-end="' .
        e($fmtLocal($workEndTs)) .
        '">' .
        $floating .
        '<div class="agenda-day-scale" aria-hidden="true">' .
        $slotRows .
        '</div><div class="agenda-day-canvas" data-agenda-canvas data-slot-minutes="' .
        $slotMinutes .
        '" data-day="' .
        e($day) .
        '" data-work-start="' .
        e($fmtLocal($workStartTs)) .
        '" data-agenda-create-url="' .
        e($agendaCreateUrl) .
        '">' .
        $eventHtml .
        "</div></section>";
    $renderRows = "";
    foreach ($rows as $r) {
        $renderRows .= $appointmentRow($r);
    }
    foreach ($blocks as $b) {
        $renderRows .= $blockRow($b);
    }
    $agendaSummary =
        '<section class="agenda-summary-view">' .
        $floating .
        '<div class="agenda-summary-grid"><article class="agenda-summary-card">' .
        icon("event_available") .
        "<b>" .
        n($total) .
        '</b><span>consultas no dia</span></article><article class="agenda-summary-card">' .
        icon("how_to_reg") .
        "<b>" .
        n($arrived) .
        '</b><span>chegaram</span></article><article class="agenda-summary-card">' .
        icon("stethoscope") .
        "<b>" .
        n($started) .
        '</b><span>em atendimento</span></article><article class="agenda-summary-card">' .
        icon("task_alt") .
        "<b>" .
        n($done) .
        '</b><span>finalizadas</span></article></div><div class="agenda-summary-list agenda-day-vertical-timeline">' .
        ($renderRows !== ""
            ? $renderRows
            : '<div class="empty">Nenhum agendamento ou bloqueio neste dia.</div>') .
        "</div></section>";
    $renderAgendaCanvas = function (
        string $renderDay,
        array $dayRows,
        array $dayBlocks,
        array $patientLinksMap,
        array $personsMap,
        array $doctorMapLocal,
    ) use (
        $cid,
        $role,
        $agendaDoctor,
        $buildParams,
        $slotMinutes,
        $statusMeta,
        $localTs,
        $fmtLocal,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:4397
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `doctor_work_ranges_for_day`, `DateTimeZone`, `app_context_timezone`, `DateTimeImmutable`, `->getTimestamp`, `count`, `max`, `ceil`, `in_array`, `e`, `href`, `min` e mais 4.
         * Classes ou serviços instanciados: `DateTimeZone`, `DateTimeImmutable`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $ranges =
            $agendaDoctor > 0
                ? doctor_work_ranges_for_day($cid, $agendaDoctor, $renderDay)
                : [];
        $zone = new DateTimeZone(app_context_timezone(null, $cid));
        if (!$ranges) {
            $fallbackStart = new DateTimeImmutable(
                $renderDay . " 08:00:00",
                $zone,
            )->getTimestamp();
            $fallbackEnd = new DateTimeImmutable(
                $renderDay . " 17:00:00",
                $zone,
            )->getTimestamp();
            $ranges = [[$fallbackStart, $fallbackEnd, "08:00", "17:00"]];
        }
        $startTs = (int) $ranges[0][0];
        $endTs = (int) $ranges[count($ranges) - 1][1];
        $totalMinutes = max(15, (int) ceil(($endTs - $startTs) / 60));
        $slotCount = max(1, (int) ceil($totalMinutes / $slotMinutes));
        $slotRows = "";
        $canCreate = in_array($role, ["recepcionista", "gerente"], true);
        for ($i = 0; $i <= $slotCount; $i++) {
            $ts = $startTs + $i * $slotMinutes * 60;
            $slotStart = $renderDay . "T" . $fmtLocal($ts);
            $slotClass = "agenda-day-slot " . ($i % 4 === 0 ? "is-hour" : "");
            if ($canCreate) {
                $slotParams = $buildParams($renderDay, $agendaDoctor, "diario");
                $slotParams["mode"] = "create";
                $slotParams["start"] = $slotStart;
                $slotRows .=
                    '<a class="' .
                    e($slotClass) .
                    '" href="' .
                    href("appointments", $slotParams) .
                    '" style="--slot-index:' .
                    $i .
                    '" data-agenda-slot="1" data-start="' .
                    e($slotStart) .
                    '" aria-label="Cadastrar agendamento às ' .
                    e($fmtLocal($ts)) .
                    '"><span>' .
                    e($fmtLocal($ts)) .
                    "</span></a>";
            } else {
                $slotRows .=
                    '<div class="' .
                    e($slotClass) .
                    '" style="--slot-index:' .
                    $i .
                    '" data-agenda-slot="1" data-start="' .
                    e($slotStart) .
                    '"><span>' .
                    e($fmtLocal($ts)) .
                    "</span></div>";
            }
        }
        $patientNameLocal = function (array $a) use (
            $patientLinksMap,
            $personsMap,
        ): string {
            /*
             * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:4471
             * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
             * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
             * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
             * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
             * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
             * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
             */
            $pid = (int) ($a["patient_link_id"] ?? 0);
            $pl = $patientLinksMap[$pid] ?? [];
            $ps = $personsMap[(int) ($pl["person_id"] ?? 0)] ?? [];
            return (string) ($ps["full_name"] ?? "Paciente não identificado");
        };
        $eventHtml = "";
        foreach ($dayRows as $r) {
            $sTs = $localTs((string) $r["start_at"]);
            $eTs = $localTs((string) $r["end_at"]);
            if ($eTs <= $startTs || $sTs >= $endTs) {
                continue;
            }
            $top = max(0, ($sTs - $startTs) / 60 / $slotMinutes);
            $height = max(
                1,
                (min($eTs, $endTs) - max($sTs, $startTs)) / 60 / $slotMinutes,
            );
            [$label, $class, $ico] = $statusMeta($r);
            $patient = $patientNameLocal($r);
            $doctor =
                $doctorMapLocal[(int) ($r["doctor_user_id"] ?? 0)]["name"] ??
                "";
            $reason = trim((string) ($r["reason"] ?? "")) ?: "Consulta";
            $period =
                app_time_br((string) $r["start_at"]) .
                "–" .
                app_time_br((string) $r["end_at"]);
            $eventHtml .=
                '<article class="agenda-day-event agenda-day-event-' .
                $class .
                '" style="--event-top:' .
                $top .
                ";--event-height:" .
                $height .
                '" title="' .
                e($period . " · " . $patient) .
                '">' .
                '<span class="agenda-day-event-time">' .
                e($period) .
                "</span><strong>" .
                e($patient) .
                "</strong><small>" .
                e(
                    trim(
                        ($doctor !== ""
                            ? first_name($doctor)
                            : "Profissional") .
                            " · " .
                            $reason,
                        " ·",
                    ),
                ) .
                "</small><em>" .
                icon($ico) .
                e($label) .
                "</em></article>";
        }
        foreach ($dayBlocks as $b) {
            $sTs = $localTs((string) $b["start_at"]);
            $eTs = $localTs((string) $b["end_at"]);
            if ($eTs <= $startTs || $sTs >= $endTs) {
                continue;
            }
            $top = max(0, ($sTs - $startTs) / 60 / $slotMinutes);
            $height = max(
                1,
                (min($eTs, $endTs) - max($sTs, $startTs)) / 60 / $slotMinutes,
            );
            $eventHtml .=
                '<article class="agenda-day-event agenda-day-event-block" style="--event-top:' .
                $top .
                ";--event-height:" .
                $height .
                '" title="' .
                e((string) $b["reason"]) .
                '">' .
                '<span class="agenda-day-event-time">' .
                e(
                    app_time_br((string) $b["start_at"]) .
                        "–" .
                        app_time_br((string) $b["end_at"]),
                ) .
                "</span><strong>Bloqueado</strong><small>" .
                e((string) $b["reason"]) .
                "</small><em>" .
                icon("event_busy") .
                "Bloqueio</em></article>";
        }
        if ($eventHtml === "") {
            $eventHtml =
                '<div class="agenda-day-empty agenda-day-empty-icon" role="img" aria-label="Sem agendamentos no expediente" title="Sem agendamentos no expediente">' .
                icon("no_sim") .
                "</div>";
        }
        $createUrl = href(
            "appointments",
            $buildParams($renderDay, $agendaDoctor, "diario") + [
                "mode" => "create",
                "quick" => "1",
            ],
        );
        return '<section class="agenda-day-shell agenda-crown-shell agenda-week-day-shell" style="--agenda-slots:' .
            $slotCount .
            ';--agenda-day-date:\'' .
            e($renderDay) .
            '\'" data-agenda-day="' .
            e($renderDay) .
            '" data-slot-minutes="' .
            $slotMinutes .
            '" data-work-start="' .
            e($fmtLocal($startTs)) .
            '" data-work-end="' .
            e($fmtLocal($endTs)) .
            '"><div class="agenda-day-scale" aria-hidden="true">' .
            $slotRows .
            '</div><div class="agenda-day-canvas" data-agenda-canvas data-slot-minutes="' .
            $slotMinutes .
            '" data-day="' .
            e($renderDay) .
            '" data-work-start="' .
            e($fmtLocal($startTs)) .
            '" data-agenda-create-url="' .
            e($createUrl) .
            '">' .
            $eventHtml .
            "</div></section>";
    };
    $groupByDay = function (array $items) use ($cid): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Appointments/Appointments.php:4602
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `app_db_utc_to_local`, `->format`, `substr`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $out = [];
        foreach ($items as $it) {
            $dt = app_db_utc_to_local((string) ($it["start_at"] ?? ""), $cid);
            $key = $dt
                ? $dt->format("Y-m-d")
                : substr((string) ($it["start_at"] ?? ""), 0, 10);
            if ($key === "") {
                continue;
            }
            $out[$key][] = $it;
        }
        return $out;
    };
    $monthLabels = ["SEG", "TER", "QUA", "QUI", "SEX", "SAB", "DOM"];
    $agendaWeek = "";
    if ($agendaView === "semanal") {
        $weekStart = date(
            "Y-m-d",
            strtotime($day . " -" . (int) date("w", strtotime($day)) . " days"),
        );
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $weekDays[] = date(
                "Y-m-d",
                strtotime($weekStart . " +" . $i . " days"),
            );
        }
        [$weekRangeStart] = app_local_day_utc_range($weekDays[0], $cid, $c);
        [, $weekRangeEnd] = app_local_day_utc_range($weekDays[6], $cid, $c);
        $weekSql =
            "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,notes,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_status,payment_method,payment_amount_cents,payment_confirmed_at,revenue_id FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?";
        $weekParams = [$cid, $weekRangeStart, $weekRangeEnd];
        if ($agendaDoctor > 0) {
            $weekSql .= " AND doctor_user_id=?";
            $weekParams[] = $agendaDoctor;
        }
        $weekSql .= " ORDER BY start_at ASC LIMIT 600";
        $weekRows = q($weekSql, $weekParams)->fetchAll();
        $weekBlockSql =
            "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at<? AND end_at>?";
        $weekBlockParams = [$cid, $weekRangeEnd, $weekRangeStart];
        if ($agendaDoctor > 0) {
            $weekBlockSql .=
                " AND (doctor_user_id IS NULL OR doctor_user_id=?)";
            $weekBlockParams[] = $agendaDoctor;
        }
        $weekBlockSql .= " ORDER BY start_at ASC LIMIT 300";
        $weekBlocks = q($weekBlockSql, $weekBlockParams)->fetchAll();
        $weekGroupedRows = $groupByDay($weekRows);
        $weekGroupedBlocks = $groupByDay($weekBlocks);
        $weekPatientLinks = scoped_patient_map(
            $cid,
            int_ids($weekRows, "patient_link_id"),
            "id,person_id",
        );
        $weekPersons = fetch_map(
            "pi_persons",
            int_ids(array_values($weekPatientLinks), "person_id"),
            "id,full_name",
        );
        $weekDoctorMap = scoped_user_map(
            $cid,
            array_values(
                array_unique(
                    array_merge(
                        int_ids($weekRows, "doctor_user_id"),
                        int_ids($weekBlocks, "doctor_user_id"),
                    ),
                ),
            ),
            "id,name",
        );
        $weekLabels = ["DOM", "SEG", "TER", "QUA", "QUI", "SEX", "SAB"];
        $agendaWeek =
            $floating .
            '<section class="agenda-week-view" aria-label="Agenda semanal"><div class="agenda-week-grid">';
        foreach ($weekDays as $i => $wd) {
            $agendaWeek .=
                '<article class="agenda-week-column"><header><b>' .
                e($weekLabels[$i]) .
                "</b><span>" .
                e(date("d/m", strtotime($wd))) .
                "</span></header>" .
                $renderAgendaCanvas(
                    $wd,
                    $weekGroupedRows[$wd] ?? [],
                    $weekGroupedBlocks[$wd] ?? [],
                    $weekPatientLinks,
                    $weekPersons,
                    $weekDoctorMap,
                ) .
                "</article>";
        }
        $agendaWeek .= "</div></section>";
    }
    $agendaMonth = "";
    if ($agendaView === "mensal") {
        $monthFirst = date("Y-m-01", strtotime($day));
        $monthLast = date("Y-m-t", strtotime($day));
        [$monthRangeStart] = app_local_day_utc_range($monthFirst, $cid, $c);
        [, $monthRangeEnd] = app_local_day_utc_range($monthLast, $cid, $c);
        $monthSql =
            "SELECT id,doctor_user_id,start_at,end_at,status FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?";
        $monthParams = [$cid, $monthRangeStart, $monthRangeEnd];
        if ($agendaDoctor > 0) {
            $monthSql .= " AND doctor_user_id=?";
            $monthParams[] = $agendaDoctor;
        }
        $monthSql .= " ORDER BY start_at ASC LIMIT 1500";
        $monthRows = q($monthSql, $monthParams)->fetchAll();
        $monthGrouped = $groupByDay($monthRows);
        $daysInMonth = (int) date("t", strtotime($monthFirst));
        $firstW = ((int) date("w", strtotime($monthFirst)) + 6) % 7;
        $agendaMonth =
            '<section class="agenda-month-view" aria-label="Agenda mensal"><div class="agenda-month-weekdays">';
        foreach ($monthLabels as $lab) {
            $agendaMonth .= "<span>" . e($lab) . "</span>";
        }
        $agendaMonth .= '</div><div class="agenda-month-grid">';
        for ($i = 0; $i < $firstW; $i++) {
            $agendaMonth .=
                '<span class="agenda-month-blank" aria-hidden="true"></span>';
        }
        for ($dnum = 1; $dnum <= $daysInMonth; $dnum++) {
            $dstr = date(
                "Y-m-d",
                strtotime($monthFirst . " +" . ($dnum - 1) . " days"),
            );
            $ranges =
                $agendaDoctor > 0
                    ? doctor_work_ranges_for_day($cid, $agendaDoctor, $dstr)
                    : [];
            if (!$ranges) {
                $totalM = 9 * 60;
            } else {
                $totalM = 0;
                foreach ($ranges as $rg) {
                    $totalM += max(
                        0,
                        (int) ceil(((int) $rg[1] - (int) $rg[0]) / 60),
                    );
                }
            }
            $busyM = 0;
            foreach ($monthGrouped[$dstr] ?? [] as $mr) {
                $sTs = $localTs((string) $mr["start_at"]);
                $eTs = $localTs((string) $mr["end_at"]);
                if ($eTs > $sTs) {
                    $busyM += (int) ceil(($eTs - $sTs) / 60);
                }
            }
            $pct = min(
                100,
                max(0, (int) round(($busyM / max(1, $totalM)) * 100)),
            );
            $fillPct = min(100, max(0, $pct));
            $isToday = $dstr === app_today_in_timezone($cid, $c);
            $agendaMonth .=
                '<a class="agenda-month-day' .
                ($isToday ? " is-today" : "") .
                '" href="' .
                href(
                    "appointments",
                    $buildParams($dstr, $agendaDoctor, "diario"),
                ) .
                '" style="--agenda-month-fill:' .
                e((string) $fillPct) .
                '%" data-occupancy="' .
                e((string) $pct) .
                '" aria-label="' .
                e(date_br($dstr) . " · " . $pct . "% de ocupação") .
                '"><span class="agenda-month-number">' .
                e((string) $dnum) .
                '</span><span class="agenda-month-occupancy-value">' .
                e($pct . "%") .
                "</span></a>";
        }
        $agendaMonth .= "</div></section>";
    }
    $agendaContent = match ($agendaView) {
        "resumo" => $agendaSummary,
        "semanal" => $agendaWeek,
        "mensal" => $agendaMonth,
        default => $agendaDay,
    };
    $body =
        '<div class="agenda-profile agenda-day-first agenda-crown-page agenda-view-' .
        e($agendaView) .
        '">' .
        card(
            $doctorFilter . $navDay,
            "agenda-card agenda-nav-card agenda-crown-nav-card",
        ) .
        $agendaContent .
        "</div>";
    page("Agenda", page_head($title, $subtitle, $headActions) . $body);
}
function appointment_procedure_id_from_post(
    int $cid,
    string $field = "reason",
): ?int {
    /*
     * GUIA DE MANUTENÇÃO — appointment_procedure_id_from_post
     * Responsabilidade: Implementa a responsabilidade “appointment procedure id from post” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `trim`, `str_starts_with`, `substr`, `one`.
     * Estado externo lido: `$_POST`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $v = trim((string) ($_POST[$field] ?? ""));
    if (str_starts_with($v, "procedure:")) {
        $id = (int) substr($v, 10);
        $p = one(
            "SELECT id FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1",
            [$id, $cid],
        );
        if ($p) {
            return $id;
        }
    }
    return null;
}
function appointment_procedure_price_cents(
    int $cid,
    ?int $procedureId,
    int $fallback = 0,
): int {
    /*
     * GUIA DE MANUTENÇÃO — appointment_procedure_price_cents
     * Responsabilidade: Implementa a responsabilidade “appointment procedure price cents” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `appointment_payment_post_context`.
     * Dependências chamadas: `max`, `val`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!$procedureId) {
        return max(0, $fallback);
    }
    $price =
        (int) (val(
            "SELECT price_cents FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1",
            [$procedureId, $cid],
        ) ?:
        0);
    return $price > 0 ? $price : max(0, $fallback);
}
function appointment_payment_post_context(
    int $cid,
    ?int $procedureId,
    int $fallbackAmount = 0,
): array {
    /*
     * GUIA DE MANUTENÇÃO — appointment_payment_post_context
     * Responsabilidade: Implementa a responsabilidade “appointment payment post context” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `appointment_procedure_price_cents`, `normalize_payment_method`, `function_exists`, `financial_office_destination_belongs`, `financial_location_belongs`.
     * Estado externo lido: `$_POST`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $paid = isset($_POST["payment_confirmed"]);
    if (!$paid) {
        return [false, "", 0, 0, null];
    }
    if (!$procedureId) {
        return [
            true,
            "",
            0,
            0,
            "Selecione um Procedimento para registrar pagamento.",
        ];
    }
    $amount = appointment_procedure_price_cents(
        $cid,
        $procedureId,
        $fallbackAmount,
    );
    $method = normalize_payment_method(
        (string) ($_POST["payment_method"] ?? ""),
    );
    if ($method === "") {
        return [true, "", $amount, 0, "Selecione a forma de pagamento."];
    }
    $destination = 0;
    if ($method !== "dinheiro") {
        $destination = (int) ($_POST["payment_destination_location_id"] ?? 0);
        if ($destination <= 0) {
            return [
                true,
                $method,
                $amount,
                0,
                "Selecione o destino do recebimento.",
            ];
        }
        if (
            function_exists("financial_office_destination_belongs")
                ? !financial_office_destination_belongs($cid, $destination)
                : !financial_location_belongs($cid, $destination)
        ) {
            return [
                true,
                $method,
                $amount,
                0,
                "Selecione um destino financeiro válido para este consultório.",
            ];
        }
    }
    return [true, $method, $amount, $destination, null];
}
function appointment_min_duration_message(
    int $cid,
    ?int $procedureId,
    string $startAt,
    string $endAt,
): ?string {
    /*
     * GUIA DE MANUTENÇÃO — appointment_min_duration_message
     * Responsabilidade: Implementa a responsabilidade “appointment min duration message” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Appointments/Appointments.php (domínio e regras de negócio).
     * Chamadores detectados: `page_appointments`.
     * Dependências chamadas: `one`, `max`, `strtotime`, `trim`, `date`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!$procedureId) {
        return null;
    }
    $p = one(
        "SELECT title,duration_minutes FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1",
        [$procedureId, $cid],
    );
    if (!$p) {
        return null;
    }
    $duration = max(0, (int) ($p["duration_minutes"] ?? 0));
    if ($duration <= 0) {
        return null;
    }
    $start = strtotime($startAt);
    $end = strtotime($endAt);
    if (!$start || !$end) {
        return null;
    }
    $minEnd = $start + $duration * 60;
    if ($end < $minEnd) {
        $title = trim((string) ($p["title"] ?? "procedimento selecionado"));
        if ($title === "") {
            $title = "procedimento selecionado";
        }
        return "O horário final não pode ser inferior à duração cadastrada para " .
            $title .
            " (mínimo de " .
            $duration .
            " min). Ajuste o fim para " .
            date("H:i", $minEnd) .
            " ou um horário posterior.";
    }
    return null;
}
