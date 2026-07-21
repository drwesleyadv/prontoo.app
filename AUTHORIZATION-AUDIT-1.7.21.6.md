# Auditoria de autorização por escopo — Prontoo 1.7.21.6

## Causa raiz

O middleware de autorização é executado antes do handler da página. A tela de Incidentes enviava POST sem `act`; o catálogo, portanto, procurava `admin_errors::__default__`, que não existia. A decisão correta era `action_contract_missing`, mas a interface exibia a mensagem genérica de credencial insuficiente.

## Falhas confirmadas

1. `admin_errors`: o formulário “Marcar resolvido” não declarava uma ação exata e não havia contrato correspondente.
2. `admin_security`: o formulário “Liberar” bloqueio de login repetia o mesmo padrão e falharia pelo mesmo motivo.

## Correções

- ações explícitas `resolve_incident` e `release_login_lock`;
- contratos globais `global_admin` exigindo exclusivamente `admin:*`;
- handlers rejeitam ações desconhecidas e identificadores inválidos;
- updates/deletes retornam mensagem idempotente quando o registro já foi tratado;
- autotestes permanentes no catálogo e no provedor, certificados pela matriz de CI.

## Diagnóstico ampliado por cargo

A matriz regressiva comprova:

- Desenvolvedor global ativo executa ações globais exatas;
- credencial global desativada ou sem `is_global_admin` é recusada;
- Administrativo, Profissional, Assistente e Recepção nunca herdam `admin:*`;
- Administrativo satisfaz políticas `manager_only` apenas no escopo do consultório;
- Assistente executa tarefas somente quando a capacidade está presente;
- Recepção executa apenas ações operacionais de caixa explicitamente permitidas;
- ações financeiras administrativas permanecem negadas à Recepção;
- Desenvolvedor global não herda capacidades clínicas sem vínculo de consultório.

## Cobertura dos demais POSTs

Os contratos explícitos das demais superfícies globais e clínicas já estavam presentes. Foram mantidos fail-closed: ação desconhecida ou ausente continua recusada, em vez de receber uma permissão genérica por rota.

## Banco e interface

- nenhuma alteração em banco, schema, tabela, coluna, índice ou constraint;
- `schema.sql` permanece byte a byte inalterado;
- apenas campos ocultos de ação foram adicionados aos formulários; não há mudança visual.
