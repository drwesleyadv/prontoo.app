# Gerações e invalidação de cache

Cache no Prontoo é uma cópia descartável de dados derivados. A fonte de verdade continua sendo o estado persistente e as regras do caso de uso.

## Política

Caches JSON usam TTL curto e invalidação seletiva. Quando uma escrita altera uma visão conhecida, o domínio/caso de uso deve invalidar a geração ou chave correspondente em vez de esperar expiração longa.

## Falha

Indisponibilidade de cache não pode conceder autorização nem mascarar violação de integridade. Em leituras onde é seguro, o sistema pode recalcular; em controles de segurança, a política específica decide se há fallback.

## Benefício

Gerações evitam varrer e apagar arquivos individualmente e tornam explícito que uma família de valores ficou obsoleta após uma mutação.
