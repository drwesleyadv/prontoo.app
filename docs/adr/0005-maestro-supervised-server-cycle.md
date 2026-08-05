# ADR 0005 — Ciclo supervisionado do Maestro

**Status:** aceito

**Data:** 2026-08-05

## Contexto

O mesmo comando cron acumulava execução de regras, auditoria diferida, integridade, reparos administrativos, preflight mutável e invalidação global de cache. Um problema em qualquer responsabilidade era apresentado como falha genérica do Maestro. A fila morta histórica também mantinha o código de saída em erro, enquanto falhas de consulta de candidatos podiam ser interpretadas como ausência de trabalho.

A execução depende exclusivamente do servidor. Gatilhos oportunistas no navegador foram descartados porque ampliariam a complexidade client-side e misturariam disponibilidade do produto com tráfego de usuários.

## Decisão

O arquivo `cron/maestro.php` permanece como adaptador operacional e supervisor do ciclo. O domínio de regras continua em `app/Domain/Maestro/Maestro.php`, e a fila assinada permanece em `app/Support/DeferredAudit.php`.

O ciclo passa a usar:

- preflight somente leitura;
- orçamento reservado por estágio;
- estado pai com saúde independente;
- rodízio entre consultórios;
- destinatários fail-closed;
- datas civis calculadas no fuso do consultório e convertidas para UTC sem alterar a sessão MySQL;
- retries com backoff para auditoria e ações;
- distinção entre falha atual e inventário histórico;
- invalidação seletiva de cache;
- bootstrap observável antes do carregamento completo.

Não são introduzidas tabelas, alterações de schema ou dependências client-side.

## Consequências

Falhas de auditoria diferida deixam de ocultar o estado das regras, mas ainda influenciam o código de saída quando acontecem no ciclo atual. Itens históricos de fila morta permanecem visíveis como atenção.

Uma regra com destinatário inválido deixa de produzir ação para toda a clínica. Ela falha de maneira explícita até que a configuração seja corrigida.

A seleção de até 300 candidatos reduz a possibilidade de estagnação causada por itens idempotentes já processados. Saturação dessa janela passa a ser observável e exige revisão antes de crescimento acima desse limite.

A compatibilidade com o comando de cron e com `pi_maestro_job_runs` é preservada.

## Alternativas rejeitadas

Executar o Maestro durante navegação autenticada foi rejeitado porque acopla confiabilidade operacional ao tráfego e ao cliente.

Tratar fila morta histórica como sucesso silencioso foi rejeitado porque apagaria risco operacional. Ela permanece em estado de atenção.

Ampliar destinatários inválidos para toda a clínica foi rejeitado por violar minimização de acesso e intenção do administrador.
