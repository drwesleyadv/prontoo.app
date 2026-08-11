# Fronteira do Runtime

Runtime é a camada que mais facilmente acumula responsabilidades porque está próxima das páginas. A consolidação arquitetural transformou essa zona em uma fronteira mensurável.

## O que pertence ao Runtime

Roteamento, parsing de request, sessão, resolução de tenant, guards de acesso, chamada de casos de uso, composição de resposta e redirects. Ele pode coordenar, mas não deve implementar a persistência ou a transação que dá atomicidade ao negócio.

## Invariantes de zero

SQL de negócio, PDO direto, transações de caso de uso, `OperationGateway` e adapters concretos fora dos composition roots têm orçamento zero no Runtime. Referências internas quebradas também são rejeitadas.

## Dívida aceita

Há input adapters historicamente grandes. O budget atual registra 39 acima de 500 linhas, sete acima de 700 e quatro acima de 1000. Também existem ratchets para acoplamentos genéricos. Isso não é licença para crescimento: `refactor-on-touch` exige redução quando um hotspot é alterado.

## Por que não decompor tudo agora

Tamanho não é, sozinho, violação de arquitetura. Depois que persistência e transação saíram dos adapters, o problema residual é principalmente modificabilidade. A política de manutenção evita risco de reescrever páginas estáveis apenas para obter números menores.
