# Design System normativo

## Objetivo

O Design System do Prontoo é uma infraestrutura de produto verificável. Uma decisão visual reutilizável deve possuir token; um componente deve possuir contrato de anatomia e semântica; estados devem usar a taxonomia canônica; e a CI deve detectar regressões de apresentação e acessibilidade antes do merge.

## Camadas de responsabilidade

A arquitetura segue a sequência `DTCG -> Resolver -> bindings de plataforma -> primitivas -> componentes -> composição -> produto`.

DTCG armazena decisões de design. O Resolver declara conjuntos e contextos. Bindings adaptam tokens ao CSS sem contaminar o formato interoperável. CSS declara layout e comportamento visual. Renderers PHP são responsáveis por HTML e semântica. JavaScript adiciona somente comportamento que não possa ser obtido nativamente.

## Estados

A taxonomia canônica é `default`, `hover`, `focus-visible`, `active`, `selected`, `disabled`, `readonly`, `invalid` e `loading`. Um componente declara somente os estados que realmente suporta.

Quando existir estado ARIA equivalente, a semântica é a fonte do estado: `aria-current`, `aria-selected`, `aria-pressed`, `aria-expanded`, `aria-disabled` e `aria-invalid`. Classes visuais não substituem estado acessível.

## Acessibilidade

WCAG 2.2 AA é requisito de componentes novos e modificados. Padrões interativos seguem WAI-ARIA APG quando HTML nativo não for suficiente. O contrato cobre nome acessível, foco, teclado, target size, forced-colors e preferência por movimento reduzido.

`button` prefere elemento nativo. `filter-chip` usa `aria-current` quando representa navegação e `aria-pressed` quando representa toggle. `dialog` exige nome acessível, foco inicial, contenção de foco quando modal, Escape e restauração do foco. Linhas não transformam um container genérico em botão quando ações nativas podem representar a interação.

## Responsividade

Primitivas reutilizáveis respondem primeiro ao espaço do container, não ao nome da rota nem exclusivamente ao viewport. `data-ds-container` cria o contexto de inline-size e composições adaptativas podem usar Container Queries. Media queries globais continuam válidas para características realmente ligadas ao viewport ou às preferências do usuário.

## Cascade

O CSS moderno usa as layers `ds-foundation`, `ds-primitives`, `ds-components` e `ds-composition`. A ordem é estável e faz parte do contrato. A folha histórica permanece fora dessa arquitetura até ser migrada por touch; sua dívida não pode crescer.

## Catálogo executável

`tests/presentation/ds-lab.html` reúne primitivas em isolamento. Ele é consumido pelos gates de acessibilidade e regressão visual. O catálogo testa estados, semântica, containers e identidades sem criar uma segunda implementação dos componentes de produto.

## Regressão

A malha de Presentation executa quatro classes de verificação: contrato DTCG/Resolver, contrato de componentes, acessibilidade automatizada com axe-core e Playwright e regressão visual do DS Lab. O baseline visual do produto existente continua independente e também precisa permanecer verde.

## Governança

O lifecycle é `experimental -> stable -> deprecated -> removed`. Tokens usam `$deprecated` quando necessário. Componentes deprecated declaram substituição. Artefatos removed não permanecem como alias de compatibilidade no runtime; referências históricas pertencem ao Git.

Uma nova variante só deve existir quando expressa semântica ou comportamento distinto. Diferenças puramente locais devem ser resolvidas por composição das primitivas existentes, não por classes versionadas ou aliases por rota.
