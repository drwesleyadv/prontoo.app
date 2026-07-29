# Integridade financeira

## Unidade canônica

Todos os valores são centavos inteiros. A interface pode aceitar e exibir reais, mas a persistência e os cálculos usam inteiros.

## Operações

- soma e subtração sem ponto flutuante;
- arredondamento apenas na conversão de entrada;
- ajustes posteriores são compensatórios;
- lançamentos confirmados não são reescritos silenciosamente;
- fechamento produz snapshot reconciliado.

## Propriedades testáveis

- conservação de centavos;
- soma independente da ordem;
- compensação restaura saldo esperado;
- repetição idempotente não duplica lançamento;
- rollback não altera saldo;
- isolamento impede composição entre consultórios.

## Investigação

Divergência deve preservar lançamentos originais, cálculo reproduzível e evidência da correção.
