from pathlib import Path
import json


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    count = source.count(old)
    if count != 1:
        raise SystemExit(f"{path}: expected one replacement, found {count}")
    file.write_text(source.replace(old, new, 1))


workflow_path = Path('app/Core/Invariant/Workflow/AppointmentWorkflow.php')
workflow = workflow_path.read_text()
replace_pairs = [
    (
        '            "agendado" => ["confirmado", "chegou", "cancelado", "nao_compareceu", "reagendado"],',
        '            "agendado" => ["confirmado", "chegou", "em_atendimento", "cancelado", "nao_compareceu", "reagendado"],',
    ),
    (
        '            "chegou" => ["em_preparo"],',
        '            "chegou" => ["em_preparo", "pronto_atendimento"],',
    ),
]
for old, new in replace_pairs:
    if workflow.count(old) != 1:
        raise SystemExit(f'AppointmentWorkflow transition not unique: {old}')
    workflow = workflow.replace(old, new, 1)

logic_anchor = '''        $unsafeComplete = true;
        SqlExpression::whereAllowedValues(
            "UPDATE pi_appointments SET status='confirmado' WHERE status='agendado' OR id=?",
            "status",
            [8],
            $unsafeComplete,
        );
        $cases = [
'''
logic_replacement = '''        $unsafeComplete = true;
        SqlExpression::whereAllowedValues(
            "UPDATE pi_appointments SET status='confirmado' WHERE status='agendado' OR id=?",
            "status",
            [8],
            $unsafeComplete,
        );
        $taskSourcesComplete = true;
        $taskSources = SqlExpression::whereAllowedValues(
            "UPDATE pi_appointments SET status='pronto_atendimento' WHERE id=? AND clinic_id=? AND status IN ('chegou','em_preparo')",
            "status",
            [8, 4],
            $taskSourcesComplete,
        );
        $clinicalStartSql = "UPDATE pi_appointments SET consultation_started_at=NOW(), status='em_atendimento' WHERE id=? AND clinic_id=? AND patient_link_id=? AND status IN ('agendado','pronto_atendimento')";
        $clinicalAssignments = SqlExpression::assignments($clinicalStartSql);
        $clinicalSourcesComplete = true;
        $clinicalSources = SqlExpression::whereAllowedValues(
            $clinicalStartSql,
            "status",
            [8, 4, 2],
            $clinicalSourcesComplete,
        );
        $cases = [
'''
if workflow.count(logic_anchor) != 1:
    raise SystemExit('AppointmentWorkflow logic anchor not unique')
workflow = workflow.replace(logic_anchor, logic_replacement, 1)
case_anchor = '''            "confirm_flow" => $machine->canTransition("agendado", "confirmado"),
            "care_flow" => $machine->canTransition("em_atendimento", "atendimento_concluido"),
'''
case_replacement = '''            "confirm_flow" => $machine->canTransition("agendado", "confirmado"),
            "task_completion_fast_ready" =>
                $taskSourcesComplete &&
                $taskSources === ["chegou", "em_preparo"] &&
                $machine->canTransition("chegou", "pronto_atendimento") &&
                $machine->canTransition("em_preparo", "pronto_atendimento"),
            "clinical_note_fast_start" =>
                $clinicalSourcesComplete &&
                $clinicalSources === ["agendado", "pronto_atendimento"] &&
                !empty($clinicalAssignments["status"]["known_direct"]) &&
                $machine->canTransition("agendado", "em_atendimento") &&
                $machine->canTransition("pronto_atendimento", "em_atendimento"),
            "care_flow" => $machine->canTransition("em_atendimento", "atendimento_concluido"),
'''
if workflow.count(case_anchor) != 1:
    raise SystemExit('AppointmentWorkflow case anchor not unique')
workflow_path.write_text(workflow.replace(case_anchor, case_replacement, 1))

replace_once(
    'app/Infrastructure/Operational/PatientsSqlCatalog07.php',
    '                "UPDATE pi_appointments SET consultation_started_at=NOW(), status=IF(status=\'agendado\',\'em_atendimento\',status), updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=?"',
    '                "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), status=\'em_atendimento\', updated_at=NOW() WHERE id=? AND clinic_id=? AND patient_link_id=? AND status IN (\'agendado\',\'pronto_atendimento\') AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"',
)

