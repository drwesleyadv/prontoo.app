# Maestro

## Finalidade

O Maestro é o executor assíncrono exclusivamente servidor-side do Prontoo. Ele remove do caminho crítico HTTP trabalhos que dependem de tempo, recuperação ou processamento supervisionado.

As responsabilidades permanentes são:

- executar regras agendadas dos consultórios;
- consumir auditorias diferidas assinadas;
- retomar ações interrompidas de forma idempotente;
- descarregar eventos de integridade produzidos pelo processo atual;
- publicar estado operacional separado por estágio;
- remover somente artefatos diagnósticos transitórios conforme retenção definida.

O Maestro não depende de navegação, JavaScript, requisições oportunistas, beacon, service worker ou qualquer outro gatilho do cliente.

## Limites de responsabilidade

O ciclo não cria ou altera schema, não concede permissões, não atribui gestores e não executa migrações. Preflight observa e relata; reparos administrativos permanecem em fluxos próprios e auditáveis.

A integridade executada no ciclo é identificada como `runtime-event-flush`. Ela não é apresentada como varredura histórica quando apenas descarrega eventos produzidos no processo atual.

## Estágios do ciclo

Cada execução possui orçamento global e reservas independentes:

1. preflight somente leitura;
2. regras agendadas;
3. auditoria diferida;
4. flush de integridade;
5. manutenção transitória.

O resultado pai é persistido em `ssd/maestro/state/latest.json`. Cada estágio informa saúde, duração e contadores próprios. A sessão MySQL permanece em UTC durante todo o ciclo.

## Regras agendadas

A seleção aplica rodízio entre consultórios antes de preencher a capacidade do ciclo. Dentro de cada consultório, prioridade, atraso e custo histórico continuam influenciando a ordem.

A materialização possui estas garantias:

- isolamento explícito pelo `clinic_id`;
- cálculo de janelas no fuso do consultório, convertido explicitamente para UTC antes da consulta ou persistência;
- destinatário validado em modo fail-closed;
- ledger idempotente por regra, origem e ação;
- retomada de estados `running` interrompidos;
- nova tentativa de estados `error` com backoff;
- estado terminal após cinco falhas de materialização;
- adiamento por capacidade sem mascarar a regra como execução normal.

Uma regra de consultório em somente leitura é reconhecida e reagendada sem criar ação. Destinatários inválidos não são ampliados silenciosamente para toda a clínica.

## Auditoria diferida

Os envelopes são assinados e preservam autoria, consultório, origem, instante do evento, IP derivado e agente de usuário. O consumidor restaura o `clinic_id` antes da materialização.

Eventos que a política vigente decidiu não registrar são consumidos como `skipped_policy`, e não como falha. Eventos válidos não expiram apenas porque o cron ficou indisponível por mais de 24 horas.

Falhas recuperáveis usam backoff progressivo. Envelope inválido ou item que excedeu oito tentativas segue para fila morta. A existência histórica de itens na fila morta gera atenção operacional, mas não transforma automaticamente um ciclo saudável em falha.

## Saúde

Os estados são:

- `healthy`: ciclo atual concluído sem pendência relevante;
- `attention`: ciclo atual concluiu, mas há backlog antigo, fila morta histórica, execução terminal ou janela de candidatos saturada;
- `failed`: ocorreu falha técnica no ciclo atual;
- `overlap`: outra execução mantém o lock;
- `unavailable`: o arquivo de lock não pôde ser aberto.

O código de saída considera falhas do ciclo atual. Inventário histórico é sinalizado separadamente.

## Persistência e retenção

São autoritativos e não são removidos automaticamente:

- `pi_audit`;
- `pi_maestro_executions`;
- fila morta de auditoria;
- cadeia e ledger de integridade.

Relatórios históricos de preflight são transitórios e podem ser removidos após 30 dias. O log de bootstrap possui rotação limitada. A rotação do arquivo redirecionado pelo painel da hospedagem permanece responsabilidade operacional do ambiente.

## Observabilidade

O log padrão do cron deve continuar redirecionado para `ssd/logs/maestro-cron.log`. Falhas anteriores ao runtime são registradas em `ssd/logs/maestro-bootstrap.log`.

`pi_maestro_job_runs` registra o estágio de regras para compatibilidade com a área do Desenvolvedor. O estado pai em JSON distingue regras, auditoria diferida, integridade, preflight e manutenção.
