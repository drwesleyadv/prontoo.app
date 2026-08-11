<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Routing;

use LogicException;

final class RouteRegistry
{
    private const SPECS = [
        'home' => [\Prontoo\Runtime\Dashboards\DashboardsRuntimeOperations01::class, 'page_home', false, false, [], ['patients', 'financial']],
        'status' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::class, 'page_status', true, false, ['GET'], []],
        'login' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::class, 'page_login', true, false, ['*'], []],
        'login_telemetry_wave' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::class, 'page_login_telemetry_wave', true, true, ['*'], []],
        'login_autotest' => [\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::class, 'page_login_autotest', true, false, ['*'], []],
        'mfa' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::class, 'page_mfa', true, false, ['*'], []],
        'mobile_web_access' => [\Prontoo\Runtime\PublicWeb\PublicWebRuntimeOperations01::class, 'page_mobile_web_access', true, false, ['GET'], []],
        'goal_status' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations01::class, 'page_goal_status', false, true, [], ['financial']],
        'signup' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::class, 'page_signup', true, false, ['GET'], []],
        'logout' => [\Prontoo\Runtime\AuthOnboarding\LogoutCoordinator::class, 'handle', true, false, ['*'], []],
        'switch' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::class, 'page_switch', false, false, [], []],
        'profile' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations06::class, 'page_profile', false, false, [], []],
        'global_reauth' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::class, 'page_global_reauth', false, false, [], []],
        'onboarding' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::class, 'page_onboarding', false, false, [], []],
        'painel' => [\Prontoo\Runtime\Dashboards\DashboardsRuntimeOperations03::class, 'page_painel', false, false, [], ['patients', 'financial']],
        'operations' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations01::class, 'page_operations', false, false, [], ['financial']],
        'maestro' => [\Prontoo\Runtime\Maestro\MaestroRuntimeOperations02::class, 'page_maestro', false, false, [], []],
        'leads' => [\Prontoo\Runtime\Leads\LeadsRuntimeOperations02::class, 'page_leads', false, false, [], []],
        'appointments' => [\Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations05::class, 'page_appointments', false, false, [], ['patients', 'financial']],
        'patients' => [\Prontoo\Runtime\Patients\PatientsRuntimeOperations05::class, 'page_patients', false, false, [], ['patients']],
        'patient' => [\Prontoo\Runtime\Patients\PatientsRuntimeOperations07::class, 'page_patient', false, false, [], ['patients', 'financial']],
        'patient_lookup' => [\Prontoo\Runtime\Patients\PatientsRuntimeOperations02::class, 'page_patient_lookup', false, true, [], ['patients']],
        'lead_lookup' => [\Prontoo\Runtime\Leads\LeadsRuntimeOperations01::class, 'page_lead_lookup', false, true, [], []],
        'lead_patient_lookup' => [\Prontoo\Runtime\Leads\LeadsRuntimeOperations01::class, 'page_lead_patient_lookup', false, true, [], ['patients']],
        'patient_suggest' => [\Prontoo\Runtime\Patients\PatientsRuntimeOperations04::class, 'page_patient_suggest', false, true, [], ['patients']],
        'person_lookup' => [\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::class, 'page_person_lookup', false, true, [], ['patients']],
        'counterparty_lookup' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations02::class, 'page_counterparty_lookup', false, true, [], ['financial']],
        'counterparty_suggest' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations02::class, 'page_counterparty_suggest', false, true, [], ['financial']],
        'procedures' => [\Prontoo\Runtime\Documents\DocumentsRuntimeOperations06::class, 'page_procedures', false, false, [], ['financial']],
        'financial' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations17::class, 'page_financial', false, false, [], ['patients', 'financial']],
        'creditors' => [\Prontoo\Runtime\Financial\FinancialRuntimeOperations16::class, 'page_creditors', false, false, [], ['patients', 'financial']],
        'tasks' => [\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations04::class, 'page_tasks', false, false, [], []],
        'documents' => [\Prontoo\Runtime\Documents\DocumentsRuntimeOperations05::class, 'page_documents', false, false, [], ['patients', 'financial']],
        'document_view' => [\Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::class, 'page_document_view', false, false, [], ['patients', 'financial']],
        'document_print' => [\Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::class, 'page_document_print', false, false, [], ['patients', 'financial']],
        'document_pdf' => [\Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::class, 'page_document_pdf', false, false, [], ['patients', 'financial']],
        'document_pdf_file' => [\Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::class, 'page_document_pdf_file', false, false, [], ['patients', 'financial']],
        'notices' => [\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations06::class, 'page_notices', false, false, [], []],
        'users' => [\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations04::class, 'page_users', false, false, [], []],
        'user' => [\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations05::class, 'page_user', false, false, [], []],
        'permissions' => [\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations05::class, 'page_permissions', false, false, [], []],
        'audit' => [\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations05::class, 'page_audit', false, false, [], []],
        'settings' => [\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations03::class, 'page_settings', false, false, [], []],
        'admin_painel' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::class, 'page_admin_painel', false, false, [], []],
        'admin_stats' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::class, 'page_admin_stats', false, false, [], []],
        'admin_clinics' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations08::class, 'page_admin_clinics', false, false, [], []],
        'admin_onboarding' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::class, 'page_admin_onboarding', false, false, [], []],
        'admin_users' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::class, 'page_admin_users', false, false, [], []],
        'admin_people' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations06::class, 'page_admin_people', false, false, [], []],
        'admin_operations' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::class, 'page_admin_operations', false, false, [], ['financial']],
        'admin_global_notices' => [\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations03::class, 'page_admin_global_notices', false, false, [], []],
        'admin_alerts' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations09::class, 'page_admin_alerts', false, false, [], []],
        'admin_maintenance' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations05::class, 'page_admin_maintenance', false, false, [], []],
        'admin_health' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::class, 'page_admin_health', false, false, [], []],
        'admin_performance' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations09::class, 'page_admin_performance', false, false, [], []],
        'admin_deleted' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::class, 'page_admin_deleted', false, false, [], []],
        'admin_errors' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::class, 'page_admin_errors', false, false, [], []],
        'admin_diagnostics' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::class, 'page_admin_diagnostics', false, false, [], []],
        'admin_integrity' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations04::class, 'page_admin_integrity', false, false, [], []],
        'admin_security' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations08::class, 'page_admin_security', false, false, [], []],
        'admin_settings' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations05::class, 'page_admin_settings', false, false, [], []],
        'admin_payment_proof' => [\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::class, 'page_admin_payment_proof', false, false, [], []],
        'admin_audit' => [\Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations08::class, 'page_admin_audit', false, false, [], []],
    ];

    private static ?array $definitions = null;

    private function __construct()
    {
    }

    public static function all(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }
        $definitions = [];
        foreach (self::SPECS as $name => $spec) {
            if (!is_array($spec) || count($spec) !== 6) {
                throw new LogicException('Definição de rota inválida: ' . $name);
            }
            [$handlerClass, $handlerMethod, $public, $json, $publicLightMethods, $moduleGroups] = $spec;
            $definitions[$name] = new RouteDefinition(
                $name,
                (string) $handlerClass,
                (string) $handlerMethod,
                (bool) $public,
                (bool) $json,
                array_values(array_map('strval', (array) $publicLightMethods)),
                array_values(array_map('strval', (array) $moduleGroups)),
            );
        }
        return self::$definitions = $definitions;
    }

    public static function definition(string $route): ?RouteDefinition
    {
        return self::all()[$route] ?? null;
    }

    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function publicNames(): array
    {
        return array_keys(array_filter(
            self::all(),
            static fn(RouteDefinition $definition): bool => $definition->public,
        ));
    }

    public static function jsonNames(): array
    {
        return array_keys(array_filter(
            self::all(),
            static fn(RouteDefinition $definition): bool => $definition->json,
        ));
    }

    public static function logicSelfTest(): array
    {
        $failures = [];
        foreach (self::all() as $name => $definition) {
            if ($definition->name !== $name) {
                $failures[] = 'name_mismatch:' . $name;
            }
            if ($definition->handlerClass === '' || $definition->handlerMethod === '') {
                $failures[] = 'handler_missing:' . $name;
            }
            foreach ($definition->publicLightMethods as $method) {
                if ($method !== '*' && !in_array($method, ['GET', 'POST'], true)) {
                    $failures[] = 'light_method_invalid:' . $name . ':' . $method;
                }
            }
            if ($definition->json && $definition->public && $name !== 'login_telemetry_wave') {
                $failures[] = 'unexpected_public_json:' . $name;
            }
        }
        $failures = array_values(array_unique($failures));
        return [
            'ok' => $failures === [],
            'routes' => count(self::all()),
            'public_routes' => count(self::publicNames()),
            'json_routes' => count(self::jsonNames()),
            'failures' => $failures,
        ];
    }
}
