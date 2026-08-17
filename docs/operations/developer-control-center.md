# Central do Desenvolvedor

## Princípio

O ambiente global do Desenvolvedor é um plano de controle direto, sem tela intermediária de resumo e sem análises de fundo de confiabilidade ou integridade.

## Hierarquia

1. **Métricas** — tela inicial, com os três comparativos essenciais de volume da plataforma.
2. **Consultórios** — ciclo de vida e gestão, com o mesmo padrão de diretório de Pacientes: resumo, busca rápida, filtros, lista compacta e ação principal por registro.
3. **Administração** — hub restrito a manutenção e configuração global.

## Contratos do ambiente

- `admin_performance` é a entrada do Desenvolvedor e aparece com o rótulo **Métricas**.
- A navegação contém somente Métricas, Consultórios e Administração, nessa ordem.
- A PageHead de Métricas contém, nesta ordem, **Métricas** e **Rotas**.
- Métricas abre com **Páginas**, **Registros** e **Landing**. Cada card compara os últimos 10 dias móveis com os 10 dias imediatamente anteriores.
- Páginas conta carregamentos HTML concluídos; Registros soma as operações preparadas observadas no banco; Landing conta carregamentos da Landing Page.
- Abaixo dos cards, Métricas preserva os gráficos **Velocidade** e **Volume**, atualizados a cada minuto, sem incorporar a tabela de Rotas. Ambos exibem Carregamento de Páginas e Consulta nos 1.440 minutos completos das últimas 24 horas, com zero explícito nos minutos sem eventos, segmentos retos entre pontos e uma marca por hora no eixo inferior.
- Rotas mostra o nome funcional da tela ou operação, o número de requisições e o tempo médio. A ordem usa maior número de requisições primeiro e, em empate, menor tempo médio.
- A PageHead de Consultórios contém, nesta ordem, **Consultórios**, **Usuários**, **Mensagens**, **Avisos** e **Auditoria**.
- Administração contém somente **Manutenção** e **Configuração**.
- `admin_painel` e sua Visão geral não existem mais.
- A revisão de pagamentos e comprovantes de assinatura pertence a Consultórios.
- `admin_onboarding` permanece apenas como alias de compatibilidade e redireciona ao filtro de onboarding.
- `admin_operations` permanece como relatório sob demanda, sem ocupar uma ação no rodapé de Consultórios.

## Limite das supressões

O snapshot de saúde, o histórico de evidências e o canário periódico foram removidos. A supressão não desativa os mecanismos que protegem a operação: isolamento entre consultórios, autorização, auditoria, validação de schema, invariantes de mutação, prontidão do runtime e persistência dos eventos de integridade continuam obrigatórios.
