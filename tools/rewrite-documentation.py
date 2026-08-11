from pathlib import Path
from textwrap import dedent

ROOT = Path(__file__).resolve().parents[1]

DOCS = {}

def add(path: str, text: str) -> None:
    DOCS[path] = dedent(text).strip() + "\n"

add("README.md", r'''
# Prontoo

Prontoo é uma aplicação web em PHP para a operação cotidiana de consultórios e clínicas pequenas. Para quem usa o sistema, o objetivo é simples: organizar pacientes, agenda, documentos, tarefas, equipe, financeiro e rotinas operacionais em uma interface direta. Por baixo dessa simplicidade há uma arquitetura deliberadamente rigorosa, porque dados clínicos, financeiros, identidades e permissões não toleram ambiguidade estrutural.

## Estado atual

A versão canônica é `1.8.11.2`, executada exclusivamente na família PHP 8.4 e com MySQL 8.0.30 ou superior. A arquitetura foi consolidada em `1.8.11.1`; desde então o projeto opera em modo de manutenção arquitetural: não se abre um novo ciclo de refatoração sem invariante quebrada ou risco material de produto.

A regra central é simples: **o Runtime coordena; Application expressa casos de uso; Domain contém regras; Infrastructure implementa mecanismos; Presentation renderiza saída**. As dependências perigosas são verificadas por código, não por convenção informal.

## O que está protegido por contrato

No Runtime, SQL de negócio, PDO direto, transações de caso de uso, `OperationGateway` e adapters concretos fora dos composition roots têm orçamento zero. A suíte de Application caracteriza 100% das entradas públicas catalogadas: 49 casos críticos, 30 services, 15 ports e 127 assertivas rastreáveis dentro da suíte rápida. O Architecture Contract também executa MySQL real, budgets de consultas, segurança de instalação, login, logout global, Maestro e smokes do front controller.

A dívida residual é explícita e monotônica. Hotspots de input adapter acima de 500 linhas, referências diretas a Infrastructure e acessos ao gateway genérico podem diminuir, mas não crescer. Quando um hotspot é tocado, `tools/runtime-refactor-on-touch-check` exige redução mensurável de dívida.

## Onde começar

- `docs/index.md`: mapa de toda a documentação.
- `docs/architecture/overview.md`: modelo mental da arquitetura.
- `docs/architecture/layers.md`: responsabilidades por camada.
- `docs/security/threat-model.md`: ameaças e controles.
- `docs/testing/strategy.md`: como os contratos executáveis defendem o sistema.
- `CONTRIBUTING.md`: regras para alterar o código sem degradar a arquitetura.

## Fonte de verdade

Documentação explica intenção. O comportamento normativo está nos contratos executáveis, especialmente `app/architecture.manifest.json`, `app/application.test-contract.json`, `version.json` e nos verificadores em `tools/`. Em caso de divergência, o contrato executável prevalece e a documentação deve ser corrigida.
''')

add("CONTRIBUTING.md", r'''
# Contribuindo com o Prontoo

Contribuir com o Prontoo significa preservar comportamento e invariantes enquanto o produto evolui. A arquitetura já está consolidada; portanto, mudanças devem resolver uma necessidade real de produto, segurança, operação ou manutenção, e não criar refatorações autônomas sem benefício mensurável.

## Ambiente

Use PHP 8.4 e MySQL 8.0.30 ou superior. A família PHP é intencionalmente exata: uma versão posterior não deve ser presumida compatível até que o contrato seja alterado. A branch de integração é `prontoo`.

## Fluxo de mudança

Crie uma branch curta a partir de `prontoo`, implemente o menor escopo coerente, execute os gates aplicáveis e abra PR. O merge só deve ocorrer com `Documentation Contract` e `Architecture Contract` verdes quando esses checks forem disparados.

Mudanças em Runtime devem respeitar a direção de dependências. SQL, PDO e fronteiras transacionais de caso de uso não pertencem a input adapters. Se um arquivo Runtime já estiver classificado como hotspot acima de 500 linhas, qualquer alteração nele deve reduzir o `hotspot_bucket` ou a quantidade de acessos ao gateway genérico; `tools/runtime-refactor-on-touch-check` aplica essa regra.

## Testes e contratos

Comece por `php tools/quality-gate --fast`. Para mudanças relevantes, execute o gate integral e os smokes específicos. Application Services públicos precisam continuar caracterizados no contrato de testes. Alterações de consultas não podem ultrapassar os budgets MySQL existentes.

A documentação é validada por `tools/documentation-check.php`; links locais quebrados e ADRs sem status/data válidos falham a CI. O projeto adota política de ausência de comentários de código inline; a explicação durável pertence a nomes, tipos, testes, ADRs e documentação.

## Release e metadados

`version.json` é a fonte canônica de versão. Não edite fallbacks ou manifests de release de forma independente. Quando arquivos rastreados mudarem, use `php tools/release-contract-reconcile --write` para reconciliar hashes e artefatos derivados, e depois confirme com `--check`.

## Critério de qualidade

Uma contribuição está pronta quando o comportamento desejado é verificável, as invariantes continuam verdadeiras, a dívida arquitetural não aumentou silenciosamente e a documentação relevante descreve o estado que realmente será mergeado.
''')

add("SECURITY.md", r'''
# Segurança do Prontoo

A segurança do Prontoo é tratada como propriedade arquitetural. Controles importantes são fail-closed sempre que uma decisão permissiva sem evidência persistente poderia ampliar acesso, atravessar consultórios ou manter uma sessão indevidamente válida.

## Fronteiras protegidas

As principais fronteiras são identidade, sessão, autorização, isolamento por consultório, persistência, auditoria e instalação. O Runtime não pode contornar essas fronteiras com SQL ou PDO local. A camada Application expressa casos de uso e ports; Infrastructure implementa acesso a dados e mecanismos criptográficos; invariantes centrais impedem dependências proibidas.

## Autenticação e sessão

O fluxo usa senha e ciclo de MFA conforme a política canônica. Sessões carregam uma geração de autenticação persistente. Logout global tenta rotacionar essa geração até três vezes; se a revogação global não puder ser confirmada, a sessão local é destruída e a resposta é HTTP 503, em vez de simular sucesso. Uma sessão com geração obsoleta é rejeitada cedo, antes de continuar o processamento normal.

## Autorização e tenant

Permissão não é inferida apenas pela presença de uma sessão. A ação solicitada, o vínculo com o consultório e o contexto precisam ser coerentes. Consultas e comandos que dependem de `clinic_id` são protegidos por contratos de escopo. O objetivo é impedir tanto vazamento de leitura quanto escrita cruzada entre tenants.

## Auditoria

Eventos críticos usam ledger e trilhas de auditoria. Trabalho diferido é supervisionado pelo Maestro e pode usar spool persistente, retry e dead-letter. Falhas de auditoria não devem ser confundidas com autorização concedida.

## Vulnerabilidades

Não publique dados sensíveis, credenciais, dumps de banco ou provas contendo informações pessoais em issues abertas. Ao relatar uma vulnerabilidade, descreva pré-condição, impacto, caminho de reprodução mínimo e evidência sanitizada. A correção deve incluir regressão automatizada sempre que a falha puder ser reproduzida de modo determinístico.

## Fonte normativa

`docs/security/` explica o modelo. `tools/security-regression-check.php`, os smokes HTTP, os contratos de tenant e `app/architecture.manifest.json` são a evidência executável de que as regras continuam ativas.
''')

add("CHANGELOG.md", r'''
# Histórico de versões

## 1.8.11.2 — Manutenção arquitetural e hardening de sessão

- formaliza `1.8.11.1` como baseline arquitetural consolidada e adota modo de manutenção incremental.
- institui `refactor-on-touch` executável para hotspots Runtime acima de 500 linhas.
- extrai o logout para coordinator dedicado com até três tentativas de revogação global.
- torna explícita a degradação do logout com sessão local destruída e resposta HTTP 503 quando a revogação global não é confirmada.
- adiciona smoke HTTP real para CSRF, rotação de sessão, revogação multissessão e falha de escrita da geração canônica.
- preserva schema, banco e interface e mantém as invariantes arquiteturais de risco em zero.

## 1.8.11.1 — Fechamento da arquitetura em camadas

- conclui as fases arquiteturais e o ciclo corretivo que tornaram verdadeiros os contratos de fronteira Runtime.
- fixa em zero SQL de negócio, PDO direto, transações de caso de uso no Runtime, `OperationGateway`, símbolos internos não resolvidos e findings objetivos do auditor SOLID.
- consolida testes de Application, budgets MySQL e documentação como contratos de CI.

## 1.8.10.x — Migração de casos de uso e contratos

- move responsabilidades transacionais e semânticas para Application/ports/adapters apropriados.
- amplia caracterização de casos críticos e mede dependências residuais por ratchets monotônicos.

## 1.8.9.x e anteriores — Preparação da consolidação

- remove compatibilidade legada, fecha fronteiras de composição, estabiliza PHP 8.4, Maestro, telemetria e contratos de runtime.
- esta seção resume a trajetória histórica; detalhes que ainda ajudam a compreender decisões estão arquivados em `docs/architecture/` e `docs/adr/`.
''')

