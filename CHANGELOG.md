# Versão canônica

## 1.8.18.1 — Cache JSON versionado por consultório e domínio

- isola chaves de cache por consultório e geração de domínio sem introduzir Redis ou outro serviço externo.
- adiciona subgerações explícitas para read models naturalmente segmentados por dia de Agenda e mês financeiro.
- efetiva invalidações pendentes antes do envio dos headers para preservar read-your-writes no redirect seguinte.
- eleva TTLs de famílias versionadas e mantém falha de manifesto como cache miss seguro por geração física legada.
- distribui arquivos de cache em 256 shards de hash e disponibiliza garbage collection assíncrona sem alterar schema ou banco.
