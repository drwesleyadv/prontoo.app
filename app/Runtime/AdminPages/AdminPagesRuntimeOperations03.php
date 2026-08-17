<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AdminPages;

final class AdminPagesRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function page_admin_operations(): void
    {
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_operations");
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Indicadores do negócio",
                "Relatório sob demanda de adoção, operação e financeiro da plataforma.",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_ops_finance_html(),
                "admin-ops-finance-card",
            );
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Indicadores do negócio · Desenvolvedor", $body);
    }
}
