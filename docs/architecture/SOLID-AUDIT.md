# Auditoria SOLID do Prontoo

## Escopo

A auditoria considera todo o código PHP versionado sob `app/`, distinguindo o núcleo arquitetural interno das fronteiras de composição e compatibilidade. O objetivo final é manter regras de negócio, casos de uso, persistência e apresentação exclusivamente em unidades coesas, namespaced e orientadas por abstrações, preservando funções globais apenas como fachadas temporárias de compatibilidade enquanto forem necessárias.

## Critério operacional

SOLID é tratado como contrato estrutural verificável:

- SRP: uma unidade interna pertence a uma camada e não mistura HTTP, persistência, domínio e apresentação;
- OCP: variações de comportamento são adicionadas por contratos, registries ou estratégias, evitando catálogos monolíticos e condicionais crescentes nos consumidores;
- LSP: consumidores dependem do contrato e não testam implementações concretas para decidir comportamento;
- ISP: portas são pequenas e orientadas ao caso de uso, sem interfaces gerais de repositório;
- DIP: Core, Domain e Application não dependem de Infrastructure; adaptadores concretos são montados apenas na composição/runtime.

## Estado auditado

O contrato `php-layered-invariants-v2` garante direção de dependências e impede PDO em arquivos nativos de Core, Domain, Application e Presentation. A migração começou com `native_files_min = 37` e `transitional_files_max = 82`.

A busca estrutural identificou como principais superfícies procedurais e de responsabilidade ampla:

- `app/Admin/AdminPages.php`;
- `app/Auth/AuthOnboarding.php`;
- `app/Domain/Appointments/Appointments.php`;
- `app/Domain/Financial/Financial.php`;
- `app/Domain/Patients/Patients.php`;
- `app/Domain/Documents/Documents.php` e `DocumentPdf.php`;
- `app/Domain/Tasks/TasksNotices.php`;
- `app/Domain/Permissions/UsersPermissions.php`;
- `app/Domain/Leads/Leads.php`;
- `app/Domain/Maestro/Maestro.php`;
- `app/Domain/Clinic/ClinicConfig.php` e `SubscriptionSettings.php`;
- `app/Pages/Dashboards.php`;
- `app/Ui/Components.php`, `PublicWeb.php` e `SpeedChartGeometry.php`;
- `app/Support/*` e `app/Database/DatabaseSchema.php`.

A arquitetura nova já demonstra os padrões corretos em `Application/*Port`, `Application/*Service` e `Infrastructure/Pdo*Repository`, além de `CapabilityProvider`/`AuthorizationService` e do composition root `LayeredKernel`.

## Achados por princípio

### SRP

A principal dívida é a coexistência de arquivos procedurais extensos que acumulam ação HTTP, SQL, normalização, regra de negócio e HTML. O alvo é extrair essas responsabilidades para Domain, Application, Infrastructure e Presentation e deixar as fachadas legadas somente como delegação.

### OCP

O catálogo de autorização deixou de ser monolítico na etapa 3. Os próximos pontos de modificação central permanecem nos módulos procedurais de domínio, apresentação e suporte.

### LSP

A arquitetura baseada em ports reduz o risco. O contrato rejeita consumidores internos que condicionem comportamento à implementação PDO concreta.

### ISP

As portas existentes são pequenas e orientadas a casos de uso. O contrato sinaliza interfaces internas excessivamente largas para impedir o retorno a repositórios genéricos.

### DIP

É o princípio atualmente mais maduro. A auditoria reforça que adaptadores concretos não podem ser instanciados fora da composição e que persistência não pode vazar para Core, Domain, Application ou Presentation.

## Etapas concluídas

### 1. Contrato executável SOLID

`tools/solid-audit` integra o `Architecture Contract`, separa achados objetivos de hotspots heurísticos e estabelece a condição verificável de conclusão.

### 2. Composição modular do runtime

O antigo `app/Support/ModuleLoader.php` deixa de acumular política de boot, catálogo, resolução e estado de carregamento e passa a ser apenas uma fachada de compatibilidade. As responsabilidades nativas ficam em:

- `app/Runtime/Modules/RuntimeBootPolicy.php`;
- `app/Runtime/Modules/RuntimeModuleCatalog.php`;
- `app/Runtime/Modules/RuntimeModuleLoader.php`;
- `app/Runtime/Modules/RuntimeModuleComposition.php`.

A baseline nativa sobe para 41 arquivos. O contrato histórico PHP 8.4 permanece como baseline da release 1.8.6.1, enquanto todos os arquivos PHP atuais e futuros continuam sujeitos a lint e auditoria de depreciações no CI.

### 3. Registry extensível de autorização

`ActionCatalog` deixa de armazenar diretamente todos os contratos do sistema. Ele passa a resolver contratos fornecidos por `ActionDefinitionSource`, enquanto a composição concreta dos providers fica em `Runtime/Authorization/ActionCatalogComposition.php`.

As definições são segregadas em providers coesos para autenticação/clínica, pacientes, agenda/leads, documentos/tarefas, equipe/Maestro, financeiro e administração global. A normalização, agregação e política de requisitos condicionais também foram extraídas para unidades próprias.

Esse estágio aplica SRP, OCP, ISP e DIP ao subsistema de autorização sem alterar o modelo fail-closed nem os contratos exatos de ação. A baseline nativa sobe para 53 arquivos.

## Sequência restante

1. concluir extrações de Patients e Financial;
2. migrar Appointments, Documents, Tasks, Leads, Users/Permissions, Clinic e Maestro;
3. migrar Auth, Admin, Pages e UI para adapters/presenters coesos;
4. migrar Support e Database para Infrastructure/Composition;
5. reduzir as fachadas procedurais a delegação sem regra, SQL ou HTML;
6. modularizar os próprios contratos de caracterização para eliminar âncoras transitórias;
7. ativar `tools/solid-audit --strict` no CI e estabelecer zero achados objetivos.

## Regra de conclusão

A migração somente é considerada concluída quando:

- não houver dependência invertida de camada;
- não houver persistência fora de Infrastructure;
- não houver estado HTTP em Core, Domain ou Application;
- não houver funções globais contendo lógica interna;
- nenhum consumidor depender de implementação concreta substituível;
- portas permanecerem coesas e específicas;
- todo comportamento de composição concreta estiver confinado a Runtime/Composition;
- o auditor SOLID executar em modo estrito com zero achados objetivos.
