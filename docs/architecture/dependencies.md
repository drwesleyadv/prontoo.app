# Direção de dependências

Dependência é uma forma de conhecimento. Quando um módulo conhece uma classe concreta, uma tabela ou o protocolo HTTP, ele assume responsabilidade por essa decisão. A arquitetura controla esse conhecimento para que mudanças locais permaneçam locais.

## Regra principal

Domain não depende de Runtime, Presentation ou Infrastructure. Application depende de políticas internas e de ports, não de PDO ou de páginas. Runtime e Presentation podem depender de Application e Domain para usar decisões já definidas. Infrastructure implementa mecanismos e adapters. Composition roots são a ponte explícita entre abstrações e concretos.

## O que a CI impede

Os gates rejeitam SQL de negócio e PDO em Runtime, adapters concretos fora dos pontos de composição, símbolos internos que deixaram de resolver e outras violações tokenizadas. A fronteira é verificada no código-fonte, evitando que uma convenção arquitetural dependa apenas de revisão humana.

## Acoplamento residual

Ainda existem referências diretas de input adapters a Infrastructure e acessos ao gateway genérico medidos por ratchets. Eles representam dívida histórica tolerada, não modelo recomendado. O valor pode diminuir; novos acoplamentos não devem elevar o teto.

## Como decidir uma nova dependência

Se o chamador precisa de uma capacidade de negócio, prefira um Application Service. Se Application precisa de um mecanismo externo, declare um port. Se o detalhe é puramente técnico, mantenha-o em Infrastructure. Se a dependência só existe para montar o grafo, concentre-a no composition root.
