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
        'Além da senha, você usará um código do aplicativo autenticador para entrar.',
        'Começar configuração',
        'Configurar com uma chave manual',
        'Gerenciar verificação em duas etapas',
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
        'Código de verificação',
        'Confirmar e entrar',
    ],
    'css' => [
        '[data-login-cpf][readonly]',
        '.login-mfa-help',
        '.account-mfa-panel',
        '.mfa-setup-step',
        '.account-mfa-option-head',
        '.security-reauth-form .field-help',
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
        'Caso utilize um aplicativo autenticador, ative-o aqui.',
        'Habilitar Proteção Avançada',
        '<h3 id="account-mfa-title">MFA ativo</h3>',
        'form_row(' . "\n" . '                "Código MFA"',
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

$operationalUiSources = [
    'appointments' => (string) file_get_contents(
        $root . '/app/Domain/Appointments/Appointments.php',
    ),
    'leads' => (string) file_get_contents(
        $root . '/app/Domain/Leads/Leads.php',
    ),
    'procedures' => (string) file_get_contents(
        $root . '/app/Domain/Documents/Documents.php',
    ),
    'javascript' => (string) file_get_contents(
        $root . '/public/assets/app.js',
    ),
    'css' => $dashboardIconCss,
];
$operationalUiFailures = [];
foreach ([
    'appointments' => [
        'bool $registeredOnly = false',
        '$registeredOnly && $procedures === []',
        'Cadastre Procedimentos primeiro',
        'if (!$registeredOnly) {',
        'if ($act === "create" && !$procId) {',
        'Selecione um procedimento cadastrado.',
        'procedure_select_html($cid, "reason", "", true)',
        'AND active=1 FOR UPDATE',
        'O procedimento selecionado não está mais disponível.',
        '(string) $lockedProcedure["title"]',
    ],
    'leads' => [
        'action_summary_label("Tornar Paciente", "person_add")',
        'action_summary_label("Registrar contato", "forum")',
        '<footer><div class="lead-actions">',
    ],
    'procedures' => [
        '$_SESSION["procedure_create_submission_tokens"]',
        'isset($submissionTokens[$submissionToken])',
        'unset($submissionTokens[$submissionToken]);',
        'procedure_submission_token',
        'data-submit-once',
        'array_slice(',
        'random_bytes(24)',
        'Este cadastro já foi enviado.',
    ],
    'javascript' => [
        'form.matches?.("[data-submit-once]")',
        'form.dataset.submitting === "1"',
        'form.dataset.submitting = "1";',
        'button.disabled = true;',
    ],
    'css' => [
        '.lead-card-expanded .lead-actions > .lead-card-details:not([open])',
        'padding:0!important;',
        '.lead-card-expanded .lead-actions > .lead-card-details > summary.cmdlike',
        'min-height:34px!important;',
        '.lead-card-expanded .lead-actions > .lead-card-details[open]',
        'flex:1 0 100%!important;',
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($operationalUiSources[$sourceKey], $requiredToken)) {
            $operationalUiFailures[] =
                $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'appointments' => ['procedure_select_html($cid, "reason") .'],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($operationalUiSources[$sourceKey], $forbiddenToken)) {
            $operationalUiFailures[] =
                $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$operationalUi = [
    'ok' => $operationalUiFailures === [],
    'failed' => $operationalUiFailures,
];

require_once $root . '/app/Domain/Identity/IdentityDocumentValidator.php';
require_once $root . '/app/Domain/Patients/PatientPure.php';

$phaseOneFailures = [];
$phaseOneAssert = static function (bool $condition, string $name) use (&$phaseOneFailures): void {
    if (!$condition) {
        $phaseOneFailures[] = $name;
    }
};
$phaseOneAssert(
    \Prontoo\Domain\Identity\IdentityDocumentValidator::cpf('52998224725'),
    'cpf_valid',
);
$phaseOneAssert(
    !\Prontoo\Domain\Identity\IdentityDocumentValidator::cpf('11111111111'),
    'cpf_repeated_rejected',
);
$phaseOneAssert(
    !\Prontoo\Domain\Identity\IdentityDocumentValidator::cpf('52998224724'),
    'cpf_invalid',
);
$phaseOneAssert(
    \Prontoo\Domain\Identity\IdentityDocumentValidator::cnpj('11222333000181'),
    'cnpj_valid',
);
$phaseOneAssert(
    !\Prontoo\Domain\Identity\IdentityDocumentValidator::cnpj('11222333000180'),
    'cnpj_invalid',
);
$phaseOneAssert(
    \Prontoo\Domain\Identity\IdentityDocumentValidator::birthDate('2000-01-01'),
    'birth_valid',
);
$phaseOneAssert(
    !\Prontoo\Domain\Identity\IdentityDocumentValidator::birthDate('2000-02-31'),
    'birth_invalid',
);
$phaseOneAssert(
    !\Prontoo\Domain\Identity\IdentityDocumentValidator::birthDate('2999-01-01'),
    'birth_future',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::cpfBr('52998224725', '52998224725') === '529.982.247-25',
    'patient_cpf_format',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::cpfBr('ABC', '') === 'ABC',
    'patient_cpf_fallback',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::cleanTabLabel(' <b> Histórico   clínico </b> ') === 'Histórico clínico',
    'patient_tab_label',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::tabRecordType(-1) === 'tab_0',
    'patient_tab_record_type',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::tabKey(7) === 'extra7',
    'patient_tab_key',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::normalizeGuardianRelationship('MAE') === 'mae',
    'guardian_relationship_known',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::normalizeGuardianRelationship('desconhecido') === 'outro',
    'guardian_relationship_fallback',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::ageYears('') === null,
    'patient_age_empty',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::ageYears('2999-01-01') === null,
    'patient_age_future',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::isMinor(['birth_date' => gmdate('Y-m-d', strtotime('-10 years'))]),
    'patient_minor',
);
foreach ([
    $root . '/app/Domain/Identity/IdentityDocumentValidator.php',
    $root . '/app/Domain/Patients/PatientPure.php',
] as $pureFile) {
    $pureSource = (string) file_get_contents($pureFile);
    foreach (['$_GET', '$_POST', '$_SESSION', 'PDO', 'header(', ' q(', ' one('] as $forbidden) {
        if (str_contains($pureSource, $forbidden)) {
            $phaseOneFailures[] = basename($pureFile) . ':forbidden:' . $forbidden;
        }
    }
}
foreach ([
    $root . '/app/Auth/AuthOnboarding.php' => [
        'IdentityDocumentValidator::cpf',
        'IdentityDocumentValidator::cnpj',
        'IdentityDocumentValidator::birthDate',
    ],
    $root . '/app/Domain/Patients/Patients.php' => [
        'PatientPure::cpfBr',
        'PatientPure::cleanTabLabel',
        'PatientPure::guardianRelationshipOptions',
        'PatientPure::ageYears',
    ],
] as $facadeFile => $requiredDelegations) {
    $facadeSource = (string) file_get_contents($facadeFile);
    foreach ($requiredDelegations as $requiredDelegation) {
        if (!str_contains($facadeSource, $requiredDelegation)) {
            $phaseOneFailures[] = basename($facadeFile) . ':missing:' . $requiredDelegation;
        }
    }
}
$phaseOneCharacterization = [
    'ok' => $phaseOneFailures === [],
    'failed' => $phaseOneFailures,
];

require_once $root . '/app/Infrastructure/Patients/PatientTabReadRepository.php';
require_once $root . '/app/Presentation/Patients/PatientTabView.php';
require_once $root . '/app/Presentation/Auth/OnboardingTipView.php';

$phaseTwoFailures = [];
$phaseTwoAssert = static function (bool $condition, string $name) use (&$phaseTwoFailures): void {
    if (!$condition) {
        $phaseTwoFailures[] = $name;
    }
};
$phaseTwoEscape = static fn(string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8',
);
$phaseTwoIcon = static fn(string $name): string => '<i>' . $phaseTwoEscape($name) . '</i>';

$phaseTwoPicker = \Prontoo\Presentation\Patients\PatientTabView::iconPicker(
    ['clinical_notes' => 'Nota & alerta'],
    'clinical_notes',
    $phaseTwoEscape,
    $phaseTwoIcon,
);
$phaseTwoAssert(
    $phaseTwoPicker === '<div class="visual-option-grid patient-health-icon-grid patient-tab-icon-symbol-grid" role="radiogroup" aria-label="Ícone da aba"><label class="visual-option patient-health-icon-choice patient-health-icon-only" title="Nota &amp; alerta" aria-label="Nota &amp; alerta"><input type="radio" name="tab_icon" value="clinical_notes" checked aria-label="Nota &amp; alerta"><span class="patient-tab-icon-symbol"><i>clinical_notes</i></span><span class="sr-only">Nota &amp; alerta</span></label></div>',
    'patient_tab_view_snapshot',
);

$phaseTwoTip = \Prontoo\Presentation\Auth\OnboardingTipView::render(
    [
        'icon' => 'patient_list',
        'title' => 'Título & teste',
        'body' => 'Corpo <seguro>',
    ],
    'patients:role:"',
    '/?r=patients&x="',
    '<input type="hidden" name="_csrf" value="token">',
    $phaseTwoEscape,
    $phaseTwoIcon,
);
$phaseTwoAssert(
    $phaseTwoTip === '<section class="onboarding-tip-card" role="note"><div class="onboarding-tip-main"><div class="onboarding-tip-head"><span class="onboarding-tip-icon"><i>patient_list</i></span><strong>Título &amp; teste</strong></div><p class="onboarding-tip-body">Corpo &lt;seguro&gt;</p></div><form method="post" action="/?r=patients&amp;x=&quot;" class="onboarding-tip-action"><input type="hidden" name="_csrf" value="token"><input type="hidden" name="act" value="onboarding_tip_dismiss"><input type="hidden" name="tip_key" value="patients:role:&quot;"><input type="hidden" name="return_to" value="/?r=patients&amp;x=&quot;"><button type="submit" class="ghost small onboarding-tip-button">Entendi</button></form></section>',
    'onboarding_tip_view_snapshot',
);

$phaseTwoSources = [
    'patients_facade' => (string) file_get_contents($root . '/app/Domain/Patients/Patients.php'),
    'auth_facade' => (string) file_get_contents($root . '/app/Auth/AuthOnboarding.php'),
    'repository' => (string) file_get_contents($root . '/app/Infrastructure/Patients/PatientTabReadRepository.php'),
    'patient_view' => (string) file_get_contents($root . '/app/Presentation/Patients/PatientTabView.php'),
    'onboarding_view' => (string) file_get_contents($root . '/app/Presentation/Auth/OnboardingTipView.php'),
    'loader' => (string) file_get_contents($root . '/app/Support/ModuleLoader.php'),
];
foreach ([
    'patients_facade' => [
        'prontoo_patient_tab_active_rows($cid, $patientId)',
        'prontoo_patient_tab_label_by_id($id)',
        'prontoo_patient_tab_icon_picker(patient_health_icon_options(), $current)',
    ],
    'auth_facade' => [
        'prontoo_onboarding_tip_render($tip, $key, $return, csrf_field())',
    ],
    'repository' => [
        'SELECT id,label,icon_name,sort_order,created_at FROM pi_patient_tabs',
        'SELECT label FROM pi_patient_tabs WHERE id=? LIMIT 1',
    ],
    'loader' => [
        "'Infrastructure/Patients/PatientTabReadRepository.php'",
        "'Presentation/Patients/PatientTabView.php'",
        "'Presentation/Auth/OnboardingTipView.php'",
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($phaseTwoSources[$sourceKey], $requiredToken)) {
            $phaseTwoFailures[] = $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'patients_facade' => [
        'SELECT id,label,icon_name,sort_order,created_at FROM pi_patient_tabs',
        '<div class="visual-option-grid patient-health-icon-grid',
    ],
    'auth_facade' => [
        '<section class="onboarding-tip-card"',
    ],
    'repository' => [
        '<section',
        '<div class=',
        '$_GET',
        '$_POST',
        '$_SESSION',
    ],
    'patient_view' => [
        'SELECT ',
        ' q(',
        ' one(',
        '$_GET',
        '$_POST',
        '$_SESSION',
    ],
    'onboarding_view' => [
        'SELECT ',
        ' q(',
        ' one(',
        '$_GET',
        '$_POST',
        '$_SESSION',
    ],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($phaseTwoSources[$sourceKey], $forbiddenToken)) {
            $phaseTwoFailures[] = $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$phaseTwoCharacterization = [
    'ok' => $phaseTwoFailures === [],
    'failed' => $phaseTwoFailures,
];

require_once $root . '/app/Application/Patients/PatientReadPort.php';
require_once $root . '/app/Application/Patients/PatientReadService.php';
require_once $root . '/app/Infrastructure/Patients/PdoPatientReadRepository.php';

$phaseThreeFailures = [];
$phaseThreeAssert = static function (bool $condition, string $name) use (&$phaseThreeFailures): void {
    if (!$condition) {
        $phaseThreeFailures[] = $name;
    }
};
$phaseThreePort = new class implements \Prontoo\Application\Patients\PatientReadPort {
    public ?array $patient = null;
    public array $guardians = [];
    public bool $hasGuardian = false;

    public function appointmentRegistration(int $clinicId, int $patientId): ?array
    {
        return $this->patient;
    }

    public function activeLegalGuardians(int $clinicId, int $patientId): array
    {
        return $this->guardians;
    }

    public function hasActiveLegalGuardian(int $clinicId, int $patientId): bool
    {
        return $this->hasGuardian;
    }
};
$phaseThreeService = new \Prontoo\Application\Patients\PatientReadService($phaseThreePort);
$phaseThreeComplete = static fn(array $patient): bool => (string) ($patient['state'] ?? '') === 'complete';
$phaseThreeAlert = static fn(array $patient): string => 'alert:' . (string) ($patient['state'] ?? '');
$phaseThreeDigits = static fn(string $value): string => (string) preg_replace('/\D+/', '', $value);
$phaseThreeRelationship = static fn(string $value): string => strtolower($value) === 'mae' ? 'mae' : 'outro';

$phaseThreeAssert(
    $phaseThreeService->appointmentRegistrationBlockReason(0, 1, $phaseThreeComplete, $phaseThreeAlert) === null,
    'appointment_registration_invalid_scope',
);
$phaseThreeAssert(
    $phaseThreeService->appointmentRegistrationBlockReason(1, 2, $phaseThreeComplete, $phaseThreeAlert) === 'Paciente não encontrado no consultório atual.',
    'appointment_registration_not_found',
);
$phaseThreePort->patient = ['state' => 'complete'];
$phaseThreeAssert(
    $phaseThreeService->appointmentRegistrationBlockReason(1, 2, $phaseThreeComplete, $phaseThreeAlert) === null,
    'appointment_registration_complete',
);
$phaseThreePort->patient = ['state' => 'incomplete'];
$phaseThreeAssert(
    $phaseThreeService->appointmentRegistrationBlockReason(1, 2, $phaseThreeComplete, $phaseThreeAlert) === 'alert:incomplete',
    'appointment_registration_incomplete',
);
$phaseThreeAssert(
    $phaseThreeService->legalGuardians(0, 2, $phaseThreeDigits, $phaseThreeRelationship) === [],
    'legal_guardians_invalid_scope',
);
$phaseThreePort->guardians = [[
    'id' => '7',
    'full_name' => 'Responsável',
    'cpf' => '529.982.247-25',
    'relationship' => 'MAE',
    'is_primary' => '1',
]];
$phaseThreeGuardians = $phaseThreeService->legalGuardians(
    1,
    2,
    $phaseThreeDigits,
    $phaseThreeRelationship,
);
$phaseThreeAssert(
    count($phaseThreeGuardians) === 1 &&
    ($phaseThreeGuardians[0]['id'] ?? null) === 7 &&
    ($phaseThreeGuardians[0]['cpf'] ?? null) === '52998224725' &&
    ($phaseThreeGuardians[0]['relationship'] ?? null) === 'mae' &&
    ($phaseThreeGuardians[0]['is_primary'] ?? null) === 1,
    'legal_guardians_normalization',
);
$phaseThreeAssert(
    !$phaseThreeService->hasLegalGuardian(0, 2),
    'legal_guardian_invalid_scope',
);
$phaseThreePort->hasGuardian = true;
$phaseThreeAssert(
    $phaseThreeService->hasLegalGuardian(1, 2),
    'legal_guardian_exists',
);

$phaseThreeSources = [
    'port' => (string) file_get_contents($root . '/app/Application/Patients/PatientReadPort.php'),
    'service' => (string) file_get_contents($root . '/app/Application/Patients/PatientReadService.php'),
    'repository' => (string) file_get_contents($root . '/app/Infrastructure/Patients/PdoPatientReadRepository.php'),
    'patients_facade' => (string) file_get_contents($root . '/app/Domain/Patients/Patients.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
    'loader' => (string) file_get_contents($root . '/app/Support/ModuleLoader.php'),
];
foreach ([
    'port' => [
        'appointmentRegistration(int $clinicId, int $patientId): ?array',
        'activeLegalGuardians(int $clinicId, int $patientId): array',
        'hasActiveLegalGuardian(int $clinicId, int $patientId): bool',
    ],
    'service' => [
        '$this->port->appointmentRegistration($clinicId, $patientId)',
        '$this->port->activeLegalGuardians($clinicId, $patientId)',
        '$this->port->hasActiveLegalGuardian($clinicId, $patientId)',
    ],
    'repository' => [
        'WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1 LIMIT 1',
        'WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY is_primary DESC, id ASC',
        'SELECT COUNT(*) FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1',
    ],
    'patients_facade' => [
        'prontoo_patient_appointment_registration_block_reason($cid, $patientId)',
        'prontoo_patient_legal_guardians($cid, $patientId)',
        'prontoo_patient_has_legal_guardian($cid, $patientId)',
    ],
    'runner' => [
        'new \\Prontoo\\Infrastructure\\Patients\\PdoPatientReadRepository()',
        'appointmentRegistrationBlockReason(',
        'legalGuardians(',
        'hasLegalGuardian(',
    ],
    'loader' => [
        "'Application/Patients/PatientReadPort.php'",
        "'Application/Patients/PatientReadService.php'",
        "'Infrastructure/Patients/PdoPatientReadRepository.php'",
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($phaseThreeSources[$sourceKey], $requiredToken)) {
            $phaseThreeFailures[] = $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'port' => ['SELECT ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION', '<div', '<section'],
    'service' => ['SELECT ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION', '<div', '<section'],
    'repository' => ['<div', '<section', '$_GET', '$_POST', '$_SESSION'],
    'patients_facade' => [
        'SELECT pp.id,pp.clinic_id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,p.full_name,p.cpf,p.birth_date FROM pi_patients',
        'SELECT id,full_name,cpf,relationship,phone,email,document_note,notes,is_primary,created_at,updated_at FROM pi_patient_guardians',
        'SELECT COUNT(*) FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1',
    ],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($phaseThreeSources[$sourceKey], $forbiddenToken)) {
            $phaseThreeFailures[] = $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$phaseThreeCharacterization = [
    'ok' => $phaseThreeFailures === [],
    'failed' => $phaseThreeFailures,
];
require_once $root . '/app/Application/Patients/PatientTabCommandPort.php';
require_once $root . '/app/Application/Patients/PatientTabCommandService.php';
require_once $root . '/app/Infrastructure/Patients/PdoPatientTabCommandRepository.php';

$phaseFourFailures = [];
$phaseFourAssert = static function (bool $condition, string $name) use (&$phaseFourFailures): void {
    if (!$condition) {
        $phaseFourFailures[] = $name;
    }
};
$phaseFourPort = new class implements \Prontoo\Application\Patients\PatientTabCommandPort {
    public array $result = ['status' => 'created', 'id' => 9, 'sort_order' => 20];
    public array $received = [];

    public function createIfAbsent(
        int $clinicId,
        int $patientId,
        string $label,
        string $iconName,
        int $userId,
    ): array {
        $this->received = [$clinicId, $patientId, $label, $iconName, $userId];
        return $this->result;
    }
};
$phaseFourService = new \Prontoo\Application\Patients\PatientTabCommandService($phaseFourPort);
$phaseFourCreated = $phaseFourService->create(3, 7, 'Evolução', 'clinical_notes', 11);
$phaseFourAssert(
    $phaseFourCreated === ['status' => 'created', 'id' => 9, 'sort_order' => 20] &&
    $phaseFourPort->received === [3, 7, 'Evolução', 'clinical_notes', 11],
    'patient_tab_command_created',
);
$phaseFourPort->result = ['status' => 'duplicate', 'id' => 4, 'sort_order' => 10];
$phaseFourAssert(
    $phaseFourService->create(3, 7, 'Evolução', 'clinical_notes', 11) === [
        'status' => 'duplicate',
        'id' => 4,
        'sort_order' => 10,
    ],
    'patient_tab_command_duplicate',
);
try {
    $phaseFourService->create(0, 7, 'Evolução', 'clinical_notes', 11);
    $phaseFourFailures[] = 'patient_tab_command_invalid_scope';
} catch (InvalidArgumentException) {
}
$phaseFourSources = [
    'port' => (string) file_get_contents($root . '/app/Application/Patients/PatientTabCommandPort.php'),
    'service' => (string) file_get_contents($root . '/app/Application/Patients/PatientTabCommandService.php'),
    'repository' => (string) file_get_contents($root . '/app/Infrastructure/Patients/PdoPatientTabCommandRepository.php'),
    'patients_facade' => (string) file_get_contents($root . '/app/Domain/Patients/Patients.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
    'loader' => (string) file_get_contents($root . '/app/Support/ModuleLoader.php'),
];
foreach ([
    'port' => ['createIfAbsent(', 'int $clinicId', 'int $patientId', 'int $userId'],
    'service' => ['$this->port->createIfAbsent(', "['created', 'duplicate']"],
    'repository' => [
        '$ownsTransaction = !$pdo->inTransaction()',
        '$pdo->beginTransaction()',
        'LIMIT 1 FOR UPDATE',
        'WHERE clinic_id=? AND patient_link_id=?',
        'INSERT INTO pi_patient_tabs',
        '$pdo->rollBack()',
    ],
    'patients_facade' => ['prontoo_create_patient_tab_command(', 'aba_paciente_criada'],
    'runner' => [
        'new \\Prontoo\\Infrastructure\\Patients\\PdoPatientTabCommandRepository()',
        'prontoo_patient_tab_command_service()->create(',
    ],
    'loader' => [
        "'Application/Patients/PatientTabCommandPort.php'",
        "'Application/Patients/PatientTabCommandService.php'",
        "'Infrastructure/Patients/PdoPatientTabCommandRepository.php'",
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($phaseFourSources[$sourceKey], $requiredToken)) {
            $phaseFourFailures[] = $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'port' => ['SELECT ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION'],
    'service' => ['SELECT ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION'],
    'repository' => ['$_GET', '$_POST', '$_SESSION', '<div', '<section'],
    'patients_facade' => [
        'SELECT id FROM pi_patient_tabs WHERE clinic_id=? AND patient_link_id=? AND label=? AND active=1 LIMIT 1',
        'INSERT INTO pi_patient_tabs (clinic_id,patient_link_id,label,icon_name,sort_order,created_by,created_at)',
    ],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($phaseFourSources[$sourceKey], $forbiddenToken)) {
            $phaseFourFailures[] = $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$phaseFourCharacterization = [
    'ok' => $phaseFourFailures === [],
    'failed' => $phaseFourFailures,
];

require_once $root . '/app/Application/Financial/PatientRevenueReceiptPort.php';
require_once $root . '/app/Application/Financial/PatientRevenueReceiptService.php';
require_once $root . '/app/Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php';

$phaseFiveFailures = [];
$phaseFiveAssert = static function (bool $condition, string $name) use (&$phaseFiveFailures): void {
    if (!$condition) {
        $phaseFiveFailures[] = $name;
    }
};
$phaseFivePort = new class implements \Prontoo\Application\Financial\PatientRevenueReceiptPort {
    public array $result = [
        'status' => 'received',
        'revenue_id' => 8,
        'movement_id' => 13,
        'amount_cents' => 12500,
        'title' => 'Consulta',
    ];
    public array $received = [];

    public function receive(
        int $clinicId,
        int $patientId,
        int $revenueId,
        int $userId,
        string $role,
    ): array {
        $this->received = [$clinicId, $patientId, $revenueId, $userId, $role];
        return $this->result;
    }
};
$phaseFiveService = new \Prontoo\Application\Financial\PatientRevenueReceiptService($phaseFivePort);
$phaseFiveReceived = $phaseFiveService->receive(2, 4, 8, 10, 'recepcionista');
$phaseFiveAssert(
    $phaseFiveReceived === [
        'status' => 'received',
        'revenue_id' => 8,
        'movement_id' => 13,
        'amount_cents' => 12500,
        'title' => 'Consulta',
    ] && $phaseFivePort->received === [2, 4, 8, 10, 'recepcionista'],
    'patient_revenue_receipt_received',
);
$phaseFivePort->result = ['status' => 'not_pending', 'revenue_id' => 8];
$phaseFiveAssert(
    $phaseFiveService->receive(2, 4, 8, 10, 'gerente')['status'] === 'not_pending',
    'patient_revenue_receipt_not_pending',
);
try {
    $phaseFiveService->receive(2, 4, 8, 10, 'medico');
    $phaseFiveFailures[] = 'patient_revenue_receipt_forbidden_role';
} catch (InvalidArgumentException) {
}
$phaseFiveSources = [
    'port' => (string) file_get_contents($root . '/app/Application/Financial/PatientRevenueReceiptPort.php'),
    'service' => (string) file_get_contents($root . '/app/Application/Financial/PatientRevenueReceiptService.php'),
    'repository' => (string) file_get_contents($root . '/app/Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php'),
    'patients_facade' => (string) file_get_contents($root . '/app/Domain/Patients/Patients.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
    'loader' => (string) file_get_contents($root . '/app/Support/ModuleLoader.php'),
];
foreach ([
    'port' => ['public function receive(', 'int $clinicId', 'int $revenueId', 'string $role'],
    'service' => ["['recepcionista', 'gerente']", '$this->port->receive(', "['received', 'not_pending']"],
    'repository' => [
        '$ownsTransaction = !$pdo->inTransaction()',
        'SELECT id FROM pi_patients WHERE id=? AND clinic_id=? AND active=1 FOR UPDATE',
        'FROM pi_financial_revenues WHERE id=? AND clinic_id=? AND patient_link_id=? LIMIT 1 FOR UPDATE',
        "source_entity='patient_revenue'",
        '\\financial_create_movement(',
        "UPDATE pi_financial_revenues SET status='efetivada'",
        "UPDATE pi_appointments SET payment_status='efetivada'",
        '$pdo->rollBack()',
    ],
    'patients_facade' => ['prontoo_receive_patient_revenue_command(', 'receita_recebida'],
    'runner' => [
        'new \\Prontoo\\Infrastructure\\Financial\\PdoPatientRevenueReceiptRepository()',
        'prontoo_patient_revenue_receipt_service()->receive(',
    ],
    'loader' => [
        "'Application/Financial/PatientRevenueReceiptPort.php'",
        "'Application/Financial/PatientRevenueReceiptService.php'",
        "'Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php'",
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($phaseFiveSources[$sourceKey], $requiredToken)) {
            $phaseFiveFailures[] = $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'port' => ['SELECT ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION'],
    'service' => ['SELECT ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION'],
    'repository' => ['$_GET', '$_POST', '$_SESSION', '<div', '<section'],
    'patients_facade' => [
        "SELECT id,appointment_id,amount_cents,title,payment_method FROM pi_financial_revenues",
        "UPDATE pi_financial_revenues SET status='efetivada'",
        "UPDATE pi_appointments SET payment_status='efetivada'",
        'financial_create_movement(',
    ],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($phaseFiveSources[$sourceKey], $forbiddenToken)) {
            $phaseFiveFailures[] = $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$phaseFiveCharacterization = [
    'ok' => $phaseFiveFailures === [],
    'failed' => $phaseFiveFailures,
];
require_once $root . '/app/Core/Performance/PerformanceBudget.php';

$serverPhaseOneFailures = [];
$performanceContract = json_decode(
    (string) file_get_contents($root . '/app/performance.budgets.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
if (!is_array($performanceContract) || ($performanceContract['schema'] ?? '') !== 'prontoo-performance-budgets-v1') {
    $serverPhaseOneFailures[] = 'performance_budget_schema';
} else {
    $underBudget = \Prontoo\Core\Performance\PerformanceBudget::evaluate(
        $performanceContract,
        'patients',
        [
            'elapsed_ms' => 400,
            'query_ms' => 120,
            'queries' => 20,
            'wide_selects' => 0,
            'module_files' => 20,
            'module_bytes' => 900000,
        ],
    );
    $overBudget = \Prontoo\Core\Performance\PerformanceBudget::evaluate(
        $performanceContract,
        'patients',
        [
            'elapsed_ms' => 5000,
            'query_ms' => 2000,
            'queries' => 300,
            'wide_selects' => 8,
            'module_files' => 90,
            'module_bytes' => 5000000,
        ],
    );
    $fallbackBudget = \Prontoo\Core\Performance\PerformanceBudget::resolve(
        $performanceContract,
        'unknown_route',
    );
    $normalizedDefault = \Prontoo\Core\Performance\PerformanceBudget::normalize(
        (array) ($performanceContract['defaults'] ?? []),
    );
    if (empty($underBudget['ok'])) {
        $serverPhaseOneFailures[] = 'performance_budget_under_limit';
    }
    if (!empty($overBudget['ok']) || count((array) ($overBudget['breaches'] ?? [])) !== 6) {
        $serverPhaseOneFailures[] = 'performance_budget_breach_detection';
    }
    if ($fallbackBudget !== $normalizedDefault) {
        $serverPhaseOneFailures[] = 'performance_budget_fallback';
    }
}
$serverPhaseOneCharacterization = [
    'ok' => $serverPhaseOneFailures === [],
    'failed' => $serverPhaseOneFailures,
];
$serverPhaseTwoFailures = [];
$cacheGenerationSource = (string) file_get_contents($root . '/app/Support/ServerJsonCache.php');
foreach ([
    'function server_json_cache_generation_file(',
    'function server_json_cache_generation(',
    'function server_json_cache_bump_generation(',
    '"/generation-"',
    'ftruncate($handle, 0)',
    'server_json_cache_bump_generation($category)',
    'generation_fallback_clears',
] as $requiredToken) {
    if (!str_contains($cacheGenerationSource, $requiredToken)) {
        $serverPhaseTwoFailures[] = 'cache_generation_missing:' . $requiredToken;
    }
}
$clearStart = strpos($cacheGenerationSource, 'function server_json_cache_clear_categories(');
$clearEnd = $clearStart === false
    ? false
    : strpos($cacheGenerationSource, 'function server_json_cache_all_categories(', $clearStart);
$clearSource = $clearStart !== false && $clearEnd !== false
    ? substr($cacheGenerationSource, $clearStart, $clearEnd - $clearStart)
    : '';
if ($clearSource === '' || str_contains($clearSource, 'server_json_cache_rrmdir($dir)')) {
    $serverPhaseTwoFailures[] = 'cache_invalidation_still_recursive';
}
if (str_contains($cacheGenerationSource, 'return server_json_cache_category_dir($category) . "/" . $key . ".json";')) {
    $serverPhaseTwoFailures[] = 'cache_key_without_generation';
}
$serverPhaseTwoCharacterization = [
    'ok' => $serverPhaseTwoFailures === [],
    'failed' => $serverPhaseTwoFailures,
];
$serverPhaseThreeFailures = [];
$telemetryPartitionSource = (string) file_get_contents($root . '/app/Support/Telemetry.php');
foreach ([
    'function telemetry_page_partition_file(',
    'function telemetry_page_partition_files(',
    'function telemetry_prune_page_partitions(',
    'new SplFileObject($file, "rb")',
    '$handle = @fopen($file, "ab")',
    '"deferred_id" => preg_match(',
] as $requiredToken) {
    if (!str_contains($telemetryPartitionSource, $requiredToken)) {
        $serverPhaseThreeFailures[] = 'telemetry_partition_missing:' . $requiredToken;
    }
}
$appendStart = strpos($telemetryPartitionSource, 'function telemetry_append_page_metric(');
$appendEnd = $appendStart === false
    ? false
    : strpos($telemetryPartitionSource, 'function telemetry_route_perf_file(', $appendStart);
$appendSource = $appendStart !== false && $appendEnd !== false
    ? substr($telemetryPartitionSource, $appendStart, $appendEnd - $appendStart)
    : '';
foreach (['json_decode($raw', 'ftruncate($fh, 0)', 'stream_get_contents($fh)'] as $forbiddenToken) {
    if ($appendSource === '' || str_contains($appendSource, $forbiddenToken)) {
        $serverPhaseThreeFailures[] = 'telemetry_append_forbidden:' . $forbiddenToken;
    }
}
$serverPhaseThreeCharacterization = [
    'ok' => $serverPhaseThreeFailures === [],
    'failed' => $serverPhaseThreeFailures,
];
require_once $root . '/app/Application/Patients/PatientReceptionHistoryReadPort.php';
require_once $root . '/app/Application/Patients/PatientReceptionHistoryReadService.php';
require_once $root . '/app/Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php';

$serverPhaseFourFailures = [];
$receptionPort = new class implements \Prontoo\Application\Patients\PatientReceptionHistoryReadPort {
    public array $received = [];
    public function read(
        int $clinicId,
        int $patientId,
        int $personId,
        string $phoneDigits,
    ): array {
        $this->received = [$clinicId, $patientId, $personId, $phoneDigits];
        return [
            'leads' => [['id' => 4]],
            'events' => [4 => [['id' => 8]]],
            'users' => [9 => ['id' => 9, 'name' => 'Ana']],
        ];
    }
};
$receptionService = new \Prontoo\Application\Patients\PatientReceptionHistoryReadService($receptionPort);
$receptionModel = $receptionService->read(2, 3, 5, '(65) 99999-0000');
if ($receptionPort->received !== [2, 3, 5, '65999990000'] ||
    count($receptionModel['leads']) !== 1 ||
    count($receptionModel['events'][4] ?? []) !== 1) {
    $serverPhaseFourFailures[] = 'reception_read_model_behavior';
}
$serverPhaseFourSources = [
    'service' => (string) file_get_contents($root . '/app/Application/Patients/PatientReceptionHistoryReadService.php'),
    'repository' => (string) file_get_contents($root . '/app/Infrastructure/Patients/PdoPatientReceptionHistoryReadRepository.php'),
    'patients' => (string) file_get_contents($root . '/app/Domain/Patients/Patients.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
];
foreach ([
    'repository' => ['LEFT JOIN pi_lead_events', 'LEFT JOIN pi_users', 'EXISTS (SELECT 1 FROM pi_patients', 'l.clinic_id=?'],
    'patients' => ['prontoo_patient_reception_history_read_model('],
    'runner' => ['PdoPatientReceptionHistoryReadRepository', 'PatientReceptionHistoryReadService'],
] as $sourceKey => $tokens) {
    foreach ($tokens as $token) {
        if (!str_contains($serverPhaseFourSources[$sourceKey], $token)) {
            $serverPhaseFourFailures[] = $sourceKey . ':missing:' . $token;
        }
    }
}
foreach (['SELECT ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION'] as $token) {
    if (str_contains($serverPhaseFourSources['service'], $token)) {
        $serverPhaseFourFailures[] = 'service:forbidden:' . $token;
    }
}
$receptionFunctionStart = strpos($serverPhaseFourSources['patients'], 'function patient_reception_history_items(');
$receptionFunctionEnd = $receptionFunctionStart === false
    ? false
    : strpos($serverPhaseFourSources['patients'], 'function patient_appointment_timeline_items(', $receptionFunctionStart);
$receptionFunction = $receptionFunctionStart !== false && $receptionFunctionEnd !== false
    ? substr($serverPhaseFourSources['patients'], $receptionFunctionStart, $receptionFunctionEnd - $receptionFunctionStart)
    : '';
if ($receptionFunction === '' || str_contains($receptionFunction, 'FROM pi_leads')) {
    $serverPhaseFourFailures[] = 'legacy_reception_sql_not_removed';
}
$serverPhaseFourCharacterization = [
    'ok' => $serverPhaseFourFailures === [],
    'failed' => $serverPhaseFourFailures,
];
$result = [
    'ok' =>
        !empty($architecture['ok']) &&
        !empty($selfTest['ok']) &&
        !empty($dashboardIconCascade['ok']) &&
        !empty($logoutCascade['ok']) &&
        !empty($loginPerformance['ok']) &&
        !empty($inlineMfa['ok']) &&
        !empty($operationalUi['ok']) &&
        !empty($phaseOneCharacterization['ok']) &&
        !empty($phaseTwoCharacterization['ok']) &&
        !empty($phaseThreeCharacterization['ok']) &&
        !empty($phaseFourCharacterization['ok']) &&
        !empty($phaseFiveCharacterization['ok']) &&
        !empty($serverPhaseOneCharacterization['ok']) &&
        !empty($serverPhaseTwoCharacterization['ok']) &&
        !empty($serverPhaseThreeCharacterization['ok']) &&
        !empty($serverPhaseFourCharacterization['ok']),
    'architecture' => $architecture,
    'self_test' => $selfTest,
    'dashboard_icon_cascade' => $dashboardIconCascade,
    'logout_cascade' => $logoutCascade,
    'login_performance' => $loginPerformance,
    'inline_mfa' => $inlineMfa,
    'operational_ui' => $operationalUi,
    'phase_one_characterization' => $phaseOneCharacterization,
    'phase_two_characterization' => $phaseTwoCharacterization,
    'phase_three_characterization' => $phaseThreeCharacterization,
    'phase_four_characterization' => $phaseFourCharacterization,
    'phase_five_characterization' => $phaseFiveCharacterization,
    'server_phase_one_characterization' => $serverPhaseOneCharacterization,
    'server_phase_two_characterization' => $serverPhaseTwoCharacterization,
    'server_phase_three_characterization' => $serverPhaseThreeCharacterization,
    'server_phase_four_characterization' => $serverPhaseFourCharacterization,
];

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
), PHP_EOL;

exit($result['ok'] ? 0 : 1);
