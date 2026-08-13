# Prontoo

Prontoo é uma aplicação web em PHP para a operação cotidiana de consultórios e clínicas pequenas. Para quem usa o sistema, o objetivo é simples: organizar pacientes, agenda, documentos, tarefas, equipe, financeiro e rotinas operacionais em uma interface direta. Por baixo dessa simplicidade há uma arquitetura deliberadamente rigorosa, porque dados clínicos, financeiros, identidades e permissões não toleram ambiguidade estrutural.

## Estado atual

A versão canônica é `1.8.13.7`, executada exclusivamente na família PHP 8.4 e com MySQL 8.0.30 ou superior. A arquitetura foi consolidada em `1.8.11.1`; desde então o projeto opera em modo de manutenção arquitetural: não se abre um novo ciclo de refatoração sem invariante quebrada ou risco material de produto.

A regra central é simples: **o Runtime coordena; Application expressa casos de uso; Domain contém regras; Infrastructure implementa mecanismos; Presentation renderiza saída**. As dependências perigosas são verificadas por código, não por convenção informal.

## O que está protegido por contrato

No Runtime, SQL de negócio, PDO direto, transações de caso de uso, `OperationGateway` e adapters concretos fora dos composition roots têm orçamento zero. A suíte de Application caracteriza 100% das entradas públicas catalogadas: 49 casos críticos, 30 services, 15 ports e 127 assertivas rastreáveis dentro da suíte rápida. O Architecture Contract também executa MySQL real, budgets de consultas, segurança de instalação, login, logout global, Maestro e smokes do front controller.

A dívida residual é explícita e monotônica. Hotspots de input adapter acima de 500 linhas, referências diretas a Infrastructure e acessos ao gateway genérico podem diminuir, mas não crescer. Quando um hotspot é tocado, `tools/runtime-refactor-on-touch-check` exige redução mensurável de dívida.

## Onde começar

- `AGENTS.md`: guardrails operacionais para IA agêntica e automações;
- `docs/index.md`: mapa de toda a documentação.
- `docs/architecture/overview.md`: modelo mental da arquitetura.
- `docs/architecture/layers.md`: responsabilidades por camada.
- `docs/security/threat-model.md`: ameaças e controles.
- `docs/testing/strategy.md`: como os contratos executáveis defendem o sistema.
- `CONTRIBUTING.md`: regras para alterar o código sem degradar a arquitetura.

## Fonte de verdade

Documentação explica intenção. O comportamento normativo está nos contratos executáveis, especialmente `app/architecture.manifest.json`, `app/application.test-contract.json`, `version.json` e nos verificadores em `tools/`. Em caso de divergência, o contrato executável prevalece e a documentação deve ser corrigida.
