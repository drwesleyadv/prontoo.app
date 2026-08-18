# Design System canônico

## Arquitetura

O Design System usa DTCG 2025.10 como fonte canônica de valores. Os arquivos `design/tokens/*.tokens.json` concentram Reference, System, Component, temas e tokens runtime necessários à ABI do produto. O compilador fixado em `style-dictionary@5.5.0` produz um único artefato `design/generated/tokens.css`.

Os seletores, estados, layouts e regras comportamentais que não são tokens vivem em `design/styles/application.css`. O artefato público `public/assets/presentation.css` é montado deterministicamente com a importação canônica de fontes no topo, seguida do bundle DTCG e do CSS comportamental.

A arquitetura anterior de múltiplos arquivos em `app/Presentation/Styles`, o manifesto de slices e o bridge CSS codificado não fazem parte da árvore canônica. O build falha se qualquer um desses artefatos reaparecer.

## Auditoria de migração

A arquitetura anterior possuía 23 arquivos CSS, 809.496 bytes de fontes, 1.074 slices de reconstrução, manifesto de 347.023 bytes e bridge de 32.369 bytes. O bridge continha 16.817 bytes de CSS codificado. A auditoria demonstrou que os 22 slices de tokens podiam ser movidos para o início da folha sem drift e que 316 declarações runtime ainda necessárias podiam ser representadas como DTCG normal.

O CSS comportamental resultante possui 786.011 bytes lineares. O baseline computado permaneceu estável em 12 cenários por 33 superfícies tanto com os tokens movidos quanto com as declarações residuais estruturadas.

## Invariantes

- Tokens `--pt-ref-*`, `--pt-sys-*` e `--pt-cmp-*` só podem ser definidos pela fonte DTCG.
- A identidade do consultório é resolvida em runtime por custom properties no `body`.
- Sucesso, aviso, erro e informação permanecem semânticos e independentes da cor de identidade.
- O contrato multitema cobre verde, azul, vinho e violeta.
- O baseline computado continua bloqueando regressões de geometria, tipografia, espaçamento e fluxos.
- `presentation.css` deve ser reproduzível exatamente pelas fontes canônicas sob `design/`.
- Payloads Base64 de CSS, offsets de slices e manifestos de cascata são proibidos.

## Fluxo de alteração

Alterações de valor visual entram em `design/tokens/*.tokens.json`. Alterações de seletor ou comportamento entram em `design/styles/application.css`. Depois execute `node tools/design-tokens-build.mjs --write` e `php tools/presentation-css-build --write`. Os modos `--check` são usados pela CI e não podem aceitar drift.
