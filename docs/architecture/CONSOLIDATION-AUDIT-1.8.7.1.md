# Auditoria de consolidação — 1.8.7.1

## Escopo

Esta auditoria revisa o estado consolidado de `prontoo` imediatamente após a conclusão da migração SOLID e do gate `tools/solid-audit --strict`. O objetivo é verificar se a árvore final, os contratos de CI, a baseline PHP 8.4, os manifests e a documentação representam de forma fiel a arquitetura executável, sem resíduos artificiais de migração.

## Achados

### 1. Seis tombstones nativos inflavam a métrica estrutural

A primeira revisão identificou três paths históricos contendo apenas `declare(strict_types=1)` e namespace:

- `app/Core/Database/SqlScopeGuard.php`;
- `app/Core/Integrity/AuditChain.php`;
- `app/Core/Integrity/PiIntegrity.php`.

As implementações canônicas já estavam, respectivamente, em:

- `app/Core/Invariant/Tenant/SqlScopeGuard.php`;
- `app/Infrastructure/Audit/AuditChain.php`;
- `app/Infrastructure/Integrity/PiIntegrity.php`.

Depois que `native-unit-contract-v1` foi integrado à CI, a própria auditoria dinâmica encontrou mais três stubs namespace-only deixados pela decomposição final do PR #172:

- `app/Domain/Legacy/AuditActivity/AuditActivityDomainOperations01.php`;
- `app/Domain/Legacy/AuditActivity/AuditActivityDomainOperations02.php`;
- `app/Domain/Legacy/Documents/DocumentsDomainOperations01.php`.

O histórico do PR #172 confirma que esses três arquivos continham implementações antes da decomposição e foram esvaziados quando suas responsabilidades migraram para policies coesas. Os destinos são:

- `AuditActivityDomainOperations01.php` → `AuditCopyPolicy`, `AuditTargetPolicy`, `AuditRecordPolicy`, `ActivityTaxonomy` e `ActivityValuePolicy`;
- `AuditActivityDomainOperations02.php` → `ActivityDisplayPolicy`, `ActivityTargetPolicy`, `AuditWritePolicy` e `AuditDocumentPolicy`;
- `DocumentsDomainOperations01.php` → `DocumentIdentifierPolicy`, `DocumentTypePolicy`, `DocumentTemplatePolicy` e `DocumentHtmlPolicy`.

Os seis arquivos eram classificados como nativos apesar de não constituírem unidades arquiteturais. Resultado: `native_files_min = 284` estava formalmente verde, mas numericamente inflado.

**Correção:** remoção dos seis tombstones e estabelecimento da baseline efetiva em `native_files_min = 278`.

### 2. A baseline PHP 8.4 não possuía migração explícita de path

`tools/php84-runtime-contract-check` rejeitava qualquer arquivo da auditoria `1.8.6.1` que deixasse de existir, mesmo quando sua implementação tivesse sido legitimamente movida ou decomposta. Isso incentivava a manutenção de paths vazios.

**Correção:** `version.json` passa a declarar `php84_baseline_path_migrations`. O contrato aceita destino único ou lista de destinos e exige que a origem pertença à baseline histórica, esteja ausente da árvore atual e que todos os destinos declarados sejam arquivos PHP rastreados. Origem ainda presente como tombstone ou qualquer destino ausente falham o gate.

### 3. Não havia veto explícito a unidade nativa sem tipo

O contrato arquitetural exigia namespace e ausência de funções globais nos arquivos nativos, mas não exigia que o arquivo declarasse efetivamente um tipo.

**Correção:** criação de `tools/native-unit-check` com política `native-unit-contract-v1`, integrada ao `Architecture Contract`. Todo arquivo nativo deve declarar `class`, `interface`, `trait` ou `enum` nomeado.

O primeiro run do novo gate foi deliberadamente tratado como parte da auditoria, não como mera falha de CI. Ele revelou os três tombstones adicionais de Audit Activity/Documents, que foram então rastreados ao histórico do PR #172 e removidos.

### 4. A documentação DIP descrevia a direção ao contrário

`docs/architecture/SOLID-AUDIT.md` afirmava que a auditoria rejeitava dependências de Infrastructure em Core, Domain e Application. O código executável permite corretamente que Infrastructure dependa das abstrações internas e rejeita dependências para fora, como Core, Domain ou Application apontando para Infrastructure.

**Correção:** documentação reescrita para espelhar exatamente `LayerMap::dependencyAllowed()` e `tools/solid-audit`.

### 5. Release e manifests estavam semanticamente defasados

A árvore em 07/08/2026 já continha toda a migração SOLID, mas `version.json`, `app/update.manifest.json`, `app/architecture.manifest.json`, `app/prontoo.php`, `br/index.php` e `CHANGELOG.md` ainda descreviam a release `1.8.6.2` de telemetria publicada em 06/08/2026.

**Correção:** consolidação como `1.8.7.1` e transformação do `Documentation Contract` em reconciliador canônico de release. `version.json` passa a ser a fonte para versão, build, fallbacks, limites arquiteturais e metadados dos manifests; o changelog é materializado a partir do bloco canônico `changelog` quando a release ainda não está registrada.

## Invariantes preservados

A consolidação não altera banco de dados, schema, rotas funcionais, comportamento de negócio, layout ou assets públicos. Os destinos reais dos seis paths removidos já continham as implementações executáveis antes desta auditoria.

Permanecem obrigatórios:

- PHP exclusivamente 8.4;
- classificação arquitetural em 100%;
- `transitional_files_max = 51`;
- `tools/solid-audit --strict` com zero achados objetivos e zero hotspots acionáveis;
- `tools/native-unit-check` sem unidades nativas vazias;
- contratos de segurança, schema, instalador e documentação.

## Baseline consolidada

A baseline efetiva da release `1.8.7.1` é:

- versão: `1.8.7.1`;
- `architecture_policy`: `php-layered-invariants-v2`;
- `native_unit_policy`: `native-unit-contract-v1`;
- `solid_policy`: `solid-structural-contract-v3`;
- `architecture_native_files_min`: `278`;
- `architecture_transitional_files_max`: `51`;
- tombstones de migração/decomposição: `0`.

A redução de 284 para 278 corrige exclusivamente a contagem artificial de seis arquivos namespace-only e não remove nenhuma implementação executável. A nova baseline passa a medir unidades reais, não paths históricos vazios.
