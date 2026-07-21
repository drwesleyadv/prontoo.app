<?php
declare(strict_types=1);
namespace Prontoo\Core\Metrics;
use Prontoo\Core\Tenant\TenantRegistry;
final class GlobalMetricScope
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Metrics.GlobalMetricScope::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Metrics/GlobalMetricScope.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }
    public static function modelClinicSql(string $column = "clinic_id"): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Metrics.GlobalMetricScope::modelClinicSql
         * Responsabilidade: Implementa a responsabilidade “model clinic sql” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Metrics/GlobalMetricScope.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `admin_model_clinic_exclude_sql`.
         * Dependências chamadas: `TenantRegistry::excludeModelClinicSql`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return TenantRegistry::excludeModelClinicSql($column);
    }
    public static function modelClinicWhere(
        string $column = "clinic_id",
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — Core.Metrics.GlobalMetricScope::modelClinicWhere
         * Responsabilidade: Implementa a responsabilidade “model clinic where” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Metrics/GlobalMetricScope.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `admin_model_clinic_exclude_where`.
         * Dependências chamadas: `TenantRegistry::excludeModelClinicWhere`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return TenantRegistry::excludeModelClinicWhere($column);
    }
    public static function countNote(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Metrics.GlobalMetricScope::countNote
         * Responsabilidade: Implementa a responsabilidade “count note” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Metrics/GlobalMetricScope.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `admin_model_clinic_count_note`.
         * Dependências chamadas: `TenantRegistry::modelClinicId`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return TenantRegistry::modelClinicId() > 0
            ? "consultório isento do Desenvolvedor descontado"
            : "consultórios isentos do Desenvolvedor descontados quando existirem";
    }
}
