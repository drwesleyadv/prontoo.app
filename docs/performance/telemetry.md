# Telemetria de performance

A telemetria operacional usa três fontes canônicas persistentes: `ssd/telemetry/views.json` para Visualizações, `ssd/telemetry/speed.json` para duração/tempo médio e `ssd/telemetry/database.json` para variação de Registros.

## Janela móvel

`Últimos 30 dias` significa os 30 intervalos de 24 horas imediatamente anteriores ao timestamp da leitura. A janela não é ancorada em meia-noite. Ela termina no instante atual, divide-se em 15 dias recentes e 15 imediatamente anteriores e avança continuamente conforme o relógio avança.

Cards de Visualizações e Tempo médio leem os eventos persistidos até o timestamp corrente. O card de Landing Page conta apenas os `page_load` elegíveis cuja rota canônica é `landing`, exibindo o total dos 15 dias móveis recentes e sua variação contra os 15 dias imediatamente anteriores. O card de Registros lê os deltas amostrados pelo Maestro, portanto sua atualização acompanha o ciclo do Maestro.

No Painel do Desenvolvedor e em `/status`, a área de telemetria é recarregada a cada 60 segundos enquanto a página está visível, de modo que cards e gráficos reflitam a fonte persistida sem esperar a antiga janela de 15 minutos.

## Higienização

As três fontes mantêm retenção móvel de 31 dias. `views.json` e `speed.json` são higienizados no fechamento de page loads; `database.json` é higienizado durante a captura do Maestro. Dados mais antigos que 31 dias não participam das fontes canônicas.

## Interpretação

Visualizações contam `page_load` elegível e deduplicado. Tempo médio usa somente eventos que possuem amostra correspondente em `speed.json`. Registros representam `total atual - total imediatamente anterior` de todas as tabelas-base, amostrado pelo Maestro; deltas negativos permanecem válidos.
