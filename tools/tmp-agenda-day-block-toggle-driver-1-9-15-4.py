from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
core_path = root / "tools/tmp-agenda-day-block-toggle-1-9-15-4.py"
core = core_path.read_text(encoding="utf-8")
core = core.replace('test_text = test_path.read_text(encoding="utf-8")n', 'test_text = test_path.read_text(encoding="utf-8")')
core = core.replace(
    'contract_path.write_text(json.dumps(contract, ensure_ascii=False, indent=4) + "\\n", encoding="utf-8")',
    'pass',
)
exec(compile(core, str(core_path), "exec"), {"__file__": str(core_path), "__name__": "__main__"})

def replace_once(path: Path, old: str, new: str, label: str) -> None:
    text = path.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    path.write_text(text.replace(old, new, 1), encoding="utf-8")

contract_path = root / "app/application.test-contract.json"
replace_once(
    contract_path,
    '"entry_points": ["createBlock", "updateBlock", "removeBlock", "updateAppointment", "createAppointment", "createAppointmentBatch"],',
    '"entry_points": ["createBlock", "updateBlock", "removeBlock", "setDayBlocked", "updateAppointment", "createAppointment", "createAppointmentBatch"],',
    "appointment command entry points",
)
replace_once(
    contract_path,
    '"assertions": ["application.appointment_commands.ids", "application.appointment_commands.remove_block", "application.appointment_commands.batch_ids", "application.appointment_commands.batch_financial_sync", "application.appointment_commands.batch_overlap", "application.appointment_commands.conflict", "application.appointment_commands.financial_sync"]',
    '"assertions": ["application.appointment_commands.ids", "application.appointment_commands.remove_block", "application.appointment_commands.day_block_toggle", "application.appointment_commands.day_block_guard", "application.appointment_commands.batch_ids", "application.appointment_commands.batch_financial_sync", "application.appointment_commands.batch_overlap", "application.appointment_commands.conflict", "application.appointment_commands.financial_sync"]',
    "appointment command assertions",
)

test_path = root / "tools/test-fast"
test = test_path.read_text(encoding="utf-8")
removed_marker = '''$removedBlock = $appointmentCommands->removeBlock(
    7,
    701,
    11,
    'Agenda ajustada',
);
$appointmentFinancialSync = [];'''
removed_insert = '''$removedBlock = $appointmentCommands->removeBlock(
    7,
    701,
    11,
    'Agenda ajustada',
);
$dayBlockId = $appointmentCommands->setDayBlocked(
    7,
    91,
    '2026-08-12 04:00:00',
    '2026-08-13 04:00:00',
    '2026-08-12 12:00:00',
    '2026-08-12 21:00:00',
    11,
    true,
);
$dayUnblockResult = $appointmentCommands->setDayBlocked(
    7,
    91,
    '2026-08-12 04:00:00',
    '2026-08-13 04:00:00',
    '2026-08-12 12:00:00',
    '2026-08-12 21:00:00',
    11,
    false,
);
$appointmentPort->rowsByOperation['operational.appointments.05.page_appointments.43'] = [['id' => 999]];
$dayAppointmentGuard = '';
try {
    $appointmentCommands->setDayBlocked(
        7,
        91,
        '2026-08-12 04:00:00',
        '2026-08-13 04:00:00',
        '2026-08-12 12:00:00',
        '2026-08-12 21:00:00',
        11,
        true,
    );
} catch (RuntimeException $error) {
    $dayAppointmentGuard = $error->getMessage();
}
unset($appointmentPort->rowsByOperation['operational.appointments.05.page_appointments.43']);
$appointmentFinancialSync = [];'''
if test.count(removed_marker) != 1:
    raise SystemExit("test day toggle insertion marker invalid")
test = test.replace(removed_marker, removed_insert, 1)
assert_marker = "$assert($removedBlock, 'application.appointment_commands.remove_block');\n"
assert_insert = """$assert($removedBlock, 'application.appointment_commands.remove_block');
$same([701, 0], [$dayBlockId, $dayUnblockResult], 'application.appointment_commands.day_block_toggle');
$same('O dia já possui consulta marcada para este profissional.', $dayAppointmentGuard, 'application.appointment_commands.day_block_guard');
"""
if test.count(assert_marker) != 1:
    raise SystemExit("test day toggle assertion marker invalid")