add("docs/index.md", r'''
# Documentação do Prontoo

Esta documentação descreve o Prontoo como ele existe na arquitetura consolidada. O objetivo não é reproduzir o código em prosa, mas oferecer um modelo mental que permita entender decisões, localizar responsabilidades e operar o sistema sem depender de conhecimento oral.

## Leitura recomendada

Comece por `architecture/overview.md` e `architecture/layers.md`. Em seguida, escolha a trilha correspondente ao trabalho: `domain/` para comportamento de negócio, `security/` para controles, `operations/` para execução e incidentes, `database/` para integridade, `performance/` para budgets e telemetria, e `testing/` para a malha de verificação.

## Arquitetura vigente

Os documentos `architecture/overview.md`, `layers.md`, `dependencies.md`, `runtime-boundary.md`, `responsibility-map.md`, `data-flow.md` e os diagramas C4 textuais são a referência explicativa principal. `architecture/MAINTENANCE.md` explica por que o projeto não está em novo ciclo de refatoração.

Documentos `phase-*`, auditorias de consolidação e conformidade PHP são históricos. Eles foram reescritos para registrar o que cada etapa acrescentou ao estado final, não para orientar novas migrações.

## Regra de autoridade

A documentação é secundária aos contratos executáveis. `version.json` define release; `app/architecture.manifest.json` define políticas e budgets; `app/application.test-contract.json` define caracterização de Application; `tools/` contém os gates. Se prosa e gate divergirem, o gate representa o estado operacional e a prosa deve ser atualizada.

## Vocabulário

Termos como Runtime, port, adapter, composition root, ratchet, tenant, action ledger, Maestro e geração de sessão estão definidos em `glossary.md`.
''')

add("docs/glossary.md", r'''
# Glossário

## Application
Camada que expressa casos de uso. Coordena regras de domínio por interfaces explícitas e não conhece detalhes de HTTP ou renderização.

## Adapter
Implementação concreta de uma interface ou mecanismo de borda. Adapters de persistência pertencem a Infrastructure; input adapters pertencem ao Runtime.

## Composition root
Ponto onde implementações concretas são conectadas às abstrações. É uma exceção deliberada à regra que proíbe espalhar adapters concretos pelo Runtime.

## Domain
Regras, políticas e invariantes de negócio que devem ser compreensíveis sem depender de HTTP ou banco concreto.

## Infrastructure
Mecanismos externos e detalhes concretos: PDO, persistência, filesystem, criptografia, integrações e adaptadores técnicos.

## Invariante
Propriedade que deve permanecer verdadeira. No Prontoo, várias invariantes arquiteturais são medidas em CI e têm orçamento zero.

## Maestro
Ciclo supervisionado server-side que executa trabalho operacional e diferido com políticas de retry, isolamento e observabilidade.

## Port
Interface pela qual Application declara uma necessidade sem escolher a tecnologia que a implementará.

## Presentation
Camada de formatação e renderização. Recebe dados já decididos pelas camadas internas e produz HTML/JSON ou estruturas de apresentação.

## Ratchet
Budget monotônico usado para dívida conhecida. O valor atual pode cair, mas não pode subir sem falhar o contrato.

## Runtime
Camada de entrada e orquestração: roteamento, request, sessão, autorização de borda, coordenação de serviços e redirects. Não é camada de persistência.

## Tenant
Consultório que delimita dados e permissões. `clinic_id` é parte da fronteira de isolamento e não mero filtro de conveniência.

## Action ledger
Registro persistente de ações relevantes para integridade, rastreabilidade e validação do fluxo operacional.

## Geração de sessão
Valor persistente associado ao usuário que permite invalidar sessões já emitidas. O logout global rotaciona essa geração.

## Refactor-on-touch
Política de manutenção: se um hotspot Runtime acima de 500 linhas for alterado, a mudança precisa reduzir uma métrica de dívida definida pelo gate.
''')

add("docs/architecture/overview.md", r'''
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
''')

add("docs/architecture/layers.md", r'''
# Camadas e responsabilidades

As camadas do Prontoo representam tipos diferentes de decisão. A pergunta útil não é “em qual pasta cabe este código?”, mas “qual conhecimento este código precisa possuir?”.

## Domain

Contém regras e políticas que expressam significado de negócio. Não deve depender de request HTTP, sessão, PDO ou detalhes de renderização. Uma regra que poderia ser discutida com alguém do domínio sem mencionar framework ou tabela tende a pertencer aqui.

## Application

Contém casos de uso. Services coordenam operações e dependem de ports. A camada sabe **o que precisa acontecer**, mas não escolhe **como o banco executa** ou **como a página responde**. A superfície pública de Application é caracterizada por contrato de testes.

## Infrastructure

Implementa detalhes concretos: banco, filesystem, criptografia, adapters e mecanismos operacionais. É a camada onde PDO pode existir. Ela satisfaz ports definidos em camadas internas ou fornece mecanismos explicitamente compostos.

## Presentation

Renderiza dados e estrutura saída. Não deve decidir autorização, transação ou persistência. Seu trabalho é transformar estado já resolvido em representação adequada.

## Runtime

É o input adapter da aplicação. Faz roteamento, lê request, resolve sessão e contexto, aplica guards de borda, chama Application e organiza resposta/redirect. Runtime não é um atalho para “tudo que roda”; SQL, PDO e transações de negócio têm budget zero aqui.

## Composition

Composition roots conectam implementações concretas às abstrações. Concreto fora desses pontos é sinal de acoplamento indevido e é detectado pelos contratos.

A direção desejada é das bordas para o centro sem permitir que detalhes técnicos comandem regras internas.
''')

add("docs/architecture/dependencies.md", r'''
# Direção de dependências

Dependência é uma forma de conhecimento. Quando um módulo conhece uma classe concreta, uma tabela ou o protocolo HTTP, ele assume responsabilidade por essa decisão. A arquitetura controla esse conhecimento para que mudanças locais permaneçam locais.

## Regra principal

Domain não depende de Runtime, Presentation ou Infrastructure. Application depende de políticas internas e de ports, não de PDO ou de páginas. Runtime e Presentation podem depender de Application e Domain para usar decisões já definidas. Infrastructure implementa mecanismos e adapters. Composition roots são a ponte explícita entre abstrações e concretos.

## O que a CI impede

Os gates rejeitam SQL de negócio e PDO em Runtime, adapters concretos fora dos pontos de composição, símbolos internos que deixaram de resolver e outras violações tokenizadas. A fronteira é verificada no código-fonte, evitando que uma convenção arquitetural dependa apenas de revisão humana.

## Acoplamento residual

Ainda existem referências diretas de input adapters a Infrastructure e acessos ao gateway genérico medidos por ratchets. Eles representam dívida histórica tolerada, não modelo recomendado. O valor pode diminuir; novos acoplamentos não devem elevar o teto.

## Como decidir uma nova dependência

Se o chamador precisa de uma capacidade de negócio, prefira um Application Service. Se Application precisa de um mecanismo externo, declare um port. Se o detalhe é puramente técnico, mantenha-o em Infrastructure. Se a dependência só existe para montar o grafo, concentre-a no composition root.
''')

add("docs/architecture/c4-context.md", r'''
# C4 — Contexto do sistema

## Pessoas e sistemas ao redor

O Prontoo é usado por profissionais e colaboradores de consultórios para administrar rotina clínica e administrativa. Pacientes são entidades centrais do domínio, mas o sistema é operado por usuários autenticados vinculados a consultórios. A hospedagem fornece runtime PHP, MySQL, filesystem persistente e execução agendada do Maestro.

## Sistema Prontoo

Como sistema único, o Prontoo concentra agenda, cadastro e ficha de pacientes, documentos, tarefas, equipe, financeiro, auditoria, telemetria e rotinas de supervisão. Ele mantém isolamento por consultório mesmo quando o mesmo runtime atende múltiplos tenants.

## Fronteiras externas

HTTP/HTTPS é a fronteira de interação. MySQL é a persistência relacional. `ssd/` é a raiz persistente para artefatos e estados que não pertencem ao banco. O cron/server cycle aciona o Maestro. GitHub e CI governam publicação e contratos do código, não participam do fluxo clínico em runtime.

## Objetivo arquitetural

O contexto externo é pequeno de propósito. Complexidade fica encapsulada dentro do monólito modular para que operação e deploy permaneçam simples, enquanto as fronteiras internas protegem regras sensíveis.
''')

