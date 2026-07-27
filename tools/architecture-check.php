<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

use Prontoo\Core\Architecture\ArchitectureVerifier;
use Prontoo\Runtime\LayeredKernel;

if (!class_exists('ProntooHttpError')) {
    class ProntooHttpError extends RuntimeException
    {
        public function __construct(public int $status, string $message)
        {
            /*
             * GUIA DE MANUTENÇÃO — ProntooHttpError::__construct
             * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
             * Local arquitetural: tools/architecture-check.php (ferramentas de certificação e manutenção).
             * Chamadores detectados: `page_admin_errors`, `page_admin_security`, `Core.Database.SqlScopeGuard::guard`, `Core.Database.SqlScopeGuard::assertScopedWrite`, `Core.Invariant.Context.MaestroContextInvariant::assertWrite`, `Core.Invariant.Context.TaskContextInvariant::deny`, `Core.Invariant.Mutation.MutationInvariant::deny`, `Core.Invariant.Relation.ForeignKeyGraph::deny` e mais 17.
             * Dependências chamadas: `parent::__construct`.
             * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
             * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
             */
            parent::__construct($message);
        }
    }
}

$root = dirname(__DIR__);
if (!defined('PRONTOO_ROOT')) {
    define('PRONTOO_ROOT', $root);
}
$versionMetadata = json_decode((string) file_get_contents($root . '/version.json'), true, 512, JSON_THROW_ON_ERROR);
if (!defined('PRONTOO_VERSION')) {
    define('PRONTOO_VERSION', (string) ($versionMetadata['version'] ?? ''));
}

require_once $root . '/app/bootstrap_architecture.php';
require_once $root . '/app/Support/ModuleLoader.php';
require_once $root . '/app/Runtime/Runner.php';

$architecture = ArchitectureVerifier::report($root, true);
$selfTest = LayeredKernel::logicSelfTest($root);
$dashboardIconCss = (string) file_get_contents(
    $root . '/public/assets/design-system.css',
);
$dashboardIconFailures = [];
if (str_contains(
    $dashboardIconCss,
    '.stat-card span,.ds-kpi span,.notice-kpi span,.kpi-card span,.mini-stat span{',
)) {
    $dashboardIconFailures[] = 'broad_kpi_span_selector';
}
if (str_contains(
    $dashboardIconCss,
    ') > :where(article,div,a,span) :where(span,small,strong){',
)) {
    $dashboardIconFailures[] = 'broad_manager_kpi_descendant_span_selector';
}
foreach ([
    '.stat-card span:not(.material-symbols-rounded):not(.pt-icon-glyph)',
    '.ds-kpi span:not(.material-symbols-rounded):not(.pt-icon-glyph)',
    '.notice-kpi span:not(.material-symbols-rounded):not(.pt-icon-glyph)',
    '.kpi-card span:not(.material-symbols-rounded):not(.pt-icon-glyph)',
    '.mini-stat span:not(.material-symbols-rounded):not(.pt-icon-glyph)',
    ':where(span:not(.material-symbols-rounded):not(.pt-icon-glyph),small,strong)',
    '.manager-action > .pt-icon-glyph',
    '.material-symbols-rounded{font-family:"Material Symbols Rounded"',
] as $requiredIconContract) {
    if (!str_contains($dashboardIconCss, $requiredIconContract)) {
        $dashboardIconFailures[] = $requiredIconContract;
    }
}
$dashboardIconCascade = [
    'ok' => $dashboardIconFailures === [],
    'failed' => $dashboardIconFailures,
];
$logoutModuleSource = (string) file_get_contents(
    $root . '/app/Auth/AuthOnboarding.php',
);
$logoutPageStart = strpos($logoutModuleSource, 'function page_logout(): void');
$logoutPageEnd = $logoutPageStart === false
    ? false
    : strpos($logoutModuleSource, 'function page_profile(): void', $logoutPageStart);
$logoutPageSource =
    $logoutPageStart !== false && $logoutPageEnd !== false
        ? substr($logoutModuleSource, $logoutPageStart, $logoutPageEnd - $logoutPageStart)
        : '';
