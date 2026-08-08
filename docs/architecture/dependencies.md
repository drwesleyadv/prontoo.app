# Regra de dependências

## Fonte de verdade

A política executável é `app/Core/Architecture/LayerMap.php`. `tools/architecture-check.php` verifica a árvore versionada e o workflow de arquitetura impede merge de relações proibidas.

## Relações permitidas

| Origem | Destinos permitidos |
|---|---|
| Core | Core |
| Domain | Core, Domain |
| Application | Core, Domain, Application |
| Infrastructure | Core, Domain, Application, Infrastructure |
| Presentation | Core, Domain, Application, Presentation |
| Composition | Core, Domain, Application, Infrastructure, Presentation, Composition |

A direção é de dependências para dentro. `Infrastructure` implementa portas declaradas em `Application`; `Presentation` consome casos de uso sem acessar adaptadores concretos; `Composition` conecta as implementações concretas.

## Relações proibidas

- `Core` chamando PDO, HTTP ou apresentação;
- `Domain` lendo `$_GET`, `$_POST`, `$_SESSION`, `$_SERVER`, HTML ou PDO;
- `Application` renderizando HTML ou conhecendo repositórios PDO concretos;
- `Presentation` executando SQL ou dependendo de `Infrastructure`;
- `Infrastructure` decidindo política de autorização;
- adaptadores externos criando regras alternativas às invariantes;
- unidades `Legacy` usando sua origem histórica como exceção às regras da camada.

## Exceções de classificação

Alguns arquivos físicos possuem classificação especial porque são composition roots ou contratos de bootstrap. Essas exceções estão enumeradas no `LayerMap`, não devem ser inferidas por convenção e não podem ser ampliadas silenciosamente.

## Compatibilidade

Fronteiras históricas podem delegar para componentes nativos. A delegação deve ser fina. `app/Support/ModuleLoader.php`, por exemplo, é fachada compatível de composição e não pode reabsorver catálogo, loading state, dispatch, persistência ou apresentação.

`Legacy` é um namespace de compatibilidade dentro de uma camada real. A regra de dependências da camada continua integralmente aplicável.

## Política de evolução

- cobertura de classificação: 100%;
- unidades nativas: não podem diminuir abaixo da baseline consolidada;
- fronteiras transitórias: não podem ultrapassar o teto consolidado;
- remoção/movimentação de símbolos internos deve passar pelo contrato de resolução;
- mudança da matriz de dependências exige ADR e atualização dos contratos executáveis.
