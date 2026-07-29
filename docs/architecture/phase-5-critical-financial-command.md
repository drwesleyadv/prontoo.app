# Fase 5 — endurecimento de recebimento financeiro

## Objetivo

Aplicar o padrão de comando transacional a uma mutação financeira crítica sem alterar a operação visível da ficha do paciente.

## Escopo

- autorização explícita para Recepção e Gerência no caso de uso;
- bloqueio pessimista do paciente e da cobrança com isolamento por consultório;
- detecção de movimento financeiro previamente confirmado;
- movimento, efetivação da receita e atualização do atendimento na mesma transação;
- preservação de mensagens, auditoria, rotas e interface.

## Componentes

- `PatientRevenueReceiptPort` define a fronteira da mutação;
- `PatientRevenueReceiptService` valida autorização, identidade do comando e resultado;
- `PdoPatientRevenueReceiptRepository` coordena bloqueios, persistência e rollback;
- `Patients.php` permanece como adaptador HTTP compatível;
- `Runner.php` compõe o caso de uso e a infraestrutura.

## Garantias

A fase não altera banco, schema, layout ou permissões cadastradas. A repetição ou concorrência não cria um segundo movimento confirmado para a mesma cobrança.
