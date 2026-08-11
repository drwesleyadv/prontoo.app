# Caso de uso — alteração de contato do paciente

Este caso ilustra a arquitetura de comando. Runtime recebe a intenção do usuário, valida contexto básico e delega ao Application Service. `PatientContactCommandService` expressa o caso de uso e depende de um port de comando; a implementação concreta de persistência fica fora de Application.

## Por que este recorte importa

Dados de contato parecem simples, mas misturar request, autorização e SQL na mesma página faria uma regra pequena contaminar a fronteira Runtime. O command service cria um ponto de teste estável e permite que o adapter de banco evolua sem mudar o contrato do caso de uso.

## Garantias esperadas

O paciente deve pertencer ao consultório correto, a escrita deve ocorrer sob contexto autorizado e a resposta não deve revelar detalhes de persistência. Mudanças futuras devem manter o port focado na capacidade necessária, em vez de reintroduzir acesso genérico a dados no input adapter.
