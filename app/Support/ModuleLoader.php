<?php
declare(strict_types=1);

use Prontoo\Core\Architecture\LayerMap;

function prontoo_module_file(string $relative): string
{

    return __DIR__ . '/../' . ltrim($relative, '/');
}

function prontoo_module_layer(string $relative): string
{

    $layer = LayerMap::layerFor('app/' . ltrim($relative, '/'));
    if ($layer === null) {
        throw new RuntimeException('Módulo sem camada arquitetural: app/' . ltrim($relative, '/'));
    }
    return $layer;
}

function prontoo_require_module(string $relative): void
{

    $file = prontoo_module_file($relative);
    if (!is_file($file)) {
        $root = defined('PRONTOO_ROOT') ? PRONTOO_ROOT : dirname(__DIR__, 2);
        throw new RuntimeException(
            'Módulo especializado essencial ausente: ' . str_replace($root . '/', '', $file),
        );
    }
    $layer = prontoo_module_layer($relative);
    $key = realpath($file) ?: $file;
    if (!isset($GLOBALS['PRONTOO_LOADED_MODULE_FILES']) || !is_array($GLOBALS['PRONTOO_LOADED_MODULE_FILES'])) {
        $GLOBALS['PRONTOO_LOADED_MODULE_FILES'] = [];
    }
    if (!isset($GLOBALS['PRONTOO_LOADED_MODULE_LAYERS']) || !is_array($GLOBALS['PRONTOO_LOADED_MODULE_LAYERS'])) {
        $GLOBALS['PRONTOO_LOADED_MODULE_LAYERS'] = [];
    }
    $measured = &$GLOBALS['PRONTOO_LOADED_MODULE_FILES'];
    require_once $file;
    if (!isset($measured[$key])) {
        $measured[$key] = true;
        $GLOBALS['PRONTOO_LOADED_MODULE_LAYERS'][$layer] =
            (int) ($GLOBALS['PRONTOO_LOADED_MODULE_LAYERS'][$layer] ?? 0) + 1;
        $GLOBALS['PRONTOO_MODULE_FILES_LOADED'] = count($measured);
        $GLOBALS['PRONTOO_MODULE_BYTES_LOADED'] =
            (int) ($GLOBALS['PRONTOO_MODULE_BYTES_LOADED'] ?? 0) +
            max(0, (int) (@filesize($file) ?: 0));
    }
}

function prontoo_require_modules(array $files): void
{

    foreach ($files as $file) {
        prontoo_require_module((string) $file);
    }
}

function prontoo_runtime_specialization_policy(): string
{

    return 'pi-v3-php-layered-runtime-route-loaded';
}

function prontoo_boot_requested_route(): string
{

    $route = $_GET['r'] ?? 'login';
    return preg_replace('/[^a-z0-9_\-]/i', '', (string) $route) ?: 'login';
}

function prontoo_public_light_routes(): array
{

    return ['login', 'login_autotest', 'mfa', 'mobile_web_access', 'signup', 'logout'];
}

function prontoo_use_light_boot(): bool
{

    return (string) getenv('PRONTOO_DISABLE_LIGHT_BOOT') !== '1';
}

function prontoo_runtime_core_modules(): array
{

    return [
        'Support/Runtime.php',
        'Support/SecurityPrivacy.php',
        'Support/Foundation.php',
        'Support/ServerJsonCache.php',
        'Core/Performance/PerformanceBudget.php',
        'Support/Telemetry.php',
        'Database/DatabaseSchema.php',
        'Support/SecurityAccess.php',
        'Support/FinancialGuard.php',
        'Domain/Audit/AuditActivity.php',
        'Domain/Clinic/ClinicConfig.php',
        'Domain/Clinic/SubscriptionSettings.php',
        'Domain/Permissions/UsersPermissions.php',
        'Ui/Components.php',
        'Ui/PublicWeb.php',
        'Presentation/Auth/OnboardingTipView.php',
        'Auth/AuthOnboarding.php',
        'Runtime/Runner.php',
    ];
}

