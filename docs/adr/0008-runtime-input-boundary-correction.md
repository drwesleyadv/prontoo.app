# ADR-0008 — correção da fronteira de entrada Runtime

**Status:** aceito
**Data:** 2026-08-11

## Contexto

O contrato da Fase 16 reconhecia métodos transacionais tradicionais, mas não `atomic()`/`atomically()`. A classificação de todos os handlers Runtime como Composition também permitia que a auditoria SOLID excluísse superfícies amplas e dependências concretas de suas heurísticas. Assim, SQL e PDO estavam realmente zerados, mas a conclusão de fechamento semântico completo era maior que a evidência executável.

## Decisão

Ampliar o analisador transacional e congelar sua baseline corrigida em 33 ocorrências de negócio. Criar um contrato separado, orientado ao papel `runtime_input_adapter`, para referências diretas a Infrastructure, chamadas ao gateway genérico de dados e buckets de tamanho. Todos os limites são globais e por arquivo, novas categorias começam em zero e a dívida só pode diminuir.

As regras zero de SQL, PDO e adapters concretos permanecem ativas. A regra zero transacional será restaurada somente após a extração dos 33 fluxos para casos de uso de Application. A auditoria SOLID continua útil dentro do seu escopo, mas seus zero hotspots não serão apresentados como prova de que os input adapters são finos.

O segundo ratchet, originado em `7f768f36d270aa09c3fa345bccaea3edfa414f6a`, extraiu os 12 fluxos financeiros e 4 de Agenda. Esses módulos agora têm regra transacional zero; a baseline global caiu para 17 e não pode regressar aos 33 iniciais.

## Alternativas rejeitadas

- manter `atomic()` invisível por ser chamado através de uma porta;
- criar uma allowlist ampla para os handlers existentes;
- contar todo método público de um serviço genérico como caso de uso crítico;
- decompor arquivos apenas para reduzir linhas, sem mover responsabilidade.

## Consequências

Os relatórios deixam de declarar um zero incorreto e passam a impedir crescimento da dívida real. A migração pode ser vertical e incremental, preservando comportamento, tenant, MFA, auditoria e atomicidade. Leituras catalogadas podem permanecer pragmáticas quando uma nova abstração não melhora a separação de responsabilidades.
