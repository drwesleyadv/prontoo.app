# Versão canônica

## 1.9.14.5 — Contagem integrada ao filtro ativo de Pacientes

- Remove a informação N encontrados. do rodapé direito da listagem de Pacientes.
- Exibe o total recuperado diretamente no título do filtro ativo no formato Nome (N).
- Mantém a contagem sincronizada ao restaurar o filtro após uma busca dinâmica.
- Normaliza o chip de busca para Encontrados (N), sem badge numérico separado.
- Preserva as regras de acesso do filtro Todos e todos os critérios de consulta existentes.
- Não altera schema, persistência ou limites dos filtros existentes.
