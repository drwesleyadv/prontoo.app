# Índice da documentação

## Arquitetura atual — fonte de trabalho

- [Visão geral](architecture/overview.md)
- [Camadas e classificação](architecture/layers.md)
- [Regra de dependências](architecture/dependencies.md)
- [Mapa atual de responsabilidades](architecture/responsibility-map.md)
- [C4 — contexto](architecture/c4-context.md)
- [C4 — contêineres](architecture/c4-containers.md)
- [Fluxos de dados e runtime](architecture/data-flow.md)
- [Contrato da fronteira Runtime](architecture/runtime-boundary.md)

## Histórico da migração arquitetural

Os documentos abaixo registram etapas concluídas. Para localização atual de código, use o mapa de responsabilidades acima.

- [Fase 1 — enxugamento estrutural](architecture/phase-1-refactoring.md)
- [Fase 2 — apresentação e leitura](architecture/phase-2-presentation-boundaries.md)
- [Fase 3 — consultas e casos de uso](architecture/phase-3-read-use-cases.md)
- [Fase 4 — comandos transacionais](architecture/phase-4-transactional-commands.md)
- [Fase 5 — endurecimento financeiro crítico](architecture/phase-5-critical-financial-command.md)

## Decisões arquiteturais

- [ADR-0001 — arquitetura em camadas](adr/0001-layered-architecture.md)
- [ADR-0002 — isolamento por consultório](adr/0002-tenant-isolation.md)
- [ADR-0003 — ledger transacional de ações](adr/0003-action-ledger.md)
- [ADR-0004 — cache JSON por geração](adr/0004-json-cache-policy.md)
- [ADR-0005 — fechamento semântico do Runtime](adr/0005-runtime-semantic-closure.md)
- [ADR-0008 — correção da fronteira de entrada Runtime](adr/0008-runtime-input-boundary-correction.md)

## Domínios

- [Agenda e jornada](domain/appointments.md)
- [Pacientes e pessoas](domain/patients.md)
- [Financeiro](domain/financial.md)
- [Documentos](domain/documents.md)
- [Tarefas](domain/tasks.md)
- [Maestro](domain/maestro.md)

## Segurança

- [Modelo de ameaças](security/threat-model.md)
- [Autenticação](security/authentication.md)
- [Autorização](security/authorization.md)
- [Isolamento multitenant](security/tenant-isolation.md)
- [Cadeia de auditoria](security/audit-chain.md)

## Operação

- [Instalação](operations/installation.md)
- [Implantação](operations/deployment.md)
- [Rollback](operations/rollback.md)
- [Backup e restauração](operations/backup-restore.md)
- [Resposta a incidentes](operations/incident-response.md)
- [Runbook do Maestro](operations/maestro-runbook.md)

## Banco e testes

- [Visão do schema](database/schema-overview.md)
- [Invariantes](database/invariants.md)
- [Política de mudanças](database/migrations-policy.md)
- [Integridade financeira](database/financial-integrity.md)
- [Estratégia de testes](testing/strategy.md)
- [Suíte regressiva](testing/regression-suite.md)
- [Testes de propriedades](testing/property-tests.md)
- [Baseline de caracterização](testing/characterization-baseline.md)

## Performance e casos arquiteturais específicos

- [Glossário](glossary.md)
- [Orçamentos de desempenho](performance/performance-budgets.md)
- [Invalidação de cache por geração](performance/cache-generations.md)
- [Telemetria de rotas](performance/telemetry.md)
- [Read model do histórico da recepção](performance/patient-reception-read-model.md)
- [Comando de contato do paciente](architecture/patient-contact-command.md)
- [View de contato do paciente](architecture/patient-contact-view.md)
- [Auditoria de consolidação pós-zero-legacy](architecture/CONSOLIDATION-AUDIT-1.8.9.1.md)

## Contratos executáveis

A documentação é complementada por `app/architecture.manifest.json`, `app/runtime.boundary-contract.json`, `app/runtime.boundary-baseline.json`, `app/update.manifest.json`, `version.json`, `tools/architecture-check.php`, `tools/runtime-boundary-check`, `tools/solid-audit`, `tools/documentation-check.php`, `tools/release-contract-reconcile` e pelos workflows de CI. Em divergência entre texto histórico e contrato atual, corrija a documentação; não contorne o contrato silenciosamente.
