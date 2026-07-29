# Mapa de responsabilidades

Este documento orienta a localização de código e estabelece os limites usados na primeira fase de enxugamento estrutural.

## Regra de localização

| Tipo de responsabilidade | Destino preferencial |
|---|---|
| entrada HTTP, `$_GET`, `$_POST`, redirecionamento e resposta | `app/Presentation`, controllers ou fronteiras legadas |
| caso de uso e coordenação | `app/Application` |
| regra de negócio, normalização e validação pura | `app/Domain` |
| SQL, PDO, arquivos e serviços externos | `app/Infrastructure` |
| HTML específico de fluxo e presenters | `app/Presentation` |
| componentes visuais genéricos | `app/Ui` |
| ligação entre camadas | composição e runtime |

## Arquivos prioritários

| Arquivo legado | Responsabilidades observadas | Direção de evolução |
|---|---|---|
| `app/Auth/AuthOnboarding.php` | login, MFA, perfil, onboarding, validações e HTML | manter fachada e extrair identidade, autenticação, MFA, perfil e apresentação |
| `app/Domain/Patients/Patients.php` | identidade, cadastro, responsáveis, prontuário, abas, SQL, ações e HTML | manter fachada e extrair regras puras, repositórios, casos de uso e views |
| `app/Admin/AdminPages.php` | painel, segurança, desempenho, clínicas, diagnósticos e apresentação | dividir por capacidade administrativa |
| `app/Domain/Financial/Financial.php` | regras monetárias, consultas, operações e apresentação | separar cálculo, persistência, casos de uso e views |
| `app/Domain/Appointments/Appointments.php` | agenda, jornada, disponibilidade, ações e HTML | separar máquina de estados, consultas, comandos e views |

## Extrações concluídas na Fase 1

### Identidade

`app/Domain/Identity/IdentityDocumentValidator.php` concentra validações puras de CPF, CNPJ e data de nascimento. As funções globais existentes continuam como fachadas compatíveis.

### Pacientes

`app/Domain/Patients/PatientPure.php` concentra formatação de CPF, limpeza e chaves de abas, vínculos de responsáveis, cálculo de idade e classificação de menoridade. As funções globais existentes continuam disponíveis.

## Extrações concluídas na Fase 2

### Abas do paciente

`app/Infrastructure/Patients/PatientTabReadRepository.php` concentra as consultas de leitura. `app/Presentation/Patients/PatientTabView.php` concentra o seletor visual. `Patients.php` mantém as funções globais como fachadas.

### Dica de onboarding

`app/Presentation/Auth/OnboardingTipView.php` concentra a renderização do componente. `AuthOnboarding.php` permanece responsável pelas condições de exibição e prepara o modelo de apresentação.

## Extrações concluídas na Fase 3

### Leituras cadastrais do paciente

`app/Application/Patients/PatientReadPort.php` define a fronteira de leitura. `app/Application/Patients/PatientReadService.php` coordena elegibilidade cadastral e normalização de responsáveis legais. `app/Infrastructure/Patients/PdoPatientReadRepository.php` concentra o SQL com isolamento explícito por consultório.

## Extrações concluídas na Fase 4

### Comando de criação de aba do paciente

`app/Application/Patients/PatientTabCommandPort.php` define a porta de escrita. `app/Application/Patients/PatientTabCommandService.php` valida e normaliza o resultado do comando. `app/Infrastructure/Patients/PdoPatientTabCommandRepository.php` concentra duplicidade, ordenação, inserção e transação com filtros explícitos por consultório e paciente.

## Extrações concluídas na Fase 5

### Recebimento financeiro do paciente

`app/Application/Financial/PatientRevenueReceiptPort.php` e `PatientRevenueReceiptService.php` tornam autorização e resultado explícitos. `app/Infrastructure/Financial/PdoPatientRevenueReceiptRepository.php` concentra bloqueios, movimento e atualizações na mesma transação. A ficha do paciente permanece como adaptador HTTP compatível.

## Inventário inicial de funções puras

| Grupo | Funções compatíveis |
|---|---|
| identidade | `valid_cpf`, `valid_cnpj`, `valid_birth_date` |
| apresentação cadastral | `patient_cpf_br`, `patient_tab_label_clean` |
| chaves canônicas | `patient_tab_record_type`, `patient_tab_key` |
| responsável legal | `patient_guardian_relationship_options`, `normalize_guardian_relationship` |
| idade | `patient_age_years`, `patient_is_minor` |

## Restrições

- não mover SQL para componentes puros;
- não introduzir acesso a sessão ou HTTP em `Domain`;
- não alterar assinaturas públicas durante a migração;
- não criar abstrações sem fronteira concreta;
- não ampliar o conjunto de módulos carregados por rota;
- manter testes de caracterização antes de novas extrações.
