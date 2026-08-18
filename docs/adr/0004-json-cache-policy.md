# ADR 0004 — Política de cache JSON

**Status:** aceito e revisado
**Data:** 2026-08-11
**Revisão:** 2026-08-18

## Contexto

Painéis e leituras agregadas precisam ser rápidos, mas cache não pode transformar dados clínicos ou financeiros em estado incoerente. A hospedagem atual exige solução baseada em filesystem, sem Redis/Valkey como dependência operacional.

## Decisão

Usar cache JSON server-side em SSD com cache-aside, locks contra stampede e versões lógicas hierárquicas. A chave efetiva incorpora `consultório + domínio + geração` e, quando o read model declara uma unidade natural, também `segmento + subgeração`.

A invalidação orientada pela mutação é o mecanismo primário de freshness. TTL é proteção secundária e política de retenção, portanto pode ser maior nas famílias versionadas. Escritas relevantes efetivam o bump de geração antes do envio dos headers, preservando `read-your-writes`; o shutdown permanece como fallback.

Arquivos de valor são distribuídos por shard de hash para limitar o tamanho dos diretórios. Gerações anteriores não são apagadas no caminho síncrono e podem ser removidas posteriormente por garbage collection.

Cache continua sendo otimização: não é fonte de verdade, não concede autorização e não substitui invariantes do banco. Falha no manifesto escopado degrada para invalidação da categoria física legada, privilegiando miss/rebuild sobre stale data.

## Consequências

A política reduz invalidações entre consultórios e entre domínios sem introduzir serviço externo. Read models já tagueados por consultório passam a aproveitar isolamento automaticamente; read models segmentados podem reduzir ainda mais o raio de invalidação usando tags explícitas de dia ou mês.

Budgets de consulta continuam medindo o caminho real para evitar que o cache esconda N+1 ou regressões arquiteturais. Telemetria de hit/miss/build deve ser usada para calibrar TTL e decidir quais read models merecem segmentação adicional.
