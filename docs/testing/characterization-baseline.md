# Baseline de caracterização

Caracterização registra o comportamento público que precisa continuar verdadeiro durante mudanças estruturais. Ela foi essencial enquanto o Prontoo migrou responsabilidades para Application sem alterar UX ou regras do produto.

## Estado atual

O contrato de Application exige cobertura integral das entradas públicas catalogadas: 49 casos críticos, 30 services, 15 ports e pelo menos 127 assertivas ligadas. Se um método público novo aparecer sem caracterização, o gate falha.

## Uso em manutenção

Antes de mover responsabilidade de um hotspot ou substituir um adapter, preserve/expanda a caracterização do caso de uso. Depois da mudança, o teste deve continuar descrevendo resultado e efeitos, não detalhes internos da implementação.

A baseline existe para permitir refatoração segura, não para impedir evolução funcional consciente.
