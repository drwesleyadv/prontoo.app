# ADR-0002 — isolamento por consultório

**Status:** aceito  
**Data:** 2026-07-29

## Contexto

O Prontoo processa dados clínicos e financeiros de múltiplos consultórios. Um erro de escopo pode expor dados de terceiros.

## Decisão

Tratar `clinic_id` como invariante estrutural. Toda operação protegida revalida o contexto e toda consulta operacional deve provar seu escopo.

## Alternativas

- filtros voluntários por página;
- banco separado por consultório;
- schema separado por consultório.

## Consequências

Filtros voluntários são rejeitados por serem frágeis. Bancos separados aumentariam custo operacional. O modelo compartilhado exige guardas, testes negativos e falha fechada.
