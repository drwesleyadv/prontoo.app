# Invariantes do banco

## Gerais

- chaves e referências preservam integridade;
- `clinic_id` não atravessa relações operacionais;
- estados usam valores canônicos;
- timestamps são armazenados de forma consistente;
- identificadores internos são únicos;
- alterações críticas deixam prova auditável.

## Temporais

Limites civis de dia e mês seguem o fuso do consultório. Consultas usam intervalos UTC semiabertos para evitar duplicidade ou lacuna.

## Financeiras

- centavos inteiros;
- soma de lançamentos reconciliável;
- ajustes compensatórios;
- snapshots diários verificáveis;
- nenhuma operação parcial após rollback.

## Sessão e segurança

Gerações, desafios e fatores devem respeitar unicidade, expiração e revogação.
