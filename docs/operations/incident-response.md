# Resposta a incidentes

Incidente é qualquer evento que ameace confidencialidade, integridade, disponibilidade ou rastreabilidade do Prontoo. A prioridade é conter dano sem apagar evidência.

## 1. Classificar

Determine se o problema é autenticação, tenant isolation, financeiro, banco, storage, deploy, Maestro ou disponibilidade geral. Identifique a primeira versão/commit conhecida e o alcance aparente.

## 2. Conter

Revogue sessões quando identidade estiver envolvida, interrompa automações se elas estiverem propagando erro e limite acesso ao recurso afetado. Não faça DDL improvisado ou edição direta de produção.

## 3. Preservar evidência

Guarde logs, estado do Maestro, hashes/commit, horário e sintomas. Remova dados pessoais antes de compartilhar evidência em canais amplos.

## 4. Corrigir

Reproduza com o menor caso possível, escreva regressão automatizada, aplique mudança focal e passe os contratos. Use rollback se a correção segura não estiver pronta e a release anterior for compatível.

## 5. Aprender

Atualize runbook, contrato ou observabilidade quando o incidente revelar uma classe de falha que poderia ter sido detectada antes.
