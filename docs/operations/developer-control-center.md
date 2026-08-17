# Central do Desenvolvedor

## Princípio

O ambiente global do Desenvolvedor é um plano de controle direto, sem tela intermediária de resumo e sem análises de fundo de confiabilidade ou integridade.

## Hierarquia

1. **Métricas** — tela inicial, com requisições, latência, falhas, gráficos e comportamento das rotas.
2. **Consultórios** — ciclo de vida e gestão, com o mesmo padrão de diretório de Pacientes: resumo, busca rápida, filtros, lista compacta e ação principal por registro.
3. **Administração** — hub de acesso, comunicação e auditoria; configuração e manutenção ficam progressivamente reveladas.

## Contratos do ambiente

- `admin_performance` é a entrada do Desenvolvedor e aparece com o rótulo **Métricas**.
- A navegação contém somente Métricas, Consultórios e Administração, nessa ordem.
- `admin_painel` e sua Visão geral não existem mais.
- A revisão de pagamentos e comprovantes de assinatura pertence a Consultórios.
- `admin_onboarding` permanece apenas como alias de compatibilidade e redireciona ao filtro de onboarding.
- `admin_operations` permanece como relatório sob demanda, sem ocupar uma ação no rodapé de Consultórios.
- Métricas tem uma única camada de quatro KPIs: Requisições, Latência média, Falhas e Rota mais lenta; depois gráficos e rotas.
- Administração não replica seus filhos na navegação da PageHead. O hub mostra Usuários, Mensagens internas, Avisos aos consultórios e Auditoria; Configurações e Manutenção ficam em Configuração avançada.

## Limite das supressões

O snapshot de saúde, o histórico de evidências e o canário periódico foram removidos. A supressão não desativa os mecanismos que protegem a operação: isolamento entre consultórios, autorização, auditoria, validação de schema, invariantes de mutação, prontidão do runtime e persistência dos eventos de integridade continuam obrigatórios.
