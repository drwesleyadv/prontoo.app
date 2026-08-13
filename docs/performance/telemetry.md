# Telemetria de performance

A telemetria operacional usa três fontes canônicas persistentes: `ssd/telemetry/views.json` para Visualizações, `ssd/telemetry/speed.json` para latência e `ssd/telemetry/database.json` para variação de Registros. `speed.json` reúne a duração do page load e, a partir da release 1.8.13.4, a quantidade e a duração acumulada das consultas preparadas executadas pelo PDO durante esse page load.

## Janelas móveis

`Últimos 30 dias` significa os 30 intervalos de 24 horas imediatamente anteriores ao timestamp da leitura. A janela não é ancorada em meia-noite e avança continuamente conforme o relógio avança. `Últimas 24 horas` usa 1.440 buckets de um minuto no mesmo princípio de janela móvel.

No Painel do Desenvolvedor e em `/status`, os dois gráficos de performance exibem a mesma dupla de métricas em milissegundos. `Rotas` mostra a duração média dos page loads observados e é desenhada em verde escuro ao fundo. `Banco de dados` mostra o tempo médio das consultas preparadas e é desenhada em verde mais claro à frente. A área de telemetria é recarregada a cada 60 segundos enquanto a página está visível.

## Tempo médio de consulta ao banco

A medição ocorre no `PDOStatement::execute()` da conexão canônica e não inclui guards de autorização, integridade ou formatação executados fora do driver. Cada page load persiste somente `database_query_count` e `database_query_duration_ns`. O valor de um bucket é ponderado pela quantidade real de consultas: soma de todas as durações SQL dividida pelo total de consultas daquele bucket. SQL, parâmetros, resultados e dados clínicos não integram a telemetria.

A coleta de latência de banco começa na release 1.8.13.4. Eventos anteriores permanecem sem amostra de banco; ausência histórica não é convertida em `0 ms` nem recebe backfill estimado.

## Higienização

As três fontes mantêm retenção móvel de 31 dias. `views.json` e `speed.json` são higienizados no fechamento de page loads; `database.json` é higienizado durante a captura do Maestro. Dados mais antigos que 31 dias não participam das fontes canônicas.

## Interpretação

Visualizações contam `page_load` elegível e deduplicado. Latência de Rotas usa somente eventos com amostra em `speed.json`. Latência de Banco usa somente eventos que registraram ao menos uma consulta preparada. Registros representam `total atual - total imediatamente anterior` de todas as tabelas-base, amostrado pelo Maestro; deltas negativos permanecem válidos.
