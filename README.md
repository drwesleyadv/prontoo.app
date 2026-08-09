# Prontoo

Prontoo é uma aplicação web monolítica modular para consultórios e clínicas de pequeno porte. A arquitetura atual é um monólito PHP 8.4 em camadas, com núcleo de invariantes, composição explícita de runtime e fronteiras de compatibilidade classificadas pelo contrato arquitetural.

## Comece por aqui

1. Leia [a visão arquitetural](docs/architecture/overview.md).
2. Consulte [o mapa de responsabilidades](docs/architecture/responsibility-map.md) e [a regra de dependências](docs/architecture/dependencies.md).
3. Consulte [o glossário](docs/glossary.md).
4. Configure o ambiente conforme [o guia de instalação](docs/operations/installation.md).
5. Execute as validações descritas em [estratégia de testes](docs/testing/strategy.md).
6. Antes de alterar uma decisão estrutural, consulte os [ADRs](docs/adr/).

## Runtime suportado

- PHP **8.4.x exclusivamente** no web, CLI e CI;
- MySQL 8.0.30 ou superior;
- extensão `pdo_mysql`;
- HTTPS no ambiente publicado;
- banco vazio para instalação limpa.

## Mapa atual do código

| Diretório | Responsabilidade arquitetural |
|---|---|
| `app/Core` | invariantes canônicas, workflows, tempo, isolamento, integridade, políticas estruturais e arquitetura |
| `app/Domain` | regras e contratos de negócio; `Domain/Legacy` contém unidades já classificadas como domínio extraídas da base histórica |
| `app/Application` | casos de uso, portas e catálogo/contratos de autorização |
| `app/Infrastructure` | PDO, persistência, credenciais vivas, auditoria, integridade e adaptadores externos; `Infrastructure/Legacy` mantém operações históricas já isoladas nesta camada |
| `app/Presentation` | adaptadores HTTP/UI e views; `Presentation/Legacy` contém apresentação histórica já separada das demais responsabilidades |
| `app/Runtime` | composition root, bootstrap, módulos, roteamento, autorização composta, serviços e coordenação de prontidão/manutenção; `Runtime/Legacy` preserva compatibilidade operacional |
| `app/Admin`, `app/Auth`, `app/Pages`, `app/Ui` | fronteiras de apresentação compatíveis, sujeitas ao teto de transição do contrato arquitetural |
| `app/Support`, `app/Database` | infraestrutura histórica e fachadas de composição explicitamente classificadas pelo `LayerMap` |
| `br`, `public` | front controllers e borda HTTP |
| `cron` | entrada CLI do Maestro |
| `tools` | contratos executáveis, auditorias e utilitários de release/manutenção |
| `docs` | documentação arquitetural, domínio, segurança, operação, banco, testes e performance |

A classificação efetiva é definida por `app/Core/Architecture/LayerMap.php` e verificada por `tools/architecture-check.php`. O alvo é **100% dos arquivos PHP versionados classificados**, com pelo menos **278 unidades nativas** e no máximo **51 entrypoints e ferramentas procedurais não classificados como unidades nativas** na baseline atual.

## Regra de dependências

`Core → Core`; `Domain → Core/Domain`; `Application → Core/Domain/Application`; `Infrastructure → Core/Domain/Application/Infrastructure`; `Presentation → Core/Domain/Application/Presentation`; somente `Composition` pode conectar todas as camadas.

Pontos canônicos:

- autorização: `Prontoo\Runtime\LayeredKernel::enforceAction`;
- mutações: `Prontoo\Core\Invariant\InvariantKernel::guardMutation`;
- composição modular: `app/Runtime/Modules`;
- prontidão/manutenção: `app/Runtime/Boot/RuntimeBootCoordinator.php`;
- catálogo de rotas: `app/Runtime/Routing/RouteCatalog.php`;
- resposta JSON: `app/Presentation/Http/JsonResponder.php`;
- Maestro: `cron/maestro.php`.

## Prontidão do runtime

Login e rotas normais executam apenas a prontidão mínima: contrato de schema e `integrity lightcheck`. Manutenção profunda é separada e não pertence ao hot path. Se storage/lock de prontidão não estiver gravável, a validação mínima pode executar sem cache, preservando os checks fail-closed.

## Comandos de validação

```bash
find . -name '*.php' -not -path './ssd/*' -not -path './vendor/*' -not -path './node_modules/*' -print0 | xargs -0 -n1 php -l
php tools/release-contract-reconcile --check
php tools/code-comment-check.php
php tools/documentation-check.php
php tools/security-regression-check.php
php tools/architecture-check.php
php tools/schema-check.php
php tools/install-security-check.php
php tools/login-post-password-runtime-check
```

O workflow `.github/workflows/architecture.yml` complementa esses contratos com PHP 8.4/MySQL 8 reais, verificação de símbolos, auditoria SOLID, regressão do Maestro e smoke matrix do runtime crítico.

## Invariantes obrigatórias

- toda ação protegida possui contrato exato e ação desconhecida falha fechada;
- usuário, consultório e cargos/capacidades são revalidados no banco;
- mutação autorizada e sua prova confirmam na mesma transação ou fazem rollback juntas;
- dados de um consultório não atravessam para outro;
- valores financeiros usam centavos inteiros e operações críticas são transacionais;
- DDL permanece bloqueado no runtime comum;
- Desenvolvedor usa MFA obrigatório; elevação global exige senha e MFA recentes;
- sessões expiram após 60 minutos de inatividade e obedecem à geração canônica do usuário;
- trabalhos secundários são duráveis, idempotentes e supervisionados pelo Maestro;
- release, versão, manifestos e hashes são reconciliados deterministicamente e a CI é read-only para divergências.

## Documentação

- [Índice completo](docs/index.md)
- [Arquitetura](docs/architecture/overview.md)
- [Domínios](docs/domain/)
- [Segurança](docs/security/)
- [Operação](docs/operations/)
- [Banco de dados](docs/database/)
- [Testes](docs/testing/)
- [Contribuição](CONTRIBUTING.md)
- [Política de segurança](SECURITY.md)
- [Histórico de versões](CHANGELOG.md)

## Licença e acesso

O repositório é privado. A autorização de acesso não implica autorização de publicação, distribuição ou uso fora do ambiente do Prontoo.
