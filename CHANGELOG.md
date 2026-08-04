# Histórico de versões

## 1.8.4.9 — Rota efetiva das faixas autenticadas

- corrige exclusivamente a cadeia de exibição das faixas na área autenticada;
- registra `login_telemetry_wave` no mapa executável do `Runner`, evitando o fallback silencioso para `page_home`;
- registra o endpoint como rota pública, JSON e de boot leve;
- impede que a consulta agregada passe por contexto autenticado, integridade de ação, manutenção de tela ou flush de renderização;
- preserva a montagem imediata, as séries, as cores, a opacidade, a altura e o intervalo de atualização;
- integra ao contrato de assets a verificação regressiva da rota, do endpoint, do JavaScript e do CSS das faixas;
- mantém a revisão de assets `1.7.15.11` porque JavaScript e CSS não foram alterados;
- não altera banco de dados nem schema.

## 1.8.4.8 — Carga inicial das faixas autenticadas

- corrige a área autenticada, que criava o SVG com caminhos vazios e aguardava até 15 minutos para consultar a telemetria;
- executa a primeira consulta imediatamente após a montagem dinâmica das faixas;
- mantém o login com os caminhos inicialmente renderizados pelo PHP, sem requisição inicial duplicada;
- publica a revisão física de assets `1.7.15.11` para invalidar JavaScript e CSS armazenados em cache;
- inclui os cinco arquivos públicos canônicos correspondentes à nova revisão;
- preserva cores, opacidade, dimensões, dados e intervalo de atualização de 15 minutos;
- não altera banco de dados nem schema.

## 1.8.4.7 — Correção do contrato de assets públicos

- corrige o bloqueio de runtime causado por `asset_version` sem arquivos públicos correspondentes;
- restaura o conjunto canônico de assets para `1.7.15.10`;
- mantém a versão funcional da aplicação independente da revisão de assets;
- adiciona validação automática da existência dos cinco arquivos públicos versionados;
- valida também a referência versionada do PIX no CSS;
- preserva integralmente as ondas de telemetria publicadas na versão anterior;
- reconcilia o teto arquitetural com o baseline preexistente de 81 arquivos transitórios, sem adicionar arquivos PHP em `app/`;
- não altera banco de dados nem schema.

## 1.8.4.6 — Ondas autenticadas na paleta do consultório

- corrige a exibição das montanhas de telemetria no rodapé da área autenticada;
- aplica à camada autenticada as mesmas dimensões, recorte e opacidade usadas no login;
- inclui a tela Criar Consultório na inicialização das faixas dinâmicas;
- mantém no login as cores verdes já aprovadas;
- usa `--clinic-accent-strong` em Requisições e `--clinic-accent` em Registros na área logada e na criação do consultório;
- acompanha dinamicamente a cor de destaque selecionada para o consultório;
- preserva a camada sem interação, contornos, eixos, escalas ou tooltips;
- não altera banco de dados nem schema.

## 1.8.4.5 — Montanhas de telemetria no login

- limita a composição dinâmica aos 15% inferiores da viewport;
- transforma as curvas em áreas preenchidas até a margem inferior;
- remove integralmente o contorno das duas séries;
- mantém `#1f6f56` em Requisições e `#347963` em Registros;
- aplica 70% de transparência, equivalente a opacidade `0.30`, em cada montanha;
- preserva dados, atualização e ausência de interação;
- não altera banco de dados nem schema.

## 1.8.4.4 — Ondulação dinâmica no login

- ocupa os 25% inferiores da viewport do login e da área autenticada e da área logada com duas curvas decorativas;
- reutiliza as séries públicas de Requisições e Registros dos últimos 20 dias;
- remove eixos, escalas, grades, pontos, legendas e tooltips;
- usa `#1f6f56` para Requisições e `#347963` para Registros;
- atualiza os dados discretamente a cada 15 minutos, sem bloquear ou receber interação;
- preserva integralmente o formulário de autenticação;
- não altera banco de dados nem schema.

## 1.8.4.3 — Tons exatos em Leitura e gravação

- define o preenchimento de Requisições no tom escuro `#1f6f56`;
- define o preenchimento de Registros no tom claro `#347963`;
- preserva 50% de opacidade e a ausência de contornos e marcadores finais;
- aplica as mesmas cores no Painel do Desenvolvedor e na página pública `/status`;
- mantém o gráfico Velocidade inalterado;
- não altera banco de dados nem schema.

