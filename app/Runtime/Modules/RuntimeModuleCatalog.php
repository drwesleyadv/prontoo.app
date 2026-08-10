<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Modules;

use ErrorException;
use Throwable;

final class RuntimeModuleCatalog
{
    private function __construct()
    {
    }

    public static function coreModules(): array
    {
        return [
            'Core/Performance/PerformanceBudget.php',
            'Presentation/Auth/OnboardingTipView.php',
            'Runtime/Runner.php',
        ];
    }

    public static function fullModules(): array
    {
        return array_values(array_unique(array_merge(self::coreModules(), [
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
            'Application/Financial/PatientRevenueReceiptPort.php',
            'Application/Financial/PatientRevenueReceiptService.php',
            'Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php',
        ])));
    }

    private static function routeModuleMap(): array
    {
        $commonClinic = [];
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
        ]];
        $leads = [];
        $tasks = [];
        $financial = ['financial' => [
            'Application/Financial/PatientRevenueReceiptPort.php',
            'Application/Financial/PatientRevenueReceiptService.php',
            'Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php',
        ]];
        $appointments = [];
        $documents = [];
        $audit = [];
        $clinic = [];
        $users = [];
        $admin = [];
        $status = [];

        return [
            'home' => $commonClinic + $appointments + $tasks + $financial + $patients + $leads,
            'status' => $status,
            'painel' => $commonClinic + $appointments + $patients + $leads + $tasks + $financial + $documents,
            'login' => [],
            'login_telemetry_wave' => [],
            'login_autotest' => [],
            'mfa' => [],
            'signup' => [],
            'logout' => [],
            'switch' => [],
            'profile' => [],
            'global_reauth' => [],
            'onboarding' => [],
            'mobile_web_access' => [],
            'patient_lookup' => $patients,
            'patient_suggest' => $patients,
            'person_lookup' => $patients,
            'lead_lookup' => $leads,
            'lead_patient_lookup' => $leads + $patients,
            'leads' => $leads,
            'appointments' => $appointments + $patients + $tasks + $financial,
            'patients' => $patients + $appointments,
            'patient' => $patients + $documents + $financial + $tasks + $appointments,
            'financial' => $financial + $patients,
            'creditors' => $financial + $patients,
            'operations' => $financial,
            'goal_status' => $financial,
            'counterparty_lookup' => $financial,
            'counterparty_suggest' => $financial,
            'tasks' => $tasks,
            'notices' => $tasks,
            'documents' => $documents + $patients + $financial + $appointments,
            'document_view' => $documents + $patients + $financial + $appointments,
            'document_print' => $documents + $patients + $financial + $appointments,
            'document_pdf' => $documents + $patients + $financial + $appointments,
            'document_pdf_file' => $documents + $patients + $financial + $appointments,
            'procedures' => $documents + $financial + $appointments,
            'users' => $users,
            'user' => $users,
            'permissions' => $users,
            'audit' => $audit + $documents + $financial + $tasks,
            'settings' => $clinic,
            'maestro' => [],
            'admin_painel' => $admin + $clinic,
            'admin_stats' => $admin,
            'admin_clinics' => $admin + $clinic,
            'admin_onboarding' => $admin,
            'admin_users' => $admin + $users,
            'admin_people' => $admin,
            'admin_operations' => $admin + $financial,
            'admin_global_notices' => $tasks + $admin,
            'admin_alerts' => $admin,
            'admin_maintenance' => $admin,
            'admin_health' => $admin,
            'admin_performance' => $admin,
            'admin_deleted' => $admin,
            'admin_errors' => $admin,
            'admin_diagnostics' => $admin,
            'admin_integrity' => $admin,
            'admin_security' => $admin,
            'admin_settings' => $admin,
            'admin_payment_proof' => $clinic + $admin,
            'admin_audit' => $admin,
        ];
    }

    public static function routeModuleGroups(string $route): array
    {
        return self::routeModuleMap()[$route] ?? [];
    }

    public static function logicSelfTest(array $routes): array
    {
        $failures = [];
        $map = [];
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            },
        );
        try {
            $map = self::routeModuleMap();
            foreach ($routes as $route) {
                $route = (string) $route;
                if (!array_key_exists($route, $map)) {
                    $failures[] = 'route_missing:' . $route;
                    continue;
                }
                $groups = self::routeModuleGroups($route);
                if (!is_array($groups)) {
                    $failures[] = 'groups_invalid:' . $route;
                    continue;
                }
                foreach ($groups as $group => $files) {
                    if (!is_string($group) || $group === '' || !is_array($files)) {
                        $failures[] = 'group_invalid:' . $route;
                        continue;
                    }
                    foreach ($files as $file) {
                        if (!is_string($file) || $file === '') {
                            $failures[] = 'module_invalid:' . $route . ':' . $group;
                        }
                    }
                }
            }
        } catch (Throwable $error) {
            $failures[] = 'runtime_error:' . $error::class . ':' . $error->getMessage();
        } finally {
            restore_error_handler();
        }

        $declared = array_map('strval', array_keys($map));
        $expected = array_map('strval', $routes);
        sort($declared, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($declared !== $expected) {
            $failures[] = 'route_set_mismatch';
        }
        $failures = array_values(array_unique($failures));

        return [
            'ok' => $failures === [],
            'routes_checked' => count($routes),
            'declared_routes' => count($map),
            'failures' => $failures,
        ];
    }
}
