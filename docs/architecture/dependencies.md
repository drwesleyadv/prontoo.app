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
- qualquer path histórico removido sendo reintroduzido como exceção à matriz de dependências.

## Exceções de classificação

Alguns arquivos físicos possuem classificação especial porque são composition roots ou contratos de bootstrap. Essas exceções estão enumeradas no `LayerMap`, não devem ser inferidas por convenção e não podem ser ampliadas silenciosamente.

## Estado pós-zero-legacy

Não existem fronteiras globais de compatibilidade executável nem namespaces ativos `/Legacy/`. Referências a origens históricas são permitidas somente em mapas explícitos de migração, listas de removidos, auditorias históricas e contratos que resolvem uma origem removida para seus destinos nativos.

Listas arquiteturais de componentes ativos devem apontar apenas para arquivos existentes e não podem apontar para `removed_legacy_files`. `tools/architecture-check.php` verifica essa condição.

## Política de evolução

- cobertura de classificação: 100%;
- unidades nativas: não podem diminuir abaixo da baseline consolidada de 278;
- entrypoints/ferramentas procedurais não nativos: não podem ultrapassar o teto corrente de 21;
- `compatibility_boundaries`: deve permanecer vazio;
- remoção/movimentação de símbolos internos deve manter rastreabilidade pelos contratos de resolução quando necessário;
- mudança da matriz de dependências exige ADR e atualização dos contratos executáveis.