add("docs/architecture/c4-containers.md", r'''
# C4 — Containers lógicos

O Prontoo é implantado como uma aplicação PHP única, mas pode ser entendido por quatro containers lógicos.

## Aplicação web PHP

Recebe requisições, autentica usuários, coordena casos de uso e renderiza respostas. Internamente contém Runtime, Application, Domain, Infrastructure e Presentation.

## MySQL

Armazena estado relacional, incluindo identidades, vínculos de consultório, pacientes, agenda, financeiro, tarefas e estruturas de integridade. O schema canônico é de instalação limpa e não recebe DDL arbitrário durante requests normais.

## Armazenamento persistente `ssd/`

Guarda arquivos, imagens, PDFs, telemetria e filas/estados operacionais que não devem depender do diretório efêmero do código. Backups precisam considerar banco e `ssd/` como partes complementares do estado.

## Maestro

É executado server-side em ciclos supervisionados. Consome trabalho diferido, aplica retry/backoff, preserva escopo de consultório e registra estado operacional. Não é um segundo produto; é um modo de execução da mesma base de código.

## CI/CD

GitHub Actions não é container de produção, mas é uma fronteira de governança: executa contratos de arquitetura, documentação, MySQL, segurança e runtime antes do merge.
''')

add("docs/architecture/data-flow.md", r'''
# Fluxo de dados

O fluxo típico começa com uma requisição HTTP e termina em uma resposta ou redirect. A arquitetura procura tornar explícita cada mudança de responsabilidade ao longo desse caminho.

## Leitura

Runtime valida contexto e chama um serviço de leitura ou composição apropriada. Application expressa a intenção. Infrastructure busca dados sob escopo de tenant. O resultado volta como estrutura estável e Presentation o transforma em HTML ou JSON. Read models críticos possuem budgets MySQL para evitar regressões de consultas.

## Comando

Runtime interpreta e valida a entrada de borda, mas não abre transação de negócio. Um Application Service coordena o caso de uso; ports entregam operações ao adapter concreto. A transação, quando necessária, é encerrada na fronteira apropriada. Auditoria e invalidação de cache seguem a operação de forma explícita.

## Sessão

Autenticação cria contexto de usuário e geração de sessão. Em cada fluxo protegido, guards verificam validade antes de continuar. Logout rotaciona a geração global; sessões antigas são recusadas no primeiro uso subsequente.

## Trabalho diferido

Eventos que podem ser processados fora da resposta síncrona entram em spool persistente. Maestro retoma o contexto necessário, aplica política de retry e separa falha temporária de dead-letter.

O princípio comum é não deixar dados mudarem de significado silenciosamente entre camadas.
''')

add("docs/architecture/responsibility-map.md", r'''
# Mapa de responsabilidades

## Runtime
Roteamento, request, sessão, guards de borda, redirects e coordenação curta. Não contém persistência de negócio nem transações de caso de uso.

## Application
Casos de uso, services, ports e coordenação transacional sem conhecimento de HTTP. Sua superfície pública é integralmente caracterizada.

## Domain
Políticas, invariantes e regras que definem significado do negócio.

## Infrastructure
PDO, repositórios/adapters, filesystem, criptografia, persistência e mecanismos externos.

## Presentation
HTML, JSON e estruturas visuais. Formata; não autoriza nem persiste.

## Composition roots
Montagem de dependências concretas. Concentram acoplamento inevitável e impedem que ele se espalhe.

## Maestro
Orquestra trabalho server-side e diferido com supervisão operacional.

## Contratos
`tools/quality-gate`, `tools/runtime-input-boundary-check`, `tools/runtime-refactor-on-touch-check`, `tools/application-test-contract-check`, budgets MySQL e smokes HTTP são responsáveis por transformar esse mapa em propriedade verificável.

Quando uma nova função parecer caber em várias áreas, escolha a camada que possui a decisão, não a camada que possui o chamador atual.
''')

add("docs/architecture/runtime-boundary.md", r'''
# Fronteira do Runtime

Runtime é a camada que mais facilmente acumula responsabilidades porque está próxima das páginas. A consolidação arquitetural transformou essa zona em uma fronteira mensurável.

## O que pertence ao Runtime

Roteamento, parsing de request, sessão, resolução de tenant, guards de acesso, chamada de casos de uso, composição de resposta e redirects. Ele pode coordenar, mas não deve implementar a persistência ou a transação que dá atomicidade ao negócio.

## Invariantes de zero

SQL de negócio, PDO direto, transações de caso de uso, `OperationGateway` e adapters concretos fora dos composition roots têm orçamento zero no Runtime. Referências internas quebradas também são rejeitadas.

## Dívida aceita

Há input adapters historicamente grandes. O budget atual registra 39 acima de 500 linhas, sete acima de 700 e quatro acima de 1000. Também existem ratchets para acoplamentos genéricos. Isso não é licença para crescimento: `refactor-on-touch` exige redução quando um hotspot é alterado.

## Por que não decompor tudo agora

Tamanho não é, sozinho, violação de arquitetura. Depois que persistência e transação saíram dos adapters, o problema residual é principalmente modificabilidade. A política de manutenção evita risco de reescrever páginas estáveis apenas para obter números menores.
''')

add("docs/architecture/MAINTENANCE.md", r'''
# Manutenção da arquitetura consolidada

A baseline consolidada é `1.8.11.1`. A versão `1.8.11.2` inaugura explicitamente o modo `consolidated_maintenance`: arquitetura deixa de ser um programa contínuo de refatoração e passa a atuar como conjunto de limites executáveis para a evolução do produto.

## Quando abrir novo ciclo

Somente quando um invariante importante não puder ser restaurado por mudança focal, quando surgir risco material de produto/segurança que a estrutura atual não absorva, ou quando uma nova capacidade exigir fronteira arquitetural inexistente. “Há arquivos grandes” não é condição suficiente.

## Refactor-on-touch

Hotspots Runtime acima de 500 linhas são tratados oportunisticamente. Se um deles precisar mudar por razão real, a mesma mudança deve reduzir o bucket de tamanho ou acessos ao gateway genérico. Assim a dívida cai com o trabalho do produto em vez de competir com ele.

## Métricas residuais

Ratchets de gateway genérico, referências diretas a Infrastructure e hotspots são dívida conhecida. Os valores não definem falha funcional; definem teto. Invariantes estruturais de risco permanecem em zero.

## Critério de sucesso

Uma arquitetura madura não é a que muda sempre, mas a que permite mudar o produto sem reabrir problemas já resolvidos.
''')

add("docs/architecture/SOLID-AUDIT.md", r'''
# Auditoria SOLID consolidada

A auditoria SOLID do Prontoo é objetiva: procura padrões estruturais que indiquem responsabilidades indevidas, dependências invertidas incorretamente ou extensões frágeis. Na baseline consolidada, findings objetivos e hotspots acionáveis do auditor estão em zero.

Isso não significa que todo arquivo seja pequeno nem que toda decisão seja perfeita. Significa que o detector não encontra as classes de problema para as quais foi projetado. Dívida de input adapters grandes é acompanhada por um contrato separado.

## Interpretação

SOLID funciona aqui como vocabulário de risco, não como obrigação de criar abstrações. SRP orienta separação de decisões; DIP mantém Application dependente de ports; ISP favorece interfaces focadas; OCP e LSP são aplicados onde extensibilidade real existe.

## Regra de manutenção

Qualquer nova abstração deve reduzir acoplamento ou tornar um caso de uso testável de forma concreta. Uma interface sem consumidor, um service que apenas renomeia uma função ou uma decomposição que aumenta navegação sem reduzir decisão compartilhada não é melhoria automática.

O auditor é executado pelo quality gate; a documentação apenas explica como interpretar seu zero atual.
''')

add("docs/architecture/patient-contact-command.md", r'''
# Caso de uso — alteração de contato do paciente

Este caso ilustra a arquitetura de comando. Runtime recebe a intenção do usuário, valida contexto básico e delega ao Application Service. `PatientContactCommandService` expressa o caso de uso e depende de um port de comando; a implementação concreta de persistência fica fora de Application.

## Por que este recorte importa

Dados de contato parecem simples, mas misturar request, autorização e SQL na mesma página faria uma regra pequena contaminar a fronteira Runtime. O command service cria um ponto de teste estável e permite que o adapter de banco evolua sem mudar o contrato do caso de uso.

## Garantias esperadas

O paciente deve pertencer ao consultório correto, a escrita deve ocorrer sob contexto autorizado e a resposta não deve revelar detalhes de persistência. Mudanças futuras devem manter o port focado na capacidade necessária, em vez de reintroduzir acesso genérico a dados no input adapter.
''')

add("docs/architecture/patient-contact-view.md", r'''
# Caso de uso — leitura de contato do paciente

A leitura de dados de paciente segue uma fronteira própria porque leitura e comando têm necessidades diferentes. Runtime solicita uma visão; Application fornece um serviço de leitura por port; Infrastructure resolve a consulta sob escopo do consultório; Presentation recebe uma estrutura pronta para exibição.

## Objetivo

O desenho evita que uma página conheça detalhes de tabelas e permite caracterizar a entrada pública do serviço. Também torna possível aplicar budget de consultas sem acoplar o teste ao HTML.

## Regra de evolução

Read models devem permanecer somente leitura. Se uma tela passar a precisar de uma mudança de estado, crie ou reutilize um comando explícito em vez de esconder escrita no fluxo de consulta.
''')

