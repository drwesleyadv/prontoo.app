from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]

def replace_once(path: Path, old: str, new: str, label: str) -> None:
    text = path.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    path.write_text(text.replace(old, new, 1), encoding="utf-8")

# Authorization: keep block/unblock permissions aligned with existing granular actions.
auth = root / "app/Application/Authorization/Definitions/SchedulingActionDefinitions.php"
replace_once(
    auth,
    "$definitions->add('appointments', ['agenda_note', 'block'], 'clinic', $appointments, ['appointments:add'], ['agenda:add']);",
    "$definitions->add('appointments', ['agenda_note', 'block', 'block_day'], 'clinic', $appointments, ['appointments:add'], ['agenda:add']);",
    "authorization block_day",
)
replace_once(
    auth,
    "$definitions->add('appointments', ['agenda_note_delete', 'delete_appointment', 'delete_block', 'unblock', 'cancel'], 'clinic', $appointments, ['appointments:delete'], ['agenda:delete'], ['financial:sync', 'tasks:edit']);",
    "$definitions->add('appointments', ['agenda_note_delete', 'delete_appointment', 'delete_block', 'unblock', 'unblock_day', 'cancel'], 'clinic', $appointments, ['appointments:delete'], ['agenda:delete'], ['financial:sync', 'tasks:edit']);",
    "authorization unblock_day",
)

# Application service: atomic full-day toggle, serialized by the same clinic lock used by appointment creation.
service = root / "app/Application/Operational/AppointmentCommandService.php"
service_text = service.read_text(encoding="utf-8")
marker = "    public function updateAppointment(\n"
method = '''    public function setDayBlocked(
        int $clinicId,
        int $doctorUserId,
        string $dayStartAt,
        string $dayEndAt,
        string $blockStartAt,
        string $blockEndAt,
        int $userId,
        bool $blocked,
    ): int {
        if ($clinicId <= 0 || $doctorUserId <= 0 || $userId <= 0) {
            throw new RuntimeException('Não foi possível identificar consultório, profissional e usuário para alterar o dia.');
        }
        $dayStartTs = strtotime($dayStartAt);
        $dayEndTs = strtotime($dayEndAt);
        $blockStartTs = strtotime($blockStartAt);
        $blockEndTs = strtotime($blockEndAt);
        if (
            $dayStartTs === false ||
            $dayEndTs === false ||
            $blockStartTs === false ||
            $blockEndTs === false ||
            $dayEndTs <= $dayStartTs ||
            $blockEndTs <= $blockStartTs ||
            $blockStartTs < $dayStartTs ||
            $blockEndTs > $dayEndTs
        ) {
            throw new RuntimeException('Não foi possível determinar o expediente deste dia.');
        }
        return (int) $this->data->atomic(function () use (
            $clinicId,
            $doctorUserId,
            $dayStartAt,
            $dayEndAt,
            $blockStartAt,
            $blockEndAt,
            $userId,
            $blocked,
        ): int {
            $this->data->result(
                'operational.appointments.05.page_appointments.22',
                [$clinicId],
            );
            $appointment = $this->data->row(
                'operational.appointments.05.page_appointments.43',
                [$clinicId, $doctorUserId, $dayStartAt, $dayEndAt],
            );
            if ($appointment) {
                throw new RuntimeException('O dia já possui consulta marcada para este profissional.');
            }
            if (!$blocked) {
                $this->data->result(
                    'operational.appointments.05.page_appointments.45',
                    [
                        $userId,
                        'Dia desbloqueado pela Agenda diária.',
                        $clinicId,
                        $doctorUserId,
                        $blockStartAt,
                        $blockEndAt,
                    ],
                );
                return 0;
            }
            $existing = $this->data->row(
                'operational.appointments.05.page_appointments.44',
                [$clinicId, $doctorUserId, $blockStartAt, $blockEndAt],
            );
            if ($existing) {
                return (int) ($existing['id'] ?? 0);
            }
            $this->data->result(
                'operational.appointments.05.page_appointments.23',
                [
                    $clinicId,
                    $doctorUserId,
                    $blockStartAt,
                    $blockEndAt,
                    'Dia bloqueado',
                    $userId,
                ],
            );
            return $this->data->lastInsertId();
        });
    }

'''
if marker not in service_text or "public function setDayBlocked(" in service_text:
    raise SystemExit("appointment service insertion point invalid")
service.write_text(service_text.replace(marker, method + marker, 1), encoding="utf-8")

