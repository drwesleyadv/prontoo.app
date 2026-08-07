# Auditoria SOLID do Prontoo

## Estado final

A estrutura interna PHP do Prontoo é protegida pelo contrato executável `solid-structural-contract-v3`. O gate oficial é `php tools/solid-audit --strict`, executado pelo `Architecture Contract` em toda alteração. A conclusão exige simultaneamente **zero achados objetivos** e **zero hotspots acionáveis**.

A auditoria cobre todos os arquivos PHP versionados de `app/` e separa duas superfícies: código arquitetural nativo e fronteiras globais de compatibilidade. As fronteiras globais não pertencem ao núcleo interno e só podem delegar para uma unidade namespaced; regra de negócio, SQL, estado HTTP e apresentação não podem permanecer nelas.

## Aplicação dos princípios

**SRP.** Core, Domain e Application não podem acessar estado HTTP. Persistência é confinada a Infrastructure. Unidades internas são namespaced e não expõem funções globais. Superfícies amplas fora do Composition Root são tratadas como hotspot e bloqueiam o modo estrito.

**OCP.** Catálogos e variações funcionais utilizam providers, registries, policies ou estratégias. Condicionais extensas em Core, Domain ou Application são hotspots bloqueantes. Wiring e dispatch do Composition Root são deliberadamente excluídos dessa heurística: conhecer implementações concretas e selecionar adapters é a responsabilidade própria da camada de composição, e classificá-la como violação OCP inverteria o propósito do composition root.

**LSP.** Consumidores internos não podem decidir comportamento verificando subtipos PDO concretos. A substituição ocorre pelos contratos usados pelos casos de uso.

**ISP.** Ports permanecem orientados ao caso de uso. Interfaces internas com superfície excessiva são reportadas como hotspot bloqueante.

**DIP.** A direção de dependências é Core/Domain → Application, com Infrastructure e Presentation apontando para dentro. Adaptadores concretos só são instanciados no Runtime/Composition. O auditor rejeita dependência de Infrastructure em Core, Domain e Application e rejeita instanciação concreta fora da composição.

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

## Métricas protegidas

Na conclusão desta migração, o contrato registra:

- 100% de classificação arquitetural;
- `native_files_min = 284`;
- `transitional_files_max = 51`, agora limitado às fronteiras de compatibilidade e entrypoints não namespaced;
- zero funções globais em arquivos nativos;
- zero achados objetivos no auditor SOLID;
- zero hotspots acionáveis no auditor SOLID.

O teto de compatibilidade não pode aumentar e a quantidade de arquivos nativos não pode diminuir. Novas funcionalidades devem nascer diretamente em unidades nativas; compatibility adapters existem somente para preservar a API histórica enquanto houver consumidores globais.

## Regra de conclusão

A migração é considerada concluída quando o `Architecture Contract` passa com `tools/solid-audit --strict`, além dos contratos PHP 8.4, versão, documentação, segurança, arquitetura, schema e instalador. Um PR que reintroduza dependência invertida, persistência na camada errada, estado HTTP no núcleo, função global com lógica, interface ampla ou hotspot SRP/OCP acionável falha antes do merge.
