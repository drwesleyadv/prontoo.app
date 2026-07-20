# Arquitetura de persistência — Prontoo 1.7.20.6

## Objetivo

A revisão `r7` é exclusiva para instalação limpa. Ela preserva os contratos operacionais da versão 1.7.20.5 e reduz a persistência auxiliar para que a camada 2 produza uma única evidência por ação protegida, em vez de várias linhas técnicas distribuídas.

## Princípios

1. **Dados operacionais permanecem nos mesmos domínios.** Sessenta definições de tabela foram comparadas por hash após neutralizar apenas a expressão da coluna `Seq`.
2. **`Seq` pertence à própria linha.** Todas as 62 tabelas usam `Seq BIGINT UNSIGNED NOT NULL DEFAULT (UUID_SHORT())`, com índice único. Não existe alocador central nem atualização posterior da linha.
3. **Autorização e mutação formam uma mesma evidência.** `pi_action_ledger` recebe a autorização antes do handler e é finalizado pelo núcleo de integridade com hash, quantidade de mutações e tabelas atingidas.
4. **Auditoria é autossuficiente.** Evento, entidade, mensagem, IP resumido e agente são gravados diretamente em `pi_audit`, eliminando dicionários e upserts auxiliares.
5. **Métricas agregadas permanecem agregadas.** Contadores, estatísticas diárias, telemetria do Maestro, erros e violações continuam separados porque possuem ciclo de retenção e consulta próprios.
6. **Não há migração in-place.** A revisão remove deliberadamente a compatibilidade estrutural com o banco anterior e exige banco com zero tabelas.

## Coluna Seq sem tabela de sequência

A coluna `Seq` não é chave de negócio nem substitui as chaves primárias. Ela funciona como cursor técnico aproximadamente cronológico para exportação e migrações futuras.

Uma futura migração deve ordenar por:

1. `Seq`;
2. nome da tabela;
3. chave primária da linha.

Essa ordenação fornece desempate determinístico sem criar um registro auxiliar para cada INSERT. A validação de instalação confirma que toda tabela possui `Seq` não nulo, default nativo e índice único.

## Ledger da camada 2

A tabela `pi_action_ledger` substitui:

- `pi_action_proofs`;
- `pi_checksum_events`;
- `pi_checksum_batches`;
- `pi_checksum_state`.

Fluxo:

1. o middleware avalia o contrato exato da ação;
2. persiste a autorização com status `authorized` ou `denied`;
3. somente uma autorização persistida libera o handler;
4. as escritas operacionais são acumuladas em memória;
5. após o commit, a mesma linha recebe `mutation_hash`, `mutation_count`, `affected_tables_json`, `finalized_at` e status `committed`.

Ações internas sem requisição de usuário recebem uma única linha `system_mutation`.

## Auditoria inline

Foram removidas as tabelas:

- `pi_audit_entities`;
- `pi_audit_event_types`;
- `pi_audit_messages`;
- `pi_ip_addresses`;
- `pi_user_agents`.

A consulta de atividade deixa de depender de cinco JOINs e a gravação deixa de executar até cinco upserts auxiliares antes da linha principal.

## Estruturas auxiliares removidas

Também foram removidas por ausência de responsabilidade operacional ativa:

- `pi_sequence`;
- `pi_audit_chain_heads`;
- `pi_integrity_anchors`;
- `pi_record_integrity`;
- `pi_runtime_flags`.

`pi_integrity_alerts` permanece como canal de incidente. `pi_meta` continua sendo a fonte pequena de metadados canônicos.

## Resultado estrutural

- tabelas anteriores: **75**;
- tabelas novas: **62**;
- redução líquida: **13 tabelas**;
- linhas de apoio por registro operacional: **1 linha a menos**, pela remoção de `pi_sequence`;
- linha técnica por ação protegida: **1 ledger**, em lugar de prova + evento de checksum + estado/batch;
- auditoria: **1 INSERT**, sem dicionários auxiliares.

## Instalação limpa e descarte da instalação anterior

Ao receber a versão 1.7.20.6, uma instalação na revisão `r6`:

1. confirma que o banco contém exclusivamente tabelas `pi_*`;
2. confirma a revisão anterior conhecida;
3. desativa temporariamente as FKs;
4. remove todas as tabelas;
5. verifica que o banco terminou com zero tabelas;
6. remove configuração e locks locais;
7. redireciona para o instalador.

A limpeza é bloqueada se houver qualquer tabela externa ao Prontoo ou revisão desconhecida.

## Certificação

O CI executa duas provas:

- prova estática de equivalência das 60 tabelas preservadas e ausência das estruturas removidas;
- instalação real do schema em MySQL 8, seguida de INSERT sem informar `Seq` e validação da ordem crescente gerada pelo servidor.
