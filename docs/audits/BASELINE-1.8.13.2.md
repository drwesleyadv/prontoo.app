# Baseline auditada 1.8.13.2

Data: 2026-08-13  
Baseline examinada: `1.8.13.1` (`c2b1aa3df681fd447560d8f79277fafa2e70c6ad`)  
Resultado: promovida a `1.8.13.2` após microajustes e reconciliação dos contratos.

## Escopo

A auditoria revisou o estado mais recente com foco em regressões pequenas, consistência entre código e documentação e segurança para manutenção por agentes. Não houve alteração de banco ou schema e não foi aberto novo ciclo de refatoração arquitetural.

## Achados corrigidos

### Grade dos KPIs globais

A release `1.8.13.1` pretendia exibir quatro cards de telemetria em 2 colunas × 2 linhas. O wrapper interno, porém, combinava `two` com `stats-grid` e `admin-overview-kpis`. No cascade canônico, as duas últimas classes recebem `grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))` depois de `.two`, anulando a garantia 2×2 em viewport ampla.

A baseline `1.8.13.2` mantém o mesmo renderer compartilhado e troca somente os hooks conflitantes do wrapper interno por `two wide global-telemetry-grid`. Assim:

- desktop amplo: 2 colunas e 2 linhas para os quatro cards;
- breakpoint responsivo vigente: 1 coluna;
- Painel do Desenvolvedor mantém o wrapper externo e os hooks de telemetria;
- Status reutiliza o mesmo renderer;
- nenhum CSS inline, novo `!important`, selector de rota ou aumento de dívida visual é introduzido.

### Documentação de versão

O `README.md` ainda declarava `1.8.11.2` como versão canônica. A referência foi atualizada para a baseline auditada.

### Operação por IA agêntica

O repositório possuía documentação arquitetural robusta, mas faltava um ponto de entrada único que explicasse a um agente como decidir o que é fonte de verdade e quais invariantes não podem ser relaxadas. `AGENTS.md` passa a registrar a ordem de autoridade, fronteiras arquiteturais, isolamento por consultório, segurança, banco, Presentation, release, gates e Definition of Done.

## Invariantes preservadas

- PHP 8.4 e MySQL 8.0.30+;
- nenhuma mudança de banco ou schema;
- sem SQL/PDO/transação de caso de uso novos no Runtime;
- isolamento por consultório e permissões inalterados;
- autenticação, MFA, sessão, CSRF e auditoria inalterados;
- cálculos e fontes dos KPIs inalterados;
- fonte CSS e budgets visuais inalterados;
- manifests e hashes derivados reconciliados pelo tooling canônico.

## Critério de baseline

`1.8.13.2` é a baseline para trabalhos posteriores. Agentes devem iniciar por `AGENTS.md`, usar `version.json` como versão canônica, respeitar os manifests executáveis e não reutilizar `1.8.13.1` como base para novas mudanças.
