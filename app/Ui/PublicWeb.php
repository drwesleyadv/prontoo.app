<?php
declare(strict_types=1);
if (!function_exists("public_web_escape")) {
    function public_web_escape($v): string
    {
        /*
         * GUIA DE MANUTENÇÃO — public_web_escape
         * Responsabilidade: Implementa a responsabilidade “public web escape” dentro do módulo de componentes e composição visual.
         * Local arquitetural: app/Ui/PublicWeb.php (componentes e composição visual).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `htmlspecialchars`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return htmlspecialchars(
            (string) $v,
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8",
        );
    }
}
function page_mobile_web_access(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_mobile_web_access
     * Responsabilidade: Coordena a rota e renderiza a tela “page mobile web access”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Ui/PublicWeb.php (componentes e composição visual).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `redirect`.
     * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    redirect("login", ["source" => "mobile_web"]);
}
