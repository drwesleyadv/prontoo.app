# Histórico de versões

## 1.8.13.2 — Baseline auditada e guardrails para agentes

- corrige a composição dos quatro KPIs de telemetria para 2 colunas × 2 linhas em viewport ampla, preservando a queda responsiva para uma coluna.
- remove do wrapper interno a combinação de classes cujo auto-fit anulava a intenção da grade two, sem alterar CSS, cálculos ou fontes de telemetria.
- atualiza README, índice documental e guia de contribuição para a baseline vigente.
- adiciona AGENTS.md com ordem de autoridade, invariantes de arquitetura, tenant, segurança, banco, Presentation, release e checklist de merge para IA agêntica.
- mantém banco e schema inalterados.

## 1.8.13.1 — KPIs do Painel e Status em grade 2×2

- distribui os quatro cards de telemetria do Painel do Desenvolvedor em duas colunas e duas linhas.
- aplica a mesma composição 2×2 aos quatro cards da página pública de Status.
- reutiliza o renderer compartilhado dos KPIs para evitar duplicação de layout.
- mantém cálculos, fontes e indicadores de tendência da telemetria inalterados.
- mantém banco e schema inalterados.

## 1.8.12.24 — Redução contratada de prioridades em primitives visuais

- remove 8 ocorrências de !important em primitives já cobertas pelo contrato observável oficial.
- reduz o budget de !important de 4.881 para 4.873.
- mantém estáveis as 396 superfícies Chromium em desktop e mobile.
- mantém route scopes em 1.091 sem ampliar seletores.
- mantém banco e schema inalterados.

## 1.8.12.23 — Redução contratada de prioridades em primitives visuais

- remove 8 ocorrências de !important em primitives já cobertas pelo contrato observável oficial.
- reduz o budget de !important de 4.889 para 4.881.
- mantém estáveis as 396 superfícies Chromium em desktop e mobile.
- mantém route scopes em 1.091 sem ampliar seletores.
- mantém banco e schema inalterados.

## 1.8.12.22 — Novo lote de prioridades visuais contratadas

- reduz 5 ocorrências de !important formalmente comprovadas, de 4894 para 4889.
- mantém estáveis as 396 superfícies do contrato Chromium em desktop e mobile.
- mantém route scopes em 1.091 sem ampliar o alcance de seletores.
- mantém banco e schema inalterados.
- ratcheta o budget de !important para 4889 sem ampliar qualquer outro budget visual.

## 1.8.12.21 — Novo lote de prioridades visuais contratadas

- reduz 8 ocorrências de !important formalmente comprovadas, de 4902 para 4894.
- mantém estáveis as 396 superfícies do contrato Chromium em desktop e mobile.
- mantém route scopes em 1.091 sem ampliar o alcance de seletores.
- mantém banco e schema inalterados.
- ratcheta o budget de !important para 4894 sem ampliar qualquer outro budget visual.

## 1.8.12.20 — Novo lote de prioridades visuais contratadas

- reduz 8 ocorrências de !important formalmente comprovadas, de 4910 para 4902.
- mantém estáveis as 396 superfícies do contrato Chromium em desktop e mobile.
- mantém route scopes em 1.091 sem ampliar o alcance de seletores.
- mantém banco e schema inalterados.
- ratcheta o budget de !important para 4902 sem ampliar qualquer outro budget visual.

## 1.8.12.19 — Novo lote de prioridades visuais contratadas

- reduz 8 ocorrências de !important formalmente comprovadas, de 4918 para 4910.
- mantém estáveis as 396 superfícies do contrato Chromium em desktop e mobile.
- mantém route scopes em 1.091 sem ampliar o alcance de seletores.
- mantém banco e schema inalterados.
- ratcheta o budget de !important para 4910 sem ampliar qualquer outro budget visual.

## 1.8.12.18 — Novo lote de prioridades visuais contratadas

- reduz 8 ocorrências de !important formalmente comprovadas, de 4926 para 4918.
- mantém estáveis as 396 superfícies do contrato Chromium em desktop e mobile.
- mantém route scopes em 1.091 sem ampliar o alcance de seletores.
- mantém banco e schema inalterados.
- ratcheta o budget de !important para 4918 sem ampliar qualquer outro budget visual.

## 1.8.12.17 — Segundo lote de prioridades visuais contratadas

