# Versão canônica

## 1.9.14.3 — Aniversariantes nos filtros de Pacientes

- substitui o filtro Cadastro Incompleto por Aniversariantes na tela de Pacientes.
- mantém Hoje, Essa semana e Desistentes sem alteração de comportamento.
- filtra aniversariantes pelo dia e mês de nascimento correspondentes à data corrente.
- considera apenas pacientes ativos do consultório atual, preservando o isolamento existente.
- não altera schema, persistência, fluxo de cadastro nem regras dos demais filtros.
