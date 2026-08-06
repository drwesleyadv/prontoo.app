# Performance e telemetria de páginas

## Fonte canônica

A fonte corrente é `ssd/telemetry/page-loads.jsonl`. Cada linha válida representa uma visualização concluída de página HTML. Requisições auxiliares, como `fetch`, XHR, JSON, lookups, prefetch, assets, downloads e redirecionamentos, não entram nos indicadores.

A telemetria não passa pelo Maestro nem por tabelas operacionais. O ledger transacional participa somente da série de Registros do gráfico **Últimos 30 dias**.

## Unidade e duração

Os pontos de entrada `index.php`, `install.php` e `br/index.php` capturam o início antes do primeiro `require`. O término normal é marcado depois da última etapa útil de renderização; o shutdown é apenas fallback identificado.

A duração usa `hrtime(true)` em nanossegundos. Os indicadores convertem o resultado para milissegundos sem calcular média de médias.

## Retenção e janelas

A retenção técnica é de 31 dias corridos. Esse dia adicional garante que o gráfico de 30 dias civis inclua integralmente o primeiro dia mesmo quando consultado depois da meia-noite.

Os cards comparam duas janelas contíguas de 10 dias:

- período atual: `[T - 10 dias, T)`;
- período anterior: `[T - 20 dias, T - 10 dias)`.

## Comunicação da interface

A página pública `/status` e o Painel do Desenvolvedor reutilizam o mesmo renderer e os mesmos dados:

- **Visualizações**: páginas HTML concluídas nos últimos 10 dias;
- **Velocidade média**: duração média das rotas no período;
- **Visualizações da Landing**: páginas concluídas da rota `landing`;
- **Performance**: título do conjunto compartilhado de gráficos;
- **Últimas 24 horas**: velocidade das rotas e da Landing;
- **Últimos 30 dias**: Visualizações e Registros diários, incluindo hoje.

As labels inferiores das séries diárias usam uma inicial do dia da semana seguida do dia com dois dígitos, no formato `SDD`. O tooltip preserva a data completa `DD/MM/AAAA`.

## Séries diárias

As séries usam o fuso `America/Cuiaba`, começam há 29 dias e terminam hoje. Dias sem eventos permanecem visíveis com valor zero. Visualizações vêm de `page-loads.jsonl`; Registros vêm de mutações confirmadas no `pi_action_ledger`.
