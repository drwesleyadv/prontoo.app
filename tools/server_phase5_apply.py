from pathlib import Path
import json
import re
import time
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.5"
PREVIOUS = "1.7.30.4"
BUILD = "1.7.30.5-server-phase5-patient-contact-command"
NOW = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
NOW_UNIX = int(time.time())


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def update_json(path: str, values: dict) -> None:
    target = ROOT / path
    data = json.loads(target.read_text(encoding="utf-8"))
    data.update(values)
    target.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")


write("app/Application/Patients/PatientContactCommandPort.php", """<?php
declare(strict_types=1);

namespace Prontoo\\Application\\Patients;

interface PatientContactCommandPort
{
    public function update(
        int $clinicId,
        int $patientId,
        int $userId,
        array $contact,
    ): array;
}
""")

write("app/Application/Patients/PatientContactCommandService.php", """<?php
declare(strict_types=1);

namespace Prontoo\\Application\\Patients;

use InvalidArgumentException;
use RuntimeException;

final class PatientContactCommandService
{
    private const FIELDS = [
        'phone',
        'email',
        'address',
        'address_zip',
        'address_number',
        'address_neighborhood',
        'address_complement',
        'address_state',
        'address_city',
        'address_city_ibge',
    ];

    public function __construct(private PatientContactCommandPort $port)
    {
    }

    public function update(
        int $clinicId,
        int $patientId,
        int $userId,
        array $contact,
    ): array {
        if ($clinicId <= 0 || $patientId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Comando de contato do paciente inválido.');
        }
        $normalized = [];
        foreach (self::FIELDS as $field) {
            $normalized[$field] = trim((string) ($contact[$field] ?? ''));
        }
        $result = $this->port->update($clinicId, $patientId, $userId, $normalized);
        if ((string) ($result['status'] ?? '') !== 'updated') {
            throw new RuntimeException('Resultado inválido ao atualizar o contato do paciente.');
        }
        return ['status' => 'updated', 'patient_id' => $patientId];
    }
}
""")

write("app/Infrastructure/Patients/PdoPatientContactCommandRepository.php", """<?php
declare(strict_types=1);

namespace Prontoo\\Infrastructure\\Patients;

use Prontoo\\Application\\Patients\\PatientContactCommandPort;
use RuntimeException;
use Throwable;

final class PdoPatientContactCommandRepository implements PatientContactCommandPort
{
    public function update(
        int $clinicId,
        int $patientId,
        int $userId,
        array $contact,
    ): array {
        $pdo = \\pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $patient = \\one(
                "SELECT id FROM pi_patients WHERE id=? AND clinic_id=? AND active=1 FOR UPDATE",
                [$patientId, $clinicId],
            );
            if (!$patient) {
                throw new RuntimeException('Paciente não encontrado no consultório atual.');
            }
            \\q(
                "UPDATE pi_patients SET phone=?,email=?,address=?,address_zip=?,address_number=?,address_neighborhood=?,address_complement=?,address_state=?,address_city=?,address_city_ibge=?,registration_needs_update=0,updated_at=NOW() WHERE id=? AND clinic_id=? AND active=1",
                [
                    $contact['phone'],
                    $contact['email'],
                    $contact['address'],
                    $contact['address_zip'],
                    $contact['address_number'],
                    $contact['address_neighborhood'],
                    $contact['address_complement'],
                    $contact['address_state'],
                    $contact['address_city'],
                    $contact['address_city_ibge'],
                    $patientId,
                    $clinicId,
                ],
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return ['status' => 'updated', 'patient_id' => $patientId];
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}
""")

patients_path = ROOT / "app/Domain/Patients/Patients.php"
patients = patients_path.read_text(encoding="utf-8")
old = '''            $contact = patient_invoice_contact_from_post();
            $loc = patient_location_from_post($cid);
            q(
                "UPDATE pi_patients SET phone=?,email=?,address=?,address_zip=?,address_number=?,address_neighborhood=?,address_complement=?,address_state=?,address_city=?,address_city_ibge=?,registration_needs_update=0,updated_at=NOW() WHERE id=? AND clinic_id=?",
                [
                    $contact["phone"],
                    $contact["email"],
                    $loc["address"],
                    $loc["address_zip"],
                    $loc["address_number"],
                    $loc["address_neighborhood"],
                    $loc["address_complement"],
                    $loc["address_state"],
                    $loc["address_city"],
                    $loc["address_city_ibge"],
                    $id,
                    $cid,
                ],
            );
'''
new = '''            $contact = patient_invoice_contact_from_post();
            $loc = patient_location_from_post($cid);
            prontoo_update_patient_contact_command(
                $cid,
                $id,
                (int) $c["user"]["id"],
                [
                    "phone" => $contact["phone"],
                    "email" => $contact["email"],
                    "address" => $loc["address"],
                    "address_zip" => $loc["address_zip"],
                    "address_number" => $loc["address_number"],
                    "address_neighborhood" => $loc["address_neighborhood"],
                    "address_complement" => $loc["address_complement"],
                    "address_state" => $loc["address_state"],
                    "address_city" => $loc["address_city"],
                    "address_city_ibge" => $loc["address_city_ibge"],
                ],
            );
'''
if patients.count(old) != 1:
    raise SystemExit(f"Bloco de contato esperado 1 vez, encontrado {patients.count(old)}")
