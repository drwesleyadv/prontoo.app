<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class SubscriptionSettingsSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.subscription_settings.02.clinic_subscription_rejected_notice.01' => (
                "SELECT owner_user_id,manager_user_id FROM pi_clinics WHERE id=?"
            ),
            'operational.subscription_settings.02.clinic_subscription_rejected_notice.02' => (
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_user_id,created_at) VALUES (?,?,?,?,?,?,NOW())"
            ),
            'operational.subscription_settings.02.clinic_subscription_register_claim.01' => (
                "INSERT INTO pi_subscription_payments (clinic_id,created_by,amount_cents,status,trust_release,account_self,account_holder_name,proof_path,applied_until,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.subscription_settings.02.clinic_subscription_register_claim.02' => (
                "UPDATE pi_clinics SET active=1, subscription_status='active', paid_until=?, subscription_trust_blocked_until=NULL, subscription_last_payment_claim_at=NOW(), updated_at=NOW() WHERE id=?"
            ),
            'operational.subscription_settings.02.clinic_subscription_register_claim.03' => (
                "INSERT INTO pi_subscription_payments (clinic_id,created_by,amount_cents,status,trust_release,account_self,account_holder_name,proof_path,applied_until,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
