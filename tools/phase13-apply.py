from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
path = ROOT / 'tools/test-fast'
source = path.read_text()

requires_old = """    'app/Application/Financial/FinancialCashierAttentionPort.php',
    'app/Application/Financial/FinancialCashierAttentionService.php',
    'app/Application/Patients/PatientReadPort.php',
    'app/Application/Patients/PatientReadService.php',
"""
requires_new = """    'app/Application/Financial/FinancialCashierAttentionPort.php',
    'app/Application/Financial/FinancialCashierAttentionService.php',
    'app/Application/Financial/FinancialGoalPort.php',
    'app/Application/Financial/FinancialGoalService.php',
    'app/Application/Financial/PatientRevenueReceiptPort.php',
    'app/Application/Financial/PatientRevenueReceiptService.php',
    'app/Application/Patients/PatientContactCommandPort.php',
    'app/Application/Patients/PatientContactCommandService.php',
    'app/Application/Patients/PatientReadPort.php',
    'app/Application/Patients/PatientReadService.php',
    'app/Application/Patients/PatientReceptionHistoryReadPort.php',
    'app/Application/Patients/PatientReceptionHistoryReadService.php',
    'app/Application/Patients/PatientTabCommandPort.php',
    'app/Application/Patients/PatientTabCommandService.php',
"""
if requires_new not in source:
    if source.count(requires_old) != 1:
        raise RuntimeError('require list insertion point not found')
    source = source.replace(requires_old, requires_new, 1)

uses_old = """use Prontoo\\Application\\Financial\\FinancialCashierAttentionPort;
use Prontoo\\Application\\Financial\\FinancialCashierAttentionService;
use Prontoo\\Application\\Patients\\PatientReadPort;
use Prontoo\\Application\\Patients\\PatientReadService;
"""
uses_new = """use Prontoo\\Application\\Financial\\FinancialCashierAttentionPort;
use Prontoo\\Application\\Financial\\FinancialCashierAttentionService;
use Prontoo\\Application\\Financial\\FinancialGoalPort;
use Prontoo\\Application\\Financial\\FinancialGoalService;
use Prontoo\\Application\\Financial\\PatientRevenueReceiptPort;
use Prontoo\\Application\\Financial\\PatientRevenueReceiptService;
use Prontoo\\Application\\Patients\\PatientContactCommandPort;
use Prontoo\\Application\\Patients\\PatientContactCommandService;
use Prontoo\\Application\\Patients\\PatientReadPort;
use Prontoo\\Application\\Patients\\PatientReadService;
use Prontoo\\Application\\Patients\\PatientReceptionHistoryReadPort;
use Prontoo\\Application\\Patients\\PatientReceptionHistoryReadService;
use Prontoo\\Application\\Patients\\PatientTabCommandPort;
use Prontoo\\Application\\Patients\\PatientTabCommandService;
"""
if uses_new not in source:
    if source.count(uses_old) != 1:
        raise RuntimeError('use list insertion point not found')
    source = source.replace(uses_old, uses_new, 1)

marker = "$assert($patientService->hasLegalGuardian(7, 31), 'application.patient.has_guardian');\n"
block = r'''

$goalPort = new class implements FinancialGoalPort {
    public array $last = [];
    public function save(int $clinicId, string $monthKey, int $targetCents, string $baseMetric, bool $shareWithTeam, int $userId): void
    {
        $this->last = [$clinicId, $monthKey, $targetCents, $baseMetric, $shareWithTeam, $userId];
    }
};
$goalService = new FinancialGoalService($goalPort);
$goal = $goalService->save(7, '2026-08', 250000, 'desconhecida', true, 91);
$same('efetivada', $goal['base_metric'] ?? null, 'application.goal.base_normalized');
$same(1, $goal['share_with_team'] ?? null, 'application.goal.share_normalized');
$same([7, '2026-08', 250000, 'efetivada', true, 91], $goalPort->last, 'application.goal.port_arguments');

$revenuePort = new class implements PatientRevenueReceiptPort {
    public array $last = [];
    public function receive(int $clinicId, int $patientId, int $revenueId, int $userId, string $role): array
    {
        $this->last = [$clinicId, $patientId, $revenueId, $userId, $role];
        return ['status' => 'received', 'revenue_id' => $revenueId, 'movement_id' => 44, 'amount_cents' => 12500, 'title' => 'Consulta'];
    }
};
$revenueService = new PatientRevenueReceiptService($revenuePort);
$receipt = $revenueService->receive(7, 31, 52, 91, ' GERENTE ');
$same('received', $receipt['status'] ?? null, 'application.revenue.received');
$same(44, $receipt['movement_id'] ?? null, 'application.revenue.movement');
$same([7, 31, 52, 91, 'gerente'], $revenuePort->last, 'application.revenue.port_arguments');

$contactPort = new class implements PatientContactCommandPort {
    public array $last = [];
    public function update(int $clinicId, int $patientId, int $userId, array $contact): array
    {
        $this->last = [$clinicId, $patientId, $userId, $contact];
        return ['status' => 'updated'];
    }
};
$contactService = new PatientContactCommandService($contactPort);
$contactResult = $contactService->update(7, 31, 91, ['phone' => ' 65999990000 ', 'email' => ' ana@example.com ']);
$same(['status' => 'updated', 'patient_id' => 31], $contactResult, 'application.contact.updated');
$same('65999990000', $contactPort->last[3]['phone'] ?? null, 'application.contact.phone_trimmed');
$same('ana@example.com', $contactPort->last[3]['email'] ?? null, 'application.contact.email_trimmed');

$historyPort = new class implements PatientReceptionHistoryReadPort {
    public array $last = [];
    public function read(int $clinicId, int $patientId, int $personId, string $phoneDigits): array
    {
        $this->last = [$clinicId, $patientId, $personId, $phoneDigits];
        return ['leads' => [8 => ['id' => 8]], 'events' => ['8' => [['id' => 9]]], 'users' => ['91' => 'Ana']];
    }
};
$historyService = new PatientReceptionHistoryReadService($historyPort);
$history = $historyService->read(7, 31, 12, '(65) 99999-0000');
$same([7, 31, 12, '65999990000'], $historyPort->last, 'application.history.port_arguments');
$same([['id' => 8]], $history['leads'] ?? null, 'application.history.leads_list');
$same(['91' => 'Ana'], $history['users'] ?? null, 'application.history.users_map');

$tabPort = new class implements PatientTabCommandPort {
    public array $last = [];
    public function createIfAbsent(int $clinicId, int $patientId, string $label, string $iconName, int $userId): array
    {
        $this->last = [$clinicId, $patientId, $label, $iconName, $userId];
        return ['status' => 'duplicate', 'id' => 71, 'sort_order' => 4];
    }
};
$tabService = new PatientTabCommandService($tabPort);
$tab = $tabService->create(7, 31, ' Exames ', ' science ', 91);
$same('duplicate', $tab['status'] ?? null, 'application.tab.duplicate');
$same(71, $tab['id'] ?? null, 'application.tab.id');
$same([7, 31, 'Exames', 'science', 91], $tabPort->last, 'application.tab.port_arguments');
'''
if 'application.goal.port_arguments' not in source:
    if source.count(marker) != 1:
        raise RuntimeError('test insertion point not found')
    source = source.replace(marker, marker + block, 1)

source = source.replace("'policy' => 'fast-unit-suite-v2'", "'policy' => 'fast-unit-suite-v3'")
source = source.replace("'application_ports_tested' => 2", "'application_ports_tested' => 7")
path.write_text(source)
print('phase13 applied')
