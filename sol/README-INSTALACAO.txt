SOL/USDT – painel refatorado

Publicação: /public_html/sol

Interface pública:
https://prontoo.app/sol/

Cron recomendado:
* * * * * php /home/SEU_USUARIO/public_html/sol/learn.php
* * * * * php /home/SEU_USUARIO/public_html/sol/cron.php

A interface usa candles horários e WebSocket públicos da Binance para o desenho em tempo real. O backend científico trabalha diretamente com candles fechados de 30 minutos, sem armazenar candles de 1 minuto.

A carteira pessoal fica no localStorage do navegador. Quando um endereço Solana é informado, o servidor consulta saldos por RPC e devolve somente o resultado da consulta; o endereço não é persistido no servidor.

Arquivos internos, cache, logs, cron e learn são bloqueados por .htaccess.
