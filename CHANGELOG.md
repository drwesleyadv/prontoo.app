# Versão canônica

## 1.8.18.3 — Filtro Ativos inicial e Volume alinhado a Registros

- define Ativos como filtro inicial da lista global de Consultórios do Desenvolvedor.
- mantém Todos como filtro explícito e faz Limpar retornar ao estado padrão Ativos.
- faz a série Consulta do gráfico Volume usar a mesma variação líquida de database.json do card Registros.
- alinha as duas séries de Volume em 20 intervalos consecutivos de 24 horas, sem misturar janelas temporais diferentes.
- preserva a ordenação dos consultórios por login recente, a regra Vencendo de 10 dias e o schema atual.