add("docs/architecture/phase-1-refactoring.md", r'''
# Arquivo histórico — Fase 1: separação inicial de responsabilidades

Este documento não descreve trabalho pendente. Ele registra a primeira ideia que permaneceu na arquitetura final: separar decisão de entrada, regra de negócio, persistência e apresentação.

A fase inicial mostrou que arquivos grandes eram menos problemáticos pelo tamanho do que pela mistura de razões para mudar. A consequência durável foi o mapa de camadas hoje aplicado por contratos.

No estado atual, qualquer nova refatoração deve ser motivada por caso de uso, falha de invariante ou redução mensurável de dívida. O plano desta fase não deve ser repetido como campanha autônoma.
''')

add("docs/architecture/phase-2-presentation-boundaries.md", r'''
# Arquivo histórico — Fase 2: fronteiras de apresentação

A contribuição durável desta fase foi separar renderização de decisões de autorização e persistência. Presentation passou a ser tratada como transformação de dados já resolvidos, e não como lugar para completar regras do caso de uso.

Essa decisão reduz testes frágeis de HTML e evita que a mesma regra seja duplicada em várias páginas. Hoje a fronteira está incorporada ao mapa arquitetural; este arquivo existe apenas para explicar a origem da decisão.
''')

add("docs/architecture/phase-3-read-use-cases.md", r'''
# Arquivo histórico — Fase 3: casos de uso de leitura

Esta fase introduziu a ideia de leituras críticas como casos de uso explícitos, com ports e estruturas estáveis entre Application e Infrastructure.

O efeito final é visível nos services de pacientes, financeiro e operação e nos budgets MySQL. Read models podem ser otimizados sem mover SQL para Runtime e sem transformar Presentation em camada de acesso a dados.

Novas leituras devem seguir o padrão apenas quando ele trouxer isolamento, teste ou orçamento mensurável; não é necessário criar service para cada `SELECT` trivial.
''')

add("docs/architecture/phase-4-transactional-commands.md", r'''
# Arquivo histórico — Fase 4: comandos transacionais

A fase de comandos transacionais retirou do Runtime a responsabilidade de decidir atomicidade. A unidade transacional passou a acompanhar o caso de uso, mediada por Application e adapters apropriados.

Esse movimento é a base do contrato atual `Runtime business transactions = 0`. A página pode coordenar a intenção, mas não deve abrir/fechar uma transação que define consistência de negócio.

O documento é histórico; a regra vigente está em `runtime-boundary.md` e nos gates.
''')

add("docs/architecture/phase-5-critical-financial-command.md", r'''
# Arquivo histórico — Fase 5: comando financeiro crítico

O financeiro foi usado como prova de que uma fronteira de Application deveria sobreviver a um caso com maior custo de erro. Recebimentos, movimentos e consolidação exigem atomicidade, escopo de tenant e invariantes numéricas que não cabem em um controller de página.

O resultado final são services e ports financeiros caracterizados, adapters de persistência e budgets reais de banco. A fase não é plano de trabalho futuro; ela registra por que o domínio financeiro ajudou a fechar a arquitetura.
''')

add("docs/architecture/CONSOLIDATION-AUDIT-1.8.7.1.md", r'''
# Arquivo histórico — Auditoria de consolidação 1.8.7.1

A auditoria `1.8.7.1` marcou a transição entre uma arquitetura ainda em migração e um repositório capaz de rejeitar resíduos estruturais por CI. Tombstones e unidades sem responsabilidade executável deixaram de ser considerados compatibilidade aceitável.

O valor histórico desta auditoria é mostrar a mudança de método: em vez de listas manuais de “arquivos para revisar”, o projeto passou a preferir classificações e contratos verificáveis.

Os números originais desta etapa não devem ser usados como estado atual. A fonte atual é `app/architecture.manifest.json` e os gates da versão `1.8.11.2`.
''')

add("docs/architecture/CONSOLIDATION-AUDIT-1.8.9.1.md", r'''
# Arquivo histórico — Auditoria de consolidação 1.8.9.1

A auditoria `1.8.9.1` verificou a arquitetura depois da remoção de compatibilidade legada. O objetivo foi distinguir referências históricas — úteis para rastreabilidade — de fronteiras executáveis ainda ativas.

A consequência que permanece é simples: compatibilidade antiga não pode sobreviver apenas porque um documento a menciona. O runtime atual deve resolver símbolos e dependências pelos caminhos nativos, enquanto mapas históricos podem registrar de onde vieram.

O estado presente é governado pela baseline consolidada `1.8.11.1` e pelo modo de manutenção de `1.8.11.2`.
''')

add("docs/audits/php84-conformance-1.8.6.1.md", r'''
# Arquivo histórico — Conformidade PHP 8.4

A versão `1.8.6.1` tornou explícita uma decisão operacional que continua vigente: o Prontoo suporta a família PHP 8.4 de forma exata, e não uma faixa aberta “8.4 ou superior”.

A auditoria revisou a base para APIs e depreciações da família escolhida e deixou um contrato permanente de lint/conformidade. O objetivo não era congelar PHP para sempre, mas impedir upgrade acidental sem validação sistêmica.

Hoje `version.json`, o bootstrap e a CI repetem a mesma política. Uma migração futura para outra família deve ser tratada como mudança consciente, com auditoria e testes próprios.
''')

add("docs/adr/0001-layered-architecture.md", r'''
# ADR 0001 — Arquitetura em camadas

**Status:** aceito
**Data:** 2026-08-11

## Contexto

O produto precisa manter páginas simples enquanto lida com regras de autorização, persistência, financeiro e auditoria. A base histórica misturava essas decisões em Runtime.

## Decisão

Adotar Domain, Application, Infrastructure, Presentation e Runtime como camadas semânticas, com composition roots explícitos. Dependências proibidas são verificadas por contratos tokenizados.

## Consequências

Casos de uso críticos ganham surfaces testáveis; PDO e SQL ficam concentrados; páginas perdem responsabilidade transacional. Há custo de navegação entre classes, compensado por fronteiras mais estáveis. A arquitetura opera hoje em manutenção, não em expansão contínua de abstrações.
''')

add("docs/adr/0002-tenant-isolation.md", r'''
# ADR 0002 — Isolamento por consultório

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Uma única aplicação atende múltiplos consultórios. Um filtro esquecido poderia expor ou alterar dados de outro tenant.

## Decisão

Tratar `clinic_id` e o vínculo do usuário como parte da fronteira de segurança. Leitura, escrita, autorização e auditoria devem preservar o contexto de consultório; mecanismos de SQL scope e invariantes complementam validações de caso de uso.

## Consequências

Consultório não é parâmetro opcional de consulta. Testes de isolamento fazem parte dos smokes críticos e qualquer operação sem contexto suficiente deve falhar em vez de assumir escopo permissivo.
''')

add("docs/adr/0003-action-ledger.md", r'''
# ADR 0003 — Action ledger

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Operações relevantes precisam de rastreabilidade e prova de que a sequência de ações pertence ao contexto correto.

## Decisão

Manter um ledger de ações de camada 2 como parte do schema canônico e usar políticas de integridade/auditoria para registrar eventos críticos sem espalhar lógica de ledger por páginas.

## Consequências

O ledger adiciona custo de persistência e verificação, mas cria uma base comum para auditoria e integridade. Trabalho diferido pode transportar contexto de prova, desde que o Maestro o restaure de forma controlada.
''')

add("docs/adr/0004-json-cache-policy.md", r'''
# ADR 0004 — Política de cache JSON

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Painéis e leituras agregadas precisam ser rápidos, mas cache não pode transformar dados clínicos ou financeiros em estado incoerente por longos períodos.

## Decisão

Usar cache JSON server-side com TTL curto e invalidação orientada pelo domínio. Cache é otimização; não é fonte de verdade e não substitui invariantes do banco.

## Consequências

Falha de cache deve degradar para leitura válida sempre que a política permitir. Escritas relevantes invalidam gerações/chaves seletivas. Budgets de consulta continuam medindo o caminho real para evitar que o cache esconda regressões arquiteturais.
''')

add("docs/adr/0005-maestro-supervised-server-cycle.md", r'''
# ADR 0005 — Ciclo supervisionado do Maestro

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Auditoria diferida e rotinas operacionais não devem alongar respostas HTTP nem depender de execução manual.

## Decisão

Executar Maestro server-side em ciclos supervisionados, com preflight, escopo de consultório, retry/backoff, dead-letter e estado operacional persistente.

## Consequências

A resposta web pode delegar trabalho não interativo sem perder rastreabilidade. O Maestro precisa permanecer idempotente onde possível e distinguir falha temporária, erro permanente e atenção histórica.
''')

