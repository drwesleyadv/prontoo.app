# Fase 3 — consultas e casos de uso

## Escopo concluído

1. criação de uma porta de leitura de pacientes na camada de aplicação;
2. implementação PDO das consultas de elegibilidade cadastral e responsáveis legais;
3. coordenação da elegibilidade para agendamento em caso de uso independente de persistência;
4. normalização dos responsáveis legais no caso de uso;
5. manutenção das funções globais existentes como fachadas compatíveis;
6. testes de caracterização do caso de uso, isolamento por consultório e direção das dependências.

## Fluxo de elegibilidade cadastral

`PatientReadService` recebe `PatientReadPort` e decide o resultado da verificação cadastral sem conhecer SQL, PDO, sessão ou HTTP. `PdoPatientReadRepository` recupera somente o paciente ativo do consultório informado. A composição fornece as políticas cadastrais já existentes, preservando textos e decisões públicas.

## Fluxo de responsáveis legais

O adaptador PDO concentra a listagem e a existência de responsáveis ativos. O caso de uso converte identificadores e indicador principal para inteiros, mantém a ordenação existente, limpa o CPF e normaliza o vínculo jurídico por funções fornecidas na composição.

## Regras consolidadas

- aplicação depende de portas, não de PDO ou funções globais de banco;
- infraestrutura implementa portas e mantém filtros explícitos por consultório;
- domínio legado chama apenas a ponte de composição para os fluxos migrados;
- assinaturas públicas, mensagens, ordenação e formatos permanecem inalterados;
- banco, schema, permissões, rotas e aparência permanecem inalterados.

## Próxima etapa

Migrar novas consultas de leitura e introduzir comandos de aplicação apenas em mutações com transação, idempotência e testes de caracterização suficientes.
