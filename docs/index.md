# Índice da documentação

## Arquitetura

- [Visão geral](architecture/overview.md)
- [Camadas](architecture/layers.md)
- [Regra de dependências](architecture/dependencies.md)
- [C4 — contexto](architecture/c4-context.md)
- [C4 — contêineres](architecture/c4-containers.md)
- [Fluxos de dados](architecture/data-flow.md)
- [Mapa de responsabilidades](architecture/responsibility-map.md)
- [Fase 1 — enxugamento estrutural](architecture/phase-1-refactoring.md)
- [Fase 2 — apresentação e leitura](architecture/phase-2-presentation-boundaries.md)
- [Fase 3 — consultas e casos de uso](architecture/phase-3-read-use-cases.md)

## Decisões arquiteturais

- [ADR-0001 — arquitetura em camadas](adr/0001-layered-architecture.md)
- [ADR-0002 — isolamento por consultório](adr/0002-tenant-isolation.md)
- [ADR-0003 — ledger transacional de ações](adr/0003-action-ledger.md)
- [ADR-0004 — cache JSON por geração](adr/0004-json-cache-policy.md)

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

## Referência

- [Glossário](glossary.md)
