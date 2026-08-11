# ADR 0003 — Action ledger

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Operações relevantes precisam de rastreabilidade e prova de que a sequência de ações pertence ao contexto correto.

## Decisão

Manter um ledger de ações de camada 2 como parte do schema canônico e usar políticas de integridade/auditoria para registrar eventos críticos sem espalhar lógica de ledger por páginas.

## Consequências

O ledger adiciona custo de persistência e verificação, mas cria uma base comum para auditoria e integridade. Trabalho diferido pode transportar contexto de prova, desde que o Maestro o restaure de forma controlada.