test = test.replace(assert_marker, assert_insert, 1)
application_contract_marker = "$applicationContract = json_decode(\n"
source_assertions = '''$agendaRuntimeSource = (string) file_get_contents($root . '/app/Runtime/Appointments/AppointmentsRuntimeOperations05.php');
$assert(
    str_contains($agendaRuntimeSource, '"block_day", "unblock_day"') &&
        str_contains($agendaRuntimeSource, '$rows === []') &&
        str_contains($agendaRuntimeSource, 'Bloquear dia') &&
        str_contains($agendaRuntimeSource, 'Desbloquear dia') &&
        str_contains($agendaRuntimeSource, 'agenda-day-toggle-form'),
    'presentation.agenda.day_block_toggle',
);
$agendaAuthorizationSource = (string) file_get_contents($root . '/app/Application/Authorization/Definitions/SchedulingActionDefinitions.php');
$assert(
    str_contains($agendaAuthorizationSource, "'block_day'") &&
        str_contains($agendaAuthorizationSource, "'unblock_day'"),
    'application.authorization.agenda_day_block_actions',
);

$applicationContract = json_decode(
'''
if test.count(application_contract_marker) != 1:
    raise SystemExit("test source assertion marker invalid")
test = test.replace(application_contract_marker, source_assertions, 1)
test_path.write_text(test, encoding="utf-8")

css_block = '''
body[data-route="appointments"] .agenda-crown-row{display:flex;align-items:center;gap:10px;width:100%;min-width:0;flex-wrap:wrap}
body[data-route="appointments"] .agenda-crown-row>.agenda-crown-picker{flex:1 1 420px;min-width:0;width:auto!important;max-width:none!important}
body[data-route="appointments"] .agenda-day-toggle-form{display:flex;align-items:center;flex:0 0 auto;margin-left:auto}
body[data-route="appointments"] .agenda-day-toggle-button{min-height:36px!important;white-space:nowrap}
'''
for relative in ["design/styles/application.css", "public/assets/presentation.css"]:
    path = root / relative
    text = path.read_text(encoding="utf-8")
    if "agenda-day-toggle-form" in text:
        raise SystemExit(f"daily toggle CSS already present in {relative}")
    text = text.replace("1.9.15.3", "1.9.15.4")
    path.write_text(text.rstrip() + "\n" + css_block, encoding="utf-8")

visual = root / "app/presentation.visual-contract.json"
replace_once(visual, '"version": "1.9.15.3"', '"version": "1.9.15.4"', "visual contract version")

docs_index = root / "docs/index.md"
replace_once(docs_index, '`1.9.15.3`', '`1.9.15.4`', "docs baseline")

domain_docs = root / "docs/domain/appointments.md"
domain = domain_docs.read_text(encoding="utf-8")
security_marker = "## Segurança\n\nToda operação é tenant-scoped e sujeita à autorização da ação correspondente.\n"
security_new = '''## Bloqueio diário

Na visualização diária, quando o profissional selecionado não possui consultas na data, a própria barra de navegação pode alternar entre **Bloquear dia** e **Desbloquear dia**. O bloqueio cobre o expediente configurado do profissional, usa o mesmo fallback temporal da Agenda quando não há expediente cadastrado e é serializado com a criação de consultas para impedir corrida entre bloqueio e agendamento. Bloqueios parciais continuam independentes e não são apagados pela alternância do dia.

## Segurança

Toda operação é tenant-scoped e sujeita à autorização da ação correspondente. `block_day` reutiliza a capacidade de adicionar bloqueios e `unblock_day` reutiliza a capacidade de removê-los.
'''
if domain.count(security_marker) != 1:
    raise SystemExit("appointment domain docs marker invalid")
domain_docs.write_text(domain.replace(security_marker, security_new, 1), encoding="utf-8")

version_path = root / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "version": "1.9.15.4",
    "release": "1.9.15.4",
    "generated_at_unix": 1789481700,
    "generated_at": "2026-09-15T10:15:00-04:00",
    "updated_at": "2026-09-15T10:15:00-04:00",
    "build": "1.9.15.4-agenda-day-block-toggle",
    "asset_version": "1.9.15.4",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "documentation_changes": True,
    "notes": "Adiciona Bloquear dia na Agenda diária quando não há consultas, com alternância atômica para bloquear ou desbloquear o expediente do profissional selecionado.",
    "deployment_sync_id": "github-prontoo-1.9.15.4-release",
    "deployment_sync_requested_at": "2026-09-15T10:15:00-04:00",
    "release_date": "2026-09-15",
    "changelog": {
        "title": "Bloqueio rápido do dia na Agenda",
        "items": [
            "Exibe Bloquear dia à direita da navegação da Agenda diária somente quando o profissional selecionado não possui consultas na data.",
            "Alterna o mesmo controle para Desbloquear dia quando existe bloqueio cobrindo todo o expediente selecionado.",
            "Bloqueia o expediente configurado do profissional e usa o mesmo fallback de 08:00 a 17:00 da Agenda quando não há faixa cadastrada.",
            "Serializa o bloqueio com a criação de consultas e revalida a ausência de agendamentos dentro da transação para evitar corrida operacional.",
            "Preserva bloqueios parciais independentes, mantém o fluxo Bloquear horário e não altera banco de dados nem schema."
        ]
    },
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
