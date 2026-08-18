# Telemetria de performance

A telemetria operacional usa três fontes canônicas persistentes: `ssd/telemetry/views.json` para carregamentos de página, `ssd/telemetry/speed.json` para duração e operações preparadas no banco, e `ssd/telemetry/database.json` para a série histórica de variação do estoque de linhas. `speed.json` reúne a duração do page load e a quantidade e a duração acumulada das consultas preparadas executadas pelo PDO durante esse page load.

## Janelas móveis

Os cards de Métricas do Desenvolvedor usam duas janelas móveis contíguas de 10 dias. Páginas conta os carregamentos HTML concluídos; Registros soma os deltas de `ssd/telemetry/database.json`, em que cada captura do Maestro mede exatamente o total de linhas de todas as tabelas-base e registra `total atual - total imediatamente anterior`; Landing conta somente os carregamentos cuja rota canônica é `landing`. O primeiro total é saldo inicial e não entra em Registros. Deltas negativos são preservados. O percentual representa o período recente em relação aos 10 dias imediatamente anteriores; quando não há cobertura completa ou o período anterior é zero e o atual é positivo, a variação permanece indefinida.

Logo abaixo desses cards, a mesma tela preserva os gráficos operacionais `Velocidade` e `Volume`, com atualização automática a cada minuto. A tabela detalhada continua exclusiva da tela Rotas.

A tela Rotas usa a janela móvel recente de 240 horas. Ela agrega quantidade e duração por rota, apresenta nomes funcionais e ordena por maior quantidade de requisições e, em caso de empate, por menor tempo médio.

`Velocidade` usa os 1.440 minutos completos imediatamente anteriores ao minuto corrente. Cada bucket contém a média daquele minuto: Carregamento de Páginas usa a duração média dos page loads observados e Consulta usa a média ponderada das consultas preparadas observadas.

No preenchimento de Velocidade, Carregamento de Páginas usa verde `#05391f` e Consulta usa verde claro `#ddf6e8`. As duas cores são sólidas, com opacidade integral. Esse contrato visual é exclusivo de Velocidade e não altera a paleta de Volume.

`Volume` usa 20 intervalos consecutivos de 24 horas imediatamente anteriores ao timestamp da leitura, alinhados à retenção canônica de `database.json`. Carregamento de Páginas conta os page loads do mesmo intervalo; Consulta usa a variação líquida de registros persistida em `ssd/telemetry/database.json`, exatamente a mesma fonte do card Registros. Carregamento de Páginas usa `#1f6f56` com opacidade `0.5` e Consulta usa `#347963` com opacidade `0.5`.

Velocidade mantém um ponto para cada minuto e Volume mantém um ponto para cada um dos 20 intervalos de 24 horas. Carregamento de Páginas recebe zero quando não há eventos; Consulta preserva a observabilidade própria de `database.json`, sem inventar amostras ausentes. Os pontos consecutivos são ligados por segmentos retos, formando picos e retornos à base sem interpolação curva. A área primária verde escuro é pintada primeiro ao fundo e a secundária verde claro depois à frente; nenhuma das séries exibe contorno ou ponto terminal, permanecendo visíveis somente as áreas inferiores preenchidas. Somente o eixo de Velocidade mostra uma marca centralizada a cada hora completa, totalizando 24 marcas sem sobreposição.

## Áreas decorativas do rodapé

Todas as áreas HTML renderizam no rodapé duas ondas preenchidas em um timeframe comum de 20 intervalos consecutivos de 24 horas. Carregamento de Páginas usa os 20 pontos finais de `page_load`; Consulta usa a série diária de variação líquida de registros de `ssd/telemetry/database.json`, e não a contagem de queries SQL. Nenhuma contagem absoluta ou array de telemetria é serializado no HTML público.

A apresentação preserva a identidade visual usada antes da supressão do Status público. Os valores são normalizados em conjunto pelo maior valor das duas séries em um SVG `1000×250`, com margem superior de `10` e inferior de `14`. Cada amostra é ligada à seguinte por uma Bézier cúbica cujos dois controles ficam no ponto médio horizontal, respectivamente nas alturas da amostra anterior e da atual. Isso produz a curva contínua característica, com tangentes horizontais nas amostras, sem substituir os dados por uma spline independente.

Cada caminho fecha até o fundo do SVG (`y=250`). A superfície fixa ocupa `15vh`, limitada a `64–180px`, usa `opacity: 0.5` em cada área, não possui stroke e recebe a máscara vertical histórica `transparent → #000`, integral aos 34% da altura.

As duas áreas usam exatamente a mesma cor. Em superfícies públicas, ambas usam `#347963`; em áreas autenticadas, ambas usam a cor principal do consultório ou do ambiente Desenvolvedor. Como cada área tem 50% de opacidade, a região compartilhada fica visualmente mais densa e as mudanças de qual série ocupa a posição superior ficam evidentes nos cruzamentos, sem depender de cores diferentes.

A fonte de dados e os controles de segurança permanecem atuais. `login_telemetry_wave` continua sendo apenas um tombstone público sem dados; `footer_telemetry_wave` permanece uma rota JSON privada e excluída da própria contagem de page loads; o renderer canônico do rodapé é de servidor e o JavaScript não busca nem recalcula sua geometria. A página pública Status não é restaurada.

## Tempo médio de consulta ao banco

A medição ocorre no `PDOStatement::execute()` da conexão canônica e não inclui guards de autorização, integridade ou formatação executados fora do driver. Cada page load persiste somente `database_query_count` e `database_query_duration_ns`. O valor de um bucket é ponderado pela quantidade real de consultas: soma de todas as durações SQL dividida pelo total de consultas daquele bucket. SQL, parâmetros, resultados e dados clínicos não integram a telemetria.

Eventos sem consulta observada não inventam duração nem quantidade. Na série visual completa, o bucket sem consulta recebe zero para preservar a escala temporal do respectivo gráfico; isso não representa uma duração estimada.

## Higienização

`views.json` e `speed.json` mantêm a retenção operacional vigente para as séries de page load e velocidade. `database.json` mantém 20 dias móveis de deltas, suficientes para os dois períodos comparativos de 10 dias, e é higienizado durante a captura do Maestro.

## Interpretação

Carregamentos contam `page_load` elegível e deduplicado. Velocidade de Carregamento de Páginas usa somente eventos com amostra em `speed.json`; Velocidade de Consulta usa consultas preparadas observadas. Volume de Carregamento de Páginas conta todos os `page_load` canônicos da janela de 20 dias. Volume de Consulta usa a série de estoque persistida em `database.json`, que representa `total atual - total imediatamente anterior` de todas as tabelas-base, amostrado pelo Maestro. Essa é exatamente a mesma fonte canônica do card Registros e da área Consulta do rodapé; `database_query_count` permanece restrito à telemetria de velocidade e diagnóstico de consultas, não ao Volume de Consulta. O saldo inicial é metadado de bootstrap e não entra nos totais móveis; deltas negativos permanecem válidos.