composition_path = Path('app/Runtime/Operational/OperationalComposition.php')
composition = composition_path.read_text()
for old, new in [
    (
        'use Prontoo\\Application\\Operational\\MaestroCommandService;\n',
        'use Prontoo\\Application\\Operational\\MaestroCommandService;\nuse Prontoo\\Application\\Operational\\PatientCareCommand;\n',
    ),
    (
        '    private static ?AppointmentCommandService $appointmentCommands = null;\n',
        '    private static ?AppointmentCommandService $appointmentCommands = null;\n    private static ?PatientCareCommand $patientCareCommands = null;\n',
    ),
    (
        '''    public static function taskCommands(): TaskCommandService
    {
        return self::$taskCommands ??= new TaskCommandService(self::tasks());
    }

    public static function patients(): OperationalUseCaseService
''',
        '''    public static function taskCommands(): TaskCommandService
    {
        return self::$taskCommands ??= new TaskCommandService(self::tasks());
    }

    public static function patientCareCommands(): PatientCareCommand
    {
        return self::$patientCareCommands ??= new PatientCareCommand(self::patients());
    }

    public static function patients(): OperationalUseCaseService
''',
    ),
    (
        '        self::$appointmentCommands = null;\n        self::$auditWrite = null;\n',
        '        self::$appointmentCommands = null;\n        self::$patientCareCommands = null;\n        self::$auditWrite = null;\n',
    ),
]:
    if composition.count(old) != 1:
        raise SystemExit(f'OperationalComposition anchor not unique: {old[:70]}')
    composition = composition.replace(old, new, 1)
composition_path.write_text(composition)

patient_path = Path('app/Runtime/Patients/PatientsRuntimeOperations07.php')
patient = patient_path.read_text()
old_patient_write = '''            \\Prontoo\\Runtime\\Operational\\OperationalComposition::patients()->result('operational.patients.07.page_patient.26', [
                    $cid,
                    $id,
                    $appointmentId > 0 ? $appointmentId : null,
                    $type,
                    $title,
                    (int) $c["user"]["id"],
                ], []);
            $careId = \\Prontoo\\Runtime\\Operational\\OperationalComposition::patients()->lastInsertId();
            \\Prontoo\\Runtime\\Operational\\OperationalComposition::patients()->result('operational.patients.07.page_patient.27', [$careId, $cid, $content], []);
            if (
                $appointmentForAudit &&
                empty($appointmentForAudit["consultation_started_at"])
            ) {
                \\Prontoo\\Runtime\\Operational\\OperationalComposition::patients()->result('operational.patients.07.page_patient.28', [$appointmentId, $cid, $id], []);
                $scheduled = \\Prontoo\\Infrastructure\\SupportFoundation\\SupportFoundationInfrastructureOperations01::app_storage_timestamp(
                    $appointmentForAudit["start_at"],
                );
                $delay = $scheduled
                    ? max(0, (int) floor((time() - $scheduled) / 60))
                    : null;
                \\Prontoo\\Runtime\\AuditActivity\\AuditActivityRuntimeOperations04::audit("consulta_iniciada", "consulta", $appointmentId, [
                    "patient_name" => (string) $p["full_name"],
                    "patient_link_id" => $id,
                    "doctor_user_id" =>
                        $appointmentForAudit["doctor_user_id"] ?? null,
                    "start_at" => $appointmentForAudit["start_at"] ?? null,
                    "end_at" => $appointmentForAudit["end_at"] ?? null,
                    "reason" => $appointmentForAudit["reason"] ?? null,
                    "audit_body" =>
                        "A consulta foi iniciada" .
                        ($delay !== null
                            ? " com atraso registrado de " .
                                \\Prontoo\\Domain\\Appointments\\AppointmentsDomainOperations01::format_minutes($delay) .
                                " em relação ao horário agendado."
                            : "."),
                ]);
            }
'''
new_patient_write = '''            $careWrite = \\Prontoo\\Runtime\\Operational\\OperationalComposition::patientCareCommands()->createClinicalNote(
                $cid,
                $id,
                $appointmentId > 0 ? $appointmentId : null,
                $type,
                $title,
                $content,
                (int) $c["user"]["id"],
                (bool) ($appointmentForAudit && empty($appointmentForAudit["consultation_started_at"])),
            );
            $careId = (int) ($careWrite["care_id"] ?? 0);
            $consultationStarted = !empty($careWrite["consultation_started"]);
            if ($consultationStarted) {
                $scheduled = \\Prontoo\\Infrastructure\\SupportFoundation\\SupportFoundationInfrastructureOperations01::app_storage_timestamp(
                    $appointmentForAudit["start_at"],
                );
                $delay = $scheduled
                    ? max(0, (int) floor((time() - $scheduled) / 60))
                    : null;
                \\Prontoo\\Runtime\\AuditActivity\\AuditActivityRuntimeOperations04::audit("consulta_iniciada", "consulta", $appointmentId, [
                    "patient_name" => (string) $p["full_name"],
                    "patient_link_id" => $id,
                    "doctor_user_id" =>
                        $appointmentForAudit["doctor_user_id"] ?? null,
                    "start_at" => $appointmentForAudit["start_at"] ?? null,
                    "end_at" => $appointmentForAudit["end_at"] ?? null,
                    "reason" => $appointmentForAudit["reason"] ?? null,
                    "audit_body" =>
                        "A consulta foi iniciada" .
                        ($delay !== null
                            ? " com atraso registrado de " .
                                \\Prontoo\\Domain\\Appointments\\AppointmentsDomainOperations01::format_minutes($delay) .
                                " em relação ao horário agendado."
                            : "."),
                ]);
            }
'''
if patient.count(old_patient_write) != 1:
    raise SystemExit(f'PatientsRuntime clinical-write block expected once, found {patient.count(old_patient_write)}')
