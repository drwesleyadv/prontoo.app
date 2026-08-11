<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class SubscriptionSettingsSqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.subscription_settings.03.page_settings.01' => (
                "SELECT id,display_name,legal_name,legal_document,phone,responsible_profession,clinic_icon,accent_color,address_line,address_state,address_city,address_city_ibge,timezone,workflow_note,onboarding_done,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents,subscription_trust_blocked_until,subscription_last_payment_claim_at FROM pi_clinics WHERE id=?"
            ),
            'operational.subscription_settings.03.page_settings.02' => (
                "UPDATE pi_clinics SET display_name=?, legal_name=?, legal_document=?, phone=?, responsible_profession=?, address_line=?, address_state=?, address_city=?, address_city_ibge=?, timezone=?, updated_at=NOW() WHERE id=?"
            ),
            'operational.subscription_settings.03.page_settings.03' => (
                "INSERT INTO pi_clinic_roles (clinic_id,role_code,label,icon_name,enabled,sort_order) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label), icon_name=VALUES(icon_name), sort_order=VALUES(sort_order), enabled=VALUES(enabled)"
            ),
            'operational.subscription_settings.03.page_settings.04' => (
                "UPDATE pi_clinics SET clinic_icon=?, accent_color=?, updated_at=NOW() WHERE id=?"
            ),
            'operational.subscription_settings.03.page_settings.05' => (
                "SELECT id,display_name,legal_name,legal_document,phone,responsible_profession,clinic_icon,accent_color,address_line,address_state,address_city,address_city_ibge,timezone,workflow_note,onboarding_done,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents,subscription_trust_blocked_until,subscription_last_payment_claim_at FROM pi_clinics WHERE id=?"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
