# Camadas

A classificação normativa está em `Prontoo\Core\Architecture\LayerMap`. O nome físico do diretório é importante, e as exceções de composition root são resolvidas exclusivamente pelo mapa executável.

## Core

Contém invariantes, políticas canônicas, integridade estrutural, tempo, escopo, workflows e decisões internas estáveis. Depende somente de `Core`.

Exemplos: `Core/Invariant`, `Core/Temporal`, políticas de banco e arquitetura.

## Domain

Contém conceitos, validações e regras de negócio independentes de HTTP e persistência. Pode depender de `Core` e `Domain`.

Domain fornece valores, estados e políticas puras; não renderiza `SELECT`, cláusulas ou fragmentos próprios do dialeto MySQL. A tradução dessas decisões para consultas pertence a Infrastructure.

Não existe camada ou namespace ativo `Domain/Legacy`. Origens históricas removidas podem constar apenas nos mapas explícitos de migração e auditorias de baseline.

## Application

Coordena casos de uso e define portas. Pode depender de `Core`, `Domain` e `Application`.

Abriga também o catálogo declarativo de autorização: fontes de definições, registry, requirements e definições por capacidade. Não implementa persistência nem HTML.

## Infrastructure

Implementa portas e detalhes externos: PDO, credenciais vivas, auditoria, integridade, armazenamento, cache e integrações. Pode depender de `Core`, `Domain`, `Application` e da própria `Infrastructure`.

Catálogos e renderizadores de consulta, inclusive filtros de leads, auditoria, diretório de pacientes e exclusão do consultório-modelo, vivem nesta camada.

Não existe camada ou namespace ativo `Infrastructure/Legacy`.

## Presentation

Interpreta HTTP, valida forma, converte entradas, chama casos de uso e renderiza respostas. Pode depender de `Core`, `Domain`, `Application` e `Presentation`, mas não de `Infrastructure`.

As antigas fachadas em `app/Admin`, `app/Auth`, `app/Pages` e `app/Ui` foram removidas ou decompostas; componentes ativos ficam nas unidades nativas de Presentation e Runtime correspondentes.

## Composition e Runtime

É a fronteira autorizada a conhecer todas as camadas. Isso não concede liberdade para implementar persistência ou caso de uso: sua responsabilidade normativa é wiring, bootstrap, catálogo/carregamento de módulos, dispatch, adaptação de entrada e coordenação fina de prontidão/manutenção.

`LayerMap` subdivide a métrica de Composition em roots explícitos, bootstrap Runtime, input adapters, comissionamento, ferramentas de qualidade e entrypoints. Os cinco composition roots concretos são enumerados por arquivo; somente eles podem instanciar adapters concretos. Os demais arquivos Runtime não se tornam composition roots por estarem no diretório, e a classificação como input adapter não afirma que o arquivo já seja fino.

`app/Runtime` não contém SQL de negócio, acesso direto a PDO ou adapters concretos fora dos composition roots. A correção do detector tornou visíveis 33 chamadas `atomic()` que ainda coordenam casos de uso no Runtime. `tools/runtime-boundary-check` congela essa dívida para redução monotônica; `tools/runtime-input-boundary-check` faz o mesmo com dependências diretas a Infrastructure, gateway genérico e faixas de tamanho dos input adapters.

## Paths históricos

Paths removidos como `app/Support/*`, `app/Admin/*` ou antigas fachadas de domínio podem permanecer em `php84_baseline_path_migrations`, `architecture_source_path_migrations`, `removed_legacy_files` e auditorias históricas. Essas referências servem apenas para rastreabilidade e resolução de origem histórica; não constituem componentes ativos.

## Migração monotônica

A baseline arquitetural exige classificação de 100% dos PHP versionados, pelo menos 278 unidades nativas e no máximo 21 entrypoints/ferramentas procedurais não nativos. Alterações devem manter ou melhorar esses limites. `compatibility_boundaries` deve permanecer vazio.
