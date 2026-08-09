<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Modules;

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

    public static function routeModuleGroups(string $route): array
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
        $financial = ['financial' => [
            'Application/Financial/PatientRevenueReceiptPort.php',
            'Application/Financial/PatientRevenueReceiptService.php',
            'Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php',
        ]];

        $map = [
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

        return $map[$route] ?? $commonClinic;
    }
}
