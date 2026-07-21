<?php
declare(strict_types=1);
namespace Prontoo\Core\Tenant;
use Prontoo\Core\Support\Check;
final class TenantRegistry
{
    private static ?int $modelClinicCache = null;
    private const SCOPED_TABLES = [
        "pi_user_roles" => "clinic_id",
        "pi_clinic_roles" => "clinic_id",
        "pi_permissions" => "clinic_id",
        "pi_permission_rules" => "clinic_id",
        "pi_patients" => "clinic_id",
        "pi_patient_tabs" => "clinic_id",
        "pi_patient_guardians" => "clinic_id",
        "pi_leads" => "clinic_id",
        "pi_lead_events" => "clinic_id",
        "pi_appointments" => "clinic_id",
        "pi_blocks" => "clinic_id",
        "pi_agenda_notes" => "clinic_id",
        "pi_tasks" => "clinic_id",
        "pi_task_details" => "clinic_id",
        "pi_task_events" => "clinic_id",
        "pi_task_comments" => "clinic_id",
        "pi_notices" => "clinic_id",
        "pi_document_templates" => "clinic_id",
        "pi_documents" => "clinic_id",
        "pi_document_pdfs" => "clinic_id",
        "pi_user_work_hours" => "clinic_id",
        "pi_procedures" => "clinic_id",
        "pi_financial_accounts" => "clinic_id",
        "pi_financial_payment_methods" => "clinic_id",
        "pi_financial_counterparties" => "clinic_id",
        "pi_financial_revenues" => "clinic_id",
        "pi_financial_expenses" => "clinic_id",
        "pi_financial_allocations" => "clinic_id",
        "pi_financial_goals" => "clinic_id",
        "pi_financial_transfers" => "clinic_id",
        "pi_financial_locations" => "clinic_id",
        "pi_financial_location_users" => "clinic_id",
        "pi_cash_sessions" => "clinic_id",
        "pi_financial_movements" => "clinic_id",
        "pi_financial_daily_closings" => "clinic_id",
        "pi_cash_closing_reviews" => "clinic_id",
        "pi_subscription_payments" => "clinic_id",
        "pi_care" => "clinic_id",
        "pi_care_content" => "clinic_id",
        "pi_care_versions" => "clinic_id",
        "pi_clinic_daily_stats" => "clinic_id",
        "pi_maestro_rules" => "clinic_id",
        "pi_maestro_executions" => "clinic_id",
    ];
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }
    public static function scopedTables(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::scopedTables
         * Responsabilidade: Implementa a responsabilidade “scoped tables” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`, `Core.Database.TenantIntegrity::assertRegistryMatchesSchema`, `Core.Readonly.ReadonlyPolicy::sqlAllowed`, `tenant_scoped_tables`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return self::SCOPED_TABLES;
    }
    public static function isScoped(string $table): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::isScoped
         * Responsabilidade: Implementa a responsabilidade “is scoped” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Relation.ForeignKeyGraph::assertWrite`, `tenant_table_is_scoped`.
         * Dependências chamadas: `array_key_exists`, `strtolower`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return array_key_exists(strtolower($table), self::SCOPED_TABLES);
    }
    public static function scopeColumn(string $table): ?string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::scopeColumn
         * Responsabilidade: Implementa a responsabilidade “scope column” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::guard`, `Core.Invariant.Relation.ForeignKeyGraph::targetClinicId`.
         * Dependências chamadas: `strtolower`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $table = strtolower($table);
        return self::SCOPED_TABLES[$table] ?? null;
    }
    public static function sessionClinicId(): int
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::sessionClinicId
         * Responsabilidade: Implementa a responsabilidade “session clinic id” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`, `Core.Invariant.Tenant.TenantContext::resolve`, `session_clinic_scope_id`.
         * Dependências chamadas: `session_status`, `max`.
         * Estado externo lido: `$_SESSION`.
         * Efeitos colaterais: lê ou altera a sessão.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 0;
        }
        if (($_SESSION["scope"] ?? "") !== "clinic") {
            return 0;
        }
        return max(0, (int) ($_SESSION["clinic_id"] ?? 0));
    }
    public static function sessionRoleCode(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::sessionRoleCode
         * Responsabilidade: Implementa a responsabilidade “session role code” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `session_clinic_role_code`.
         * Dependências chamadas: `session_status`, `preg_replace`.
         * Estado externo lido: `$_SESSION`.
         * Efeitos colaterais: lê ou altera a sessão.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return "";
        }
        return preg_replace(
            "/[^a-z0-9_\-]/i",
            "",
            (string) ($_SESSION["role_code"] ?? ""),
        ) ?:
            "";
    }
    private static function globalAdminExemptSubquery(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::globalAdminExemptSubquery
         * Responsabilidade: Implementa a responsabilidade “global admin exempt subquery” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Tenant.TenantRegistry::excludeModelClinicSql`, `Core.Tenant.TenantRegistry::excludeModelClinicWhere`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: consulta dados persistidos.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return "SELECT c.id FROM pi_clinics c LEFT JOIN pi_users owner_user ON owner_user.id=c.owner_user_id LEFT JOIN pi_users manager_user ON manager_user.id=c.manager_user_id WHERE c.subscription_status='exempt' AND (COALESCE(owner_user.is_global_admin,0)=1 OR COALESCE(manager_user.is_global_admin,0)=1)";
    }
    public static function excludeModelClinicSql(
        string $column = "clinic_id",
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::excludeModelClinicSql
         * Responsabilidade: Implementa a responsabilidade “exclude model clinic sql” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Metrics.GlobalMetricScope::modelClinicSql`.
         * Dependências chamadas: `Check::scopedColumn`, `self::globalAdminExemptSubquery`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $column = Check::scopedColumn($column);
        return " AND " .
            $column .
            " NOT IN (" .
            self::globalAdminExemptSubquery() .
            ") ";
    }
    public static function excludeModelClinicWhere(
        string $column = "clinic_id",
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::excludeModelClinicWhere
         * Responsabilidade: Implementa a responsabilidade “exclude model clinic where” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Metrics.GlobalMetricScope::modelClinicWhere`.
         * Dependências chamadas: `Check::scopedColumn`, `self::globalAdminExemptSubquery`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $column = Check::scopedColumn($column);
        return $column . " NOT IN (" . self::globalAdminExemptSubquery() . ")";
    }
    public static function modelClinicId(): int
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::modelClinicId
         * Responsabilidade: Implementa a responsabilidade “model clinic id” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Metrics.GlobalMetricScope::countNote`, `admin_model_clinic_id`.
         * Dependências chamadas: `function_exists`.
         * Efeitos colaterais: consulta dados persistidos.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (self::$modelClinicCache !== null) {
            return self::$modelClinicCache;
        }
        if (!function_exists("safe_val")) {
            return self::$modelClinicCache = 0;
        }
        return self::$modelClinicCache = (int) \safe_val(
            "SELECT c.id FROM pi_clinics c LEFT JOIN pi_users owner_user ON owner_user.id=c.owner_user_id LEFT JOIN pi_users manager_user ON manager_user.id=c.manager_user_id WHERE c.subscription_status='exempt' AND (COALESCE(owner_user.is_global_admin,0)=1 OR COALESCE(manager_user.is_global_admin,0)=1) ORDER BY c.id ASC LIMIT 1",
            [],
            0,
        );
    }
    public static function resetModelClinicCache(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Tenant.TenantRegistry::resetModelClinicCache
         * Responsabilidade: Implementa a responsabilidade “reset model clinic cache” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Tenant/TenantRegistry.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `page_admin_clinics`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        self::$modelClinicCache = null;
    }
}
