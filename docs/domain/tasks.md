# Tarefas

## Escopos

- minhas tarefas;
- tarefas do meu cargo;
- tarefas da clínica;
- tarefas atrasadas.

## Invariantes

- tarefa pertence a consultório;
- responsável e cargo precisam estar ativos quando exigido;
- alterações de status seguem o contrato do banco;
- filtros não ampliam autorização;
- atrasos são calculados no fuso do consultório;
- ações agendadas pelo Maestro respeitam o mesmo isolamento das ações HTTP.

## Busca

Quando uma tarefa se relaciona a pessoa, a busca padrão deve privilegiar Pacientes e não expor registros de outro consultório.
