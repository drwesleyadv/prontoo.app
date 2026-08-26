SOL/USDT – painel refatorado

Publicação: /public_html/sol

Interface pública:
https://prontoo.app/sol/

Cron recomendado:
* * * * * php /home/SEU_USUARIO/public_html/sol/learn.php
* * * * * php /home/SEU_USUARIO/public_html/sol/cron.php

A interface usa candles horários e WebSocket públicos da Binance para o desenho em tempo real. O backend científico trabalha diretamente com candles fechados de 30 minutos, sem armazenar candles de 1 minuto.

Carteira e Integralizado são persistidos exclusivamente em cache server-side sob cache/runtime. O navegador mantém somente o snapshot corrente em memória. Saldos SOL, USDC e Stake são lidos por RPC; posições Perps são obtidas pela API dedicada da Jupiter. Os snapshots externos usam cache curto e fallback stale para evitar zerar o patrimônio por falhas transitórias.

Stake nativo é atribuído à carteira pela autoridade withdrawer da conta de stake. Os jobs cron.php e learn.php compartilham lock de execução para impedir escrita concorrente dos caches científicos.

Arquivos internos, cache, logs, locks, cron e learn são bloqueados por .htaccess.
