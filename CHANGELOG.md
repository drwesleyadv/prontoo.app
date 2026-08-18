# Versão canônica

## 1.8.18.2 — Filtros e ordenação dos consultórios por atividade

- reordena os filtros da lista global para Todos, Ativos, Vencendo e Somente Leitura.
- considera Vencendo os consultórios cuja assinatura termina entre hoje e os próximos 10 dias.
- ordena todas as listas pelo login mais recente entre colaboradores ativos do consultório, do mais recente para o mais antigo.
- mantém consultórios sem login de colaborador ao final e não altera schema ou banco de dados.
- preserva a busca rápida e os demais controles existentes da tela de Consultórios do Desenvolvedor.
