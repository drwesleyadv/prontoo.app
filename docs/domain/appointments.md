# Domínio de agenda

Agenda combina leitura intensiva, mudança de estado e regras temporais. O fluxo do paciente evolui de agendado para estados como finalizado ou cancelado, e operações de mover/reagendar precisam manter consistência de data, hora, paciente e consultório.

## Arquitetura

Runtime interpreta ações da página; Application/serviços operacionais coordenam comandos; Infrastructure executa consultas e escritas. Transações de agenda foram retiradas do Runtime durante o fechamento corretivo.

## Tempo

Datas armazenadas e exibidas seguem políticas explícitas de UTC e timezone do consultório. Normalização temporal é infraestrutura/política compartilhada, não regra improvisada por página.

## Performance

Agenda participa de cenários reais de query budget. Alterações que aumentem consultas devem ser justificadas e medidas, especialmente em visões diárias com múltiplos pacientes.

## Segurança

Toda operação é tenant-scoped e sujeita à autorização da ação correspondente.
