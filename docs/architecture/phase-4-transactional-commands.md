# Fase 4 — comandos transacionais

## Escopo concluído

1. criação de uma porta de comando para abas do paciente;
2. criação de caso de uso independente de HTTP, sessão e persistência;
3. implementação PDO com transação própria somente quando necessário;
4. bloqueio pessimista das leituras que determinam duplicidade e ordenação;
5. resultado idempotente para repetição de uma aba ativa com o mesmo nome;
6. manutenção da ação, mensagens, auditoria e redirecionamentos existentes na fachada;
7. testes de caracterização da transação, idempotência, isolamento e direção das dependências.

## Fronteira transacional

`PatientTabCommandService` valida o comando e aceita somente os resultados `created` e `duplicate`. `PdoPatientTabCommandRepository` usa a conexão canônica, participa de transação já aberta ou cria uma transação própria, bloqueia as linhas do paciente no consultório e confirma ou reverte a operação como uma unidade.

## Compatibilidade

A fachada continua limpando o nome, normalizando o ícone, emitindo as mesmas mensagens, registrando a mesma auditoria e retornando à mesma ficha. Banco, schema, permissões, rotas e aparência não foram alterados.

## Próxima etapa

Aplicar a mesma disciplina em uma área crítica, com autorização explícita, isolamento multitenant, idempotência e prova regressiva antes do merge.
