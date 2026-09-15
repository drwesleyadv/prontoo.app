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
typed_method = r'''    public function timedAgendaReservationConflict(
        int $clinicId,
        int $doctorUserId,
        string $startAt,
        string $endAt,
        bool $lockRows,
    ): ?array {
        if ($clinicId <= 0 || $doctorUserId <= 0) {
            return null;
        }
        return $this->data->row(
            'operational.appointments.03.agenda_conflict_message.05',
            [$clinicId, $doctorUserId, $endAt, $startAt],
            ['lockRows' => $lockRows],
        );
    }

'''
if service.count(marker) != 1:
    raise SystemExit('AppointmentCommandService create note marker')
service = service.replace(marker, typed_method + marker, 1)
service_path.write_text(service)

runtime_path = Path('app/Runtime/Appointments/AppointmentsRuntimeOperations03.php')
runtime = runtime_path.read_text()
old = r'''            $reservation = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->row(
                'operational.appointments.03.agenda_conflict_message.05',
                [$cid, $doctorId, $endAt, $startAt],
                ['lockRows' => $lock],
            );'''
new = r'''            $reservation = \Prontoo\Runtime\Operational\OperationalComposition::appointmentCommands()->timedAgendaReservationConflict(
                $cid,
                $doctorId,
                $startAt,
                $endAt,
                $lock,
            );'''
if runtime.count(old) != 1:
    raise SystemExit('Runtime03 reservation gateway marker')
runtime_path.write_text(runtime.replace(old, new, 1))

replace_once(
    'tools/test-fast',
    "        'operational.appointments.05.page_appointments.27' => [['id' => 701]],",
    "        'operational.appointments.05.page_appointments.27' => [['id' => 701]],\n        'operational.appointments.03.agenda_conflict_message.05' => [],",
)
replace_once(
    'tools/test-fast',
    "$visibleTimedAgendaNotes = $appointmentCommands->visibleTimedAgendaNotes(7, 91, '2026-08-11');",
    "$visibleTimedAgendaNotes = $appointmentCommands->visibleTimedAgendaNotes(7, 91, '2026-08-11');\n$reservationConflict = $appointmentCommands->timedAgendaReservationConflict(7, 91, '2026-08-11 13:00:00', '2026-08-11 13:30:00', true);",
)
replace_once(
    'tools/test-fast',
    "$same(702, (int) ($visibleTimedAgendaNotes[0]['id'] ?? 0), 'application.appointment_commands.timed_notes_visible');",
    "$same(702, (int) ($visibleTimedAgendaNotes[0]['id'] ?? 0), 'application.appointment_commands.timed_notes_visible');\n$same(null, $reservationConflict, 'application.appointment_commands.reservation_conflict_absent');",
)

contract_path = Path('app/application.test-contract.json')
contract = contract_path.read_text()
old_entries = '"entry_points": ["agendaNoteById", "visibleTimedAgendaNotes", "createAgendaNote", "removeAgendaNote", "createBlock", "updateBlock", "removeBlock", "setDayBlocked", "updateAppointment", "createAppointment", "createAppointmentBatch"]'
new_entries = '"entry_points": ["agendaNoteById", "visibleTimedAgendaNotes", "timedAgendaReservationConflict", "createAgendaNote", "removeAgendaNote", "createBlock", "updateBlock", "removeBlock", "setDayBlocked", "updateAppointment", "createAppointment", "createAppointmentBatch"]'
if contract.count(old_entries) != 1:
    raise SystemExit('application contract entry point marker')
contract = contract.replace(old_entries, new_entries, 1)
old_assertions = '"application.appointment_commands.note_created", "application.appointment_commands.note_conflict", "application.appointment_commands.note_removed"'
new_assertions = '"application.appointment_commands.note_created", "application.appointment_commands.note_conflict", "application.appointment_commands.reservation_conflict_absent", "application.appointment_commands.note_removed"'
if contract.count(old_assertions) != 1:
    raise SystemExit('application contract assertion marker')
contract_path.write_text(contract.replace(old_assertions, new_assertions, 1))
