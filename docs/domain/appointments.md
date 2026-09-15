# Domínio de agenda

Agenda combina leitura intensiva, mudança de estado e regras temporais. O fluxo do paciente evolui de agendado para estados como finalizado ou cancelado, e operações de mover/reagendar precisam manter consistência de data, hora, paciente e consultório.

## Arquitetura

Runtime interpreta ações da página; Application/serviços operacionais coordenam comandos; Infrastructure executa consultas e escritas. Transações de agenda foram retiradas do Runtime durante o fechamento corretivo.

## Tempo

Datas armazenadas e exibidas seguem políticas explícitas de UTC e timezone do consultório. Normalização temporal é infraestrutura/política compartilhada, não regra improvisada por página.

## Performance

Agenda participa de cenários reais de query budget. Alterações que aumentem consultas devem ser justificadas e medidas, especialmente em visões diárias com múltiplos pacientes.

## Bloqueio diário

Na visualização diária, o seletor de data e profissional permanece em uma única linha. O controle de bloqueio integral aparece imediatamente após o profissional: cadeado fechado bloqueia o dia e cadeado aberto desbloqueia. Quando já existem consultas, o cadeado fechado permanece visível, desabilitado e em baixa opacidade para sinalizar o impedimento do bloqueio integral. Quando o dia está integralmente bloqueado, a grade recebe uma atenuação discreta e uma marca d’água de cadeado, sem desenhar o bloqueio como evento vertical; a criação rápida por horários também fica indisponível. Bloqueios parciais continuam independentes, assim como as permissões, a serialização transacional e as regras de clínica.

## Segurança

Toda operação é tenant-scoped e sujeita à autorização da ação correspondente. `block_day` reutiliza a capacidade de adicionar bloqueios e `unblock_day` reutiliza a capacidade de removê-los.