function prontoo_full_runtime_modules(): array
{

    return array_values(array_unique(array_merge(prontoo_runtime_core_modules(), [
        'Application/Patients/PatientContactCommandPort.php',
        'Application/Patients/PatientContactCommandService.php',
        'Infrastructure/Patients/PdoPatientContactCommandRepository.php',
        'Application/Patients/PatientReceptionHistoryReadPort.php',
        'Application/Patients/PatientReceptionHistoryReadService.php',
        'Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php',
        'Application/Patients/PatientTabCommandPort.php',
        'Application/Patients/PatientTabCommandService.php',
        'Infrastructure/Patients/PdoPatientTabCommandRepository.php',
        'Application/Patients/PatientReadPort.php',
        'Application/Patients/PatientReadService.php',
        'Infrastructure/Patients/PdoPatientReadRepository.php',
        'Infrastructure/Patients/PatientTabReadRepository.php',
        'Presentation/Patients/PatientTabView.php',
        'Presentation/Patients/PatientContactView.php',
        'Domain/Patients/Patients.php',
        'Domain/Appointments/Appointments.php',
        'Domain/Leads/Leads.php',
        'Domain/Documents/Documents.php',
        'Domain/Documents/DocumentPdf.php',
        'Domain/Tasks/TasksNotices.php',
        'Application/Financial/PatientRevenueReceiptPort.php',
        'Application/Financial/PatientRevenueReceiptService.php',
        'Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php',
        'Domain/Financial/Financial.php',
        'Domain/Maestro/Maestro.php',
        'Admin/AdminPages.php',
        'Pages/Dashboards.php',
    ])));
}

function prontoo_load_runtime_core_modules(): void
{

    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    prontoo_require_modules(prontoo_use_light_boot() ? prontoo_runtime_core_modules() : prontoo_full_runtime_modules());
}

function prontoo_load_full_runtime_modules(): void
{

    static $done = false;
    if ($done) {
        return;
    }
    prontoo_require_modules(prontoo_full_runtime_modules());
    $done = true;
}

function prontoo_load_route_modules(string $route): void
{

    static $loaded = [];
    foreach (prontoo_route_module_groups($route) as $group => $files) {
        if (isset($loaded[$group])) {
            continue;
        }
        prontoo_require_modules($files);
        $loaded[$group] = true;
    }
}

