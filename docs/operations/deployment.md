# Implantação

## Princípio

Deploy publica exatamente o commit aprovado. Release, build, schema revision, fallbacks e manifestos são artefatos determinísticos reconciliados a partir da fonte canônica de versão; CI detecta divergência em modo read-only.

## Antes do deploy

- branch/commit identificados;
- Architecture Contract e Documentation Contract aprovados;
- `php tools/release-contract-reconcile --check` aprovado;
- versão e manifestos consistentes;
- PHP web/CLI confirmado na família 8.4;
- backup verificado;
- plano de rollback definido;
- mudanças de banco explicitamente avaliadas;
- janela operacional comunicada quando necessária.

## Procedimento

1. obter o artefato do commit aprovado;
2. validar hashes/manifestos;
3. sincronizar código sem substituir `ssd`;
4. aplicar configuração e segredos externos;
5. confirmar permissões de storage necessárias;
6. confirmar PHP 8.4 e conectividade MySQL;
7. executar smoke tests;
8. observar erros, telemetria e filas;
9. registrar versão/commit implantados.

## Prontidão

Rotas normais e login usam prontidão mínima: schema contract e integrity lightcheck. Manutenção profunda possui marcador separado e não deve ser executada automaticamente em cada requisição. Falha de gravabilidade de cache/lock usa validação mínima sem cache quando prevista, sem converter estado desconhecido em sucesso.

## Smoke tests

- login por envio HTML nativo, senha e MFA;
- runtime pós-senha;
- abertura da Agenda;
- leitura isolada de Pacientes e Agenda;
- resolução de credencial/capacidade viva;
- operação protegida sem mutação destrutiva;
- recebimento financeiro transacional de teste quando aplicável;
- execução do Maestro;
- acesso a documento autorizado;
- bloqueio de rota administrativa sem capacidade.

## Pós-deploy

Compare telemetria com a baseline anterior, observe `runtime.log` sem exposição pública permanente e mantenha rollback disponível até estabilidade confirmada. Diagnósticos temporários devem ter escopo mínimo, sanitização e remoção explícita após uso.
