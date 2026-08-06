# Auditoria integral de conformidade PHP 8.4 — 1.8.6.1

- Arquivos PHP revisados individualmente: **119**.
- Arquivos com refatoração aplicável: **51**.
- Arquivos sem mudança segura aplicável: **68**.
- Funções e métodos nomeados preservados: **1922**.
- Closures preservadas: **34**.
- Arrow functions preservadas: **125**.
- Quantidade e assinaturas de funções: **inalteradas**.
- Banco, schema, rotas, layout e assets: **inalterados**.

## Refatorações aplicadas

- normalização multibyte com a família `mb_trim`;
- política explícita `RoundingMode::HalfAwayFromZero`;
- `PDO::connect()` para retorno da subclasse específica do driver;
- `DateTimeImmutable::createFromTimestamp()`;
- parâmetro `escape` explícito nas APIs CSV;
- helper interno coerente com a família exclusiva PHP 8.4;
- lint individual com `E_ALL` e bloqueio permanente de depreciações.

## Recursos não forçados

Property hooks, visibilidade assimétrica, lazy objects, `request_parse_body()`, nova API DOM, `#[Deprecated]`, novas funções de coleção, BCMath orientado a objetos e JIT não foram introduzidos porque alterariam contratos, acrescentariam callbacks, mudariam comportamento ou exigiriam configuração fora deste escopo.

O inventário completo está em `docs/audits/php84-conformance-1.8.6.1.json`.
