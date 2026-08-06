# Telemetria de carregamento de página

## Fonte de verdade

A fonte corrente é `ssd/telemetry/page-loads.jsonl`. Cada linha válida representa uma página HTML carregada, não uma requisição auxiliar.

## Marcos de duração

- início: `front_controller_first_executable_line`, capturado antes do primeiro carregamento de módulo;
- fim normal: `front_controller_last_useful_line`, chamado depois da renderização da página;
- fallback: `shutdown_fallback`, reservado para encerramentos excepcionais nos quais o fluxo normal não alcançou o fechamento explícito.

O tempo de persistência da própria telemetria e a compactação do arquivo ocorrem depois da captura do marco final e não contaminam a duração medida.

## Exclusões

Não são contabilizados JSON, XHR, `fetch`, rotas de lookup, atualização da meta, autoteste de login, atualização do gráfico, prefetch, prerender, assets, anexos e respostas de redirecionamento.

## Diagnóstico

Um evento deve conter `tipo=page_load`, schema `prontoo.telemetria.pagina.v2`, rota, método, caminho sem query string, marcos, duração, status e versão. O caminho não armazena parâmetros para evitar persistência de dados pessoais.

A validação permanente é executada por `tools/architecture-check.php`, que inclui `tools/page-load-telemetry-contract-check`.

## Retenção e apresentação

Os eventos são mantidos por 31 dias corridos para sustentar uma visão completa dos últimos 30 dias civis, incluindo hoje. Na interface, a contagem recebe o nome **Visualizações**, a duração média recebe o nome **Velocidade média** e a rota `landing` é apresentada como **Visualizações da Landing**.
