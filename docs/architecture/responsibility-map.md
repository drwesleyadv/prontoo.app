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
| registro persistente de incidentes de escopo | `app/Application/SecurityAccess/SecurityIncident*`, `app/Infrastructure/SecurityAccess/PdoSecurityIncidentRepository.php` |
| ciclo persistente de cadastro MFA | `app/Application/SecurityAccess/MfaRecord*`, `app/Infrastructure/SecurityAccess/PdoMfaRecordRepository.php` |
| auditoria/integridade concreta | `app/Infrastructure/Audit`, `app/Infrastructure/Integrity` |
| HTTP, views e JSON | `app/Presentation/*` |
| composição de módulos | `app/Runtime/Modules/*` |
| composição do catálogo de autorização | `app/Runtime/Authorization/*` |
| prontidão e manutenção | `app/Runtime/Boot/*` |
| roteamento | `app/Runtime/Routing/*` |
| wiring de features | `app/Runtime/*` |
| inventário monotônico da fronteira Runtime | `app/runtime.boundary-contract.json`, `app/runtime.boundary-baseline.json`, `tools/runtime-boundary-check` |
| entradas web | `index.php`, `install.php`, `br`, `public` |
| execução secundária | `cron/maestro.php` |

## Autorização

O catálogo não é mais uma tabela monolítica no núcleo. `Application/Authorization` define fontes, collection/registry, requirements e definições coesas por família (`Auth`, `Patient`, `Scheduling`, `DocumentTask`, `Workforce`, `Financial`, `Admin`). `Runtime/Authorization/ActionCatalogComposition.php` faz a composição. A entrada canônica permanece `LayeredKernel::enforceAction`.

## Runtime

A composição deixou de concentrar boot, catálogo, loading e wiring numa única unidade. `RuntimeBootPolicy`, `RuntimeModuleCatalog`, `RuntimeModuleLoader` e `RuntimeModuleComposition` possuem responsabilidades separadas. `RuntimeBootCoordinator` separa prontidão mínima de manutenção profunda. `RouteCatalog`, `JsonResponder`, composições de feature e `Runner` separam roteamento, resposta e execução.

## Estado pós-zero-legacy

Não há fachadas globais ou namespaces `/Legacy/` executáveis. Paths históricos removidos permanecem somente nos mapas explícitos de migração, em `removed_legacy_files`, auditorias de baseline e contratos de rastreabilidade. Nova lógica deve nascer diretamente na unidade nativa da camada correta.

## Casos de uso nativos consolidados

- leitura cadastral do paciente: `PatientReadPort/Service` + `PdoPatientReadRepository`;
- histórico de recepção: `PatientReceptionHistoryReadPort/Service` + repositório PDO;
- criação de aba: `PatientTabCommandPort/Service` + repositório PDO transacional;
- atualização de contato: `PatientContactCommandPort/Service` + repositório PDO transacional;
- recebimento financeiro: `PatientRevenueReceiptPort/Service` + repositório PDO transacional;
- fronteira financeira fechada: `FinancialDataPort/Service` aceita somente operações semânticas catalogadas; `FinancialComposition` liga o adapter ao executor guardado e o Runtime financeiro não contém SQL, PDO, helpers ou controle transacional;
- fronteira de identidade fechada: SecurityAccess, AuthOnboarding e UsersPermissions usam operações nomeadas de `IdentityDataService`; PDO, SQL, locks de persistência e transações ficam em Infrastructure, enquanto request, sessão, cookies e respostas HTTP permanecem no Runtime;
- fronteira operacional fechada: Tarefas, Agenda, Pacientes, Documentos, Leads, Maestro e módulos residuais usam serviços por escopo e operações nomeadas; SQL, PDO e atomicidade de casos de uso ficam fora do Runtime;
- view de contato: `Presentation/Patients/PatientContactView.php`.

## Regras de localização

- SQL/PDO somente em Infrastructure;
- HTML/HTTP em Presentation ou nos entrypoints classificados;
- coordenação de caso de uso em Application;
- regra de negócio pura em Domain;
- invariantes transversais e decisões canônicas em Core;
- wiring concreto somente em Composition/Runtime;
- nenhum path histórico removido cria exceção a essas regras.

## Limites atuais

A baseline consolidada exige 100% de classificação dos PHP versionados, pelo menos 278 unidades nativas, no máximo 21 entrypoints/ferramentas procedurais não nativos e `compatibility_boundaries=[]`. SQL de negócio, PDO direto, adapters concretos fora dos composition roots e transações de caso de uso estão zerados no Runtime; os contadores estruturais residuais do executor guardado permanecem inventariados e monotônicos. `tools/architecture-check.php`, `tools/runtime-boundary-check`, `tools/native-unit-check` e `tools/solid-audit --strict` são contratos permanentes de regressão.
