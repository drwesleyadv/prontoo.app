from pathlib import Path
import hashlib
import json
import re
import shutil


def replace_once(path, old, new):
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected one occurrence, got {count}')
    p.write_text(text.replace(old, new, 1))


def replace_all(path, old, new, expected_min=1):
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count < expected_min:
        raise SystemExit(f'{path}: expected at least {expected_min} occurrences, got {count}')
    p.write_text(text.replace(old, new))


replace_once(
    'app/Database/schema.sql',
    "  `clinic_id` int UNSIGNED NOT NULL,\n  `note_date` bigint UNSIGNED NOT NULL DEFAULT '0',",
    "  `clinic_id` int UNSIGNED NOT NULL,\n  `doctor_user_id` int UNSIGNED DEFAULT NULL,\n  `note_date` bigint UNSIGNED NOT NULL DEFAULT '0',",
)
replace_once(
    'app/Database/schema.sql',
    "  KEY `idx_agenda_notes_timed` (`clinic_id`,`note_date`,`start_at`,`end_at`,`deleted_at`,`id`),",
    "  KEY `idx_agenda_notes_timed` (`clinic_id`,`doctor_user_id`,`note_date`,`start_at`,`end_at`,`deleted_at`,`id`),",
)
replace_once(
    'app/Database/schema.sql',
    "  KEY `idx_fk_agenda_notes_created_by` (`created_by`),",
    "  KEY `idx_fk_agenda_notes_doctor_user_id` (`doctor_user_id`),\n  KEY `idx_fk_agenda_notes_created_by` (`created_by`),",
)
replace_once(
    'app/Database/schema.sql',
    "  CONSTRAINT `fk_agenda_notes_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `pi_clinics` (`id`),\n  CONSTRAINT `fk_agenda_notes_created_by`",
    "  CONSTRAINT `fk_agenda_notes_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `pi_clinics` (`id`),\n  CONSTRAINT `fk_agenda_notes_doctor_user` FOREIGN KEY (`doctor_user_id`) REFERENCES `pi_users` (`id`) ON DELETE SET NULL,\n  CONSTRAINT `fk_agenda_notes_created_by`",
)

replace_once(
    'app/Infrastructure/Appointments/AppointmentsInfrastructureOperations01.php',
    '            \\Prontoo\\Infrastructure\\DatabaseSchema\\DatabaseSchemaInfrastructureOperations02::db_column_exists("pi_agenda_notes", "start_at") &&',
    '            \\Prontoo\\Infrastructure\\DatabaseSchema\\DatabaseSchemaInfrastructureOperations02::db_column_exists("pi_agenda_notes", "doctor_user_id") &&\n            \\Prontoo\\Infrastructure\\DatabaseSchema\\DatabaseSchemaInfrastructureOperations02::db_column_exists("pi_agenda_notes", "start_at") &&',
)

catalog3 = Path('app/Infrastructure/Operational/AppointmentsSqlCatalog03.php')
text = catalog3.read_text()
needle = '''            'operational.appointments.03.agenda_conflict_message.04' => (
                "SELECT u.name FROM pi_users u WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) LIMIT 1"
            ),'''
insert = needle + '''
            'operational.appointments.03.agenda_conflict_message.05' => (
                "SELECT id,doctor_user_id,start_at,end_at,created_by FROM pi_agenda_notes WHERE clinic_id=? AND doctor_user_id=? AND deleted_at IS NULL AND start_at IS NOT NULL AND end_at IS NOT NULL AND start_at < ? AND end_at > ? ORDER BY start_at ASC LIMIT 1" .
                (!empty($lockRows) ? " FOR UPDATE" : "")
            ),'''
if text.count(needle) != 1:
    raise SystemExit('AppointmentsSqlCatalog03 conflict insertion marker')
