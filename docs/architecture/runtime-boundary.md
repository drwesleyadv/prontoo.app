# Contrato da fronteira Runtime

## Finalidade

`app/Runtime` deve convergir para bootstrap, wiring, adaptação de entrada, dispatch e coordenação fina. Persistência, transações de caso de uso e adaptadores concretos pertencem a Application/Infrastructure e não ganham legitimidade por estarem presentes na árvore histórica.

O gate canônico é `php tools/runtime-boundary-check`. Ele integra `tools/quality-gate` e analisa a árvore inteira de `app/Runtime` por tokens PHP. Comentários, docblocks e HTML fora de PHP não entram no inventário. SQL é reconhecido em tokens de string com forma de `SELECT`, `INSERT`, `UPDATE`, `DELETE` ou `REPLACE`; chamadas a helpers, PDO, transações e instanciações concretas são reconhecidas pela estrutura de tokens e pela resolução de imports.

## Baseline da Fase 16

| Categoria | Ocorrências | Arquivos |
|---|---:|---:|
| SQL de negócio em Runtime | 913 | 95 |
| SQL estrutural explicitamente classificado | 2 | 1 |
| acesso direto a PDO | 38 | 19 |
| helpers de persistência | 921 | 91 |
| adapters/repositories fora de composition roots | 0 | 0 |
| controle transacional de negócio | 71 | 22 |
| controle transacional estrutural | 3 | 1 |

A origem congelada é o commit `081cdbb234b70f2fa8dc4a1ac95325e022c614ca`, versão `1.8.10.1`. O inventário completo, por arquivo, operação e assinatura, está em `app/runtime.boundary-baseline.json`.

## Monotonicidade

Cada achado recebe fingerprint estável por categoria, arquivo, operação e payload normalizado. O gate falha quando:

- o total de uma categoria aumenta;
- um arquivo ultrapassa sua contagem congelada;
- surge uma assinatura que não existia na baseline;
- um arquivo novo introduz persistência Runtime;
- uma categoria ou prefixo declarado como zero deixa de estar zerado;
- composition roots ou classificações estruturais divergem da lista explícita congelada.

Remoções passam sem rebaselinar. Assim, uma fase posterior pode reduzir a dívida sem tornar o teto restante mais permissivo.

## Fechamento financeiro da Fase 17

O prefixo `app/Runtime/Financial/` possui regra zero explícita para todas as categorias do contrato. A migração removeu 201 ocorrências de SQL de negócio, 227 chamadas a helpers de persistência e 12 controles transacionais do Runtime financeiro. Nesse prefixo, SQL, PDO, helpers, instanciação concreta e transações agora permanecem em zero.

Os adaptadores Runtime preservam request, sessão, redirect, flash e composição HTML. Operações de dados passam por `FinancialDataService` e sua porta fechada; somente identificadores semânticos catalogados são aceitos. `PdoFinancialDataRepository` resolve esses identificadores nos catálogos SQL de Infrastructure e executa pelo executor guardado ligado em `FinancialComposition`, preservando scope guard, integridade, retries e a proteção de movimentos consolidados. O serviço não aceita SQL arbitrário vindo do Runtime.

## Exceções explícitas

As composition roots autorizadas são quatro arquivos exatos, cada um com justificativa no contrato. A única classificação estrutural inicial é `DatabaseSchemaRuntimeOperations01.php`, que permanece contada como dívida estrutural e não pode crescer. Não existe allowlist genérica por diretório, namespace ou padrão.

## Operação

- `php tools/runtime-boundary-check` valida monotonicidade e tetos zero;
- `php tools/runtime-boundary-check --inventory` inclui o inventário atual completo;
- `php tools/runtime-boundary-check --print-baseline --source-sha=<sha>` materializa uma baseline para revisão explícita, mas não é executado pela CI.

As fases de fechamento adicionam regras zero por módulo e, ao final, para as categorias globais de persistência de negócio.
