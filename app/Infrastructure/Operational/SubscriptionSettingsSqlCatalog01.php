<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class SubscriptionSettingsSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.subscription_settings.01.ensure_clinic_trial_active.01' => (
                "SELECT id,onboarding_done,created_at,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents FROM pi_clinics WHERE id=? LIMIT 1"
            ),
            'operational.subscription_settings.01.ensure_clinic_trial_active.02' => (
                "UPDATE pi_clinics SET subscription_status='trial', trial_started_at=?, trial_ends_at=?, monthly_price_cents=?, paid_until=NULL, updated_at=NOW() WHERE id=?"
            ),
            'operational.subscription_settings.01.page_admin_payment_proof.01' => (
                "SELECT sp.id,sp.clinic_id,sp.status,sp.proof_path,c.display_name FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.id=?"
            ),
            'operational.subscription_settings.01.clinic_subscription_pending_payment.01' => (
                "SELECT id,amount_cents,proof_path,applied_until,created_at FROM pi_subscription_payments WHERE clinic_id=? AND status='pending_admin' ORDER BY id DESC LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
