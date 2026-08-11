# ADR 0005 — Ciclo supervisionado do Maestro

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Auditoria diferida e rotinas operacionais não devem alongar respostas HTTP nem depender de execução manual.

## Decisão

Executar Maestro server-side em ciclos supervisionados, com preflight, escopo de consultório, retry/backoff, dead-letter e estado operacional persistente.

## Consequências

A resposta web pode delegar trabalho não interativo sem perder rastreabilidade. O Maestro precisa permanecer idempotente onde possível e distinguir falha temporária, erro permanente e atenção histórica.
