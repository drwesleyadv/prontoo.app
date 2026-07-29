# Fase 2 — fronteiras de apresentação e leitura

## Escopo concluído

1. separação da leitura das abas do paciente em repositório de infraestrutura;
2. separação do seletor visual das abas em componente de apresentação;
3. separação da renderização da dica de onboarding em componente de apresentação;
4. manutenção das funções globais existentes como fachadas compatíveis;
5. composição explícita dos novos módulos pelo carregador seletivo de rotas;
6. testes de caracterização estrutural e de saída HTML.

## Fluxo de abas do paciente

`PatientTabReadRepository` concentra as consultas de leitura das abas. `PatientTabView` recebe dados já preparados e produz o seletor visual. `Patients.php` preserva as funções públicas, mas deixa de conter o SQL e o HTML desses pontos.

## Fluxo de onboarding

`OnboardingTipView` recebe o modelo da dica, os dados de retorno e o campo CSRF já preparados. `AuthOnboarding.php` continua decidindo quando a dica deve aparecer, mas não constrói mais o HTML do componente.

## Regras consolidadas

- infraestrutura não produz HTML;
- apresentação não executa SQL;
- arquivos legados preservam assinaturas públicas durante a migração;
- o carregador de módulos realiza a composição na ordem necessária;
- módulos adicionais são carregados apenas nos fluxos que os utilizam;
- banco, schema, permissões, rotas e aparência permanecem inalterados.

## Próxima etapa

Extrair consultas adicionais para repositórios e transformar operações coordenadas em casos de uso, priorizando fluxos de leitura antes das mutações críticas.