# SQL catalog for the guarded daily toggle.
sql = root / "app/Infrastructure/Operational/AppointmentsSqlCatalog05.php"
sql_text = sql.read_text(encoding="utf-8")
default_marker = "            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),\n"
sql_ops = '''            'operational.appointments.05.page_appointments.43' => (
                "SELECT id FROM pi_appointments WHERE clinic_id=? AND doctor_user_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<? LIMIT 1 FOR UPDATE"
            ),
            'operational.appointments.05.page_appointments.44' => (
                "SELECT id FROM pi_blocks WHERE clinic_id=? AND doctor_user_id=? AND deleted_at IS NULL AND start_at<=? AND end_at>=? ORDER BY start_at ASC LIMIT 1 FOR UPDATE"
            ),
            'operational.appointments.05.page_appointments.45' => (
                "UPDATE pi_blocks SET deleted_at=NOW(),deleted_by=?,cancel_reason=?,updated_at=NOW() WHERE clinic_id=? AND doctor_user_id=? AND deleted_at IS NULL AND start_at<=? AND end_at>=?"
            ),
'''
if sql_text.count(default_marker) != 1:
    raise SystemExit("SQL catalog default marker invalid")
sql.write_text(sql_text.replace(default_marker, sql_ops + default_marker, 1), encoding="utf-8")

# Runtime: one command gateway per POST, quick daily toggle, and contextual control in the daily crown row.
runtime = root / "app/Runtime/Appointments/AppointmentsRuntimeOperations05.php"
runtime_text = runtime.read_text(encoding="utf-8")
post_marker = '''            $postRedirect = function () use (
                $postDay,
                $postDoctor,
                $redirectBack,
            ): void {
    
                $redirectBack($postDay, $postDoctor);
            };
            if ($act === "agenda_note_delete") {'''
post_insert = '''            $postRedirect = function () use (
                $postDay,
                $postDoctor,
                $redirectBack,
            ): void {
    
                $redirectBack($postDay, $postDoctor);
            };
            $appointmentCommands = \\Prontoo\\Runtime\\Operational\\OperationalComposition::appointmentCommands();
            if (in_array($act, ["block_day", "unblock_day"], true)) {
                if (!in_array($role, ["recepcionista", "medico", "gerente"], true)) {
                    \\Prontoo\\Presentation\\SecurityAccess\\SecurityAccessPresentationOperations01::flash(
                        "Seu cargo não possui operação de bloqueio de dia.",
                        "bad",
                    );
                    $postRedirect();
                }
                if ($postDoctor <= 0 || !isset($docs[$postDoctor])) {
                    \\Prontoo\\Presentation\\SecurityAccess\\SecurityAccessPresentationOperations01::flash(
                        "Selecione um profissional válido para alterar o dia.",
                        "bad",
                    );
                    $postRedirect();
                }
                if ($role === "medico" && $postDoctor !== $uid) {
                    \\Prontoo\\Presentation\\SecurityAccess\\SecurityAccessPresentationOperations01::flash(
                        "O profissional só pode alterar o bloqueio da própria Agenda.",
                        "bad",
                    );
                    $postRedirect();
                }
                [$toggleDayStart, $toggleDayEnd] = \\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::app_local_day_utc_range(
                    $postDay,
                    $cid,
                    $c,
                );
                $toggleRanges = \\Prontoo\\Runtime\\Appointments\\AppointmentsRuntimeOperations01::doctor_work_ranges_for_day(
                    $cid,
                    $postDoctor,
                    $postDay,
                );
                $toggleStartHm = $toggleRanges ? (string) $toggleRanges[0][2] : "08:00";
                $toggleEndHm = $toggleRanges
                    ? (string) $toggleRanges[count($toggleRanges) - 1][3]
                    : "17:00";
                $toggleBlockStart = \\Prontoo\\Runtime\\Appointments\\AppointmentsRuntimeOperations03::normalize_db_datetime(
                    \\Prontoo\\Domain\\Appointments\\AppointmentsDomainOperations01::agenda_iso_local_value($postDay, $toggleStartHm),
                );
                $toggleBlockEnd = \\Prontoo\\Runtime\\Appointments\\AppointmentsRuntimeOperations03::normalize_db_datetime(
                    \\Prontoo\\Domain\\Appointments\\AppointmentsDomainOperations01::agenda_iso_local_value($postDay, $toggleEndHm),
                );
                $shouldBlockDay = $act === "block_day";
                try {
                    $dayBlockId = $appointmentCommands->setDayBlocked(
                        $cid,
                        $postDoctor,
                        $toggleDayStart,
                        $toggleDayEnd,
                        $toggleBlockStart,
                        $toggleBlockEnd,
                        $uid,
                        $shouldBlockDay,
                    );
                } catch (RuntimeException $error) {
                    \\Prontoo\\Presentation\\SecurityAccess\\SecurityAccessPresentationOperations01::flash(
                        $error->getMessage(),
                        "bad",
                    );
                    $postRedirect();
                }
                if ($shouldBlockDay) {
                    \\Prontoo\\Runtime\\ClinicConfig\\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "agenda_blocks");
                    \\Prontoo\\Runtime\\AuditActivity\\AuditActivityRuntimeOperations04::audit(
                        "agenda_dia_bloqueado",
                        "bloqueio",
                        $dayBlockId,
                        [
                            "doctor_user_id" => $postDoctor,
                            "day" => $postDay,
                            "start_at" => $toggleBlockStart,
                            "end_at" => $toggleBlockEnd,
                            "audit_body" => "Expediente inteiro bloqueado pela ação rápida da Agenda diária.",
                        ],
                    );
                    \\Prontoo\\Presentation\\SecurityAccess\\SecurityAccessPresentationOperations01::flash("Dia bloqueado.");
                } else {
                    \\Prontoo\\Runtime\\AuditActivity\\AuditActivityRuntimeOperations04::audit(
                        "agenda_dia_desbloqueado",
                        "bloqueio",
                        0,
                        [
                            "doctor_user_id" => $postDoctor,
                            "day" => $postDay,
                            "start_at" => $toggleBlockStart,
                            "end_at" => $toggleBlockEnd,
                            "audit_body" => "Bloqueio integral do expediente removido pela ação rápida da Agenda diária.",
                        ],
                    );
                    \\Prontoo\\Presentation\\SecurityAccess\\SecurityAccessPresentationOperations01::flash("Dia desbloqueado.");
                }
                $postRedirect();
            }
            if ($act === "agenda_note_delete") {'''
