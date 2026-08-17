# Versão canônica

## 1.8.17.10 — Montanhas de telemetria no rodapé

- renderiza duas linhas decorativas de telemetria em todas as áreas HTML a partir das mesmas séries de 30 pontos de Volume.
- mantém as encostas retas e arredonda somente os vértices com curvas quadráticas para formar o perfil de montanhas.
- usa nas áreas públicas as cores #1f6f56 e #347963 de Volume com opacidade 0.5.
- expõe publicamente somente as coordenadas normalizadas do desenho, sem contagens absolutas nem nova rota pública de telemetria.
- usa nas áreas autenticadas as cores forte e principal de destaque do consultório ou do ambiente Desenvolvedor.
- marca o wrapper renderizado no servidor como pronto e preserva footer_telemetry_wave apenas como fallback privado.
- mantém asset_version em 1.8.17.9 porque nenhum asset estático JS, CSS ou binário foi alterado.
- atualiza contratos executáveis e documentação sem alterar schema ou banco de dados.
