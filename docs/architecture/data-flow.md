# Fluxos de dados

## Ação protegida

```mermaid
sequenceDiagram
    participant B as Navegador
    participant P as Presentation
    participant K as LayeredKernel
    participant AC as Action Catalog
    participant A as Application
    participant I as InvariantKernel
    participant DB as MySQL

    B->>P: POST + CSRF + ação exata
    P->>K: enforceAction
    K->>AC: resolver contrato/capacidade
    K->>DB: revalidar usuário, consultório e cargos
    DB-->>K: credenciais vivas
    K-->>P: autorização fail-closed
    P->>A: executar caso de uso
    A->>I: guardMutation
    I->>DB: mutação + prova na mesma transação
    DB-->>A: commit ou rollback conjunto
    A-->>P: resultado
    P-->>B: resposta
```

O catálogo de ações é definido em `Application/Authorization` e composto por `Runtime/Authorization`. Adaptadores concretos não decidem autorização.

## Login e prontidão

```mermaid
sequenceDiagram
    participant B as Navegador
    participant L as Login
    participant R as RuntimeBootCoordinator
    participant DB as MySQL

    B->>L: CPF + senha
    L->>DB: usuário, bloqueios e senha
    L->>R: prontidão pós-senha
    R->>DB: schema contract + integrity lightcheck
    DB-->>R: estado mínimo válido
    R-->>L: ready
    L->>L: MFA quando exigido
    L-->>B: sessão autenticada
```

Prontidão mínima e manutenção profunda possuem marcadores separados. Login não executa Maestro contract, autoteste profundo, cleanup ou runtime self-check no caminho síncrono. Se cache/lock de prontidão estiver indisponível, os checks mínimos são executados sem cache em vez de liberar a rota sem validação.

## Logout

A geração canônica do usuário é rotacionada e a sessão local é destruída. Auditoria secundária segue por spool/fila durável assinada, com retry, dead-letter e consumo idempotente pelo Maestro. Telemetria da navegação é encerrada separadamente.

## Maestro

O ciclo é supervisionado no servidor, com orçamento residual, preflight somente leitura, estágios de saúde independentes e envelopes duráveis. Processamento é idempotente; falhas seguem política de retry/backoff e dead-letter sem transformar manutenção administrativa em efeito colateral do hot path.

## Leitura e escrita por feature

Presentation chama `Application Service`; Application depende de uma porta; Composition injeta a implementação PDO de Infrastructure. Em comandos críticos, o repositório concreto mantém locks, mutação e persistência na mesma transação, enquanto Core protege invariantes e escopo.
