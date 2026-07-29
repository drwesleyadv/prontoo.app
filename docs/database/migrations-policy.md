# Política de mudanças estruturais

## Estado atual

O schema é congelado no runtime. A instalação limpa opera apenas em banco vazio e sob janela estrutural controlada.

## Mudança de schema

Uma mudança exige:

1. ADR ou decisão equivalente;
2. análise de compatibilidade;
3. atualização de `schema.sql`;
4. atualização dos contratos;
5. instalação limpa testada;
6. estratégia para ambientes existentes;
7. rollback ou procedimento de contenção;
8. validação no CI.

## Proibições

- DDL disparado por página comum;
- apagar banco existente;
- corrigir produção por SQL manual não registrado;
- divergência entre schema, contrato e versão;
- migração destrutiva sem cópia e verificação.

## Dados existentes

Transformações devem ser idempotentes, auditáveis e executadas fora do caminho crítico.
