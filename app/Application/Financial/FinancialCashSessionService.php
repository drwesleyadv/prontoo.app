<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

use Closure;
use RuntimeException;

final class FinancialCashSessionService
{
    public function __construct(
        private FinancialDataService $data,
        private FinancialMovementService $movements,
    ) {
    }

    public function keepClosed(
        int $clinicId,
        int $userId,
        string $businessDate,
        Closure $cashierLocation,
        Closure $guardDrawerUse,
        Closure $openDrawerSession,
        Closure $pendingPreviousReview,
        Closure $unclosedPreviousSession,
        Closure $sessionForDate,
        Closure $previousBalance,
        Closure $firstName,
        Closure $audit,
    ): int {
        return (int) $this->data->atomic(function () use (
            $clinicId,
            $userId,
            $businessDate,
            $cashierLocation,
            $guardDrawerUse,
            $openDrawerSession,
            $pendingPreviousReview,
            $unclosedPreviousSession,
            $sessionForDate,
            $previousBalance,
            $firstName,
            $audit,
        ): int {
            $this->data->result('financial.07.keep_closed.01', [$clinicId]);
            $locationId = (int) $cashierLocation($clinicId, $userId);
            if ($locationId <= 0) {
                throw new RuntimeException(
                    'Você não é responsável por nenhuma gaveta ainda. Aguarde até que receba autorização para gerenciar gavetas.',
                );
            }
            $guardDrawerUse($clinicId, $locationId);
            $other = $openDrawerSession($clinicId, $locationId, $userId);
            if ($other) {
                throw new RuntimeException(
                    'Esta Gaveta já está aberta por ' .
                    $firstName((string) ($other['user_name'] ?? 'outro colaborador')) .
                    '. Aguarde o fechamento antes de abrir ou manter fechado.',
                );
            }
            if ($pendingPreviousReview($clinicId, $locationId, $businessDate)) {
                throw new RuntimeException(
                    'Esta Gaveta possui fechamento anterior aguardando conferência da Gerência. Aguarde o destravamento para usá-la.',
                );
            }
            if ($unclosedPreviousSession($clinicId, $userId, $businessDate)) {
                throw new RuntimeException(
                    'Há uma Gaveta de caixa anterior sem fechamento. Feche a sessão pendente antes de manter o caixa fechado hoje.',
                );
            }
            $existing = $sessionForDate($clinicId, $userId, $businessDate);
            if ($existing) {
                if ((string) $existing['status'] === 'kept_closed') {
                    return (int) $existing['id'];
                }
                if ((string) $existing['status'] === 'open') {
                    throw new RuntimeException('O caixa de hoje já está aberto.');
                }
                throw new RuntimeException(
                    'O caixa de hoje já foi fechado ou está em conferência.',
                );
            }
            $balance = (int) $previousBalance($clinicId, $locationId, $businessDate);
            $this->data->result(
                'financial.07.keep_closed.02',
                [
                    $clinicId,
                    $userId,
                    $locationId,
                    $businessDate,
                    $balance,
                    $balance,
                    $balance,
                    $balance,
                    'Gaveta mantida fechada pelo Atendimento; saldo físico preservado na Gaveta.',
                ],
            );
            $sessionId = $this->data->lastInsertId();
            $audit(
                'caixa_atendimento_mantido_fechado',
                'financeiro',
                $sessionId,
                [
                    'gaveta' => $locationId,
                    'audit_body' =>
                        'Atendimento optou por manter a Gaveta fechada no dia, preservando o saldo físico.',
                ],
            );
            return $sessionId;
        });
    }

