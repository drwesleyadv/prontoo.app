# Histórico de versões

## 1.8.11.2 — Manutenção arquitetural e hardening de sessão

- formaliza `1.8.11.1` como baseline arquitetural consolidada e adota modo de manutenção incremental.
- institui `refactor-on-touch` executável para hotspots Runtime acima de 500 linhas.
- extrai o logout para coordinator dedicado com até três tentativas de revogação global.
- torna explícita a degradação do logout com sessão local destruída e resposta HTTP 503 quando a revogação global não é confirmada.
- adiciona smoke HTTP real para CSRF, rotação de sessão, revogação multissessão e falha de escrita da geração canônica.
- preserva schema, banco e interface e mantém as invariantes arquiteturais de risco em zero.

## 1.8.11.1 — Fechamento da arquitetura em camadas

- conclui as fases arquiteturais e o ciclo corretivo que tornaram verdadeiros os contratos de fronteira Runtime.
- fixa em zero SQL de negócio, PDO direto, transações de caso de uso no Runtime, `OperationGateway`, símbolos internos não resolvidos e findings objetivos do auditor SOLID.
- consolida testes de Application, budgets MySQL e documentação como contratos de CI.

## 1.8.10.x — Migração de casos de uso e contratos

- move responsabilidades transacionais e semânticas para Application/ports/adapters apropriados.
- amplia caracterização de casos críticos e mede dependências residuais por ratchets monotônicos.

## 1.8.9.x e anteriores — Preparação da consolidação

- remove compatibilidade legada, fecha fronteiras de composição, estabiliza PHP 8.4, Maestro, telemetria e contratos de runtime.
- esta seção resume a trajetória histórica; detalhes que ainda ajudam a compreender decisões estão arquivados em `docs/architecture/` e `docs/adr/`.
