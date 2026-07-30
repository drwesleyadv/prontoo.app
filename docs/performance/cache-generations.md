# Invalidação de cache por geração

## Objetivo

A Fase 2 substitui a exclusão recursiva de categorias durante gravações por troca atômica de geração. A mutação deixa de percorrer e remover todos os arquivos da categoria no caminho da requisição.

## Funcionamento

Cada categoria mantém um contador persistente. A chave física inclui a geração atual. Uma invalidação incrementa o contador sob bloqueio exclusivo e limpa apenas a memória da requisição. Arquivos de gerações anteriores deixam de ser endereçáveis imediatamente.

## Falha segura

Se o contador não puder ser bloqueado ou persistido, o sistema executa a limpeza física da categoria e reinicia a geração. A coerência prevalece sobre o ganho de desempenho.

## Conformidade

- gerações são separadas por categoria e as chaves continuam incluindo o escopo funcional definido pelo chamador;
- não são armazenados segredos, senhas ou fatores de autenticação;
- a invalidação permanece obrigatória após mutações;
- gerações antigas podem ser removidas apenas por manutenção assíncrona, nunca como requisito para a consistência da resposta atual.
