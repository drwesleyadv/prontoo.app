<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class ClinicConfigSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.clinic_config.01.clinic_visual.01' => (
                "SELECT clinic_icon,accent_color,responsible_profession FROM pi_clinics WHERE id=?"
            ),
            'operational.clinic_config.01.clinic_role_sector_fields.01' => (
                "SELECT role_code,icon_name FROM pi_clinic_roles WHERE clinic_id=?"
            ),
            'operational.clinic_config.01.clinic_profession.01' => (
                "SELECT responsible_profession FROM pi_clinics WHERE id=?"
            ),
            'operational.clinic_config.01.clinic_is_global_admin_owned.01' => (
                "SELECT COUNT(*) FROM pi_clinics c LEFT JOIN pi_users owner_user ON owner_user.id=c.owner_user_id LEFT JOIN pi_users manager_user ON manager_user.id=c.manager_user_id WHERE c.id=? AND (COALESCE(owner_user.is_global_admin,0)=1 OR COALESCE(manager_user.is_global_admin,0)=1)"
            ),
            'operational.clinic_config.01.clinic_metric_inc.01' => (
                "INSERT INTO pi_clinic_daily_stats (clinic_id,day_date,metric_key,metric_value,updated_at) VALUES (?,CURDATE(),?,?,NOW()) ON DUPLICATE KEY UPDATE metric_value=metric_value+VALUES(metric_value), updated_at=NOW()"
            ),
            'operational.clinic_config.01.clinic_read_only_db.01' => (
                "SELECT subscription_status,paid_until,trial_ends_at FROM pi_clinics WHERE id=? LIMIT 1"
            ),
            'operational.clinic_config.01.seed_clinic_roles.01' => (
                "INSERT INTO pi_clinic_roles (clinic_id,role_code,label,icon_name,enabled,sort_order) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE icon_name=IF(icon_name='',VALUES(icon_name),icon_name), sort_order=IF(sort_order IS NULL OR sort_order=0,VALUES(sort_order),sort_order)"
            ),
            'operational.clinic_config.01.clinic_roles.01' => (
                "SELECT role_code,label,enabled FROM pi_clinic_roles WHERE clinic_id=? ORDER BY sort_order, role_code"
            ),
            'operational.clinic_config.01.clinic_roles.02' => (
                "SELECT role_code,label,enabled FROM pi_clinic_roles WHERE clinic_id=? ORDER BY sort_order, role_code"
            ),
            'operational.clinic_config.01.clinic_role_icons.01' => (
                "SELECT role_code,icon_name,enabled FROM pi_clinic_roles WHERE clinic_id=? ORDER BY sort_order, role_code"
            ),
            'operational.clinic_config.01.is_responsible_doctor.01' => (
                "SELECT id FROM pi_clinics WHERE id=? AND owner_user_id=?"
            ),
            'operational.clinic_config.01.onboarding_pending.01' => (
                "SELECT onboarding_done FROM pi_clinics WHERE id=?"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
