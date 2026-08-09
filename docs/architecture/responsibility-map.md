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
| auditoria/integridade concreta | `app/Infrastructure/Audit`, `app/Infrastructure/Integrity` |
| HTTP, views e JSON | `app/Presentation/*` |
| composição de módulos | `app/Runtime/Modules/*` |
| composição do catálogo de autorização | `app/Runtime/Authorization/*` |
| prontidão e manutenção | `app/Runtime/Boot/*` |
| roteamento | `app/Runtime/Routing/*` |
| wiring de features | `app/Runtime/*` |
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

A baseline consolidada exige 100% de classificação dos PHP versionados, pelo menos 278 unidades nativas, no máximo 21 entrypoints/ferramentas procedurais não nativos e `compatibility_boundaries=[]`. `tools/architecture-check.php`, `tools/native-unit-check` e `tools/solid-audit --strict` são contratos permanentes de regressão.
