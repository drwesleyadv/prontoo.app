# Manutenção arquitetural pós-consolidação

## Baseline

A release `1.8.11.1` é a baseline arquitetural consolidada após as fases de Engenharia 16–21 e o ciclo corretivo 1–4.

A partir dessa baseline, a arquitetura deixa de evoluir por ciclos autônomos de refatoração. Novo ciclo arquitetural amplo só deve ser aberto quando houver evidência material de pelo menos uma destas condições:

- regressão de uma invariante arquitetural executável;
- risco funcional, de segurança, integridade ou isolamento que não possa ser resolvido de forma focal;
- crescimento de dívida estrutural além dos ratchets existentes;
- mudança de produto que exija uma nova fronteira arquitetural real.

Ausentes essas condições, o trabalho arquitetural ocorre por manutenção incremental junto das mudanças funcionais ou corretivas.

## Refactoring on touch

Input adapters Runtime acima de 500 linhas são dívida explícita, não violação de camada. Quando um desses hotspots for alterado em um pull request, a própria alteração deve reduzir pelo menos uma das métricas locais protegidas:

- `generic_data_gateway_calls`; ou
- `hotspot_bucket`.

O contrato executável é `tools/runtime-refactor-on-touch-check`, integrado ao quality gate em pull requests. O contrato não exige refatoração de arquivos que não foram tocados e não autoriza aumento de qualquer ratchet já protegido por `tools/runtime-input-boundary-check`.

## Segurança focal

Falhas de segurança ou integridade não abrem automaticamente um novo ciclo arquitetural. Devem ser corrigidas no menor escopo seguro, preservando as fronteiras consolidadas. O hardening de logout global segue essa regra: revogação de sessões é corrigida como política de segurança e coberta por smoke HTTP real, sem reabrir a decomposição geral do Runtime.

## Regra operacional

A arquitetura serve ao produto. Refatoração sem ganho mensurável de segurança, integridade, modificabilidade ou redução de dívida protegida não constitui objetivo autônomo de release.
