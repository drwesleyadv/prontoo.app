# Domínio financeiro

O financeiro do Prontoo foi desenhado para a operação de clínicas pequenas: recursos como gavetas, cofre e bancos; contas a pagar/receber; consolidação; metas e movimentos.

## Casos de uso

Application concentra services financeiros específicos em vez de um gateway universal. Há services para dados, gavetas, movimentos, recebimentos, consolidação, metas, revisão e fluxos relacionados a pacientes.

## Consistência

Recebimentos e movimentos que precisam de múltiplas escritas são transacionais na fronteira apropriada. O tenant e as entidades relacionadas precisam ser válidos antes da confirmação. A UI não é fonte de verdade para saldo.

## Conferência

Consolidação é uma operação de fechamento/conferência, não simples soma de tela. Estados intermediários devem permanecer distinguíveis até a confirmação que gera lançamentos definitivos.

## Dívida e evolução

Novas capacidades devem preferir ports semânticos. A meta é reduzir gradualmente dependência de gateways genéricos quando o domínio tocado justificar a mudança.
