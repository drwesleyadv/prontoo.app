# Fase 5 — endurecimento de recebimento financeiro

> **Documento histórico.** Fase concluída e incorporada à arquitetura vigente.

## Objetivo realizado

Aplicar o padrão de comando transacional a uma mutação financeira crítica sem alterar a operação visível da ficha do paciente.

## Componentes consolidados

- `PatientRevenueReceiptPort` define a fronteira;
- `PatientRevenueReceiptService` valida autorização, comando e resultado;
- `PdoPatientRevenueReceiptRepository` mantém locks, persistência e rollback;
- a borda histórica permanece adaptador HTTP compatível;
- a composição concreta pertence ao Runtime/Composition, hoje separada em unidades de feature.

## Garantias permanentes

- autorização e tenant são explícitos;
- paciente/cobrança são bloqueados no escopo correto;
- repetição/concorrência não cria segundo movimento confirmado;
- movimento, efetivação da receita e atualização relacionada pertencem à mesma transação;
- valores financeiros permanecem em centavos inteiros;
- mutações protegidas também obedecem ao action ledger e às invariantes canônicas.

A fase não é uma descrição completa do Financeiro atual; para responsabilidades vigentes consulte `responsibility-map.md` e `domain/financial.md`.
