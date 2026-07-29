# Testes de propriedades

## Quando usar

Propriedades são adequadas quando muitos exemplos compartilham uma regra:

- conservação financeira;
- idempotência;
- ordenação temporal;
- cadeia de auditoria;
- escopo multitenant;
- máquina de estados;
- estimadores.

## Exemplos

**Financeiro:** soma dos componentes é igual ao total reconciliado.  
**Auditoria:** qualquer alteração em evento anterior invalida a cadeia posterior.  
**Idempotência:** processar o mesmo envelope duas vezes produz um único efeito.  
**Tenant:** trocar apenas o consultório do contexto nunca amplia acesso.  
**Tempo:** intervalos UTC semiabertos não duplicam instante de fronteira.

## Reprodutibilidade

Falhas aleatórias devem registrar seed e exemplo mínimo. Geradores evitam valores impossíveis que não pertencem ao domínio.
