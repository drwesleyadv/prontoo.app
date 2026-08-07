<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$replaceOnce = static function (string $path, string $search, string $replacement): void {
    $source = (string) file_get_contents($path);
    $count = substr_count($source, $search);
    if ($count !== 1) {
        throw new RuntimeException('Trecho esperado não localizado exatamente uma vez em ' . $path . ': ' . $count);
    }
    $updated = str_replace($search, $replacement, $source);
    if (file_put_contents($path, $updated) === false) {
        throw new RuntimeException('Falha ao atualizar ' . $path);
    }
};

$loginPath = $root . '/app/Runtime/Legacy/AuthOnboarding/AuthOnboardingRuntimeOperations03.php';
$replaceOnce(
    $loginPath,
    <<<'OLD'
        $bootStatus = $mfaStage
            ? "Senha confirmada. Agora, digite o código do aplicativo."
            : "Verificando liberação do acesso.";
        $bootIcon = $mfaStage ? "verified_user" : "sync";
OLD,
    <<<'NEW'
        $bootStatus = $mfaStage
            ? "Senha confirmada. Agora, digite o código do aplicativo."
            : "Informe seu CPF e senha.";
        $bootIcon = $mfaStage ? "verified_user" : "login";
NEW,
);
$replaceOnce(
    $loginPath,
    <<<'OLD'
            ($mfaStage
                ? ' data-autotest-ready="1"'
                : " data-login-autotest") .
OLD,
    <<<'NEW'
            ' data-autotest-ready="1"' .
NEW,
);
$replaceOnce(
    $loginPath,
    <<<'OLD'
            '<button type="submit" class="primary wide login-submit" data-login-submit disabled aria-disabled="true">' .
OLD,
    <<<'NEW'
            '<button type="submit" class="primary wide login-submit" data-login-submit>' .
NEW,
);

$selftestPath = $root . '/app/Infrastructure/Legacy/SupportFoundation/SupportFoundationInfrastructureOperations01.php';
$replaceOnce(
    $selftestPath,
    <<<'OLD'
            $checks["storage"] = $writable && $freeOk;
            $checks["storage_free_bytes"] = $free === false ? null : (float) $free;
            $ok = $ok && (bool) $checks["storage"];
OLD,
    <<<'NEW'
            $checks["storage"] = $writable && $freeOk;
            $checks["storage_free_bytes"] = $free === false ? null : (float) $free;
            $checks["storage_advisory"] = true;
NEW,
);

