# Telemetria de performance

A telemetria operacional usa três fontes canônicas persistentes: `ssd/telemetry/views.json` para carregamentos de página, `ssd/telemetry/speed.json` para duração e operações preparadas no banco, e `ssd/telemetry/database.json` para a série histórica de variação do estoque de linhas. `speed.json` reúne a duração do page load e a quantidade e a duração acumulada das consultas preparadas executadas pelo PDO durante esse page load.

## Janelas móveis

Os cards de Métricas do Desenvolvedor usam duas janelas móveis contíguas de 10 dias. Páginas conta os carregamentos HTML concluídos, Registros soma `database_query_count` e Landing conta somente os carregamentos cuja rota canônica é `landing`. O percentual representa o período recente em relação aos 10 dias imediatamente anteriores; quando o período anterior é zero e o atual é positivo, a variação permanece indefinida.

Logo abaixo desses cards, a mesma tela preserva os gráficos operacionais `Velocidade` e `Volume`, com atualização automática a cada minuto. A tabela detalhada continua exclusiva da tela Rotas.

A tela Rotas usa a janela móvel recente de 240 horas. Ela agrega quantidade e duração por rota, apresenta nomes funcionais e ordena por maior quantidade de requisições e, em caso de empate, por menor tempo médio.

`Velocidade` usa os 1.440 minutos completos imediatamente anteriores ao minuto corrente. Cada bucket contém a média daquele minuto: Carregamento de Páginas usa a duração média dos page loads observados e Consulta usa a média ponderada das consultas preparadas observadas.

No preenchimento de Velocidade, Carregamento de Páginas usa verde `#05391f` e Consulta usa verde claro `#ddf6e8`. As duas cores são sólidas, com opacidade integral. Esse contrato visual é exclusivo de Velocidade e não altera a paleta de Volume.

`Volume` usa 30 intervalos consecutivos de 24 horas imediatamente anteriores ao timestamp da leitura. Cada um dos 30 pontos contém as quantidades totais de seu intervalo: Carregamento de Páginas usa `#1f6f56` com opacidade `0.5` e Consulta usa `#347963` com opacidade `0.5`.

Velocidade mantém um ponto para cada minuto e Volume mantém um ponto para cada intervalo de 24 horas, inclusive quando não há eventos. Nesse caso, as duas séries recebem valor zero. Os pontos consecutivos são ligados por segmentos retos, formando picos e retornos à base sem interpolação curva. A área primária verde escuro é pintada primeiro ao fundo e a secundária verde claro depois à frente; nenhuma das séries exibe contorno ou ponto terminal, permanecendo visíveis somente as áreas inferiores preenchidas. Somente o eixo de Velocidade mostra uma marca centralizada a cada hora completa, totalizando 24 marcas sem sobreposição.

## Linhas decorativas do rodapé

Todas as áreas HTML renderizam no rodapé duas linhas decorativas derivadas das mesmas séries canônicas de 30 pontos de Volume: `page_load` para Carregamento de Páginas e `database_queries` para Consulta. O layout obtém essas séries no servidor e converte os valores diretamente em coordenadas SVG normalizadas pelo maior valor conjunto; nenhuma contagem absoluta ou array de telemetria é serializado no HTML público.

A geometria conserva encostas retas entre os pontos e arredonda somente a aproximação e a saída de cada vértice com curvas quadráticas. O resultado mantém picos e vales reconhecíveis, mas elimina quinas rígidas para produzir a aparência de uma cadeia de montanhas.

Nas áreas públicas, as linhas usam exatamente a paleta e o tom de Volume: `#1f6f56` para Carregamento de Páginas e `#347963` para Consulta, ambas com opacidade `0.5`. Nas áreas autenticadas, as duas linhas usam respectivamente a cor forte e a cor principal de destaque do consultório; no escopo global, usam as cores de destaque do ambiente Desenvolvedor.

`login_telemetry_wave` continua sendo apenas um tombstone público sem dados. `footer_telemetry_wave` permanece uma rota JSON privada e serve somente como fallback autenticado legado; o wrapper renderizado no servidor é marcado como pronto para impedir uma segunda consulta e não expõe URL pública de refresh. As rotas de telemetria decorativa continuam excluídas da própria contagem de page loads para evitar retroalimentação.

## Tempo médio de consulta ao banco

A medição ocorre no `PDOStatement::execute()` da conexão canônica e não inclui guards de autorização, integridade ou formatação executados fora do driver. Cada page load persiste somente `database_query_count` e `database_query_duration_ns`. O valor de um bucket é ponderado pela quantidade real de consultas: soma de todas as durações SQL dividida pelo total de consultas daquele bucket. SQL, parâmetros, resultados e dados clínicos não integram a telemetria.

Eventos sem consulta observada não inventam duração nem quantidade. Na série visual completa, o bucket sem consulta recebe zero para preservar a escala temporal do respectivo gráfico; isso não representa uma duração estimada.

## Higienização

As três fontes mantêm retenção móvel de 31 dias. `views.json` e `speed.json` são higienizados no fechamento de page loads; `database.json` é higienizado durante a captura do Maestro. Dados mais antigos que 31 dias não participam das fontes canônicas.

## Interpretação

Carregamentos contam `page_load` elegível e deduplicado. Velocidade de Carregamento de Páginas usa somente eventos com amostra em `speed.json`; Velocidade de Consulta usa consultas preparadas observadas. Volume de Carregamento de Páginas conta todos os `page_load` canônicos da janela. Volume de Consulta soma `database_query_count` apenas onde a instrumentação está presente. A série de estoque persistida em `database.json` continua representando `total atual - total imediatamente anterior` de todas as tabelas-base, amostrado pelo Maestro; deltas negativos permanecem válidos e não são confundidos com o card Registros da tela Métricas.