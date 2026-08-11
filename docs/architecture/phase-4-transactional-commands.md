# Arquivo histórico — Fase 4: comandos transacionais

A fase de comandos transacionais retirou do Runtime a responsabilidade de decidir atomicidade. A unidade transacional passou a acompanhar o caso de uso, mediada por Application e adapters apropriados.

Esse movimento é a base do contrato atual `Runtime business transactions = 0`. A página pode coordenar a intenção, mas não deve abrir/fechar uma transação que define consistência de negócio.

O documento é histórico; a regra vigente está em `runtime-boundary.md` e nos gates.
