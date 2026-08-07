<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Routing;

final class RouteCatalog
{
    private const ROUTES = [
        'home', 'status', 'login', 'login_telemetry_wave', 'login_autotest', 'mfa',
        'mobile_web_access', 'goal_status', 'signup', 'logout', 'switch', 'profile',
        'global_reauth', 'onboarding', 'painel', 'operations', 'maestro', 'leads',
        'appointments', 'patients', 'patient', 'patient_lookup', 'lead_lookup',
        'lead_patient_lookup', 'patient_suggest', 'person_lookup', 'counterparty_lookup',
        'counterparty_suggest', 'procedures', 'financial', 'creditors', 'tasks',
        'documents', 'document_view', 'document_print', 'document_pdf', 'document_pdf_file',
        'notices', 'users', 'user', 'permissions', 'audit', 'settings', 'admin_painel',
        'admin_stats', 'admin_clinics', 'admin_onboarding', 'admin_users', 'admin_people',
        'admin_operations', 'admin_global_notices', 'admin_alerts', 'admin_maintenance',
        'admin_health', 'admin_performance', 'admin_deleted', 'admin_errors',
        'admin_diagnostics', 'admin_integrity', 'admin_security', 'admin_settings',
        'admin_payment_proof', 'admin_audit',
    ];

    private const PUBLIC_ROUTES = [
        'status', 'login', 'login_telemetry_wave', 'login_autotest', 'mfa',
        'mobile_web_access', 'signup', 'logout',
    ];

    private const JSON_ROUTES = [
        'login_telemetry_wave', 'patient_lookup', 'patient_suggest', 'person_lookup',
        'lead_lookup', 'lead_patient_lookup', 'counterparty_lookup',
        'counterparty_suggest', 'goal_status',
    ];

    private function __construct()
    {
    }

    public static function all(): array
    {
        return self::ROUTES;
    }

    public static function public(): array
    {
        return self::PUBLIC_ROUTES;
    }

    public static function json(): array
    {
        return self::JSON_ROUTES;
    }

    public static function wantsJson(string $route, string $accept): bool
    {
        return in_array($route, self::JSON_ROUTES, true) ||
            str_contains(strtolower($accept), 'application/json');
    }

    public static function isPublicLight(string $route, string $method): bool
    {
        if (in_array($route, ['login', 'login_telemetry_wave', 'login_autotest', 'mfa', 'logout'], true)) {
            return true;
        }
        return in_array($route, ['mobile_web_access', 'signup', 'status'], true) &&
            strtoupper($method) === 'GET';
    }
}
