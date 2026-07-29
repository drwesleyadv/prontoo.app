# Histórico de versões

## 1.7.29.3 — Fase 2: apresentação e leitura

- move a leitura das abas do paciente para infraestrutura;
- move o seletor de ícones das abas para apresentação;
- move a renderização da dica de onboarding para apresentação;
- mantém as funções globais existentes como fachadas compatíveis;
- preserva o carregamento seletivo por rota;
- adiciona snapshots HTML e contratos de fronteira;
- não altera banco, schema, interface, permissões ou regras de negócio.
## 1.7.29.2 — Fase 1 de enxugamento estrutural

- compacta blocos de linhas vazias sem alterar tokens executáveis;
- cria mapa de responsabilidades e inventário inicial de funções puras;
- extrai validadores de identidade e utilidades puras de pacientes;
- preserva as funções globais existentes como fachadas compatíveis;
- adiciona testes de caracterização ao contrato arquitetural;
- não altera banco, schema, interface, permissões ou regras de negócio.

Este arquivo registra mudanças relevantes para desenvolvedores e operadores. O histórico detalhado anterior permanece em `ChangeLog.txt`.

## 1.7.29.1 — documentação como código

- remove comentários de código de todos os arquivos versionados;
- substitui documentação repetitiva por documentação arquitetural, de domínio, segurança e operação;
- introduz README, guia de contribuição, política de segurança, diagramas C4, ADRs e runbooks;
- adiciona validação automática da documentação e da política de ausência de comentários;
- preserva interface, regras de negócio, banco, schema, permissões e comportamento do runtime;
- mantém PHP 8.4 e MySQL 8.0.30 como requisitos mínimos.

## 1.7.27.6 — republicação integral da baseline

- regravou os arquivos descritos no manifesto a partir da baseline 1.7.27.5;
- regenerou contratos canônicos e hashes SHA-256;
- preservou funcionalidades, interface, banco, schema, segurança, permissões e regras de negócio.
