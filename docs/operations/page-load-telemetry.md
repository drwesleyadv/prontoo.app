# Operação da telemetria de carregamento

A série de page load mede navegações HTML concluídas. Ela não deve ser interpretada como contagem de todas as requisições HTTP.

## Fonte

O marcador inicial fica no primeiro ponto executável do front controller; o final ocorre depois do último passo útil da renderização. Fetch, XHR, JSON, prefetch, redirects e downloads são excluídos desta série.

## Armazenamento

Eventos canônicos são persistidos em `ssd/telemetry/page-loads.jsonl`. Telemetria histórica de requisições pode coexistir, mas não deve ser misturada com page load.

## Diagnóstico

Se a série cair a zero, primeiro verifique se navegações HTML continuam registrando marcador final. Se a duração subir, correlacione com query budgets, erros de banco e mudança recente; não conclua causalidade apenas pelo gráfico.
