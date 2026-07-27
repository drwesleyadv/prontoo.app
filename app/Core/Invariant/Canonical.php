<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;

final class Canonical
{
    public const POLICY_VERSION = "layer3-php-layered-invariants-v1";

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Canonical::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Canonical.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function value(mixed $value): mixed
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Canonical::value
         * Responsabilidade: Implementa a responsabilidade “value” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Canonical.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Canonical::json`.
         * Dependências chamadas: `is_array`, `array_is_list`, `array_map`, `self::value`, `ksort`, `is_object`, `get_object_vars`, `is_float`, `is_nan`, `is_infinite`, `sprintf`, `is_resource` e mais 1.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map([self::class, "value"], $value);
            }
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[(string) $key] = self::value($item);
            }
            ksort($normalized, SORT_STRING);
            return $normalized;
        }
        if (is_object($value)) {
            return self::value(get_object_vars($value));
        }
        if (is_float($value)) {
            if (is_nan($value) || is_infinite($value)) {
                return (string) $value;
            }
            return (float) sprintf("%.12F", $value);
        }
        if (is_resource($value)) {
            return get_resource_type($value);
        }
        return $value;
    }

    public static function json(mixed $value): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Canonical::json
         * Responsabilidade: Gerencia o cache ou a memoização de “json”, reduzindo I/O sem substituir a fonte canônica.
         * Local arquitetural: app/Core/Invariant/Canonical.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Canonical::hash`, `Infrastructure.Audit.PdoActionProofStore::write`.
         * Dependências chamadas: `json_encode`, `self::value`.
         * Efeitos colaterais: produz conteúdo de saída.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        try {
            return json_encode(
                self::value($value),
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRESERVE_ZERO_FRACTION |
                    JSON_THROW_ON_ERROR,
            );
        } catch (\Throwable) {
            return "{}";
        }
    }

    public static function hash(string $domain, mixed $value): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Canonical::hash
         * Responsabilidade: Implementa a responsabilidade “hash” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Canonical.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Decision::make`, `Core.Invariant.Mutation.MutationLedger::record`, `Core.Invariant.Relation.ForeignKeyGraph::assertWrite`, `Core.Invariant.Workflow.AppointmentWorkflow::assertWrite`, `Domain.Authorization.ActionContract::hash`, `Infrastructure.Audit.PdoActionProofStore::write`.
         * Dependências chamadas: `preg_replace`, `trim`, `hash`, `self::json`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $domain = preg_replace("/[^a-z0-9_.:-]/i", "_", trim($domain)) ?: "proof";
        return hash(
            "sha256",
            self::POLICY_VERSION . "|" . $domain . "|" . self::json($value),
        );
    }

    public static function token(string $value, string $fallback = "unknown"): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Canonical::token
         * Responsabilidade: Implementa a responsabilidade “token” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Canonical.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Application.Authorization.ActionCatalog::actionToken`, `Application.Authorization.ActionCatalog::resolve`, `Application.Authorization.AuthorizationService::evaluate`, `Application.Authorization.AuthorizationService::splitCapability`, `Infrastructure.Audit.PdoActionProofStore::write`.
         * Dependências chamadas: `preg_replace`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return preg_replace("/[^a-z0-9_\-]/i", "", trim($value)) ?: $fallback;
    }
}
