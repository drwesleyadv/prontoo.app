# Histórico de versões

## 1.7.30.16 — Tom exato da área de Requisições

- mantém na área inferior de Requisições exatamente o mesmo tom visual de sua linha;
- remove a diluição causada pela opacidade parcial apenas no preenchimento de Requisições;
- preserva Registros, Velocidade, métricas, banco e schema.

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