$logoutCascadeSources = [
    'auth' => (string) file_get_contents($root . '/app/Support/SecurityAccess.php'),
    'cache' => (string) file_get_contents($root . '/app/Support/ServerJsonCache.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
    'loader' => (string) file_get_contents($root . '/app/Support/ModuleLoader.php'),
    'audit' => (string) file_get_contents($root . '/app/Domain/Audit/AuditActivity.php'),
    'audit_chain' => (string) file_get_contents($root . '/app/Core/Integrity/AuditChain.php'),
    'telemetry' => (string) file_get_contents($root . '/app/Support/Telemetry.php'),
    'cron' => (string) file_get_contents($root . '/cron/maestro.php'),
    'logout' => $logoutPageSource,
];
$logoutCascadeFailures = [];
foreach ([
    'auth' => [
        'INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)',
        'hash_equals($userCurrent, $userSession)',
    ],
    'cache' => [
        'if ($route === "logout") {',
        '"user_auth_generation" =>',
        '$_SESSION["user_auth_generation"]',
    ],
    'runner' => [
        '$publicHome || $r === "logout" ? [] : ctx()',
        'if ($r !== "logout") {',
    ],
    'loader' => ["'signup', 'logout'"],
    'audit' => [
        'function audit_trusted_origin_resolve(',
        'str_starts_with((string) $key, "_audit_")',
        '?array $trustedOrigin = null',
        '$c = $skipRuntimeContext ? [] : ctx();',
        '$forcedProofContext',
        'COALESCE(?,NOW())',
    ],
    'audit_chain' => [
        '?array $proofContext = null',
        '$proofContext ?? self::runtimeProofContext()',
    ],
    'telemetry' => [
        'maestro_deferred_enqueue("audit"',
        'maestro_deferred_enqueue("telemetry"',
        'in_array($event["route"], ["login", "logout"], true)',
        'maestro_defer_telemetry_event($event, $includePageMetric);',
        'function maestro_process_deferred_work(',
        'JSON_UNQUOTE(JSON_EXTRACT(context_json',
        '$event["deferred_id"] = $id;',
        '"dead_letter" => 0',
        '$stateDir . "/deferred-work.json"',
    ],
    'cron' => [
        'maestro_process_deferred_work($deferredBudget, 1000)',
        '"deferred_work" => $deferredWork',
        '$__prontooCronDeadline',
    ],
    'logout' => [
        'user_auth_generation_rotate($uid);',
        'maestro_defer_audit_event(',
        'secure_session_destroy();',
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($logoutCascadeSources[$sourceKey], $requiredToken)) {
            $logoutCascadeFailures[] = $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'auth' => [
        'server_json_cache_clear_categories(["context", "meta"])',
        'meta_set(user_auth_generation_key($uid), $generation);',
    ],
    'logout' => [
        'security_retire_persistent_devices_for_user($uid);',
        'audit(',
        '"_skip_runtime_context"',
    ],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($logoutCascadeSources[$sourceKey], $forbiddenToken)) {
            $logoutCascadeFailures[] = $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$logoutCascade = [
    'ok' => $logoutCascadeFailures === [],
    'failed' => $logoutCascadeFailures,
];

$loginApplyStart = strpos(
    $logoutModuleSource,
    'function login_apply_resolved_credential(',
);
$loginApplyEnd = $loginApplyStart === false
    ? false
    : strpos(
        $logoutModuleSource,
        'function developer_first_login_clear_json_cache(',
        $loginApplyStart,
    );
$loginApplySource =
    $loginApplyStart !== false && $loginApplyEnd !== false
        ? substr(
            $logoutModuleSource,
            $loginApplyStart,
            $loginApplyEnd - $loginApplyStart,
        )
        : '';
$loginPerformanceSources = [
    'auth' => $logoutModuleSource,
    'login_apply' => $loginApplySource,
    'security' => (string) file_get_contents(
        $root . '/app/Support/SecurityAccess.php',
    ),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
];
$loginPerformanceFailures = [];
foreach ([
    'auth' => [
        'JOIN pi_users u ON u.person_id=p.id',
        "VALUES\n               (?,?,1,UNIX_TIMESTAMP()+2,NOW()),",
        'meta_value=IF(meta_value<>VALUES(meta_value),VALUES(meta_value),meta_value)',
        "LEFT JOIN pi_meta m ON m.meta_key=CONCAT('auth_user_',u.id)",
        'server_json_cache_file(',
        'developer_first_login_clear_json_cache($uid, true);',
    ],
    'login_apply' => [
        '"skip_runtime_context" => true',
        '"skip_context_enrichment" => true',
        'session_harden_after_login($uid, $verifiedUserGeneration);',
    ],
    'security' => [
        '.security-storage-',
        '$storageGuardFilesPresent',
        'static $secret = null;',
        '?string $verifiedUserGeneration = null',
    ],
    'runner' => [
        '["route_deep", "post_password_login"]',
        '"reason" => "runtime_marker_fresh"',
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($loginPerformanceSources[$sourceKey], $requiredToken)) {
            $loginPerformanceFailures[] =
                $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'login_apply' => ['security_retire_persistent_devices_for_user($uid);'],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($loginPerformanceSources[$sourceKey], $forbiddenToken)) {
            $loginPerformanceFailures[] =
                $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$loginPerformance = [
    'ok' => $loginPerformanceFailures === [],
    'failed' => $loginPerformanceFailures,
];

$mfaPendingStart = strpos(
    $logoutModuleSource,
    'function mfa_pending_login_user(): ?array',
);
$mfaPendingEnd = $mfaPendingStart === false
    ? false
    : strpos(
        $logoutModuleSource,
        'function mfa_complete_pending_login(',
        $mfaPendingStart,
    );
$mfaPendingSource =
    $mfaPendingStart !== false && $mfaPendingEnd !== false
        ? substr(
            $logoutModuleSource,
            $mfaPendingStart,
            $mfaPendingEnd - $mfaPendingStart,
        )
        : '';
$mfaPageStart = strpos($logoutModuleSource, 'function page_mfa(): void');
$mfaPageEnd = $mfaPageStart === false
    ? false
    : strpos(
        $logoutModuleSource,
        'function page_global_reauth(): void',
        $mfaPageStart,
    );
$mfaPageSource =
    $mfaPageStart !== false && $mfaPageEnd !== false
        ? substr(
            $logoutModuleSource,
            $mfaPageStart,
            $mfaPageEnd - $mfaPageStart,
        )
        : '';
$inlineMfaSources = [
    'auth' => $logoutModuleSource,
    'pending' => $mfaPendingSource,
    'mfa_page' => $mfaPageSource,
    'catalog' => (string) file_get_contents(
        $root . '/app/Application/Authorization/ActionCatalog.php',
    ),
    'javascript' => (string) file_get_contents(
        $root . '/public/assets/app.js',
    ),
    'css' => $dashboardIconCss,
];
$inlineMfaFailures = [];
foreach ([
    'auth' => [
        'if ($isGlobalAdmin || $enrolled) {',
        'mfa_enrollment_state((int) ($pendingMfaUser["id"] ?? 0))',
        '$mfaState === "unavailable"',
        'mfa_complete_pending_login(!$wantsJson)',
        'login_apply_resolved_credential(',
        'null,' . "\n" . '            !$wantsJson,',
        '"redirect" => href($destination)',
        '"csrf" => csrf()',
        '$isGlobalAdmin = (int) ($user["is_global_admin"] ?? 0) === 1;',
        'unset($_SESSION["privileged_auth_at"]);',
        'icon("security_key")',
        'profile_mfa_prepare',
        'profile_mfa_enable',
        'profile_mfa_recovery_regenerate',
        'profile_mfa_replace_enable',
        'profile_mfa_disable',
        'profile_mfa_recovery_codes_issued_at',
        'user_auth_generation_rotate($uid)',
        'Proteção Avançada',
        'Verificação em duas etapas',
        'Caso utilize um aplicativo autenticador, ative-o aqui.',
        'Habilitar Proteção Avançada',
    ],
    'catalog' => [
        "\$add('login', 'mfa_verify', 'public'",
        "'profile_mfa_recovery_ack', 'profile_mfa_recovery_regenerate'",
    ],
    'javascript' => [
        'function initLoginMfaFlow(root = d)',
        'const enterMfaStage = (data) =>',
        'row.innerHTML =',
        'Accept: "application/json"',
        'data-login-code',
    ],
    'css' => [
        '[data-login-cpf][readonly]',
        '.login-mfa-help',
        '.account-mfa-panel',
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($inlineMfaSources[$sourceKey], $requiredToken)) {
            $inlineMfaFailures[] =
                $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'pending' => ['($user["is_global_admin"] ?? 0) !== 1'],
    'mfa_page' => [
        'security-verification-card',
        '<input type="hidden" name="act" value="mfa_verify">',
    ],
    'auth' => [
        'Segundo fator do Desenvolvedor validado na mesma tela do login',
        'Acrescente um código do autenticador ao login deste usuário.',
        '<span>Ativar MFA</span>',
        'icon("phonelink_lock")',
    ],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($inlineMfaSources[$sourceKey], $forbiddenToken)) {
            $inlineMfaFailures[] =
                $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$inlineMfa = [
    'ok' => $inlineMfaFailures === [],
    'failed' => $inlineMfaFailures,
];

$result = [
    'ok' =>
        !empty($architecture['ok']) &&
        !empty($selfTest['ok']) &&
        !empty($dashboardIconCascade['ok']) &&
        !empty($logoutCascade['ok']) &&
        !empty($loginPerformance['ok']) &&
        !empty($inlineMfa['ok']),
    'architecture' => $architecture,
    'self_test' => $selfTest,
    'dashboard_icon_cascade' => $dashboardIconCascade,
    'logout_cascade' => $logoutCascade,
    'login_performance' => $loginPerformance,
    'inline_mfa' => $inlineMfa,
];

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
), PHP_EOL;

exit($result['ok'] ? 0 : 1);
