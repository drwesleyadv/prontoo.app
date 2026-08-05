# Runbook do Maestro

## Agendamento

A tarefa da Hostoo deve executar a cada dez minutos com o PHP 8.4 CLI confirmado:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/xzmpked0/public_html/cron/maestro.php >> /home/xzmpked0/public_html/ssd/logs/maestro-cron.log 2>&1
```

A seleção de PHP do servidor web não substitui o caminho explícito do executável CLI.

## Evidências de execução

Verifique, nesta ordem:

1. `ssd/logs/maestro-cron.log` para a saída JSON do ciclo;
2. `ssd/maestro/state/latest.json` para os estados separados;
3. `pi_maestro_job_runs` para o histórico do estágio de regras;
4. `ssd/logs/maestro-bootstrap.log` quando não houver JSON do runtime;
5. `ssd/maestro-deferred/state/deferred-work.json` para a fila assinada.

## Interpretação

`healthy` indica conclusão sem pendência relevante. `attention` indica que o ciclo concluiu, mas existe backlog antigo, fila morta histórica, execução terminal ou saturação da janela de candidatos. `failed` indica erro ocorrido no ciclo atual.

`overlap` significa que outra execução mantém o lock. `unavailable` significa que o arquivo de lock não pôde ser aberto; verifique permissões de `ssd` e o caminho configurado.

## Auditoria diferida

`waiting_retry` representa itens aguardando o próximo instante do backoff. `dead_letter_new` representa itens enviados à fila morta no ciclo atual. `dead_letter` é o inventário histórico e não deve ser apagado sem análise.

Eventos com `skipped_policy` foram consumidos porque a política atual não permite sua persistência, como visualizações de página quando essa auditoria está desativada.

## Regras

`retrying` representa ações retomadas após falha. `terminal` representa ações que atingiram cinco falhas e exigem intervenção. `candidate_window_saturated` indica que a regra encontrou o limite de 300 candidatos e deve ser revisada para confirmar progressão.

Uma regra com cargo sem colaborador ativo ou usuário inválido falha de modo explícito. Corrija o destinatário; não amplie manualmente para toda a clínica como solução automática. Janelas locais são convertidas para UTC; a sessão MySQL não muda de fuso.

## Incidente

Em falha repetida:

1. preserve os arquivos da fila e os logs;
2. confirme o PHP CLI e o código de saída;
3. identifique o estágio em `latest.json`;
4. examine a mensagem de `pi_maestro_executions` quando a falha for de ação;
5. corrija configuração, destinatário ou dependência de banco;
6. aguarde o backoff ou reprocesse sob supervisão;
7. não remova ledger, auditoria ou fila morta sem registrar a decisão.

## Retenção

Relatórios históricos de preflight podem ser removidos automaticamente após 30 dias. Auditoria, ledger e fila morta são autoritativos e não são purgados pelo ciclo. A rotação de `maestro-cron.log` deve ser configurada na hospedagem quando o arquivo crescer além da política operacional definida.