text = text.replace(needle, insert, 1)
old_timed = '''            'operational.appointments.03.agenda_notes_timed_visible_for_day.01' => (
                "SELECT n.id,n.note_date,n.start_at,n.end_at,n.content,n.target_scope,n.target_role,n.created_by,n.updated_by,n.deleted_by,n.created_at,n.updated_at,n.deleted_at,u.name AS created_by_name FROM pi_agenda_notes n LEFT JOIN pi_users u ON u.id=n.created_by WHERE n.clinic_id=? AND n.note_date=? AND n.start_at IS NOT NULL AND n.end_at IS NOT NULL AND n.deleted_at IS NULL AND (n.target_scope IN ('clinic','all')" .
                                ((int) $roleCount > 0
                                    ? " OR (n.target_scope='role' AND n.target_role IN (" . OperationalSequenceSql::placeholders((int) $roleCount) . "))"
                                    : "") .
                                ") ORDER BY n.start_at ASC,n.id DESC LIMIT 100"
            ),'''
new_timed = '''            'operational.appointments.03.agenda_notes_timed_visible_for_day.01' => (
                "SELECT n.id,n.doctor_user_id,n.note_date,n.start_at,n.end_at,n.content,n.target_scope,n.target_role,n.created_by,n.updated_by,n.deleted_by,n.created_at,n.updated_at,n.deleted_at,u.name AS created_by_name FROM pi_agenda_notes n LEFT JOIN pi_users u ON u.id=n.created_by WHERE n.clinic_id=? AND n.doctor_user_id=? AND n.note_date=? AND n.start_at IS NOT NULL AND n.end_at IS NOT NULL AND n.deleted_at IS NULL ORDER BY n.start_at ASC,n.id DESC LIMIT 100"
            ),'''
if text.count(old_timed) != 1:
    raise SystemExit('AppointmentsSqlCatalog03 timed query marker')
text = text.replace(old_timed, new_timed, 1)
catalog3.write_text(text)

replace_once(
    'app/Infrastructure/Operational/AppointmentsSqlCatalog05.php',
    '                "SELECT id,note_date,start_at,end_at,created_by FROM pi_agenda_notes WHERE id=? AND clinic_id=? AND deleted_at IS NULL LIMIT 1"',
    '                "SELECT id,doctor_user_id,note_date,start_at,end_at,created_by FROM pi_agenda_notes WHERE id=? AND clinic_id=? AND deleted_at IS NULL LIMIT 1"',
)
replace_once(
    'app/Infrastructure/Operational/AppointmentsSqlCatalog05.php',
    '                "INSERT INTO pi_agenda_notes (clinic_id,note_date,start_at,end_at,content,target_scope,target_role,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())"',
    '                "INSERT INTO pi_agenda_notes (clinic_id,doctor_user_id,note_date,start_at,end_at,content,target_scope,target_role,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"',
)

service_path = Path('app/Application/Operational/AppointmentCommandService.php')
service = service_path.read_text()
start = service.index('    public function visibleTimedAgendaNotes(')
end = service.index('    public function removeAgendaNote(', start)
new_methods = r'''    public function visibleTimedAgendaNotes(
        int $clinicId,
        int $doctorUserId,
        string $day,
    ): array {
        if (
            $clinicId <= 0 ||
            $doctorUserId <= 0 ||
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1
        ) {
            return [];
        }
        return $this->data->result(
            'operational.appointments.03.agenda_notes_timed_visible_for_day.01',
            [$clinicId, $doctorUserId, $day],
        )->fetchAll();
    }

    public function createAgendaNote(
        int $clinicId,
        ?int $doctorUserId,
        string $noteDate,
        ?string $startAt,
        ?string $endAt,
        string $content,
        string $targetScope,
        ?string $targetRole,
        int $userId,
        Closure $conflictMessage,
    ): int {
        $timed = $startAt !== null || $endAt !== null;
        if (($startAt === null) !== ($endAt === null)) {
            throw new RuntimeException('Informe os horários de início e fim da pré-reserva.');
        }
        if ($timed && ($doctorUserId === null || $doctorUserId <= 0)) {
            throw new RuntimeException('Selecione um profissional para criar uma pré-reserva com horário.');
        }
        if (!$timed) {
            $doctorUserId = null;
        }
        return (int) $this->data->atomic(function () use (
            $clinicId,
            $doctorUserId,
            $noteDate,
            $startAt,
            $endAt,
            $content,
            $targetScope,
            $targetRole,
            $userId,
            $conflictMessage,
            $timed,
        ): int {
            if ($timed) {
                $this->data->result(
                    'operational.appointments.05.page_appointments.35',
                    [$clinicId],
                );
                $conflict = (string) $conflictMessage(
                    $clinicId,
                    $doctorUserId,
                    (string) $startAt,
                    (string) $endAt,
                    'pré-reserva',
                    0,
                    0,
                );
                if ($conflict !== '') {
                    throw new RuntimeException($conflict);
                }
            }
            $this->data->result(
                'operational.appointments.05.page_appointments.03',
                [
                    $clinicId,
                    $doctorUserId,
                    $noteDate,
                    $startAt,
                    $endAt,
                    $content,
                    $targetScope,
                    $targetRole,
                    $userId,
                    $userId,
                ],
            );
            return $this->data->lastInsertId();
        });
    }

'''
service = service[:start] + new_methods + service[end:]
marker = """            $existing = $this->data->row(
                'operational.appointments.05.page_appointments.44',
                [$clinicId, $doctorUserId, $blockStartAt, $blockEndAt],
            );"""
