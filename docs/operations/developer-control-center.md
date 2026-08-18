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
- Páginas conta carregamentos HTML concluídos; Registros soma a variação líquida de linhas registrada pelo Maestro em `ssd/telemetry/database.json`, excluindo o saldo inicial e preservando deltas negativos; Landing conta carregamentos da Landing Page.
- Abaixo dos cards, Métricas preserva os gráficos **Velocidade** e **Volume**, atualizados a cada minuto, sem incorporar a tabela de Rotas. Ambos exibem Carregamento de Páginas e Consulta, com zero explícito nos intervalos sem eventos e segmentos retos entre pontos. As duas séries aparecem somente como áreas inferiores preenchidas, sem contornos ou pontos terminais visíveis. Em Velocidade, os preenchimentos sólidos usam `#05391f` para Carregamento de Páginas e `#ddf6e8` para Consulta. Velocidade usa os 1.440 minutos completos das últimas 24 horas e uma marca por hora no eixo inferior; Volume usa 20 pontos, um para cada intervalo de 24 horas dos últimos 20 dias, e a série Consulta usa a mesma variação líquida de `database.json` do card Registros.
- Todas as áreas HTML exibem duas ondas preenchidas no rodapé em um timeframe comum de 20 intervalos de 24 horas. Carregamento de Páginas usa os 20 pontos finais de `page_load`; Consulta usa a variação líquida de registros de `database.json`, não a contagem de queries. A identidade visual mantém SVG `1000×250`, normalização conjunta, curvas Bézier cúbicas contínuas, fechamento até o fundo, altura `15vh` limitada a `64–180px` e máscara vertical integral aos 34%. As duas áreas usam o mesmo tom principal (`#347963` em público ou a cor principal do consultório) com `opacity: 0.5` cada, de modo que sobreposição e cruzamentos evidenciem qual série está acima. A página Status continua suprimida, não há contagens públicas e o cliente não redesenha a geometria.
- Rotas mostra o nome funcional da tela ou operação, o número de requisições e o tempo médio. A ordem usa maior número de requisições primeiro e, em empate, menor tempo médio.
- A PageHead de Consultórios contém, nesta ordem, **Consultórios**, **Usuários**, **Mensagens**, **Avisos** e **Auditoria**. A lista abre em **Ativos** por padrão; **Todos** permanece um filtro explícito, e limpar busca/filtro retorna ao estado Ativos.
- A PageHead de Administração contém, nesta ordem, **Manutenção** e **Configuração**. Ao acessar Administração, Manutenção abre diretamente e fica ativa; a antiga tela intermediária que listava essas duas opções foi suprimida.
- `admin_painel` e sua Visão geral não existem mais.
- A revisão de pagamentos e comprovantes de assinatura pertence a Consultórios.
- `admin_onboarding` permanece apenas como alias de compatibilidade e redireciona ao filtro de onboarding.
- `admin_operations` permanece como relatório sob demanda, sem ocupar uma ação no rodapé de Consultórios.

## Limite das supressões

O snapshot de saúde, o histórico de evidências e o canário periódico foram removidos. A supressão não desativa os mecanismos que protegem a operação: isolamento entre consultórios, autorização, auditoria, validação de schema, invariantes de mutação, prontidão do runtime e persistência dos eventos de integridade continuam obrigatórios.