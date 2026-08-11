# Suíte de regressão

A regressão não é um único comando; é uma malha ordenada de gates.

## Fast gate

`php tools/quality-gate --fast` cobre contratos que devem falhar rapidamente durante desenvolvimento. É a primeira barreira antes de mudanças maiores.

## Architecture Contract

Executa quality gate integral, schema, query budgets MySQL, segurança do instalador, regressão pós-senha, smoke de login/logout global, Maestro, runtime crítico e HTTP front controller.

## Documentation Contract

Valida release determinístico, lint PHP, documentação, política de comentários e regressões de segurança estáticas.

## Interpretação de falha

Não rerun automaticamente até ficar verde sem investigar. Um gate novo pode revelar defeito latente, como ocorreu com a invalidação de segunda sessão durante o hardening de logout. Corrija a causa ou o fixture; nunca enfraqueça o contrato apenas para liberar o merge.
