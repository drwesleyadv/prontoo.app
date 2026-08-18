# Design Tokens

O Design System do Prontoo usa Design Tokens como fonte canônica de identidade visual. Os arquivos editáveis ficam em `design/tokens/` e seguem o formato DTCG 2025.10. O Style Dictionary 5.5.0 compila essa fonte para CSS Custom Properties consumidas pelo runtime.

## Camadas

- `reference.tokens.json`: valores primitivos e paletas de referência.
- `system.tokens.json`: significado semântico, incluindo accent, superfícies, texto e estados.
- `component.tokens.json`: aliases específicos de componentes, sem duplicar valores de identidade.
- `themes.tokens.json`: escopos de tema Prontoo, consultório, público e somente leitura.
- `css-bridge.json`: ABI de compatibilidade gerada para nomes CSS já consumidos pelo produto durante a migração, sem autoridade de autoria visual.

Os arquivos `app/Presentation/Styles/tokens/*.css` são artefatos gerados. Não devem ser editados manualmente. `npm run tokens:build` os regenera e `npm run tokens:check` verifica equivalência determinística.

## Tema por consultório

A identidade do consultório entra no runtime como sementes de tema no `<body>`. Aliases dependentes da identidade são resolvidos nesse mesmo escopo, evitando que valores calculados em `:root` fiquem presos à cor padrão. Cores semânticas de sucesso, aviso, erro e informação permanecem independentes do accent do consultório.

## Contratos

`tools/design-tokens-contract.mjs` exige:

- fontes DTCG presentes e compilação determinística;
- ausência de definições canônicas `--pt-ref-*`, `--pt-sys-*` e `--pt-cmp-*` fora do gerador;
- propagação do accent em fixtures verde, azul, vinho e violeta;
- invariância das cores semânticas entre temas;
- preservação do baseline computado existente pelo `Presentation UX Contract`.

O workflow `.github/workflows/presentation-ux.yml` executa o contrato DTCG antes do baseline computado. Assim, alterações futuras de identidade só podem ser publicadas se a fonte canônica, os artefatos gerados, a propagação multitema e a experiência visual permanecerem coerentes.