reservation_guard = """            $reservation = $this->data->row(
                'operational.appointments.03.agenda_conflict_message.05',
                [$clinicId, $doctorUserId, $blockEndAt, $blockStartAt],
                ['lockRows' => true],
            );
            if ($reservation) {
                throw new RuntimeException('O dia já possui pré-reserva neste intervalo para este profissional.');
            }
""" + marker
if service.count(marker) != 1:
    raise SystemExit('AppointmentCommandService day block marker')
service = service.replace(marker, reservation_guard, 1)
service_path.write_text(service)

runtime3_path = Path('app/Runtime/Appointments/AppointmentsRuntimeOperations03.php')
runtime3 = runtime3_path.read_text()
fn_start = runtime3.index('    public static function agenda_conflict_message(')
fn_end = runtime3.find('\n    public static function ', fn_start + 10)
if fn_end < 0:
    fn_end = len(runtime3)
segment = runtime3[fn_start:fn_end]
return_pos = segment.rfind('        return null;')
if return_pos < 0:
    raise SystemExit('agenda_conflict_message return marker')
reservation_conflict = r'''        if ($doctorId !== null) {
            $reservation = \Prontoo\Runtime\Operational\OperationalComposition::appointments()->row(
                'operational.appointments.03.agenda_conflict_message.05',
                [$cid, $doctorId, $endAt, $startAt],
                ['lockRows' => $lock],
            );
            if ($reservation) {
                $doctorLabel = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name(
                    \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_doctor_name($cid, $doctorId),
                );
                $reservedPeriod =
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $reservation["start_at"], $cid) .
                    "–" .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_time_br((string) $reservation["end_at"], $cid);
                return
                    "Não é possível concluir " .
                    $operation .
                    ": o período " .
                    $period .
                    " na agenda de " .
                    $doctorLabel .
                    " se sobrepõe à pré-reserva " .
                    $reservedPeriod .
                    ". Remova a anotação de pré-reserva ou escolha outro horário.";
            }
        }
'''
segment = segment[:return_pos] + reservation_conflict + segment[return_pos:]
runtime3 = runtime3[:fn_start] + segment + runtime3[fn_end:]
runtime3_path.write_text(runtime3)

runtime5_path = Path('app/Runtime/Appointments/AppointmentsRuntimeOperations05.php')
runtime5 = runtime5_path.read_text()
old_call = '''                $noteId = $appointmentCommands->createAgendaNote(
                    $cid,
                    $noteDate,
                    $noteStartAt,
                    $noteEndAt,
                    $content,
                    $targetScope,
                    $targetRole,
                    $uid,
                );'''
new_call = r'''                $noteDoctorId = null;
                if ($noteStartAt !== null) {
                    if ($postDoctor <= 0 || !isset($docs[$postDoctor])) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "Selecione um profissional para criar uma pré-reserva com horário.",
                            "bad",
                        );
                        $postRedirect();
                    }
                    $noteDoctorId = $postDoctor;
                }
                try {
                    $noteId = $appointmentCommands->createAgendaNote(
                        $cid,
                        $noteDoctorId,
                        $noteDate,
                        $noteStartAt,
                        $noteEndAt,
                        $content,
                        $targetScope,
                        $targetRole,
                        $uid,
                        static fn(...$arguments): ?string =>
                            \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::agenda_conflict_message(...$arguments),
                    );
                } catch (RuntimeException $error) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        $error->getMessage(),
                        "bad",
                    );
                    $postRedirect();
                }'''
