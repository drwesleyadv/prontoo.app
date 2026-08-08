# Fase 3 — consultas e casos de uso

> **Documento histórico.** Fase concluída. Consulte `responsibility-map.md` para a arquitetura vigente.

## Escopo concluído

1. porta de leitura de pacientes em Application;
2. implementação PDO da leitura em Infrastructure;
3. coordenação da elegibilidade cadastral em caso de uso independente de persistência;
4. normalização de responsáveis no caso de uso;
5. preservação das fachadas compatíveis;
6. caracterização de isolamento e direção de dependências.

## Resultado permanente

`PatientReadService` depende de `PatientReadPort`; `PdoPatientReadRepository` implementa a porta com filtro explícito de consultório. Esse padrão tornou-se a referência para leituras nativas e foi reutilizado no histórico de recepção.

## Evolução posterior

A “próxima etapa” original foi concluída: comandos transacionais foram introduzidos nas fases seguintes, outros read models/casos de uso foram extraídos e a árvore histórica foi classificada por camada. Application continua proibida de conhecer PDO e Infrastructure continua responsável pelos adaptadores concretos.
