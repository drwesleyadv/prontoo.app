# Política de evolução do schema

O Prontoo trabalha com um schema canônico limpo. O runtime não possui autorização para “consertar” ou migrar o banco durante requests comuns.

## Regra

DDL só pode ocorrer em caminhos explicitamente autorizados de instalação, ferramentas de engenharia ou CI. `version.json` declara se uma release possui mudanças de banco/schema. A `1.8.11.2` não possui.

## Por que

Migração automática no boot mistura disponibilidade com manutenção e pode transformar uma falha de deploy em mutação parcial de dados. Separar as duas coisas permite rollback de código mais previsível e auditoria clara do que mudou.

## Mudança futura

Uma evolução real de schema deve definir pré-condições, compatibilidade, backup, validação e rollback antes do merge. Se o projeto continuar exigindo banco limpo para instalação, isso precisa permanecer explícito no release contract.
