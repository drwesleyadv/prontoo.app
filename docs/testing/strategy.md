# Estratégia de testes

## Objetivo

Demonstrar comportamento correto, falha segura e preservação das invariantes.

## Níveis

**Unitários:** decisões puras, normalização, cálculos e máquinas de estado.  
**Integração:** banco, transações, escopo, filas e armazenamento.  
**HTTP:** autenticação, CSRF, contratos de ação e respostas.  
**End-to-end:** fluxos essenciais vistos pelo usuário.  
**Propriedades:** integridade financeira, auditoria, tempo e idempotência.  
**Operacionais:** instalação, deploy, rollback e recuperação.

## Pirâmide

A maioria dos testes deve ser rápida e determinística. Testes de integração cobrem fronteiras reais. End-to-end fica restrito aos caminhos críticos.

## Casos negativos

Toda área sensível deve testar ausência de permissão, tenant incorreto, estado indeterminado, repetição, concorrência, entrada inválida e indisponibilidade de dependência.

## Ambiente

CI usa PHP 8.4 e MySQL 8.0. Dados de teste são sintéticos e não contêm informações reais.
