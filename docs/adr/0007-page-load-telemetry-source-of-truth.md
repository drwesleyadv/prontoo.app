# ADR 0007 — Carregamento de página como fonte de verdade da telemetria

**Status:** aceito

**Data:** 2026-08-06

## Contexto

A telemetria anterior registrava toda execução do front controller. Uma única navegação podia gerar eventos adicionais por `fetch`, XHR, consultas JSON, atualização do gráfico de telemetria e redirecionamentos. A métrica de requisições, por isso, não representava carregamentos de página.

## Decisão

A unidade contabilizada passa a ser uma navegação de documento HTML concluída. O candidato é identificado pelos metadados HTTP de navegação, pelo método, pelo destino e pelo formato aceito. Rotas internas, JSON, XHR, prefetch, prerender, assets, downloads e redirecionamentos não são persistidos.

O marco inicial é capturado na primeira linha executável do front controller, antes do primeiro `require`. O marco final normal é chamado explicitamente após a última etapa útil de renderização. O shutdown permanece apenas como fallback para encerramentos excepcionais e é identificado no evento.

Os eventos passam a usar o schema `prontoo.telemetria.pagina.v2` e o arquivo `ssd/telemetry/page-loads.jsonl`. Eventos legados de requisição não compõem os novos indicadores.

## Consequências

Cada navegação concluída produz no máximo um evento. Rotinas internas não alteram contagem nem tempo médio. Redirecionamentos somente contribuem por meio da página final realmente carregada. A série histórica reinicia semanticamente na publicação desta versão, preservando o arquivo legado sem utilizá-lo como fonte corrente.
