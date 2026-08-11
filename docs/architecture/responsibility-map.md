# Mapa de responsabilidades

## Runtime
Roteamento, request, sessão, guards de borda, redirects e coordenação curta. Não contém persistência de negócio nem transações de caso de uso.

## Application
Casos de uso, services, ports e coordenação transacional sem conhecimento de HTTP. Sua superfície pública é integralmente caracterizada.

## Domain
Políticas, invariantes e regras que definem significado do negócio.

## Infrastructure
PDO, repositórios/adapters, filesystem, criptografia, persistência e mecanismos externos.

## Presentation
HTML, JSON e estruturas visuais. Formata; não autoriza nem persiste.

## Composition roots
Montagem de dependências concretas. Concentram acoplamento inevitável e impedem que ele se espalhe.

## Maestro
Orquestra trabalho server-side e diferido com supervisão operacional.

## Contratos
`tools/quality-gate`, `tools/runtime-input-boundary-check`, `tools/runtime-refactor-on-touch-check`, `tools/application-test-contract-check`, budgets MySQL e smokes HTTP são responsáveis por transformar esse mapa em propriedade verificável.

Quando uma nova função parecer caber em várias áreas, escolha a camada que possui a decisão, não a camada que possui o chamador atual.
