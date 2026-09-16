# Versão canônica

## 1.9.15.10 — Banco existente: recuperação do contrato da Agenda

- Reconhece exclusivamente o contrato predecessor publicado antes das pré-reservas temporizadas.
- Adiciona de forma idempotente as colunas, índices e chave estrangeira da pré-reserva em bancos existentes.
- Valida o schema completo antes de promover o hash e o marcador persistido.
- Mantém qualquer divergência desconhecida em modo fail-closed.
- Preserva o bloqueio geral de DDL fora do instalador, CI controlada e migração allowlisted.
- Corrige o hash canônico do schema publicado na metainformação da release.
