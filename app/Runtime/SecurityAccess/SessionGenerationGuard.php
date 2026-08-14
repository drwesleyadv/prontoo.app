<?php
declare(strict_types=1);

namespace Prontoo\Runtime\SecurityAccess;

final class SessionGenerationGuard
{
    private function __construct()
    {
    }

    public static function enforce(int $uid): void
    {
        if ($uid <= 0) {
            return;
        }

        $current = SecurityAccessRuntimeOperations02::auth_generation_current();
        $session = (string) ($_SESSION['auth_generation'] ?? '');
        $policy = defined('PRONTOO_AUTH_POLICY_GENERATION')
            ? PRONTOO_AUTH_POLICY_GENERATION
            : 'password-session-v1';
        $sessionPolicy = (string) ($_SESSION['auth_policy_generation'] ?? '');
        $userCurrent = SecurityAccessRuntimeOperations02::user_auth_generation_current($uid);
        $userSession = (string) ($_SESSION['user_auth_generation'] ?? '');

        if (
            $sessionPolicy === $policy &&
            ($current === '0' || $session === $current) &&
            $userCurrent !== '0' &&
            hash_equals($userCurrent, $userSession)
        ) {
            return;
        }

        $clinicId = (int) ($_SESSION['clinic_id'] ?? 0);
        $roleCode = (string) ($_SESSION['role_code'] ?? '');
        $queued = \Prontoo\Runtime\DeferredAudit\DeferredAuditRuntimeOperations01::maestro_defer_audit_event(
            'sessao_obsoleta_encerrada',
            'seguranca',
            $uid,
            [
                'clinic_id' => $clinicId > 0 ? $clinicId : null,
                'role_code' => $roleCode,
                'audit_body' => 'Sessão encerrada no primeiro uso após divergência da geração canônica de autenticação do usuário.',
            ],
            $uid,
        );
        if (!$queued) {
            error_log('[Prontoo auth generation audit] Evento de sessão obsoleta não pôde ser enfileirado.');
        }
        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::secure_session_destroy();
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header(
                'Location: ' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href('login', ['relogin' => '1']),
            );
        }
        exit();
    }
}
