# AGENTS.md — Guardrails para agentes no Prontoo

Este arquivo é o ponto de entrada operacional para qualquer IA agêntica ou automação que altere o repositório. O objetivo é permitir mudanças pequenas, verificáveis e reversíveis sem reabrir decisões arquiteturais já consolidadas nem enfraquecer contratos de segurança, isolamento, integridade ou UX.

## 1. Ordem de autoridade

Antes de alterar código, considere as fontes nesta ordem:

1. contratos executáveis e gates em `tools/`;
2. `version.json`, fonte canônica da release;
3. `app/architecture.manifest.json`, budgets e políticas arquiteturais;
4. `app/application.test-contract.json`, caracterização de Application;
5. `app/Presentation/Styles/styles.manifest.json`, contrato da fonte CSS;
6. `CONTRIBUTING.md` e documentação em `docs/`;
7. código existente e padrões locais.

Se prosa e contrato executável divergirem, preserve o contrato e corrija a documentação. Nunca altere um gate apenas para fazer uma mudança passar sem antes demonstrar que o contrato está errado.

## 2. Baseline e ambiente

- branch de integração: `prontoo`;
- estado canônico: definido exclusivamente por `version.json`;
- PHP: família `8.4`, mínimo `8.4.0`;
- MySQL: `8.0.30` ou superior;
- aplicação: monólito modular PHP;
- política de manutenção: sem refatoração estrutural autônoma; prefira o menor diff que resolva produto, segurança, operação, desempenho ou manutenção objetiva;
- banco e schema permanecem congelados salvo escopo explícito de mudança de schema.

Nunca presuma compatibilidade com outra família de PHP, outro banco ou outra branch principal.

## 3. Modelo arquitetural obrigatório

A direção conceitual é `Runtime -> Application -> Domain`, com `Infrastructure` implementando mecanismos concretos e `Presentation` renderizando saída. Composition roots conectam ports a adapters.

Responsabilidades:

- `Runtime`: HTTP, sessão, contexto, roteamento, coordenação e adaptação de entrada;
- `Application`: sequência dos casos de uso, services e ports;
- `Domain`: regras, políticas e invariantes de negócio;
- `Infrastructure`: PDO, filesystem, integrações, persistência e mecanismos concretos;
- `Presentation`: HTML, formatação, componentes visuais e CSS.

No Runtime, os budgets estruturalmente perigosos são zero: não introduza SQL de negócio, PDO direto, fronteiras transacionais de caso de uso, `OperationGateway` nem adapters concretos fora dos composition roots. Não mova responsabilidade entre camadas por conveniência local.

Hotspots e dívidas existentes são ratchets: podem diminuir, não crescer. Ao tocar Runtime classificado como hotspot, respeite `tools/runtime-refactor-on-touch-check`.

## 4. Isolamento por consultório e autorização

O Prontoo é multi-tenant. Toda operação clínica, financeira, documental ou administrativa que pertença a um consultório deve carregar e validar o contexto de `clinic_id` pelo caminho já existente.

Regras obrigatórias:

- não remova filtros ou guardas de tenant para simplificar consultas;
- não aceite `clinic_id`, identidade, papel ou escopo enviados pelo cliente como prova de autorização;
- use o contexto autenticado e as APIs de permissão existentes;
- operações globais do Desenvolvedor devem ser explicitamente globais, nunca resultado da ausência acidental de escopo;
- não contorne `SqlScopeGuard`, evidências de isolamento ou contratos de escopo;
- um bloqueio do guard é evidência de prevenção, não autorização para relaxar a regra;
- mudanças de permissão devem permanecer server-side e auditáveis.

Qualquer dúvida sobre escopo deve ser tratada como risco de vazamento entre consultórios.

## 5. Segurança, sessão e auditoria

Preserve os contratos vigentes:

