# Cadeia de auditoria

## Objetivo

Detectar remoção, inserção, reordenação ou alteração de eventos auditáveis.

## Estrutura

Cada evento incorpora dimensões canônicas e referência criptográfica ao estado anterior. A cabeça da cadeia é atualizada de forma transacional.

## Propriedades

- autoria vem de contexto confiável;
- IP e agente são normalizados antes da prova;
- campos públicos não substituem autoria ou instante;
- mutação protegida e prova confirmam juntas;
- verificação de cadeia falha fechada;
- filas adiadas preservam origem assinada;
- replay não cria evento duplicado.

## Operação

Falha de verificação deve interromper a operação sensível, preservar evidências e iniciar o runbook de incidente.
