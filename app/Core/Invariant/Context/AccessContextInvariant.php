<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

final class AccessContextInvariant
{
    private const TABLES = [
        "pi_user_roles",
        "pi_clinic_roles",
        "pi_permissions",
        "pi_permission_rules",
        "pi_financial_location_users",
    ];

    private function __construct() {
        








    }

    public static function supports(string $table): bool
    {
        








        return in_array($table, self::TABLES, true);
    }

    public static function assertWrite(string $table): array
    {
        








        return [
            "context" => "access",
            "checked" => true,
            "entity_table" => $table,
        ];
    }
}
