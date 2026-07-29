# Maestro

## Finalidade

O Maestro executa atividades que não devem ampliar o caminho crítico HTTP:

- auditoria adiada;
- telemetria;
- integridade;
- regras agendadas;
- recuperação de filas;
- limpeza supervisionada.

## Contrato operacional

- um ciclo possui prazo residual único;
- envelopes são assinados;
- processamento é idempotente;
- itens interrompidos podem ser retomados;
- falhas repetidas seguem para fila morta;
- materialização preserva autoria e contexto originais;
- o processo cron não assume autoria do evento.

## Persistência

Filas primária e emergencial ficam em armazenamento persistente. Estado de saúde deve distinguir atraso, corrupção, indisponibilidade e fila morta.
