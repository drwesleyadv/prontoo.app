# ADR 0005 — Fechamento semântico do Runtime

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Separar arquivos por pasta não bastava enquanto Runtime ainda executava SQL e transações de negócio.

## Decisão

Definir fechamento por comportamento mensurável: zero SQL de negócio, PDO direto e transações de caso de uso no Runtime; zero `OperationGateway`; adapters concretos limitados aos composition roots. Detectores devem reconhecer formas semânticas equivalentes, não apenas nomes específicos de método.

## Consequências

A arquitetura passa a ser verdadeira por contrato. Falsos zeros são tratados como defeitos do detector e corrigidos antes de declarar fechamento.
