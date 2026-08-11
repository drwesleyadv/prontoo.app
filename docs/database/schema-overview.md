# Visão geral do banco de dados

O Prontoo usa MySQL como fonte relacional de verdade. A versão atual exige MySQL 8.0.30 ou superior e mantém um schema canônico de instalação limpa, identificado por `prontoo_1_7_20_6_clean_schema_r7_layer2_ledger`.

## Estrutura

O contrato atual contabiliza 62 tabelas, das quais 60 são verificadas como operacionais pelos gates. O desenho cobre identidade, consultórios, pacientes, agenda, financeiro, tarefas, documentos, permissões, auditoria e estruturas de suporte.

## Identificadores e sequência

Tabelas usam `Seq` não nulo e único com geração nativa baseada em `UUID_SHORT()` onde definido pelo schema. A regra evita uma tabela global de sequência e mantém a integridade verificável no MySQL.

## Mutação de schema

Requests normais não executam DDL. Instalação e CI possuem caminhos explícitos para montar/verificar schema. A aplicação foi projetada para banco limpo; uma alteração futura de schema precisa ser uma decisão de release, não efeito colateral de boot.

## Relação com as camadas

Runtime não conhece SQL. Infrastructure concentra acesso concreto; Application expressa casos de uso e ports. Essa separação permite testar schema e budgets sem misturar persistência com controllers.