## 1.8.4.2 — Áreas translúcidas em Leitura e gravação

- remove os contornos das séries Requisições e Registros no gráfico Leitura e gravação;
- remove os marcadores finais dessas duas séries para preservar a leitura exclusivamente por área;
- aplica 50% de opacidade aos dois preenchimentos inferiores;
- mantém o gráfico Velocidade com suas linhas e opacidades atuais;
- aplica a mesma composição no Painel do Desenvolvedor e na página pública `/status`;
- não altera banco de dados nem schema.

## 1.8.4.1 — Preço fixado no agendamento

- grava no agendamento o preço vigente do procedimento no momento da marcação;
- preserva esse valor quando o catálogo do procedimento é reajustado posteriormente;
- mantém o valor fixado na confirmação do pagamento e na integração financeira;
- atualiza o preço somente quando o próprio procedimento do agendamento é substituído;
- identifica na interface que o valor foi fixado no agendamento;
- não altera banco de dados nem schema.

## 1.8.3.5 — Tempos médios com duas casas decimais

- limita a apresentação dos tempos médios de telemetria a duas casas decimais;
- aplica o padrão aos cards, indicadores e tooltips dos gráficos de velocidade;
- preserva a coleta e os cálculos internos em nanossegundos, sem reduzir a precisão da fonte;
- não altera banco de dados nem schema.

## 1.8.3.4 — Execuções da Landing Page no comparativo

- substitui o KPI de tempo médio da Landing Page pela quantidade de execuções da rota `landing`;
- mostra o total registrado nos últimos 10 dias e a variação percentual contra os 10 dias anteriores;
- mantém o tempo da Landing Page disponível no gráfico de velocidade, sem alterar sua série histórica;
- não altera banco de dados nem schema.

## 1.8.3.3 — Correção da janela estrutural do instalador

- reabre uma janela integral em `03/08/2026 11:20 até 13:20 (America/Cuiaba)` (`03/08/2026 15:20 UTC até 17:20 UTC`);
- sincroniza os timestamps de `InstallAccess` e `SchemaMutationLock`;
- reconhece tanto `/install.php` quanto `/?r=install` como entradas autorizadas;
- corrige o contrato automatizado para duração exata de 7.200 segundos;
- mantém HTTPS, host canônico, banco vazio e fechamento automático por 404;
- não altera banco de dados nem schema.

## 1.8.3.2 — Janela de duas horas para instalação limpa

- abre o instalador público em `03/08/2026 10:20 até 12:20 (America/Cuiaba)` (`03/08/2026 14:20 UTC até 16:20 UTC`);
- restringe o acesso a HTTPS no domínio canônico `prontoo.app`;
- permite a execução somente em estado novo, sem `app/config.php` e sem `ssd/install.lock`;
- mantém a exigência de banco de dados vazio e encerra o acesso automaticamente com resposta 404;
- não altera banco de dados nem schema.

## 1.8.3.1 — Telemetria de rotas como fonte única e matematicamente precisa

- remove integralmente a telemetria legada, suas partições, agregados, amostragem e processamento pelo Maestro;
- mede cada rota entre marcadores monotônicos de início e fim, preservando nanossegundos e exibindo milissegundos com precisão de seis casas;
- persiste um evento JSON por linha em `ssd/telemetry/telemetria.json` e mantém exatamente os últimos 20 dias;
- calcula cards compartilhados pelo Painel do Desenvolvedor e `/status` para os últimos 10 dias contra os 10 dias anteriores, sem médias de médias;
- alimenta Velocidade com durações das rotas e da Landing Page e Leitura e gravação com requisições canônicas e registros efetivamente alterados;
- documenta a remoção manual dos arquivos JSON legados, sem alterar banco de dados ou schema.

## 1.7.31.2 — Janela de quatro horas para instalação limpa

- abre o instalador público de 31/07/2026 12:22 UTC até 16:22 UTC;
- restringe o acesso a HTTPS no domínio canônico prontoo.app;
- permite a execução somente em estado novo, sem app/config.php e sem ssd/install.lock;
- mantém a exigência de banco de dados vazio e encerra o acesso automaticamente com resposta 404;
- não altera banco de dados nem schema.

