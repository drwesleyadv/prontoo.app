# Visão arquitetural

## Estado atual

O Prontoo é um **monólito modular PHP 8.4 em camadas com núcleo de invariantes**. A migração estrutural não depende mais apenas de grandes fachadas históricas: responsabilidades foram decompostas em unidades nativas de `Core`, `Domain`, `Application`, `Infrastructure`, `Presentation` e `Runtime/Composition`, enquanto não há fachadas globais de compatibilidade; apenas entrypoints e ferramentas procedurais permanecem fora da contagem de unidades nativas.

A fonte executável de verdade para classificação e dependências é `app/Core/Architecture/LayerMap.php`; o manifesto arquitetural fixa cobertura de classificação em 100%, piso de unidades nativas e teto de fronteiras transitórias.

## Objetivos

- manter regras de negócio independentes de HTTP e PDO;
- concentrar autorização, isolamento, integridade e prova de mutação;
- manter composition root explícito e carregamento orientado à rota;
- reduzir o caminho crítico de login e requisições normais;
- tornar falhas explícitas, auditáveis e fail-closed;
- preservar rastreabilidade histórica sem reintroduzir compatibilidade executável;
- impedir regressão estrutural por contratos executáveis.

## Camadas

```mermaid
flowchart TD
    P[Presentation] --> A[Application]
    P --> D[Domain]
    P --> C[Core]
    I[Infrastructure] --> A
    I --> D
    I --> C
    A --> D
    A --> C
    D --> C
    R[Runtime / Composition] --> P
    R --> I
    R --> A
    R --> D
    R --> C
```

As setas representam dependências permitidas. `Core` é autocontido; `Domain` não conhece infraestrutura/apresentação nem renderiza consultas; `Application` não conhece adaptadores externos; `Infrastructure` e `Presentation` não dependem entre si. Composition pode conhecer todas as camadas, mas somente os cinco roots enumerados podem instanciar adapters concretos; as demais unidades Runtime são bootstrap ou adapters finos de entrada.

## Núcleo de invariantes

`app/Core/Invariant` contém decisões canônicas de contexto, mutação, SQL, tenant, workflow e prova. A mutação protegida entra por `Prontoo\Core\Invariant\InvariantKernel::guardMutation`. A autorização entra por `Prontoo\Runtime\LayeredKernel::enforceAction`, que compõe catálogo de ações e capacidades com credenciais vivas.

## Runtime e composição

A composição foi decomposta em unidades coesas e perfilada executavelmente pelo `LayerMap`:

- `app/Runtime/Modules/RuntimeBootPolicy.php` — política de boot;
- `RuntimeModuleCatalog.php` — catálogo de módulos;
- `RuntimeModuleLoader.php` — carregamento;
- `RuntimeModuleComposition.php` — wiring concreto;
- `app/Runtime/Routing/RouteCatalog.php` — catálogo de rotas;
- `app/Runtime/Boot/RuntimeBootCoordinator.php` — prontidão mínima e manutenção profunda;
- `app/Runtime/Authorization/ActionCatalogComposition.php` — composição do catálogo de autorização;
- os cinco roots concretos `LayeredKernel`, `PatientComposition`, `FinancialComposition`, `SecurityAccessComposition` e `OperationalComposition`;
- `app/Runtime/Runner.php` — coordenação final do runtime.

Pertencer a `app/Runtime` não transforma uma unidade em root. Controllers e adapters de entrada mantêm request, sessão, cookies, redirects, flash e montagem de resposta; casos de uso e persistência atravessam Application e ports até Infrastructure.

A fachada global `app/Support/ModuleLoader.php` foi removida. Entrypoints e unidades de runtime consomem diretamente `RuntimeModuleComposition`, `RuntimeBootPolicy`, `RouteCatalog`, `JsonResponder` e as composições de feature.

## Prontidão versus manutenção

Login e rotas normais verificam somente contrato de schema e `integrity lightcheck`. O marcador de prontidão é separado do marcador de manutenção profunda. Autotestes pesados, runtime self-check, cleanup e verificações profundas são executados fora do hot path quando explicitamente necessários. Falha de gravabilidade do cache/lock não dispensa os checks mínimos: eles executam sem cache.

## Histórico de migração

A árvore atual não possui camada, namespace ou fachada executável `Legacy`. Os nomes de origem removidos sobrevivem apenas como metadados históricos declarados nos mapas de migração de `version.json`, em `removed_legacy_files`, nas auditorias de baseline e nos contratos que resolvem origem histórica para destino nativo.

Essas referências não participam do dispatch, bootstrap ou composição do runtime. Componentes arquiteturais ativos devem apontar exclusivamente para arquivos existentes e não podem apontar para itens de `removed_legacy_files`; `tools/architecture-check.php` verifica essa condição.

A migração permanece monotônica: o número efetivo de unidades nativas não pode diminuir e o teto corrente de entrypoints e ferramentas procedurais não nativos não pode aumentar.

## Pontos de entrada

- web: front controllers em `br`/`public`;
- autorização: `Prontoo\Runtime\LayeredKernel::enforceAction`;
- mutações: `Prontoo\Core\Invariant\InvariantKernel::guardMutation`;
- runtime: `app/Runtime/Runner.php` e unidades de composição;
- tarefas secundárias: `cron/maestro.php`;
- instalação: `/install.php`, somente na janela explícita de comissionamento e banco vazio.

## Contratos arquiteturais

A arquitetura não é apenas documental. São gates permanentes:

- `tools/architecture-check.php`;
- `tools/architecture-consolidation-check`;
- `tools/runtime-boundary-check`;
- `tools/application-test-contract-check`;
- `tools/query-budget-contract-check` e `tools/mysql-query-budget-check`;
- `tools/solid-audit`;
- `tools/security-regression-check.php`;
- `tools/schema-check.php`;
- `tools/install-security-check.php`;
- `tools/login-post-password-runtime-check`;
- `.github/workflows/architecture.yml`.

## Qualidades prioritárias

1. isolamento multitenant;
2. integridade transacional e auditável;
3. segurança fail-closed;
4. consistência operacional;
5. rastreabilidade histórica sem compatibilidade executável;
6. legibilidade e coesão;
7. desempenho do hot path;
8. extensibilidade sem regressão estrutural.