    public function open(
        int $clinicId,
        int $userId,
        int $openingBalanceCents,
        string $businessDate,
        Closure $cashierLocation,
        Closure $guardDrawerUse,
        Closure $openDrawerSession,
        Closure $pendingPreviousReview,
        Closure $unclosedPreviousSession,
        Closure $sessionForDate,
        Closure $firstName,
        Closure $assertAmount,
        Closure $expectedOpeningBalance,
        Closure $requestOpeningAuthorization,
        Closure $audit,
    ): array {
        return (array) $this->data->atomic(function () use (
            $clinicId,
            $userId,
            $openingBalanceCents,
            $businessDate,
            $cashierLocation,
            $guardDrawerUse,
            $openDrawerSession,
            $pendingPreviousReview,
            $unclosedPreviousSession,
            $sessionForDate,
            $firstName,
            $assertAmount,
            $expectedOpeningBalance,
            $requestOpeningAuthorization,
            $audit,
        ): array {
            $this->data->result('financial.07.open_session.01', [$clinicId]);
            $locationId = (int) $cashierLocation($clinicId, $userId);
            if ($locationId <= 0) {
                throw new RuntimeException(
                    'Você não é responsável por nenhuma gaveta ainda. Aguarde até que receba autorização para gerenciar gavetas.',
                );
            }
            $guardDrawerUse($clinicId, $locationId);
            $other = $openDrawerSession($clinicId, $locationId, $userId);
            if ($other) {
                throw new RuntimeException(
                    'Esta Gaveta já está aberta por ' .
                    $firstName((string) ($other['user_name'] ?? 'outro colaborador')) .
                    '. Aguarde o fechamento antes de abrir a Gaveta.',
                );
            }
            if ($pendingPreviousReview($clinicId, $locationId, $businessDate)) {
                throw new RuntimeException(
                    'Esta Gaveta possui fechamento anterior aguardando conferência da Gerência. Aguarde o destravamento para usá-la.',
                );
            }
            if ($unclosedPreviousSession($clinicId, $userId, $businessDate)) {
                throw new RuntimeException(
                    'Há uma Gaveta de caixa anterior sem fechamento. Feche a sessão pendente antes de abrir uma nova.',
                );
            }
            $existing = $sessionForDate($clinicId, $userId, $businessDate);
            $status = $existing ? (string) $existing['status'] : '';
            if ($existing && $status === 'open') {
                throw new RuntimeException('A sessão de caixa de hoje já foi aberta.');
            }
            if ($existing && in_array(
                $status,
                ['closed_pending_review', 'approved', 'rejected'],
                true,
            )) {
                throw new RuntimeException(
                    'O caixa de hoje já foi fechado ou está em conferência.',
                );
            }
            $openingBalance = (int) $assertAmount(
                max(0, $openingBalanceCents),
                'Saldo inicial',
            );
            $expected = (int) $expectedOpeningBalance(
                $clinicId,
                $userId,
                $businessDate,
                $locationId,
                $existing ? (int) $existing['id'] : 0,
            );
            if ($existing && $status === 'kept_closed') {
                $expected = (int) ($existing['opening_balance_cents'] ?? $expected);
            }
            if ($openingBalance !== $expected) {
                $sessionId = (int) $requestOpeningAuthorization(
                    $clinicId,
                    $userId,
                    $locationId,
                    $businessDate,
                    $expected,
                    $openingBalance,
                    $existing ?: null,
                );
                return [
                    'session_id' => $sessionId,
                    'authorization_requested' => true,
                    'authorization_message' =>
                        'O Saldo Inicial informado não coincide com o valor esperado para esta Gaveta. O Administrativo recebeu aviso para autorizar a abertura com saldo diferente.',
                ];
            }
            if ($existing) {
                if (in_array(
                    $status,
                    ['kept_closed', 'opening_pending_review', 'opening_rejected'],
                    true,
                )) {
                    $this->data->result(
                        'financial.07.open_session.02',
                        [$locationId, $openingBalance, (int) $existing['id'], $clinicId, $userId],
                    );
                    $audit(
                        'caixa_atendimento_aberto',
                        'financeiro',
                        (int) $existing['id'],
                        [
                            'gaveta' => $locationId,
                            'saldo_inicial' => $openingBalance,
                            'audit_body' =>
                                'Gaveta aberta pelo Atendimento com valor inicial coincidente.',
                        ],
                    );
                    return [
                        'session_id' => (int) $existing['id'],
                        'authorization_requested' => false,
                        'authorization_message' => '',
                    ];
                }
                throw new RuntimeException(
                    'A situação atual do caixa não permite abertura.',
                );
            }
            $this->data->result(
                'financial.07.open_session.03',
                [$clinicId, $userId, $locationId, $businessDate, $openingBalance],
            );
            $sessionId = $this->data->lastInsertId();
            $audit(
                'caixa_atendimento_aberto',
                'financeiro',
                $sessionId,
                [
                    'gaveta' => $locationId,
                    'saldo_inicial' => $openingBalance,
                    'audit_body' => 'Gaveta aberta pelo Atendimento.',
                ],
            );
            return [
                'session_id' => $sessionId,
                'authorization_requested' => false,
                'authorization_message' => '',
            ];
        });
    }

