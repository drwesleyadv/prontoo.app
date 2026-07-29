# Isolamento multitenant

## Regra

Cada registro operacional deve estar associado ao consultório correto ou ser explicitamente global. O contexto do cliente nunca é prova suficiente de escopo.

## Leitura

Consultas incluem o escopo canônico e evitam joins capazes de atravessar tenants. Resultados agregados devem manter a mesma prova.

## Escrita

O consultório é derivado do contexto revalidado. Referências externas são confirmadas no mesmo tenant antes do commit.

## Filas e cache

Envelopes do Maestro carregam contexto assinado. Caches são identificados por geração e consultório. Nenhum processo cron recebe autorização implícita para atravessar dados.

## Testes mínimos

- leitura de registro de outro consultório retorna negação ou ausência;
- escrita com referência externa de outro consultório falha;
- agregação não mistura tenants;
- tarefa adiada preserva tenant original;
- perfil global só acessa escopo global após reautenticação.
