# Testes de propriedades e invariantes

Nem toda regra importante é melhor descrita por um exemplo específico. Propriedades expressam relações que devem valer para uma família de entradas e estados.

## Exemplos de propriedades

Tenant nunca muda implicitamente durante uma operação; read model não escreve; geração de sessão mais antiga não volta a ser válida; identificadores `Seq` permanecem não nulos e únicos; uma operação financeira confirmada não deve produzir duplicata equivalente.

## Relação com contratos

Algumas propriedades são verificadas por testes com dados variados; outras por analisadores estáticos ou constraints do banco. O nome “property test” é menos importante que provar a invariante no nível adequado.

## Boas práticas

Gere entradas que cubram limites e estados inválidos, mantenha o teste determinístico e registre o contraexemplo mínimo quando falhar. Não substitua um constraint de banco por teste probabilístico quando o banco pode garantir a propriedade sempre.
