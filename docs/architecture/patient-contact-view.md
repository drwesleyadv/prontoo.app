# Caso de uso — leitura de contato do paciente

A leitura de dados de paciente segue uma fronteira própria porque leitura e comando têm necessidades diferentes. Runtime solicita uma visão; Application fornece um serviço de leitura por port; Infrastructure resolve a consulta sob escopo do consultório; Presentation recebe uma estrutura pronta para exibição.

## Objetivo

O desenho evita que uma página conheça detalhes de tabelas e permite caracterizar a entrada pública do serviço. Também torna possível aplicar budget de consultas sem acoplar o teste ao HTML.

## Regra de evolução

Read models devem permanecer somente leitura. Se uma tela passar a precisar de uma mudança de estado, crie ou reutilize um comando explícito em vez de esconder escrita no fluxo de consulta.
