# Fase 4 — comandos transacionais

> **Documento histórico.** Fase concluída. Consulte `responsibility-map.md` para a arquitetura vigente.

## Escopo concluído

1. porta de comando para abas do paciente;
2. caso de uso independente de HTTP, sessão e persistência;
3. implementação PDO transacional;
4. bloqueio pessimista das leituras de duplicidade/ordenação;
5. resultado idempotente para repetição;
6. preservação da fachada HTTP e comportamento público;
7. caracterização de transação, isolamento e dependências.

## Resultado permanente

`PatientTabCommandService` valida o comando e depende da porta; `PdoPatientTabCommandRepository` participa de transação existente ou cria a própria, mantém locks e confirma/reverte como unidade. O padrão foi posteriormente aplicado a contato do paciente e recebimento financeiro.

## Evolução posterior

A “próxima etapa” original foi concluída na Fase 5 e em extrações posteriores. Mutações protegidas hoje também obedecem ao núcleo de invariantes e à política de prova transacional do action ledger.
