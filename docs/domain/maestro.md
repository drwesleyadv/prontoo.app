# Domínio operacional do Maestro

Maestro é o supervisor server-side do Prontoo. Ele executa trabalho que não deve depender de uma página aberta: auditoria diferida, verificações e rotinas operacionais.

## Ciclo

Cada execução faz preflight, seleciona trabalho elegível, restaura contexto seguro, executa com budget e registra estado. Falhas temporárias usam retry/backoff; itens que excedem a política podem seguir para dead-letter.

## Tenant

Trabalho diferido não perde o consultório de origem. Contexto e prova precisam ser restaurados antes da execução. A ausência de escopo válido deve impedir a ação.

## Supervisão

Maestro distingue saúde atual de atenção histórica. Um erro antigo não deve marcar todo ciclo futuro como falho, e um ciclo aparentemente verde não deve apagar evidência de item em dead-letter.

## Operação

A CI possui regressão específica do Maestro contra MySQL real. Em produção, seu estado deve ser observado junto com filas e logs, não apenas pelo sucesso do cron.
