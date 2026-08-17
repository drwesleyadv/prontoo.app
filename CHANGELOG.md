# Versão canônica

## 1.8.17.6 — Telemetria minuto a minuto

- renomeia o gráfico Últimas 24 horas para Velocidade.
- padroniza Velocidade e Volume em 1.440 buckets de um minuto completo.
- representa minutos sem Carregamento de Páginas ou Consulta com pontos explícitos em zero.
- liga os valores consecutivos por segmentos retos para formar picos sem interpolação curva.
- mostra exatamente uma marca centralizada por hora no eixo inferior.
- mantém contornos visíveis nas duas séries e atualização automática a cada minuto.
- adiciona contratos determinísticos para buckets, agregação, geometria e escala horária sem alterar schema ou banco de dados.
