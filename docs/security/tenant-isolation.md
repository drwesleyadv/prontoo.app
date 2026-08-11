# Isolamento entre consultórios

O mesmo runtime atende dados de múltiplos consultórios, mas cada request autenticado opera em um tenant efetivo. O isolamento é tratado como invariante transversal.

## Leitura

Consultas sensíveis precisam incorporar o `clinic_id` resolvido do contexto, e não confiar apenas em IDs globais de paciente, tarefa ou agenda.

## Escrita

Commands validam que as entidades manipuladas pertencem ao mesmo tenant. Uma transação correta no tenant errado continua sendo uma falha de segurança.

## Defesa em profundidade

Guards de autorização, SQL scope, constraints e testes de runtime crítico se complementam. Nenhum componente individual é considerado suficiente.

## Trabalho diferido

Spools carregam contexto necessário e o Maestro restaura o tenant antes de executar. Evento sem contexto confiável não deve ser reaproveitado em escopo global.
