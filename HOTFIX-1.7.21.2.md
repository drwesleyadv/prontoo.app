# Hotfix Prontoo 1.7.21.2

## Incidente

A instalação limpa da versão 1.7.21.1 executava o primeiro `CREATE TABLE`, mas era interrompida logo depois porque `run_schema_sql()` ainda chamava `PiIntegrity::proveSchemaOperation()` com a assinatura anterior. No PHP 8.4, o booleano de sucesso era recebido no argumento tipado como nome da tabela, produzindo `TypeError`.

## Correção

- compatibiliza exclusivamente a chamada estrutural legada do instalador;
- extrai o nome da tabela somente de SQL `CREATE TABLE` canônico;
- mantém a assinatura moderna estrita para novas chamadas;
- carrega `PiIntegrity` nos testes estático e de instalação;
- executa `install_fresh_schema()` em MySQL 8 com zero tabelas iniciais.

## Escopo

Não há alteração no `schema.sql`, na revisão `prontoo_1_7_20_6_clean_schema_r7_layer2_ledger`, nas 62 tabelas, nos dados operacionais, na interface ou nos ativos.
