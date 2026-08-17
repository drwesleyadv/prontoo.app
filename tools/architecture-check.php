<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/page-load-telemetry-contract-check';
require __DIR__ . '/database-record-telemetry-contract-check';
require __DIR__ . '/telemetry-json-source-contract-check';

use Prontoo\Core\Architecture\ArchitectureVerifier;
use Prontoo\Runtime\LayeredKernel;
use Prontoo\Runtime\Routing\RouteCatalog;
use Prontoo\Runtime\Modules\RuntimeModuleComposition;

$root = dirname(__DIR__);
if (!defined('PRONTOO_ROOT')) {
    define('PRONTOO_ROOT', $root);
}
$version = json_decode(
    (string) file_get_contents($root . '/version.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
if (!defined('PRONTOO_VERSION')) {
    define('PRONTOO_VERSION', (string) ($version['version'] ?? ''));
}

if (!class_exists('ProntooHttpError')) {
    class ProntooHttpError extends RuntimeException
    {
        public function __construct(public int $status, string $message)
        {
            parent::__construct($message);
        }
    }
}

require_once $root . '/app/bootstrap_architecture.php';
require_once $root . '/app/Runtime/Runner.php';
require_once $root . '/app/Domain/Identity/IdentityDocumentValidator.php';
require_once $root . '/app/Domain/Patients/PatientPure.php';
require_once $root . '/app/Application/Patients/PatientReadPort.php';
require_once $root . '/app/Application/Patients/PatientReadService.php';
require_once $root . '/app/Application/Patients/PatientReceptionHistoryReadPort.php';
require_once $root . '/app/Application/Patients/PatientReceptionHistoryReadService.php';
require_once $root . '/app/Application/Patients/PatientTabCommandPort.php';
require_once $root . '/app/Application/Patients/PatientTabCommandService.php';
require_once $root . '/app/Application/Patients/PatientContactCommandPort.php';
require_once $root . '/app/Application/Patients/PatientContactCommandService.php';
require_once $root . '/app/Application/Financial/PatientRevenueReceiptPort.php';
require_once $root . '/app/Application/Financial/PatientRevenueReceiptService.php';

$failures = [];
$assert = static function (bool $condition, string $name) use (&$failures): void {
    if (!$condition) {
        $failures[] = $name;
    }
};

$architecture = ArchitectureVerifier::report(
    $root,
    true,
    \Prontoo\Runtime\Modules\RuntimeModuleCatalog::fullModules(),
    RouteCatalog::all(),
);
$selfTest = LayeredKernel::logicSelfTest($root);
$assert(!empty($architecture['ok']), 'architecture_verifier');
$assert(!empty($selfTest['ok']), 'layered_kernel_self_test');
$assert((float) ($architecture['classification_coverage_percent'] ?? 0) === 100.0, 'classification_coverage');
$assert((int) ($architecture['action_contracts_total'] ?? 0) === 151, 'action_contract_count');

$routes = RouteCatalog::all();
$publicRoutes = RouteCatalog::public();
$jsonRoutes = RouteCatalog::json();
foreach (['home', 'login', 'login_telemetry_wave', 'patients', 'financial', 'status'] as $route) {
    $assert(in_array($route, $routes, true), 'route_catalog:' . $route);
}
foreach (['login', 'login_telemetry_wave', 'logout', 'status'] as $route) {
    $assert(in_array($route, $publicRoutes, true), 'public_route_catalog:' . $route);
}
foreach (['login_telemetry_wave', 'footer_telemetry_wave', 'patient_lookup', 'goal_status'] as $route) {
    $assert(in_array($route, $jsonRoutes, true), 'json_route_catalog:' . $route);
}
$telemetryRoute = \Prontoo\Runtime\Routing\RouteRegistry::definition('login_telemetry_wave');
$assert(
    $telemetryRoute !== null &&
        $telemetryRoute->handlerClass === \Prontoo\Runtime\PublicWeb\PublicWebRuntimeOperations01::class &&
        $telemetryRoute->handlerMethod === 'page_public_telemetry_tombstone' &&
        $telemetryRoute->isPublicLight('GET'),
    'telemetry_public_tombstone_only',
);
$footerTelemetryRoute = \Prontoo\Runtime\Routing\RouteRegistry::definition('footer_telemetry_wave');
$assert(
    $footerTelemetryRoute !== null &&
        !$footerTelemetryRoute->public &&
        $footerTelemetryRoute->json &&
        $footerTelemetryRoute->handlerClass === \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::class &&
        $footerTelemetryRoute->handlerMethod === 'page_footer_telemetry_wave',
    'footer_telemetry_authenticated_only',
);
$statusRoute = \Prontoo\Runtime\Routing\RouteRegistry::definition('status');
$assert(
    $statusRoute !== null &&
        $statusRoute->handlerClass === \Prontoo\Runtime\PublicWeb\PublicWebRuntimeOperations01::class &&
        $statusRoute->handlerMethod === 'page_public_status' &&
        $statusRoute->isPublicLight('GET'),
    'status_public_without_metrics',
);
$assert(RouteCatalog::isPublicLight('login', 'POST'), 'login_public_light');
$assert(RouteCatalog::isPublicLight('login_autotest', 'GET'), 'login_autotest_get_public_light');
$assert(!RouteCatalog::isPublicLight('login_autotest', 'POST'), 'login_autotest_post_not_light');
$assert(RouteCatalog::isPublicLight('status', 'GET'), 'status_get_public_light');
$assert(!RouteCatalog::isPublicLight('status', 'POST'), 'status_post_not_light');
$assert(RouteCatalog::wantsJson('patient_lookup', ''), 'patient_lookup_json');
$assert(RouteCatalog::wantsJson('patients', 'application/json'), 'accept_json');

$runnerSource = (string) file_get_contents($root . '/app/Runtime/Runner.php');
$bootSource = (string) file_get_contents($root . '/app/Runtime/Boot/RuntimeBootCoordinator.php');
$patientCompositionSource = (string) file_get_contents($root . '/app/Runtime/Patients/PatientComposition.php');
$patientViewCompositionSource = (string) file_get_contents($root . '/app/Runtime/Patients/PatientViewComposition.php');
$financialCompositionSource = (string) file_get_contents($root . '/app/Runtime/Financial/FinancialComposition.php');
$publicWebSource = (string) file_get_contents($root . '/app/Runtime/PublicWeb/PublicWebRuntimeOperations01.php');
$loginSource = (string) file_get_contents($root . '/app/Runtime/AuthOnboarding/AuthOnboardingRuntimeOperations03.php');

foreach ([
    'RuntimeBootCoordinator::bootDatabaseForRoute(',
    'RouteCatalog::isPublicLight(',
    'RouteCatalog::wantsJson(',
    'JsonResponder::send(',
    'RuntimeBootCoordinator::flushIntegrityBeforeRender(',
    'LayeredKernel::enforceAction($route, $method, $_POST, $context)',
    'RuntimeModuleComposition::loader()->loadRouteModules($route)',
] as $token) {
    $assert(str_contains($runnerSource, $token), 'runner_missing:' . $token);
}
foreach ([
    'function prontoo_',
    'PdoPatientReadRepository',
    'PdoPatientTabCommandRepository',
    'PdoPatientRevenueReceiptRepository',
    'PatientContactView::editForm(',
    '$publicTelemetry =',
    '$publicStatus =',
] as $token) {
    $assert(!str_contains($runnerSource, $token), 'runner_forbidden:' . $token);
}
$assert(!str_contains($loginSource, 'login_telemetry_wave_html()'), 'login_has_no_public_telemetry');
$assert(str_contains($publicWebSource, '"telemetry_public" => false'), 'public_telemetry_tombstone_flag');
foreach ([
    'telemetry_route_requests_series_20d',
    'telemetry_database_record_series_30d',
    'telemetry_comparative_summary',
    'PRONTOO_VERSION',
] as $token) {
    $assert(!str_contains($publicWebSource, $token), 'public_web_forbidden:' . $token);
}
foreach ([
    "self::runReadinessCycle('route_readiness')",
    "self::runMaintenanceCycle('forced_deep')",
    "'reason' => 'runtime_readiness_fresh'",
    "self::runReadinessCycle('post_password_login', \$uid)",
    'readinessMarkerPath()',
    'markReadinessOk($mode)',
    '::runtime_self_check();',
] as $token) {
    $assert(str_contains($bootSource, $token), 'boot_missing:' . $token);
}
foreach ([
    "self::runMaintenanceCycle('post_password_login', \$uid)",
    "['route_deep', 'post_password_login']",
] as $token) {
    $assert(!str_contains($bootSource, $token), 'boot_forbidden:' . $token);
}
foreach ([
    'new PdoPatientReadRepository()',
    'appointmentRegistrationBlockReason(',
    'legalGuardians(',
    'hasLegalGuardian(',
    'new PdoPatientReceptionHistoryReadRepository()',
    'new PdoPatientTabCommandRepository()',
    'new PdoPatientContactCommandRepository()',
] as $token) {
    $assert(str_contains($patientCompositionSource, $token), 'patient_composition_missing:' . $token);
}
foreach ([
    'PatientContactView::editForm(',
    'PatientTabView::iconPicker(',
    'OnboardingTipView::render(',
] as $token) {
    $assert(str_contains($patientViewCompositionSource, $token), 'patient_view_composition_missing:' . $token);
}
foreach ([
    'new PdoPatientRevenueReceiptRepository(new PatientRevenueSettlementAdapter())',
    'new FinancialDataService(',
    'new PdoFinancialDataRepository(',
    "Closure::fromCallable([DatabaseSchemaRuntimeOperations01::class, 'q'])",
    'receivePatientRevenue(',
] as $token) {
    $assert(str_contains($financialCompositionSource, $token), 'financial_composition_missing:' . $token);
}
$assert(\Prontoo\Domain\Identity\IdentityDocumentValidator::cpf('52998224725'), 'cpf_valid');
$assert(!\Prontoo\Domain\Identity\IdentityDocumentValidator::cpf('11111111111'), 'cpf_repeated_rejected');
$assert(\Prontoo\Domain\Identity\IdentityDocumentValidator::cnpj('11222333000181'), 'cnpj_valid');
$assert(\Prontoo\Domain\Patients\PatientPure::cleanTabLabel(' <b> Histórico   clínico </b> ') === 'Histórico clínico', 'patient_tab_label');

$readPort = new class implements \Prontoo\Application\Patients\PatientReadPort {
    public ?array $patient = null;
    public array $guardians = [];
    public bool $hasGuardian = false;
    public function appointmentRegistration(int $clinicId, int $patientId): ?array
    {
        return $this->patient;
    }
    public function activeLegalGuardians(int $clinicId, int $patientId): array
    {
        return $this->guardians;
    }
    public function hasActiveLegalGuardian(int $clinicId, int $patientId): bool
    {
        return $this->hasGuardian;
    }
};
$readService = new \Prontoo\Application\Patients\PatientReadService($readPort);
$complete = static fn(array $patient): bool => (string) ($patient['state'] ?? '') === 'complete';
$alert = static fn(array $patient): string => 'alert:' . (string) ($patient['state'] ?? '');
$digits = static fn(string $value): string => (string) preg_replace('/\D+/', '', $value);
$relationship = static fn(string $value): string => strtolower($value) === 'mae' ? 'mae' : 'outro';
$assert($readService->appointmentRegistrationBlockReason(0, 1, $complete, $alert) === null, 'patient_read_invalid_scope');
$assert($readService->appointmentRegistrationBlockReason(1, 2, $complete, $alert) === 'Paciente não encontrado no consultório atual.', 'patient_read_not_found');
$readPort->patient = ['state' => 'complete'];
$assert($readService->appointmentRegistrationBlockReason(1, 2, $complete, $alert) === null, 'patient_read_complete');
$readPort->guardians = [[
    'id' => '7',
    'full_name' => 'Responsável',
    'cpf' => '529.982.247-25',
    'relationship' => 'MAE',
    'is_primary' => '1',
]];
$guardians = $readService->legalGuardians(1, 2, $digits, $relationship);
$assert(($guardians[0]['cpf'] ?? '') === '52998224725', 'guardian_cpf_normalized');
$assert(($guardians[0]['relationship'] ?? '') === 'mae', 'guardian_relationship_normalized');
$readPort->hasGuardian = true;
$assert($readService->hasLegalGuardian(1, 2), 'guardian_exists');

$historyPort = new class implements \Prontoo\Application\Patients\PatientReceptionHistoryReadPort {
    public array $received = [];
    public function read(int $clinicId, int $patientId, int $personId, string $phoneDigits): array
    {
        $this->received = [$clinicId, $patientId, $personId, $phoneDigits];
        return ['leads' => [['id' => 4]], 'events' => [4 => [['id' => 8]]], 'users' => []];
    }
};
$historyService = new \Prontoo\Application\Patients\PatientReceptionHistoryReadService($historyPort);
$history = $historyService->read(2, 3, 5, '(65) 99999-0000');
$assert($historyPort->received === [2, 3, 5, '65999990000'], 'history_phone_normalized');
$assert(count($history['leads'] ?? []) === 1, 'history_model');

$tabPort = new class implements \Prontoo\Application\Patients\PatientTabCommandPort {
    public array $received = [];
    public function createIfAbsent(int $clinicId, int $patientId, string $label, string $iconName, int $userId): array
    {
        $this->received = [$clinicId, $patientId, $label, $iconName, $userId];
        return ['status' => 'created', 'id' => 9, 'sort_order' => 20];
    }
};
$tabService = new \Prontoo\Application\Patients\PatientTabCommandService($tabPort);
$tabResult = $tabService->create(3, 7, 'Evolução', 'clinical_notes', 11);
$assert(($tabResult['status'] ?? '') === 'created', 'patient_tab_created');
$assert($tabPort->received === [3, 7, 'Evolução', 'clinical_notes', 11], 'patient_tab_port');

$contactPort = new class implements \Prontoo\Application\Patients\PatientContactCommandPort {
    public array $received = [];
    public function update(int $clinicId, int $patientId, int $userId, array $contact): array
    {
        $this->received = [$clinicId, $patientId, $userId, $contact];
        return ['status' => 'updated', 'patient_id' => $patientId];
    }
};
$contactService = new \Prontoo\Application\Patients\PatientContactCommandService($contactPort);
$contactResult = $contactService->update(2, 7, 11, ['phone' => '65999990000']);
$assert(($contactResult['status'] ?? '') === 'updated', 'patient_contact_updated');
$assert($contactPort->received[0] === 2 && $contactPort->received[1] === 7, 'patient_contact_scope');

$revenuePort = new class implements \Prontoo\Application\Financial\PatientRevenueReceiptPort {
    public array $received = [];
    public function receive(int $clinicId, int $patientId, int $revenueId, int $userId, string $role): array
    {
        $this->received = [$clinicId, $patientId, $revenueId, $userId, $role];
        return ['status' => 'received', 'revenue_id' => $revenueId, 'movement_id' => 13, 'amount_cents' => 12500, 'title' => 'Consulta'];
    }
};
$revenueService = new \Prontoo\Application\Financial\PatientRevenueReceiptService($revenuePort);
$revenue = $revenueService->receive(2, 4, 8, 10, 'recepcionista');
$assert(($revenue['status'] ?? '') === 'received', 'patient_revenue_received');
$assert($revenuePort->received === [2, 4, 8, 10, 'recepcionista'], 'patient_revenue_port');
try {
    $revenueService->receive(2, 4, 8, 10, 'medico');
    $failures[] = 'patient_revenue_forbidden_role';
} catch (InvalidArgumentException) {
}

$consolidationManifest = json_decode(
    (string) file_get_contents($root . '/app/architecture.manifest.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
foreach ($consolidationManifest as $key => $paths) {
    if (!is_string($key) || !str_ends_with($key, '_components') || !is_array($paths)) {
        continue;
    }
    foreach ($paths as $componentPath) {
        if (!is_string($componentPath) || $componentPath === '') {
            $failures[] = 'consolidation_component_invalid:' . $key;
            continue;
        }
        $assert(is_file($root . '/' . $componentPath), 'consolidation_component_missing:' . $key . ':' . $componentPath);
    }
}
$result = [
    'ok' => $failures === [],
    'architecture' => $architecture,
    'self_test' => $selfTest,
    'runtime_composition' => [
        'ok' => $failures === [],
        'failed' => $failures,
    ],
];

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($result['ok'] ? 0 : 1);