    public function close(
        int $clinicId,
        int $userId,
        int $sessionId,
        int $declaredCents,
        int $withdrawalCents,
        int $withdrawalDestinationId,
        string $notes,
        Closure $sessionExpected,
        Closure $assertAmount,
        Closure $checkedAdd,
        Closure $officeDestinationBelongs,
        Closure $ensureAdministratorSafe,
        Closure $validateMovement,
        Closure $defaultMovementTitle,
        Closure $recordCashDifference,
        Closure $lockDrawerAfterClose,
        Closure $audit,
    ): void {
        $this->data->atomic(function () use (
            $clinicId,
            $userId,
            $sessionId,
            $declaredCents,
            $withdrawalCents,
            $withdrawalDestinationId,
            $notes,
            $sessionExpected,
            $assertAmount,
            $checkedAdd,
            $officeDestinationBelongs,
            $ensureAdministratorSafe,
            $validateMovement,
            $defaultMovementTitle,
            $recordCashDifference,
            $lockDrawerAfterClose,
            $audit,
        ): void {
            $session = $this->data->row(
                'financial.08.close_session.01',
                [$sessionId, $clinicId, $userId],
            );
            if (!$session) {
                throw new RuntimeException('Não há Gaveta aberta para fechamento.');
            }
            $expected = (int) $sessionExpected($session);
            $declared = (int) $assertAmount(max(0, $declaredCents));
            $withdrawal = max(0, min($withdrawalCents, $declared));
            $keep = max(0, $declared - $withdrawal);
            $difference = (int) $checkedAdd(
                $declared,
                -$expected,
                'Diferença do fechamento',
            );
            $destinationId = 0;
            if ($withdrawal > 0) {
                if ($withdrawalDestinationId > 0) {
                    if (!$officeDestinationBelongs($clinicId, $withdrawalDestinationId)) {
                        throw new RuntimeException(
                            'Informe um Destino da Retirada válido entre as contas do Consultório.',
                        );
                    }
                    $destinationId = $withdrawalDestinationId;
                } else {
                    $destinationId = (int) $ensureAdministratorSafe($clinicId, $userId);
                }
                if ($destinationId <= 0) {
                    throw new RuntimeException(
                        'Não foi possível preparar o Destino da Retirada.',
                    );
                }
            }
            $this->data->result(
                'financial.08.close_session.02',
                [
                    $expected,
                    $declared,
                    $keep,
                    $withdrawal,
                    $difference,
                    trim($notes) ?: null,
                    $sessionId,
                    $clinicId,
                    $userId,
                ],
            );
            if ($withdrawal > 0) {
                $this->movements->create(
                    $clinicId,
                    'transfer',
                    $withdrawal,
                    (int) $session['location_id'],
                    $destinationId,
                    $sessionId,
                    $userId,
                    'Retirada do fechamento da Gaveta',
                    '',
                    trim($notes),
                    'pending_review',
                    'cash_session',
                    $sessionId,
                    $validateMovement,
                    $defaultMovementTitle,
                );
            }
            $recordCashDifference(
                $clinicId,
                $userId,
                $sessionId,
                (int) $session['location_id'],
                $difference,
                'closing',
                'pending_review',
                trim($notes),
                true,
            );
            $lockDrawerAfterClose(
                $clinicId,
                (int) $session['location_id'],
                (string) $session['business_date'],
                $sessionId,
                $userId,
            );
            $audit(
                'caixa_atendimento_fechado',
                'financeiro',
                $sessionId,
                [
                    'esperado' => $expected,
                    'declarado' => $declared,
                    'fazer_retirada' => $withdrawal,
                    'destino_retirada' => $destinationId,
                    'manter_gaveta' => $keep,
                    'diferenca' => $difference,
                ],
            );
        });
    }
}
