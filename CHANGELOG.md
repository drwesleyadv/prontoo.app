# Versão canônica

## 1.8.17.7 — Volume restaurado para 30 dias

- mantém Velocidade com 1.440 pontos de um minuto nas últimas 24 horas.
- restaura Volume para o timeframe de 30 dias.
- representa Volume com exatamente 30 pontos consecutivos de 24 horas.
- soma Carregamento de Páginas e Consulta separadamente em cada intervalo diário.
- preserva pontos explícitos em zero quando um intervalo não possui eventos.
- restaura os resumos de Volume para 24 horas, 7 dias e 30 dias.
- adiciona contratos determinísticos para quantidade, distância e totais dos pontos sem alterar schema ou banco de dados.