add("docs/adr/0005-runtime-semantic-closure.md", r'''
# ADR 0005 — Fechamento semântico do Runtime

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Separar arquivos por pasta não bastava enquanto Runtime ainda executava SQL e transações de negócio.

## Decisão

Definir fechamento por comportamento mensurável: zero SQL de negócio, PDO direto e transações de caso de uso no Runtime; zero `OperationGateway`; adapters concretos limitados aos composition roots. Detectores devem reconhecer formas semânticas equivalentes, não apenas nomes específicos de método.

## Consequências

A arquitetura passa a ser verdadeira por contrato. Falsos zeros são tratados como defeitos do detector e corrigidos antes de declarar fechamento.
''')

add("docs/adr/0007-page-load-telemetry-source-of-truth.md", r'''
# ADR 0007 — Navegação HTML como fonte de telemetria de página

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Contar toda requisição HTTP como “carregamento de página” mistura fetch, XHR, JSON, redirects e assets, tornando métricas de experiência pouco interpretáveis.

## Decisão

Uma carga de página é uma navegação de documento HTML concluída. A medição começa no primeiro ponto executável do front controller e termina após o último passo útil de renderização. Requisições auxiliares ficam fora dessa série.

## Consequências

Telemetria de página passa a medir experiência de navegação, enquanto outras requisições podem ser observadas separadamente.
''')

add("docs/adr/0008-runtime-input-boundary-correction.md", r'''
# ADR 0008 — Correção da fronteira de input adapters

**Status:** aceito
**Data:** 2026-08-11

## Contexto

Após o fechamento semântico, restaram input adapters grandes. Decompor todos imediatamente criaria grande superfície de mudança sem evidência de defeito.

## Decisão

Tratar tamanho e acoplamentos residuais como ratchets e aplicar `refactor-on-touch`: hotspots acima de 500 linhas só podem ser alterados se uma métrica de dívida cair.

## Consequências

A dívida se reduz junto com mudanças úteis do produto. Arquivos estáveis não são reescritos apenas para melhorar métricas cosméticas.
''')

add("docs/database/schema-overview.md", r'''
# Visão geral do banco de dados

O Prontoo usa MySQL como fonte relacional de verdade. A versão atual exige MySQL 8.0.30 ou superior e mantém um schema canônico de instalação limpa, identificado por `prontoo_1_7_20_6_clean_schema_r7_layer2_ledger`.

## Estrutura

O contrato atual contabiliza 62 tabelas, das quais 60 são verificadas como operacionais pelos gates. O desenho cobre identidade, consultórios, pacientes, agenda, financeiro, tarefas, documentos, permissões, auditoria e estruturas de suporte.

## Identificadores e sequência

Tabelas usam `Seq` não nulo e único com geração nativa baseada em `UUID_SHORT()` onde definido pelo schema. A regra evita uma tabela global de sequência e mantém a integridade verificável no MySQL.

## Mutação de schema

Requests normais não executam DDL. Instalação e CI possuem caminhos explícitos para montar/verificar schema. A aplicação foi projetada para banco limpo; uma alteração futura de schema precisa ser uma decisão de release, não efeito colateral de boot.

## Relação com as camadas

Runtime não conhece SQL. Infrastructure concentra acesso concreto; Application expressa casos de uso e ports. Essa separação permite testar schema e budgets sem misturar persistência com controllers.
''')

add("docs/database/invariants.md", r'''
# Invariantes de banco de dados

Invariantes são condições que o sistema não negocia. Algumas são impostas pelo schema; outras são verificadas por código e testes. O objetivo é tornar estados inválidos difíceis de criar e fáceis de detectar.

## Classes principais

Isolamento por consultório impede cruzamento de tenant. Identificadores `Seq` preservam unicidade. Relacionamentos e constraints defendem referências válidas. Regras financeiras protegem equilíbrio e unicidade operacional. O action ledger acrescenta rastreabilidade a ações críticas.

## Atomicidade

Quando um caso de uso exige múltiplas escritas coerentes, a transação pertence à fronteira de Application/adapter, nunca à página Runtime. Isso evita que uma resposta HTTP controle consistência de negócio.

## Leitura

Read models críticos são monitorados por budgets de consulta. Budget não é invariante de dado, mas protege uma propriedade operacional: uma leitura não deve tornar-se silenciosamente N+1 ou introduzir escrita.

## Teste

Schema contract, critical runtime smoke e query budgets rodam contra MySQL real na CI. A combinação de constraints e testes é intencional: nenhum dos dois substitui o outro.
''')

add("docs/database/migrations-policy.md", r'''
# Política de evolução do schema

O Prontoo trabalha com um schema canônico limpo. O runtime não possui autorização para “consertar” ou migrar o banco durante requests comuns.

## Regra

DDL só pode ocorrer em caminhos explicitamente autorizados de instalação, ferramentas de engenharia ou CI. `version.json` declara se uma release possui mudanças de banco/schema. A `1.8.11.2` não possui.

## Por que

Migração automática no boot mistura disponibilidade com manutenção e pode transformar uma falha de deploy em mutação parcial de dados. Separar as duas coisas permite rollback de código mais previsível e auditoria clara do que mudou.

## Mudança futura

Uma evolução real de schema deve definir pré-condições, compatibilidade, backup, validação e rollback antes do merge. Se o projeto continuar exigindo banco limpo para instalação, isso precisa permanecer explícito no release contract.
''')

add("docs/database/financial-integrity.md", r'''
# Integridade financeira

O domínio financeiro exige mais do que validação de formulário. Um recebimento ou movimento precisa ser coerente como unidade, permanecer no consultório correto e deixar rastros suficientes para conferência.

## Fronteira

Application Services financeiros coordenam casos de uso como recebimentos, movimentos, gavetas, metas e consolidação. Ports descrevem capacidades; Infrastructure executa persistência. Runtime não abre as transações de negócio.

## Invariantes

Escritas relacionadas devem ser atômicas quando a operação exige. Duplicidade operacional deve ser impedida pelo caso de uso e pelo banco onde aplicável. Devedor, recurso financeiro, consultório e estado da operação precisam permanecer coerentes.

## Performance

Leituras financeiras críticas participam dos budgets MySQL. Read models não podem introduzir INSERT/UPDATE/DELETE/REPLACE. A meta é manter previsibilidade sem trocar correção por velocidade.

## Auditoria

Movimentos relevantes devem ser rastreáveis por ledger/auditoria; falha de apresentação não pode reexecutar silenciosamente uma operação financeira já confirmada.
''')

add("docs/domain/patients.md", r'''
# Domínio de pacientes

Paciente é uma entidade central do Prontoo e aparece em cadastro, ficha, recepção, agenda, documentos e financeiro. Por isso, o domínio evita uma “classe paciente” monolítica e usa casos de uso focados para leitura e comando.

## Leituras

Services de leitura entregam dados necessários às telas sem expor SQL. Histórico de recepção possui leitura própria porque custo e forma de consulta são diferentes do cadastro básico. Budgets MySQL protegem caminhos críticos.

## Comandos

Alteração de contato e comandos de ficha usam ports específicos. A escrita precisa respeitar tenant e identidade do paciente. Runtime coleta intenção; Application coordena; Infrastructure persiste.

## Isolamento

Um identificador de paciente não é suficiente para acesso. O consultório ativo faz parte do contexto e deve ser validado em toda leitura/escrita.

## Evolução

Quando uma nova função da ficha for criada, prefira ampliar um caso de uso coerente ou criar um novo recorte sem devolver persistência genérica à página.
''')

add("docs/domain/appointments.md", r'''
# Domínio de agenda

Agenda combina leitura intensiva, mudança de estado e regras temporais. O fluxo do paciente evolui de agendado para estados como finalizado ou cancelado, e operações de mover/reagendar precisam manter consistência de data, hora, paciente e consultório.

## Arquitetura

Runtime interpreta ações da página; Application/serviços operacionais coordenam comandos; Infrastructure executa consultas e escritas. Transações de agenda foram retiradas do Runtime durante o fechamento corretivo.

## Tempo

Datas armazenadas e exibidas seguem políticas explícitas de UTC e timezone do consultório. Normalização temporal é infraestrutura/política compartilhada, não regra improvisada por página.

## Performance

Agenda participa de cenários reais de query budget. Alterações que aumentem consultas devem ser justificadas e medidas, especialmente em visões diárias com múltiplos pacientes.

## Segurança

Toda operação é tenant-scoped e sujeita à autorização da ação correspondente.
''')

