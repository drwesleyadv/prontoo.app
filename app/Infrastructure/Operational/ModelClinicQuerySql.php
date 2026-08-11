<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use Prontoo\Core\Support\Check;

final class ModelClinicQuerySql
{
    private function __construct()
    {
    }

    public static function exclude(string $column = 'clinic_id'): string
    {
        return ' AND ' . self::where($column) . ' ';
    }

    public static function where(string $column = 'clinic_id'): string
    {
        return Check::scopedColumn($column) . ' NOT IN (' . self::globalAdminExemptSubquery() . ')';
    }

    private static function globalAdminExemptSubquery(): string
    {
        return "SELECT c.id FROM pi_clinics c LEFT JOIN pi_users owner_user ON owner_user.id=c.owner_user_id LEFT JOIN pi_users manager_user ON manager_user.id=c.manager_user_id WHERE c.subscription_status='exempt' AND (COALESCE(owner_user.is_global_admin,0)=1 OR COALESCE(manager_user.is_global_admin,0)=1)";
    }
}