- autenticação por senha seguida do fluxo MFA tri-state fail-closed;
- MFA obrigatório para Desenvolvedor e ciclo de reautenticação dos demais conforme a política vigente;
- expiração por inatividade de 3600 segundos;
- mutações HTTP protegidas por CSRF;
- logout e revogação global não podem ser enfraquecidos;
- origem de auditoria, ator, horário, IP e agente não podem ser substituídos por dados públicos não confiáveis;
- envelopes diferidos assinados devem ser revalidados;
- segredos, comprovantes, PDFs, imagens e telemetria persistente não devem migrar para diretórios públicos.

Storage persistente canônico: `ssd/`, incluindo `ssd/pdfs`, `ssd/img` e `ssd/telemetry`. Não versione conteúdo operacional de `ssd/`.

Leia `docs/security/threat-model.md` antes de mudanças em autenticação, autorização, sessão, instalação, auditoria ou dados sensíveis.

## 6. Banco e integridade

A baseline usa schema limpo e exige banco vazio para instalação limpa. Runtime não é local de evolução oportunista de DDL.

- não adicione `ALTER`, `CREATE` ou correções de schema em rotas normais;
- preserve o contrato de schema e a política de mutação por CI/CLI;
- respeite `Seq` não nulo e único e os contratos de ledger/auditoria;
- mudanças financeiras devem manter atomicidade, idempotência e rastreabilidade existentes;
- não aumente budgets de consultas para acomodar regressão sem justificativa demonstrável.

Para qualquer mudança de persistência, leia `docs/database/` e execute os gates MySQL aplicáveis.

## 7. Presentation e UX

A fonte visual canônica está em `app/Presentation/Styles/`. `public/assets/presentation.css` é artefato gerado: nunca o edite isoladamente.

Fluxo para CSS:

1. altere o owner file semântico correto;
2. preserve a ordem de cascade de `styles.manifest.json`;
3. não aumente budgets de `!important` nem scopes de rota;
4. execute `php tools/presentation-css-build --lint` e `--check` após gerar/reconciliar o artefato pelo fluxo do projeto;
5. execute os contratos visuais/UX aplicáveis.

Não introduza estilos inline para contornar o build nem duplique componentes canônicos por rota.

### Contrato atual da telemetria global

Métricas do Desenvolvedor contém exatamente três cards comparativos: Páginas, Registros e Landing. Em viewport acima de 980 px aparecem em uma única linha com três colunas; em 980 px ou menos aparecem em uma coluna. Os cards são filhos diretos de `.admin-metrics-kpis`. Toda mudança nesse contrato deve passar `tools/presentation-ux-contract.mjs --check`, que verifica por computed style 1280 px (3×1) e 390 px (1×3).

Cada card compara os 10 dias móveis recentes aos 10 imediatamente anteriores. Páginas conta page loads canônicos, Registros soma `database_query_count` e Landing conta a rota canônica `landing`. SQL, parâmetros, resultados e dados clínicos não são persistidos.

Depois dos três cards, Métricas preserva os gráficos canônicos Velocidade e Volume. Velocidade usa os 1.440 minutos completos das últimas 24 horas, com um ponto por minuto e 24 marcas horárias centralizadas. Volume usa 30 intervalos consecutivos de 24 horas, com exatamente 30 pontos nos últimos 30 dias. Nos dois gráficos, Carregamento de Páginas é a série primária, Consulta é a série secundária, intervalos sem eventos são pontos explícitos em zero e a geometria liga os valores consecutivos por segmentos retos. Velocidade compara durações médias e Volume compara quantidades. Os gráficos atualizam a cada minuto e não substituem nem incorporam a tabela da tela Rotas.

O detalhamento pertence à tela Rotas, separada de Métricas. A tabela exibe nomes funcionais, requisições e tempo médio dos últimos 10 dias, ordenando primeiro pelo maior número de requisições e depois pelo menor tempo médio.

O Status público preserva seu contrato independente de quatro KPIs: acima de 980 px, uma linha com quatro colunas; em 980 px ou menos, duas colunas por duas linhas. A simplificação de Métricas não altera essa superfície pública.

## 8. Release e versionamento

`version.json` é a única fonte canônica de versão. O formato é `1.mês.dia.sequência`; a sequência começa em 1 e reinicia em novo dia de publicação.

