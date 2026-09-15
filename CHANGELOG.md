# Versão canônica

## 1.9.15.4 — Bloqueio rápido do dia na Agenda

- Exibe Bloquear dia à direita da navegação da Agenda diária somente quando o profissional selecionado não possui consultas na data.
- Alterna o mesmo controle para Desbloquear dia quando existe bloqueio cobrindo todo o expediente selecionado.
- Bloqueia o expediente configurado do profissional e usa o mesmo fallback de 08:00 a 17:00 da Agenda quando não há faixa cadastrada.
- Serializa o bloqueio com a criação de consultas e revalida a ausência de agendamentos dentro da transação para evitar corrida operacional.
- Preserva bloqueios parciais independentes, mantém o fluxo Bloquear horário e não altera banco de dados nem schema.