function prontoo_route_module_groups(string $route): array
{

    $commonClinic = ['dashboards' => ['Pages/Dashboards.php']];
    $patients = ['patients' => [
        'Application/Patients/PatientContactCommandPort.php',
        'Application/Patients/PatientContactCommandService.php',
        'Infrastructure/Patients/PdoPatientContactCommandRepository.php',
        'Application/Patients/PatientReceptionHistoryReadPort.php',
        'Application/Patients/PatientReceptionHistoryReadService.php',
        'Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php',
        'Application/Patients/PatientTabCommandPort.php',
        'Application/Patients/PatientTabCommandService.php',
        'Infrastructure/Patients/PdoPatientTabCommandRepository.php',
        'Application/Patients/PatientReadPort.php',
        'Application/Patients/PatientReadService.php',
        'Infrastructure/Patients/PdoPatientReadRepository.php',
        'Infrastructure/Patients/PatientTabReadRepository.php',
        'Presentation/Patients/PatientTabView.php',
        'Presentation/Patients/PatientContactView.php',
        'Domain/Patients/Patients.php',
    ]];
    $leads = ['leads' => ['Domain/Leads/Leads.php']];
    $tasks = ['tasks' => ['Domain/Tasks/TasksNotices.php']];
    $financial = ['financial' => [
        'Application/Financial/PatientRevenueReceiptPort.php',
        'Application/Financial/PatientRevenueReceiptService.php',
        'Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php',
        'Domain/Financial/Financial.php',
    ]];
    $appointments = ['appointments' => ['Domain/Appointments/Appointments.php']];
    $documents = ['documents' => ['Domain/Documents/Documents.php', 'Domain/Documents/DocumentPdf.php']];
    $audit = ['audit' => ['Domain/Audit/AuditActivity.php']];
    $clinic = ['clinic' => ['Domain/Clinic/ClinicConfig.php', 'Domain/Clinic/SubscriptionSettings.php']];
    $users = ['users' => ['Domain/Appointments/Appointments.php', 'Domain/Permissions/UsersPermissions.php']];
    $admin = ['admin' => ['Domain/Leads/Leads.php', 'Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']];
    $map = [
        'home' => $commonClinic + $appointments + $tasks + $financial + $patients + $leads,
        'painel' => $commonClinic + $appointments + $patients + $leads + $tasks + $financial + $documents,
        'login' => [], 'login_autotest' => [], 'mfa' => [], 'signup' => [], 'logout' => [], 'switch' => [], 'profile' => [], 'global_reauth' => [], 'onboarding' => [], 'mobile_web_access' => [],
        'patient_lookup' => $patients, 'patient_suggest' => $patients, 'person_lookup' => $patients,
        'lead_lookup' => $leads, 'lead_patient_lookup' => $leads + $patients, 'leads' => $leads,
        'appointments' => $appointments + $patients + $tasks + $financial,
        'patients' => $patients + $appointments,
        'patient' => $patients + $documents + $financial + $tasks + $appointments,
        'financial' => $financial + $patients, 'creditors' => $financial + $patients,
        'operations' => $financial, 'goal_status' => $financial,
        'counterparty_lookup' => $financial, 'counterparty_suggest' => $financial,
        'tasks' => $tasks, 'notices' => $tasks,
        'documents' => $documents + $patients + $financial + $appointments,
        'document_view' => $documents + $patients + $financial + $appointments,
        'document_print' => $documents + $patients + $financial + $appointments,
        'document_pdf' => $documents + $patients + $financial + $appointments,
        'document_pdf_file' => $documents + $patients + $financial + $appointments,
        'procedures' => $documents + $financial + $appointments,
        'users' => $users, 'user' => $users, 'permissions' => $users,
        'audit' => $audit + $documents + $financial + $tasks,
        'settings' => $clinic,
        'maestro' => ['maestro' => ['Domain/Maestro/Maestro.php']],
        'admin_painel' => $admin + $clinic, 'admin_stats' => $admin,
        'admin_clinics' => $admin + $clinic, 'admin_onboarding' => $admin,
        'admin_users' => $admin + $users, 'admin_people' => $admin,
        'admin_operations' => $admin + $financial,
        'admin_global_notices' => $tasks + $admin,
        'admin_alerts' => $admin, 'admin_maintenance' => $admin,
        'admin_health' => $admin, 'admin_performance' => $admin,
        'admin_deleted' => $admin, 'admin_errors' => $admin,
        'admin_diagnostics' => $admin, 'admin_integrity' => $admin,
        'admin_security' => $admin, 'admin_settings' => $admin,
        'admin_payment_proof' => $clinic + $admin, 'admin_audit' => $admin,
    ];
    return $map[$route] ?? $commonClinic;
}

function prontoo_runtime_layer_coverage(): array
{

    $modules = prontoo_full_runtime_modules();
    $layers = [];
    foreach ($modules as $module) {
        $layer = prontoo_module_layer((string) $module);
        $layers[$layer] = ($layers[$layer] ?? 0) + 1;
    }
    return [
        'ok' => array_sum($layers) === count($modules),
        'modules' => count($modules),
        'classified' => array_sum($layers),
        'coverage_percent' => $modules !== [] ? 100.0 : 0.0,
        'layers' => $layers,
    ];
}

function prontoo_load_financial_guard_module(): void
{

    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    prontoo_require_module('Domain/Financial/Financial.php');
}
