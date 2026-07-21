# Arquitetura do Prontoo

## Objetivo

O Prontoo usa uma arquitetura PHP em camadas orientada por invariantes. Toda ação mutável precisa possuir contrato exato, credencial viva, prova de autorização persistida e escrita compatível com escopo, relações, contexto e workflow.

## Camadas

### Core

Contém tipos matemáticos, decisões canônicas, invariantes de mutação, isolamento por consultório, workflows, integridade e verificadores arquiteturais. O Core não depende de aplicação, infraestrutura ou apresentação.

### Domain

Contém contratos e conceitos de negócio independentes de HTTP, sessão, PDO e renderização. Pode depender apenas do Core e do próprio Domain.

### Application

Orquestra casos de uso e define portas. A autorização é resolvida por `rota + ação real`, sem PDO, sessão ou HTML.

### Infrastructure

Implementa portas da aplicação para banco, credenciais vivas, matriz de permissões e persistência das provas de autorização.

### Presentation

Recebe a requisição e adapta decisões da aplicação para HTTP. Não decide capacidades e não persiste diretamente.

### Composition

Monta as dependências concretas, carrega o runtime e mantém somente fronteiras transitórias finas para chamadas globais anteriores.

## Regra de dependência

As dependências apontam para dentro:

- Core → Core;
- Domain → Core, Domain;
- Application → Core, Domain, Application;
- Infrastructure → Core, Domain, Application, Infrastructure;
- Presentation → Core, Domain, Application, Presentation;
- Composition → todas as camadas, exclusivamente para montagem.

## Segurança e verificabilidade

1. Ação POST não catalogada falha fechada.
2. Todas as capacidades exigidas pelo contrato precisam ser satisfeitas.
3. Antes de conceder capacidades, a infraestrutura revalida no banco o usuário ativo, o consultório ativo, os vínculos e todos os cargos ativos, ou a condição viva de administrador global.
4. `admin:*` exige simultaneamente escopo global, usuário ativo, contexto global administrativo e `is_global_admin=1` no banco.
5. Capacidades clínicas podem ser satisfeitas por qualquer cargo ativo do usuário no consultório atual.
6. A decisão autorizadora é persistida antes do handler. Se a prova de uma ação permitida não puder ser gravada, a alteração é bloqueada com falha fechada.
7. Efeitos delegados não ampliam permissões do usuário.
8. Escritas permanecem submetidas ao núcleo de invariantes de mutação.
9. O CI confronta cada ação literal com contratos declarados para o mesmo arquivo-fonte.

## Métricas arquiteturais

A cobertura de classificação e a migração nativa são métricas diferentes:

- **Cobertura de classificação:** todos os arquivos PHP versionados precisam pertencer a uma camada; alvo obrigatório de 100%.
- **Cobertura nativa:** arquivos namespaced que já obedecem diretamente às fronteiras das camadas.
- **Arquivos transitórios:** módulos procedurais ainda classificados e protegidos pelo núcleo, mas não reescritos internamente como componentes nativos.

A política `php-layered-invariants-v2` impede regressão: o número de arquivos nativos não pode ficar abaixo de 11 e o número de transitórios não pode ultrapassar 79. Esses valores são uma linha de base auditável, não uma alegação de migração integral do código.

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
- arquivo nativo sem namespace;
- acesso PDO fora de Infrastructure ou Composition;
- ação literal sem contrato no mesmo arquivo-fonte;
- contrato sem rota ou arquivo-fonte;
- arquivo legado de autorização;
- divergência do manifesto arquitetural;
- cobertura classificatória inferior a 100%;
- regressão abaixo do piso nativo ou acima do teto transitório;
- falha nos autotestes de autorização, credenciais, prova, middleware ou invariantes de mutação.

## Persistência limpa r7 — camada 2

A versão 1.7.20.6 adota `app/Database/schema.sql` como contrato exclusivo de instalação em banco vazio. A coluna `Seq` é gerada nativamente por tabela; autorização e mutação convergem em `pi_action_ledger`; a auditoria grava dimensões inline. A equivalência das tabelas operacionais é certificada por `app/Database/operational-schema.contract.json`, e `tools/schema-check.php` valida o contrato estático e uma instalação real em MySQL 8.

Metas de suporte:

- nenhuma tabela global de sequência;
- nenhuma atualização posterior apenas para preencher `Seq`;
- uma evidência persistente por ação protegida;
- ausência de dicionários auxiliares na auditoria;
- migração estrutural in-place proibida para a revisão r7.
