# Integridade financeira

O domínio financeiro exige mais do que validação de formulário. Um recebimento ou movimento precisa ser coerente como unidade, permanecer no consultório correto e deixar rastros suficientes para conferência.

## Fronteira

Application Services financeiros coordenam casos de uso como recebimentos, movimentos, gavetas, metas e consolidação. Ports descrevem capacidades; Infrastructure executa persistência. Runtime não abre as transações de negócio.

## Invariantes

Escritas relacionadas devem ser atômicas quando a operação exige. Duplicidade operacional deve ser impedida pelo caso de uso e pelo banco onde aplicável. Devedor, recurso financeiro, consultório e estado da operação precisam permanecer coerentes.

## Performance

Leituras financeiras críticas participam dos budgets MySQL. Read models não podem introduzir INSERT/UPDATE/DELETE/REPLACE. A meta é manter previsibilidade sem trocar correção por velocidade.

## Auditoria

Movimentos relevantes devem ser rastreáveis por ledger/auditoria; falha de apresentação não pode reexecutar silenciosamente uma operação financeira já confirmada.