add("docs/domain/financial.md", r'''
# Domínio financeiro

O financeiro do Prontoo foi desenhado para a operação de clínicas pequenas: recursos como gavetas, cofre e bancos; contas a pagar/receber; consolidação; metas e movimentos.

## Casos de uso

Application concentra services financeiros específicos em vez de um gateway universal. Há services para dados, gavetas, movimentos, recebimentos, consolidação, metas, revisão e fluxos relacionados a pacientes.

## Consistência

Recebimentos e movimentos que precisam de múltiplas escritas são transacionais na fronteira apropriada. O tenant e as entidades relacionadas precisam ser válidos antes da confirmação. A UI não é fonte de verdade para saldo.

## Conferência

Consolidação é uma operação de fechamento/conferência, não simples soma de tela. Estados intermediários devem permanecer distinguíveis até a confirmação que gera lançamentos definitivos.

## Dívida e evolução

Novas capacidades devem preferir ports semânticos. A meta é reduzir gradualmente dependência de gateways genéricos quando o domínio tocado justificar a mudança.
''')

add("docs/domain/documents.md", r'''
# Domínio de documentos

Documentos combinam modelos, conteúdo do paciente, geração e apresentação. A arquitetura separa identificação/tipo de documento, regras de template e renderização concreta.

## Fluxo

Runtime recebe a intenção. Application coordena emissão quando a operação tem significado de caso de uso. Domain contém políticas de identificador, tipo e template. Presentation/Infrastructure cuidam de HTML, PDF e mecanismos concretos.

## Segurança

Um documento pertence ao contexto do consultório e do paciente aplicável. Caminhos de arquivo não devem ser aceitos como autorização. PDFs persistentes ficam em `ssd/pdfs`, fora da árvore de código.

## Integridade

Geração deve ser determinística quanto aos dados fornecidos e não modificar o domínio durante uma leitura. Se emissão produzir efeitos auditáveis, eles precisam ser explícitos no caso de uso.
''')

add("docs/domain/tasks.md", r'''
# Domínio de tarefas e avisos

Tarefas organizam trabalho interno e avisos operacionais. A complexidade principal está em estado, destinatário, visibilidade e escopo de consultório, não no formulário de criação.

## Arquitetura

Comandos operacionais usam Application Services; Runtime coordena ações da página; Infrastructure persiste. O input adapter de tarefas é um dos hotspots históricos e, portanto, está protegido por `refactor-on-touch`.

## Estados

Transições devem respeitar o conjunto de estados aceito pelo schema e pelas políticas do domínio. Corrigir uma inconsistência de estado não deve envolver DDL no runtime.

## Evolução

Quando a tela de tarefas for alterada, a mudança deve aproveitar a oportunidade para reduzir dívida mensurada do adapter em vez de acrescentar novos ramos ao mesmo bloco.
''')

add("docs/domain/maestro.md", r'''
# Domínio operacional do Maestro

Maestro é o supervisor server-side do Prontoo. Ele executa trabalho que não deve depender de uma página aberta: auditoria diferida, verificações e rotinas operacionais.

## Ciclo

Cada execução faz preflight, seleciona trabalho elegível, restaura contexto seguro, executa com budget e registra estado. Falhas temporárias usam retry/backoff; itens que excedem a política podem seguir para dead-letter.

## Tenant

Trabalho diferido não perde o consultório de origem. Contexto e prova precisam ser restaurados antes da execução. A ausência de escopo válido deve impedir a ação.

## Supervisão

Maestro distingue saúde atual de atenção histórica. Um erro antigo não deve marcar todo ciclo futuro como falho, e um ciclo aparentemente verde não deve apagar evidência de item em dead-letter.

## Operação

A CI possui regressão específica do Maestro contra MySQL real. Em produção, seu estado deve ser observado junto com filas e logs, não apenas pelo sucesso do cron.
''')

add("docs/security/threat-model.md", r'''
# Modelo de ameaças

O Prontoo protege dados pessoais, informações clínicas, rotinas internas e movimentos financeiros. O modelo assume que erros de aplicação são mais prováveis do que comprometimento físico do servidor e prioriza barreiras contra acesso indevido, cruzamento de tenant, sessão residual e mutações inconsistentes.

## Ameaças principais

- usuário autenticado acessando dados de outro consultório;
- sessão roubada ou antiga permanecendo válida após logout;
- autorização inferida por rota sem validar ação/contexto;
- SQL ou transação introduzidos em página e escapando de invariantes comuns;
- escrita financeira parcial ou duplicada;
- trabalho diferido executado sem contexto original;
- telemetria/auditoria expondo conteúdo sensível;
- instalação ou debug disponíveis fora da janela prevista.

## Controles

Tenant isolation, action catalog, geração global de sessão, MFA, constraints, Application Services transacionais, action ledger, spool supervisionado e gates arquiteturais formam defesa em profundidade. HTTPS e host canônico protegem transporte e origem pública.

## Princípio de falha

Quando o sistema não consegue provar uma condição de segurança — por exemplo, confirmar revogação global — prefere negar/indicar degradação a continuar como se nada tivesse ocorrido.
''')

add("docs/security/authentication.md", r'''
# Autenticação e sessão

Autenticação é um ciclo, não apenas a validação de senha. O Prontoo combina credencial, MFA conforme política, rotação de ID de sessão, tempo de inatividade e uma geração persistente que permite invalidar sessões emitidas anteriormente.

## Login

A senha é validada server-side e o ID de sessão é rotacionado para impedir fixation. Estados de MFA distinguem inativo, ativo e indisponível; indisponibilidade não deve ser interpretada como MFA dispensado quando a política exigir validação.

## Sessão

Contexto autenticado inclui usuário e vínculo com consultório. Guards verificam a geração persistente antes de processamento protegido. Sessões antigas são eliminadas cedo.

## Logout global

A política `logout_global_session_revocation_v1` faz até três tentativas para rotacionar a geração. Sucesso produz revogação global; outras sessões se tornam obsoletas. Se a persistência não confirmar a rotação, a sessão local é destruída e o usuário recebe HTTP 503 com degradação explícita.

## Teste

`tools/login-logout-http-smoke` cobre CSRF, rotação de sessão, duas sessões simultâneas, revogação e falha simulada da escrita canônica.
''')

add("docs/security/authorization.md", r'''
# Autorização

Autorização responde se um usuário, em um consultório e contexto específicos, pode executar uma ação. Ela não deve ser reduzida a “está logado?” nem duplicada em condicionais de página.

## Catálogo de ações

Application mantém definições e requisitos de autorização por grupos de ações. Runtime consulta a capacidade necessária antes de delegar o caso de uso. O objetivo é manter nomes e requisitos centralizados.

## Tenant e papel

Vínculo com consultório e função/cargo participam da decisão. Identificadores recebidos pelo request nunca substituem o tenant autenticado.

## Fail-closed

Se informação necessária para decidir permissão estiver ausente ou inconsistente, a ação deve ser negada. Erros de infraestrutura não podem virar autorização implícita.

## Testabilidade

Services de autorização e providers possuem contratos próprios, permitindo testar política sem renderizar páginas.
''')

add("docs/security/tenant-isolation.md", r'''
# Isolamento entre consultórios

O mesmo runtime atende dados de múltiplos consultórios, mas cada request autenticado opera em um tenant efetivo. O isolamento é tratado como invariante transversal.

## Leitura

Consultas sensíveis precisam incorporar o `clinic_id` resolvido do contexto, e não confiar apenas em IDs globais de paciente, tarefa ou agenda.

## Escrita

Commands validam que as entidades manipuladas pertencem ao mesmo tenant. Uma transação correta no tenant errado continua sendo uma falha de segurança.

## Defesa em profundidade

Guards de autorização, SQL scope, constraints e testes de runtime crítico se complementam. Nenhum componente individual é considerado suficiente.

## Trabalho diferido

Spools carregam contexto necessário e o Maestro restaura o tenant antes de executar. Evento sem contexto confiável não deve ser reaproveitado em escopo global.
''')

add("docs/security/audit-chain.md", r'''
# Auditoria e cadeia de integridade

Auditoria existe para responder quem fez o quê, em qual contexto e em que sequência, sem transformar logs em fonte de autorização.

## Componentes

O action ledger registra ações relevantes no banco. Mecanismos de audit chain e integridade verificam continuidade e consistência. Eventos que podem ser adiados entram em spool persistente e são processados pelo Maestro.

## Prova de contexto

Eventos diferidos preservam informações suficientes para reconstruir o escopo do consultório. O consumidor valida o contexto antes de escrever ou executar efeitos derivados.

## Falhas

Falha de auditoria deve ser observável. Retry é apropriado para indisponibilidade transitória; dead-letter evita loop infinito em eventos permanentemente inválidos. Arquivos inválidos não devem ser “consertados” silenciosamente.

## Privacidade

Auditoria deve registrar identidade e significado operacional necessários, evitando copiar conteúdo clínico ou segredo quando um identificador e metadado forem suficientes.
''')

