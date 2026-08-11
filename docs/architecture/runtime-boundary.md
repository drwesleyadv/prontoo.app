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

## Fechamento de segurança e identidade da Fase 18

Os prefixos `app/Runtime/SecurityAccess/`, `app/Runtime/AuthOnboarding/` e `app/Runtime/UsersPermissions/` também possuem regras zero para todas as categorias do contrato. Login, MFA, geração de autenticação, onboarding, usuários e permissões preservam request, sessão, cookie, redirect e flash no Runtime; a persistência usa operações fechadas de `IdentityDataService`, catálogos em Infrastructure e transações executadas pela porta. O registro de violações de escopo usa uma porta dedicada que mantém PDO e integração com `PiIntegrity` fora do Runtime, inclusive para evitar recursão no próprio scope guard.

O analisador reconhece formas executáveis de `SELECT`, `INSERT`, `UPDATE`, `DELETE` e `REPLACE`. Valores de domínio isolados, como os estados `replace` e `delete`, não são SQL e não entram no inventário.

## Fechamento operacional da Fase 19

A regra zero global cobre SQL de negócio, PDO direto, adapters concretos fora dos composition roots e transações de caso de uso em toda a árvore `app/Runtime/`. Tarefas, Agenda, Pacientes, Documentos, Leads, Maestro e os módulos residuais usam operações semânticas fechadas por escopo através de `OperationalUseCaseService`; `OperationalComposition` conecta as portas pequenas de dados, schema e contexto ao catálogo PDO de Infrastructure.

O fechamento removeu do Runtime 913 ocorrências de SQL de negócio, 38 acessos diretos a PDO e 71 controles transacionais de negócio em relação à baseline da Fase 16. O executor guardado preserva isolamento de tenant, integridade e transações estruturais; seus dois helpers de compatibilidade e três controles transacionais estruturais continuam inventariados, não representam persistência de caso de uso e não podem crescer.

## Exceções explícitas

As composition roots autorizadas são cinco arquivos exatos, cada um com justificativa no contrato. A única classificação estrutural é `DatabaseSchemaRuntimeOperations01.php`: nela permanecem somente dispatch guardado e atomicidade estrutural explicitamente inventariados, sem SQL, PDO ou transação de negócio. Não existe allowlist genérica por diretório, namespace ou padrão.

## Operação

- `php tools/runtime-boundary-check` valida monotonicidade e tetos zero;
- `php tools/runtime-boundary-check --inventory` inclui o inventário atual completo;
- `php tools/runtime-boundary-check --print-baseline --source-sha=<sha>` materializa uma baseline para revisão explícita, mas não é executado pela CI.

Novos arquivos Runtime não podem introduzir persistência. As quatro categorias semânticas globais permanecem obrigatoriamente em zero.
