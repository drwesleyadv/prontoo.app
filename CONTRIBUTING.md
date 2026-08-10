# Contribuição

## Princípios

Toda alteração deve ser mínima, rastreável, testável e compatível com as invariantes do sistema. Não introduza abstrações sem necessidade comprovada e não mova regras de negócio para arquivos de apresentação.

## Fluxo de trabalho

1. Atualize a branch a partir de `prontoo`.
2. Crie uma branch descritiva.
3. Identifique a camada pelo `app/Core/Architecture/LayerMap.php` antes de editar.
4. Implemente a menor mudança capaz de resolver o problema.
5. Atualize testes, contratos e documentação afetados.
6. Durante a implementação, execute `php tools/quality-gate --fast` para validar a camada rápida sem banco ou HTTP.
7. Antes de abrir o pull request, execute `php tools/quality-gate`; esse é o gate estático canônico local e deve permanecer equivalente ao início da CI.
8. Abra pull request explicando contexto, decisão, impacto e riscos.
9. Faça merge apenas após o quality gate, os testes de integração com MySQL/HTTP e a autorização estarem verdes.

## Quality gate canônico

`php tools/quality-gate --fast` executa os contratos de feedback imediato: suíte rápida, composition roots, performance budgets e teto do `OperationGateway`.

`php tools/quality-gate` acrescenta lint PHP 8.4, consistência de versão/manifestos, documentação, segurança, arquitetura, unidades nativas, símbolos de runtime e auditoria SOLID. Schema, instalador, login pós-senha, Maestro, runtime crítico e HTTP permanecem gates de integração porque dependem do ambiente MySQL/HTTP da CI.

Não duplique um novo contrato estático diretamente no workflow sem integrá-lo também ao `tools/quality-gate`. O objetivo é manter uma única entrada reproduzível entre desenvolvimento local e CI.

## Regras arquiteturais

- `Core` depende somente de `Core`.
- `Domain` depende de `Core/Domain` e não conhece HTTP, sessão ou PDO.
- `Application` coordena casos de uso e portas; não conhece adaptadores concretos.
- `Infrastructure` implementa portas e detalhes externos.
- `Presentation` converte HTTP/UI em comandos/resultados e não depende de `Infrastructure`.
- `Composition/Runtime` é o único ponto autorizado a conectar todas as camadas.
- não introduza fachadas globais de compatibilidade, namespaces `/Legacy/` ou bridges procedurais para código nativo;
- referências a paths históricos são permitidas apenas em mapas explícitos de migração, auditorias e contratos de baseline;
- `compatibility_boundaries` deve permanecer vazio;
- o teto corrente de entrypoints/ferramentas procedurais não nativos não pode aumentar;
- o número efetivo de unidades nativas não pode regredir.

## Runtime

Não reúna novamente boot, catálogo de módulos, loading, dispatch e wiring em fachadas globais. Use as unidades de `app/Runtime/Modules`, `Runtime/Routing`, `Runtime/Boot`, `Runtime/Authorization` e composições específicas de feature. Prontidão mínima de login/rotas normais deve permanecer separada da manutenção profunda.

## Segurança

Mudanças em autenticação, autorização, sessão, auditoria, financeiro, isolamento de consultório ou instalador exigem análise de ameaça, casos positivos/negativos, teste fail-closed e concorrência quando aplicável. Cache nunca deve se tornar autoridade de segurança.

## Banco de dados

Não execute DDL no runtime comum. Mudanças estruturais exigem decisão formal, contrato de schema, instalação limpa validada e estratégia de compatibilidade de dados quando necessária. Nunca elimine dados existentes como mecanismo de atualização.

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

Uma alteração está pronta quando `php tools/quality-gate` passa localmente, os gates de integração canônicos passam na CI, documentação reflete a árvore atual, componentes ativos apontam somente para paths existentes, não há segredo/dado pessoal/arquivo temporário no diff e versão/manifestos permanecem deterministicamente consistentes.
