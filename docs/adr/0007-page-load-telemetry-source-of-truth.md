# ADR 0007 — Navegação HTML como fonte de telemetria de página

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Contar toda requisição HTTP como “carregamento de página” mistura fetch, XHR, JSON, redirects e assets, tornando métricas de experiência pouco interpretáveis.

## Decisão

Uma carga de página é uma navegação de documento HTML concluída. A medição começa no primeiro ponto executável do front controller e termina após o último passo útil de renderização. Requisições auxiliares ficam fora dessa série.

## Consequências

Telemetria de página passa a medir experiência de navegação, enquanto outras requisições podem ser observadas separadamente.
