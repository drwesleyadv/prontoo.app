# Autorização

Autorização responde se um usuário, em um consultório e contexto específicos, pode executar uma ação. Ela não deve ser reduzida a “está logado?” nem duplicada em condicionais de página.

## Catálogo de ações

Application mantém definições e requisitos de autorização por grupos de ações. Runtime consulta a capacidade necessária antes de delegar o caso de uso. O objetivo é manter nomes e requisitos centralizados.

## Tenant e papel

Vínculo com consultório e função/cargo participam da decisão. Identificadores recebidos pelo request nunca substituem o tenant autenticado.

## Fail-closed

Se informação necessária para decidir permissão estiver ausente ou inconsistente, a ação deve ser negada. Erros de infraestrutura não podem virar autorização implícita.

## Testabilidade

Services de autorização e providers possuem contratos próprios, permitindo testar política sem renderizar páginas.
