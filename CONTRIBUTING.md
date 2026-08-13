# Contribuindo com o Prontoo

Contribuir com o Prontoo significa preservar comportamento e invariantes enquanto o produto evolui. A arquitetura já está consolidada; portanto, mudanças devem resolver uma necessidade real de produto, segurança, operação ou manutenção, e não criar refatorações autônomas sem benefício mensurável.

## Antes de alterar

Humanos e agentes devem ler `AGENTS.md`. Ele reúne a ordem de autoridade, invariantes arquiteturais, regras de tenant, segurança, banco, Presentation, release e Definition of Done.

## Ambiente

Use PHP 8.4 e MySQL 8.0.30 ou superior. A família PHP é intencionalmente exata: uma versão posterior não deve ser presumida compatível até que o contrato seja alterado. A branch de integração é `prontoo`.

## Fluxo de mudança

Crie uma branch curta a partir de `prontoo`, implemente o menor escopo coerente, execute os gates aplicáveis e abra PR. O merge só deve ocorrer com `Documentation Contract` e `Architecture Contract` verdes quando esses checks forem disparados.

Mudanças em Runtime devem respeitar a direção de dependências. SQL, PDO e fronteiras transacionais de caso de uso não pertencem a input adapters. Se um arquivo Runtime já estiver classificado como hotspot acima de 500 linhas, qualquer alteração nele deve reduzir o `hotspot_bucket` ou a quantidade de acessos ao gateway genérico; `tools/runtime-refactor-on-touch-check` aplica essa regra.

## Testes e contratos

Comece por `php tools/quality-gate --fast`. Para mudanças relevantes, execute o gate integral e os smokes específicos. Application Services públicos precisam continuar caracterizados no contrato de testes. Alterações de consultas não podem ultrapassar os budgets MySQL existentes.

A documentação é validada por `tools/documentation-check.php`; links locais quebrados e ADRs sem status/data válidos falham a CI. O projeto adota política de ausência de comentários de código inline; a explicação durável pertence a nomes, tipos, testes, ADRs e documentação.

## Release e metadados

`version.json` é a fonte canônica de versão. Não edite fallbacks ou manifests de release de forma independente. Quando arquivos rastreados mudarem, use `php tools/release-contract-reconcile --write` para reconciliar hashes e artefatos derivados, e depois confirme com `--check`.

## Critério de qualidade

Uma contribuição está pronta quando o comportamento desejado é verificável, as invariantes continuam verdadeiras, a dívida arquitetural não aumentou silenciosamente e a documentação relevante descreve o estado que realmente será mergeado.
