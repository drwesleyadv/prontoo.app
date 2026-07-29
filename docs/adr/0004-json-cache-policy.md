# ADR-0004 — cache JSON por geração

**Status:** aceito  
**Data:** 2026-07-29

## Contexto

Varreduras integrais de cache durante login e logout aumentam latência, criam contenção e dificultam recuperação.

## Decisão

Usar caches JSON preguiçosos, identificados por geração. A rotação da geração torna estados anteriores inalcançáveis sem apagar diretórios no caminho crítico.

## Alternativas

- limpeza síncrona completa;
- cache exclusivamente em banco;
- cache externo distribuído.

## Consequências

Login e logout ficam menores e previsíveis. Arquivos antigos podem permanecer até limpeza supervisionada, sem serem reutilizados. Credenciais e autorização continuam sendo revalidadas no banco quando necessário.
