# Runbook do Maestro

## Sinais de saúde

- último ciclo concluído;
- duração;
- orçamento residual;
- itens pendentes;
- tentativas;
- fila morta;
- spool emergencial;
- falhas de assinatura;
- atraso de materialização.

## Diagnóstico

1. confirmar execução do cron;
2. verificar lock e processo concorrente;
3. inspecionar estado das filas sem alterar envelopes;
4. identificar classe de falha;
5. confirmar disponibilidade do banco e `ssd`;
6. verificar orçamento e item que consome tempo;
7. executar recuperação controlada.

## Reprocessamento

Somente reprocesse item cuja idempotência esteja comprovada. Nunca edite manualmente autoria, tenant, instante ou assinatura.

## Fila morta

Preserve o envelope, registre motivo, corrija a causa e mova para reprocessamento por procedimento auditável. Não descarte silenciosamente.
