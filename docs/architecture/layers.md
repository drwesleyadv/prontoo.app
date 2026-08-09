# Camadas

A classificação normativa está em `Prontoo\Core\Architecture\LayerMap`. O nome físico do diretório é importante, e as exceções de composition root são resolvidas exclusivamente pelo mapa executável.

## Core

Contém invariantes, políticas canônicas, integridade estrutural, tempo, escopo, workflows e decisões internas estáveis. Depende somente de `Core`.

Exemplos: `Core/Invariant`, `Core/Temporal`, políticas de banco e arquitetura.

## Domain

Contém conceitos, validações e regras de negócio independentes de HTTP e persistência. Pode depender de `Core` e `Domain`.

Não existe camada ou namespace ativo `Domain/Legacy`. Origens históricas removidas podem constar apenas nos mapas explícitos de migração e auditorias de baseline.

## Application

Coordena casos de uso e define portas. Pode depender de `Core`, `Domain` e `Application`.

Abriga também o catálogo declarativo de autorização: fontes de definições, registry, requirements e definições por capacidade. Não implementa persistência nem HTML.

## Infrastructure

Implementa portas e detalhes externos: PDO, credenciais vivas, auditoria, integridade, armazenamento, cache e integrações. Pode depender de `Core`, `Domain`, `Application` e da própria `Infrastructure`.

Não existe camada ou namespace ativo `Infrastructure/Legacy`.

## Presentation

Interpreta HTTP, valida forma, converte entradas, chama casos de uso e renderiza respostas. Pode depender de `Core`, `Domain`, `Application` e `Presentation`, mas não de `Infrastructure`.

As antigas fachadas em `app/Admin`, `app/Auth`, `app/Pages` e `app/Ui` foram removidas ou decompostas; componentes ativos ficam nas unidades nativas de Presentation e Runtime correspondentes.

## Composition e Runtime

É a única camada autorizada a conhecer todas as camadas. Faz wiring, bootstrap, catálogo/carregamento de módulos, composição de serviços, dispatch e coordenação de prontidão/manutenção.

`app/Runtime` concentra a composição executável. Front controllers, contratos de bootstrap e arquivos especiais recebem a classificação explicitamente definida por `LayerMap`.

## Paths históricos

Paths removidos como `app/Support/*`, `app/Admin/*` ou antigas fachadas de domínio podem permanecer em `php84_baseline_path_migrations`, `architecture_source_path_migrations`, `removed_legacy_files` e auditorias históricas. Essas referências servem apenas para rastreabilidade e resolução de origem histórica; não constituem componentes ativos.

## Migração monotônica

A baseline arquitetural exige classificação de 100% dos PHP versionados, pelo menos 278 unidades nativas e no máximo 21 entrypoints/ferramentas procedurais não nativos. Alterações devem manter ou melhorar esses limites. `compatibility_boundaries` deve permanecer vazio.
