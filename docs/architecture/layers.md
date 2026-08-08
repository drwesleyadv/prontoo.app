# Camadas

A classificação normativa está em `Prontoo\Core\Architecture\LayerMap`. O nome físico do diretório é importante, mas exceções de composição e compatibilidade são resolvidas pelo mapa executável.

## Core

Contém invariantes, políticas canônicas, integridade estrutural, tempo, escopo, workflows e decisões internas estáveis. Depende somente de `Core`.

Exemplos: `Core/Invariant`, `Core/Temporal`, políticas de banco e arquitetura.

## Domain

Contém conceitos, validações e regras de negócio independentes de HTTP e persistência. Pode depender de `Core` e `Domain`.

`app/Domain/Legacy` é domínio histórico já classificado: o sufixo `Legacy` não autoriza acesso a PDO, sessão ou apresentação.

## Application

Coordena casos de uso e define portas. Pode depender de `Core`, `Domain` e `Application`.

Abriga também o catálogo declarativo de autorização: fontes de definições, registry, requirements e definições por capacidade. Não implementa persistência nem HTML.

## Infrastructure

Implementa portas e detalhes externos: PDO, credenciais vivas, auditoria, integridade, armazenamento e integrações. Pode depender de `Core`, `Domain`, `Application` e da própria `Infrastructure`.

`app/Infrastructure/Legacy` contém adaptadores históricos já separados na camada correta.

## Presentation

Interpreta HTTP, valida forma, converte entradas, chama casos de uso e renderiza respostas. Pode depender de `Core`, `Domain`, `Application` e `Presentation`, mas não de `Infrastructure`.

`app/Presentation/Legacy` contém views e operações históricas de apresentação. `app/Admin`, `app/Auth`, `app/Pages` e `app/Ui` são classificados como Presentation enquanto permanecerem como fronteiras compatíveis.

## Composition e Runtime

É a única camada autorizada a conhecer todas as camadas. Faz wiring, bootstrap, catálogo/carregamento de módulos, composição de serviços, dispatch e coordenação de prontidão/manutenção.

`app/Runtime` e `app/Install` são Composition. Também são classificados como Composition os frontais/arquivos explicitamente especiais definidos pelo `LayerMap`, como `Core/Architecture/ArchitectureVerifier.php` e contratos de instalação.

## Diretórios históricos adicionais

`app/Support` e `app/Database` são, por padrão, Infrastructure, exceto os paths explicitamente promovidos a Composition no `LayerMap`. `br` e `public` são Presentation. `tools`, arquivos PHP de raiz e demais entradas de orquestração são Composition.

## Regra para `Legacy`

`Legacy` descreve compatibilidade, não permissão de dependência. Uma unidade `Presentation/Legacy` continua proibida de executar SQL; uma unidade `Domain/Legacy` continua proibida de ler sessão; uma unidade `Infrastructure/Legacy` não decide autorização.

## Migração monotônica

A baseline arquitetural exige classificação de 100% dos PHP versionados, pelo menos 278 unidades nativas e no máximo 51 fronteiras transitórias. Alterações devem manter ou melhorar esses limites.
