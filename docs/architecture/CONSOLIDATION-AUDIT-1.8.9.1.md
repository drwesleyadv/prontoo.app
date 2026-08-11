# Arquivo histórico — Auditoria de consolidação 1.8.9.1

A auditoria `1.8.9.1` verificou a arquitetura depois da remoção de compatibilidade legada. O objetivo foi distinguir referências históricas — úteis para rastreabilidade — de fronteiras executáveis ainda ativas.

A consequência que permanece é simples: compatibilidade antiga não pode sobreviver apenas porque um documento a menciona. O runtime atual deve resolver símbolos e dependências pelos caminhos nativos, enquanto mapas históricos podem registrar de onde vieram.

O estado presente é governado pela baseline consolidada `1.8.11.1` e pelo modo de manutenção de `1.8.11.2`.
