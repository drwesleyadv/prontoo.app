# Auditoria SOLID do Prontoo

## Estado final

A estrutura interna PHP do Prontoo é protegida pelo contrato executável `solid-structural-contract-v3`. O gate oficial é `php tools/solid-audit --strict`, executado pelo `Architecture Contract` em toda alteração. A conclusão exige simultaneamente **zero achados objetivos** e **zero hotspots acionáveis**.

A auditoria cobre os arquivos PHP versionados de `app/`, exceto a configuração de ambiente. A árvore atual não possui fachadas globais de compatibilidade executável nem namespaces ativos `/Legacy/`; entrypoints e ferramentas procedurais fora da contagem nativa são tratados separadamente pelo contrato arquitetural.

O contrato `native-unit-contract-v1`, executado no mesmo workflow, exige que todo arquivo classificado como nativo declare ao menos um `class`, `interface`, `trait` ou `enum` nomeado. Arquivo apenas com namespace não conta como unidade arquitetural. O mesmo contrato verifica decomposições pós-baseline declaradas em `native_consolidation_path_decompositions`: a origem deve ter desaparecido e todos os destinos nativos devem existir e declarar tipos nomeados.

## Aplicação dos princípios

**SRP.** Core, Domain e Application não podem acessar estado HTTP. Persistência é confinada a Infrastructure. Unidades internas são namespaced e não expõem funções globais. Superfícies amplas fora do Composition Root são tratadas como hotspot e bloqueiam o modo estrito.

**OCP.** Catálogos e variações funcionais utilizam providers, registries, policies ou estratégias. Condicionais extensas em Core, Domain ou Application são hotspots bloqueantes. Wiring e dispatch do Composition Root são deliberadamente excluídos dessa heurística.

**LSP.** Consumidores internos não podem decidir comportamento verificando subtipos PDO concretos. A substituição ocorre pelos contratos usados pelos casos de uso.

**ISP.** Ports permanecem orientados ao caso de uso. Interfaces internas com superfície superior ao limite do contrato são achados objetivos bloqueantes.

**DIP.** As dependências apontam para dentro. Core depende apenas de Core; Domain pode depender de Core e Domain; Application pode depender de Core, Domain e Application; Infrastructure pode depender das abstrações internas de Core, Domain e Application; Presentation pode depender de Core, Domain e Application. Runtime/Composition é a única camada autorizada a conhecer e conectar todas as camadas.

## Refatorações consolidadas

1. boot, catálogo, loading e wiring foram separados em unidades nativas de Runtime;
2. autorização foi convertida em registry extensível de `ActionDefinitionSource`, preservando 157 contratos fail-closed;
3. `Runner` foi decomposto em catálogo de rotas, responder JSON, boot/maintenance e compositions de features;
4. módulos procedurais históricos foram migrados para classes namespaced por responsabilidade e as fachadas globais de compatibilidade foram removidas;
5. Core passou a receber contexto explicitamente; estado de sessão/request foi deslocado para Runtime e persistência para Infrastructure;
6. `PiTime`, `SqlExpression`, Documents e Audit Activity foram decompostos em políticas/unidades coesas;
7. a consolidação `1.8.7.1` removeu seis tombstones namespace-only;
8. a materialização zero-legacy removeu as últimas 22 fachadas executáveis e 1.289 funções globais delegadoras, mantendo composição nativa via registries explícitos;
9. as fases 11–15 removeram o `OperationGateway`; o contrato atual proíbe o arquivo e fixa `max_invoke_calls = 0`.

## Métricas protegidas

A baseline corrente registra:

- 100% de classificação arquitetural;
- `native_files_min = 278` unidades nativas efetivas;
- `transitional_files_max = 21`, limitado a entrypoints e ferramentas procedurais não classificados como unidades nativas;
- `compatibility_boundaries = []`;
- zero paths PHP ativos sob `/Legacy/`;
- zero arquivos nativos vazios;
- zero funções globais em arquivos nativos;
- zero achados objetivos no auditor SOLID;
- zero hotspots acionáveis no auditor SOLID.
- zero chamadas ou arquivos do `OperationGateway`.

A persistência de negócio do Runtime é acompanhada pelo contrato tokenizado `tools/runtime-boundary-check`. O prefixo financeiro foi encerrado na Fase 17; Segurança, Autenticação/Onboarding e Usuários/Permissões foram encerrados na Fase 18; a Fase 19 zerou globalmente SQL de negócio, PDO direto, adapters concretos fora dos composition roots e transações de caso de uso. O dispatch e a atomicidade estrutural do executor guardado permanecem inventariados e não podem crescer.

A quantidade de unidades nativas efetivas não pode diminuir e o teto corrente de arquivos não nativos não pode aumentar. Novas funcionalidades devem nascer diretamente em unidades nativas.

## Rastreamento histórico

`version.json` mantém `php84_baseline_path_migrations` e `architecture_source_path_migrations` para provar onde implementações históricas removidas passaram a viver. `app/architecture.manifest.json` mantém `removed_legacy_files` como inventário de origens eliminadas. Essas referências são metadados de rastreabilidade; não participam do runtime.

As decomposições pós-baseline permanecem declaradas em `native_consolidation_path_decompositions` e verificadas por `tools/native-unit-check`.

## Regra de conclusão

A arquitetura é considerada consolidada quando o `Architecture Contract` passa com `tools/solid-audit --strict`, `tools/native-unit-check`, `tools/architecture-check.php` e os contratos PHP 8.4, versão, documentação e segurança. O gate de consolidação também exige `compatibility_boundaries=[]`, componentes ativos apontando apenas para arquivos existentes e não removidos, ausência de artefatos temporários da migração e ausência de paths PHP atuais sob `/Legacy/`.
