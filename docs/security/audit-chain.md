# Auditoria e cadeia de integridade

Auditoria existe para responder quem fez o quê, em qual contexto e em que sequência, sem transformar logs em fonte de autorização.

## Componentes

O action ledger registra ações relevantes no banco. Mecanismos de audit chain e integridade verificam continuidade e consistência. Eventos que podem ser adiados entram em spool persistente e são processados pelo Maestro.

## Prova de contexto

Eventos diferidos preservam informações suficientes para reconstruir o escopo do consultório. O consumidor valida o contexto antes de escrever ou executar efeitos derivados.

## Falhas

Falha de auditoria deve ser observável. Retry é apropriado para indisponibilidade transitória; dead-letter evita loop infinito em eventos permanentemente inválidos. Arquivos inválidos não devem ser “consertados” silenciosamente.

## Privacidade

Auditoria deve registrar identidade e significado operacional necessários, evitando copiar conteúdo clínico ou segredo quando um identificador e metadado forem suficientes.
