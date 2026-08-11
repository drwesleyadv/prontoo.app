# Histórico de versões

## 1.8.11.6 — Telemetria visual e Landing Page

- mantém Registros pintados depois de Visualizações no gráfico móvel de 30 dias.
- atribui o tom mais escuro à série de Registros no gráfico e nas curvas decorativas do rodapé.
- preserva a mesma hierarquia tonal também nos temas de clínica.
- adiciona o card Landing Page ao Painel do Desenvolvedor e à página Status.
- mostra no card Landing Page o total dos 15 dias móveis recentes e a comparação percentual contra os 15 dias imediatamente anteriores.
- reutiliza a rota canônica landing já persistida em views.json, sem criar contador ou arquivo paralelo.
- amplia o contrato executável de telemetria para proteger a ordem de pintura, os tons e a fonte do novo card.

## 1.8.11.5 — Telemetria em janela móvel de 30 dias

- Redefine os últimos 30 dias como janela móvel encerrada no timestamp corrente, sem ancoragem em meia-noite.
- Compara 15 dias móveis recentes com os 15 imediatamente anteriores.
- Faz Visualizações e Tempo médio refletirem eventos persistidos até o instante corrente e mantém Registros pela amostragem do Maestro.
- Exibe Visualizações, Tempo médio e Registros nos cards e mantém os gráficos sobre as mesmas fontes canônicas.
- Atualiza cards e gráficos automaticamente a cada 60 segundos enquanto a página estiver visível.
- Mantém views.json, speed.json e database.json com retenção móvel de 31 dias.
- Preserva schema e banco sem DDL.

## 1.8.11.4 — Telemetria confiável de Visualizações e Registros

- padroniza o gráfico em 30 dias civis completos de America/Cuiaba, divididos em 15 dias recentes e 15 imediatamente anteriores.
- mantém Visualizações como eventos page_load válidos e deduplicados por evento_id.
- substitui a inferência de Registros pelo estoque exato de linhas de todas as tabelas base, amostrado pelo Maestro a cada execução válida.
- calcula Registros como total atual menos o total imediatamente anterior, preservando inclusive deltas negativos.
- persiste a série em ssd/telemetry/database-record-counts.json com retenção móvel de 31 dias.
- preserva schema e banco sem DDL e adiciona contrato executável para as fronteiras 30d e 15x15.

## 1.8.11.3 — Telemetria confiável de Visualizações e Registros

- padroniza o gráfico em 30 dias civis completos de America/Cuiaba, divididos em 15 dias recentes e 15 imediatamente anteriores.
- mantém Visualizações como eventos page_load válidos e deduplicados por evento_id.
- substitui a inferência de Registros pelo estoque exato de linhas de todas as tabelas base, amostrado pelo Maestro a cada execução válida.
- calcula Registros como total atual menos o total imediatamente anterior, preservando inclusive deltas negativos.
- persiste a série em ssd/telemetry/database-record-counts.json com retenção móvel de 31 dias.
- preserva schema e banco sem DDL e adiciona contrato executável para as fronteiras 30d e 15x15.

## 1.8.11.2 — Manutenção arquitetural e hardening de sessão

- formaliza `1.8.11.1` como baseline arquitetural consolidada e adota modo de manutenção incremental.
- institui `refactor-on-touch` executável para hotspots Runtime acima de 500 linhas.
- extrai o logout para coordinator dedicado com até três tentativas de revogação global.
- torna explícita a degradação do logout com sessão local destruída e resposta HTTP 503 quando a revogação global não é confirmada.
- adiciona smoke HTTP real para CSRF, rotação de sessão, revogação multissessão e falha de escrita da geração canônica.
- preserva schema, banco e interface e mantém as invariantes arquiteturais de risco em zero.

## 1.8.11.1 — Fechamento da arquitetura em camadas

- conclui as fases arquiteturais e o ciclo corretivo que tornaram verdadeiros os contratos de fronteira Runtime.
- fixa em zero SQL de negócio, PDO direto, transações de caso de uso no Runtime, `OperationGateway`, símbolos internos não resolvidos e findings objetivos do auditor SOLID.
- consolida testes de Application, budgets MySQL e documentação como contratos de CI.

## 1.8.10.x — Migração de casos de uso e contratos

- move responsabilidades transacionais e semânticas para Application/ports/adapters apropriados.
- amplia caracterização de casos críticos e mede dependências residuais por ratchets monotônicos.

## 1.8.9.x e anteriores — Preparação da consolidação

- remove compatibilidade legada, fecha fronteiras de composição, estabiliza PHP 8.4, Maestro, telemetria e contratos de runtime.
- esta seção resume a trajetória histórica; detalhes que ainda ajudam a compreender decisões estão arquivados em `docs/architecture/` e `docs/adr/`.
