<?php
declare(strict_types=1);

namespace Prontoo\Runtime;

require_once __DIR__ . '/Routing/RouteCatalog.php';
require_once dirname(__DIR__) . '/Presentation/Http/JsonResponder.php';
require_once __DIR__ . '/Boot/RuntimeBootCoordinator.php';
require_once __DIR__ . '/Patients/PatientComposition.php';
require_once __DIR__ . '/Patients/PatientViewComposition.php';
require_once __DIR__ . '/Financial/FinancialComposition.php';

use Prontoo\Infrastructure\ServerJsonCache\ScopedServerJsonCacheInfrastructureOperations01;
use Prontoo\Presentation\Http\JsonResponder;
use Prontoo\Runtime\Boot\RuntimeBootCoordinator;
use Prontoo\Runtime\Modules\RuntimeModuleComposition;
use Prontoo\Runtime\FinancialGuard\FinancialGuardRuntimeOperations01;
use Prontoo\Runtime\Routing\RouteCatalog;
use Prontoo\Runtime\Routing\PageDispatcher;
use Throwable;

final class Runner
{
    private function __construct()
    {
    }

    public static function run(bool $installMode = false): void
    {
        try {
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::boot_security();
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::guard_request();
            $route = \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route();
            \Prontoo\Infrastructure\Integrity\PiIntegrity::configureRuntimeContext([
                'route' => $route,
                'clinic_id' => (int) ($_SESSION['clinic_id'] ?? 0),
                'user_id' => (int) ($_SESSION['uid'] ?? 0),
                'role' => (string) ($_SESSION['role_code'] ?? ''),
            ]);
            \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_route_identify($route);
            $publicHome = false;
            $publicCompatibility = in_array($route, ['status', 'login_telemetry_wave'], true);
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::headers_secure(false);
            if (!in_array($route, RouteCatalog::all(), true)) {
                \Prontoo\Runtime\PublicWeb\PublicWebRuntimeOperations01::page_public_status();
                return;
            }
            if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg() && !$installMode && !$publicHome && !$publicCompatibility) {
                throw new \ProntooHttpError(
                    503,
                    is_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path('install.lock'))
                        ? 'Instalação existente detectada, mas app/config.php não foi encontrado. O instalador está bloqueado: restaure o arquivo de configuração da instalação atual.'
                        : 'Configuração ausente. A instalação desta publicação está encerrada e não pode ser iniciada por HTTP; restaure app/config.php a partir do ambiente comissionado.',
                );
            }
            $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
            RuntimeBootCoordinator::bootDatabaseForRoute(
                $route,
                RouteCatalog::isPublicLight($route, $method),
                PHP_SAPI === 'cli' && getenv('PRONTOO_FORCE_DEEP_BOOT') === '1',
            );
            $isPost = $method === 'POST';
            if ($isPost) {
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::check_csrf();
            }
            if ($route === 'onboarding' &&
                (int) ($_SESSION['clinic_id'] ?? 0) > 0 &&
                is_callable([\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::class, 'ensure_clinic_trial_active'])) {
                \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::ensure_clinic_trial_active((int) $_SESSION['clinic_id'], true);
            }
            $context = $publicCompatibility || $publicHome || $route === 'logout'
                ? []
                : \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::enforce_read_only($context, $route);
            if ($route !== 'logout' && !$publicCompatibility) {
                LayeredKernel::enforceAction($route, $method, $_POST, $context);
            }
            if ($isPost) {
                ScopedServerJsonCacheInfrastructureOperations01::server_json_cache_schedule_invalidation_for_write(
                    $route,
                    (string) ($_POST['act'] ?? ''),
                    $_POST,
                    (int) ($context['clinic_id'] ?? ($_SESSION['clinic_id'] ?? 0)),
                );
                if ((string) ($_POST['act'] ?? '') === 'onboarding_tip_dismiss') {
                    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::onboarding_tip_dismiss();
                }
            }
            if (!$publicHome &&
                !$publicCompatibility &&
                is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'maintenance_active']) &&
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::maintenance_active() &&
                (!$context || ($context['scope'] ?? '') !== 'global') &&
                !in_array($route, ['login', 'login_autotest', 'mfa', 'logout'], true)) {
                if (RouteCatalog::wantsJson($route, (string) ($_SERVER['HTTP_ACCEPT'] ?? ''))) {
                    JsonResponder::send([
                        'ok' => false,
                        'found' => false,
                        'message' => 'Sistema temporariamente em manutenção. Tente novamente em instantes.',
                    ], 503);
                    return;
                }
                RuntimeModuleComposition::loader()->loadRouteModules('admin_performance');
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::page_maintenance_notice();
                return;
            }
            if ($context &&
                ($context['scope'] ?? '') === 'clinic' &&
                is_callable([\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::class, 'onboarding_pending']) &&
                \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::onboarding_pending($context) &&
                !in_array($route, ['onboarding', 'logout', 'switch', 'settings'], true)) {
                if (RouteCatalog::wantsJson($route, (string) ($_SERVER['HTTP_ACCEPT'] ?? ''))) {
                    JsonResponder::send([
                        'ok' => false,
                        'found' => false,
                        'message' => 'Finalize a configuração inicial do consultório antes de usar esta busca.',
                    ], 409);
                    return;
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect('onboarding');
            }
            if ($context &&
                ($context['scope'] ?? '') === 'clinic' &&
                FinancialGuardRuntimeOperations01::financial_cashier_requires_attention_light($context) &&
                !in_array($route, ['financial', 'logout', 'switch', 'goal_status', 'notices'], true)) {
                if (RouteCatalog::wantsJson($route, (string) ($_SERVER['HTTP_ACCEPT'] ?? ''))) {
                    JsonResponder::send([
                        'ok' => false,
                        'found' => false,
                        'message' => 'Há uma pendência operacional no financeiro antes desta busca.',
                    ], 409);
                    return;
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect('financial');
            }
            RuntimeModuleComposition::loader()->loadRouteModules($route);
            if ($route !== 'logout' && !$publicCompatibility) {
                RuntimeBootCoordinator::flushIntegrityBeforeRender();
            }
            $effectiveRoute = in_array($route, RouteCatalog::all(), true) ? $route : 'home';
            if (!PageDispatcher::dispatch($effectiveRoute)) {
                throw new \RuntimeException('Rota sem handler nativo: ' . $effectiveRoute);
            }
        } catch (Throwable $error) {
            if (is_callable([\Prontoo\Presentation\Financial\FinancialPresentationOperations01::class, 'financial_cash_debug_request_active']) &&
                \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_cash_debug_request_active() &&
                is_callable([\Prontoo\Runtime\Financial\FinancialRuntimeOperations10::class, 'financial_cash_debug_failure_page'])) {
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_cash_debug_failure_page($error);
            }
            $status = $error instanceof \ProntooHttpError ? (int) $error->status : 500;
            $routeForError = is_callable([\Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::class, 'route']) ? \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route() : '';
            if (RouteCatalog::wantsJson($routeForError, (string) ($_SERVER['HTTP_ACCEPT'] ?? ''))) {
                error_log(
                    '[Prontoo json route failure] ' . $routeForError . ' | ' .
                    $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine(),
                );
                JsonResponder::send([
                    'ok' => false,
                    'found' => false,
                    'message' => JsonResponder::failureMessage($routeForError, $status, $error),
                ], $status);
                return;
            }
            if ($status === 404) {
                \Prontoo\Runtime\PublicWeb\PublicWebRuntimeOperations01::page_public_status();
                return;
            }
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_fail($error);
        }
    }
}