- reduz mais 8 ocorrências de !important formalmente comprovadas em Paciente e Procedimentos, de 4.934 para 4.926.
- mantém estáveis as 396 superfícies do contrato Chromium em desktop e mobile.
- mantém route scopes em 1.091 sem ampliar o alcance de seletores.
- mantém banco e schema inalterados.
- ratcheta o budget de !important para 4.926 sem ampliar qualquer outro budget visual.

## 1.8.12.16 — Prioridades visuais contratadas e versão no Status

- Reduz 8 ocorrências de !important formalmente comprovadas em Paciente e Procedimentos, de 4.942 para 4.934.
- Mantém estáveis as 396 superfícies do contrato Chromium em desktop e mobile.
- Substitui a descrição da janela móvel na página de Status por “Versão: 1.8.12.16”, no padrão do painel do Desenvolvedor.
- Mantém banco e schema inalterados.
- ratcheta o budget de !important para 4.934 sem ampliar qualquer outro budget visual.

## 1.8.12.15 — CSS de Presentation consolidado sem alteração de UX

- remove o arquivo físico public/assets/design-system.css e passa a gerar public/assets/presentation.css.
- divide os 810.078 bytes do stylesheet em módulos de cascade order sob app/Presentation/Styles sem reordenar regras.
- prova equivalência por SHA-256 e tamanho, mantendo exatamente os mesmos bytes entregues ao navegador.
- mantém temporariamente o URL histórico por rewrite interno para não tocar hotspots de Runtime nesta migração.
- separa conclusão da consolidação de fonte da eliminação futura da dívida visual.
- ratcheia o teto existente de 5.083 !important e 1.171 scopes de rota sem permitir aumento.
- integra lint de fonte e equivalência do artefato ao quality gate canônico.

## 1.8.12.14 — CSS de Presentation consolidado sem alteração de UX

- remove o arquivo físico public/assets/design-system.css e passa a gerar public/assets/presentation.css.
- divide os 810.078 bytes do stylesheet em módulos de cascade order sob app/Presentation/Styles sem reordenar regras.
- prova equivalência por SHA-256 e tamanho, mantendo exatamente os mesmos bytes entregues ao navegador.
- mantém temporariamente o URL histórico por rewrite interno para não tocar hotspots de Runtime nesta migração.
- separa conclusão da consolidação de fonte da eliminação futura da dívida visual.
- ratcheia o teto existente de 5.083 !important e 1.171 scopes de rota sem permitir aumento.
- integra lint de fonte e equivalência do artefato ao quality gate canônico.

## 1.8.12.13 — CSS de Presentation consolidado sem alteração de UX

- remove o arquivo físico public/assets/design-system.css e passa a gerar public/assets/presentation.css.
- divide os 810.078 bytes do stylesheet em módulos de cascade order sob app/Presentation/Styles sem reordenar regras.
- prova equivalência por SHA-256 e tamanho, mantendo exatamente os mesmos bytes entregues ao navegador.
- mantém temporariamente o URL histórico por rewrite interno para não tocar hotspots de Runtime nesta migração.
- separa conclusão da consolidação de fonte da eliminação futura da dívida visual.
- ratcheia o teto existente de 5.083 !important e 1.171 scopes de rota sem permitir aumento.
- integra lint de fonte e equivalência do artefato ao quality gate canônico.

## 1.8.12.12 — CSS de Presentation consolidado sem alteração de UX

- remove o arquivo físico public/assets/design-system.css e passa a gerar public/assets/presentation.css.
- divide os 810.078 bytes do stylesheet em módulos de cascade order sob app/Presentation/Styles sem reordenar regras.
- prova equivalência por SHA-256 e tamanho, mantendo exatamente os mesmos bytes entregues ao navegador.
- mantém temporariamente o URL histórico por rewrite interno para não tocar hotspots de Runtime nesta migração.
- separa conclusão da consolidação de fonte da eliminação futura da dívida visual.
- ratcheia o teto existente de 5.083 !important e 1.171 scopes de rota sem permitir aumento.
- integra lint de fonte e equivalência do artefato ao quality gate canônico.

## 1.8.12.11 — CSS de Presentation consolidado sem alteração de UX

- remove o arquivo físico public/assets/design-system.css e passa a gerar public/assets/presentation.css.
- divide os 810.078 bytes do stylesheet em módulos de cascade order sob app/Presentation/Styles sem reordenar regras.
- prova equivalência por SHA-256 e tamanho, mantendo exatamente os mesmos bytes entregues ao navegador.
- mantém temporariamente o URL histórico por rewrite interno para não tocar hotspots de Runtime nesta migração.
- separa conclusão da consolidação de fonte da eliminação futura da dívida visual.
- ratcheia o teto existente de 5.083 !important e 1.171 scopes de rota sem permitir aumento.
- integra lint de fonte e equivalência do artefato ao quality gate canônico.

