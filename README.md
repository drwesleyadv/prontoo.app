# Prontoo

Prontoo é uma aplicação web monolítica modular para a operação de consultórios e clínicas de pequeno porte. Reúne agenda, jornada do paciente, pessoas, documentos, tarefas, equipe, financeiro, auditoria e rotinas operacionais supervisionadas pelo Maestro.

## Comece por aqui

1. Leia [a visão arquitetural](docs/architecture/overview.md).
2. Consulte [o glossário](docs/glossary.md).
3. Configure o ambiente conforme [o guia de instalação](docs/operations/installation.md).
4. Execute as validações descritas em [estratégia de testes](docs/testing/strategy.md).
5. Antes de alterar uma decisão estrutural, consulte os [ADRs](docs/adr/).

## Requisitos

- PHP 8.4 ou superior
- MySQL 8.0.30 ou superior
- extensão `pdo_mysql`
- HTTPS no ambiente publicado
- banco vazio para instalação limpa

## Mapa do código

| Diretório | Responsabilidade |
|---|---|
| `app/Core` | invariantes, integridade, isolamento, decisões canônicas e políticas transversais |
| `app/Domain` | regras e contratos de negócio independentes de HTTP e persistência |
| `app/Application` | casos de uso, portas e coordenação da aplicação |
| `app/Infrastructure` | PDO, persistência, credenciais e adaptadores externos |
| `app/Presentation` | adaptadores HTTP e entrada da interface |
| `app/Runtime` | composição e execução do runtime |
| `app/Pages`, `app/Admin`, `app/Auth`, `app/Ui` | fronteiras de apresentação ainda parcialmente transitórias |
| `br` | bootstrap e runtime web |
| `cron` | execução do Maestro |
| `tools` | verificadores e utilitários de manutenção |
| `docs` | documentação arquitetural, de domínio, segurança e operação |

A regra central é: dependências apontam para dentro; somente a composição conecta todas as camadas.

## Comandos de validação

```bash
find . -name '*.php' -not -path './ssd/*' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
php tools/code-comment-check.php
php tools/documentation-check.php
php tools/security-regression-check.php
php tools/architecture-check.php
php tools/schema-check.php
php tools/install-security-check.php
```

## Regras que não podem ser quebradas

- toda ação protegida deve possuir contrato exato;
- usuário, consultório e cargos devem ser revalidados no banco;
- ações desconhecidas falham fechadas;
- mutação e prova de mutação confirmam na mesma transação;
- dados de um consultório não podem atravessar para outro;
- valores financeiros são representados em centavos inteiros;
- DDL permanece bloqueado no runtime comum;
- o Desenvolvedor usa MFA obrigatório;
- sessões expiram após 60 minutos de inatividade;
- trabalhos secundários devem ser duráveis e idempotentes.

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
