# Arquivo histórico — Fase 2: fronteiras de apresentação

A contribuição durável desta fase foi separar renderização de decisões de autorização e persistência. Presentation passou a ser tratada como transformação de dados já resolvidos, e não como lugar para completar regras do caso de uso.

Essa decisão reduz testes frágeis de HTML e evita que a mesma regra seja duplicada em várias páginas. Hoje a fronteira está incorporada ao mapa arquitetural; este arquivo existe apenas para explicar a origem da decisão.