if runtime5.count(old_call) != 1:
    raise SystemExit('Runtime05 create note call marker')
runtime5 = runtime5.replace(old_call, new_call, 1)
runtime5 = runtime5.replace(
    '                        "note_date" => $noteDate,\n                        "start_at" => $noteStartAt,',
    '                        "note_date" => $noteDate,\n                        "doctor_user_id" => $noteDoctorId,\n                        "start_at" => $noteStartAt,',
    1,
)
runtime5 = runtime5.replace(
    '                        : "Anotação registrada no intervalo informado.",',
    '                        : "Pré-reserva registrada. O intervalo ficou bloqueado para novos agendamentos deste profissional.",',
    1,
)
old_fetch = r'''        $timedAgendaNotes =
            $agendaView === "diario"
                ? $appointmentCommands->visibleTimedAgendaNotes(
                    $cid,
                    $day,
                    \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_note_role_codes($c),
                )
                : [];'''
new_fetch = r'''        $timedNoteRoleCodes = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::agenda_note_role_codes($c);
        $timedAgendaNotes =
            $agendaView === "diario" && $agendaDoctor > 0
                ? $appointmentCommands->visibleTimedAgendaNotes(
                    $cid,
                    $agendaDoctor,
                    $day,
                )
                : [];'''
if runtime5.count(old_fetch) != 1:
    raise SystemExit('Runtime05 timed note fetch marker')
runtime5 = runtime5.replace(old_fetch, new_fetch, 1)
occupancy_marker = '        $occupancyPct = min(\n'
if runtime5.count(occupancy_marker) != 1:
    raise SystemExit('Runtime05 occupancy marker')
timed_busy = r'''        foreach ($timedAgendaNotes as $timedAgendaNote) {
            $sTs = max($workStartTs, $localTs((string) ($timedAgendaNote["start_at"] ?? "")));
            $eTs = min($workEndTs, $localTs((string) ($timedAgendaNote["end_at"] ?? "")));
            if ($eTs > $sTs) {
                $busyMinutes += (int) ceil(($eTs - $sTs) / 60);
                $busy[] = [$sTs, $eTs];
            }
        }
'''
runtime5 = runtime5.replace(occupancy_marker, timed_busy + occupancy_marker, 1)
runtime5 = runtime5.replace(
    "'<article class=\"agenda-day-event-note\" data-agenda-note style=\"--event-top:' .",
    "'<article class=\"agenda-day-event agenda-day-event-note\" data-agenda-note data-agenda-reservation style=\"--event-top:' .",
    1,
)
render_start = runtime5.index('        $timedNoteHtml = "";')
render_end = runtime5.index('        if ($eventHtml === "" && $timedNoteHtml === "" && !$dayBlocked)', render_start)
render = runtime5[render_start:render_end]
creator_marker = '            $creatorName = mb_trim((string) ($timedAgendaNote["created_by_name"] ?? ""));\n'
if render.count(creator_marker) != 1:
    raise SystemExit('Runtime05 timed note creator marker')
