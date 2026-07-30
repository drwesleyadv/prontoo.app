from pathlib import Path
import json
import re
import time
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.4"
PREVIOUS = "1.7.30.3"
BUILD = "1.7.30.4-server-phase4-reception-read-model"
NOW = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
NOW_UNIX = int(time.time())


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace_once(path: str, old: str, new: str) -> None:
    target = ROOT / path
    source = target.read_text(encoding="utf-8")
    count = source.count(old)
    if count != 1:
        raise SystemExit(f"{path}: esperado 1 trecho, encontrado {count}")
    target.write_text(source.replace(old, new, 1), encoding="utf-8")


def update_json(path: str, values: dict) -> None:
    target = ROOT / path
    data = json.loads(target.read_text(encoding="utf-8"))
    data.update(values)
    target.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")


write("app/Application/Patients/PatientReceptionHistoryReadPort.php", """<?php
declare(strict_types=1);

namespace Prontoo\\Application\\Patients;

interface PatientReceptionHistoryReadPort
{
    public function read(
        int $clinicId,
        int $patientId,
        int $personId,
        string $phoneDigits,
    ): array;
}
""")

write("app/Application/Patients/PatientReceptionHistoryReadService.php", """<?php
declare(strict_types=1);

namespace Prontoo\\Application\\Patients;

use InvalidArgumentException;

final class PatientReceptionHistoryReadService
{
    public function __construct(private PatientReceptionHistoryReadPort $port)
    {
    }

    public function read(
        int $clinicId,
        int $patientId,
        int $personId,
        string $phoneDigits,
    ): array {
        $phoneDigits = substr(preg_replace('/\\D+/', '', $phoneDigits) ?? '', 0, 11);
        if ($clinicId <= 0 || $patientId <= 0) {
            throw new InvalidArgumentException('Escopo de histórico da recepção inválido.');
        }
        if ($personId <= 0 && $phoneDigits === '') {
            return ['leads' => [], 'events' => [], 'users' => []];
        }
        $model = $this->port->read($clinicId, $patientId, max(0, $personId), $phoneDigits);
        return [
            'leads' => array_values(is_array($model['leads'] ?? null) ? $model['leads'] : []),
            'events' => is_array($model['events'] ?? null) ? $model['events'] : [],
            'users' => is_array($model['users'] ?? null) ? $model['users'] : [],
        ];
    }
}
""")

write("app/Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php", """<?php
declare(strict_types=1);

namespace Prontoo\\Infrastructure\\Patients;

use Prontoo\\Application\\Patients\\PatientReceptionHistoryReadPort;

final class PdoPatientReceptionHistoryReadRepository implements PatientReceptionHistoryReadPort
{
    public function read(
        int $clinicId,
        int $patientId,
        int $personId,
        string $phoneDigits,
    ): array {
        $identity = [];
        $params = [$clinicId, $patientId];
        if ($personId > 0) {
            $identity[] = 'l.person_id=?';
            $params[] = $personId;
        }
        if ($phoneDigits !== '') {
            $identity[] = "l.phone_digits=? OR LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(l.phone,''),'(',''),')',''),' ',''),'-',''),'.',''),11)=?";
            $params[] = $phoneDigits;
            $params[] = $phoneDigits;
        }
        if ($identity === []) {
            return ['leads' => [], 'events' => [], 'users' => []];
        }
        $rows = \\q(
            "SELECT l.id AS lead_id,l.person_id,l.name,l.phone,l.phone_digits,l.source,l.interest,l.stage,l.next_action_at,l.notes,l.created_by AS lead_created_by,l.created_at AS lead_created_at,l.updated_at AS lead_updated_at,e.id AS event_id,e.event_type,e.stage_from,e.stage_to,e.phone AS event_phone,e.source AS event_source,e.interest AS event_interest,e.next_action_at AS event_next_action_at,e.body AS event_body,e.created_by AS event_created_by,e.created_at AS event_created_at,u.name AS event_created_by_name FROM pi_leads l LEFT JOIN pi_lead_events e ON e.clinic_id=l.clinic_id AND e.lead_id=l.id LEFT JOIN pi_users u ON u.id=e.created_by WHERE l.clinic_id=? AND EXISTS (SELECT 1 FROM pi_patients pp WHERE pp.id=? AND pp.clinic_id=l.clinic_id AND pp.active=1) AND (" . implode(' OR ', $identity) . ") ORDER BY l.created_at ASC,l.id ASC,e.created_at ASC,e.id ASC",
            $params,
        )->fetchAll();
        $leads = [];
        $events = [];
        $users = [];
        foreach ($rows as $row) {
            $leadId = (int) ($row['lead_id'] ?? 0);
            if ($leadId <= 0) {
                continue;
            }
            if (!isset($leads[$leadId])) {
                $leads[$leadId] = [
                    'id' => $leadId,
                    'person_id' => $row['person_id'] ?? null,
                    'name' => $row['name'] ?? '',
                    'phone' => $row['phone'] ?? '',
                    'phone_digits' => $row['phone_digits'] ?? '',
                    'source' => $row['source'] ?? '',
                    'interest' => $row['interest'] ?? '',
                    'stage' => $row['stage'] ?? '',
                    'next_action_at' => $row['next_action_at'] ?? null,
                    'notes' => $row['notes'] ?? '',
                    'created_by' => $row['lead_created_by'] ?? null,
                    'created_at' => $row['lead_created_at'] ?? null,
                    'updated_at' => $row['lead_updated_at'] ?? null,
                ];
            }
            $eventId = (int) ($row['event_id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }
            $creatorId = (int) ($row['event_created_by'] ?? 0);
            $events[$leadId][] = [
                'id' => $eventId,
                'lead_id' => $leadId,
                'event_type' => $row['event_type'] ?? '',
                'stage_from' => $row['stage_from'] ?? null,
                'stage_to' => $row['stage_to'] ?? null,
                'phone' => $row['event_phone'] ?? null,
                'source' => $row['event_source'] ?? null,
                'interest' => $row['event_interest'] ?? null,
                'next_action_at' => $row['event_next_action_at'] ?? null,
                'body' => $row['event_body'] ?? null,
                'created_by' => $creatorId > 0 ? $creatorId : null,
                'created_at' => $row['event_created_at'] ?? null,
            ];
            if ($creatorId > 0 && trim((string) ($row['event_created_by_name'] ?? '')) !== '') {
                $users[$creatorId] = ['id' => $creatorId, 'name' => (string) $row['event_created_by_name']];
            }
        }
        return ['leads' => array_values($leads), 'events' => $events, 'users' => $users];
    }
}
""")

