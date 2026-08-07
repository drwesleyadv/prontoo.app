<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Auth;

use Throwable;

final class LogoutCoordinator
{
    public const POLICY = 'logout_global_session_revocation_v1';

    private function __construct()
    {
    }

    public static function handle(): void
    {
        if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('login');
        }

        $uid = (int) ($_SESSION['uid'] ?? 0);
        $clinicId = (int) ($_SESSION['clinic_id'] ?? 0);
        $roleCode = (string) ($_SESSION['role_code'] ?? '');
        $revocationConfirmed = $uid <= 0;

        try {
            $revocationConfirmed = self::revokeOtherSessions($uid);
            self::recordLogout($uid, $clinicId, $roleCode, $revocationConfirmed);
        } finally {
            secure_session_destroy();
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header(
            'X-Prontoo-Global-Logout: ' .
                ($revocationConfirmed ? 'confirmed' : 'degraded'),
        );
        if (!$revocationConfirmed && $uid > 0) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Sua sessão neste navegador foi encerrada, mas não foi possível confirmar a revogação das demais sessões. Por segurança, tente novamente em instantes.';
            exit();
        }
        header('Location: ' . href('login'));
        exit();
    }

    private static function revokeOtherSessions(int $uid): bool
    {
        if ($uid <= 0) {
            return true;
        }
        $lastError = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                user_auth_generation_rotate($uid);
                return true;
            } catch (Throwable $error) {
                $lastError = $error;
                if ($attempt < 3) {
                    usleep(random_int(50000, 150000));
                }
            }
        }
        error_log(
            '[Prontoo logout global revocation] ' .
                ($lastError instanceof Throwable
                    ? $lastError->getMessage()
                    : 'falha desconhecida'),
        );
        return false;
    }

    private static function recordLogout(
        int $uid,
        int $clinicId,
        string $roleCode,
        bool $revocationConfirmed,
    ): void {
        $context = [
            'clinic_id' => $clinicId > 0 ? $clinicId : null,
            'role_code' => $roleCode,
            'logout_policy' => self::POLICY,
            'global_revocation_confirmed' => $revocationConfirmed,
            'audit_body' => $revocationConfirmed
                ? 'Logout concluído com destruição da sessão local e rotação da geração canônica do usuário; sessões anteriores serão recusadas no próximo uso.'
                : 'A sessão local foi encerrada, mas a rotação da geração canônica não pôde ser confirmada após três tentativas.',
        ];
        $queued = false;
        try {
            if (function_exists('maestro_defer_audit_event')) {
                $queued = (bool) maestro_defer_audit_event(
                    'saida_realizada',
                    'seguranca',
                    $uid > 0 ? $uid : null,
                    $context,
                    $uid > 0 ? $uid : null,
                );
            }
        } catch (Throwable $error) {
            error_log('[Prontoo logout deferred audit] ' . $error->getMessage());
        }
        if ($queued || !function_exists('audit')) {
            return;
        }
        try {
            $recorded = audit(
                'saida_realizada',
                'seguranca',
                $uid > 0 ? $uid : null,
                $context,
            );
            if (!$recorded) {
                error_log('[Prontoo logout audit] Evento não persistido.');
            }
        } catch (Throwable $error) {
            error_log('[Prontoo logout audit] ' . $error->getMessage());
        }
    }
}
