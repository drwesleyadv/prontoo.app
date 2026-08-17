# Versão canônica

## 1.8.17.11 — Rodapé com geometria do Volume

- reutiliza no rodapé a projeção canônica 960 por 210 do gráfico Volume, inclusive escala conjunta e paddings.
- preserva os mesmos 30 pontos e a mesma ordem de Carregamento de Páginas e Consulta.
- renderiza duas áreas inferiores preenchidas, sem contornos e sem pontos terminais, como Volume.
- arredonda somente uma vizinhança horizontal curta de cada pico e vale, mantendo as encostas originais fora dos cantos.
- usa nas áreas públicas #1f6f56 e #347963 com opacidade 0.5 e nas áreas autenticadas as cores de destaque do consultório com a mesma opacidade.
- remove o fade do rodapé para não alterar os tons do gráfico.
- torna o renderer de servidor canônico e elimina o redesenho e o refresh de geometria pelo cliente.
- não altera schema nem banco de dados.
