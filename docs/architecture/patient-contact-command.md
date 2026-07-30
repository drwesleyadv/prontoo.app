# Comando de contato do paciente

## Objetivo

A Fase 5 concentra a atualização do contato fiscal do paciente em um comando transacional isolado por consultório.

## Fronteiras

A apresentação mantém autorização, leitura do formulário, auditoria, mensagem e redirecionamento. O serviço normaliza a entrada e o adaptador PDO bloqueia a linha ativa do paciente antes de atualizar.

## Robustez

A transação é curta e contém somente bloqueio e persistência. Qualquer falha executa rollback. O filtro utiliza simultaneamente paciente, consultório e estado ativo.

## Compatibilidade

Os mesmos campos são gravados, `registration_needs_update` continua sendo zerado e `updated_at` continua sendo renovado. Não há alteração de banco, schema ou interface.
