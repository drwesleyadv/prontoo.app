# Glossário

## Application
Camada que expressa casos de uso. Coordena regras de domínio por interfaces explícitas e não conhece detalhes de HTTP ou renderização.

## Adapter
Implementação concreta de uma interface ou mecanismo de borda. Adapters de persistência pertencem a Infrastructure; input adapters pertencem ao Runtime.

## Composition root
Ponto onde implementações concretas são conectadas às abstrações. É uma exceção deliberada à regra que proíbe espalhar adapters concretos pelo Runtime.

## Domain
Regras, políticas e invariantes de negócio que devem ser compreensíveis sem depender de HTTP ou banco concreto.

## Infrastructure
Mecanismos externos e detalhes concretos: PDO, persistência, filesystem, criptografia, integrações e adaptadores técnicos.

## Invariante
Propriedade que deve permanecer verdadeira. No Prontoo, várias invariantes arquiteturais são medidas em CI e têm orçamento zero.

## Maestro
Ciclo supervisionado server-side que executa trabalho operacional e diferido com políticas de retry, isolamento e observabilidade.

## Port
Interface pela qual Application declara uma necessidade sem escolher a tecnologia que a implementará.

## Presentation
Camada de formatação e renderização. Recebe dados já decididos pelas camadas internas e produz HTML/JSON ou estruturas de apresentação.

## Ratchet
Budget monotônico usado para dívida conhecida. O valor atual pode cair, mas não pode subir sem falhar o contrato.

## Runtime
Camada de entrada e orquestração: roteamento, request, sessão, autorização de borda, coordenação de serviços e redirects. Não é camada de persistência.

## Tenant
Consultório que delimita dados e permissões. `clinic_id` é parte da fronteira de isolamento e não mero filtro de conveniência.

## Action ledger
Registro persistente de ações relevantes para integridade, rastreabilidade e validação do fluxo operacional.

## Geração de sessão
Valor persistente associado ao usuário que permite invalidar sessões já emitidas. O logout global rotaciona essa geração.

## Refactor-on-touch
Política de manutenção: se um hotspot Runtime acima de 500 linhas for alterado, a mudança precisa reduzir uma métrica de dívida definida pelo gate.
