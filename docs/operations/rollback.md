# Rollback

Rollback precisa distinguir código, schema e estado persistente. A condição de mudança de schema deve ser conferida no contrato da release antes de qualquer reversão de código.

## Código

Escolha um commit/release conhecido e validado, publique-o pelo mesmo canal de deployment e preserve `ssd/`. Não copie apenas um subconjunto de arquivos, porque o release contract depende de consistência entre código, manifests e metadados canônicos.

## Banco

Se a release não alterou schema, não reverta banco apenas porque o código voltou. Se houve mudança de schema, siga o plano específico daquela mudança; rollback de DDL sem plano pode ser mais destrutivo que o incidente original.

## Validação

Após rollback, confirme versão, login, tenant isolation, uma leitura crítica, Maestro e logs. Registre o motivo para que a correção seguinte trate a causa, não apenas o sintoma.