## 1.8.12.10 — CSS de Presentation consolidado sem alteração de UX

- remove o arquivo físico public/assets/design-system.css e passa a gerar public/assets/presentation.css.
- divide os 810.078 bytes do stylesheet em módulos de cascade order sob app/Presentation/Styles sem reordenar regras.
- prova equivalência por SHA-256 e tamanho, mantendo exatamente os mesmos bytes entregues ao navegador.
- mantém temporariamente o URL histórico por rewrite interno para não tocar hotspots de Runtime nesta migração.
- separa conclusão da consolidação de fonte da eliminação futura da dívida visual.
- ratcheia o teto existente de 5.083 !important e 1.171 scopes de rota sem permitir aumento.
- integra lint de fonte e equivalência do artefato ao quality gate canônico.

## 1.8.12.9 — CSS de Presentation consolidado sem alteração de UX

- remove o arquivo físico public/assets/design-system.css e passa a gerar public/assets/presentation.css.
- divide os 810.078 bytes do stylesheet em módulos de cascade order sob app/Presentation/Styles sem reordenar regras.
- prova equivalência por SHA-256 e tamanho, mantendo exatamente os mesmos bytes entregues ao navegador.
- mantém temporariamente o URL histórico por rewrite interno para não tocar hotspots de Runtime nesta migração.
- separa conclusão da consolidação de fonte da eliminação futura da dívida visual.
- ratcheia o teto existente de 5.083 !important e 1.171 scopes de rota sem permitir aumento.
- integra lint de fonte e equivalência do artefato ao quality gate canônico.

## 1.8.12.8 — CSS de Presentation consolidado sem alteração de UX

- remove o arquivo físico public/assets/design-system.css e passa a gerar public/assets/presentation.css.
- divide os 810.078 bytes do stylesheet em módulos de cascade order sob app/Presentation/Styles sem reordenar regras.
- prova equivalência por SHA-256 e tamanho, mantendo exatamente os mesmos bytes entregues ao navegador.
- mantém temporariamente o URL histórico por rewrite interno para não tocar hotspots de Runtime nesta migração.
- separa conclusão da consolidação de fonte da eliminação futura da dívida visual.
- ratcheia o teto existente de 5.083 !important e 1.171 scopes de rota sem permitir aumento.
- integra lint de fonte e equivalência do artefato ao quality gate canônico.

## 1.8.12.7 — CSS de Presentation consolidado sem alteração de UX

- remove o arquivo físico public/assets/design-system.css e passa a gerar public/assets/presentation.css.
- divide os 810.078 bytes do stylesheet em módulos de cascade order sob app/Presentation/Styles sem reordenar regras.
- prova equivalência por SHA-256 e tamanho, mantendo exatamente os mesmos bytes entregues ao navegador.
- mantém temporariamente o URL histórico por rewrite interno para não tocar hotspots de Runtime nesta migração.
- separa conclusão da consolidação de fonte da eliminação futura da dívida visual.
- ratcheia o teto existente de 5.083 !important e 1.171 scopes de rota sem permitir aumento.
- integra lint de fonte e equivalência do artefato ao quality gate canônico.

## 1.8.12.6 — CSS de Presentation consolidado sem alteração de UX

- remove o arquivo físico public/assets/design-system.css e passa a gerar public/assets/presentation.css.
- divide os 810.078 bytes do stylesheet em módulos de cascade order sob app/Presentation/Styles sem reordenar regras.
- prova equivalência por SHA-256 e tamanho, mantendo exatamente os mesmos bytes entregues ao navegador.
- mantém temporariamente o URL histórico por rewrite interno para não tocar hotspots de Runtime nesta migração.
- separa conclusão da consolidação de fonte da eliminação futura da dívida visual.
- ratcheia o teto existente de 5.083 !important e 1.171 scopes de rota sem permitir aumento.
- integra lint de fonte e equivalência do artefato ao quality gate canônico.

## 1.8.12.5 — PageHeadControl canônico e redução de compatibilidade

