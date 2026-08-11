# Mapa de responsabilidades

Este documento descreve **onde a responsabilidade vive hoje**. Os documentos `phase-*` permanecem como registro histórico da migração; não devem ser usados como mapa da árvore atual.

## Mapa canônico

| Responsabilidade | Local atual |
|---|---|
| invariantes de contexto, mutação, tenant, SQL e workflow | `app/Core/Invariant` |
| políticas temporais | `app/Core/Temporal` |
| arquitetura e classificação | `app/Core/Architecture` |
| regras de identidade/paciente | `app/Domain/Identity`, `app/Domain/Patients` |
| regras e políticas de domínio | `app/Domain/*` |
| casos de uso e portas | `app/Application/*` |
| contratos e catálogo declarativo de autorização | `app/Application/Authorization/*` |
| PDO e repositórios | `app/Infrastructure/*` |
| catálogo SQL e adapter de dados financeiros | `app/Infrastructure/Financial/FinancialSqlCatalog*.php`, `PdoFinancialDataRepository.php` |
| casos de uso e catálogo de persistência de identidade | `app/Application/Identity/*`, `app/Infrastructure/Identity/*` |
| casos de uso operacionais e catálogos fechados | `app/Application/Operational/*`, `app/Infrastructure/Operational/*` |
| renderização de consultas de leads, auditoria, pacientes e consultório-modelo | `app/Infrastructure/Operational/LeadQuerySql.php`, `AuditActivityQuerySql.php`, `PatientDirectorySql.php`, `ModelClinicQuerySql.php` |
| registro persistente de incidentes de escopo | `app/Application/SecurityAccess/SecurityIncident*`, `app/Infrastructure/SecurityAccess/PdoSecurityIncidentRepository.php` |
| ciclo persistente de cadastro MFA | `app/Application/SecurityAccess/MfaRecord*`, `app/Infrastructure/SecurityAccess/PdoMfaRecordRepository.php` |
| auditoria/integridade concreta | `app/Infrastructure/Audit`, `app/Infrastructure/Integrity` |
| HTTP, views e JSON | `app/Presentation/*` |
| composição de módulos | `app/Runtime/Modules/*` |
| composição do catálogo de autorização | `app/Runtime/Authorization/*` |
| prontidão e manutenção | `app/Runtime/Boot/*` |
| roteamento | `app/Runtime/Routing/*` |
| wiring concreto de features | os cinco roots enumerados por `LayerMap::compositionRoots()` |
| input adapters HTTP/runtime | demais handlers em `app/Runtime/*`; a classificação não presume que já sejam finos |
| inventário monotônico da fronteira Runtime | `app/runtime.boundary-contract.json`, `app/runtime.boundary-baseline.json`, `tools/runtime-boundary-check` |
| dívida por papel dos input adapters | `app/runtime.input-boundary-budget.json`, `tools/runtime-input-boundary-check` |
| matriz e cobertura dos casos críticos de Application | `app/application.test-contract.json`, `tools/application-test-contract-check`, `tools/test-fast` |
| budgets reais dos read models críticos | `app/query.budgets.json`, `tools/query-budget-contract-check`, `tools/mysql-query-budget-check` |
| entradas web | `index.php`, `install.php`, `br`, `public` |
| execução secundária | `cron/maestro.php` |

## Autorização

O catálogo não é mais uma tabela monolítica no núcleo. `Application/Authorization` define fontes, collection/registry, requirements e definições coesas por família (`Auth`, `Patient`, `Scheduling`, `DocumentTask`, `Workforce`, `Financial`, `Admin`). `Runtime/Authorization/ActionCatalogComposition.php` faz a composição. A entrada canônica permanece `LayeredKernel::enforceAction`.

## Runtime

A composição deixou de concentrar boot, catálogo, loading e wiring numa única unidade. `RuntimeBootPolicy`, `RuntimeModuleCatalog`, `RuntimeModuleLoader` e `RuntimeModuleComposition` possuem responsabilidades separadas. `RuntimeBootCoordinator` separa prontidão mínima de manutenção profunda. `RouteCatalog`, `JsonResponder`, composições de feature e `Runner` separam roteamento, resposta e execução. `LayerMap` publica a submétrica de responsabilidades de Composition; somente cinco arquivos possuem papel de root concreto.

## Estado pós-zero-legacy

Não há fachadas globais ou namespaces `/Legacy/` executáveis. Paths históricos removidos permanecem somente nos mapas explícitos de migração, em `removed_legacy_files`, auditorias de baseline e contratos de rastreabilidade. Nova lógica deve nascer diretamente na unidade nativa da camada correta.

## Casos de uso nativos consolidados

- leitura cadastral do paciente: `PatientReadPort/Service` + `PdoPatientReadRepository`;
- histórico de recepção: `PatientReceptionHistoryReadPort/Service` + repositório PDO;
- criação de aba: `PatientTabCommandPort/Service` + repositório PDO transacional;
- atualização de contato: `PatientContactCommandPort/Service` + repositório PDO transacional;
- recebimento financeiro: `PatientRevenueReceiptPort/Service` + repositório PDO transacional;
- fronteira financeira de persistência: `FinancialDataPort/Service` aceita operações catalogadas e o Runtime financeiro não contém SQL, PDO ou helpers; 12 sequências `atomic()` ainda aguardam extração semântica;
- fronteira de identidade de persistência: SecurityAccess, AuthOnboarding e UsersPermissions delegam cadastro, onboarding, perfil, credenciais e cargos a Application Services sobre `IdentityDataService`; SQL, PDO e transações ficam fora do Runtime;
- fronteira operacional de persistência: Tarefas, Agenda, Pacientes, Documentos, Leads, Maestro, auditoria, assinatura e instalação usam serviços semânticos sobre `OperationalUseCaseService`; SQL, PDO e transações ficam fora do Runtime;
- view de contato: `Presentation/Patients/PatientContactView.php`.
- contrato de testes de Application: 49 casos críticos de 30 services estão caracterizados sem banco e ligados a 15 ports, com o plumbing genérico distinguido dos casos semânticos.
- consolidação de performance: 11 read models críticos possuem budgets MySQL reais para `SELECT`, `INSERT`, `UPDATE`, `DELETE` e `REPLACE`, sem gates frágeis baseados em tempo absoluto.

## Regras de localização

- SQL/PDO somente em Infrastructure;
- HTML/HTTP em Presentation ou nos entrypoints classificados;
- coordenação de caso de uso em Application;
- regra de negócio pura em Domain;
- invariantes transversais e decisões canônicas em Core;
- wiring concreto somente nos composition roots explicitamente enumerados;
- nenhum path histórico removido cria exceção a essas regras.

## Limites atuais

A baseline exige 100% de classificação dos PHP versionados, pelo menos 278 unidades nativas, no máximo 21 entrypoints/ferramentas procedurais não nativos e `compatibility_boundaries=[]`. SQL de negócio, PDO direto, adapters concretos fora dos roots e transações de caso de uso estão zerados. As 615 referências diretas a Infrastructure, 784 chamadas genéricas e 39 input adapters acima de 500 linhas estão explicitamente congelados para redução monotônica. `tools/runtime-input-boundary-check` complementa os contratos anteriores sem transformar Composition em exceção livre.
