# Domínio de agenda

Agenda combina leitura intensiva, mudança de estado e regras temporais. O fluxo do paciente evolui de agendado para estados como finalizado ou cancelado, e operações de mover/reagendar precisam manter consistência de data, hora, paciente e consultório.

## Arquitetura

Runtime interpreta ações da página; Application/serviços operacionais coordenam comandos; Infrastructure executa consultas e escritas. Transações de agenda foram retiradas do Runtime durante o fechamento corretivo.

## Tempo

Datas armazenadas e exibidas seguem políticas explícitas de UTC e timezone do consultório. Normalização temporal é infraestrutura/política compartilhada, não regra improvisada por página.

## Performance

Agenda participa de cenários reais de query budget. Alterações que aumentem consultas devem ser justificadas e medidas, especialmente em visões diárias com múltiplos pacientes.

## Bloqueio diário

Na visualização diária, quando o profissional selecionado não possui consultas na data, um controle por ícone aparece imediatamente após o nome do profissional no seletor: cadeado fechado bloqueia o dia e cadeado aberto desbloqueia. O bloqueio cobre o expediente configurado do profissional, usa o mesmo fallback temporal da Agenda quando não há expediente cadastrado e é serializado com a criação de consultas para impedir corrida entre bloqueio e agendamento. Os limites do dia e do expediente são comparados no formato temporal canônico armazenado pela Agenda, inclusive quando chegam como timestamps UTC. Bloqueios parciais continuam independentes e não são apagados pela alternância do dia.

## Segurança

Toda operação é tenant-scoped e sujeita à autorização da ação correspondente. `block_day` reutiliza a capacidade de adicionar bloqueios e `unblock_day` reutiliza a capacidade de removê-los.
