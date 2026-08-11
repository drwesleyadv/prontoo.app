# ADR-0005 — fechamento semântico do Runtime

**Status:** substituído parcialmente pelo ADR-0008
**Data:** 2026-08-10

## Contexto

A classificação histórica reunia wiring, bootstrap e handlers de entrada sob Runtime/Composition. Mesmo com dependências permitidas, isso não poderia legitimar persistência ou renderização de SQL fora de Infrastructure.

## Decisão

Manter o monólito modular e perfilar Composition por responsabilidade. Cinco arquivos enumerados são composition roots concretos; os demais arquivos Runtime são bootstrap ou adapters de entrada. Runtime não contém SQL de negócio, PDO direto ou adapters concretos fora dos roots. Domain/Core fornecem regras e invariantes, enquanto Infrastructure traduz essas decisões para SQL e implementa os ports de Application.

A afirmação original de que não havia transações de caso de uso no Runtime foi invalidada pela correção do analisador descrita no ADR-0008. O restante da decisão permanece vigente.

O contrato final combina análise tokenizada da fronteira Runtime, matriz integral de casos críticos de Application, resolução de símbolos, auditoria SOLID, classificação de 100% e budgets por tipo de comando em MySQL real.

## Alternativas

- tratar todo Runtime como composition root irrestrito;
- introduzir container de DI ou framework novo;
- separar o sistema em microserviços;
- bloquear performance apenas por milissegundos absolutos.

## Consequências

A fronteira permanece pragmática e compatível com o comportamento existente. Novos casos de uso atravessam Application/ports, adapters concretos nascem em Infrastructure e somente roots explícitos fazem wiring. O custo é manter os catálogos e contratos executáveis sincronizados; a CI falha diante de drift.
