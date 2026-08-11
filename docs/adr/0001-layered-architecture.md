# ADR 0001 — Arquitetura em camadas

**Status:** aceito
**Data:** 2026-08-11

## Contexto

O produto precisa manter páginas simples enquanto lida com regras de autorização, persistência, financeiro e auditoria. A base histórica misturava essas decisões em Runtime.

## Decisão

Adotar Domain, Application, Infrastructure, Presentation e Runtime como camadas semânticas, com composition roots explícitos. Dependências proibidas são verificadas por contratos tokenizados.

## Consequências

Casos de uso críticos ganham surfaces testáveis; PDO e SQL ficam concentrados; páginas perdem responsabilidade transacional. Há custo de navegação entre classes, compensado por fronteiras mais estáveis. A arquitetura opera hoje em manutenção, não em expansão contínua de abstrações.