Para uma release:

1. faça bump explícito em `version.json` (`version`, `release`, `build`, timestamps, flags e `changelog`);
2. não edite fallbacks, `app/update.manifest.json` ou `app/architecture.manifest.json` de forma independente;
3. execute `php tools/release-contract-reconcile --write`;
4. confirme com `php tools/release-contract-reconcile --check`;
5. verifique que `CHANGELOG.md`, fallbacks e hashes derivados convergiram.

Uma alteração rastreada sem reconciliação de release não está pronta para merge.

## 9. Sequência de trabalho para agentes

Antes de editar:

1. leia este arquivo, `CONTRIBUTING.md`, `version.json` e os docs da área;
2. procure implementação e testes existentes antes de criar abstração nova;
3. identifique tenant, permissão, transação, audit trail e superfície de Presentation afetados;
4. confirme se o arquivo é hotspot ou owner canônico;
5. defina o menor escopo reversível.

Durante a edição:

- preserve nomes e contratos públicos salvo requisito explícito;
- reutilize ports, composition roots, helpers e componentes existentes;
- não faça limpeza lateral não solicitada;
- não altere banco/schema em microajustes;
- não adicione comentários inline como substituto de arquitetura; o projeto privilegia nomes, tipos, testes, ADRs e documentação.

Antes do merge:

- revise o diff por mudança acidental;
- execute `php tools/quality-gate --fast` e, para mudança relevante, `php tools/quality-gate`;
- execute `php tools/release-contract-reconcile --check`;
- execute `php tools/documentation-check.php` quando documentação mudar;
- execute gates específicos de schema, MySQL, segurança, Presentation ou Runtime conforme a área;
- só trate a tarefa como concluída quando os checks de PR aplicáveis estiverem verdes.

## 10. Ações proibidas sem requisito explícito

- refatoração arquitetural ampla ou renomeação em massa;
- mudança de schema/banco em tarefa visual ou documental;
- relaxar tenant guard, autorização, MFA, CSRF, sessão ou auditoria;
- aumentar budgets/ratchets para silenciar falhas;
- editar artefatos gerados sem a fonte canônica;
- editar manifests derivados ou fallbacks manualmente como fonte de verdade;
- adicionar dependência externa quando o projeto já possui primitive equivalente;
- usar dados de produção em testes ou versionar `ssd/`;
- introduzir `!important`, selector de rota ou exceção inline apenas para vencer cascade;
- apagar teste/gate para fazer CI passar.

## 11. Mapa rápido

- `app/Runtime/`: entrada e coordenação;
- `app/Application/`: casos de uso e ports;
- `app/Domain/`: regras e invariantes;
- `app/Infrastructure/`: mecanismos concretos;
- `app/Presentation/`: renderização e fontes visuais;
- `app/architecture.manifest.json`: contrato arquitetural executável;
- `app/application.test-contract.json`: caracterização de Application;
- `app/Presentation/Styles/styles.manifest.json`: contrato CSS;
- `tools/`: quality gates e reconciliadores;
- `docs/architecture/`: modelo e fronteiras;
- `docs/security/`: ameaças e controles;
- `docs/database/`: schema e integridade;
- `docs/testing/`: estratégia e gates;
- `docs/operations/`: deploy, incidentes e execução;
- `docs/audits/`: auditorias e baselines publicadas.

## 12. Definition of Done

Uma mudança está pronta quando o comportamento pedido é observável, o diff é mínimo, tenant/segurança/integridade permanecem preservados, budgets não cresceram, artefatos derivados estão reconciliados, documentação corresponde ao estado mergeado e a CI aplicável passa sem exceções improvisadas.

### Estado canônico sem compatibilidade histórica

O código ativo, contratos e documentação normativa devem referenciar apenas caminhos, artefatos e mecanismos canônicos atuais. Não mantenha aliases, resolvers, mapas de migração de paths, fallbacks de arquivos ou metadados de release anterior apenas para compatibilidade com versões superadas. Histórico permanece no Git, não no runtime nem nos contratos executáveis.