privacy = r'''            $noteScope = mb_trim((string) ($timedAgendaNote["target_scope"] ?? "clinic"));
            $noteRole = mb_trim((string) ($timedAgendaNote["target_role"] ?? ""));
            $noteOwner = (int) ($timedAgendaNote["created_by"] ?? 0) === $uid;
            $canViewTimedNote =
                $noteOwner ||
                in_array($noteScope, ["clinic", "all"], true) ||
                ($noteScope === "role" && $noteRole !== "" && in_array($noteRole, $timedNoteRoleCodes, true));
            $creatorName = $canViewTimedNote
                ? mb_trim((string) ($timedAgendaNote["created_by_name"] ?? ""))
                : "";
'''
render = render.replace(creator_marker, privacy, 1)
render = render.replace(
    '            if ((int) ($timedAgendaNote["created_by"] ?? 0) === $uid) {',
    '            if ($noteOwner) {',
    1,
)
render = render.replace(
    "                '\" title=\"Anotação · ' .",
    "                '\" title=\"Pré-reserva · ' .",
    1,
)
render = render.replace(
    "                '<span>Anotação</span></strong><small>' .\n                \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e((string) ($timedAgendaNote[\"content\"] ?? \"\")) .\n                '</small><em>' .\n                ($creatorName !== \"\"\n                    ? \"Por \" . \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e(\\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::first_name($creatorName))\n                    : \"Aviso da Agenda\") .",
    "                '<span>Pré-reserva</span></strong><small>' .\n                \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e(\n                    $canViewTimedNote\n                        ? (string) ($timedAgendaNote[\"content\"] ?? \"\")\n                        : \"Horário pré-reservado para negociação em andamento.\",\n                ) .\n                '</small><em>' .\n                ($canViewTimedNote\n                    ? ($creatorName !== \"\"\n                        ? \"Por \" . \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e(\\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::first_name($creatorName))\n                        : \"Pré-reserva da Agenda\")\n                    : \"Conteúdo restrito\") .",
    1,
)
runtime5 = runtime5[:render_start] + render + runtime5[render_end:]
runtime5_path.write_text(runtime5)

replace_once(
    'app/Runtime/Appointments/AppointmentsRuntimeOperations04.php',
    'Deixe os dois horários em branco para manter a anotação como dia todo. Se informar um intervalo, a anotação aparecerá posicionada nessa faixa da Agenda diária.',
    'Deixe os dois horários em branco para manter a anotação como dia todo. Ao informar um intervalo, a anotação vira uma pré-reserva do profissional selecionado e bloqueia essa faixa para novos agendamentos.',
)

replace_once(
    'tools/test-fast',
    "        'operational.appointments.05.page_appointments.01' => [['id' => 702, 'note_date' => '2026-08-11', 'start_at' => '2026-08-11 13:00:00', 'end_at' => '2026-08-11 13:30:00', 'created_by' => 11]],",
    "        'operational.appointments.05.page_appointments.01' => [['id' => 702, 'doctor_user_id' => 91, 'note_date' => '2026-08-11', 'start_at' => '2026-08-11 13:00:00', 'end_at' => '2026-08-11 13:30:00', 'created_by' => 11]],",
)
replace_once(
    'tools/test-fast',
    "        'operational.appointments.03.agenda_notes_timed_visible_for_day.01' => [['id' => 702, 'note_date' => '2026-08-11', 'start_at' => '2026-08-11 13:00:00', 'end_at' => '2026-08-11 13:30:00', 'content' => 'Aviso', 'created_by' => 11]],",
    "        'operational.appointments.03.agenda_notes_timed_visible_for_day.01' => [['id' => 702, 'doctor_user_id' => 91, 'note_date' => '2026-08-11', 'start_at' => '2026-08-11 13:00:00', 'end_at' => '2026-08-11 13:30:00', 'content' => 'Aviso', 'created_by' => 11]],",
)
replace_once(
    'tools/test-fast',
    "$visibleTimedAgendaNotes = $appointmentCommands->visibleTimedAgendaNotes(7, '2026-08-11', ['medico']);",
    "$visibleTimedAgendaNotes = $appointmentCommands->visibleTimedAgendaNotes(7, 91, '2026-08-11');",
)
replace_once(
    'tools/test-fast',
    '''$createdAgendaNoteId = $appointmentCommands->createAgendaNote(
    7,
    '2026-08-11',
    '2026-08-11 13:00:00',
    '2026-08-11 13:30:00',
    'Aviso',
    'role',
    'medico',
    11,
);''',
    '''$createdAgendaNoteId = $appointmentCommands->createAgendaNote(
    7,
    91,
    '2026-08-11',
    '2026-08-11 13:00:00',
    '2026-08-11 13:30:00',
    'Aviso',
    'role',
    'medico',
    11,
    $noConflict,
);
$agendaNoteConflict = '';
try {
    $appointmentCommands->createAgendaNote(
        7,
        91,
        '2026-08-11',
        '2026-08-11 13:00:00',
        '2026-08-11 13:30:00',
        'Reserva concorrente',
        'clinic',
        null,
        11,
        static fn(...$arguments): string => 'Horário já pré-reservado.',
    );
} catch (RuntimeException $error) {
    $agendaNoteConflict = $error->getMessage();
}''',
)
replace_once(
    'tools/test-fast',
    "$same(701, $createdAgendaNoteId, 'application.appointment_commands.note_created');",
    "$same(701, $createdAgendaNoteId, 'application.appointment_commands.note_created');\n$same('Horário já pré-reservado.', $agendaNoteConflict, 'application.appointment_commands.note_conflict');",
)

