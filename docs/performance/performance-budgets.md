# Budgets de performance

O Prontoo prefere budgets determinísticos de trabalho a limites frágeis de milissegundos na CI. O principal exemplo é o budget MySQL, que conta operações por cenário real.

## Query budgets

A suíte atual possui 11 cenários cobrindo leituras e fluxos críticos. Cada cenário tem teto explícito de SELECT e, para read models, escrita precisa permanecer zero. Isso detecta N+1 e regressões de acesso a dados sem depender da velocidade variável do runner.

## Runtime

Hotspots de tamanho e acoplamento também funcionam como budgets de modificabilidade. Eles não medem velocidade, mas protegem custo futuro de mudança.

## Telemetria

Tempo real em produção é observado pela telemetria de page load e métricas operacionais. Budgets de CI e observabilidade de produção respondem perguntas diferentes e devem ser usados em conjunto.
