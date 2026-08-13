# Documentação do Prontoo

Esta documentação descreve o Prontoo como ele existe na arquitetura consolidada. O objetivo não é reproduzir o código em prosa, mas oferecer um modelo mental que permita entender decisões, localizar responsabilidades e operar o sistema sem depender de conhecimento oral.

## Entrada para agentes


## Leitura recomendada

Comece por `architecture/overview.md` e `architecture/layers.md`. Em seguida, escolha a trilha correspondente ao trabalho: `domain/` para comportamento de negócio, `security/` para controles, `operations/` para execução e incidentes, `database/` para integridade, `performance/` para budgets e telemetria, e `testing/` para a malha de verificação.

## Arquitetura vigente

Os documentos `architecture/overview.md`, `layers.md`, `dependencies.md`, `runtime-boundary.md`, `responsibility-map.md`, `data-flow.md` e os diagramas C4 textuais são a referência explicativa principal. `architecture/MAINTENANCE.md` explica por que o projeto não está em novo ciclo de refatoração.

Documentos `phase-*`, auditorias de consolidação e conformidade PHP são históricos. Eles foram reescritos para registrar o que cada etapa acrescentou ao estado final, não para orientar novas migrações.

## Regra de autoridade

A documentação é secundária aos contratos executáveis. `version.json` define release; `app/architecture.manifest.json` define políticas e budgets; `app/application.test-contract.json` define caracterização de Application; `tools/` contém os gates. Se prosa e gate divergirem, o gate representa o estado operacional e a prosa deve ser atualizada.

## Vocabulário

Termos como Runtime, port, adapter, composition root, ratchet, tenant, action ledger, Maestro e geração de sessão estão definidos em `glossary.md`.
