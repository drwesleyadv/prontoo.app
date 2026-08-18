# Versão canônica

## 1.8.18.7 — Restaura Tempo médio em Métricas

- restaura o quarto card Tempo médio na tela Métricas do Desenvolvedor.
- calcula o valor somente com page loads cuja duração foi observada pela fonte canônica speed.json.
- mantém a comparação móvel dos 10 dias recentes contra os 10 dias imediatamente anteriores.
- restaura o grid responsivo de quatro KPIs em 4x1 no desktop e 2x2 no mobile.
- adiciona contrato executável para impedir nova supressão do card e excluir eventos sem speed_observed da média.
- preserva os gráficos Velocidade e Volume, o banco de dados e o schema sem novas coletas.
