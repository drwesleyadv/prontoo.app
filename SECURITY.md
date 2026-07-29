# Política de segurança

## Escopo

Esta política cobre código, configuração, banco, autenticação, autorização, armazenamento, filas, auditoria e infraestrutura diretamente associada ao Prontoo.

## Comunicação de vulnerabilidade

Não registre vulnerabilidades exploráveis em issue pública. Encaminhe o relato diretamente ao responsável pelo repositório, com:

- componente afetado;
- pré-condições;
- passos de reprodução;
- impacto;
- evidências mínimas;
- proposta de mitigação, quando disponível.

Evite incluir dados pessoais, credenciais reais ou conteúdo clínico.

## Princípios obrigatórios

- menor privilégio;
- defesa em profundidade;
- falha fechada;
- isolamento por consultório;
- revalidação de credenciais sensíveis;
- trilha de auditoria íntegra;
- segredos fora do repositório;
- recuperação sem perda silenciosa;
- correções sem enfraquecer controles existentes.

## Áreas críticas

Alterações nas áreas abaixo exigem revisão reforçada:

- login, logout e MFA;
- elevação global;
- catálogo de ações;
- escopo multitenant;
- financeiro;
- documentos e armazenamento;
- instalador e schema;
- Maestro, filas e auditoria;
- geração e revogação de sessões.

## Resposta a incidente

Siga [o runbook de incidentes](docs/operations/incident-response.md). Preserve evidências, limite o impacto, revogue credenciais comprometidas e registre as ações realizadas.

## Dependências

Atualizações de dependências devem considerar procedência, licença, vulnerabilidades conhecidas, compatibilidade e possibilidade de rollback.