contract_path = Path('app/application.test-contract.json')
contract_text = contract_path.read_text()
needle = '"application.appointment_commands.note_created", "application.appointment_commands.note_removed"'
if needle not in contract_text:
    raise SystemExit('application test contract note assertion marker')
contract_text = contract_text.replace(
    needle,
    '"application.appointment_commands.note_created", "application.appointment_commands.note_conflict", "application.appointment_commands.note_removed"',
    1,
)
contract_path.write_text(contract_text)

schema = Path('app/Database/schema.sql').read_text()
match = re.search(r'CREATE TABLE `pi_agenda_notes` \(.*?\n\) ENGINE=InnoDB[^;]*;', schema, re.S)
if not match:
    raise SystemExit('pi_agenda_notes block missing')
block = re.sub(
    r'`Seq` bigint UNSIGNED (?:DEFAULT NULL|NOT NULL DEFAULT \(UUID_SHORT\(\)\))',
    '`Seq` <native-seq>',
    match.group(0),
)
block = re.sub(r'\s+', ' ', block).strip()
schema_contract_path = Path('app/Database/operational-schema.contract.json')
schema_contract = json.loads(schema_contract_path.read_text())
schema_contract['tables']['pi_agenda_notes'] = hashlib.sha256(block.encode()).hexdigest()
schema_contract_path.write_text(json.dumps(schema_contract, ensure_ascii=False, indent=4) + '\n')

version_path = Path('version.json')
version = json.loads(version_path.read_text())
version['asset_version'] = '1.9.15.9'
version['build'] = '1.9.15.9-timed-agenda-reservations'
version['notes'] = 'Permite anotações de dia todo ou pré-reservas com horário vinculadas ao profissional selecionado; pré-reservas bloqueiam a faixa contra novos agendamentos e preservam a visibilidade do conteúdo.'
version['database_changes'] = True
version['schema_changes'] = True
version['logic_changes'] = True
version['visual_changes'] = True
version['changelog'] = {
    'title': 'Agenda diária: anotações com horário funcionam como pré-reserva',
    'items': [
        'Mantém anotações sem horário como anotações de dia todo no formato atual',
        'Permite informar início e fim opcionais para transformar a anotação em pré-reserva',
        'Vincula a pré-reserva ao profissional selecionado na visão diária',
        'Bloqueia conflitos com consultas, bloqueios e outras pré-reservas no servidor',
        'Considera pré-reservas na ocupação e nos horários livres da Agenda diária',
        'Preserva o conteúdo conforme a visibilidade e mostra marcador genérico quando restrito',
    ],
}
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + '\n')

visual_path = Path('app/presentation.visual-contract.json')
visual = json.loads(visual_path.read_text())
visual['version'] = '1.9.15.9'
visual_path.write_text(json.dumps(visual, ensure_ascii=False, indent=4) + '\n')

replace_all('docs/index.md', '1.9.15.8', '1.9.15.9')
replace_all('design/styles/application.css', 'pix-1.9.15.8.svg', 'pix-1.9.15.9.svg')

assets = Path('public/assets')
for stem, suffix in [
    ('app-icon-', '.png'),
    ('prontoo-mark-', '.png'),
    ('favicon-', '.png'),
    ('favicon-', '.ico'),
    ('pix-', '.svg'),
]:
    old = assets / f'{stem}1.9.15.8{suffix}'
    new = assets / f'{stem}1.9.15.9{suffix}'
    if not old.exists():
        raise SystemExit(f'missing source asset: {old}')
    shutil.copyfile(old, new)
    old.unlink()
