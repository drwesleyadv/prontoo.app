# Fase 2 — fronteiras de apresentação e leitura

> **Documento histórico.** Fase concluída. O mapa vigente está em `responsibility-map.md`.

## Escopo concluído

1. separação da leitura das abas do paciente em repositório de infraestrutura;
2. separação do seletor visual das abas em componente de apresentação;
3. separação da renderização da dica de onboarding em componente de apresentação;
4. manutenção das funções globais existentes como fachadas compatíveis;
5. composição explícita dos módulos pelo carregamento seletivo;
6. testes de caracterização estrutural e de saída HTML.

## Resultado preservado

`PatientTabReadRepository` concentra leitura; `PatientTabView` produz apresentação; `OnboardingTipView` concentra a view correspondente. A regra “Infrastructure não produz HTML / Presentation não executa SQL” tornou-se parte permanente da matriz de dependências.

## Evolução posterior

A estratégia foi generalizada: hoje há `Presentation/Legacy`, `Infrastructure/Legacy`, `Domain/Legacy` e `Runtime/Legacy` para classificar responsabilidades históricas já separadas. O carregamento/composição também foi decomposto em `RuntimeBootPolicy`, `RuntimeModuleCatalog`, `RuntimeModuleLoader` e `RuntimeModuleComposition`. Portanto, a antiga seção de próxima etapa está concluída e substituída pela arquitetura consolidada.
