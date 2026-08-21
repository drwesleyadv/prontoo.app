# Design System canônico

## Fonte normativa

O Prontoo usa o Design Tokens Format Module DTCG 2025.10 como fonte canônica das decisões de design e o Design Tokens Resolver Module 2025.10 para declarar composição e contexto. Os arquivos `design/tokens/*.tokens.json` contêm somente dados compreensíveis como tokens: valores, aliases, tipos, descrições, depreciação e extensões que não sejam essenciais à interpretação do valor.

Metadados de plataforma não pertencem aos tokens. Nomes de CSS Custom Properties, seletores, ordem de emissão, fases e valores de adaptação ficam em `design/platform/css.bindings.json`. O compilador também possui uma convenção determinística para tokens que não necessitem binding explícito.

O Format Module 2025.10 não publica JSON Schema oficial. Por isso, os arquivos `.tokens.json` são validados pelo contrato executável `tools/design-tokens-contract.mjs`, sem `$schema` inventado. O documento `design/resolvers/application.resolver.json`, por outro lado, usa o schema oficial do Resolver Module 2025.10.

## Resolução

`design/resolvers/application.resolver.json` é a lista canônica de fontes consumidas pelo compilador. A resolução contém os sets Reference, System, Component e Runtime e um modifier `surface` com os contextos `application` e `public`.

A identidade do consultório continua resolvida em runtime por CSS Custom Properties no `body`. Sucesso, aviso, erro e informação permanecem semânticos e independentes da identidade institucional. O contrato multitema continua verificando verde, azul, vinho e violeta.

## Compilação

`style-dictionary@5.5.0` compila os tokens resolvidos para `design/generated/tokens.css`. O artefato público `public/assets/presentation.css` é construído deterministicamente nesta ordem:

1. importação canônica de fontes;
2. `design/generated/tokens.css`;
3. `design/styles/application.css`, mantido como compatibilidade comportamental sob ratchet;
4. `design/styles/standards.css`, fonte das novas primitivas normativas.

`application.css` não recebe novas definições de tokens e não pode aumentar os budgets históricos de `!important` ou escopos de rota. `standards.css` não aceita `!important`, escopo por rota, cores literais ou dimensões visuais arbitrárias que possam ser expressas por token.

## Arquitetura moderna de CSS

As novas primitivas usam Cascade Layers na ordem `ds-foundation`, `ds-primitives`, `ds-components` e `ds-composition`. Responsividade reutilizável é component-first e usa Container Queries. `forced-colors` e `prefers-reduced-motion` são preferências de usuário cobertas pelo Design System.

O objetivo não é traduzir HTML, ARIA ou comportamento para DTCG. DTCG decide valores e relações semânticas; os contratos de componente definem anatomia e estados; CSS compõe e responde ao espaço disponível; renderers PHP produzem semântica; JavaScript implementa comportamento quando necessário.

## Componentes e governança

Os contratos machine-readable vivem em `design/components/*.component.json`. Cada componente declara anatomia, variantes, estados, tokens consumidos, semântica e comportamento de container. O lifecycle canônico é `experimental`, `stable`, `deprecated`, `removed`.

Tokens podem usar `$deprecated` conforme o DTCG. Componentes deprecated precisam declarar substituição. Aliases removidos não podem reaparecer em código ativo; o histórico permanece no Git.

## Acessibilidade e verificação

O Design System assume WCAG 2.2 nível AA e padrões WAI-ARIA APG como contrato. `tests/presentation/ds-lab.html` é o catálogo executável de primitivas. A CI usa Playwright, axe-core, forced-colors, target-size e regressão visual determinística em desktop e viewport compacto.

## Fluxo de alteração

Valores reutilizáveis entram em `design/tokens/*.tokens.json`. Bindings específicos do CSS entram em `design/platform/css.bindings.json` somente quando a convenção determinística não for suficiente. Anatomia e semântica entram nos contratos em `design/components/`. Comportamento moderno entra em `design/styles/standards.css`.

Depois execute `node tools/design-tokens-build.mjs --write`, `php tools/presentation-css-build --write` e os contratos de Presentation. Os modos `--check` devem permanecer sem drift.
