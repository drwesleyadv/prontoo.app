# Fluxos de dados

## Ação protegida

```mermaid
sequenceDiagram
    participant B as Navegador
    participant P as Presentation
    participant K as LayeredKernel
    participant A as Application
    participant I as InvariantKernel
    participant DB as MySQL

    B->>P: POST com CSRF e ação exata
    P->>K: solicitar autorização
    K->>DB: revalidar usuário, consultório e cargos
    DB-->>K: credenciais vivas
    K-->>P: capacidade autorizada
    P->>A: executar caso de uso
    A->>I: guardar mutação
    I->>DB: alteração e prova na mesma transação
    DB-->>B: resultado confirmado
```

## Login

Senha, estado do usuário, bloqueios e MFA são avaliados antes da criação da sessão. Estados indeterminados falham fechados.

## Logout

A geração canônica do usuário é rotacionada e a sessão local é destruída. A auditoria secundária segue por fila durável. A telemetria da rota é concluída separadamente pelo marcador de encerramento da própria requisição.

## Maestro

O ciclo possui orçamento residual único. Envelopes são assinados, processados de forma idempotente e enviados à fila morta quando excedem a política de tentativas.
