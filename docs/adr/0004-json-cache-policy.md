# ADR 0004 — Política de cache JSON

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Painéis e leituras agregadas precisam ser rápidos, mas cache não pode transformar dados clínicos ou financeiros em estado incoerente por longos períodos.

## Decisão

Usar cache JSON server-side com TTL curto e invalidação orientada pelo domínio. Cache é otimização; não é fonte de verdade e não substitui invariantes do banco.

## Consequências

Falha de cache deve degradar para leitura válida sempre que a política permitir. Escritas relevantes invalidam gerações/chaves seletivas. Budgets de consulta continuam medindo o caminho real para evitar que o cache esconda regressões arquiteturais.
