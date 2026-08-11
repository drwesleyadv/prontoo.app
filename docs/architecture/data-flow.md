# Fluxo de dados

O fluxo típico começa com uma requisição HTTP e termina em uma resposta ou redirect. A arquitetura procura tornar explícita cada mudança de responsabilidade ao longo desse caminho.

## Leitura

Runtime valida contexto e chama um serviço de leitura ou composição apropriada. Application expressa a intenção. Infrastructure busca dados sob escopo de tenant. O resultado volta como estrutura estável e Presentation o transforma em HTML ou JSON. Read models críticos possuem budgets MySQL para evitar regressões de consultas.

## Comando

Runtime interpreta e valida a entrada de borda, mas não abre transação de negócio. Um Application Service coordena o caso de uso; ports entregam operações ao adapter concreto. A transação, quando necessária, é encerrada na fronteira apropriada. Auditoria e invalidação de cache seguem a operação de forma explícita.

## Sessão

Autenticação cria contexto de usuário e geração de sessão. Em cada fluxo protegido, guards verificam validade antes de continuar. Logout rotaciona a geração global; sessões antigas são recusadas no primeiro uso subsequente.

## Trabalho diferido

Eventos que podem ser processados fora da resposta síncrona entram em spool persistente. Maestro retoma o contexto necessário, aplica política de retry e separa falha temporária de dead-letter.

O princípio comum é não deixar dados mudarem de significado silenciosamente entre camadas.
