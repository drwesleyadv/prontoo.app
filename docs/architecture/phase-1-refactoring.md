# Fase 1 — enxugamento estrutural

> **Documento histórico.** Esta fase está concluída e não representa o mapa atual da árvore. Consulte `overview.md`, `layers.md` e `responsibility-map.md` para a arquitetura vigente.

## Escopo concluído

1. compactação segura de blocos de linhas vazias em tokens PHP;
2. mapa inicial de responsabilidades e direção de modularização;
3. inventário inicial de funções puras;
4. extração de validadores e formatadores sem mudança de assinatura pública;
5. testes de caracterização incorporados ao contrato arquitetural.

## Estratégia usada

Os arquivos históricos funcionaram inicialmente como fachadas enquanto implementações puras foram extraídas. Essa estratégia evoluiu nas fases posteriores para portas/casos de uso, adaptadores PDO, Presentation dedicada e, por fim, decomposição sistemática em unidades `Domain/Legacy`, `Infrastructure/Legacy`, `Presentation/Legacy` e `Runtime/Legacy` classificadas por camada.

## Legado da fase

- `app/Domain/Identity/IdentityDocumentValidator.php`;
- `app/Domain/Patients/PatientPure.php`;
- caracterização permanente no contrato arquitetural;
- princípio de preservação de comportamento durante migração.

## Situação posterior

A arquitetura consolidada possui composition root decomposto, catálogo de autorização coeso, runtime orientado a módulos/rotas e contratos de classificação/SOLID. Portanto, referências desta fase a “próxima etapa” devem ser interpretadas apenas como histórico cronológico.