patients_path = ROOT / "app/Domain/Patients/Patients.php"
patients = patients_path.read_text(encoding="utf-8")
start = patients.find('    if (function_exists("ensure_lead_events_schema")) {', patients.find('function patient_reception_history_items('))
end = patients.find('    $items = [];', start)
if start < 0 or end < 0:
    raise SystemExit("Bloco de leitura do histórico da recepção não localizado")
replacement = '''    if (function_exists("ensure_lead_events_schema")) {
        ensure_lead_events_schema();
    }
    $personId = (int) ($p["person_id"] ?? 0);
    $phoneDigits = substr(only_digits((string) ($p["phone"] ?? "")), 0, 11);
    try {
        $readModel = prontoo_patient_reception_history_read_model(
            $cid,
            $patientId,
            $personId,
            $phoneDigits,
        );
    } catch (Throwable $e) {
        error_log("[Prontoo patient reception history] " . $e->getMessage());
        return [];
    }
    $leads = (array) ($readModel["leads"] ?? []);
    $events = (array) ($readModel["events"] ?? []);
    $users = (array) ($readModel["users"] ?? []);
    if (!$leads) {
        return [];
    }
'''
patients = patients[:start] + replacement + patients[end:]
patients_path.write_text(patients, encoding="utf-8")

runner_path = ROOT / "app/Runtime/Runner.php"
runner = runner_path.read_text(encoding="utf-8")
marker = "function prontoo_patient_tab_command_service(): \\Prontoo\\Application\\Patients\\PatientTabCommandService\n"
if runner.count(marker) != 1:
    raise SystemExit("Marcador de composição de pacientes divergente")
composition = '''function prontoo_patient_reception_history_read_service(): \\Prontoo\\Application\\Patients\\PatientReceptionHistoryReadService
{
    static $service = null;
    if (!$service instanceof \\Prontoo\\Application\\Patients\\PatientReceptionHistoryReadService) {
        $service = new \\Prontoo\\Application\\Patients\\PatientReceptionHistoryReadService(
            new \\Prontoo\\Infrastructure\\Patients\\PdoPatientReceptionHistoryReadRepository(),
        );
    }
    return $service;
}
function prontoo_patient_reception_history_read_model(
    int $clinicId,
    int $patientId,
    int $personId,
    string $phoneDigits,
): array {
    return prontoo_patient_reception_history_read_service()->read(
        $clinicId,
        $patientId,
        $personId,
        $phoneDigits,
    );
}
'''
runner_path.write_text(runner.replace(marker, composition + marker, 1), encoding="utf-8")

