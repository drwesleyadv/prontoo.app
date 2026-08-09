<?php
declare(strict_types=1);
namespace Prontoo\Runtime\Routing;
final class PageDispatcher
{
    private const HANDLERS = [
        'admin_alerts' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations09::class, 'page_admin_alerts'],
        'admin_audit' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations08::class, 'page_admin_audit'],
        'admin_clinics' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations08::class, 'page_admin_clinics'],
        'admin_deleted' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::class, 'page_admin_deleted'],
        'admin_diagnostics' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::class, 'page_admin_diagnostics'],
        'admin_errors' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::class, 'page_admin_errors'],
        'admin_global_notices' => [\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations03::class, 'page_admin_global_notices'],
        'admin_health' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::class, 'page_admin_health'],
        'admin_integrity' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::class, 'page_admin_integrity'],
        'admin_maintenance' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations05::class, 'page_admin_maintenance'],
        'admin_onboarding' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::class, 'page_admin_onboarding'],
        'admin_operations' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::class, 'page_admin_operations'],
        'admin_painel' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::class, 'page_admin_painel'],
        'admin_payment_proof' => [\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::class, 'page_admin_payment_proof'],
        'admin_people' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::class, 'page_admin_people'],
        'admin_performance' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations09::class, 'page_admin_performance'],
        'admin_security' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations08::class, 'page_admin_security'],
        'admin_settings' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations05::class, 'page_admin_settings'],
        'admin_stats' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::class, 'page_admin_stats'],
        'admin_users' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::class, 'page_admin_users'],
        'appointments' => [\Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations05::class, 'page_appointments'],
        'audit' => [\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations05::class, 'page_audit'],
        'counterparty_lookup' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations02::class, 'page_counterparty_lookup'],
        'counterparty_suggest' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations02::class, 'page_counterparty_suggest'],
        'creditors' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations16::class, 'page_creditors'],
        'document_pdf' => [\Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::class, 'page_document_pdf'],
        'document_pdf_file' => [\Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::class, 'page_document_pdf_file'],
        'document_print' => [\Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::class, 'page_document_print'],
        'document_view' => [\Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::class, 'page_document_view'],
        'documents' => [\Prontoo\Runtime\Documents\DocumentsRuntimeOperations05::class, 'page_documents'],
        'financial' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations17::class, 'page_financial'],
        'global_reauth' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::class, 'page_global_reauth'],
        'goal_status' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations01::class, 'page_goal_status'],
        'head' => [\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::class, 'page_head'],
        'head_icon_name' => [\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::class, 'page_head_icon_name'],
        'home' => [\Prontoo\Runtime\Dashboards\DashboardsRuntimeOperations01::class, 'page_home'],
        'lead_lookup' => [\Prontoo\Runtime\Leads\LeadsRuntimeOperations01::class, 'page_lead_lookup'],
        'lead_patient_lookup' => [\Prontoo\Runtime\Leads\LeadsRuntimeOperations01::class, 'page_lead_patient_lookup'],
        'leads' => [\Prontoo\Runtime\Leads\LeadsRuntimeOperations02::class, 'page_leads'],
        'login' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::class, 'page_login'],
        'login_autotest' => [\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::class, 'page_login_autotest'],
        'login_telemetry_wave' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::class, 'page_login_telemetry_wave'],
        'logout' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::class, 'page_logout'],
        'maestro' => [\Prontoo\Runtime\Maestro\MaestroRuntimeOperations02::class, 'page_maestro'],
        'maintenance_notice' => [\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'page_maintenance_notice'],
        'mfa' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::class, 'page_mfa'],
        'mobile_web_access' => [\Prontoo\Runtime\PublicWeb\PublicWebRuntimeOperations01::class, 'page_mobile_web_access'],
        'notices' => [\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations06::class, 'page_notices'],
        'onboarding' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::class, 'page_onboarding'],
        'operation_specs' => [\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::class, 'page_operation_specs'],
        'operations' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations01::class, 'page_operations'],
        'operations_html' => [\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::class, 'page_operations_html'],
        'painel' => [\Prontoo\Runtime\Dashboards\DashboardsRuntimeOperations03::class, 'page_painel'],
        'patient' => [\Prontoo\Runtime\Patients\PatientsRuntimeOperations07::class, 'page_patient'],
        'patient_lookup' => [\Prontoo\Runtime\Patients\PatientsRuntimeOperations02::class, 'page_patient_lookup'],
        'patient_suggest' => [\Prontoo\Runtime\Patients\PatientsRuntimeOperations04::class, 'page_patient_suggest'],
        'patients' => [\Prontoo\Runtime\Patients\PatientsRuntimeOperations05::class, 'page_patients'],
        'permissions' => [\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations05::class, 'page_permissions'],
        'person_lookup' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::class, 'page_person_lookup'],
        'procedures' => [\Prontoo\Runtime\Documents\DocumentsRuntimeOperations06::class, 'page_procedures'],
        'profile' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations06::class, 'page_profile'],
        'settings' => [\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations03::class, 'page_settings'],
        'signup' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::class, 'page_signup'],
        'status' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::class, 'page_status'],
        'switch' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::class, 'page_switch'],
        'tasks' => [\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations04::class, 'page_tasks'],
        'user' => [\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations05::class, 'page_user'],
        'users' => [\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations04::class, 'page_users'],
    ];
    private function __construct() {}
    public static function nativeRoutes(): array { return array_keys(self::HANDLERS); }
    public static function hasNativeHandler(string $route): bool { return isset(self::HANDLERS[$route]); }
    public static function dispatch(string $route): bool
    {
        $handler = self::HANDLERS[$route] ?? null;
        if (!is_array($handler) || count($handler) !== 2) return false;
        [$class, $method] = $handler;
        $class::$method();
        return true;
    }
}
