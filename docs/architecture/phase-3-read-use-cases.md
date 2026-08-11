# Arquivo histórico — Fase 3: casos de uso de leitura

Esta fase introduziu a ideia de leituras críticas como casos de uso explícitos, com ports e estruturas estáveis entre Application e Infrastructure.

O efeito final é visível nos services de pacientes, financeiro e operação e nos budgets MySQL. Read models podem ser otimizados sem mover SQL para Runtime e sem transformar Presentation em camada de acesso a dados.

Novas leituras devem seguir o padrão apenas quando ele trouxer isolamento, teste ou orçamento mensurável; não é necessário criar service para cada `SELECT` trivial.
