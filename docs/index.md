# Documentação do Prontoo

Esta documentação descreve exclusivamente o estado canônico atualmente suportado do Prontoo.

## Baseline canônica

A única baseline mantida, suportada e publicável na árvore corrente é `1.8.18.6`. Versões anteriores não são conservadas como documentação paralela, pacote, asset versionado ou snapshot dentro da árvore de trabalho. Quando for necessário investigar evolução ou regressões, o histórico Git é a fonte de rastreabilidade.

Migrações de banco, marcadores de schema e compatibilidades estritamente necessárias ao runtime ou ao processo de atualização podem registrar estados técnicos anteriores. Esses elementos são mecanismos operacionais e não constituem baselines suportadas.

## Leitura recomendada

Comece por `architecture/overview.md` e `architecture/layers.md`. Para a infraestrutura visual e temas, consulte `architecture/design-tokens.md`. Em seguida, escolha a trilha correspondente ao trabalho: `domain/` para comportamento de negócio, `security/` para controles, `operations/` para execução e incidentes, `database/` para integridade, `performance/` para budgets e telemetria, e `testing/` para a malha de verificação.

## Arquitetura vigente

Os documentos de `architecture/` e os ADRs de `adr/` descrevem a arquitetura consolidada. A documentação corrente deve explicar apenas o estado vigente; relatórios de fases, snapshots de versões substituídas e auditorias históricas não permanecem na árvore canônica.

## Regra de autoridade

A documentação é secundária aos contratos executáveis. `version.json` define a release canônica; `app/architecture.manifest.json` define políticas e budgets; `app/application.test-contract.json` define caracterização de Application; `design/tokens/*.tokens.json` define os tokens visuais canônicos; `tools/` contém os gates. `design/generated/tokens.css` é o artefato gerado dos tokens, e `design/styles/application.css` é a fonte canônica de seletores, estados, layouts e regras comportamentais. Se prosa e gate divergirem, o gate representa o estado operacional e a prosa deve ser atualizada.

O gate `tools/superseded-reference-contract-check` impede a reintrodução de releases literais substituídas em documentação e manifests e também protege a política de árvore sem artefatos históricos de release.

## Vocabulário

Termos como Runtime, port, adapter, composition root, ratchet, tenant, action ledger, Maestro e geração de sessão estão definidos em `glossary.md`.
