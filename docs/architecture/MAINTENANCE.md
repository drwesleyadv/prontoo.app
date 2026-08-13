# Manutenção da arquitetura consolidada

O projeto opera em modo `consolidated_maintenance`: arquitetura não é um programa contínuo de refatoração; ela atua como conjunto de limites executáveis para a evolução do produto.

## Quando abrir novo ciclo

Somente quando um invariante importante não puder ser restaurado por mudança focal, quando surgir risco material de produto/segurança que a estrutura atual não absorva, ou quando uma nova capacidade exigir fronteira arquitetural inexistente. “Há arquivos grandes” não é condição suficiente.

## Refactor-on-touch

Hotspots Runtime acima de 500 linhas são tratados oportunisticamente. Se um deles precisar mudar por razão real, a mesma mudança deve reduzir o bucket de tamanho ou acessos ao gateway genérico. Assim a dívida cai com o trabalho do produto em vez de competir com ele.

## Métricas residuais

Ratchets de gateway genérico, referências diretas a Infrastructure e hotspots são dívida conhecida. Os valores não definem falha funcional; definem teto. Invariantes estruturais de risco permanecem em zero.

## Critério de sucesso

Uma arquitetura madura não é a que muda sempre, mas a que permite mudar o produto sem reabrir problemas já resolvidos.
