# Runbook do Maestro

Maestro deve ser observado como supervisor de trabalho, não apenas como “cron executou”. Um processo pode terminar com código zero e ainda acumular fila ou dead-letter.

## Execução do cron

Na hospedagem, o Maestro deve ser executado explicitamente com o binário CLI da família suportada: `/opt/cpanel/ea-php84/root/usr/bin/php`. Não use `php` sem caminho absoluto no cron, porque o PHP padrão do shell pode divergir da família 8.4 exigida pelo runtime. O comando deve apontar esse binário para `cron/maestro.php` no diretório publicado da aplicação.

## Verificações normais

Confirme que o ciclo recente atualizou seu estado, que a fila não cresce continuamente e que itens em retry estão respeitando backoff. Verifique dead-letter separadamente.

## Quando houver erro

Identifique o evento, consultório e tentativa. Distinga erro de regra, indisponibilidade de banco/storage e envelope inválido. Não reenvie manualmente um item sem entender por que ele falhou, pois isso pode duplicar efeito.

## Auditoria diferida

Os diretórios canônicos são derivados por Infrastructure e incluem spool principal e caminho emergencial. Assinatura/contexto precisam ser válidos; arquivo inválido deve ser isolado, não aceito permissivamente.

## Recuperação

Depois de corrigir a causa, deixe a política de retry retomar itens elegíveis quando possível. Para dead-letter, faça reprocessamento explícito e documentado.

## CI

`tools/maestro-runtime-regression-check` prova o ciclo contra banco real e deve permanecer verde após mudanças operacionais. `tools/php84-runtime-contract-check` também verifica que este runbook mantém explícito o binário CLI de PHP 8.4.
