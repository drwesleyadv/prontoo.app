# ADR 0002 — Isolamento por consultório

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Uma única aplicação atende múltiplos consultórios. Um filtro esquecido poderia expor ou alterar dados de outro tenant.

## Decisão

Tratar `clinic_id` e o vínculo do usuário como parte da fronteira de segurança. Leitura, escrita, autorização e auditoria devem preservar o contexto de consultório; mecanismos de SQL scope e invariantes complementam validações de caso de uso.

## Consequências

Consultório não é parâmetro opcional de consulta. Testes de isolamento fazem parte dos smokes críticos e qualquer operação sem contexto suficiente deve falhar em vez de assumir escopo permissivo.
