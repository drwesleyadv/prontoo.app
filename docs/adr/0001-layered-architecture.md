# ADR-0001 — arquitetura em camadas

**Status:** aceito  
**Data:** 2026-07-29

## Contexto

O sistema cresceu a partir de páginas PHP com responsabilidades combinadas. Segurança, financeiro e isolamento exigem decisões uniformes e testáveis.

## Decisão

Adotar monólito modular com `Core`, `Domain`, `Application`, `Infrastructure`, `Presentation` e `Composition`. Dependências apontam para dentro.

## Alternativas

- MVC clássico;
- páginas procedurais;
- microserviços.

## Consequências

A arquitetura reduz acoplamento e centraliza invariantes. A migração é incremental e mantém fronteiras transitórias. Microserviços não são adotados porque aumentariam complexidade operacional sem benefício proporcional.