add("docs/operations/installation.md", r'''
# Instalação

A instalação do Prontoo parte de banco vazio e schema canônico. Ela é uma operação administrativa excepcional, não um modo normal de inicialização do runtime.

## Requisitos

PHP deve ser da família 8.4 e MySQL deve ser 8.0.30 ou superior. O host público precisa usar HTTPS e o domínio canônico esperado. Diretórios persistentes em `ssd/` precisam ser graváveis pelo runtime conforme a política de hospedagem.

## Banco

O instalador cria o schema limpo sob um lock explícito de mutação. Não execute instalação contra banco que contenha dados de produção. O contrato `requires_empty_database` existe para tornar essa pré-condição inequívoca.

## Janela de acesso

O endpoint de instalação só deve ficar disponível dentro de janela administrativa declarada no release metadata; fora dela, a política é 404/fechamento automático. Não prolongue janela apenas para contornar um problema de configuração.

## Pós-instalação

Valide schema contract, login, criação/vínculo inicial e estado do Maestro antes de considerar o ambiente pronto.
''')

add("docs/operations/deployment.md", r'''
# Deployment

A branch operacional é `prontoo`. Deploy começa com um commit já aprovado pelos contratos, não com edição manual no servidor.

## Antes do merge

O PR deve refletir exatamente o código que será publicado. `Documentation Contract` valida release determinístico, PHP, documentação e segurança estática. `Architecture Contract` executa quality gate, schema, MySQL query budgets, installer security, login/logout, Maestro, runtime crítico e HTTP smoke.

## Release metadata

`version.json` é a fonte canônica. `app/update.manifest.json`, fallbacks e hashes são derivados por `tools/release-contract-reconcile`. Um `deployment_sync_id` identifica a intenção de sincronização com a hospedagem, mas não substitui verificação pós-deploy.

## Depois do merge

Confirme a versão servida pelo ambiente e execute verificações de saúde compatíveis com produção. Merge no GitHub prova publicação da árvore canônica; não é, por si só, evidência de que a hospedagem já sincronizou os bytes.

## Regra operacional

Nunca “corrija” produção com arquivo fora do Git. Se uma correção é necessária, faça-a em branch, teste, merge e sincronize novamente.
''')

add("docs/operations/rollback.md", r'''
# Rollback

Rollback precisa distinguir código, schema e estado persistente. Na release `1.8.11.2` não houve mudança de schema, o que torna reversão de código mais simples, mas essa condição deve ser conferida em cada release.

## Código

Escolha um commit/release conhecido e validado, publique-o pelo mesmo canal de deployment e preserve `ssd/`. Não copie apenas um subconjunto de arquivos, porque o release contract depende de consistência entre código, manifests e fallbacks.

## Banco

Se a release não alterou schema, não reverta banco apenas porque o código voltou. Se houve mudança de schema, siga o plano específico daquela mudança; rollback de DDL sem plano pode ser mais destrutivo que o incidente original.

## Validação

Após rollback, confirme versão, login, tenant isolation, uma leitura crítica, Maestro e logs. Registre o motivo para que a correção seguinte trate a causa, não apenas o sintoma.
''')

add("docs/operations/backup-restore.md", r'''
# Backup e restauração

O estado do Prontoo é composto por MySQL e armazenamento persistente `ssd/`. Um backup completo precisa considerar os dois.

## O que preservar

Banco relacional, PDFs, imagens, arquivos persistentes necessários, telemetria quando exigida operacionalmente e spools/estado do Maestro conforme a política de recuperação. Código não precisa ser incluído no backup de dados porque é reconstituível pelo repositório.

## Consistência

Idealmente, banco e filesystem devem representar um ponto temporal compatível. Em restaurações críticas, pause ou coordene writes para evitar que o dump faça referência a arquivo ainda não copiado ou vice-versa.

## Restauração

Restaure em ambiente controlado, valide versão compatível, permissões de `ssd/`, schema contract e integridade antes de abrir tráfego. Não use o instalador sobre o banco restaurado.

## Teste de backup

Backup não testado é apenas uma hipótese. Execute restaurações periódicas fora de produção e registre tempo, lacunas e procedimentos manuais encontrados.
''')

add("docs/operations/incident-response.md", r'''
# Resposta a incidentes

Incidente é qualquer evento que ameace confidencialidade, integridade, disponibilidade ou rastreabilidade do Prontoo. A prioridade é conter dano sem apagar evidência.

## 1. Classificar

Determine se o problema é autenticação, tenant isolation, financeiro, banco, storage, deploy, Maestro ou disponibilidade geral. Identifique a primeira versão/commit conhecida e o alcance aparente.

## 2. Conter

Revogue sessões quando identidade estiver envolvida, interrompa automações se elas estiverem propagando erro e limite acesso ao recurso afetado. Não faça DDL improvisado ou edição direta de produção.

## 3. Preservar evidência

Guarde logs, estado do Maestro, hashes/commit, horário e sintomas. Remova dados pessoais antes de compartilhar evidência em canais amplos.

## 4. Corrigir

Reproduza com o menor caso possível, escreva regressão automatizada, aplique mudança focal e passe os contratos. Use rollback se a correção segura não estiver pronta e a release anterior for compatível.

## 5. Aprender

Atualize runbook, contrato ou observabilidade quando o incidente revelar uma classe de falha que poderia ter sido detectada antes.
''')

add("docs/operations/maestro-runbook.md", r'''
# Runbook do Maestro

Maestro deve ser observado como supervisor de trabalho, não apenas como “cron executou”. Um processo pode terminar com código zero e ainda acumular fila ou dead-letter.

## Verificações normais

Confirme que o ciclo recente atualizou seu estado, que a fila não cresce continuamente e que itens em retry estão respeitando backoff. Verifique dead-letter separadamente.

## Quando houver erro

Identifique o evento, consultório e tentativa. Distinga erro de regra, indisponibilidade de banco/storage e envelope inválido. Não reenvie manualmente um item sem entender por que ele falhou, pois isso pode duplicar efeito.

## Auditoria diferida

Os diretórios canônicos são derivados por Infrastructure e incluem spool principal e caminho emergencial. Assinatura/contexto precisam ser válidos; arquivo inválido deve ser isolado, não aceito permissivamente.

## Recuperação

Depois de corrigir a causa, deixe a política de retry retomar itens elegíveis quando possível. Para dead-letter, faça reprocessamento explícito e documentado.

## CI

`tools/maestro-runtime-regression-check` prova o ciclo contra banco real e deve permanecer verde após mudanças operacionais.
''')

add("docs/operations/page-load-telemetry.md", r'''
# Operação da telemetria de carregamento

A série de page load mede navegações HTML concluídas. Ela não deve ser interpretada como contagem de todas as requisições HTTP.

## Fonte

O marcador inicial fica no primeiro ponto executável do front controller; o final ocorre depois do último passo útil da renderização. Fetch, XHR, JSON, prefetch, redirects e downloads são excluídos desta série.

## Armazenamento

Eventos canônicos são persistidos em `ssd/telemetry/page-loads.jsonl`. Telemetria histórica de requisições pode coexistir, mas não deve ser misturada com page load.

## Diagnóstico

Se a série cair a zero, primeiro verifique se navegações HTML continuam registrando marcador final. Se a duração subir, correlacione com query budgets, erros de banco e mudança recente; não conclua causalidade apenas pelo gráfico.
''')

add("docs/performance/performance-budgets.md", r'''
# Budgets de performance

O Prontoo prefere budgets determinísticos de trabalho a limites frágeis de milissegundos na CI. O principal exemplo é o budget MySQL, que conta operações por cenário real.

## Query budgets

A suíte atual possui 11 cenários cobrindo leituras e fluxos críticos. Cada cenário tem teto explícito de SELECT e, para read models, escrita precisa permanecer zero. Isso detecta N+1 e regressões de acesso a dados sem depender da velocidade variável do runner.

## Runtime

Hotspots de tamanho e acoplamento também funcionam como budgets de modificabilidade. Eles não medem velocidade, mas protegem custo futuro de mudança.

## Telemetria

Tempo real em produção é observado pela telemetria de page load e métricas operacionais. Budgets de CI e observabilidade de produção respondem perguntas diferentes e devem ser usados em conjunto.
''')

add("docs/performance/cache-generations.md", r'''
# Gerações e invalidação de cache

Cache no Prontoo é uma cópia descartável de dados derivados. A fonte de verdade continua sendo o estado persistente e as regras do caso de uso.

## Política

Caches JSON usam TTL curto e invalidação seletiva. Quando uma escrita altera uma visão conhecida, o domínio/caso de uso deve invalidar a geração ou chave correspondente em vez de esperar expiração longa.

## Falha

Indisponibilidade de cache não pode conceder autorização nem mascarar violação de integridade. Em leituras onde é seguro, o sistema pode recalcular; em controles de segurança, a política específica decide se há fallback.

## Benefício

Gerações evitam varrer e apagar arquivos individualmente e tornam explícito que uma família de valores ficou obsoleta após uma mutação.
''')

