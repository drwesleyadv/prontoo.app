# Autorização

## Catálogo de ações

Toda mutação protegida usa ação exata, associada a rota, escopo, política e capacidades requeridas.

## Processo

1. a apresentação identifica a ação;
2. o catálogo resolve o contrato;
3. o runtime revalida usuário, consultório e cargos;
4. capacidades são avaliadas;
5. ação desconhecida ou contexto indeterminado é negado;
6. a mutação passa pelo núcleo de invariantes.

## Escopos

**Consultório:** limitado ao tenant ativo.  
**Global:** reservado ao Desenvolvedor reautenticado.

## Proibições

- autorização baseada apenas em elemento oculto da interface;
- confiar em cargo armazenado somente na sessão;
- aceitar ação genérica quando existe contrato específico;
- decidir acesso dentro do adaptador de persistência;
- ampliar escopo por filtro enviado pelo cliente.
