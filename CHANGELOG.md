# Versão canônica

## 1.9.15.3 — Correção da adição de recorrências

- Isola a inicialização da recorrência do módulo de elementos flutuantes da Agenda.
- Garante que o botão de adição registre seu listener de forma própria e idempotente.
- Preserva a composição inline por Procedimento e Data/Hora sem alterar o backend transacional.
- Mantém a inclusão sucessiva de recorrências e a limpeza da Data/Hora após cada adição.
- Adiciona contrato automatizado para impedir regressão no clique, clonagem e inclusão da linha de recorrência.