$bootPath = $root . '/app/Runtime/Boot/RuntimeBootCoordinator.php';
$bootSource = (string) file_get_contents($bootPath);
$pattern = '/    public static function runReadinessCycle\(string \$mode = \'route_readiness\', int \$uid = 0\): array\n    \{.*?\n    \}\n\n    public static function runMaintenanceCycle/s';
$replacement = <<<'PHP'
    private static function executeReadinessChecks(
        string $mode,
        int $uid,
        float $startedAt,
        bool $writeMarker,
        string $reason = '',
    ): array {
        $result = ['ok' => false, 'mode' => $mode, 'uid' => $uid, 'steps' => []];
        \prontoo_load_full_runtime_modules();
        \ensure_runtime_schema_minimum();
        $result['steps'][] = 'schema_contract';
        if (class_exists('\\Prontoo\\Infrastructure\\Integrity\\PiIntegrity')) {
            \Prontoo\Infrastructure\Integrity\PiIntegrity::bootIndexLightcheck();
            $result['steps'][] = 'integrity_lightcheck';
        }
        if ($writeMarker) {
            self::markReadinessOk($mode);
        }
        $result['ok'] = true;
        $result['ran'] = true;
        if ($reason !== '') {
            $result['reason'] = $reason;
        }
        $result['duration_ms'] = (int) round(
            (microtime(true) - $startedAt) * 1000,
            0,
            \RoundingMode::HalfAwayFromZero,
        );
        return $result;
    }

    public static function runReadinessCycle(string $mode = 'route_readiness', int $uid = 0): array
    {
        $startedAt = microtime(true);
        $result = ['ok' => false, 'mode' => $mode, 'uid' => $uid, 'steps' => []];
        if (self::readinessMarkerValid()) {
            return $result + ['ok' => true, 'ran' => false, 'reason' => 'readiness_marker_fresh'];
        }
        $lockDir = \storage_path('cache/locks');
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0750, true) && !is_dir($lockDir)) {
            error_log('[Prontoo runtime readiness] lock indisponível; executando prontidão sem cache.');
            return self::executeReadinessChecks(
                $mode,
                $uid,
                $startedAt,
                false,
                'readiness_uncached_lock_unavailable',
            );
        }
        $lockHandle = @fopen(
            $lockDir . '/runtime-readiness-' . hash('sha256', PRONTOO_SCHEMA_REV) . '.lock',
            'c+',
        );
        if (!is_resource($lockHandle)) {
            error_log('[Prontoo runtime readiness] arquivo de lock indisponível; executando prontidão sem cache.');
            return self::executeReadinessChecks(
                $mode,
                $uid,
                $startedAt,
                false,
                'readiness_uncached_lock_unavailable',
            );
        }
        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            error_log('[Prontoo runtime readiness] lock não adquirido; executando prontidão sem cache.');
            return self::executeReadinessChecks(
                $mode,
                $uid,
                $startedAt,
                false,
                'readiness_uncached_lock_failed',
            );
        }
        try {
            if (self::readinessMarkerValid()) {
                return $result + ['ok' => true, 'ran' => false, 'reason' => 'readiness_completed_concurrently'];
            }
            return self::executeReadinessChecks($mode, $uid, $startedAt, true);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public static function runMaintenanceCycle
PHP;
$updatedBoot = preg_replace($pattern, $replacement, $bootSource, 1, $count);
if (!is_string($updatedBoot) || $count !== 1) {
    throw new RuntimeException('Método runReadinessCycle não pôde ser substituído com segurança: ' . $count);
}
if (file_put_contents($bootPath, $updatedBoot) === false) {
    throw new RuntimeException('Falha ao atualizar RuntimeBootCoordinator.php');
}

$testPath = $root . '/tools/login-post-password-runtime-check';
$testSource = (string) file_get_contents($testPath);
$needle = <<<'OLD'
foreach (['maestro_contract', 'integrity_autotest', 'runtime_self_check', 'pdf_cleanup'] as $forbiddenStep) {
    if (in_array($forbiddenStep, $readinessSteps, true)) {
        throw new RuntimeException('Login executou etapa pesada proibida: ' . $forbiddenStep);
    }
}

$deep = \Prontoo\Runtime\Boot\RuntimeBootCoordinator::runMaintenanceCycle('ci_forced_deep', $userId);
OLD;
$insert = <<<'NEW'
foreach (['maestro_contract', 'integrity_autotest', 'runtime_self_check', 'pdf_cleanup'] as $forbiddenStep) {
    if (in_array($forbiddenStep, $readinessSteps, true)) {
        throw new RuntimeException('Login executou etapa pesada proibida: ' . $forbiddenStep);
    }
}

$loginSource = (string) file_get_contents($root . '/app/Runtime/Legacy/AuthOnboarding/AuthOnboardingRuntimeOperations03.php');
if (str_contains($loginSource, 'data-login-autotest')) {
    throw new RuntimeException('Formulário de login ainda depende do autoteste auxiliar para liberar o submit.');
}
if (str_contains($loginSource, 'data-login-submit disabled')) {
    throw new RuntimeException('Formulário de login ainda nasce bloqueado por JavaScript.');
}
if (!str_contains($loginSource, 'data-autotest-ready="1"')) {
    throw new RuntimeException('Formulário de login perdeu a compatibilidade progressiva do fluxo JavaScript.');
}

$selftestSource = (string) file_get_contents($root . '/app/Infrastructure/Legacy/SupportFoundation/SupportFoundationInfrastructureOperations01.php');
if (str_contains($selftestSource, '$ok = $ok && (bool) $checks["storage"]')) {
    throw new RuntimeException('Autoteste leve ainda trata storage como pré-requisito de autenticação.');
}

$lockDir = storage_path('cache/locks');
if (!is_dir($lockDir) && !prontoo_fs_mkdir($lockDir)) {
    throw new RuntimeException('Não foi possível preparar diretório de lock para o teste degradado.');
}
$lockFile = $lockDir . '/runtime-readiness-' . hash('sha256', PRONTOO_SCHEMA_REV) . '.lock';
if (is_file($lockFile)) {
    prontoo_fs_unlink($lockFile, false);
}
if (is_file($readinessMarker)) {
    prontoo_fs_unlink($readinessMarker, false);
}
$lockMode = fileperms($lockDir);
$lockMode = is_int($lockMode) ? ($lockMode & 0777) : 0750;
if (!chmod($lockDir, 0500)) {
    throw new RuntimeException('Não foi possível simular lock de readiness indisponível.');
}
try {
    $uncachedReadiness = \Prontoo\Runtime\Boot\RuntimeBootCoordinator::runReadinessCycle(
        'ci_unwritable_readiness_lock',
        $userId,
    );
} finally {
    chmod($lockDir, $lockMode ?: 0750);
}
if (empty($uncachedReadiness['ok'])) {
    throw new RuntimeException('Readiness degradada bloqueou o runtime quando o cache de lock estava indisponível.');
}
if (($uncachedReadiness['reason'] ?? '') !== 'readiness_uncached_lock_unavailable') {
    throw new RuntimeException('Readiness degradada não registrou o motivo esperado.');
}
$uncachedSteps = array_values((array) ($uncachedReadiness['steps'] ?? []));
foreach (['schema_contract', 'integrity_lightcheck'] as $requiredStep) {
    if (!in_array($requiredStep, $uncachedSteps, true)) {
        throw new RuntimeException('Readiness degradada perdeu etapa mínima: ' . $requiredStep);
    }
}

$deep = \Prontoo\Runtime\Boot\RuntimeBootCoordinator::runMaintenanceCycle('ci_forced_deep', $userId);
NEW;
if (substr_count($testSource, $needle) !== 1) {
    throw new RuntimeException('Ponto de ampliação do teste de login não localizado exatamente uma vez.');
}
$testSource = str_replace($needle, $insert, $testSource);
if (file_put_contents($testPath, $testSource) === false) {
    throw new RuntimeException('Falha ao atualizar teste de login.');
}

$versionPath = $root . '/version.json';
$version = json_decode((string) file_get_contents($versionPath), true, 512, JSON_THROW_ON_ERROR);
$version['version'] = '1.8.7.8';
$version['release'] = '1.8.7.8';
$version['generated_at_unix'] = 1786140900;
$version['generated_at'] = '2026-08-07T22:15:00+00:00';
$version['updated_at'] = '2026-08-07T22:15:00+00:00';
$version['build'] = '1.8.7.8-login-availability-hotfix';
$version['logic_changes'] = true;
$version['visual_changes'] = false;
$version['functional_equivalence_policy'] = 'login-progressive-enhancement-and-readiness-cache-degradation-no-user-resource-change';
$version['previous_version'] = '1.8.7.7';
$version['notes'] = 'Corrige bloqueios de login causados por autoteste auxiliar no navegador e por indisponibilidade do cache de locks de readiness, preservando autenticação, MFA e contratos mínimos do runtime.';
$version['deployment_sync_id'] = 'github-prontoo-1.8.7.8-login-availability-hotfix';
$version['deployment_sync_requested_at'] = '2026-08-07T22:15:00+00:00';
$version['runtime_readiness_policy'] = 'login_and_normal_routes_verify_schema_contract_and_integrity_lightcheck_with_uncached_fallback_when_readiness_lock_cache_is_unavailable';
$version['login_availability_policy'] = 'html_form_progressive_enhancement_submit_never_depends_on_advisory_login_autotest_storage_or_javascript';
$version['rewrite_scope'] = 'remove_advisory_preflight_from_login_gate_and_add_uncached_readiness_fallback';
$version['changelog'] = [
    'title' => 'Hotfix de disponibilidade do login',
    'items' => [
        'faz o formulário de login funcionar por envio HTML nativo mesmo sem JavaScript',
        'remove o autoteste auxiliar e a gravabilidade do storage como pré-requisitos para habilitar o botão Entrar',
        'mantém CPF, senha, MFA, rate limit e credenciais validados exclusivamente pelo servidor',
        'executa readiness mínima sem cache quando o diretório ou arquivo de lock não puder ser gravado',
        'mantém schema contract e integrity lightcheck fail-closed mesmo no modo degradado',
        'preserva schema, dados, interface funcional e todos os recursos do usuário',
    ],
];
file_put_contents(
    $versionPath,
    json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
);
