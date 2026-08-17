# Versão canônica

## 1.8.17.13 — Registros por variação real e sobreposição no rodapé

- define Registros como a soma dos deltas do total exato de linhas capturado pelo Maestro, excluindo o saldo inicial.
- mantém 20 dias móveis em database.json e compara os 10 dias recentes aos 10 imediatamente anteriores, preservando deltas negativos.
- alinha o rodapé em 20 intervalos equivalentes, com Carregamento de Páginas e Consulta alimentada pela variação líquida de registros.
- usa a mesma cor nas duas áreas do rodapé, com 50% de opacidade em cada uma para evidenciar sobreposição e cruzamentos.
- mantém Volume com sua métrica própria de consultas SQL e não altera schema nem banco de dados.
