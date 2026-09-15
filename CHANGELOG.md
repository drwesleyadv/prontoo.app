# Versão canônica

## 1.9.15.5 — Correção do bloqueio diário da Agenda

- Corrige a validação do bloqueio diário para aceitar limites temporais armazenados como timestamps UTC.
- Mantém a leitura do expediente configurado no perfil do profissional sem converter timestamps válidos por strtotime.
- Preserva a revalidação transacional contra consultas concorrentes e as regras de propriedade do bloqueio.
- Move o controle para imediatamente após o profissional no seletor diário, sem rótulo visual.
- Usa cadeado fechado para Bloquear dia e cadeado aberto para Desbloquear dia, preservando rótulos acessíveis.
