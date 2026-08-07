# Auditoria SOLID do Prontoo

## Estado final

A estrutura interna PHP do Prontoo é protegida pelo contrato executável `solid-structural-contract-v3`. O gate oficial é `php tools/solid-audit --strict`, executado pelo `Architecture Contract` em toda alteração. A conclusão exige simultaneamente **zero achados objetivos** e **zero hotspots acionáveis**.

A auditoria cobre os arquivos PHP versionados de `app/`, exceto a configuração de ambiente, e separa duas superfícies: código arquitetural nativo e fronteiras globais de compatibilidade. As fronteiras globais não pertencem ao núcleo interno e só podem delegar para uma unidade namespaced; regra de negócio, SQL, estado HTTP e apresentação não podem permanecer nelas.

O contrato `native-unit-contract-v1`, executado no mesmo workflow, complementa a auditoria estrutural exigindo que todo arquivo classificado como nativo declare ao menos um `class`, `interface`, `trait` ou `enum` nomeado. Arquivo apenas com namespace não conta como unidade arquitetural. O mesmo contrato verifica decomposições pós-baseline declaradas em `native_consolidation_path_decompositions`: a origem deve ter desaparecido e todos os destinos nativos devem existir e declarar tipos nomeados.

## Aplicação dos princípios

**SRP.** Core, Domain e Application não podem acessar estado HTTP. Persistência é confinada a Infrastructure. Unidades internas são namespaced e não expõem funções globais. Superfícies amplas fora do Composition Root são tratadas como hotspot e bloqueiam o modo estrito.

**OCP.** Catálogos e variações funcionais utilizam providers, registries, policies ou estratégias. Condicionais extensas em Core, Domain ou Application são hotspots bloqueantes. Wiring e dispatch do Composition Root são deliberadamente excluídos dessa heurística: conhecer implementações concretas e selecionar adapters é a responsabilidade própria da camada de composição, e classificá-la como violação OCP inverteria o propósito do composition root.

**LSP.** Consumidores internos não podem decidir comportamento verificando subtipos PDO concretos. A substituição ocorre pelos contratos usados pelos casos de uso.

**ISP.** Ports permanecem orientados ao caso de uso. Interfaces internas com superfície superior ao limite do contrato são achados objetivos bloqueantes.

**DIP.** As dependências apontam para dentro. Core depende apenas de Core; Domain pode depender de Core e Domain; Application pode depender de Core, Domain e Application; Infrastructure pode depender das abstrações internas de Core, Domain e Application; Presentation pode depender de Core, Domain e Application. Runtime/Composition é a única camada autorizada a conhecer e conectar todas as camadas. O auditor rejeita dependências que apontem para fora, como Core, Domain ou Application dependendo de Infrastructure, e rejeita instanciação de adapters concretos fora da composição.

## Refatorações concluídas

1. `ModuleLoader` foi reduzido a fachada de composição; política de boot, catálogo, loading e wiring foram separados.
2. A autorização foi convertida em registry extensível de `ActionDefinitionSource`, preservando 157 contratos fail-closed.
3. `Runner` foi decomposto em catálogo de rotas, responder JSON, boot/maintenance e compositions de Patients/Financial.
4. Os módulos procedurais históricos foram migrados para classes namespaced por responsabilidade; as funções globais restantes são somente compatibility adapters.
5. O Core passou a receber contexto explicitamente. Estado de sessão/request foi deslocado para Runtime; `PiIntegrity` e `AuditChain` persistentes foram deslocados para Infrastructure.
6. `PiTime` foi decomposto em política de schema, normalização de query e conversão temporal; verificação temporal PDO foi deslocada para Infrastructure.
7. `SqlExpression` foi decomposto em scanner lexical, parser de mutações e analisador de predicados.
8. Documents foi dividido em políticas de identificador, tipo, template e HTML.
9. Audit Activity foi dividido em políticas de texto, alvo, registro, taxonomia, apresentação, escrita e documentos.
10. A consolidação `1.8.7.1` removeu seis tombstones namespace-only deixados por migrações ou decomposições já concluídas: três antigos paths pertencentes à baseline PHP 8.4 e três containers criados depois dessa baseline e esvaziados durante a decomposição final de Audit Activity/Documents.

## Métricas protegidas

Após a consolidação da baseline estrutural, o contrato registra:

- 100% de classificação arquitetural;
- `native_files_min = 278` unidades nativas efetivas;
- `transitional_files_max = 51`, limitado às fronteiras de compatibilidade e entrypoints não namespaced;
- zero arquivos nativos vazios;
- zero funções globais em arquivos nativos;
- zero achados objetivos no auditor SOLID;
- zero hotspots acionáveis no auditor SOLID.

O valor anterior de 284 incluía seis arquivos namespace-only sem implementação: três preservados por limitação da baseline PHP 8.4 e três deixados após a decomposição final de Audit Activity/Documents. A correção para 278 não representa regressão arquitetural: nenhuma implementação executável foi removida; apenas a contagem artificial foi eliminada. A partir de `1.8.7.1`, a quantidade de unidades nativas efetivas não pode diminuir e o teto de compatibilidade não pode aumentar.

Novas funcionalidades devem nascer diretamente em unidades nativas; compatibility adapters existem somente para preservar a API histórica enquanto houver consumidores globais.

## Migrações históricas PHP 8.4

`version.json` é a fonte canônica de `php84_baseline_path_migrations`. Esse contrato cobre somente arquivos presentes na auditoria integral `1.8.6.1`. Um arquivo dessa baseline só pode desaparecer quando houver destino explícito, rastreado e atual. O contrato falha se a origem continuar como tombstone, se o destino estiver ausente ou se a origem declarada não pertencer à baseline histórica.

As três migrações históricas consolidadas são:

- `app/Core/Database/SqlScopeGuard.php` → `app/Core/Invariant/Tenant/SqlScopeGuard.php`;
- `app/Core/Integrity/AuditChain.php` → `app/Infrastructure/Audit/AuditChain.php`;
- `app/Core/Integrity/PiIntegrity.php` → `app/Infrastructure/Integrity/PiIntegrity.php`.

## Decomposições nativas pós-baseline

Os três containers esvaziados no PR #172 não existiam na baseline `1.8.6.1`, por isso pertencem a um contrato distinto, `native_consolidation_path_decompositions`, verificado por `tools/native-unit-check`:

- `app/Domain/Legacy/AuditActivity/AuditActivityDomainOperations01.php` → policies de copy, target, record, taxonomy e value;
- `app/Domain/Legacy/AuditActivity/AuditActivityDomainOperations02.php` → policies de display, target, write e documentos;
- `app/Domain/Legacy/Documents/DocumentsDomainOperations01.php` → policies de identifier, type, template e HTML.

## Regra de conclusão

A migração é considerada consolidada quando o `Architecture Contract` passa com `tools/solid-audit --strict` e `tools/native-unit-check`, além dos contratos PHP 8.4, versão, documentação, segurança, arquitetura, schema e instalador. Um PR que reintroduza dependência invertida, persistência na camada errada, estado HTTP no núcleo, função global com lógica, interface ampla, unidade nativa vazia, decomposição declarada inconsistente ou hotspot SRP/OCP acionável falha antes do merge.
