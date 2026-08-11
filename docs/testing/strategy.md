# Estratégia de testes

A estratégia do Prontoo combina testes rápidos de casos de uso, contratos estáticos de arquitetura e smokes reais com PHP/MySQL. Cada camada de teste responde uma pergunta diferente.

## Suíte rápida

Application possui 49 casos críticos catalogados, cobrindo 30 services e 15 ports, com 127 assertivas explicitamente ligadas ao contrato dentro de uma suíte rápida de 194 assertivas. A meta é caracterizar 100% das entradas públicas catalogadas.

## Contratos arquiteturais

Quality gate e verificadores tokenizados medem dependências, símbolos, SOLID, Runtime boundary e ratchets. Eles impedem regressões que testes funcionais poderiam não perceber.

## Banco real

Schema contract, 11 query-budget scenarios, login, logout global, Maestro e critical runtime smoke usam MySQL real na CI. Isso valida SQL, constraints e transações que mocks não reproduzem.

## HTTP

Smokes do front controller atravessam a borda web e comprovam sessão, CSRF, redirects e boot.

A regra é testar a propriedade na camada mais determinística capaz de prová-la.
