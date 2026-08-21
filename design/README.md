# Design System

A árvore `design/` contém as fontes normativas de apresentação do Prontoo.

- `tokens/`: valores e aliases em DTCG 2025.10, sem detalhes de plataforma.
- `resolvers/application.resolver.json`: composição e contextos pelo Resolver Module 2025.10.
- `platform/css.bindings.json`: adaptação de tokens ao CSS; arquivo gerado/mantido pela toolchain.
- `components/`: contratos machine-readable de anatomia, estados, semântica e lifecycle.
- `generated/tokens.css`: artefato derivado; não editar manualmente.
- `styles/application.css`: comportamento histórico sob ratchet.
- `styles/standards.css`: novas primitivas, Cascade Layers, Container Queries e preferências de usuário.
- `design-system.contract.json`: política normativa e padrões obrigatórios.

A documentação detalhada está em `docs/architecture/design-system.md` e `docs/architecture/design-tokens.md`.
