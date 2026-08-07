<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Tenant;

final class SessionTenantAccess
{
    private function __construct()
    {
    }

    public static function clinicId(): int
    {
        return SessionTenantContext::clinicId();
    }

    public static function roleCode(): string
    {
        return SessionTenantContext::roleCode();
    }
}