patients_path.write_text(patients.replace(old, new, 1), encoding="utf-8")

runner_path = ROOT / "app/Runtime/Runner.php"
runner = runner_path.read_text(encoding="utf-8")
marker = "function prontoo_patient_revenue_receipt_service(): \\Prontoo\\Application\\Financial\\PatientRevenueReceiptService\n"
if runner.count(marker) != 1:
    raise SystemExit("Marcador financeiro de composição divergente")
composition = '''function prontoo_patient_contact_command_service(): \\Prontoo\\Application\\Patients\\PatientContactCommandService
{
    static $service = null;
    if (!$service instanceof \\Prontoo\\Application\\Patients\\PatientContactCommandService) {
        $service = new \\Prontoo\\Application\\Patients\\PatientContactCommandService(
            new \\Prontoo\\Infrastructure\\Patients\\PdoPatientContactCommandRepository(),
        );
    }
    return $service;
}
function prontoo_update_patient_contact_command(
    int $clinicId,
    int $patientId,
    int $userId,
    array $contact,
): array {
    return prontoo_patient_contact_command_service()->update(
        $clinicId,
        $patientId,
        $userId,
        $contact,
    );
}

'''
runner_path.write_text(runner.replace(marker, composition + marker, 1), encoding="utf-8")

loader_path = ROOT / "app/Support/ModuleLoader.php"
loader = loader_path.read_text(encoding="utf-8")
needle = "        'Application/Patients/PatientReceptionHistoryReadPort.php',\n"
addition = "        'Application/Patients/PatientContactCommandPort.php',\n        'Application/Patients/PatientContactCommandService.php',\n        'Infrastructure/Patients/PdoPatientContactCommandRepository.php',\n"
if loader.count(needle) != 2:
    raise SystemExit(f"ModuleLoader: esperado 2 grupos de pacientes, encontrado {loader.count(needle)}")
loader_path.write_text(loader.replace(needle, addition + needle), encoding="utf-8")

architecture_path = ROOT / "tools/architecture-check.php"
source = architecture_path.read_text(encoding="utf-8")
marker = "$result = [\n"
index = source.rfind(marker)
if index < 0:
    raise SystemExit("Marcador final do contrato arquitetural divergente")
block = r'''require_once $root . '/app/Application/Patients/PatientContactCommandPort.php';
require_once $root . '/app/Application/Patients/PatientContactCommandService.php';
require_once $root . '/app/Infrastructure/Patients/PdoPatientContactCommandRepository.php';

$serverPhaseFiveFailures = [];
$contactPort = new class implements \Prontoo\Application\Patients\PatientContactCommandPort {
    public array $received = [];
    public function update(
        int $clinicId,
        int $patientId,
        int $userId,
        array $contact,
    ): array {
        $this->received = [$clinicId, $patientId, $userId, $contact];
        return ['status' => 'updated', 'patient_id' => $patientId];
    }
};
$contactService = new \Prontoo\Application\Patients\PatientContactCommandService($contactPort);
$contactResult = $contactService->update(2, 3, 4, [
    'phone' => ' 65999990000 ',
    'email' => ' contato@example.com ',
    'address' => ' Rua A ',
]);
if ($contactResult !== ['status' => 'updated', 'patient_id' => 3] ||
    ($contactPort->received[3]['phone'] ?? '') !== '65999990000' ||
    ($contactPort->received[3]['email'] ?? '') !== 'contato@example.com') {
    $serverPhaseFiveFailures[] = 'patient_contact_command_behavior';
}
$serverPhaseFiveSources = [
    'service' => (string) file_get_contents($root . '/app/Application/Patients/PatientContactCommandService.php'),
    'repository' => (string) file_get_contents($root . '/app/Infrastructure/Patients/PdoPatientContactCommandRepository.php'),
    'patients' => (string) file_get_contents($root . '/app/Domain/Patients/Patients.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
];
foreach ([
    'repository' => ['FOR UPDATE', '$pdo->beginTransaction()', '$pdo->commit()', '$pdo->rollBack()', 'WHERE id=? AND clinic_id=? AND active=1'],
    'patients' => ['prontoo_update_patient_contact_command('],
    'runner' => ['PdoPatientContactCommandRepository', 'PatientContactCommandService'],
] as $sourceKey => $tokens) {
    foreach ($tokens as $token) {
        if (!str_contains($serverPhaseFiveSources[$sourceKey], $token)) {
            $serverPhaseFiveFailures[] = $sourceKey . ':missing:' . $token;
        }
    }
}
foreach (['SELECT ', 'UPDATE ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION'] as $token) {
    if (str_contains($serverPhaseFiveSources['service'], $token)) {
        $serverPhaseFiveFailures[] = 'service:forbidden:' . $token;
    }
}
$contactActionStart = strpos($serverPhaseFiveSources['patients'], 'if ($act === "update_patient_contact")');
$contactActionEnd = $contactActionStart === false
    ? false
    : strpos($serverPhaseFiveSources['patients'], 'if ($act === "patient_revenue_receive")', $contactActionStart);
$contactAction = $contactActionStart !== false && $contactActionEnd !== false
    ? substr($serverPhaseFiveSources['patients'], $contactActionStart, $contactActionEnd - $contactActionStart)
    : '';
if ($contactAction === '' || str_contains($contactAction, 'UPDATE pi_patients')) {
    $serverPhaseFiveFailures[] = 'legacy_contact_sql_not_removed';
}
$serverPhaseFiveCharacterization = [
    'ok' => $serverPhaseFiveFailures === [],
    'failed' => $serverPhaseFiveFailures,
];
'''
source = source[:index] + block + source[index:]
source = source.replace(
    "        !empty($serverPhaseFourCharacterization['ok']),",
    "        !empty($serverPhaseFourCharacterization['ok']) &&\n        !empty($serverPhaseFiveCharacterization['ok']),",
    1,
)
source = source.replace(
    "    'server_phase_four_characterization' => $serverPhaseFourCharacterization,",
    "    'server_phase_four_characterization' => $serverPhaseFourCharacterization,\n    'server_phase_five_characterization' => $serverPhaseFiveCharacterization,",
    1,
)
architecture_path.write_text(source, encoding="utf-8")

