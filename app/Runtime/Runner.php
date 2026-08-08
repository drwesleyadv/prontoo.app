<?php
declare(strict_types=1);

namespace Prontoo\Runtime;

require_once __DIR__ . '/Routing/RouteCatalog.php';
require_once dirname(__DIR__) . '/Presentation/Http/JsonResponder.php';
require_once __DIR__ . '/Boot/RuntimeBootCoordinator.php';
require_once __DIR__ . '/Patients/PatientComposition.php';
require_once __DIR__ . '/Patients/PatientViewComposition.php';
require_once __DIR__ . '/Financial/FinancialComposition.php';

use Prontoo\Presentation\Http\JsonResponder;
use Prontoo\Runtime\Boot\RuntimeBootCoordinator;
use Prontoo\Runtime\Modules\RuntimeModuleComposition;
use Prontoo\Runtime\Routing\RouteCatalog;
use Throwable;

final class Runner
{
    private function __construct()
    {
    }

    public static function run(bool $installMode = false): void
    {
        try {
            \boot_security();
            \guard_request();
            $route = \route();
            \Prontoo\Infrastructure\Integrity\PiIntegrity::configureRuntimeContext([
                'route' => $route,
                'clinic_id' => (int) ($_SESSION['clinic_id'] ?? 0),
                'user_id' => (int) ($_SESSION['uid'] ?? 0),
                'role' => (string) ($_SESSION['role_code'] ?? ''),
            ]);
            \telemetry_route_identify($route);
            $publicTelemetry = $route === 'login_telemetry_wave';
            $publicStatus = $route === 'status' || $publicTelemetry;
            $publicHome = false;
            \headers_secure($publicStatus);
            if (!\has_cfg() && !$installMode && !$publicHome) {
                throw new \ProntooHttpError(
                    503,
                    is_file(\storage_path('install.lock'))
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
                if ($route !== 'login_autotest') {
                    \check_csrf();
                } elseif (empty($_SESSION['csrf'])) {
                    \csrf();
                }
            }
            if ($route === 'onboarding' &&
                (int) ($_SESSION['clinic_id'] ?? 0) > 0 &&
                function_exists('ensure_clinic_trial_active')) {
                \ensure_clinic_trial_active((int) $_SESSION['clinic_id'], true);
            }
            $context = $publicStatus || $publicHome || $route === 'logout' ? [] : \ctx();
            if (!$publicTelemetry) {
                \enforce_read_only($context, $route);
            }
            if ($route !== 'logout' && !$publicTelemetry) {
                LayeredKernel::enforceAction($route, $method, $_POST, $context);
            }
            if ($isPost) {
                if (function_exists('server_json_cache_schedule_invalidation_for_write')) {
                    \server_json_cache_schedule_invalidation_for_write(
                        $route,
                        (string) ($_POST['act'] ?? ''),
                    );
                }
                if ((string) ($_POST['act'] ?? '') === 'onboarding_tip_dismiss') {
                    \onboarding_tip_dismiss();
                }
            }
            if (!$publicStatus &&
                !$publicHome &&
                function_exists('maintenance_active') &&
                \maintenance_active() &&
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
                RuntimeModuleComposition::loader()->loadRouteModules('admin_health');
                \page_maintenance_notice();
                return;
            }
            if ($context &&
                ($context['scope'] ?? '') === 'clinic' &&
                function_exists('onboarding_pending') &&
                \onboarding_pending($context) &&
                !in_array($route, ['onboarding', 'logout', 'switch', 'settings'], true)) {
                if (RouteCatalog::wantsJson($route, (string) ($_SERVER['HTTP_ACCEPT'] ?? ''))) {
                    JsonResponder::send([
                        'ok' => false,
                        'found' => false,
                        'message' => 'Finalize a configuração inicial do consultório antes de usar esta busca.',
                    ], 409);
                    return;
                }
                \redirect('onboarding');
            }
            if ($context &&
                ($context['scope'] ?? '') === 'clinic' &&
                function_exists('financial_cashier_requires_attention_light') &&
                \financial_cashier_requires_attention_light($context) &&
                !in_array($route, ['financial', 'logout', 'switch', 'goal_status', 'notices'], true)) {
                if (RouteCatalog::wantsJson($route, (string) ($_SERVER['HTTP_ACCEPT'] ?? ''))) {
                    JsonResponder::send([
                        'ok' => false,
                        'found' => false,
                        'message' => 'Há uma pendência operacional no financeiro antes desta busca.',
                    ], 409);
                    return;
                }
                \redirect('financial');
            }
            RuntimeModuleComposition::loader()->loadRouteModules($route);
            if ($route !== 'logout' && !$publicStatus) {
                RuntimeBootCoordinator::flushIntegrityBeforeRender();
            }
            $page = in_array($route, RouteCatalog::all(), true) ? 'page_' . $route : 'page_home';
            if (!function_exists($page)) {
                throw new \RuntimeException('Rota sem função de página: ' . $page);
            }
            $page();
        } catch (Throwable $error) {
            if (function_exists('financial_cash_debug_request_active') &&
                \financial_cash_debug_request_active() &&
                function_exists('financial_cash_debug_failure_page')) {
                \financial_cash_debug_failure_page($error);
            }
            $status = $error instanceof \ProntooHttpError ? (int) $error->status : 500;
            $routeForError = function_exists('route') ? \route() : '';
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
            \app_fail($error);
        }
    }
}
