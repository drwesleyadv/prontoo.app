from pathlib import Path

root = Path(__file__).resolve().parents[1]

def replace_once(path, old, new, label):
    path = root / path
    text = path.read_text(encoding='utf-8')
    if text.count(old) != 1:
        raise SystemExit(f'{label}: expected once, found {text.count(old)}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')

replace_once(
    'app/Application/Operational/AppointmentCommandService.php',
    '''        int $userId,
        bool $blocked,
    ): int {''',
    '''        int $userId,
        bool $blocked,
        bool $canRemoveAnyBlock,
    ): int {''',
    'service signature',
)
replace_once(
    'app/Application/Operational/AppointmentCommandService.php',
    '''            $userId,
            $blocked,
        ): int {''',
    '''            $userId,
            $blocked,
            $canRemoveAnyBlock,
        ): int {''',
    'service closure capture',
)
replace_once(
    'app/Application/Operational/AppointmentCommandService.php',
    '''            $existing = $this->data->row(
                'operational.appointments.05.page_appointments.44',
                [$clinicId, $doctorUserId, $blockStartAt, $blockEndAt],
            );
            if (!$blocked) {''',
    '''            $existing = $this->data->row(
                'operational.appointments.05.page_appointments.44',
                [$clinicId, $doctorUserId, $blockStartAt, $blockEndAt],
            );
            if (
                !$blocked &&
                $existing &&
                !$canRemoveAnyBlock &&
                (int) ($existing['created_by'] ?? 0) !== $userId
            ) {
                throw new RuntimeException('Esse bloqueio não foi criado por você.');
            }
            if (!$blocked) {''',
    'service ownership guard',
)
replace_once(
    'app/Infrastructure/Operational/AppointmentsSqlCatalog05.php',
    '"SELECT id FROM pi_blocks WHERE clinic_id=? AND doctor_user_id=? AND deleted_at IS NULL AND start_at<=? AND end_at>=? ORDER BY start_at ASC LIMIT 1 FOR UPDATE"',
    '"SELECT id,created_by FROM pi_blocks WHERE clinic_id=? AND doctor_user_id=? AND deleted_at IS NULL AND start_at<=? AND end_at>=? ORDER BY start_at ASC LIMIT 1 FOR UPDATE"',
    'day block owner SQL',
)
replace_once(
    'app/Runtime/Appointments/AppointmentsRuntimeOperations05.php',
    '''                        $uid,
                        $shouldBlockDay,
                    );''',
    '''                        $uid,
                        $shouldBlockDay,
                        $role === "gerente",
                    );''',
    'runtime service call',
)
replace_once(
    'app/Runtime/Appointments/AppointmentsRuntimeOperations05.php',
    '''        $dayBlocked = false;
        if ($agendaView === "diario" && $agendaDoctor > 0 && $rows === []) {''',
    '''        $dayBlocked = false;
        $dayBlockCreatorId = 0;
        if ($agendaView === "diario" && $agendaDoctor > 0 && $rows === []) {''',
    'runtime day block creator init',
)
replace_once(
    'app/Runtime/Appointments/AppointmentsRuntimeOperations05.php',
    '''                    $dayBlocked = true;
                    break;''',
    '''                    $dayBlocked = true;
                    $dayBlockCreatorId = (int) ($dayBlock["created_by"] ?? 0);
                    break;''',
    'runtime day block creator read',
)
replace_once(
    'app/Runtime/Appointments/AppointmentsRuntimeOperations05.php',
    '''            $rows === [] &&
            in_array($role, ["recepcionista", "medico", "gerente"], true)
        ) {''',
    '''            $rows === [] &&
            in_array($role, ["recepcionista", "medico", "gerente"], true) &&
            (!$dayBlocked || $role === "gerente" || $dayBlockCreatorId === $uid)
        ) {''',
    'runtime day toggle ownership visibility',
)

test_path = root / 'tools/test-fast'
test = test_path.read_text(encoding='utf-8')
needle = '''    11,
    true,
);
$dayUnblockResult = $appointmentCommands->setDayBlocked('''
if test.count(needle) != 1:
    raise SystemExit('test first day block call marker invalid')
test = test.replace(needle, '''    11,
    true,
    true,
);
$dayUnblockResult = $appointmentCommands->setDayBlocked(''', 1)
needle = '''    11,
    false,
);
$appointmentPort->rowsByOperation['operational.appointments.05.page_appointments.43']'''
if test.count(needle) != 1:
    raise SystemExit('test day unblock call marker invalid')
test = test.replace(needle, '''    11,
    false,
    true,
);
$appointmentPort->rowsByOperation['operational.appointments.05.page_appointments.43']''', 1)
needle = '''        11,
        true,
    );
} catch (RuntimeException $error) {
    $dayAppointmentGuard = $error->getMessage();
}
unset($appointmentPort->rowsByOperation['operational.appointments.05.page_appointments.43']);'''
if test.count(needle) != 1:
    raise SystemExit('test appointment guard call marker invalid')
test = test.replace(needle, '''        11,
        true,
        true,
    );
} catch (RuntimeException $error) {
    $dayAppointmentGuard = $error->getMessage();
}
unset($appointmentPort->rowsByOperation['operational.appointments.05.page_appointments.43']);
$appointmentPort->rowsByOperation['operational.appointments.05.page_appointments.44'] = [['id' => 702, 'created_by' => 99]];
$dayBlockOwnershipGuard = '';
try {
    $appointmentCommands->setDayBlocked(
        7,
        91,
        '2026-08-12 04:00:00',
        '2026-08-13 04:00:00',
        '2026-08-12 12:00:00',
        '2026-08-12 21:00:00',
        11,
        false,
        false,
    );
} catch (RuntimeException $error) {
    $dayBlockOwnershipGuard = $error->getMessage();
}
unset($appointmentPort->rowsByOperation['operational.appointments.05.page_appointments.44']);''', 1)
needle = '''$same('O dia já possui consulta marcada para este profissional.', $dayAppointmentGuard, 'application.appointment_commands.day_block_guard');
'''
if test.count(needle) != 1:
    raise SystemExit('test day guard assertion marker invalid')
test = test.replace(needle, needle + "$same('Esse bloqueio não foi criado por você.', $dayBlockOwnershipGuard, 'application.appointment_commands.day_block_ownership');\n", 1)
test_path.write_text(test, encoding='utf-8')

replace_once(
    'app/application.test-contract.json',
    '"application.appointment_commands.day_block_guard", "application.appointment_commands.batch_ids"',
    '"application.appointment_commands.day_block_guard", "application.appointment_commands.day_block_ownership", "application.appointment_commands.batch_ids"',
    'ownership assertion contract',
)
