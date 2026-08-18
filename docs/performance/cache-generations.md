# Gerações e invalidação de cache

Cache no Prontoo é uma cópia descartável de read models derivados. A fonte de verdade continua sendo o estado persistente e as regras do caso de uso.

## Política

O cache JSON server-side continua armazenado em `ssd/cache/server-json`, sem dependência de Redis ou serviço externo. A política combina TTL de segurança com invalidação por versão lógica: a atualidade é controlada pela geração; o TTL limita retenção e funciona como proteção secundária.

Leituras com tag `clinic:<id>` incorporam à chave a geração do consultório e do domínio. Domínios que declaram uma unidade natural podem acrescentar subgerações, como `agenda-day:YYYY-MM-DD` e `goal:YYYY-MM`/`financial-month:YYYY-MM`. Uma escrita torna a versão anterior logicamente obsoleta sem varrer ou apagar os arquivos antigos no caminho síncrono.

## Invalidação

POSTs protegidos registram as dependências do domínio afetado. A invalidação pendente é efetivada antes do envio dos headers e possui fallback no shutdown. Isso preserva `read-your-writes`: depois de uma mutação concluída, o redirect seguinte já calcula uma chave nova.

A invalidação é escopada ao consultório quando há contexto de tenant. Falha ao gravar o manifesto de versões degrada para o bump da geração física da categoria correspondente, produzindo cache miss adicional em vez de risco de stale data.

## Armazenamento

Arquivos de valor permanecem atomically written e protegidos por lock contra stampede. Dentro de cada geração, o primeiro byte hexadecimal efetivo do hash é dividido em 256 shards (`00` a `ff`) para evitar diretórios excessivamente grandes.

Manifestos por consultório ficam em `ssd/cache/server-json/scopes/clinic-<id>/versions.json`. O manifesto é pequeno, lido uma vez por request e atualizado sob `flock` com troca atômica de arquivo.

## TTL efetivo

Categorias operacionais podem permanecer mais tempo no SSD porque uma mutação relevante invalida sua geração antes da resposta. A política inicial usa pisos de 5 minutos para Agenda/Recepção e Dashboard, 10 minutos para Financeiro/Gavetas, 30 minutos para lookup/auxiliares, 1 hora para clínica/catálogo/expediente e 2 horas para templates. Controles sensíveis que não pertencem a essas famílias preservam o TTL solicitado.

## Limpeza

A invalidação não remove arquivos antigos durante a interação do usuário. `server_json_cache_gc()` permite garbage collection assíncrona de JSONs, locks e temporários antigos, preservando os manifestos de versão. O mecanismo legado de geração global continua disponível como fail-safe e para fluxos não migrados.

## Observabilidade

O Runtime mantém contadores do request para `memory_hit`, `disk_hit`, `miss`, `expired`, `build`, `write`, falhas de escrita e `lock_bypass`. Budgets MySQL continuam medindo o caminho real para que cache não esconda regressões de consulta.

## Falha e segurança

Cache não concede autorização, não substitui invariantes e não deve mascarar falhas de integridade. Em caso de indisponibilidade, leituras permitidas recalculam a partir do banco. A consequência aceitável de falha de cache é perda de desempenho, nunca aceitação de dado ou permissão inválida.
