<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Context;

final class DocumentContextInvariant
{
    private const TABLES = [
        "pi_document_templates",
        "pi_documents",
        "pi_document_pdfs",
    ];

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.DocumentContextInvariant::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Context/DocumentContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function supports(string $table): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.DocumentContextInvariant::supports
         * Responsabilidade: Implementa a responsabilidade “supports” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/DocumentContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.ContextInvariantRegistry::assertWrite`, `Core.Invariant.Context.ContextInvariantRegistry::logicSelfTest`.
         * Dependências chamadas: `in_array`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return in_array($table, self::TABLES, true);
    }

    public static function assertWrite(string $table): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Context.DocumentContextInvariant::assertWrite
         * Responsabilidade: Implementa a responsabilidade “assert write” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Context/DocumentContextInvariant.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.ContextInvariantRegistry::assertWrite`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return [
            "context" => "documents",
            "checked" => true,
            "entity_table" => $table,
        ];
    }
}
