# Visão geral da arquitetura

A arquitetura do Prontoo foi desenhada para manter simples a experiência de uso sem tornar implícitas as regras que protegem dados, dinheiro e identidade. O sistema é um monólito modular em PHP: uma única aplicação implantável, porém organizada em camadas com dependências verificadas automaticamente.

## Modelo mental

Uma requisição entra pelo Runtime. O Runtime interpreta HTTP, sessão e contexto, escolhe o caso de uso e delega. Application decide a sequência do caso de uso por services e ports. Domain contém políticas e invariantes. Infrastructure executa mecanismos concretos. Presentation transforma o resultado em saída. O composition root é o lugar autorizado para conectar interfaces a adapters.

Essa separação não busca multiplicar classes; busca impedir que decisões diferentes se misturem. Um redirect é preocupação de entrada. Uma regra de autorização é política. Uma transação de recebimento é caso de uso. Um `PDOStatement` é detalhe de infraestrutura.

## Invariantes de fechamento

A consolidação fixou em zero as violações consideradas estruturalmente perigosas: SQL de negócio e PDO no Runtime, transações de caso de uso no Runtime, `OperationGateway`, adapters concretos fora dos roots, símbolos internos não resolvidos e findings objetivos do auditor SOLID. A classificação arquitetural é integralmente coberta pelos contratos.

## Dívida conhecida

O projeto não confunde “zero violações” com “zero dívida”. Há input adapters grandes, referências diretas a Infrastructure e uso de gateway genérico ainda mensurados. Esses valores são ratchets. O modo de manutenção exige redução quando a área é tocada, evitando uma campanha de refatoração sem necessidade de produto.

## Consequência prática

A arquitetura já não é um projeto paralelo ao produto. Ela funciona como guardrail. Novas funcionalidades devem escolher o menor caminho que respeite as fronteiras existentes; um novo ciclo arquitetural só é justificável se um contrato falhar ou surgir risco material que a estrutura atual não consiga absorver.
