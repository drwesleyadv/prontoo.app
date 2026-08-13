# Telemetria de performance

A telemetria operacional usa três fontes canônicas persistentes: `ssd/telemetry/views.json` para Visualizações, `ssd/telemetry/speed.json` para latência e `ssd/telemetry/database.json` para variação de Registros. `speed.json` reúne a duração do page load e, a partir da release 1.8.13.4, a quantidade e a duração acumulada das consultas preparadas executadas pelo PDO durante esse page load.

## Janelas móveis

`Velocidade` usa os 1.440 intervalos de um minuto imediatamente anteriores ao timestamp da leitura. Cada bucket contém a média daquele minuto: Rotas usa a duração média dos page loads observados e Banco de dados usa a média ponderada das consultas preparadas observadas.

`Volume` usa os 30 intervalos móveis de 24 horas imediatamente anteriores ao mesmo timestamp. Cada bucket contém totais: verde escuro para carregamentos de página e verde claro para consultas ao banco. Status e Painel do Desenvolvedor compartilham exatamente estas séries e atualizam a cada 60 segundos enquanto a página está visível.

As duas séries são áreas sem contorno. A área primária verde escuro é pintada primeiro ao fundo e a secundária verde claro depois à frente. Ausência histórica de instrumentação de consultas permanece sem amostra e não é convertida em zero.

## Tempo médio de consulta ao banco

A medição ocorre no `PDOStatement::execute()` da conexão canônica e não inclui guards de autorização, integridade ou formatação executados fora do driver. Cada page load persiste somente `database_query_count` e `database_query_duration_ns`. O valor de um bucket é ponderado pela quantidade real de consultas: soma de todas as durações SQL dividida pelo total de consultas daquele bucket. SQL, parâmetros, resultados e dados clínicos não integram a telemetria.

A coleta de latência e contagem de consultas de banco começa na release 1.8.13.4. Eventos anteriores permanecem sem amostra de banco; ausência histórica não é convertida em `0 ms`, não é tratada como `0 consultas` e não recebe backfill estimado.

## Higienização

As três fontes mantêm retenção móvel de 31 dias. `views.json` e `speed.json` são higienizados no fechamento de page loads; `database.json` é higienizado durante a captura do Maestro. Dados mais antigos que 31 dias não participam das fontes canônicas.

## Interpretação

Visualizações contam `page_load` elegível e deduplicado. Velocidade de Rotas usa somente eventos com amostra em `speed.json`; Velocidade de Banco usa consultas preparadas observadas. Volume de páginas conta todos os `page_load` canônicos da janela. Volume de Banco soma `database_query_count` apenas onde a instrumentação está presente. Registros continuam representando `total atual - total imediatamente anterior` de todas as tabelas-base, amostrado pelo Maestro; deltas negativos permanecem válidos.
