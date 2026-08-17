# Versão canônica

## 1.8.17.12 — Identidade histórica das ondas de telemetria

- mantém os mesmos 30 pontos diários de Carregamento de Páginas e Consulta usados por Volume.
- restaura o renderer histórico em SVG 1000×250 com curvas Bézier cúbicas contínuas e fechamento inferior.
- restaura altura de 15vh limitada a 64–180px, opacidade 0.5 e máscara vertical até 34%.
- restaura a ordem visual histórica: Carregamento de Páginas no tom principal e Consulta no tom forte.
- mantém o renderer no servidor, sem serializar contagens públicas nem reabrir o Status ou rotas públicas de telemetria.
- não altera schema nem banco de dados.
