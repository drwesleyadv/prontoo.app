# Orçamentos de desempenho

## Objetivo

A Fase 1 da evolução de eficiência institui limites versionados para custo de requisição sem alterar o comportamento operacional. Os limites são guardrails de engenharia e devem ser apertados apenas com base em observações reais de produção.

## Métricas

Cada rota possui limites para tempo total, tempo de banco, número de consultas, consultas amplas, módulos carregados e bytes de módulos. A ausência de configuração específica utiliza os limites padrão.

Além da telemetria de rota, `app/query.budgets.json` fixa budgets determinísticos de MySQL real por read model. Os contadores `Com_select`, `Com_insert`, `Com_update`, `Com_delete` e `Com_replace` são medidos na mesma sessão após instalação limpa; isso protege o número de comandos sem depender da velocidade absoluta do runner.

## Regras de conformidade

- a redução de orçamento não pode enfraquecer autorização, isolamento, auditoria ou integridade transacional;
- o contrato não recebe dados clínicos, CPF, conteúdo livre ou parâmetros SQL;
- alterações no contrato exigem revisão, caracterização e registro de versão;
- uma regressão deve ser corrigida na origem, não ocultada com aumento automático dos limites;
- limites de produção devem usar percentis e volume mínimo antes de bloquear publicação.

## Operação

`app/performance.budgets.json` é a fonte versionada para rotas. `Prontoo\Core\Performance\PerformanceBudget` resolve e avalia esses limites. Para consultas reais, `app/query.budgets.json`, `tools/query-budget-contract-check` e `tools/mysql-query-budget-check` formam o contrato versionado, executado no CI com MySQL 8.