write("docs/architecture/patient-contact-command.md", """# Comando de contato do paciente

## Objetivo

A Fase 5 concentra a atualização do contato fiscal do paciente em um comando transacional isolado por consultório.

## Fronteiras

A apresentação mantém autorização, leitura do formulário, auditoria, mensagem e redirecionamento. O serviço normaliza a entrada e o adaptador PDO bloqueia a linha ativa do paciente antes de atualizar.

## Robustez

A transação é curta e contém somente bloqueio e persistência. Qualquer falha executa rollback. O filtro utiliza simultaneamente paciente, consultório e estado ativo.

## Compatibilidade

Os mesmos campos são gravados, `registration_needs_update` continua sendo zerado e `updated_at` continua sendo renovado. Não há alteração de banco, schema ou interface.
""")

changelog = ROOT / "CHANGELOG.md"
text = changelog.read_text(encoding="utf-8")
entry = """\n## 1.7.30.5 — Evolução do servidor, Fase 5: comando de contato do paciente

- move a atualização de contato para porta, serviço e adaptador PDO;
- adiciona bloqueio pessimista da linha ativa do paciente;
- mantém a persistência em transação curta com rollback;
- preserva autorização, auditoria, mensagens e campos existentes;
- não altera banco, schema ou interface.\n"""
if entry.strip() not in text:
    changelog.write_text(text.replace("# Histórico de versões\n", "# Histórico de versões\n" + entry, 1), encoding="utf-8")
change_txt = ROOT / "ChangeLog.txt"
text = change_txt.read_text(encoding="utf-8")
entry = """Prontoo 1.7.30.5 — Evolução do servidor, Fase 5: comando de contato do paciente

- Torna a atualização do contato tenant-scoped, bloqueada e transacional.
- Mantém banco, schema, interface, permissões e comportamento funcional.
"""
if not text.startswith("Prontoo 1.7.30.5"):
    change_txt.write_text(entry + text, encoding="utf-8")
docs_index = ROOT / "docs/index.md"
text = docs_index.read_text(encoding="utf-8")
line = "- [Comando de contato do paciente](architecture/patient-contact-command.md)\n"
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
    "functional_equivalence_policy": "preserves_1_7_30_4_runtime_behavior",
    "previous_version": PREVIOUS,
    "documentation_changes": True,
    "notes": "Torna a atualização do contato do paciente um comando tenant-scoped e transacional.",
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "patient_contact_transactional_command",
    "deployment_sync_id": "github-server-phase5-patient-contact-command-1-7-30-5",
    "deployment_sync_requested_at": NOW,
}
update_json("version.json", common)
update_json("app/update.manifest.json", common)
update_json("app/architecture.manifest.json", {
    "version": VERSION,
    "generated_at": NOW,
    "updated_at": NOW,
    "baseline_source": PREVIOUS,
    "native_files_min": 36,
    "transitional_files_max": 80,
    "server_evolution_phase_five_policy": "patient_contact_update_is_tenant_locked_transactional_and_audited_by_http_facade",
    "server_evolution_phase_five_components": [
        "app/Application/Patients/PatientContactCommandPort.php",
        "app/Application/Patients/PatientContactCommandService.php",
        "app/Infrastructure/Patients/PdoPatientContactCommandRepository.php",
    ],
    "server_evolution_phase_five_check": "tools/architecture-check.php",
})
