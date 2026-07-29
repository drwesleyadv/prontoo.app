# ADR-0003 — ledger transacional de ações

**Status:** aceito  
**Data:** 2026-07-29

## Contexto

Uma alteração sem prova auditável ou uma prova sem alteração correspondente produz inconsistência.

## Decisão

Persistir mutação e prova na mesma transação. Se qualquer parte falhar, ambas são revertidas.

## Alternativas

- auditoria assíncrona para todas as ações;
- log em arquivo;
- auditoria após o commit.

## Consequências

A consistência é forte e verificável. O caminho mutável assume custo adicional controlado. Trabalho secundário pode ser adiado, mas a prova essencial não pode se separar da mutação.