loader_path = ROOT / "app/Support/ModuleLoader.php"
loader = loader_path.read_text(encoding="utf-8")
needle = "        'Application/Patients/PatientTabCommandPort.php',\n"
addition = "        'Application/Patients/PatientReceptionHistoryReadPort.php',\n        'Application/Patients/PatientReceptionHistoryReadService.php',\n        'Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php',\n"
if loader.count(needle) != 2:
    raise SystemExit(f"ModuleLoader: esperado 2 grupos de pacientes, encontrado {loader.count(needle)}")
loader_path.write_text(loader.replace(needle, addition + needle), encoding="utf-8")

architecture_path = ROOT / "tools/architecture-check.php"
source = architecture_path.read_text(encoding="utf-8")
marker = "$result = [\n"
index = source.rfind(marker)
if index < 0:
    raise SystemExit("Marcador final do contrato arquitetural divergente")
block = r'''require_once $root . '/app/Application/Patients/PatientReceptionHistoryReadPort.php';
require_once $root . '/app/Application/Patients/PatientReceptionHistoryReadService.php';
require_once $root . '/app/Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php';

$serverPhaseFourFailures = [];
$receptionPort = new class implements \Prontoo\Application\Patients\PatientReceptionHistoryReadPort {
    public array $received = [];
    public function read(
        int $clinicId,
        int $patientId,
        int $personId,
        string $phoneDigits,
    ): array {
        $this->received = [$clinicId, $patientId, $personId, $phoneDigits];
        return [
            'leads' => [['id' => 4]],
            'events' => [4 => [['id' => 8]]],
            'users' => [9 => ['id' => 9, 'name' => 'Ana']],
        ];
    }
};
$receptionService = new \Prontoo\Application\Patients\PatientReceptionHistoryReadService($receptionPort);
$receptionModel = $receptionService->read(2, 3, 5, '(65) 99999-0000');
if ($receptionPort->received !== [2, 3, 5, '65999990000'] ||
    count($receptionModel['leads']) !== 1 ||
    count($receptionModel['events'][4] ?? []) !== 1) {
    $serverPhaseFourFailures[] = 'reception_read_model_behavior';
}
$serverPhaseFourSources = [
    'service' => (string) file_get_contents($root . '/app/Application/Patients/PatientReceptionHistoryReadService.php'),
    'repository' => (string) file_get_contents($root . '/app/Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php'),
    'patients' => (string) file_get_contents($root . '/app/Domain/Patients/Patients.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
];
foreach ([
    'repository' => ['LEFT JOIN pi_lead_events', 'LEFT JOIN pi_users', 'EXISTS (SELECT 1 FROM pi_patients', 'l.clinic_id=?'],
    'patients' => ['prontoo_patient_reception_history_read_model('],
    'runner' => ['PdoPatientReceptionHistoryReadRepository', 'PatientReceptionHistoryReadService'],
] as $sourceKey => $tokens) {
    foreach ($tokens as $token) {
        if (!str_contains($serverPhaseFourSources[$sourceKey], $token)) {
            $serverPhaseFourFailures[] = $sourceKey . ':missing:' . $token;
        }
    }
}
foreach (['SELECT ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION'] as $token) {
    if (str_contains($serverPhaseFourSources['service'], $token)) {
        $serverPhaseFourFailures[] = 'service:forbidden:' . $token;
    }
}
$receptionFunctionStart = strpos($serverPhaseFourSources['patients'], 'function patient_reception_history_items(');
$receptionFunctionEnd = $receptionFunctionStart === false
    ? false
    : strpos($serverPhaseFourSources['patients'], 'function patient_appointment_timeline_items(', $receptionFunctionStart);
$receptionFunction = $receptionFunctionStart !== false && $receptionFunctionEnd !== false
    ? substr($serverPhaseFourSources['patients'], $receptionFunctionStart, $receptionFunctionEnd - $receptionFunctionStart)
    : '';
if ($receptionFunction === '' || str_contains($receptionFunction, 'FROM pi_leads')) {
    $serverPhaseFourFailures[] = 'legacy_reception_sql_not_removed';
}
$serverPhaseFourCharacterization = [
    'ok' => $serverPhaseFourFailures === [],
    'failed' => $serverPhaseFourFailures,
];
'''
source = source[:index] + block + source[index:]
source = source.replace(
    "        !empty($serverPhaseThreeCharacterization['ok']),",
    "        !empty($serverPhaseThreeCharacterization['ok']) &&\n        !empty($serverPhaseFourCharacterization['ok']),",
    1,
)
source = source.replace(
    "    'server_phase_three_characterization' => $serverPhaseThreeCharacterization,",
    "    'server_phase_three_characterization' => $serverPhaseThreeCharacterization,\n    'server_phase_four_characterization' => $serverPhaseFourCharacterization,",
    1,
)
architecture_path.write_text(source, encoding="utf-8")

