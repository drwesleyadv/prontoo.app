# ADR 0008 — Correção da fronteira de input adapters

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Após o fechamento semântico, restaram input adapters grandes. Decompor todos imediatamente criaria grande superfície de mudança sem evidência de defeito.

## Decisão

Tratar tamanho e acoplamentos residuais como ratchets e aplicar `refactor-on-touch`: hotspots acima de 500 linhas só podem ser alterados se uma métrica de dívida cair.

## Consequências

A dívida se reduz junto com mudanças úteis do produto. Arquivos estáveis não são reescritos apenas para melhorar métricas cosméticas.
