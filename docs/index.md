# Documentação do Prontoo

Esta documentação descreve exclusivamente o estado canônico atualmente suportado do Prontoo.

## Baseline canônica

A única baseline mantida, suportada e publicável na árvore corrente é `1.9.14.1`. Versões anteriores não são conservadas como documentação paralela, pacote, asset versionado ou snapshot dentro da árvore de trabalho. Quando for necessário investigar evolução ou regressões, o histórico Git é a fonte de rastreabilidade.

Migrações de banco, marcadores de schema e compatibilidades estritamente necessárias ao runtime ou ao processo de atualização podem registrar estados técnicos anteriores. Esses elementos são mecanismos operacionais e não constituem baselines suportadas.

## Leitura recomendada

Comece por `architecture/overview.md` e `architecture/layers.md`. Para a infraestrutura visual, leia `architecture/design-system.md` e `architecture/design-tokens.md`: o primeiro define componentes, estados, acessibilidade, cascade e responsividade; o segundo define DTCG 2025.10, Resolver, bindings de plataforma e compilação. Em seguida, escolha a trilha correspondente ao trabalho: `domain/` para comportamento de negócio, `security/` para controles, `operations/` para execução e incidentes, `database/` para integridade, `performance/` para budgets e telemetria, e `testing/` para a malha de verificação.

## Arquitetura vigente

Os documentos de `architecture/` e os ADRs de `adr/` descrevem a arquitetura consolidada. A documentação corrente deve explicar apenas o estado vigente; relatórios de fases, snapshots de versões substituídas e auditorias históricas não permanecem na árvore canônica.

## Regra de autoridade

A documentação é secundária aos contratos executáveis. `version.json` define a release canônica; `app/architecture.manifest.json` define políticas e budgets; `app/application.test-contract.json` define caracterização de Application; `design/tokens/*.tokens.json` define os valores e relações DTCG canônicos; `design/resolvers/application.resolver.json` define a composição das fontes e contextos; `design/platform/css.bindings.json` contém adaptação específica da plataforma CSS; `design/components/*.component.json` define anatomia, estados e semântica dos componentes; e `tools/` contém os gates.

`design/generated/tokens.css` é o artefato gerado dos tokens. `design/styles/application.css` preserva o comportamento histórico sob ratchet, enquanto `design/styles/standards.css` é a fonte normativa das novas primitivas, Cascade Layers, Container Queries e preferências de usuário. `public/assets/presentation.css` é exclusivamente artefato derivado. Se prosa e gate divergirem, o gate representa o estado operacional e a prosa deve ser atualizada.

O gate `tools/superseded-reference-contract-check` impede a reintrodução de releases literais substituídas em documentação e manifests e também protege a política de árvore sem artefatos históricos de release.

## Vocabulário

Termos como Runtime, port, adapter, composition root, ratchet, tenant, action ledger, Maestro e geração de sessão estão definidos em `glossary.md`.
