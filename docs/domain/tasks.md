# Domínio de tarefas e avisos

Tarefas organizam trabalho interno e avisos operacionais. A complexidade principal está em estado, destinatário, visibilidade e escopo de consultório, não no formulário de criação.

## Arquitetura

Comandos operacionais usam Application Services; Runtime coordena ações da página; Infrastructure persiste. O input adapter de tarefas é um dos hotspots históricos e, portanto, está protegido por `refactor-on-touch`.

## Estados

Transições devem respeitar o conjunto de estados aceito pelo schema e pelas políticas do domínio. Corrigir uma inconsistência de estado não deve envolver DDL no runtime.

## Evolução

Quando a tela de tarefas for alterada, a mudança deve aproveitar a oportunidade para reduzir dívida mensurada do adapter em vez de acrescentar novos ramos ao mesmo bloco.
