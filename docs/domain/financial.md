# Financeiro

## Escopo

O módulo atende clínicas pequenas e cobre recursos, contas a pagar, contas a receber, movimentos, conferência, metas e consolidação.

## Representação

Valores monetários são centavos inteiros. Conversão para formato decimal ocorre apenas na borda de entrada ou apresentação.

## Invariantes

- recebimento possui devedor identificado;
- pagamento possui credor identificado;
- movimentos definitivos surgem de operação confirmada;
- correções usam ajustes compensatórios;
- snapshots diários precisam reconciliar com os lançamentos;
- toda operação pertence ao consultório correto;
- arredondamento não pode criar ou eliminar centavos.

## Fechamento

Conferência só é liberada quando as gavetas aplicáveis estão fechadas. A confirmação produz lançamentos definitivos e auditáveis.
