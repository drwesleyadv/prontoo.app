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
- Abaixo dos cards, Métricas preserva os gráficos **Velocidade** e **Volume**, atualizados a cada minuto, sem incorporar a tabela de Rotas. Ambos exibem Carregamento de Páginas e Consulta, com zero explícito nos intervalos sem eventos e segmentos retos entre pontos. As duas séries aparecem somente como áreas inferiores preenchidas, sem contornos ou pontos terminais visíveis. Em Velocidade, os preenchimentos sólidos usam `#05391f` para Carregamento de Páginas e `#ddf6e8` para Consulta. Velocidade usa os 1.440 minutos completos das últimas 24 horas e uma marca por hora no eixo inferior; Volume usa 30 pontos, um para cada intervalo de 24 horas dos últimos 30 dias.
- Todas as áreas HTML exibem duas linhas decorativas no rodapé derivadas dos mesmos 30 pontos de Volume. Nas superfícies públicas, somente a geometria normalizada é renderizada, nas cores `#1f6f56` e `#347963` com opacidade `0.5`, sem contagens ou rota pública de telemetria; nas superfícies autenticadas, as linhas usam as cores de destaque do consultório ou do ambiente Desenvolvedor. Os vértices são arredondados por curvas quadráticas sem eliminar as encostas retas do perfil de montanha.
- Rotas mostra o nome funcional da tela ou operação, o número de requisições e o tempo médio. A ordem usa maior número de requisições primeiro e, em empate, menor tempo médio.
- A PageHead de Consultórios contém, nesta ordem, **Consultórios**, **Usuários**, **Mensagens**, **Avisos** e **Auditoria**.
- Administração contém somente **Manutenção** e **Configuração**.
- `admin_painel` e sua Visão geral não existem mais.
- A revisão de pagamentos e comprovantes de assinatura pertence a Consultórios.
- `admin_onboarding` permanece apenas como alias de compatibilidade e redireciona ao filtro de onboarding.
- `admin_operations` permanece como relatório sob demanda, sem ocupar uma ação no rodapé de Consultórios.

## Limite das supressões

O snapshot de saúde, o histórico de evidências e o canário periódico foram removidos. A supressão não desativa os mecanismos que protegem a operação: isolamento entre consultórios, autorização, auditoria, validação de schema, invariantes de mutação, prontidão do runtime e persistência dos eventos de integridade continuam obrigatórios.