add("docs/performance/patient-reception-read-model.md", r'''
# Read model de recepção do paciente

Recepção precisa combinar dados do paciente e histórico operacional com poucas consultas. Tratar essa tela como sequência de pequenas buscas por item criaria N+1 e misturaria composição de apresentação com persistência.

## Desenho

Application oferece um serviço de leitura de histórico/recepção por port específico. Infrastructure monta a consulta necessária sob tenant. Runtime recebe a estrutura já pronta para a página.

## Budget

O cenário participa dos budgets MySQL. Uma otimização deve ser medida em número de operações e preservar semântica, não apenas parecer mais rápida em um teste local.

## Evolução

Se a recepção precisar de novo dado, avalie se ele pertence ao read model existente. Evite inserir consultas avulsas na Presentation ou no loop de renderização.
''')

add("docs/performance/telemetry.md", r'''
# Telemetria de performance

Telemetria serve para observar comportamento real depois que os contratos de CI já garantiram limites determinísticos. O Prontoo separa page load de requisições auxiliares para evitar métricas ambíguas.

## Page load

Uma amostra representa navegação HTML concluída, do início do front controller ao fim útil da renderização. A série permite comparar evolução de experiência sem misturar fetch e endpoints internos.

## Requisições e registros

Outras séries podem medir volume de requests e registros processados. Elas respondem capacidade/carga, não duração de página.

## Uso correto

Procure tendência e mudança de distribuição, correlacione com deploys e query budgets, e evite transformar uma média isolada em SLO sem entender a população. Telemetria deve conter metadados operacionais, não payloads sensíveis.
''')

add("docs/testing/strategy.md", r'''
# Estratégia de testes

A estratégia do Prontoo combina testes rápidos de casos de uso, contratos estáticos de arquitetura e smokes reais com PHP/MySQL. Cada camada de teste responde uma pergunta diferente.

## Suíte rápida

Application possui 49 casos críticos catalogados, cobrindo 30 services e 15 ports, com 127 assertivas explicitamente ligadas ao contrato dentro de uma suíte rápida de 194 assertivas. A meta é caracterizar 100% das entradas públicas catalogadas.

## Contratos arquiteturais

Quality gate e verificadores tokenizados medem dependências, símbolos, SOLID, Runtime boundary e ratchets. Eles impedem regressões que testes funcionais poderiam não perceber.

## Banco real

Schema contract, 11 query-budget scenarios, login, logout global, Maestro e critical runtime smoke usam MySQL real na CI. Isso valida SQL, constraints e transações que mocks não reproduzem.

## HTTP

Smokes do front controller atravessam a borda web e comprovam sessão, CSRF, redirects e boot.

A regra é testar a propriedade na camada mais determinística capaz de prová-la.
''')

add("docs/testing/regression-suite.md", r'''
# Suíte de regressão

A regressão não é um único comando; é uma malha ordenada de gates.

## Fast gate

`php tools/quality-gate --fast` cobre contratos que devem falhar rapidamente durante desenvolvimento. É a primeira barreira antes de mudanças maiores.

## Architecture Contract

Executa quality gate integral, schema, query budgets MySQL, segurança do instalador, regressão pós-senha, smoke de login/logout global, Maestro, runtime crítico e HTTP front controller.

## Documentation Contract

Valida release determinístico, lint PHP, documentação, política de comentários e regressões de segurança estáticas.

## Interpretação de falha

Não rerun automaticamente até ficar verde sem investigar. Um gate novo pode revelar defeito latente, como ocorreu com a invalidação de segunda sessão durante o hardening de logout. Corrija a causa ou o fixture; nunca enfraqueça o contrato apenas para liberar o merge.
''')

add("docs/testing/property-tests.md", r'''
# Testes de propriedades e invariantes

Nem toda regra importante é melhor descrita por um exemplo específico. Propriedades expressam relações que devem valer para uma família de entradas e estados.

## Exemplos de propriedades

Tenant nunca muda implicitamente durante uma operação; read model não escreve; geração de sessão mais antiga não volta a ser válida; identificadores `Seq` permanecem não nulos e únicos; uma operação financeira confirmada não deve produzir duplicata equivalente.

## Relação com contratos

Algumas propriedades são verificadas por testes com dados variados; outras por analisadores estáticos ou constraints do banco. O nome “property test” é menos importante que provar a invariante no nível adequado.

## Boas práticas

Gere entradas que cubram limites e estados inválidos, mantenha o teste determinístico e registre o contraexemplo mínimo quando falhar. Não substitua um constraint de banco por teste probabilístico quando o banco pode garantir a propriedade sempre.
''')

add("docs/testing/characterization-baseline.md", r'''
# Baseline de caracterização

Caracterização registra o comportamento público que precisa continuar verdadeiro durante mudanças estruturais. Ela foi essencial enquanto o Prontoo migrou responsabilidades para Application sem alterar UX ou regras do produto.

## Estado atual

O contrato de Application exige cobertura integral das entradas públicas catalogadas: 49 casos críticos, 30 services, 15 ports e pelo menos 127 assertivas ligadas. Se um método público novo aparecer sem caracterização, o gate falha.

## Uso em manutenção

Antes de mover responsabilidade de um hotspot ou substituir um adapter, preserve/expanda a caracterização do caso de uso. Depois da mudança, o teste deve continuar descrevendo resultado e efeitos, não detalhes internos da implementação.

A baseline existe para permitir refatoração segura, não para impedir evolução funcional consciente.
''')

# Ensure all remaining architecture/domain/operations docs are present with dedicated content.

EXPECTED = {
"CHANGELOG.md","CONTRIBUTING.md","README.md","SECURITY.md",
"docs/adr/0001-layered-architecture.md","docs/adr/0002-tenant-isolation.md","docs/adr/0003-action-ledger.md","docs/adr/0004-json-cache-policy.md","docs/adr/0005-maestro-supervised-server-cycle.md","docs/adr/0005-runtime-semantic-closure.md","docs/adr/0007-page-load-telemetry-source-of-truth.md","docs/adr/0008-runtime-input-boundary-correction.md",
"docs/architecture/CONSOLIDATION-AUDIT-1.8.7.1.md","docs/architecture/CONSOLIDATION-AUDIT-1.8.9.1.md","docs/architecture/MAINTENANCE.md","docs/architecture/SOLID-AUDIT.md","docs/architecture/c4-containers.md","docs/architecture/c4-context.md","docs/architecture/data-flow.md","docs/architecture/dependencies.md","docs/architecture/layers.md","docs/architecture/overview.md","docs/architecture/patient-contact-command.md","docs/architecture/patient-contact-view.md","docs/architecture/phase-1-refactoring.md","docs/architecture/phase-2-presentation-boundaries.md","docs/architecture/phase-3-read-use-cases.md","docs/architecture/phase-4-transactional-commands.md","docs/architecture/phase-5-critical-financial-command.md","docs/architecture/responsibility-map.md","docs/architecture/runtime-boundary.md",
"docs/audits/php84-conformance-1.8.6.1.md",
"docs/database/financial-integrity.md","docs/database/invariants.md","docs/database/migrations-policy.md","docs/database/schema-overview.md",
"docs/domain/appointments.md","docs/domain/documents.md","docs/domain/financial.md","docs/domain/maestro.md","docs/domain/patients.md","docs/domain/tasks.md",
"docs/glossary.md","docs/index.md",
"docs/operations/backup-restore.md","docs/operations/deployment.md","docs/operations/incident-response.md","docs/operations/installation.md","docs/operations/maestro-runbook.md","docs/operations/page-load-telemetry.md","docs/operations/rollback.md",
"docs/performance/cache-generations.md","docs/performance/patient-reception-read-model.md","docs/performance/performance-budgets.md","docs/performance/telemetry.md",
"docs/security/audit-chain.md","docs/security/authentication.md","docs/security/authorization.md","docs/security/tenant-isolation.md","docs/security/threat-model.md",
"docs/testing/characterization-baseline.md","docs/testing/property-tests.md","docs/testing/regression-suite.md","docs/testing/strategy.md"
}

if set(DOCS) != EXPECTED:
    missing = sorted(EXPECTED - set(DOCS))
    extra = sorted(set(DOCS) - EXPECTED)
    raise SystemExit(f"documentation map mismatch missing={missing} extra={extra}")

current = {
    p.relative_to(ROOT).as_posix()
    for p in ROOT.rglob("*.md")
    if not any(part in {".git", "vendor", "node_modules", "ssd"} for part in p.parts)
}
if current != EXPECTED:
    missing = sorted(current - EXPECTED)
    absent = sorted(EXPECTED - current)
    raise SystemExit(f"repository markdown inventory mismatch unhandled={missing} absent={absent}")

changed = 0
for relative, content in DOCS.items():
    path = ROOT / relative
    old = path.read_text()
    if old == content:
        raise SystemExit(f"document was not rewritten: {relative}")
    path.write_text(content)
    changed += 1

print(f"rewritten_markdown={changed}")
