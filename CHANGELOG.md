# Versão canônica

## 1.8.18.6 — Arquitetura de design independente do CSS legado

- remove integralmente app/Presentation/Styles e o manifesto de 1.074 slices da arquitetura CSS anterior.
- substitui o payload Base64 de compatibilidade por tokens runtime DTCG estruturados e auditáveis.
- consolida os tokens gerados em design/generated/tokens.css e os estilos comportamentais em design/styles/application.css.
- simplifica presentation.css para uma montagem determinística sem offsets, hashes por slice ou bridge legado.
- mantém o contrato multitema e o baseline computado sem alteração observável de geometria, tipografia, espaçamento ou fluxos.
- preserva banco, schema e regras de negócio enquanto reduz arquivos-fonte, metadados e custo de manutenção do Design System.
