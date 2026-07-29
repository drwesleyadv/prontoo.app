# Visão do schema

## Modelo

O Prontoo usa schema limpo para instalação em banco vazio, com identificadores `Seq` nativos e ledger de ações.

## Grupos conceituais

- identidade e autenticação;
- consultórios e cargos;
- pessoas e pacientes;
- agenda e jornada;
- documentos;
- tarefas;
- financeiro;
- auditoria e integridade;
- filas e operação.

## Contratos

- `schema.sql` é a origem estrutural de instalação;
- `app/Database/operational-schema.contract.json` descreve o contrato operacional;
- hashes canônicos detectam divergência;
- runtime comum permanece sem DDL.

## Identificadores

Cada tabela aplicável possui `Seq` não nulo e único, gerado pelo banco. Chaves técnicas não substituem prova de tenant ou autorização.
