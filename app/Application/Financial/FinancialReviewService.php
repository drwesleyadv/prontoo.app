<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

use Closure;
use RuntimeException;

final class FinancialReviewService
{
    public function __construct(private FinancialDataService $data)
    {
    }

    public function reviewOpening(
        int $clinicId,
        int $administratorUserId,
        int $sessionId,
        string $decision,
        string $notes,
        Closure $checkedAdd,
        Closure $formatMoney,
        Closure $recordCashDifference,
        Closure $audit,
    ): void {
        $this->data->atomic(function () use (
            $clinicId,
            $administratorUserId,
            $sessionId,
            $decision,
            $notes,
            $checkedAdd,
            $formatMoney,
            $recordCashDifference,
            $audit,
        ): void {
            $session = $this->data->row(
                'financial.08.review_opening_request.01',
                [$sessionId, $clinicId],
            );
            if (!$session) {
                throw new RuntimeException(
                    'A solicitação de abertura não está pendente de autorização.',
                );
            }
            $normalizedDecision = $decision === 'reject' ? 'reject' : 'approve';
            $expected = (int) ($session['expected_closing_cents'] ?? 0);
            $informed = (int) ($session['opening_balance_cents'] ?? 0);
            $difference = (int) $checkedAdd(
                $informed,
                -$expected,
                'Diferença da abertura',
            );
            $cleanNotes = trim($notes);
            if ($normalizedDecision === 'reject') {
                $this->data->result(
                    'financial.08.review_opening_request.02',
                    [$administratorUserId, $cleanNotes ?: null, $sessionId, $clinicId],
                );
                $this->data->result(
                    'financial.08.review_opening_request.03',
                    [
                        $clinicId,
                        'Abertura de caixa recusada',
                        'O Administrativo recusou a abertura de caixa com saldo diferente. Esperado: ' .
                        $formatMoney($expected) .
                        '. Saldo informado: ' .
                        $formatMoney($informed) .
                        ($cleanNotes !== '' ? "\n\nObservação: " . $cleanNotes : ''),
                        1,
                        'user',
                        null,
                        (int) $session['user_id'],
                        $administratorUserId,
                    ],
                );
                $audit(
                    'abertura_caixa_divergente_recusada',
                    'financeiro',
                    $sessionId,
                    [
                        'usuario_caixa' => (int) $session['user_id'],
                        'esperado' => $expected,
                        'informado' => $informed,
                        'diferenca' => $difference,
                        'motivo' => $cleanNotes,
                    ],
                );
                return;
            }
            $this->data->result(
                'financial.08.review_opening_request.04',
                [$administratorUserId, $cleanNotes ?: null, $sessionId, $clinicId],
            );
            $recordCashDifference(
                $clinicId,
                $administratorUserId,
                $sessionId,
                (int) $session['location_id'],
                $difference,
                'opening',
                'confirmed',
                $cleanNotes,
                false,
            );
            $this->data->result(
                'financial.08.review_opening_request.05',
                [
                    $clinicId,
                    'Abertura de caixa autorizada',
                    'O Administrativo autorizou a abertura de caixa com saldo diferente. Esperado: ' .
                    $formatMoney($expected) .
                    '. Saldo autorizado: ' .
                    $formatMoney($informed) .
                    ($cleanNotes !== '' ? "\n\nObservação: " . $cleanNotes : ''),
                    1,
                    'user',
                    null,
                    (int) $session['user_id'],
                    $administratorUserId,
                ],
            );
            $audit(
                'abertura_caixa_divergente_autorizada',
                'financeiro',
                $sessionId,
                [
                    'usuario_caixa' => (int) $session['user_id'],
                    'esperado' => $expected,
                    'informado' => $informed,
                    'diferenca' => $difference,
                    'observacao' => $cleanNotes,
                    'audit_body' =>
                        'Gerência autorizou abertura da Gaveta com valor inicial divergente.',
                ],
            );
        });
    }