- institui renderer único de Presentation para controles do PageHead.
- emite papéis nav, primary, secondary e danger sobre uma única geometria de 38 px desktop e 42 px mobile.
- consome classes legadas no boundary sem permitir que sejam autoridade visual no CSS ou JavaScript.
- remove aliases de tokens PageHeadAction e seletores pagehead-actions, pagehead-operations e operation-chip do contrato visual.
- remove suporte morto a operation menu e reduz UiComponentsRuntimeOperations03 para menos de 500 linhas.
- preserva cor do consultório, somente leitura, danger semântico, compactação mobile e fluxo funcional existente.
- publica a mudança sem alteração de banco ou schema.

## 1.8.12.4 — Harmonia global dos controles do PageHead

- unifica em 38 px a altura desktop de navegação, ações primárias e ações secundárias do PageHead, preservando 42 px no mobile.
- compartilha raio de 16 px, padding, tipografia, ícones de 20 px, alinhamento e ritmo entre todos os controles canônicos.
- mantém operações como navegação segmentada, ações primárias preenchidas e ações secundárias com tint e borda sem apagar sua hierarquia.
- remove exceções geométricas de Configurações, Procedimentos, Perfil e Caixa que criavam 40 px ou radius 999 px dentro do PageHead.
- faz as operações de navegação consumirem a mesma identidade cromática contextual do consultório, inclusive em somente leitura.
- reforça o Presentation Visual Contract para impedir novos overrides de identidade de operações ou ações por rota.
- preserva posições, nomenclaturas, fluxos, permissões, banco, schema e estratégia de compactação mobile já conhecida pelos usuários.

## 1.8.12.3 — Engenharia arquitetural 16–21 e consolidação

- institui análise tokenizada e baseline monotônica para a fronteira Runtime.
- remove do Runtime a persistência financeira, de autenticação, MFA, permissões e módulos operacionais sem alterar comportamento.
- mantém SQL de negócio, PDO direto, adapters concretos fora dos composition roots e transações de caso de uso em zero no Runtime.
- cataloga 49 casos críticos de 30 Application Services, ligados a 15 ports e 127 assertivas rastreáveis dentro de 194 assertivas rápidas sem banco.
- amplia de 2 para 11 os budgets reais MySQL e mede SELECT, INSERT, UPDATE, DELETE e REPLACE sem usar tempo absoluto frágil.
- move renderizadores concretos de consultas de Domain e Core para Infrastructure e perfila Composition por responsabilidade.
- consolida em CI os contratos finais de classificação, SOLID, símbolos, documentação, release e performance.
- publica a conclusão das fases 16–21 sem mudança de schema, banco ou interface.

## 1.8.12.2 — Engenharia arquitetural 16–21 e consolidação

- institui análise tokenizada e baseline monotônica para a fronteira Runtime.
- remove do Runtime a persistência financeira, de autenticação, MFA, permissões e módulos operacionais sem alterar comportamento.
- mantém SQL de negócio, PDO direto, adapters concretos fora dos composition roots e transações de caso de uso em zero no Runtime.
- cataloga 49 casos críticos de 30 Application Services, ligados a 15 ports e 127 assertivas rastreáveis dentro de 194 assertivas rápidas sem banco.
- amplia de 2 para 11 os budgets reais MySQL e mede SELECT, INSERT, UPDATE, DELETE e REPLACE sem usar tempo absoluto frágil.
- move renderizadores concretos de consultas de Domain e Core para Infrastructure e perfila Composition por responsabilidade.
- consolida em CI os contratos finais de classificação, SOLID, símbolos, documentação, release e performance.
- publica a conclusão das fases 16–21 sem mudança de schema, banco ou interface.

## 1.8.12.1 — Contrato visual canônico da Presentation

- canoniza as ações fixas do PageHead em 34 px de altura no desktop, radius de 16 px, padding de 7 por 12 px, gap de 8 px e ícone de 20 px.
- preserva no mobile o alvo tátil mínimo de 42 px e padding horizontal de 13 px sem alterar posição, fluxo ou hierarquia dos comandos.
- faz a paleta canônica derivar do destaque configurado por consultório e mantém estados destrutivos na semântica de erro do sistema.
- usa no modo somente leitura uma variante secundária derivada da mesma cor do consultório, alterando cor sem alterar geometria.
- remove exceções cosméticas específicas de Agenda e Documentos para ações que exercem o mesmo papel estrutural.
- institui contrato de Presentation e ratchet monotônico para impedir crescimento de important, overrides de rota e combinações visuais legadas.
- integra o contrato visual ao quality gate sem mudança de banco, schema ou regras de negócio.

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
