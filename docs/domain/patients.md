# Pacientes e pessoas

## Conceitos

**Interessado** é pessoa em relacionamento inicial.  
**Paciente** possui vínculo clínico operacional.  
**Colaborador** participa da equipe.  
**Credor** participa de operações financeiras.

Uma pessoa pode exercer mais de um papel, conforme regras e permissões.

## Invariantes

- identidade e escopo devem ser consistentes;
- conversão de interessado em paciente é idempotente;
- dados sensíveis aparecem somente para cargos autorizados;
- buscas e consultas respeitam rate limit e consultório;
- documentos e agendamentos referenciam a pessoa correta.

## Interface

O fluxo de Pacientes é o padrão de consistência para listagens, busca, criação e edição de pessoas.
