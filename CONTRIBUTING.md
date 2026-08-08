# Contribuição

## Princípios

Toda alteração deve ser mínima, rastreável, testável e compatível com as invariantes do sistema. Não introduza abstrações sem necessidade comprovada e não mova regras de negócio para arquivos de apresentação.

## Fluxo de trabalho

1. Atualize a branch a partir de `prontoo`.
2. Crie uma branch descritiva.
3. Identifique a camada pelo `app/Core/Architecture/LayerMap.php` antes de editar.
4. Implemente a menor mudança capaz de resolver o problema.
5. Atualize testes, contratos e documentação afetados.
6. Execute os gates locais relevantes, incluindo `release-contract-reconcile --check` quando houver artefatos versionados.
7. Abra pull request explicando contexto, decisão, impacto e riscos.
8. Faça merge apenas após CI e autorização.

## Regras arquiteturais

- `Core` depende somente de `Core`.
- `Domain` depende de `Core/Domain` e não conhece HTTP, sessão ou PDO.
- `Application` coordena casos de uso e portas; não conhece adaptadores concretos.
- `Infrastructure` implementa portas e detalhes externos.
- `Presentation` converte HTTP/UI em comandos/resultados e não depende de `Infrastructure`.
- `Composition/Runtime` é o único ponto autorizado a conectar todas as camadas.
- `Legacy` indica compatibilidade dentro de uma camada, não uma exceção à regra de dependências.
- fronteiras transitórias devem ficar mais finas; o teto arquitetural não pode aumentar.
- o número efetivo de unidades nativas não pode regredir.

## Runtime

Não reúna novamente boot, catálogo de módulos, loading, dispatch e wiring em fachadas globais. Use as unidades de `app/Runtime/Modules`, `Runtime/Routing`, `Runtime/Boot`, `Runtime/Authorization` e composições específicas de feature. Prontidão mínima de login/rotas normais deve permanecer separada da manutenção profunda.

## Segurança

Mudanças em autenticação, autorização, sessão, auditoria, financeiro, isolamento de consultório ou instalador exigem análise de ameaça, casos positivos/negativos, teste fail-closed e concorrência quando aplicável. Cache nunca deve se tornar autoridade de segurança.

## Banco de dados

Não execute DDL no runtime comum. Mudanças estruturais exigem decisão formal, contrato de schema, instalação limpa validada e estratégia de compatibilidade. Nunca elimine dados existentes como mecanismo de atualização.

## Runtime suportado

Código PHP versionado deve ser compatível com a família **PHP 8.4 exclusivamente**. CI usa PHP 8.4 e MySQL 8 real nos contratos de integração.

## Estilo

- use `declare(strict_types=1)` em PHP;
- prefira nomes que expressem intenção;
- mantenha unidades coesas e pequenas;
- evite estado global novo;
- não adicione comentários inline ao código;
- registre decisões não óbvias em ADR ou documento técnico;
- não duplique contratos em múltiplos pontos.

## Definição de pronto

Uma alteração está pronta quando lint, contratos arquiteturais, segurança, SOLID, schema e testes relevantes passam; documentação reflete a árvore atual; não há segredo/dado pessoal/arquivo temporário no diff; e versão/manifestos permanecem deterministicamente consistentes.
