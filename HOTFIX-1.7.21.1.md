# Hotfix Prontoo 1.7.21.1

## Incidente

A instalação limpa da versão 1.7.20.6 parava antes do primeiro `CREATE TABLE` porque `app/Database/DatabaseSchema.php` ainda exigia exatamente 75 instruções, embora o schema r7 contenha 62 tabelas. O CI anterior validava `schema.sql` diretamente e não exercitava o parser runtime chamado por `install_fresh_schema()`.

## Correção

- a coleção esperada passa a ser derivada do contrato operacional versionado;
- o parser runtime compara o conjunto completo de nomes, não somente uma quantidade;
- tabelas duplicadas, ausentes ou inesperadas falham antes do DDL;
- o teste MySQL chama o mesmo caminho `prontoo_schema_statements()` e executa `install_fresh_schema()` sobre banco com zero tabelas;
- o teste conclui a criação da Pessoa e do primeiro Usuário, validando FKs e `Seq` nativo.

## Escopo

O schema SQL e a revisão r7 permanecem inalterados. Não há migração, mudança operacional, visual ou de banco além da correção do contrato de instalação.
