# Versão canônica

## 1.9.15.7 — Sessão autenticada: inatividade de 4 horas

- Amplia o limite de inatividade autenticada de 3600 para 14400 segundos.
- Mantém o limite absoluto de duração da sessão em 43200 segundos (12 horas).
- Mantém MFA, CSRF, logout e revogação global de sessões sem relaxamento.
- Atualiza o contrato executável de segurança para exigir o novo limite de inatividade.
- Alinha documentação e metadados canônicos sem alteração de schema ou banco.
