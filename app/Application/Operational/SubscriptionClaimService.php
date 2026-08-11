<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

use Closure;

final class SubscriptionClaimService
{
    public function __construct(private OperationalUseCaseService $data)
    {
    }

    public function register(
        bool $blocked,
        int $clinicId,
        int $userId,
        int $priceCents,
        bool $ownAccount,
        string $holderName,
        ?string $proof,
        string $renewalUntil,
        string $provisionalUntil,
        Closure $audit,
    ): string {
        return (string) $this->data->atomic(function () use (
            $blocked,
            $clinicId,
            $userId,
            $priceCents,
            $ownAccount,
            $holderName,
            $proof,
            $renewalUntil,
            $provisionalUntil,
            $audit,
        ): string {
            if ($blocked) {
                $this->data->result(
                    'operational.subscription_settings.02.clinic_subscription_register_claim.01',
                    [
                        $clinicId,
                        $userId,
                        $priceCents,
                        'pending_admin',
                        0,
                        $ownAccount ? 1 : 0,
                        $holderName !== '' ? $holderName : null,
                        $proof,
                        $renewalUntil,
                    ],
                );
                $paymentId = $this->data->lastInsertId();
                $audit('assinatura_comprovante_enviado', 'assinatura', $clinicId, [
                    'pagamento_id' => $paymentId,
                    'valor' => $priceCents,
                    'renovacao_ate' => $renewalUntil,
                    'audit_body' =>
                        'Responsável enviou comprovante após recusa anterior. Uma nova Ação Recomendada foi disponibilizada para o Desenvolvedor visualizar, aprovar ou recusar o comprovante. O consultório permanece em Somente Leitura até aprovação administrativa.',
                ]);
                return 'Comprovante enviado. A administração vai conferir o arquivo para liberar a assinatura.';
            }
            $this->data->result(
                'operational.subscription_settings.02.clinic_subscription_register_claim.02',
                [$provisionalUntil, $clinicId],
            );
            $this->data->result(
                'operational.subscription_settings.02.clinic_subscription_register_claim.03',
                [
                    $clinicId,
                    $userId,
                    $priceCents,
                    'pending_admin',
                    1,
                    $ownAccount ? 1 : 0,
                    $holderName !== '' ? $holderName : null,
                    null,
                    $renewalUntil,
                ],
            );
            $paymentId = $this->data->lastInsertId();
            $audit('assinatura_pagamento_informado', 'assinatura', $clinicId, [
                'pagamento_id' => $paymentId,
                'valor' => $priceCents,
                'liberado_ate' => $provisionalUntil,
                'renovacao_ate' => $renewalUntil,
                'audit_body' =>
                    'Responsável informou pagamento da assinatura. Uma Ação Recomendada foi disponibilizada para confirmação ou recusa pelo Desenvolvedor. O acesso operacional foi liberado automaticamente em confiança por prazo operacional interno enquanto aguarda conferência administrativa.',
            ]);
            return 'Obrigado. Já liberamos sua assinatura em confiança enquanto o banco confirma sua transação. Aproveite!';
        });
    }
}
