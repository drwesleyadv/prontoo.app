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

## Áreas decorativas do rodapé

Todas as áreas HTML renderizam no rodapé duas ondas preenchidas derivadas das mesmas séries canônicas de 30 pontos de Volume: `page_load` para Carregamento de Páginas e `database_queries` para Consulta. Nenhuma contagem absoluta ou array de telemetria é serializado no HTML público.

A apresentação restaura a identidade visual usada antes da supressão do Status público. Os valores são normalizados em conjunto pelo maior valor das duas séries em um SVG `1000×250`, com margem superior de `10` e inferior de `14`. Cada amostra é ligada à seguinte por uma Bézier cúbica cujos dois controles ficam no ponto médio horizontal, respectivamente nas alturas da amostra anterior e da atual. Isso produz a curva contínua característica, com tangentes horizontais nas amostras, sem substituir os dados por uma spline independente.

Cada caminho fecha até o fundo do SVG (`y=250`). A superfície fixa ocupa `15vh`, limitada a `64–180px`, usa `opacity: 0.5`, não possui stroke e recebe a máscara vertical histórica `transparent → #000`, integral aos 34% da altura.

A ordem cromática também segue o renderer histórico: Carregamento de Páginas ocupa o papel visual antes usado por requisições e recebe o tom principal — `#347963` nas superfícies públicas; Consulta ocupa o papel antes usado por registros e recebe o tom forte — `#1f6f56` nas superfícies públicas. Nas áreas autenticadas, a mesma relação usa respectivamente a cor principal e a cor forte do consultório ou do ambiente Desenvolvedor.

A fonte de dados e os controles de segurança permanecem atuais. `login_telemetry_wave` continua sendo apenas um tombstone público sem dados; `footer_telemetry_wave` permanece uma rota JSON privada e excluída da própria contagem de page loads; o renderer canônico do rodapé é de servidor e o JavaScript não busca nem recalcula sua geometria. A página pública Status não é restaurada.

## Tempo médio de consulta ao banco

A medição ocorre no `PDOStatement::execute()` da conexão canônica e não inclui guards de autorização, integridade ou formatação executados fora do driver. Cada page load persiste somente `database_query_count` e `database_query_duration_ns`. O valor de um bucket é ponderado pela quantidade real de consultas: soma de todas as durações SQL dividida pelo total de consultas daquele bucket. SQL, parâmetros, resultados e dados clínicos não integram a telemetria.

Eventos sem consulta observada não inventam duração nem quantidade. Na série visual completa, o bucket sem consulta recebe zero para preservar a escala temporal do respectivo gráfico; isso não representa uma duração estimada.

## Higienização

As três fontes mantêm retenção móvel de 31 dias. `views.json` e `speed.json` são higienizados no fechamento de page loads; `database.json` é higienizado durante a captura do Maestro. Dados mais antigos que 31 dias não participam das fontes canônicas.

## Interpretação

Carregamentos contam `page_load` elegível e deduplicado. Velocidade de Carregamento de Páginas usa somente eventos com amostra em `speed.json`; Velocidade de Consulta usa consultas preparadas observadas. Volume de Carregamento de Páginas conta todos os `page_load` canônicos da janela. Volume de Consulta soma `database_query_count` apenas onde a instrumentação está presente. A série de estoque persistida em `database.json` continua representando `total atual - total imediatamente anterior` de todas as tabelas-base, amostrado pelo Maestro; deltas negativos permanecem válidos e não são confundidos com o card Registros da tela Métricas.