patient_path.write_text(patient.replace(old_patient_write, new_patient_write, 1))

audit_path = Path('tools/global-audit-contract-check')
audit = audit_path.read_text()
for old, new in [
    (
        '    \'function_exists("privacy_log_file_label")\',\n];',
        '    \'function_exists("privacy_log_file_label")\',\n    "status=IF(status=\'agendado\',\'em_atendimento\',status)",\n];',
    ),
    (
        '$result = [\n',
        '''$journey = (string) file_get_contents($root . '/app/Core/Invariant/Workflow/AppointmentWorkflow.php');
$patientSql = (string) file_get_contents($root . '/app/Infrastructure/Operational/PatientsSqlCatalog07.php');
$patientRuntime = (string) file_get_contents($root . '/app/Runtime/Patients/PatientsRuntimeOperations07.php');
$patientCommand = (string) file_get_contents($root . '/app/Application/Operational/PatientCareCommand.php');
foreach ([
    'journey:task_completion_fast_ready' => str_contains($journey, '"task_completion_fast_ready"'),
    'journey:clinical_note_fast_start' => str_contains($journey, '"clinical_note_fast_start"'),
    'patient_sql:direct_em_atendimento' => str_contains($patientSql, "status='em_atendimento'") && str_contains($patientSql, "status IN ('agendado','pronto_atendimento')"),
    'patient_runtime:typed_atomic_command' => str_contains($patientRuntime, 'patientCareCommands()->createClinicalNote(') && !str_contains($patientRuntime, "operational.patients.07.page_patient.28"),
    'patient_command:atomicity' => str_contains($patientCommand, '$this->data->atomic(') && str_contains($patientCommand, "operational.patients.07.page_patient.26") && str_contains($patientCommand, "operational.patients.07.page_patient.27") && str_contains($patientCommand, "operational.patients.07.page_patient.28"),
] as $name => $ok) {
    if (!$ok) {
        $failures[] = $name;
    }
}
$result = [
''',
    ),
]:
    if audit.count(old) != 1:
        raise SystemExit(f'global audit anchor not unique: {old[:60]}')
    audit = audit.replace(old, new, 1)
audit_path.write_text(audit)

version_path = Path('version.json')
version = json.loads(version_path.read_text())
version.update({
    'version': '1.8.14.1',
    'release': '1.8.14.1',
    'generated_at_unix': 1786714860,
    'generated_at': '2026-08-14T09:41:00-04:00',
    'updated_at': '2026-08-14T09:41:00-04:00',
    'build': '1.8.14.1-global-audit-followup-remediation',
    'asset_version': '1.8.14.1',
    'logic_changes': True,
    'visual_changes': False,
    'database_changes': False,
    'schema_changes': False,
    'documentation_changes': True,
    'functional_equivalence_policy': 'canonical_global_audit_followup_remediation_without_schema_change',
    'deployment_sync_id': 'github-prontoo-1.8.14.1-global-audit-followup-remediation',
    'deployment_sync_requested_at': '2026-08-14T09:41:00-04:00',
    'release_date': '2026-08-14',
    'notes': 'Fecha regressões funcionais da Jornada encontradas na reauditoria global, torna gravação clínica inicial atômica e reforça governança do repositório sem alterar schema.',
    'changelog': {
        'title': 'Remediação pós-reauditoria global',
        'items': [
            'formaliza o atalho de preparo concluído da Jornada sem recusar o fluxo existente de chegou para pronto para atendimento',
            'substitui a expressão condicional de início clínico por transição de estado diretamente demonstrável pelo kernel',
            'torna atômica a criação da anotação clínica com o início da consulta por command tipado e reduz dívida do Runtime tocado',
            'adiciona contratos anti-regressão específicos para os dois defeitos funcionais encontrados na reauditoria',
            'adiciona limpeza segura de branches agent já incorporadas ao estado canônico e prepara proteção obrigatória da branch principal',
        ],
    },
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + '\n')
