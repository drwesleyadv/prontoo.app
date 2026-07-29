# Fase 1 — enxugamento estrutural

## Escopo concluído

1. compactação segura de blocos de linhas vazias em tokens PHP;
2. mapa de responsabilidades e direção de modularização;
3. inventário inicial de funções puras;
4. extração de validadores e formatadores sem mudança de assinatura pública;
5. testes de caracterização incorporados ao contrato arquitetural.

## Estratégia de compatibilidade

Os arquivos legados permanecem como fachadas. Chamadores existentes continuam usando as mesmas funções globais, enquanto a implementação pura passa a residir em componentes de domínio.

## Resultado esperado

- menor extensão visual dos arquivos;
- localização mais rápida de regras puras;
- redução gradual de responsabilidades nos arquivos monolíticos;
- base testável para as próximas extrações;
- nenhuma mudança de banco, schema, interface ou comportamento funcional.

## Próxima etapa

Concluída na Fase 2: apresentação e leitura das abas do paciente e apresentação da dica de onboarding foram separadas preservando as fachadas.
