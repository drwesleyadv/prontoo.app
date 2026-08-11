# Telemetria de performance

Telemetria serve para observar comportamento real depois que os contratos de CI já garantiram limites determinísticos. O Prontoo separa page load de requisições auxiliares para evitar métricas ambíguas.

## Page load

Uma amostra representa navegação HTML concluída, do início do front controller ao fim útil da renderização. A série permite comparar evolução de experiência sem misturar fetch e endpoints internos.

## Requisições e registros

Outras séries podem medir volume de requests e registros processados. Elas respondem capacidade/carga, não duração de página.

## Uso correto

Procure tendência e mudança de distribuição, correlacione com deploys e query budgets, e evite transformar uma média isolada em SLO sem entender a população. Telemetria deve conter metadados operacionais, não payloads sensíveis.
