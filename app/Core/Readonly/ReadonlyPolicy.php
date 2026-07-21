<?php
declare(strict_types=1);
namespace Prontoo\Core\Readonly;
use Prontoo\Core\Support\Check;
use Prontoo\Core\Tenant\TenantRegistry;
final class ReadonlyPolicy
{
    private const PASSIVE_ROUTES = [
        "login",
        "login_autotest",
        "logout",
        "switch",
        "profile",
        "onboarding",
    ];
    private const PASSIVE_WRITE_TABLES = [
        "pi_audit",
        "pi_user_devices",
        "pi_login_locks",
        "pi_clinic_roles",
        "pi_permissions",
        "pi_platform_counters",
        "pi_users",
        "pi_persons",
    ];
    private const SUBSCRIPTION_WRITE_TABLES = [
        "pi_subscription_payments",
        "pi_clinics",
        "pi_audit",
        "pi_notices",
        "pi_notice_reads",
        "pi_platform_counters",
    ];
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Readonly.ReadonlyPolicy::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Readonly/ReadonlyPolicy.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }
    public static function postAllowed(string $route, string $action = ""): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Readonly.ReadonlyPolicy::postAllowed
         * Responsabilidade: Implementa a responsabilidade “post allowed” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Readonly/ReadonlyPolicy.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `read_only_post_allowed`.
         * Dependências chamadas: `self::cleanRoute`, `self::cleanAction`, `in_array`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $route = self::cleanRoute($route);
        $action = self::cleanAction($action);
        if (in_array($route, self::PASSIVE_ROUTES, true)) {
            return true;
        }
        if (
            $route === "notices" &&
            in_array(
                $action,
                ["support_message", "ack", "hide", "unhide"],
                true,
            )
        ) {
            return true;
        }
        return $route === "settings" && $action === "subscription_claim";
    }
    public static function allowedWriteTables(
        string $route,
        string $action = "",
        bool $isClinicScope = true,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Readonly.ReadonlyPolicy::allowedWriteTables
         * Responsabilidade: Implementa a responsabilidade “allowed write tables” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Readonly/ReadonlyPolicy.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Readonly.ReadonlyPolicy::sqlAllowed`, `read_only_allowed_write_tables_for_request`.
         * Dependências chamadas: `self::cleanRoute`, `self::cleanAction`, `in_array`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
         */
        if (!$isClinicScope) {
            return ["*"];
        }
        $route = self::cleanRoute($route);
        $action = self::cleanAction($action);
        if ($route === "onboarding") {
            return [
                "pi_clinics",
                "pi_clinic_roles",
                "pi_permissions",
                "pi_permission_rules",
                "pi_persons",
                "pi_users",
                "pi_user_roles",
                "pi_user_work_hours",
                "pi_audit",
                "pi_platform_counters",
            ];
        }
        if (in_array($route, self::PASSIVE_ROUTES, true)) {
            return self::PASSIVE_WRITE_TABLES;
        }
        if ($route === "notices" && $action === "support_message") {
            return ["pi_admin_alerts", "pi_audit", "pi_platform_counters"];
        }
        if (
            $route === "notices" &&
            in_array($action, ["ack", "hide", "unhide"], true)
        ) {
            return ["pi_notice_reads", "pi_audit", "pi_platform_counters"];
        }
        if ($route === "settings" && $action === "subscription_claim") {
            return self::SUBSCRIPTION_WRITE_TABLES;
        }
        return [];
    }
    public static function sqlAllowed(
        string $sql,
        string $route,
        string $action = "",
        bool $isClinicScope = true,
    ): bool {
        /*
         * GUIA DE MANUTENÇÃO — Core.Readonly.ReadonlyPolicy::sqlAllowed
         * Responsabilidade: Implementa a responsabilidade “sql allowed” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Readonly/ReadonlyPolicy.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`, `Core.Invariant.Mutation.MutationInvariant::assertReadonly`, `read_only_write_allowed_for_sql`.
         * Dependências chamadas: `self::allowedWriteTables`, `in_array`, `Check::normalizedSql`, `TenantRegistry::scopedTables`, `Check::tableHit`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $allowed = self::allowedWriteTables($route, $action, $isClinicScope);
        if (in_array("*", $allowed, true)) {
            return true;
        }
        if ($allowed === []) {
            return false;
        }
        $norm = Check::normalizedSql($sql);
        foreach (TenantRegistry::scopedTables() as $table => $scopeCol) {
            if (Check::tableHit($norm, $table)) {
                return in_array($table, $allowed, true);
            }
        }
        return false;
    }
    private static function cleanRoute(string $route): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Readonly.ReadonlyPolicy::cleanRoute
         * Responsabilidade: Implementa a responsabilidade “clean route” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Readonly/ReadonlyPolicy.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Readonly.ReadonlyPolicy::postAllowed`, `Core.Readonly.ReadonlyPolicy::allowedWriteTables`.
         * Dependências chamadas: `preg_replace`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return preg_replace("/[^a-z0-9_\-]/i", "", $route) ?: "login";
    }
    private static function cleanAction(string $action): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Readonly.ReadonlyPolicy::cleanAction
         * Responsabilidade: Implementa a responsabilidade “clean action” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Readonly/ReadonlyPolicy.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Readonly.ReadonlyPolicy::postAllowed`, `Core.Readonly.ReadonlyPolicy::allowedWriteTables`.
         * Dependências chamadas: `preg_replace`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return preg_replace("/[^a-z0-9_\-]/i", "", $action) ?: "";
    }
}
