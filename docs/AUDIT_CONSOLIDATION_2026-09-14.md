# Consolidação pós-auditoria — 2026-09-14

Base auditada para esta consolidação: `cbc47ee6ad7075eb22e50f778fbad13be81ad597`. A auditoria ampla iniciada antes desta publicação permanece parcial; este documento não declara leitura integral dos 657 arquivos rastreados nem cobertura funcional total.

## Correções materializadas

- callbacks MFA e Maestro agora usam referências estáticas válidas, eliminando cinco referências a funções globais inexistentes;
- a classificação arquitetural considera transitórios apenas arquivos internos de `app/` que não sejam composition roots;
- o manifesto de release passa a enumerar arquivos rastreados no índice Git, excluindo arquivos operacionais locais ignorados;
- hashes de artefatos derivados são calculados a partir do conteúdo final esperado, permitindo convergência em uma única execução `--write` seguida de `--check`;
- o gerador de versão deixa de embutir metadados históricos e exige metadados explícitos para cada publicação.

## Evidência incorporada ao commit

O commit de consolidação só é criado após sucesso de `tools/release-version`, `tools/release-contract-reconcile --write`, `tools/release-contract-reconcile --check`, prova de exclusão de `app/config.php` não rastreado, `tools/runtime-symbol-contract-check`, `tools/quality-gate --fast`, `tools/documentation-check.php` e `git diff --check`. `tools/code-comment-check.php` é executado novamente após a remoção do workflow temporário. Os workflows do PR permanecem a evidência autoritativa para as suítes integrais e contratos dependentes de MySQL/HTTP/Playwright antes do merge.

## Pendências deliberadamente não tratadas como falhas confirmadas

Permanecem hipóteses a reproduzir nos componentes ScopeProof, TemporalQueryNormalizer, ForeignKeyGraph/TenantIntegrityInspector, escape de PDF, cálculo de idade, concorrência de idempotência em TaskCommandService e retorno de rotação em UserCredentialService. Nenhuma delas é classificada aqui como vulnerabilidade sem reprodução.

A publicação no GitHub não implica implantação em produção.
