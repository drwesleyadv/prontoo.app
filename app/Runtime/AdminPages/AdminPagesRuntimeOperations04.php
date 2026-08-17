<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AdminPages;

final class AdminPagesRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function page_admin_onboarding(): void
    {
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_clinics", ["view" => "onboarding"]);
    }
}