## 1.7.31.1 — Montanhas sobrepostas e suavizadas nos gráficos de performance

- renderiza, em Velocidade e Leitura e gravação, duas montanhas completas até a linha de base;
- mantém a série escura ao fundo e a série clara à frente, ambas sem transparência;
- usa exatamente as cores dos respectivos contornos nos preenchimentos;
- substitui segmentos angulosos por curvas Bézier com controles limitados entre os pontos métricos;
- não altera banco de dados nem schema.

## 1.7.30.22 — Camadas corretas em Leitura e gravação

- mantém as cores e espessuras atuais das linhas de Requisições e Registros;
- preenche a faixa inferior, da base até Registros, com o verde claro opaco de Registros;
- preenche somente o intervalo entre Registros e Requisições com o verde escuro opaco de Requisições;
- preserva o renderer compartilhado e o comportamento do gráfico Velocidade;
- não altera banco de dados nem schema.

## 1.7.30.21 — Montanhas opacas em Leitura e gravação

- mantém inalteradas as cores e espessuras das linhas de Requisições e Registros;
- aplica à área inferior de cada série exatamente a mesma cor da respectiva linha;
- remove integralmente a transparência dos dois preenchimentos, formando montanhas opacas até a linha de base;
- preserva o preenchimento translúcido do gráfico Velocidade e o renderer compartilhado;
- não altera banco de dados nem schema.

## 1.7.30.20 — Administrador inicia na Agenda diária

- remove completamente o item Painel da navegação do perfil Administrador;
- direciona o login do Administrador para a Agenda, cuja visão padrão é Diário;
- redireciona acessos diretos à antiga rota Painel do Administrador para a Agenda;
- preserva os demais perfis, permissões, banco e schema.

## 1.7.30.19 — Sincronização do contrato de versão

- corrige `app/update.manifest.json`, que permaneceu em 1.7.30.17 após a publicação 1.7.30.18;
- sincroniza version, release, build, schema e hashes do pacote com a fonte canônica;
- torna o diagnóstico do runtime específico por campo divergente;
- adiciona guard de CI que bloqueia publicação parcial antes do merge;
- preserva banco, schema e comportamento funcional da plataforma.

## 1.7.30.18 — Leitura e gravação como cópia visual de Velocidade

- descarta integralmente a geometria segmentada, a interseção e as classes exclusivas do gráfico Leitura e gravação;
- reutiliza o mesmo renderer, SVG, classes, ordem de pintura, espessuras, preenchimentos, marcadores e ícone do gráfico Velocidade;
- altera somente as séries, os rótulos e a formatação numérica para Requisições e Registros;
- preserva a página pública /status, as métricas, o banco e o schema.

## 1.7.30.17 — Áreas segmentadas em Leitura e gravação

- separa geometricamente as áreas exclusivas de Requisições e Registros;
- mantém cada área exclusiva com exatamente a mesma cor e opacidade da respectiva linha;
- trata a interseção como uma terceira área neutra, sem mistura cromática entre as séries;
- preserva linhas, pontos, eixos, tooltips, gráfico Velocidade, métricas, banco e schema.

## 1.7.30.16 — Tom exato da área de Requisições

- mantém na área inferior de Requisições exatamente o mesmo tom visual de sua linha;
- remove a diluição causada pela opacidade parcial apenas no preenchimento de Requisições;
- desenha Registros primeiro e Requisições depois, mantendo o tom exato da área visível de Requisições;
- mantém a fórmula cromática de Registros e preserva Velocidade, métricas, banco e schema.

## 1.7.30.15 — Status público com bordas e cores correspondentes

- renomeia a página pública de `/stats` para `/status`;
- restaura bordas no card externo, no agrupamento dos gráficos e em cada gráfico público;
- mantém 95% da largura da viewport com espaçamento responsivo;
- aplica às áreas de Requisições e Registros a mesma cor-base de suas respectivas linhas;
- preserva o gráfico Velocidade, as métricas, o banco e o schema.

## 1.7.30.14 — Stats amplo e sem bordas

- remove bordas, raios, sombras e espaçamentos externos do card e dos gráficos somente em `/stats`;
- faz o conteúdo ocupar 95% da largura da viewport;
- preserva o layout do Painel do Desenvolvedor;
- mantém banco e schema inalterados.

## 1.7.30.13 — Renderer único para os gráficos do Painel

