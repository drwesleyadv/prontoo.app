<?php
declare(strict_types=1);
namespace Prontoo\Core\Support;
final class Check
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Support.Check::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Support/Check.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }
    public static function positiveInt(mixed $value, string $field): int
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Support.Check::positiveInt
         * Responsabilidade: Implementa a responsabilidade “positive int” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Support/Check.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `filter_var`.
         * Classes ou serviços instanciados: `.InvalidArgumentException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $int = filter_var($value, FILTER_VALIDATE_INT, [
            "options" => ["min_range" => 1],
        ]);
        if ($int === false) {
            throw new \InvalidArgumentException(
                $field . " deve ser um inteiro positivo.",
            );
        }
        return (int) $int;
    }
    public static function nonNegativeInt(mixed $value, string $field): int
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Support.Check::nonNegativeInt
         * Responsabilidade: Implementa a responsabilidade “non negative int” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Support/Check.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `filter_var`.
         * Classes ou serviços instanciados: `.InvalidArgumentException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $int = filter_var($value, FILTER_VALIDATE_INT, [
            "options" => ["min_range" => 0],
        ]);
        if ($int === false) {
            throw new \InvalidArgumentException(
                $field . " deve ser zero ou inteiro positivo.",
            );
        }
        return (int) $int;
    }
    public static function identifier(
        string $value,
        string $field = "identificador",
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — Core.Support.Check::identifier
         * Responsabilidade: Implementa a responsabilidade “identifier” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Support/Check.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Support.Check::tableHit`.
         * Dependências chamadas: `trim`, `preg_match`.
         * Classes ou serviços instanciados: `.InvalidArgumentException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $value = trim($value);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new \InvalidArgumentException($field . " inválido.");
        }
        return $value;
    }
    public static function scopedColumn(string $value): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Support.Check::scopedColumn
         * Responsabilidade: Implementa a responsabilidade “scoped column” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Support/Check.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Tenant.TenantRegistry::excludeModelClinicSql`, `Core.Tenant.TenantRegistry::excludeModelClinicWhere`.
         * Dependências chamadas: `trim`, `preg_match`.
         * Classes ou serviços instanciados: `.InvalidArgumentException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $value = trim($value);
        if (!preg_match('/^[A-Za-z0-9_`\.]+$/', $value)) {
            throw new \InvalidArgumentException("Coluna de escopo inválida.");
        }
        return $value;
    }
    public static function normalizedSql(string $sql): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Support.Check::normalizedSql
         * Responsabilidade: Implementa a responsabilidade “normalized sql” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Support/Check.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`, `Core.Readonly.ReadonlyPolicy::sqlAllowed`.
         * Dependências chamadas: `strtolower`, `preg_replace`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return strtolower(preg_replace("/\s+/", " ", trim($sql)) ?? "");
    }
    public static function writeOperation(string $sql): ?string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Support.Check::writeOperation
         * Responsabilidade: Implementa a responsabilidade “write operation” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Support/Check.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`.
         * Dependências chamadas: `preg_match`, `strtoupper`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (
            !preg_match(
                "/^\s*(UPDATE|DELETE|INSERT|REPLACE)\s+/i",
                $sql,
                $match,
            )
        ) {
            return null;
        }
        return strtoupper($match[1]);
    }
    public static function sqlFingerprint(string $sql): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Support.Check::sqlFingerprint
         * Responsabilidade: Implementa a responsabilidade “sql fingerprint” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Support/Check.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::recordViolation`.
         * Dependências chamadas: `hash`, `preg_replace`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return hash("sha256", preg_replace("/\s+/", " ", trim($sql)) ?? "");
    }
    public static function tableHit(string $normalizedSql, string $table): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Support.Check::tableHit
         * Responsabilidade: Implementa a responsabilidade “table hit” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Support/Check.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`, `Core.Readonly.ReadonlyPolicy::sqlAllowed`.
         * Dependências chamadas: `self::identifier`, `strtolower`, `preg_match`, `preg_quote`, `str_contains`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        self::identifier($table, "tabela");
        $table = strtolower($table);
        $quoted = "`" . $table . "`";
        return preg_match(
            "/\b" . preg_quote($table, "/") . "\b/",
            $normalizedSql,
        ) === 1 || str_contains($normalizedSql, $quoted);
    }
    public static function clampText(string $text, int $max): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Support.Check::clampText
         * Responsabilidade: Implementa a responsabilidade “clamp text” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Support/Check.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::recordViolation`.
         * Dependências chamadas: `max`, `function_exists`, `mb_substr`, `substr`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $max = max(1, $max);
        if (function_exists("mb_substr")) {
            return mb_substr($text, 0, $max, "UTF-8");
        }
        return substr($text, 0, $max);
    }
}
