# Arquitetura do Prontoo

## Objetivo

O Prontoo usa uma arquitetura PHP em camadas orientada por invariantes. Toda ação mutável deve ser identificada por um contrato exato, autorizada pela camada de aplicação e submetida aos guardiões de escopo, relações, estado e integridade antes da persistência.

## Camadas

### Core

Contém tipos matemáticos, decisões canônicas, invariantes de mutação, isolamento por consultório, workflows, integridade e verificadores arquiteturais. O Core não depende de aplicação, infraestrutura ou apresentação.

### Domain

Contém contratos e conceitos de negócio independentes de HTTP, sessão, PDO e renderização. Pode depender apenas do Core e do próprio Domain.

### Application

Orquestra casos de uso e define portas. A autorização de ações é resolvida aqui por `rota + ação real`, sem acesso direto a PDO, sessão ou HTML.

### Infrastructure

Implementa portas da aplicação para banco, matriz de permissões, cache e outros mecanismos externos. Toda persistência de provas operacionais fica nesta camada.

### Presentation

Recebe a requisição e adapta decisões da aplicação para HTTP. Não decide capacidades e não persiste diretamente.

### Composition

Monta as dependências concretas, carrega o runtime e mantém apenas adaptadores temporários estritamente finos para chamadas globais anteriores.

## Regra de dependência

As dependências apontam para dentro:

- Core → Core;
- Domain → Core, Domain;
- Application → Core, Domain, Application;
- Infrastructure → Core, Domain, Application, Infrastructure;
- Presentation → Core, Domain, Application, Presentation;
- Composition → todas as camadas, exclusivamente para montagem.

## Segurança

1. Ação POST não catalogada falha fechada.
2. Todas as capacidades exigidas pelo contrato precisam ser satisfeitas.
3. Escopo global, clínico, autenticado e público é verificado antes do handler.
4. Efeitos delegados não ampliam permissões do usuário.
5. A prova da decisão é persistida por uma porta de infraestrutura.
6. Escritas continuam submetidas ao núcleo de invariantes de mutação.
7. O CI verifica cobertura arquitetural de 100% dos arquivos PHP versionados.

## Compatibilidade

`enforce_action_integrity()` e `Prontoo\Core\Integrity\ActionProof` permanecem apenas como adaptadores finos para o dispatcher existente. Eles não contêm resolução de capacidade, regra de negócio ou persistência. A fonte de verdade é `Prontoo\Runtime\LayeredKernel`.

## Verificação automática

Execute:

```bash
php tools/architecture-check.php
```

A verificação falha quando encontra:

- arquivo PHP sem camada;
- dependência em direção proibida;
- arquivo nativo de camada sem namespace;
- acesso PDO fora de Infrastructure ou Composition;
- ação literal sem contrato;
- contrato sem rota ou arquivo-fonte;
- arquivo legado de autorização;
- divergência do manifesto arquitetural;
- cobertura inferior a 100%.
