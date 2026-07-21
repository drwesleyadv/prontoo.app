<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Tenant;

use Prontoo\Core\Tenant\TenantRegistry;

final class TenantContext
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Tenant.TenantContext::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Tenant/TenantContext.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function resolve(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Tenant.TenantContext::resolve
         * Responsabilidade: Localiza, carrega ou resolve os dados de “resolve” para consumo pelas camadas superiores.
         * Local arquitetural: app/Core/Invariant/Tenant/TenantContext.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::guard`.
         * Dependências chamadas: `max`, `self::globalOrAdminContext`, `TenantRegistry::sessionClinicId`.
         * Estado externo lido: `$GLOBALS`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"])) {
            return ["bypass" => true, "reason" => "guard_disabled", "clinic_id" => 0];
        }
        if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"])) {
            return ["bypass" => true, "reason" => "system_operation", "clinic_id" => 0];
        }
        $expected = max(
            0,
            (int) ($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] ?? 0),
        );
        if ($expected > 0) {
            return [
                "bypass" => false,
                "reason" => "declared_clinic_context",
                "clinic_id" => $expected,
            ];
        }
        if (self::globalOrAdminContext()) {
            return ["bypass" => true, "reason" => "global_operation", "clinic_id" => 0];
        }
        $clinicId = TenantRegistry::sessionClinicId();
        return [
            "bypass" => $clinicId <= 0,
            "reason" => $clinicId > 0 ? "session_clinic_context" : "no_clinic_context",
            "clinic_id" => $clinicId,
        ];
    }

    private static function globalOrAdminContext(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Tenant.TenantContext::globalOrAdminContext
         * Responsabilidade: Implementa a responsabilidade “global or admin context” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Tenant/TenantContext.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Tenant.TenantContext::resolve`.
         * Dependências chamadas: `session_status`, `function_exists`, `str_starts_with`, `self::sessionUserIsGlobalAdmin`.
         * Estado externo lido: `$_SESSION`.
         * Efeitos colaterais: lê ou altera a sessão.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        if ((string) ($_SESSION["scope"] ?? "") === "global") {
            return true;
        }
        $route = function_exists("route") ? (string) \route() : "";
        return str_starts_with($route, "admin_") && self::sessionUserIsGlobalAdmin();
    }

    private static function sessionUserIsGlobalAdmin(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Tenant.TenantContext::sessionUserIsGlobalAdmin
         * Responsabilidade: Implementa a responsabilidade “session user is global admin” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Tenant/TenantContext.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Tenant.TenantContext::globalOrAdminContext`.
         * Dependências chamadas: `function_exists`, `error_log`, `->getMessage`.
         * Estado externo lido: `$_SESSION`.
         * Efeitos colaterais: lê ou altera a sessão; gera trilha de auditoria ou telemetria.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $uid = (int) ($_SESSION["uid"] ?? 0);
        if ($uid <= 0 || !function_exists("user_is_global_admin")) {
            return false;
        }
        try {
            return (bool) \user_is_global_admin($uid);
        } catch (\Throwable $error) {
            error_log(
                "[Prontoo invariant tenant context] falha ao validar Desenvolvedor: " .
                    $error->getMessage(),
            );
            return false;
        }
    }
}
