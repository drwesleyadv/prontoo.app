# Camadas e responsabilidades

As camadas do Prontoo representam tipos diferentes de decisão. A pergunta útil não é “em qual pasta cabe este código?”, mas “qual conhecimento este código precisa possuir?”.

## Domain

Contém regras e políticas que expressam significado de negócio. Não deve depender de request HTTP, sessão, PDO ou detalhes de renderização. Uma regra que poderia ser discutida com alguém do domínio sem mencionar framework ou tabela tende a pertencer aqui.

## Application

Contém casos de uso. Services coordenam operações e dependem de ports. A camada sabe **o que precisa acontecer**, mas não escolhe **como o banco executa** ou **como a página responde**. A superfície pública de Application é caracterizada por contrato de testes.

## Infrastructure

Implementa detalhes concretos: banco, filesystem, criptografia, adapters e mecanismos operacionais. É a camada onde PDO pode existir. Ela satisfaz ports definidos em camadas internas ou fornece mecanismos explicitamente compostos.

## Presentation

Renderiza dados e estrutura saída. Não deve decidir autorização, transação ou persistência. Seu trabalho é transformar estado já resolvido em representação adequada.

## Runtime

É o input adapter da aplicação. Faz roteamento, lê request, resolve sessão e contexto, aplica guards de borda, chama Application e organiza resposta/redirect. Runtime não é um atalho para “tudo que roda”; SQL, PDO e transações de negócio têm budget zero aqui.

## Composition

Composition roots conectam implementações concretas às abstrações. Concreto fora desses pontos é sinal de acoplamento indevido e é detectado pelos contratos.

A direção desejada é das bordas para o centro sem permitir que detalhes técnicos comandem regras internas.
