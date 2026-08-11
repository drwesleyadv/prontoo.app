# Read model de recepção do paciente

Recepção precisa combinar dados do paciente e histórico operacional com poucas consultas. Tratar essa tela como sequência de pequenas buscas por item criaria N+1 e misturaria composição de apresentação com persistência.

## Desenho

Application oferece um serviço de leitura de histórico/recepção por port específico. Infrastructure monta a consulta necessária sob tenant. Runtime recebe a estrutura já pronta para a página.

## Budget

O cenário participa dos budgets MySQL. Uma otimização deve ser medida em número de operações e preservar semântica, não apenas parecer mais rápida em um teste local.

## Evolução

Se a recepção precisar de novo dado, avalie se ele pertence ao read model existente. Evite inserir consultas avulsas na Presentation ou no loop de renderização.
