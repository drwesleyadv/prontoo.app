# Histórico de versões

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
