# Deployment

A branch operacional é `prontoo`. Deploy começa com um commit já aprovado pelos contratos, não com edição manual no servidor.

## Antes do merge

O PR deve refletir exatamente o código que será publicado. `Documentation Contract` valida release determinístico, PHP, documentação e segurança estática. `Architecture Contract` executa quality gate, schema, MySQL query budgets, installer security, login/logout, Maestro, runtime crítico e HTTP smoke.

## Release metadata

`version.json` é a fonte canônica. `app/update.manifest.json`, fallbacks e hashes são derivados por `tools/release-contract-reconcile`. Um `deployment_sync_id` identifica a intenção de sincronização com a hospedagem, mas não substitui verificação pós-deploy.

## Depois do merge

Confirme a versão servida pelo ambiente e execute verificações de saúde compatíveis com produção. Merge no GitHub prova publicação da árvore canônica; não é, por si só, evidência de que a hospedagem já sincronizou os bytes.

## Regra operacional

Nunca “corrija” produção com arquivo fora do Git. Se uma correção é necessária, faça-a em branch, teste, merge e sincronize novamente.
