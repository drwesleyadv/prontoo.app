# Mapa de responsabilidades

Este documento descreve **onde a responsabilidade vive hoje**. Os documentos `phase-*` permanecem como registro histórico da migração; não devem ser usados como mapa da árvore atual.

## Mapa canônico

| Responsabilidade | Local atual |
|---|---|
| invariantes de contexto, mutação, tenant, SQL e workflow | `app/Core/Invariant` |
| políticas temporais | `app/Core/Temporal` |
| arquitetura e classificação | `app/Core/Architecture` |
| regras puras de identidade/paciente | `app/Domain/Identity`, `app/Domain/Patients` |
| regras históricas já classificadas como domínio | `app/Domain/Legacy/*` |
| casos de uso e portas | `app/Application/*` |
| contratos e catálogo declarativo de autorização | `app/Application/Authorization/*` |
| PDO e repositórios | `app/Infrastructure/*` |
| auditoria/integridade concreta | `app/Infrastructure/Audit`, `app/Infrastructure/Integrity` |
| adaptadores históricos de persistência | `app/Infrastructure/Legacy/*` |
| HTTP, views e JSON | `app/Presentation/*` |
| apresentação histórica isolada | `app/Presentation/Legacy/*` |
| composição de módulos | `app/Runtime/Modules/*` |
| composição do catálogo de autorização | `app/Runtime/Authorization/*` |
| prontidão e manutenção | `app/Runtime/Boot/*` |
| roteamento | `app/Runtime/Routing/*` |
| wiring de Pacientes e Financeiro | `app/Runtime/Patients/*`, `app/Runtime/Financial/*` |
| runtime histórico compatível | `app/Runtime/Legacy/*` |
| fachada de composição global | `app/Support/ModuleLoader.php` |
| entradas web | `br`, `public` |
| execução secundária | `cron/maestro.php` |

## Autorização

O catálogo não é mais uma tabela monolítica no núcleo. `Application/Authorization` define fontes, collection/registry, requirements e definições coesas por família (`Auth`, `Patient`, `Scheduling`, `DocumentTask`, `Workforce`, `Financial`, `Admin`). `Runtime/Authorization/ActionCatalogComposition.php` faz a composição. A entrada pública permanece `LayeredKernel::enforceAction`.

## Runtime

A composição deixou de concentrar boot, catálogo, loading e wiring numa única unidade. `RuntimeBootPolicy`, `RuntimeModuleCatalog`, `RuntimeModuleLoader` e `RuntimeModuleComposition` possuem responsabilidades separadas. `RuntimeBootCoordinator` separa prontidão mínima de manutenção profunda. `RouteCatalog`, `JsonResponder`, composições de feature e `Runner` separam roteamento, resposta e execução.

## Compatibilidade

Fachadas globais e namespaces `Legacy` existem para preservar chamadas e comportamento durante a migração. Eles não são destino preferencial para lógica nova. Nova regra deve nascer na camada correta; alterações em código histórico devem, quando possível, reduzir a responsabilidade da fronteira.

## Casos de uso nativos já consolidados

- leitura cadastral do paciente: `PatientReadPort/Service` + `PdoPatientReadRepository`;
- histórico de recepção: `PatientReceptionHistoryReadPort/Service` + repositório PDO;
- criação de aba: `PatientTabCommandPort/Service` + repositório PDO transacional;
- atualização de contato: `PatientContactCommandPort/Service` + repositório PDO transacional;
- recebimento financeiro: `PatientRevenueReceiptPort/Service` + repositório PDO transacional;
- view de contato: `Presentation/Patients/PatientContactView.php`.

## Regras de localização

- SQL/PDO somente em Infrastructure;
- HTML/HTTP somente em Presentation ou borda compatível classificada como Presentation;
- coordenação de caso de uso em Application;
- regra de negócio pura em Domain;
- invariantes transversais e decisões canônicas em Core;
- wiring concreto somente em Composition/Runtime;
- `Legacy` nunca cria exceção a essas regras.

## Limites de migração

A baseline consolidada exige 100% de classificação dos PHP versionados, pelo menos 278 unidades nativas e no máximo 51 fronteiras transitórias. `tools/architecture-check.php` e `tools/solid-audit` são os contratos de regressão.