- elimina o renderer específico de Leitura e gravação;
- renderiza Velocidade e Leitura e gravação pela mesma função, com HTML, SVG, áreas, linhas, pontos, eixos e tooltips idênticos;
- altera somente dados, rótulos e unidade para Requisições e Registros;
- remove estilos exclusivos e divergentes do gráfico anterior;
- disponibiliza `https://prontoo.app/stats` como página pública GET com exclusivamente o card de desempenho, sem contexto autenticado, POST ou criação de schema;
- inclui dias com zero requisições nas médias diárias de 7 e 30 dias;
- mantém banco e schema inalterados.

## 1.7.30.12 — Composição visual idêntica entre gráficos

- reutiliza no gráfico Leitura e gravação as mesmas classes de preenchimento do gráfico Velocidade;
- alinha a ordem de pintura: Requisições no tom escuro primeiro e Registros no tom claro depois;
- elimina regras duplicadas de paleta que poderiam divergir por especificidade;
- mantém métricas, banco e schema inalterados.

## 1.7.30.11 — Paleta compartilhada e volume da Landing Page

- renomeia o gráfico Requisições e registros para Leitura e gravação;
- aplica às duas áreas exatamente os mesmos tons usados no gráfico Velocidade;
- mantém o gráfico sem linhas de contorno ou marcadores visíveis;
- altera o card Landing Page para mostrar requisições das últimas 24 horas;
- mantém banco e schema inalterados.

## 1.7.30.10 — Áreas do gráfico sem contornos

- organiza os cards em Requisições, Tempo Médio, Landing Page e Usuários Ativos;
- remove linhas de contorno e marcadores finais das séries Requisições e Registros;
- mantém somente as áreas preenchidas na base, com Registros em verde claro e Requisições em verde escuro;
- preserva período, valores, tooltips e demais gráficos;
- mantém banco e schema inalterados.

## 1.7.30.9 — Consolidação do Painel do Desenvolvedor

- remove a rota, o item de navegação e as funções da tela Telemetria duplicada;
- reorganiza os cards para Requisições 24h, Tempo da Landing Page e Usuários ativos 24h;
- unifica Requisições e Registros em gráfico de duas séries no modelo visual de Velocidade;
- aplica verde escuro às Requisições e verde claro aos Registros;
- mantém banco e schema inalterados.

## 1.7.30.8 — Telemetria no Painel do Desenvolvedor

- adiciona o item Telemetria à navegação global do Desenvolvedor;
- apresenta gráficos de resposta, banco, volume e falhas em janelas selecionáveis;
- mostra eficiência do cache, rotas lentas, comparação entre versões e saúde da fila diferida;
- mantém a tela estritamente somente leitura e sem dados clínicos ou pessoais;
- não altera banco ou schema.

## 1.7.30.7 — Sincronização final dos contratos arquiteturais

- sincroniza a baseline nativa em 37 nos contratos de versão e arquitetura;
- mantém o teto transitório em 80;
- adiciona verificação fail-fast de consistência entre os dois manifestos;
- não altera banco, schema, lógica, interface ou comportamento operacional.

## 1.7.30.6 — Evolução do servidor, Fase 6: view de contato do paciente

- remove duas renderizações duplicadas do formulário de contato;
- move o HTML específico para uma view de apresentação;
- preserva componentes, campos, atributos, CSRF e ação do formulário;
- adiciona snapshot determinístico e contrato de fronteira;
- não altera banco, schema, interface ou comportamento.

## 1.7.30.5 — Evolução do servidor, Fase 5: comando de contato do paciente

- move a atualização de contato para porta, serviço e adaptador PDO;
- adiciona bloqueio pessimista da linha ativa do paciente;
- mantém a persistência em transação curta com rollback;
- preserva autorização, auditoria, mensagens e campos existentes;
- não altera banco, schema ou interface.

## 1.7.30.4 — Evolução do servidor, Fase 4: read model da recepção

- consolida interessados, eventos e autores em uma única leitura;
- move SQL do histórico da recepção para adaptador PDO;
- mantém ordenação, estrutura e apresentação existentes;
- torna isolamento e custo da leitura explicitamente testáveis;
- não altera banco, schema ou interface.

## 1.7.30.3 — Evolução do servidor, Fase 3: telemetria particionada

