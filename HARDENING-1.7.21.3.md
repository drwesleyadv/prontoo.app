# Hardening Prontoo 1.7.21.3

## Objetivo

Encerrar a fase de instalação e congelar a estrutura do banco de dados após a instalação bem-sucedida da versão 1.7.21.2.

## Política

- o instalador HTTP será acessível somente por uma requisição originada do loopback e destinada explicitamente a `localhost`, `127.0.0.1` ou `::1`;
- cabeçalhos de proxy não serão aceitos para autorizar o instalador;
- requisições públicas não receberão formulário, diagnóstico ou redirecionamento para o instalador;
- comandos DDL permanecerão bloqueados no runtime e somente poderão ser executados dentro de uma janela privada do instalador local ou do teste de certificação;
- a rotina histórica de limpeza integral do banco será removida;
- `schema.sql`, revisão r7 e as 62 tabelas permanecerão inalterados.