if runtime_text.count(post_marker) != 1:
    raise SystemExit("runtime POST insertion marker invalid")
runtime_text = runtime_text.replace(post_marker, post_insert, 1)
gateway = "\\Prontoo\\Runtime\\Operational\\OperationalComposition::appointmentCommands()->"
gateway_count = runtime_text.count(gateway)
if gateway_count < 4:
    raise SystemExit(f"unexpected appointment command gateway count: {gateway_count}")
runtime_text = runtime_text.replace(gateway, "$appointmentCommands->")

nav_marker = '''        $navDay =
            '<form method="get" class="agenda-crown-picker" aria-label="Selecionar dia e profissional">' .'''
nav_prelude = '''        $dayBlocked = false;
        if ($agendaView === "diario" && $agendaDoctor > 0 && $rows === []) {
            foreach ($blocks as $dayBlock) {
                if ((int) ($dayBlock["doctor_user_id"] ?? 0) !== $agendaDoctor) {
                    continue;
                }
                $dayBlockStartTs = $localTs((string) ($dayBlock["start_at"] ?? ""));
                $dayBlockEndTs = $localTs((string) ($dayBlock["end_at"] ?? ""));
                if ($dayBlockStartTs <= $workStartTs && $dayBlockEndTs >= $workEndTs) {
                    $dayBlocked = true;
                    break;
                }
            }
        }
        $dayBlockToggle = "";
        if (
            $agendaView === "diario" &&
            $agendaDoctor > 0 &&
            $rows === [] &&
            in_array($role, ["recepcionista", "medico", "gerente"], true)
        ) {
            $dayBlockAct = $dayBlocked ? "unblock_day" : "block_day";
            $dayBlockLabel = $dayBlocked ? "Desbloquear dia" : "Bloquear dia";
            $dayBlockIcon = $dayBlocked ? "event_available" : "event_busy";
            $dayBlockToggle =
                '<form method="post" class="agenda-day-toggle-form">' .
                \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="' .
                \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e($dayBlockAct) .
                '"><input type="hidden" name="return_day" value="' .
                \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e($day) .
                '"><input type="hidden" name="return_doctor" value="' .
                $agendaDoctor .
                '"><button type="submit" class="pagehead-control pagehead-control--secondary agenda-day-toggle-button">' .
                \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::icon($dayBlockIcon) .
                '<span>' .
                \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e($dayBlockLabel) .
                '</span></button></form>';
        }
        $navDay =
            '<div class="agenda-crown-row"><form method="get" class="agenda-crown-picker" aria-label="Selecionar dia e profissional">' .'''
if runtime_text.count(nav_marker) != 1:
    raise SystemExit("runtime nav insertion marker invalid")
runtime_text = runtime_text.replace(nav_marker, nav_prelude, 1)
nav_end = '''            \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::icon("chevron_right") .
            "</a>" .
            "</form>";'''
nav_end_new = '''            \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::icon("chevron_right") .
            "</a>" .
            "</form>" .
            $dayBlockToggle .
            "</div>";'''
if runtime_text.count(nav_end) != 1:
    raise SystemExit(f"runtime nav end marker invalid: {runtime_text.count(nav_end)}")
runtime_text = runtime_text.replace(nav_end, nav_end_new, 1)
runtime.write_text(runtime_text, encoding="utf-8")

# Application contract: expose and require tests for the new command.
contract_path = root / "app/application.test-contract.json"
contract = json.loads(contract_path.read_text(encoding="utf-8"))
case = next((item for item in contract.get("cases", []) if item.get("id") == "operational.appointment.commands"), None)
if not case:
    raise SystemExit("appointment command test contract not found")
if "setDayBlocked" not in case["entry_points"]:
    case["entry_points"].append("setDayBlocked")
for assertion in [
    "application.appointment_commands.day_block_toggle",
    "application.appointment_commands.day_block_guard",
]:
    if assertion not in case["assertions"]:
        case["assertions"].append(assertion)
contract_path.write_text(json.dumps(contract, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

# Fast unit/source contracts.
test_path = root / "tools/test-fast"
test_text = test_path.read_text(encoding="utf-8")n