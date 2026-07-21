# Hardening Prontoo 1.7.21.3

## Estado

A instalação 1.7.21.2 foi concluída com sucesso. A partir desta versão, a estrutura do banco é considerada congelada.

## Defesas

- `install.php` recebe `Require local` no servidor web;
- o ponto de entrada valida loopback e host local antes de carregar o bootstrap;
- solicitações com cabeçalhos de proxy são recusadas;
- o runtime público não redireciona para o instalador;
- DDL exige uma janela privada autenticada por nonce em memória;
- a janela só abre para HTTP local real ou CI/CLI explicitamente autorizado;
- a rotina histórica de limpeza integral do banco foi removida;
- testes verificam exposição, congelamento e ausência de referências destrutivas.

## Invariantes preservadas

Não há alteração no `schema.sql`, na revisão `prontoo_1_7_20_6_clean_schema_r7_layer2_ledger`, nas 62 tabelas, nos dados operacionais, na interface ou nos ativos.