write("docs/performance/patient-reception-read-model.md", """# Read model do histórico da recepção

## Objetivo

A Fase 4 consolida a leitura de interessados, eventos e autores associados ao paciente em uma única consulta orientada ao caso de uso.

## Contratos

A aplicação recebe consultório, paciente, pessoa e telefone normalizado. A infraestrutura comprova a existência do paciente ativo no mesmo consultório e filtra todos os interessados pelo `clinic_id`.

## Compatibilidade

Ordenação, campos e estrutura consumida pela apresentação permanecem iguais. A formatação do histórico continua na fachada legada, enquanto SQL e reconstrução do modelo ficam no adaptador PDO.

## Manutenção

O serviço não acessa sessão, HTTP ou banco. Novos campos devem ser incluídos primeiro na porta e caracterizados antes de alterar a apresentação.
""")

changelog = ROOT / "CHANGELOG.md"
text = changelog.read_text(encoding="utf-8")
entry = """\n## 1.7.30.4 — Evolução do servidor, Fase 4: read model da recepção

- consolida interessados, eventos e autores em uma única leitura;
- move SQL do histórico da recepção para adaptador PDO;
- mantém ordenação, estrutura e apresentação existentes;
- torna isolamento e custo da leitura explicitamente testáveis;
- não altera banco, schema ou interface.\n"""
if entry.strip() not in text:
    changelog.write_text(text.replace("# Histórico de versões\n", "# Histórico de versões\n" + entry, 1), encoding="utf-8")
change_txt = ROOT / "ChangeLog.txt"
text = change_txt.read_text(encoding="utf-8")
entry = """Prontoo 1.7.30.4 — Evolução do servidor, Fase 4: read model da recepção

- Consolida o histórico da recepção em uma leitura isolada por consultório.
- Mantém banco, schema, interface, permissões e comportamento funcional.
"""
if not text.startswith("Prontoo 1.7.30.4"):
    change_txt.write_text(entry + text, encoding="utf-8")
docs_index = ROOT / "docs/index.md"
text = docs_index.read_text(encoding="utf-8")
line = "- [Read model do histórico da recepção](performance/patient-reception-read-model.md)\n"
if line not in text:
    docs_index.write_text(text.rstrip() + "\n" + line, encoding="utf-8")

for path, constant in [("app/prontoo.php", "PRONTOO_VERSION_FALLBACK"), ("br/index.php", "BR_LANDING_VERSION_FALLBACK")]:
    target = ROOT / path
    current = target.read_text(encoding="utf-8")
    pattern = rf"(const\s+{constant}\s*=\s*['\"])[^'\"]+(['\"]\s*;)"
    current, count = re.subn(pattern, rf"\g<1>{VERSION}\g<2>", current, count=1)
    if count != 1:
        raise SystemExit(f"Fallback não localizado: {path}")
    target.write_text(current, encoding="utf-8")

common = {
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "package_type": "incremental_refactor",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": False,
    "visual_changes": False,
    "functional_equivalence_policy": "preserves_1_7_30_3_runtime_behavior",
    "previous_version": PREVIOUS,
    "documentation_changes": True,
    "notes": "Consolida o histórico da recepção em read model isolado por consultório.",
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "patient_reception_history_read_model",
    "deployment_sync_id": "github-server-phase4-reception-read-model-1-7-30-4",
    "deployment_sync_requested_at": NOW,
}
update_json("version.json", common)
update_json("app/update.manifest.json", common)
update_json("app/architecture.manifest.json", {
    "version": VERSION,
    "generated_at": NOW,
    "updated_at": NOW,
    "baseline_source": PREVIOUS,
    "native_files_min": 33,
    "transitional_files_max": 80,
    "server_evolution_phase_four_policy": "patient_reception_history_is_one_tenant_scoped_read_model",
    "server_evolution_phase_four_components": [
        "app/Application/Patients/PatientReceptionHistoryReadPort.php",
        "app/Application/Patients/PatientReceptionHistoryReadService.php",
        "app/Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php",
    ],
    "server_evolution_phase_four_check": "tools/architecture-check.php",
})
