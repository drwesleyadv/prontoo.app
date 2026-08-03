# Telemetria de rotas

## Fonte canônica

A única fonte de telemetria é `ssd/telemetry/telemetria.json`. Apesar da extensão `.json`, o arquivo segue JSON Lines: cada linha é um evento JSON completo e independente, correspondente ao carregamento concluído de uma rota.

Nenhum outro arquivo da pasta participa de cards, gráficos, cálculos ou retenção. A telemetria não passa pelo Maestro nem por tabelas do banco de dados.

## Marcadores e unidade

Os pontos de entrada `index.php`, `install.php` e `br/index.php` capturam o marcador inicial antes de carregar o runtime. Um callback de encerramento captura o marcador final depois da execução da rota, inclusive em respostas antecipadas e erros fatais.

O intervalo canônico usa `hrtime(true)`, relógio monotônico em nanossegundos:

\[
\text{duração\_ns}=\text{fim\_monotônico\_ns}-\text{início\_monotônico\_ns}
\]

\[
\text{duração\_ms}=\frac{\text{duração\_ns}}{1\,000\,000}
\]

O evento preserva o resultado inteiro em nanossegundos e publica milissegundos com seis casas decimais. Os timestamps de parede em UTC têm precisão de microssegundos e servem para posicionar o evento nas janelas; ajustes do relógio do sistema não alteram a duração monotônica.

## Evento

Cada linha contém schema, identificador, rota, marcadores UTC e Unix, marcadores monotônicos, duração, status HTTP, sucesso, erro fatal e versão. Parâmetros de URL, conteúdo clínico, identidade, IP, SQL e textos livres não são registrados.

## Retenção e janelas

Ao concluir uma rota, a aplicação grava o evento com bloqueio exclusivo e remove fisicamente linhas cujo timestamp final seja anterior ao corte exato de 20 dias. A retenção não usa dias civis arredondados.

Os cards comparam duas janelas contíguas e semiabertas, calculadas a partir do instante atual `T`:

- período atual: `[T - 10 dias, T)`;
- período anterior: `[T - 20 dias, T - 10 dias)`.

O limite inferior pertence à janela e o limite superior não pertence. Assim, nenhum evento é contado duas vezes. As médias são calculadas por `soma(duração_ns) / quantidade`, nunca pela média de médias intermediárias.

A variação percentual é:

\[
\text{variação}=\frac{\text{atual}-\text{anterior}}{\text{anterior}}\times100
\]

Quando os dois valores são zero, a variação é `0%`. Quando o período anterior é zero e o atual é positivo, não existe base matemática de comparação; a interface exibe `sem base comparável` em vez de infinito ou um percentual inventado. Uma média sem observações também é exibida sem base comparável.

## Cards e gráficos

O Painel do Desenvolvedor e a página pública `/status` reutilizam o mesmo cálculo e a mesma leitura do arquivo canônico para:

- quantidade de rotas concluídas nos últimos 10 dias e variação contra os 10 dias anteriores;
- duração média de todas as rotas nos últimos 10 dias e variação contra o período anterior;
- duração média da rota `landing` nos últimos 10 dias e variação contra o período anterior.

O gráfico **Velocidade** usa médias ponderadas por amostra da duração das rotas e da Landing Page, obtidas do mesmo arquivo. O gráfico **Leitura e gravação** usa esse arquivo para a série de requisições e o ledger transacional para a série de registros efetivamente alterados. As duas séries diárias usam o fuso `America/Cuiaba` e incluem dias sem eventos com valor zero.

## Limpeza de legado

Após implantar esta versão, o desenvolvedor pode acessar manualmente `ssd/telemetry` no servidor e apagar todos os arquivos `.json` e `.ndjson` legados, preservando apenas `telemetria.json`. A aplicação ignora qualquer arquivo remanescente, portanto `telemetria.json` continua sendo a única fonte da verdade.

Se `telemetria.json` também for removido durante uma zeragem deliberada, ele será recriado automaticamente na conclusão da próxima rota; o histórico removido não pode ser reconstruído pela aplicação.
