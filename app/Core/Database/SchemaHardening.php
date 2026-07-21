<?php
declare(strict_types=1);

namespace Prontoo\Core\Database;

final class SchemaHardening
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaHardening::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Database/SchemaHardening.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
    }

    public static function run(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaHardening::run
         * Responsabilidade: Orquestra a execução de “run” e delega etapas específicas às dependências do módulo.
         * Local arquitetural: app/Core/Database/SchemaHardening.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if (!\function_exists("schema_validate_complete")) {
            throw new \RuntimeException(
                "Validador do schema não foi carregado.",
            );
        }
        \schema_validate_complete();
    }
}
