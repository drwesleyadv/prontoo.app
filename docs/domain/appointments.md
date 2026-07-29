# Agenda e jornada do paciente

## Responsabilidades

- criar e editar agendamentos;
- associar paciente, profissional, consultório e procedimento;
- controlar transições da jornada;
- impedir duplicidade por repetição de envio;
- manter histórico legível.

## Estados canônicos

- agendado;
- confirmado;
- chegou;
- em preparo;
- em atendimento;
- atendimento concluído;
- aguardando pagamento;
- finalizado;
- não compareceu;
- cancelado.

## Invariantes

- todo agendamento pertence a um consultório;
- novos agendamentos rápidos usam procedimento ativo do mesmo consultório;
- formulários de criação usam token de uso único;
- transições inválidas são recusadas;
- registros históricos não são reinterpretados por regras novas.

## Concorrência

Procedimento e disponibilidade devem ser revalidados dentro da transação quando a decisão puder mudar entre leitura e gravação.
