# Contribuição

## Princípios

Toda alteração deve ser mínima, rastreável, testável e compatível com as invariantes do sistema. Não introduza abstrações sem necessidade comprovada e não mova regras de negócio para arquivos de apresentação.

## Fluxo de trabalho

1. Atualize o branch a partir de `main`.
2. Crie um branch descritivo.
3. Identifique a camada responsável antes de editar.
4. Implemente a menor mudança capaz de resolver o problema.
5. Atualize testes, contratos e documentação afetados.
6. Execute todas as validações locais relevantes.
7. Abra pull request explicando contexto, decisão, impacto e riscos.
8. Faça merge apenas após validação e autorização.

## Regras arquiteturais

- `Core` não depende de infraestrutura ou apresentação.
- `Domain` não conhece HTTP, sessão ou PDO.
- `Application` coordena casos de uso por portas.
- `Infrastructure` implementa portas e acesso externo.
- `Presentation` converte HTTP em comandos e resultados.
- composição é o único ponto autorizado a conectar todas as camadas.
- arquivos transitórios devem ficar mais finos a cada alteração, nunca mais complexos.

## Segurança

Mudanças em autenticação, autorização, sessão, auditoria, financeiro, isolamento de consultório ou instalador exigem:

- análise explícita de ameaça;
- casos positivos e negativos;
- teste de falha fechada;
- teste de concorrência quando aplicável;
- atualização da documentação correspondente.

## Banco de dados

Não execute DDL no runtime comum. Mudanças estruturais exigem decisão formal, contrato de schema, instalação limpa validada e estratégia de compatibilidade. Nunca elimine dados existentes como mecanismo de atualização.

## Estilo

- use `declare(strict_types=1)` em PHP;
- prefira nomes que expressem intenção;
- mantenha funções pequenas e com uma responsabilidade;
- evite estado global novo;
- não adicione comentários ao código;
- registre decisões não óbvias em ADR ou documento técnico;
- não duplique contratos em múltiplos pontos.

## Definição de pronto

Uma alteração está pronta quando:

- lint e verificadores passam;
- invariantes continuam válidas;
- testes cobrem o comportamento alterado;
- documentação foi atualizada;
- não há segredo, dado pessoal ou arquivo temporário no diff;
- a versão e os manifestos estão consistentes.