    public function reviewSession(
        int $clinicId,
        int $userId,
        int $sessionId,
        string $decision,
        string $notes,
        string $drawerUnlockLocal,
        Closure $ensureClosingAdjustment,
        Closure $assertSessionReconciled,
        Closure $drawerRow,
        Closure $scheduleDrawerUnlock,
        Closure $audit,
    ): void {
        $this->data->atomic(function () use (
            $clinicId,
            $userId,
            $sessionId,
            $decision,
            $notes,
            $drawerUnlockLocal,
            $ensureClosingAdjustment,
            $assertSessionReconciled,
            $drawerRow,
            $scheduleDrawerUnlock,
            $audit,
        ): void {
            $session = $this->data->row(
                'financial.08.review_session.01',
                [$sessionId, $clinicId],
            );
            if (!$session) {
                throw new RuntimeException(
                    'Fechamento não está pendente de conferência.',
                );
            }
            if ($decision === 'reject') {
                $this->data->result(
                    'financial.08.review_session.02',
                    [$userId, trim($notes) ?: null, $sessionId, $clinicId],
                );
                $this->data->result(
                    'financial.08.review_session.03',
                    [$userId, trim($notes), $clinicId, $sessionId],
                );
                $this->data->result(
                    'financial.08.review_session.04',
                    [
                        $clinicId,
                        $sessionId,
                        $userId,
                        'rejected',
                        (int) $session['expected_closing_cents'],
                        (int) $session['declared_closing_cents'],
                        0,
                        (int) $session['difference_cents'],
                        trim($notes) ?: null,
                    ],
                );
                $audit(
                    'fechamento_caixa_devolvido',
                    'financeiro',
                    $sessionId,
                    ['motivo' => $notes],
                );
                return;
            }
            $ensureClosingAdjustment($session, $userId, 'pending_review');
            $session = $this->data->row(
                'financial.08.review_session.05',
                [$sessionId, $clinicId],
            ) ?: $session;
            $assertSessionReconciled($session);
            $remaining = (int) ($this->data->scalar(
                'financial.08.review_session.06',
                [
                    $clinicId,
                    (int) $session['location_id'],
                    (string) $session['business_date'],
                    $sessionId,
                ],
            ) ?: 0);
            if ($remaining === 0 && (string) ($session['location_id'] ?? '') !== '0') {
                $drawer = $drawerRow($clinicId, (int) $session['location_id']);
                if (
                    $drawer &&
                    (string) ($drawer['drawer_lock_status'] ?? 'unlocked') === 'locked' &&
                    trim($drawerUnlockLocal) === ''
                ) {
                    throw new RuntimeException(
                        'Informe o horário do dia seguinte em que a Gaveta será destrancada.',
                    );
                }
            }
            $this->data->result(
                'financial.08.review_session.07',
                [$userId, trim($notes) ?: null, $sessionId, $clinicId],
            );
            $this->data->result(
                'financial.08.review_session.08',
                [$userId, $userId, $clinicId, $sessionId],
            );
            $this->data->result(
                'financial.08.review_session.09',
                [
                    $clinicId,
                    $sessionId,
                    $userId,
                    'approved',
                    (int) $session['expected_closing_cents'],
                    (int) $session['declared_closing_cents'],
                    (int) $session['transfer_to_safe_cents'],
                    (int) $session['difference_cents'],
                    trim($notes) ?: null,
                ],
            );
            if ($remaining === 0 && (int) $session['location_id'] > 0) {
                $scheduleDrawerUnlock(
                    $clinicId,
                    (int) $session['location_id'],
                    $userId,
                    $drawerUnlockLocal,
                    trim($notes),
                );
            }
            $audit(
                'fechamento_caixa_conferido',
                'financeiro',
                $sessionId,
                [
                    'retirada' => (int) $session['transfer_to_safe_cents'],
                    'diferenca' => (int) $session['difference_cents'],
                    'destravar_em' => $drawerUnlockLocal,
                ],
            );
        });
    }
}
