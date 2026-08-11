# Invariantes de banco de dados

Invariantes são condições que o sistema não negocia. Algumas são impostas pelo schema; outras são verificadas por código e testes. O objetivo é tornar estados inválidos difíceis de criar e fáceis de detectar.

## Classes principais

Isolamento por consultório impede cruzamento de tenant. Identificadores `Seq` preservam unicidade. Relacionamentos e constraints defendem referências válidas. Regras financeiras protegem equilíbrio e unicidade operacional. O action ledger acrescenta rastreabilidade a ações críticas.

## Atomicidade

Quando um caso de uso exige múltiplas escritas coerentes, a transação pertence à fronteira de Application/adapter, nunca à página Runtime. Isso evita que uma resposta HTTP controle consistência de negócio.

## Leitura

Read models críticos são monitorados por budgets de consulta. Budget não é invariante de dado, mas protege uma propriedade operacional: uma leitura não deve tornar-se silenciosamente N+1 ou introduzir escrita.

## Teste

Schema contract, critical runtime smoke e query budgets rodam contra MySQL real na CI. A combinação de constraints e testes é intencional: nenhum dos dois substitui o outro.