- substitui reescrita integral do histórico bruto por append NDJSON;
- particiona eventos por data UTC e mantém leitura do formato legado;
- deduplica identificadores diferidos durante a leitura;
- move retenção de partições para o consumidor assíncrono;
- não altera banco, schema ou interface.

## 1.7.30.2 — Evolução do servidor, Fase 2: gerações de cache

- substitui a invalidação recursiva por troca atômica de geração;
- mantém fallback físico fail-safe quando a geração não pode ser persistida;
- adiciona métricas de troca e fallback;
- preserva TTL, chaves, isolamento e invalidação após mutações;
- não altera banco, schema ou interface.

## 1.7.30.1 — Evolução do servidor, Fase 1: orçamentos de desempenho

- cria contrato versionado de custo por rota;
- adiciona avaliação determinística de tempo, consultas e módulos carregados;
- integra a caracterização ao contrato arquitetural existente;
- documenta regras de ajuste sem enfraquecer segurança ou integridade;
- não altera banco, schema, interface ou comportamento operacional.

## 1.7.29.6 — Fase 5: endurecimento financeiro crítico

- Migra o recebimento pela ficha do paciente para porta, caso de uso e adaptador PDO.
- Torna autorização, isolamento por consultório, bloqueios e resultado explicitamente testáveis.
- Mantém movimento financeiro, receita e atendimento na mesma unidade transacional.
- Evita movimento confirmado duplicado para a mesma cobrança.
- Preserva mensagens, auditoria, rotas, permissões, banco, schema e interface.

## 1.7.29.5 — Fase 4: comandos transacionais

- Criação de abas do paciente migrada para porta, caso de uso e adaptador PDO.
- Transação participa do contexto existente ou abre unidade própria com rollback seguro.
- Duplicidade e ordenação são decididas sob bloqueio pessimista e repetição retorna resultado idempotente.
- Fachada, mensagens, auditoria, rotas, permissões, banco, schema e interface permanecem equivalentes.
- Testes de caracterização ampliados para a Fase 4.

## 1.7.29.4 — Fase 3: consultas e casos de uso

- cria uma porta de leitura de pacientes na camada de aplicação;
- introduz caso de uso para elegibilidade cadastral e responsáveis legais;
- move as consultas correspondentes para adaptador PDO com isolamento por consultório;
- mantém as funções globais existentes como fachadas compatíveis;
- adiciona testes de comportamento, direção de dependências e ausência de SQL na aplicação;
- não altera banco, schema, interface, permissões ou regras de negócio.
## 1.7.29.3 — Fase 2: apresentação e leitura

- move a leitura das abas do paciente para infraestrutura;
- move o seletor de ícones das abas para apresentação;
- move a renderização da dica de onboarding para apresentação;
- mantém as funções globais existentes como fachadas compatíveis;
- preserva o carregamento seletivo por rota;
- adiciona snapshots HTML e contratos de fronteira;
- não altera banco, schema, interface, permissões ou regras de negócio.
## 1.7.29.2 — Fase 1 de enxugamento estrutural

- compacta blocos de linhas vazias sem alterar tokens executáveis;
- cria mapa de responsabilidades e inventário inicial de funções puras;
- extrai validadores de identidade e utilidades puras de pacientes;
- preserva as funções globais existentes como fachadas compatíveis;
- adiciona testes de caracterização ao contrato arquitetural;
- não altera banco, schema, interface, permissões ou regras de negócio.

Este arquivo registra mudanças relevantes para desenvolvedores e operadores. O histórico detalhado anterior permanece em `ChangeLog.txt`.

## 1.7.29.1 — documentação como código

- remove comentários de código de todos os arquivos versionados;
- substitui documentação repetitiva por documentação arquitetural, de domínio, segurança e operação;
- introduz README, guia de contribuição, política de segurança, diagramas C4, ADRs e runbooks;
- adiciona validação automática da documentação e da política de ausência de comentários;
- preserva interface, regras de negócio, banco, schema, permissões e comportamento do runtime;
- mantém PHP 8.4 e MySQL 8.0.30 como requisitos mínimos.

## 1.7.27.6 — republicação integral da baseline

- regravou os arquivos descritos no manifesto a partir da baseline 1.7.27.5;
- regenerou contratos canônicos e hashes SHA-256;
- preservou funcionalidades, interface, banco, schema, segurança, permissões e regras de negócio.
