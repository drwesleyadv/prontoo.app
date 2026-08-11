# Rollback

Rollback precisa distinguir código, schema e estado persistente. Na release `1.8.11.2` não houve mudança de schema, o que torna reversão de código mais simples, mas essa condição deve ser conferida em cada release.

## Código

Escolha um commit/release conhecido e validado, publique-o pelo mesmo canal de deployment e preserve `ssd/`. Não copie apenas um subconjunto de arquivos, porque o release contract depende de consistência entre código, manifests e fallbacks.

## Banco

Se a release não alterou schema, não reverta banco apenas porque o código voltou. Se houve mudança de schema, siga o plano específico daquela mudança; rollback de DDL sem plano pode ser mais destrutivo que o incidente original.

## Validação

Após rollback, confirme versão, login, tenant isolation, uma leitura crítica, Maestro e logs. Registre o motivo para que a correção seguinte trate a causa, não apenas o sintoma.
