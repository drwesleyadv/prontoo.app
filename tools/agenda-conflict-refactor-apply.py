from pathlib import Path


def replace_once(path, old, new):
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected one occurrence, got {count}')
    p.write_text(text.replace(old, new, 1))


service_path = Path('app/Application/Operational/AppointmentCommandService.php')
service = service_path.read_text()
marker = '    public function createAgendaNote(\n'
method = r'''    public function agendaBlockConflict(
        int $clinicId,
        ?int $doctorUserId,
        string $startAt,
        string $endAt,
        int $ignoreBlockId,
        bool $lockRows,
    ): ?array {
        if ($clinicId <= 0) {
            return null;
        }
        $doctorUserId = $doctorUserId !== null && $doctorUserId > 0 ? $doctorUserId : null;
        $params = [$clinicId, $endAt, $startAt];
        if ($ignoreBlockId > 0) {
            $params[] = $ignoreBlockId;
        }
        if ($doctorUserId !== null) {
            $params[] = $doctorUserId;
        }
        return $this->data->row(
            'operational.appointments.03.agenda_conflict_message.03',
            $params,
            [
                'ignoreBlock' => $ignoreBlockId > 0,
                'doctorScoped' => $doctorUserId !== null,
                'lockRows' => $lockRows,
            ],
        );
    }

'''
if service.count(marker) != 1:
    raise SystemExit('AppointmentCommandService create note marker')
service_path.write_text(service.replace(marker, method + marker, 1))

runtime_path = Path('app/Runtime/Appointments/AppointmentsRuntimeOperations03.php')
runtime = runtime_path.read_text()
old = r'''        $blockParams = [$cid, $endAt, $startAt];
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
        ]);'''
new = r'''        $block = \Prontoo\Runtime\Operational\OperationalComposition::appointmentCommands()->agendaBlockConflict(
            $cid,
            $doctorId,
            $startAt,
            $endAt,
            $ignoreBlockId,
            $lock,
        );'''
if runtime.count(old) != 1:
    raise SystemExit('Runtime03 block conflict marker')
runtime_path.write_text(runtime.replace(old, new, 1))

test_path = Path('tools/test-fast')
test = test_path.read_text()
old_call = "$reservationConflict = $appointmentCommands->timedAgendaReservationConflict(7, 91, '2026-08-11 13:00:00', '2026-08-11 13:30:00', true);"
new_call = old_call + "\n$blockConflict = $appointmentCommands->agendaBlockConflict(7, 91, '2026-08-11 13:00:00', '2026-08-11 13:30:00', 0, true);"
if test.count(old_call) != 1:
    raise SystemExit('test-fast reservation conflict call marker')
test = test.replace(old_call, new_call, 1)
old_assert = "$same(null, $reservationConflict, 'application.appointment_commands.reservation_conflict_absent');"
new_assert = old_assert + "\n$same(null, $blockConflict, 'application.appointment_commands.block_conflict_absent');"
if test.count(old_assert) != 1:
    raise SystemExit('test-fast reservation conflict assertion marker')
test_path.write_text(test.replace(old_assert, new_assert, 1))

contract_path = Path('app/application.test-contract.json')
contract = contract_path.read_text()
old_entries = '"entry_points": ["agendaNoteById", "visibleTimedAgendaNotes", "timedAgendaReservationConflict", "createAgendaNote", "removeAgendaNote", "createBlock", "updateBlock", "removeBlock", "setDayBlocked", "updateAppointment", "createAppointment", "createAppointmentBatch"]'
new_entries = '"entry_points": ["agendaNoteById", "visibleTimedAgendaNotes", "timedAgendaReservationConflict", "agendaBlockConflict", "createAgendaNote", "removeAgendaNote", "createBlock", "updateBlock", "removeBlock", "setDayBlocked", "updateAppointment", "createAppointment", "createAppointmentBatch"]'
if contract.count(old_entries) != 1:
    raise SystemExit('application contract entry marker')
contract = contract.replace(old_entries, new_entries, 1)
old_assertions = '"application.appointment_commands.reservation_conflict_absent", "application.appointment_commands.note_removed"'
new_assertions = '"application.appointment_commands.reservation_conflict_absent", "application.appointment_commands.block_conflict_absent", "application.appointment_commands.note_removed"'
if contract.count(old_assertions) != 1:
    raise SystemExit('application contract assertion marker')
contract_path.write_text(contract.replace(old_assertions, new_assertions, 1))
