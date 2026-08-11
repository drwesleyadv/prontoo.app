# Estratégia de testes

## Objetivo

Demonstrar comportamento correto, falha segura, preservação das invariantes e ausência de regressão estrutural na arquitetura consolidada.

## Níveis

**Unitários:** decisões puras, normalização, cálculos, policies e máquinas de estado.  
**Integração:** MySQL, transações, escopo, repositórios PDO, filas e armazenamento.  
**HTTP:** autenticação, CSRF, contratos exatos de ação, roteamento e respostas.  
**Runtime:** boot, resolução de símbolos, prontidão pós-senha, Maestro e composição.  
**End-to-end:** fluxos essenciais vistos pelo usuário.  
**Propriedades:** integridade financeira, auditoria, tempo, idempotência e isolamento.  
**Arquitetura:** classificação, dependências, SOLID, contratos de release e ausência de tombstones/unidades inválidas.  
**Operacionais:** instalação, deploy, rollback e recuperação.

## Contrato de casos de uso de Application

`app/application.test-contract.json` é a matriz executável dos casos críticos. Cada registro liga o Application Service e seus entry points públicos aos ports consumidos, às características cobertas e às assertivas nomeadas de `tools/test-fast`. `tools/application-test-contract-check` usa tokens PHP para descobrir services, métodos, interfaces e literais de assertiva; um service ou método público novo falha até ser catalogado e efetivamente exercitado.

A suíte rápida não abre conexão de banco. Casos que dependem de atomicidade real permanecem ligados a uma suíte MySQL separada no próprio contrato; o recebimento financeiro, por exemplo, aponta para `tools/critical-runtime-smoke-check`. O gate exige 100% dos casos críticos catalogados, sem usar percentual de linhas como substituto de comportamento.

## CI canônica

`.github/workflows/architecture.yml` executa em PHP 8.4 e MySQL 8 reais e cobre, entre outros gates:

- sintaxe e depreciações PHP 8.4;
- contratos JSON canônicos;
- matriz crítica de Application e suíte rápida sem banco;
- documentação e política sem comentários inline;
- regressões de segurança;
- invariantes arquiteturais;
- resolução de símbolos internos;
- auditoria estrutural SOLID;
- contrato de schema;
- segurança do instalador;
- regressão do runtime pós-senha;
- regressão do Maestro;
- smoke matrix do runtime crítico.

O Documentation Contract executa `release-contract-reconcile --check` em modo read-only: divergência de release deve falhar, não gerar commit automático.

## Hot path

Há regressão específica para garantir que login pós-senha execute prontidão mínima sem trazer manutenção profunda para o caminho síncrono. Cache/lock indisponível deve preservar schema contract e integrity lightcheck via fallback sem cache.

## Casos negativos

Toda área sensível testa ausência de permissão, tenant incorreto, estado indeterminado, repetição, concorrência, entrada inválida e indisponibilidade de dependência. A regra é fail-closed, exceto fallbacks explicitamente desenhados para preservar a mesma validação sem depender de cache.

## Ambiente

Web, CLI e CI pertencem exclusivamente à família PHP 8.4. CI usa MySQL 8 real para contratos que dependem do banco. Dados de teste são sintéticos e não contêm informações reais.
