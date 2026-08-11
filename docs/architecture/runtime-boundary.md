# Contrato da fronteira Runtime

## Finalidade

`app/Runtime` deve convergir para bootstrap, wiring, adaptação HTTP e coordenação fina. SQL, PDO, transações de caso de uso e adapters concretos pertencem às camadas Application/Infrastructure; a classificação ampla como Composition não autoriza essas responsabilidades.

Dois gates tokenizados protegem essa fronteira:

- `php tools/runtime-boundary-check` inventaria SQL, PDO, helpers, adapters concretos e controle transacional;
- `php tools/runtime-input-boundary-check` mede, somente nos arquivos classificados como input adapters, referências diretas a Infrastructure, chamadas ao gateway genérico de dados e faixas de tamanho.

Comentários, docblocks e HTML fora de PHP não entram nos inventários. Os gates mantêm limites globais, por arquivo e por categoria; arquivo novo começa com teto zero.

## Baseline histórica da Fase 16

| Categoria | Ocorrências | Arquivos |
|---|---:|---:|
| SQL de negócio em Runtime | 913 | 95 |
| SQL estrutural | 2 | 1 |
| acesso direto a PDO | 38 | 19 |
| helpers de persistência | 921 | 91 |
| adapters/repositories fora de composition roots | 0 | 0 |
| controle transacional então detectado | 71 | 22 |
| controle transacional estrutural | 3 | 1 |

A origem histórica permanece o commit `081cdbb234b70f2fa8dc4a1ac95325e022c614ca`, versão `1.8.10.1`. Esses números são preservados para rastreabilidade e não são o teto corrente.

## Correção semântica de 2026-08-11

A reavaliação identificou que o analisador anterior não reconhecia `atomic()`/`atomically()`. Por isso, a conclusão de transações de caso de uso iguais a zero não era comprovada. Com esses métodos incluídos, o commit `7fe4800f2752e4656f88820e77609e355f9f72f3` possui:

| Categoria corrente | Ocorrências |
|---|---:|
| SQL de negócio | 0 |
| SQL estrutural | 0 |
| PDO direto | 0 |
| helpers estruturais | 2 |
| adapters concretos fora dos roots | 0 |
| transações de caso de uso | 33 |
| transações estruturais | 3 |

As 33 ocorrências estavam em 22 arquivos: 12 no financeiro, 7 em autenticação/permissões e 14 em módulos operacionais/instalação. Elas são dívida explícita, não exceção arquitetural. Durante a extração, seu contador pode apenas diminuir; a regra zero voltará a ser ativada quando a última ocorrência sair do Runtime.

O segundo inventário tornou mensurável outra distinção antes mascarada pela classificação Composition:

| Dívida de input adapters | Teto corrigido inicial |
|---|---:|
| referências diretas a Infrastructure | 615 |
| chamadas ao gateway genérico de dados | 928 |
| arquivos acima de 500 linhas | 42 |
| arquivos acima de 700 linhas | 7 |
| arquivos acima de 1.000 linhas | 4 |

Esses eram os tetos corrigidos iniciais, não metas arquiteturais nem allowlist por diretório. Cada arquivo e cada categoria de dependência possui limite próprio; novas referências, chamadas genéricas ou mudança para uma faixa de tamanho pior falham na CI.

## Ratchet corretivo 2 — Financeiro e Agenda

O commit de origem `7f768f36d270aa09c3fa345bccaea3edfa414f6a` move as 12 sequências financeiras e as 4 sequências de Agenda para serviços semânticos de Application. O Runtime financeiro e `app/Runtime/Appointments` passam a ter regra transacional zero explícita. A nova baseline monotônica registra:

| Categoria corrente | Ocorrências |
|---|---:|
| transações de caso de uso | 17 |
| arquivos com transação de caso de uso | 14 |
| referências diretas a Infrastructure | 615 |
| chamadas ao gateway genérico de dados | 853 |
| arquivos acima de 500 linhas | 41 |
| arquivos acima de 700 linhas | 7 |
| arquivos acima de 1.000 linhas | 4 |

As 17 transações restantes são 7 de autenticação/permissões e 10 de módulos operacionais/instalação. O ratchet substitui a baseline anterior: uma sequência removida não pode reaparecer.

## Estado das migrações 17–19

As fases 17–19 removeram SQL de negócio e PDO direto do Runtime e proibiram a instanciação de adapters concretos fora dos cinco roots. `FinancialDataService`, `IdentityDataService` e `OperationalUseCaseService` usam catálogos fechados de operações, portanto o Runtime não envia SQL arbitrário.

Os casos financeiros de gaveta, movimentos, receitas, caixa, revisão e consolidação agora pertencem a Application Services. O mesmo vale para criar/alterar bloqueios e criar/alterar agendamentos. Os handlers Runtime preservam request, autorização, flash, redirect e adaptação dos colaboradores legados. Leituras simples podem continuar em read models catalogados quando outra abstração não trouxer benefício concreto.

## Exceções explícitas

As cinco composition roots são arquivos exatos enumerados pelo contrato. `DatabaseSchemaRuntimeOperations01.php` é a única classificação estrutural: mantém dois helpers de dispatch e três controles transacionais estruturais inventariados. Não há allowlist genérica por namespace, diretório ou padrão.

## Operação

- `php tools/runtime-boundary-check` valida monotonicidade e categorias zero;
- `php tools/runtime-boundary-check --inventory` exibe o inventário completo;
- `php tools/runtime-input-boundary-check` valida dependências, gateway e hotspots por papel;
- os modos `--print-baseline` e `--print-budget` materializam contratos para revisão explícita e não são executados automaticamente pela CI.

O estado comprovado hoje é: SQL, PDO e adapters concretos indevidos em zero; Financeiro e Agenda com transação Runtime igual a zero; 17 orquestrações transacionais residuais, 853 chamadas genéricas e 41 hotspots acima de 500 linhas congelados para redução monotônica.
