# Política de versionamento do Prontoo

O número de versão do Prontoo segue obrigatoriamente o formato:

`1.mês.dia.sequência`

Regras:

- `1` é a linha principal permanente do produto.
- `mês` é o mês numérico da publicação, sem zero à esquerda.
- `dia` é o dia numérico da publicação, sem zero à esquerda.
- `sequência` começa em `1` e aumenta a cada nova publicação realizada no mesmo dia.
- Em um novo dia, a sequência reinicia em `1`.
- Branches, changelog, `version.json`, `app/update.manifest.json`, fallbacks de runtime e landing devem usar a mesma versão lógica.
- A revisão de ativos permanece independente e não altera a versão lógica.

Exemplo: a primeira publicação em 20 de julho usa `1.7.20.1`; a segunda usa `1.7.20.2`.
