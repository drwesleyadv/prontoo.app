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
            'Support/Runtime.php',
            'Support/SecurityPrivacy.php',
            'Support/Foundation.php',
            'Support/ServerJsonCache.php',
            'Core/Performance/PerformanceBudget.php',
            'Support/Telemetry.php',
            'Support/DeferredAudit.php',
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

    public static function routeModuleGroups(string $route): array
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
        $status = ['status' => ['Domain/Maestro/Maestro.php', 'Admin/AdminPages.php']];

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
            'maestro' => ['maestro' => ['Domain/Maestro/Maestro.php']],
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
