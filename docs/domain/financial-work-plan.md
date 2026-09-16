# Consistência financeira com operação simples

## Escopo

Preservar Receber, Pagar, Transferir e Conferir. Não introduzir escrituração contábil completa, acesso a produção ou migração automática durante requests. Cada etapa exige validação e merge antes da seguinte.

## Etapa 1: caracterização

O painel administrativo e a tela Locais usam `financial_global_position` e saldos por movimentos confirmados para bancos e cofre. Gavetas usam snapshots de sessão: a posição operacional pode incluir movimentos pendentes. Essa posição não deve ser confundida com saldo conferido.

`financial_account_balances` soma saldo inicial, receitas, despesas e transferências em tabelas separadas. Seu único consumidor PHP encontrado é `financial_dashboard_numbers`; não foi encontrado consumidor PHP desta última rotina. Portanto, há cálculo divergente disponível no código, mas não está demonstrada divergência entre duas telas ativas.

O cadastro administrativo grava saldo inicial zero e cria o local bancário sem movimento de abertura. Conta preexistente com dinheiro não possui implantação explícita neste fluxo.

O serviço de recebimento da recepção coloca dinheiro em `pending_review` na sessão da gaveta e PIX confirmado no destino administrativo, sem sessão. Já a sincronização do agendamento cria movimento confirmado inclusive para dinheiro. A ficha do paciente usa origem `patient_revenue` e destino derivado do papel, sem escolher destino bancário para meios não físicos. Esses caminhos precisam convergir sem duplicar recebimentos existentes.

Receber pelo serviço usa transação; os formulários administrativos avulsos escrevem receita/despesa antes de chamar o serviço transacional de movimento. A atomicidade do conjunto deve ser garantida na fronteira do caso de uso, não no Runtime.

Os testes de caracterização em `tools/test-fast` verificam situação, destino, identidade de origem, sessão e rejeição de destino inválido no serviço existente. Eles não provam concorrência ou rollback MySQL; estes exigem integração.

## Etapas seguintes

1. Unificar leituras de saldos, distinguindo posição operacional e conferida; preservar a continuidade da gaveta sem recriar saldo diário.
2. Cadastrar saldo inicial como evento específico datado, sem inflar receitas ou metas; valor zero não cria dinheiro e transferência não vira abertura.
3. Uniformizar os caminhos de recebimento, identidade e atomicidade; correções confirmadas preservam o original e períodos consolidados.
4. Diagnosticar histórico em leitura antes de converter qualquer valor; impedir dupla contagem e exigir conferência dos casos ambíguos.
5. Avaliar contas futuras, recebimentos parciais e liquidação bancária separadamente após estabilizar o núcleo.

## Critérios de aceite

Saldo inicial de 1000000 centavos, recebimento de 50000 e pagamento de 20000 resultam em 1030000 de saldo e somente 50000 de recebimentos. Transferências preservam o total. Abertura diária não cria entrada. Repetir um recebimento não duplica dinheiro. Contas de outro consultório não são aceitas. Fechamentos não são alterados silenciosamente.

## Validação remota

O workflow Financial Preparation executa somente em branches `codex/finance-*`, com token de leitura, PHP 8.4 e MySQL isolado. Reconcilia arquivos derivados no checkout temporário e apresenta o diff comprimido nos logs para revisão e incorporação no commit. Não publica, não faz push e não substitui Architecture Contract ou Documentation Contract. O candidato reconciliado deve ser commitado e os checks do PR devem passar no SHA final antes do merge.
