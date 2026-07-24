<?php
declare(strict_types=1);
if (!function_exists("admin_choice_card")) {
    function admin_choice_card(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — admin_choice_card
         * Responsabilidade: Monta a representação de interface associada a “admin choice card” sem alterar o contrato visual externo.
         * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `e`, `icon`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return '<button class="clinic-choice credential-choice admin-choice" type="submit" name="act" value="choose_admin"><span class="credential-icon app-brandmark-inline" data-app-brandmark><img class="auth-brandmark-favicon app-brandmark-img" src="/public/assets/app-icon-' .
            e(PRONTOO_ASSET_REV) .
            '.png" alt="" aria-hidden="true"></span><span class="credential-main"><span class="credential-role">Desenvolvedor</span><span class="credential-context"><span>Painel do Desenvolvedor</span><small>Gerenciamento técnico da plataforma</small></span></span><span class="credential-enter">' .
            icon("login") .
            "</span></button>";
    }
}
function onboarding_tips_ensure_schema(): void
{
    /*
     * GUIA DE MANUTENÇÃO — onboarding_tips_ensure_schema
     * Responsabilidade: Opera a etapa “onboarding tips ensure schema” do contrato de banco e instalação, restrita às janelas autorizadas.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `onboarding_tip_dismissed`, `onboarding_tip_dismiss`.
     * Dependências chamadas: `has_cfg`, `db_table_exists`, `RuntimeException`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    static $validated = false;
    if ($validated || !has_cfg()) {
        return;
    }
    if (!db_table_exists("pi_user_onboarding_tips")) {
        throw new RuntimeException(
            "Schema incompleto: onboarding de usuário indisponível.",
        );
    }
    $validated = true;
}

function onboarding_tip_module_routes(): array
{
    /*
     * GUIA DE MANUTENÇÃO — onboarding_tip_module_routes
     * Responsabilidade: Implementa a responsabilidade “onboarding tip module routes” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `onboarding_tip_html`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    return [
        "painel",
        "operations",
        "leads",
        "patients",
        "appointments",
        "procedures",
        "documents",
        "tasks",
        "maestro",
        "audit",
        "financial",
        "notices",
        "users",
        "settings",
        "permissions",
    ];
}
function onboarding_tip_key(array $c, string $route): string
{
    /*
     * GUIA DE MANUTENÇÃO — onboarding_tip_key
     * Responsabilidade: Implementa a responsabilidade “onboarding tip key” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `onboarding_tip_dismissed`, `onboarding_tip_html`.
     * Dependências chamadas: `max`, `mb_substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $role = (string) ($c["role"] ?? "usuario");
    $clinicId = max(0, (int) ($c["clinic_id"] ?? 0));
    return mb_substr(
        $route . ":" . $role . ":clinic:" . $clinicId,
        0,
        120,
    );
}
function onboarding_tip_dismissed(array $c, string $route): bool
{
    /*
     * GUIA DE MANUTENÇÃO — onboarding_tip_dismissed
     * Responsabilidade: Implementa a responsabilidade “onboarding tip dismissed” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `onboarding_tip_html`.
     * Dependências chamadas: `onboarding_tip_key`, `onboarding_tips_ensure_schema`, `val`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    $uid = (int) ($c["user"]["id"] ?? 0);
    if ($uid <= 0) {
        return true;
    }
    $key = onboarding_tip_key($c, $route);
    try {
        onboarding_tips_ensure_schema();
        return (int) val(
            "SELECT id FROM pi_user_onboarding_tips WHERE user_id=? AND tip_key=? LIMIT 1",
            [$uid, $key],
        ) > 0;
    } catch (Throwable $e) {
        error_log("[Prontoo onboarding tip read] " . $e->getMessage());
        return false;
    }
}
function onboarding_tip_dismiss(): void
{
    /*
     * GUIA DE MANUTENÇÃO — onboarding_tip_dismiss
     * Responsabilidade: Implementa a responsabilidade “onboarding tip dismiss” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `prontoo_run`.
     * Dependências chamadas: `need_login`, `mb_substr`, `trim`, `onboarding_tips_ensure_schema`, `q`, `error_log`, `->getMessage`, `base_path`, `str_starts_with`, `header`, `redirect`, `route`.
     * Estado externo lido: `$_POST`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 3: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
     */
    $c = need_login();
    $uid = (int) ($c["user"]["id"] ?? 0);
    $key = mb_substr(trim((string) ($_POST["tip_key"] ?? "")), 0, 120);
    if ($uid > 0 && $key !== "") {
        try {
            onboarding_tips_ensure_schema();
            q(
                "INSERT INTO pi_user_onboarding_tips (user_id,tip_key,dismissed_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE dismissed_at=VALUES(dismissed_at)",
                [$uid, $key],
            );
        } catch (Throwable $e) {
            error_log("[Prontoo onboarding tip dismiss] " . $e->getMessage());
        }
    }
    $to = (string) ($_POST["return_to"] ?? "");
    $base = base_path() ?: "";
    if ($to !== "" && str_starts_with($to, $base . "/?")) {
        header("Location: " . $to);
        exit();
    }
    redirect(route());
}
function onboarding_tip_copy(array $c, string $route): ?array
{
    /*
     * GUIA DE MANUTENÇÃO — onboarding_tip_copy
     * Responsabilidade: Implementa a responsabilidade “onboarding tip copy” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `onboarding_tip_html`.
     * Dependências chamadas: `role_label_for`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    $role = (string) ($c["role"] ?? "");
    $roleName = role_label_for($role, (int) ($c["clinic_id"] ?? 0));
    $tips = [
        "painel" => [
            "icon" => "space_dashboard",
            "title" => "Seu resumo do dia:",
            "body" =>
                "O consultório já está pronto para uso. Para concluir os ajustes iniciais, comece em Meu Consultório: revise aparência e departamentos; depois cadastre a equipe e confira as permissões de cada cargo.",
        ],
        "operations" => [
            "icon" => "account_tree",
            "title" => "Um passeio pelo consultório!",
            "body" =>
                "Acompanhe o trabalho de outros departamentos, de forma centralizada.",
        ],
        "leads" => [
            "icon" => "person_search",
            "title" => "Como usar o recurso Interessados?",
            "body" =>
                "Interessados são todas as pessoas que entraram em contato e demonstraram interesse, mas ainda não são Pacientes. Eles são identificados pelo número de telefone e permitem que, caso outro membro da equipe já o tenha atendido, você tenha o histórico do que foi conversado.",
        ],
        "patients" => [
            "icon" => "patient_list",
            "title" => "Seus ativos mais Preciosos!",
            "body" =>
                "Pacientes são a base da Agenda e dos Documentos. Um Paciente estar cadastrado é o primeiro passo para permitir que o nome dele seja usado em outras áreas da plataforma, como Agendamentos e Documentos.",
        ],
        "procedures" => [
            "icon" => "medical_services",
            "title" => "O que você faz e quanto custa?",
            "body" =>
                "Procedimentos são serviços que você oferece, que podem ser agendados e quanto custam. Apenas Procedimentos cadastrados podem ser Agendados e o tempo médio deles é considerado no momento de reservar o horário na Agenda.",
        ],
        "appointments" => [
            "icon" => "calendar_month",
            "title" => "Seu dia, sem atropelos.",
            "body" =>
                "Antes de criar um Agendamento, cadastre o Paciente e confira se o Procedimento e o Profissional estão disponíveis. Isso evita horários soltos ou incompletos.",
        ],
        "documents" => [
            "icon" => "description",
            "title" => "Documentos gerados com eficiência.",
            "body" =>
                "Comece criando Modelos de Documentos. Depois, é só selecionar Paciente e clicar em Emitir Documento!",
        ],
        "tasks" => [
            "icon" => "task_alt",
            "title" => "Tarefas distribuem a rotina",
            "body" =>
                "As Tarefas transformam pendências em responsabilidade clara. Quem vai fazer? Até que dia? Está atrasado? Aqui você controla tudo isso.",
        ],
        "maestro" => [
            "icon" => "event_repeat",
            "title" => "Rotinas automáticas para reduzir repetição.",
            "body" =>
                "Precisa que a Recepção ligue para o Paciente alguns dias antes e confirme a Consulta? As Rotinas geram a Tarefa para o Colaborador responsável e ajudam a equipe a manter o fluxo em dia.",
        ],
        "audit" => [
            "icon" => "history",
            "title" => "Quem fez o quê?",
            "body" =>
                "As Atividades são o diário da sua equipe. Saiba que tarefas foram feitas para evitar o retrabalho.",
        ],
        "financial" => [
            "icon" => "payments",
            "title" => "Rotina financeira de forma clara!",
            "body" =>
                "Quando o Paciente paga na Recepção, o dinheiro vai para uma Gaveta. As Gavetas são conferidas no início e final do expediente. No fechamento, parte continua na Gaveta para troco e o restante é enviado para Cofres. Entenda Cofre como o local onde o dinheiro fica antes de ser depositado. Após ser depositado, ele sai do Cofre e chega a um Banco. Assim, é possível saber quanto dinheiro têm e onde está!",
        ],
        "notices" => [
            "icon" => "campaign",
            "title" => "Avisos para alinhar a equipe",
            "body" =>
                "Use Avisos para recados internos onde todos podem confirmar que estão cientes. Para pedidos com prazo ou responsável, prefira criar uma Tarefa.",
        ],
        "users" => [
            "icon" => "groups",
            "title" => "Seu time espera clareza!",
            "body" =>
                "Cadastre aqui os membros da equipe que antes poderiam ser incluídos no assistente inicial. Defina os cargos de cada pessoa e, para profissionais que participam da Agenda, informe também os horários disponíveis.",
        ],
        "permissions" => [
            "icon" => "admin_panel_settings",
            "title" => "Quem pode ver ou alterar o quê?",
            "body" =>
                "Depois de revisar departamentos e cadastrar a equipe, confira o que cada cargo pode ver, criar ou alterar. Mantenha somente as permissões necessárias para o trabalho de cada ambiente.",
        ],
        "settings" => [
            "icon" => "home_health",
            "title" => "Seu consultório, do seu jeito!",
            "body" =>
                "A identificação e o fuso já foram definidos na criação. Em Identificação, escolha o ícone e a cor do consultório; em Departamentos, revise nomes e ative somente os ambientes usados pela equipe.",
        ],
    ];
    $tip = $tips[$route] ?? null;
    if (!$tip) {
        return null;
    }
    $tip["role"] = $roleName;
    return $tip;
}
function onboarding_tip_html(array $c, string $route): string
{
    /*
     * GUIA DE MANUTENÇÃO — onboarding_tip_html
     * Responsabilidade: Monta a representação de interface associada a “onboarding tip html” sem alterar o contrato visual externo.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page`.
     * Dependências chamadas: `in_array`, `onboarding_tip_module_routes`, `onboarding_tip_dismissed`, `onboarding_tip_copy`, `onboarding_tip_key`, `href`, `icon`, `e`, `csrf_field`.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!$c || ($c["scope"] ?? "") !== "clinic") {
        return "";
    }
    if (!in_array($route, onboarding_tip_module_routes(), true)) {
        return "";
    }
    if ($route === "documents" && (int) ($_GET["doc"] ?? 0) > 0) {
        return "";
    }
    if (onboarding_tip_dismissed($c, $route)) {
        return "";
    }
    $tip = onboarding_tip_copy($c, $route);
    if (!$tip) {
        return "";
    }
    $key = onboarding_tip_key($c, $route);
    $params = $_GET;
    unset($params["r"]);
    $return = href($route, $params);
    return '<section class="onboarding-tip-card" role="note"><div class="onboarding-tip-main"><div class="onboarding-tip-head"><span class="onboarding-tip-icon">' .
        icon((string) $tip["icon"]) .
        "</span><strong>" .
        e($tip["title"]) .
        '</strong></div><p class="onboarding-tip-body">' .
        e($tip["body"]) .
        '</p></div><form method="post" action="' .
        e($return) .
        '" class="onboarding-tip-action">' .
        csrf_field() .
        '<input type="hidden" name="act" value="onboarding_tip_dismiss"><input type="hidden" name="tip_key" value="' .
        e($key) .
        '"><input type="hidden" name="return_to" value="' .
        e($return) .
        '"><button type="submit" class="ghost small onboarding-tip-button">Entendi</button></form></section>';
}
function valid_cpf(string $cpf): bool
{
    /*
     * GUIA DE MANUTENÇÃO — valid_cpf
     * Responsabilidade: Implementa a responsabilidade “valid cpf” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_login`, `page_signup`, `upsert_person`, `save_person_flexible`, `page_person_lookup`, `page_settings`, `page_counterparty_lookup`, `financial_creditor_upsert_from_post` e mais 14.
     * Dependências chamadas: `only_digits`, `strlen`, `preg_match`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $cpf = only_digits($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += (int) $cpf[$i] * ($t + 1 - $i);
        }
        $d = ((10 * $sum) % 11) % 10;
        if ((int) $cpf[$t] !== $d) {
            return false;
        }
    }
    return true;
}
function valid_cnpj(string $cnpj): bool
{
    /*
     * GUIA DE MANUTENÇÃO — valid_cnpj
     * Responsabilidade: Implementa a responsabilidade “valid cnpj” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_signup`, `save_person_by_document`, `page_settings`, `page_counterparty_lookup`, `financial_creditor_upsert_from_post`, `posted_identity_document_error`.
     * Dependências chamadas: `only_digits`, `strlen`, `preg_match`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $cnpj = only_digits($cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }
    $calc = function (int $len) use ($cnpj): int {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Auth/AuthOnboarding.php:264
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de autenticação, sessão e entrada de usuários.
         * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $weights =
            $len === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < $len; $i++) {
            $sum += (int) $cnpj[$i] * $weights[$i];
        }
        $r = $sum % 11;
        return $r < 2 ? 0 : 11 - $r;
    };
    return (int) $cnpj[12] === $calc(12) && (int) $cnpj[13] === $calc(13);
}
function db_birth_date_input(null|string|int $birth): string
{
    /*
     * GUIA DE MANUTENÇÃO — db_birth_date_input
     * Responsabilidade: Opera a etapa “db birth date input” do contrato de banco e instalação, restrita às janelas autorizadas.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_person_lookup`, `page_profile`, `person_autosuggest_datalist`.
     * Dependências chamadas: `function_exists`, `app_date_input_from_storage`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return function_exists("app_date_input_from_storage")
        ? app_date_input_from_storage($birth)
        : (string) $birth;
}
function valid_birth_date(null|string|int $birth): bool
{
    /*
     * GUIA DE MANUTENÇÃO — valid_birth_date
     * Responsabilidade: Implementa a responsabilidade “valid birth date” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_signup`, `upsert_person`, `save_person_flexible`, `lead_prepare_person_for_patient`, `page_leads`, `patient_identity_complete`, `patient_invoice_registration_missing_fields`, `page_patients` e mais 3.
     * Dependências chamadas: `trim`, `preg_match`, `gmdate`, `checkdate`, `DateTimeImmutable`, `DateTimeZone`, `->modify`, `->setTime`.
     * Classes ou serviços instanciados: `DateTimeImmutable`, `DateTimeZone`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $raw = trim((string) $birth);
    if ($raw === "") {
        return false;
    }
    $ymd = preg_match('/^-?\d+$/', $raw)
        ? gmdate("Y-m-d", (int) $raw)
        : $raw;
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return false;
    }
    $year = (int) $m[1];
    $month = (int) $m[2];
    $day = (int) $m[3];
    if (!checkdate($month, $day, $year)) {
        return false;
    }
    $date = new DateTimeImmutable($ymd . " 12:00:00", new DateTimeZone("UTC"));
    $today = new DateTimeImmutable(gmdate("Y-m-d") . " 23:59:59", new DateTimeZone("UTC"));
    $oldest = $today->modify("-120 years")->setTime(0, 0, 0);
    return $date <= $today && $date >= $oldest;
}
function login_last_credential_key(int $uid): string
{
    /*
     * GUIA DE MANUTENÇÃO — login_last_credential_key
     * Responsabilidade: Implementa a responsabilidade “login last credential key” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `login_last_credential_remember`, `login_last_credential_from_meta`.
     * Dependências chamadas: `max`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return "last_login_credential_user_" . max(0, $uid);
}
function login_last_credential_normalize(mixed $raw): ?array
{
    /*
     * GUIA DE MANUTENÇÃO — login_last_credential_normalize
     * Responsabilidade: Transforma e normaliza “login last credential normalize” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `login_last_credential_from_meta`, `login_last_credential_from_devices`.
     * Dependências chamadas: `is_string`, `trim`, `json_decode`, `is_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (is_string($raw)) {
        $raw = trim($raw);
        if ($raw === "") {
            return null;
        }
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : null;
    }
    if (!is_array($raw)) {
        return null;
    }
    $scope = (string) ($raw["scope"] ?? "");
    if ($scope === "global") {
        return ["scope" => "global", "clinic_role_id" => null];
    }
    if ($scope === "clinic") {
        $roleId = (int) ($raw["clinic_role_id"] ?? ($raw["uc_id"] ?? 0));
        if ($roleId > 0) {
            return ["scope" => "clinic", "clinic_role_id" => $roleId];
        }
    }
    return null;
}
function login_last_credential_remember(
    int $uid,
    string $scope,
    ?int $clinicRoleId = null,
): void {
    /*
     * GUIA DE MANUTENÇÃO — login_last_credential_remember
     * Responsabilidade: Implementa a responsabilidade “login last credential remember” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `login_apply_resolved_credential`, `page_signup`, `device_session_remember_after_login`, `device_session_update_current_context`, `device_session_auto_login`.
     * Dependências chamadas: `has_cfg`, `date`, `meta_set`, `login_last_credential_key`, `json_encode`, `error_log`, `->getMessage`.
     * Efeitos colaterais: produz conteúdo de saída; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($uid <= 0 || !has_cfg()) {
        return;
    }
    $scope = $scope === "global" ? "global" : "clinic";
    if ($scope === "clinic" && ($clinicRoleId === null || $clinicRoleId <= 0)) {
        return;
    }
    try {
        $payload = [
            "scope" => $scope,
            "clinic_role_id" =>
                $scope === "clinic" ? (int) $clinicRoleId : null,
            "remembered_at" => date("c"),
        ];
        meta_set(
            login_last_credential_key($uid),
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        );
    } catch (Throwable $e) {
        error_log(
            "[Prontoo login last credential remember] " . $e->getMessage(),
        );
    }
}
function login_last_credential_from_meta(int $uid): ?array
{
    /*
     * GUIA DE MANUTENÇÃO — login_last_credential_from_meta
     * Responsabilidade: Implementa a responsabilidade “login last credential from meta” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `login_resolve_user_credential`.
     * Dependências chamadas: `has_cfg`, `login_last_credential_normalize`, `meta_get`, `login_last_credential_key`, `error_log`, `->getMessage`.
     * Efeitos colaterais: gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($uid <= 0 || !has_cfg()) {
        return null;
    }
    try {
        return login_last_credential_normalize(
            meta_get(login_last_credential_key($uid), ""),
        );
    } catch (Throwable $e) {
        error_log("[Prontoo login last credential meta] " . $e->getMessage());
        return null;
    }
}
function login_last_credential_from_devices(int $uid): ?array
{
    /*
     * GUIA DE MANUTENÇÃO — login_last_credential_from_devices
     * Responsabilidade: Implementa a responsabilidade “login last credential from devices” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `login_resolve_user_credential`.
     * Dependências chamadas: `has_cfg`, `function_exists`, `db_table_exists`, `one`, `login_last_credential_normalize`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return null;
}
function login_credential_match(
    int $uid,
    bool $isAdmin,
    array $choices,
    ?array $credential,
): ?array {
    /*
     * GUIA DE MANUTENÇÃO — login_credential_match
     * Responsabilidade: Implementa a responsabilidade “login credential match” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `login_resolve_user_credential`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!$credential) {
        return null;
    }
    if (($credential["scope"] ?? "") === "global") {
        return $isAdmin
            ? ["scope" => "global", "clinic_role_id" => null, "choice" => null]
            : null;
    }
    $roleId = (int) ($credential["clinic_role_id"] ?? 0);
    if ($roleId <= 0) {
        return null;
    }
    foreach ($choices as $choice) {
        if (
            (int) ($choice["id"] ?? 0) === $roleId &&
            (int) ($choice["user_id"] ?? $uid) === $uid
        ) {
            return [
                "scope" => "clinic",
                "clinic_role_id" => $roleId,
                "choice" => $choice,
            ];
        }
    }
    return null;
}
function login_resolve_user_credential(
    int $uid,
    bool $isAdmin,
    array $choices,
): ?array {
    /*
     * GUIA DE MANUTENÇÃO — login_resolve_user_credential
     * Responsabilidade: Localiza, carrega ou resolve os dados de “login resolve user credential” para consumo pelas camadas superiores.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_login`.
     * Dependências chamadas: `login_credential_match`, `login_last_credential_from_meta`, `login_last_credential_from_devices`, `count`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $fromMeta = login_credential_match(
        $uid,
        $isAdmin,
        $choices,
        login_last_credential_from_meta($uid),
    );
    if ($fromMeta) {
        return $fromMeta;
    }
    if (count($choices) > 0) {
        return [
            "scope" => "clinic",
            "clinic_role_id" => (int) $choices[0]["id"],
            "choice" => $choices[0],
        ];
    }
    if ($isAdmin) {
        return [
            "scope" => "global",
            "clinic_role_id" => null,
            "choice" => null,
        ];
    }
    return null;
}
function login_apply_resolved_credential(
    int $uid,
    array $credential,
): void {
    /*
     * GUIA DE MANUTENÇÃO — login_apply_resolved_credential
     * Responsabilidade: Implementa a responsabilidade “login apply resolved credential” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_login`.
     * Dependências chamadas: `session_harden_after_login`, `developer_first_login_clear_json_cache`, `mark_login_success`, `login_last_credential_remember`, `device_session_remember_after_login`, `audit`, `redirect`, `RuntimeException`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão; controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 2: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    $scope = (string) ($credential["scope"] ?? "clinic");
    session_harden_after_login($uid);
    $_SESSION["uid"] = $uid;
    unset($_SESSION["pending_login_uid"], $_SESSION["pending_device_login"]);
    mfa_pending_login_clear();
    security_clear_legacy_device_cookie();
    if ($scope === "global") {
        $_SESSION["scope"] = "global";
        unset(
            $_SESSION["uc_id"],
            $_SESSION["clinic_id"],
            $_SESSION["role_code"],
            $_SESSION["effective_roles"],
        );
        developer_first_login_clear_json_cache($uid);
        mark_login_success($uid);
        login_last_credential_remember($uid, "global", null);
        security_retire_persistent_devices_for_user($uid);
        audit("entrada_realizada", "usuario", $uid, [
            "scope" => "global",
            "audit_body" =>
                "Entrada realizada com a última credencial do usuário carregada automaticamente.",
        ]);
        redirect("admin_painel");
    }
    $choice = $credential["choice"] ?? null;
    if (!$choice || (int) ($choice["id"] ?? 0) <= 0) {
        throw new RuntimeException(
            "Não foi possível carregar a credencial de trabalho.",
        );
    }
    $_SESSION["scope"] = "clinic";
    $_SESSION["uc_id"] = (int) $choice["id"];
    $_SESSION["clinic_id"] = (int) $choice["clinic_id"];
    $_SESSION["role_code"] = (string) $choice["role_code"];
    $_SESSION["effective_roles"] = [(string) $choice["role_code"]];
    mark_login_success($uid);
    login_last_credential_remember($uid, "clinic", (int) $choice["id"]);
    security_retire_persistent_devices_for_user($uid);
    audit("entrada_realizada", "usuario", $uid, [
        "clinic_id" => (int) $choice["clinic_id"],
        "role_code" => (string) $choice["role_code"],
        "audit_body" =>
            "Entrada realizada com a última credencial de trabalho do usuário carregada automaticamente.",
    ]);
    redirect(
        (string) ($choice["role_code"] ?? "") === "gerente"
            ? "painel"
            : "appointments",
    );
}
function developer_first_login_clear_json_cache(int $uid): bool
{
    /*
     * GUIA DE MANUTENÇÃO — developer_first_login_clear_json_cache
     * Responsabilidade: Gerencia o cache ou a memoização de “developer first login clear json cache”, reduzindo I/O sem substituir a fonte canônica.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `login_apply_resolved_credential`.
     * Dependências chamadas: `function_exists`, `val`, `server_json_cache_clear_all_json_files`, `q`, `time`, `audit`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    if (
        $uid <= 0 ||
        !function_exists("server_json_cache_clear_all_json_files")
    ) {
        return false;
    }
    $marker = "developer_first_login_json_cache_cleared_v1";
    $lockName = "prontoo_developer_first_login_json_cache";
    $locked = false;
    try {
        $isDeveloper =
            (int) val(
                "SELECT is_global_admin FROM pi_users WHERE id=? AND active=1 LIMIT 1",
                [$uid],
            ) === 1;
        if (!$isDeveloper) {
            return false;
        }
        $ready =
            (string) (val(
                "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
                [$marker],
            ) ?? "") === "1";
        if ($ready) {
            return true;
        }
        $locked =
            (int) val("SELECT GET_LOCK(?,5)", [$lockName]) === 1;
        if (!$locked) {
            return false;
        }
        $ready =
            (string) (val(
                "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
                [$marker],
            ) ?? "") === "1";
        if ($ready) {
            return true;
        }
        $deleted = server_json_cache_clear_all_json_files();
        q(
            "INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES (?, '1', ?) ON DUPLICATE KEY UPDATE meta_value='1',updated_at=VALUES(updated_at)",
            [$marker, time()],
        );
        audit("cache_instalacao_limpo", "plataforma", null, [
            "arquivos_json_removidos" => $deleted,
            "primeiro_login_desenvolvedor" => true,
            "audit_body" =>
                "O cache JSON da instalação foi limpo uma única vez no primeiro acesso do Desenvolvedor.",
        ]);
        return true;
    } catch (Throwable $e) {
        error_log(
            "[Prontoo first developer login cache] " . $e->getMessage(),
        );
        return false;
    } finally {
        if ($locked) {
            try {
                val("SELECT RELEASE_LOCK(?)", [$lockName]);
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo first developer login unlock] " .
                        $e->getMessage(),
                );
            }
        }
    }
}
/* Guia de manutenção: Limpa integralmente o estado temporário de MFA para impedir reaproveitamento entre tentativas. */
function mfa_pending_login_clear(): void
{
    unset(
        $_SESSION["pending_mfa_login"],
        $_SESSION["mfa_enrollment_secret"],
        $_SESSION["mfa_recovery_codes"],
        $_SESSION["mfa_pending_verified"],
    );
}
/* Guia de manutenção: Cria sessão pré-autenticada curta para o desafio MFA do Desenvolvedor. */
function mfa_begin_pending_login(int $uid, array $credential): void
{
    session_regenerate_id(true);
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
    $_SESSION["pending_mfa_login"] = [
        "uid" => $uid,
        "scope" => (string) ($credential["scope"] ?? "clinic"),
        "clinic_role_id" => (int) ($credential["clinic_role_id"] ?? 0),
        "issued_at" => time(),
        "user_auth_generation" => user_auth_generation_ensure($uid),
    ];
    unset(
        $_SESSION["mfa_enrollment_secret"],
        $_SESSION["mfa_recovery_codes"],
        $_SESSION["mfa_pending_verified"],
    );
}
/* Guia de manutenção: Revalida usuário, privilégio, prazo e geração antes de aceitar o estado pré-autenticado. */
function mfa_pending_login_user(): ?array
{
    $pending = $_SESSION["pending_mfa_login"] ?? null;
    if (
        !is_array($pending) ||
        (int) ($pending["uid"] ?? 0) <= 0 ||
        time() - (int) ($pending["issued_at"] ?? 0) > 300
    ) {
        mfa_pending_login_clear();
        return null;
    }
    $uid = (int) $pending["uid"];
    $user = one(
        "SELECT id,name,email,password_hash,is_global_admin,active FROM pi_users WHERE id=? AND active=1 LIMIT 1",
        [$uid],
    );
    if (
        !$user ||
        (int) ($user["is_global_admin"] ?? 0) !== 1 ||
        !hash_equals(
            user_auth_generation_current($uid),
            (string) ($pending["user_auth_generation"] ?? ""),
        )
    ) {
        mfa_pending_login_clear();
        return null;
    }
    return $user;
}
/* Guia de manutenção: Converte desafio MFA aprovado em sessão autenticada usando credencial revalidada. */
function mfa_complete_pending_login(): void
{
    $user = mfa_pending_login_user();
    $pending = $_SESSION["pending_mfa_login"] ?? null;
    if (
        !$user ||
        !is_array($pending) ||
        empty($_SESSION["mfa_pending_verified"])
    ) {
        mfa_pending_login_clear();
        redirect("login", ["relogin" => "1"]);
    }
    $uid = (int) $user["id"];
    $choices = active_clinic_roles_for_user($uid);
    $wanted = [
        "scope" => (string) ($pending["scope"] ?? "clinic"),
        "clinic_role_id" => (int) ($pending["clinic_role_id"] ?? 0),
    ];
    $credential = login_credential_match(
        $uid,
        true,
        $choices,
        $wanted,
    );
    if (!$credential) {
        $credential = login_resolve_user_credential($uid, true, $choices);
    }
    if (!$credential) {
        mfa_pending_login_clear();
        throw new RuntimeException(
            "Não foi possível carregar a credencial do Desenvolvedor.",
        );
    }
    mfa_pending_login_clear();
    $_SESSION["mfa_verified_at"] = time();
    $_SESSION["privileged_auth_at"] = time();
    prontoo_login_post_password_maintenance($uid);
    login_apply_resolved_credential($uid, $credential);
}
/* Guia de manutenção: Limita tentativas MFA simultaneamente por usuário e endereço de origem. */
function mfa_attempt_limited(int $uid, string $purpose): bool
{
    return security_rate_limit(
        security_value_bucket("mfa_" . $purpose, (string) $uid),
        10,
        300,
    ) ||
        security_rate_limit(
            security_ip_bucket("mfa_" . $purpose),
            30,
            300,
        );
}
/* Guia de manutenção: Apresenta cadastro obrigatório inicial e validação MFA recorrente do Desenvolvedor. */
function page_mfa(): void
{
    $user = mfa_pending_login_user();
    if (!$user) {
        redirect("login", ["relogin" => "1"]);
    }
    $uid = (int) $user["id"];
    $enrolled = mfa_is_enrolled($uid);
    if (!$enrolled && empty($_SESSION["mfa_enrollment_secret"])) {
        $_SESSION["mfa_enrollment_secret"] = mfa_totp_secret_generate();
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "");
        if (mfa_attempt_limited($uid, "login")) {
            flash(
                "Muitas tentativas de autenticação. Aguarde alguns minutos.",
                "bad",
            );
            redirect("mfa");
        }
        try {
            if ($act === "mfa_enroll" && !$enrolled) {
                $secret = (string) ($_SESSION["mfa_enrollment_secret"] ?? "");
                if ($secret === "") {
                    throw new RuntimeException(
                        "A configuração MFA expirou. Inicie novamente.",
                    );
                }
                $codes = mfa_enroll_user(
                    $uid,
                    $secret,
                    (string) ($_POST["code"] ?? ""),
                );
                $_SESSION["mfa_recovery_codes"] = $codes;
                $_SESSION["mfa_pending_verified"] = true;
                unset($_SESSION["mfa_enrollment_secret"]);
                audit("mfa_cadastrado", "usuario", $uid, [
                    "audit_body" =>
                        "MFA obrigatório do Desenvolvedor cadastrado e confirmado por TOTP.",
                ]);
                redirect("mfa");
            }
            if (
                $act === "mfa_continue" &&
                !empty($_SESSION["mfa_pending_verified"]) &&
                !empty($_SESSION["mfa_recovery_codes"])
            ) {
                unset($_SESSION["mfa_recovery_codes"]);
                mfa_complete_pending_login();
            }
            if ($act === "mfa_verify" && $enrolled) {
                if (
                    !mfa_verify_user_code(
                        $uid,
                        (string) ($_POST["code"] ?? ""),
                    )
                ) {
                    throw new RuntimeException(
                        "O código de autenticação não confere.",
                    );
                }
                $_SESSION["mfa_pending_verified"] = true;
                audit("mfa_validado", "usuario", $uid, [
                    "audit_body" =>
                        "Segundo fator do Desenvolvedor validado antes da criação da sessão autenticada.",
                ]);
                mfa_complete_pending_login();
            }
            throw new RuntimeException("Ação MFA inválida.");
        } catch (Throwable $e) {
            usleep(random_int(250000, 450000));
            audit("falha_mfa", "login", $uid, [
                "motivo_hash" => hash("sha256", $e->getMessage()),
            ]);
            flash(
                app_public_error_message(
                    $e,
                    "Não foi possível validar o segundo fator.",
                ),
                "bad",
            );
            redirect("mfa");
        }
    }
    $recovery = (array) ($_SESSION["mfa_recovery_codes"] ?? []);
    if ($recovery) {
        $items = "";
        foreach ($recovery as $code) {
            $items .=
                '<li><code tabindex="0">' .
                e((string) $code) .
                "</code></li>";
        }
        $body =
            '<section class="auth login-card security-auth-card security-recovery-card"><div class="auth-titleline security-auth-titleline"><span class="auth-brandmark security-auth-icon">' .
            icon("verified_user") .
            '</span><div><span class="eyebrow">MFA configurado</span><h1>Códigos de recuperação</h1><p>Conclua esta etapa antes de entrar.</p></div></div><div class="security-auth-notice" role="status"><span class="security-auth-notice-icon">' .
            icon("key") .
            '</span><div><strong>Guarde agora</strong><span>Cada código funciona uma única vez e não será exibido novamente.</span></div></div><ul class="recovery-code-list" aria-label="Códigos de recuperação">' .
            $items .
            '</ul><form method="post" class="security-auth-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="mfa_continue"><button type="submit" class="primary wide security-auth-submit">' .
            icon("arrow_forward") .
            "<span>Concluir e entrar</span></button></form></section>";
        page("Códigos de recuperação", $body, ["public" => true]);
        return;
    }
    if (!$enrolled) {
        $secret = (string) $_SESSION["mfa_enrollment_secret"];
        $account =
            trim((string) ($user["email"] ?? "")) ?:
            ((string) ($user["name"] ?? "Desenvolvedor") . " #" . $uid);
        $uri = mfa_otpauth_uri($account, $secret);
        $body =
            '<section class="auth login-card security-auth-card security-enrollment-card"><div class="auth-titleline security-auth-titleline"><span class="auth-brandmark security-auth-icon">' .
            icon("shield_lock") .
            '</span><div><span class="eyebrow">Primeiro acesso · Desenvolvedor</span><h1>Proteja sua conta</h1><p>O MFA será exigido nos próximos acessos.</p></div></div><ol class="security-step-list" aria-label="Etapas do cadastro"><li class="is-current"><span>1</span><div><strong>Adicione a conta</strong><small>Abra seu aplicativo autenticador pelo botão ou use a chave manual.</small></div></li><li><span>2</span><div><strong>Confirme o código</strong><small>Digite os seis dígitos exibidos pelo aplicativo.</small></div></li></ol><a class="ghost wide security-auth-launch" href="' .
            e($uri) .
            '">' .
            icon("open_in_new") .
            '<span>Abrir no autenticador</span></a><div class="mfa-secret"><div><span>Chave manual</span><small>Use se o aplicativo não abrir pelo botão.</small></div><code tabindex="0" aria-label="Chave manual do autenticador">' .
            e($secret) .
            '</code></div><form method="post" class="compact security-auth-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="mfa_enroll">' .
            form_row(
                "Código de seis dígitos",
                input(
                    "code",
                    "text",
                    "",
                    'required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" aria-describedby="mfa-code-help"',
                ),
            ) .
            '<small id="mfa-code-help" class="field-help">Digite o código atual do aplicativo autenticador.</small><button type="submit" class="primary wide security-auth-submit">' .
            icon("check_circle") .
            "<span>Confirmar e cadastrar</span></button></form></section>";
        page("Cadastrar MFA", $body, ["public" => true]);
        return;
    }
    $body =
        '<section class="auth login-card security-auth-card security-verification-card"><div class="auth-titleline security-auth-titleline"><span class="auth-brandmark security-auth-icon">' .
        icon("shield_lock") .
        '</span><div><span class="eyebrow">Desenvolvedor</span><h1>Confirme sua identidade</h1><p>Esta verificação protege o acesso global.</p></div></div><div class="security-auth-notice security-auth-notice-soft"><span class="security-auth-notice-icon">' .
        icon("phonelink_lock") .
        '</span><div><strong>Segundo fator</strong><span>Use o código atual do autenticador ou um código de recuperação.</span></div></div><form method="post" class="compact security-auth-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="mfa_verify">' .
        form_row(
            "Código de autenticação",
            input(
                "code",
                "text",
                "",
                'required autocomplete="one-time-code" maxlength="16" placeholder="Código do autenticador" autocapitalize="characters" spellcheck="false"',
            ),
        ) .
        '<button type="submit" class="primary wide security-auth-submit">' .
        icon("login") .
        "<span>Validar e entrar</span></button></form></section>";
    page("Confirmar MFA", $body, ["public" => true]);
}
/* Guia de manutenção: Exige nova senha e MFA para elevar sessão clínica ao Painel do Desenvolvedor. */
function page_global_reauth(): void
{
    $c = need_login();
    if (($c["scope"] ?? "") === "global") {
        redirect("admin_painel");
    }
    $uid = (int) ($c["user"]["id"] ?? 0);
    $user = one(
        "SELECT id,name,password_hash,is_global_admin,active FROM pi_users WHERE id=? AND active=1 LIMIT 1",
        [$uid],
    );
    if (
        !$user ||
        (int) ($user["is_global_admin"] ?? 0) !== 1 ||
        !mfa_is_enrolled($uid)
    ) {
        throw new ProntooHttpError(
            403,
            "Ambiente global indisponível para este usuário.",
        );
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        try {
            if (mfa_attempt_limited($uid, "elevation")) {
                throw new RuntimeException(
                    "Muitas tentativas. Aguarde alguns minutos.",
                );
            }
            $password = (string) ($_POST["password"] ?? "");
            $code = (string) ($_POST["code"] ?? "");
            if (
                !password_verify($password, (string) $user["password_hash"]) ||
                !mfa_verify_user_code($uid, $code)
            ) {
                usleep(random_int(250000, 450000));
                throw new RuntimeException(
                    "A senha ou o código de autenticação não confere.",
                );
            }
            session_regenerate_id(true);
            $_SESSION["csrf"] = bin2hex(random_bytes(32));
            $_SESSION["scope"] = "global";
            $_SESSION["mfa_verified_at"] = time();
            $_SESSION["privileged_auth_at"] = time();
            unset(
                $_SESSION["uc_id"],
                $_SESSION["clinic_id"],
                $_SESSION["role_code"],
                $_SESSION["effective_roles"],
            );
            login_last_credential_remember($uid, "global", null);
            audit("elevacao_global_reautenticada", "usuario", $uid, [
                "scope" => "global",
                "audit_body" =>
                    "Entrada no Painel do Desenvolvedor autorizada após nova confirmação de senha e MFA.",
            ]);
            redirect("admin_painel");
        } catch (Throwable $e) {
            audit("falha_elevacao_global", "seguranca", $uid, [
                "motivo_hash" => hash("sha256", $e->getMessage()),
            ]);
            flash(
                app_public_error_message(
                    $e,
                    "Não foi possível confirmar a elevação de acesso.",
                ),
                "bad",
            );
            redirect("global_reauth");
        }
    }
    $body =
        page_head(
            "Confirmar acesso de Desenvolvedor",
            "Confirme sua identidade antes de entrar no ambiente global.",
        ) .
        '<section class="card account-card security-reauth-card"><header class="security-reauth-head"><span class="security-reauth-icon">' .
        icon("admin_panel_settings") .
        '</span><div><span class="eyebrow">Elevação de acesso</span><h2>Verificação adicional</h2><p>Informe novamente sua senha e o segundo fator.</p></div></header><div class="security-auth-notice security-auth-notice-soft"><span class="security-auth-notice-icon">' .
        icon("enhanced_encryption") .
        '</span><div><strong>Acesso protegido</strong><span>A liberação vale somente para esta sessão e não cria dispositivo persistente.</span></div></div><form method="post" class="compact security-reauth-form">' .
        csrf_field() .
        form_row(
            "Senha atual",
            '<div class="password-field">' .
                input(
                    "password",
                    "password",
                    "",
                    'required autocomplete="current-password" data-password-toggle',
                ) .
                '<button type="button" class="password-toggle" data-password-toggle-button aria-label="Mostrar senha">' .
                icon("visibility") .
                "</button></div>",
        ) .
        form_row(
            "Código MFA",
            input(
                "code",
                "text",
                "",
                'required autocomplete="one-time-code" maxlength="16" placeholder="Código do autenticador" autocapitalize="characters" spellcheck="false"',
            ),
        ) .
        '<div class="form-actions security-reauth-actions"><a class="ghost" href="' .
        href("profile") .
        '">' .
        icon("arrow_back") .
        '<span>Cancelar</span></a><button type="submit" class="primary">' .
        icon("verified_user") .
        "<span>Confirmar acesso</span></button></div></form></section>";
    page("Confirmar acesso", $body);
}
function page_login(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_login
     * Responsabilidade: Coordena a rota e renderiza a tela “page login”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `ctx`, `redirect`, `only_digits`, `max`, `login_lock`, `login_session_wait`, `login_session_forget`, `valid_cpf`, `flash`, `login_session_remember`, `audit`, `one` e mais 19.
     * Estado externo lido: `$_SESSION`, `$_POST`, `$_SERVER`, `$_GET`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; lê ou altera a sessão; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; produz conteúdo de saída; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 2: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    unset($_SESSION["pending_login_uid"], $_SESSION["pending_device_login"]);
    if (ctx()) {
        redirect(ctx()["scope"] === "global" ? "admin_painel" : "appointments");
    }
    $cpf = only_digits($_POST["cpf"] ?? ($_SESSION["login_last_cpf"] ?? ""));
    $wait = max($cpf ? login_lock($cpf) : 0, login_session_wait());
    if ($wait <= 0) {
        login_session_forget();
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        login_locks_cleanup_maybe();
        $cpf = only_digits($_POST["cpf"] ?? "");
        $_SESSION["login_last_cpf"] = $cpf;
        $cpfValid = valid_cpf($cpf);
        [$loginSubjectHash, $loginIpHash] = login_key($cpf);
        $loginAttemptLock =
            "prontoo_login_" .
            substr(
                hash("sha256", $loginSubjectHash . "|" . $loginIpHash),
                0,
                48,
            );
        $loginAttemptLocked = false;
        $loginAttemptState = "busy";
        $person = null;
        $userRow = null;
        $wait = 2;
        try {
            $loginAttemptLocked =
                (int) val("SELECT GET_LOCK(?,2)", [$loginAttemptLock]) === 1;
            if ($loginAttemptLocked) {
                $wait = max(
                    $cpf ? login_lock($cpf) : 0,
                    login_session_wait(),
                );
                if ($wait > 0) {
                    $loginAttemptState = "locked";
                } else {
                    $person = $cpfValid
                        ? one(
                            "SELECT id,full_name,cpf,birth_date FROM pi_persons WHERE cpf=? LIMIT 1",
                            [$cpf],
                        )
                        : null;
                    $userRow = $person
                        ? one(
                            "SELECT id uid,password_hash,active,is_global_admin FROM pi_users WHERE person_id=? LIMIT 1",
                            [(int) $person["id"]],
                        )
                        : null;
                    if ($person && $userRow) {
                        $person += $userRow;
                    }
                    $passwordValid = $person && $userRow
                        ? password_verify(
                            (string) $_POST["password"],
                            (string) $person["password_hash"],
                        )
                        : password_verify(
                            (string) ($_POST["password"] ?? ""),
                            '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
                        );
                    if (
                        !$person ||
                        !$userRow ||
                        !(int) $person["active"] ||
                        !$passwordValid
                    ) {
                        $wait = login_fail($cpf);
                        $loginAttemptState = "invalid";
                    } else {
                        login_clear($cpf);
                        $loginAttemptState = "authenticated";
                    }
                }
            }
        } finally {
            if ($loginAttemptLocked) {
                try {
                    val("SELECT RELEASE_LOCK(?)", [$loginAttemptLock]);
                } catch (Throwable $unlockError) {
                    error_log(
                        "[Prontoo login attempt unlock] " .
                            $unlockError->getMessage(),
                    );
                }
            }
        }
        if (in_array($loginAttemptState, ["busy", "locked"], true)) {
            login_session_remember(
                $cpf,
                $wait,
                "Você tentou entrar muitas vezes.",
            );
            audit("falha_entrada", "login", null, [
                "cpf" => $cpf,
                "motivo" =>
                    $loginAttemptState === "busy"
                        ? "tentativa concorrente"
                        : "tentativa durante pausa",
                "aguarde_segundos" => $wait,
            ]);
            redirect("login");
        }
        if ($loginAttemptState === "invalid") {
            login_session_remember(
                $cpf,
                $wait,
                "CPF ou senha não conferem.",
            );
            audit("falha_entrada", "login", null, [
                "cpf" => $cpf,
                "aguarde_segundos" => $wait,
            ]);
            redirect("login");
        }
        login_session_forget();
        $uid = (int) $person["uid"];
        $choices = active_clinic_roles_for_user($uid);
        $credential = login_resolve_user_credential(
            $uid,
            (int) $person["is_global_admin"] === 1,
            $choices,
        );
        if (!$credential) {
            $w = login_fail($cpf);
            login_session_remember(
                $cpf,
                $w,
                "CPF ou senha não conferem.",
            );
            audit("falha_entrada", "login", null, [
                "cpf" => $cpf,
                "motivo" => "sem vínculo clínico ativo",
                "aguarde_segundos" => $w,
            ]);
            redirect("login");
        }
        if ((int) $person["is_global_admin"] === 1) {
            mfa_begin_pending_login($uid, $credential);
            redirect("mfa");
        }
        prontoo_login_post_password_maintenance($uid);
        login_apply_resolved_credential($uid, $credential);
    }
    $wait = max($cpf ? login_lock($cpf) : 0, login_session_wait());
    $prefill = e($_SESSION["login_last_cpf"] ?? "");
    $lockTitle = e(
        (string) ($_SESSION["login_lock_message"] ?? "CPF ou senha não conferem."),
    );
    $reloginNotice =
        (string) ($_GET["relogin"] ?? "") === "1"
            ? '<div class="flash warn" role="status">Entre novamente para continuar.</div>'
            : "";
    $msg =
        $wait > 0
            ? '<div class="login-lock-panel" role="alert" aria-live="polite" data-login-wait data-login-lock-title="' .
                $lockTitle .
                '" data-login-lock-total="' .
                max(1, $wait) .
                '"><div class="login-lock-icon">' .
                icon("lock_clock") .
                '</div><div class="login-lock-copy"><strong>' .
                $lockTitle .
                '</strong><span>Tente novamente em <em data-countdown="' .
                $wait .
                '">' .
                seconds_label($wait) .
                '</em>.</span></div><div class="login-lock-meter" aria-hidden="true"><i data-countdown-bar style="--progress:100%"></i></div></div>'
            : $reloginNotice;
    $form =
        '<section class="auth login-card login-shell"><div class="auth-titleline login-titleline"><div class="auth-brandmark" data-app-favicon-brandmark><img class="auth-brandmark-favicon" src="/public/assets/app-icon-' .
        e(PRONTOO_ASSET_REV) .
        '.png" alt="" aria-hidden="true"></div><div><span class="eyebrow">Prontoo</span><h1>Meu Consultório</h1></div></div>' .
        $msg .
        '<div class="login-boot" data-login-boot role="status" aria-live="polite"><span data-login-boot-icon>' .
        icon("sync") .
        "</span><small data-login-boot-status>Verificando liberação do acesso.</small></div>" .
        '<form method="post" data-login-form data-login-autotest data-login-locked="' .
        ($wait > 0 ? "1" : "0") .
        '" class="login-form">' .
        csrf_field() .
        form_row(
            "CPF",
            input(
                "cpf",
                "text",
                $prefill,
                'required inputmode="numeric" autocomplete="username" maxlength="14" placeholder="000.000.000-00" data-login-cpf',
            ),
        ) .
        form_row(
            "Senha",
            '<div class="password-field">' .
                input(
                    "password",
                    "password",
                    "",
                    'required minlength="8" maxlength="128" autocomplete="current-password" placeholder="Sua senha" data-login-password data-password-toggle',
                ) .
                '<button type="button" class="password-toggle" data-password-toggle-button aria-label="Mostrar senha">' .
                icon("visibility") .
                "</button></div>",
        ) .
        '<button type="submit" class="primary wide login-submit" data-login-submit disabled aria-disabled="true">' .
        icon("hourglass_top") .
        "<span>Entrar</span></button></form>" .
        (function_exists("clinic_signup_blocked") && clinic_signup_blocked()
            ? ""
            : '<div class="auth-footer"><span>Ainda não usa o Prontoo?</span><a class="ghost small" href="' .
                href("signup") .
                '">' .
                icon("home_health") .
                "<span>Criar consultório</span></a></div>") .
        "</section>";
    page("Meu Consultório", $form, ["public" => true]);
}
function login_key(string $cpf): array
{
    /*
     * GUIA DE MANUTENÇÃO — login_key
     * Responsabilidade: Implementa a responsabilidade “login key” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `login_lock`, `login_fail`, `login_clear`.
     * Dependências chamadas: `secret_key`, `hash_hmac`.
     * Estado externo lido: `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $secret = secret_key();
    return [
        hash_hmac("sha256", $cpf . "|subject", $secret),
        hash_hmac("sha256", ($_SERVER["REMOTE_ADDR"] ?? "") . "|ip", $secret),
    ];
}
/* Guia de manutenção: Deriva buckets por credencial, usuário e origem para conter ataques distribuídos. */
function login_bucket_keys(string $cpf): array
{
    [$subject, $ip] = login_key($cpf);
    $secret = secret_key();
    return [
        [$subject, $ip],
        [
            $subject,
            hash_hmac("sha256", "login|all-ip-addresses", $secret),
        ],
        [
            hash_hmac("sha256", "login|all-subjects", $secret),
            $ip,
        ],
    ];
}
/* Guia de manutenção: Remove controles de login antigos oportunisticamente para limitar crescimento da tabela. */
function login_locks_cleanup_maybe(): void
{
    if (random_int(1, 64) !== 1) {
        return;
    }
    try {
        q(
            "DELETE FROM pi_login_locks WHERE locked_until<UNIX_TIMESTAMP()-604800 LIMIT 500",
        );
    } catch (Throwable $e) {
        error_log("[Prontoo login lock cleanup] " . $e->getMessage());
    }
}
function login_lock(string $cpf): int
{
    /*
     * GUIA DE MANUTENÇÃO — login_lock
     * Responsabilidade: Implementa a responsabilidade “login lock” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_login`.
     * Dependências chamadas: `login_key`, `time`, `one`, `max`, `error_log`, `->getMessage`, `login_session_wait`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        [$pair, $subject, $ip] = login_bucket_keys($cpf);
        $now = time();
        $r = one(
            "SELECT MAX(GREATEST(0,locked_until-?)) AS wait_seconds FROM pi_login_locks WHERE (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?)",
            [
                $now,
                $pair[0],
                $pair[1],
                $subject[0],
                $subject[1],
                $ip[0],
                $ip[1],
            ],
        );
        if (!$r) {
            return 0;
        }
        return max(0, (int) ($r["wait_seconds"] ?? 0));
    } catch (Throwable $e) {
        error_log("[Prontoo login_lock] " . $e->getMessage());
        return login_session_wait();
    }
}
function login_fail(string $cpf): int
{
    /*
     * GUIA DE MANUTENÇÃO — login_fail
     * Responsabilidade: Implementa a responsabilidade “login fail” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_login`.
     * Dependências chamadas: `login_key`, `one`, `min`, `max`, `time`, `q`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    $seconds = 60;
    try {
        [$pair, $subject, $ip] = login_bucket_keys($cpf);
        $buckets = [
            [$pair, 1, 2, 900],
            [$subject, 5, 60, 86400],
            [$ip, 20, 60, 3600],
        ];
        foreach ($buckets as [$keys, $threshold, $base, $cap]) {
            q(
                "INSERT INTO pi_login_locks (subject_hash,ip_hash,fail_count,locked_until,updated_at)
                 VALUES (?,?,1,IF(?<=1,UNIX_TIMESTAMP()+?,0),NOW())
                 ON DUPLICATE KEY UPDATE
                   locked_until=CASE
                     WHEN fail_count+1>=? THEN UNIX_TIMESTAMP()+CAST(
                       LEAST(?,?*POW(2,LEAST(10,GREATEST(0,fail_count+1-?))))
                       AS UNSIGNED
                     )
                     ELSE COALESCE(locked_until,0)
                   END,
                   fail_count=LEAST(100000,fail_count+1),
                   updated_at=NOW()",
                [
                    $keys[0],
                    $keys[1],
                    $threshold,
                    $base,
                    $threshold,
                    $cap,
                    $base,
                    $threshold,
                ],
            );
        }
        $seconds = max(1, login_lock($cpf));
    } catch (Throwable $e) {
        error_log("[Prontoo login_fail] " . $e->getMessage());
    }
    return $seconds;
}
function login_clear(string $cpf): void
{
    /*
     * GUIA DE MANUTENÇÃO — login_clear
     * Responsabilidade: Implementa a responsabilidade “login clear” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_login`.
     * Dependências chamadas: `login_key`, `q`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    try {
        [$pair, $subject] = login_bucket_keys($cpf);
        q(
            "DELETE FROM pi_login_locks WHERE (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?)",
            [$pair[0], $pair[1], $subject[0], $subject[1]],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo login_clear] " . $e->getMessage());
    }
}
function mark_login_success(int $uid): void
{
    /*
     * GUIA DE MANUTENÇÃO — mark_login_success
     * Responsabilidade: Implementa a responsabilidade “mark login success” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `login_apply_resolved_credential`.
     * Dependências chamadas: `val`, `app_apply_request_timezone`, `app_global_admin_timezone`, `q`, `error_log`, `->getMessage`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; lê ou altera a sessão; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    try {
        if (
            ($_SESSION["scope"] ?? "") === "clinic" &&
            (int) ($_SESSION["uc_id"] ?? 0) > 0
        ) {
            $tz =
                (string) (val(
                    "SELECT c.timezone FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id WHERE ur.id=? AND ur.user_id=? LIMIT 1",
                    [(int) $_SESSION["uc_id"], $uid],
                ) ?:
                "America/Cuiaba");
            app_apply_request_timezone($tz);
        } elseif (($_SESSION["scope"] ?? "") === "global") {
            app_apply_request_timezone(app_global_admin_timezone($uid));
        }
        q(
            "UPDATE pi_users SET failed_login_count=0, locked_until=NULL, last_login_at=NOW() WHERE id=?",
            [$uid],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo mark_login_success] " . $e->getMessage());
    }
}
function login_session_remember(
    string $cpf,
    int $wait,
    string $message = "CPF ou senha não conferem.",
): void {
    /*
     * GUIA DE MANUTENÇÃO — login_session_remember
     * Responsabilidade: Implementa a responsabilidade “login session remember” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_login`.
     * Dependências chamadas: `time`, `max`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $_SESSION["login_last_cpf"] = $cpf;
    $_SESSION["login_wait_until"] = time() + max(0, $wait);
    $_SESSION["login_lock_message"] = $message;
}
function login_session_wait(): int
{
    /*
     * GUIA DE MANUTENÇÃO — login_session_wait
     * Responsabilidade: Implementa a responsabilidade “login session wait” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_login`, `login_lock`.
     * Dependências chamadas: `max`, `time`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return max(0, (int) ($_SESSION["login_wait_until"] ?? 0) - time());
}
function login_session_forget(): void
{
    /*
     * GUIA DE MANUTENÇÃO — login_session_forget
     * Responsabilidade: Implementa a responsabilidade “login session forget” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_login`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    unset(
        $_SESSION["login_wait_until"],
        $_SESSION["login_last_cpf"],
        $_SESSION["login_lock_message"],
    );
}
function seconds_label(int $s): string
{
    /*
     * GUIA DE MANUTENÇÃO — seconds_label
     * Responsabilidade: Monta a representação de interface associada a “seconds label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_login`.
     * Dependências chamadas: `max`, `floor`, `str_pad`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $s = max(0, $s);
    $m = floor($s / 60);
    $r = $s % 60;
    return $m > 0
        ? $m . "min " . str_pad((string) $r, 2, "0", STR_PAD_LEFT) . "s"
        : $r . "s";
}
function page_signup(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_signup
     * Responsabilidade: Coordena a rota e renderiza a tela “page signup”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `ctx`, `redirect`, `function_exists`, `clinic_signup_blocked`, `page`, `e`, `href`, `flash`, `only_digits`, `security_rate_limit`, `security_client_bucket`, `security_ip_bucket` e mais 53.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Estado externo lido: `$_SERVER`, `$_POST`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; produz conteúdo de saída; gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     * Cuidado 3: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    $currentCtx = ctx();
    if ($currentCtx) {
        redirect(
            ($currentCtx["scope"] ?? "") === "global"
                ? "admin_painel"
                : "painel",
        );
    }
    if (function_exists("clinic_signup_blocked") && clinic_signup_blocked()) {
        page(
            "Cadastro pausado",
            '<section class="auth widebox"><h1>A inauguração de consultórios foi pausada</h1><p>Atingimos nossa capacidade máxima de consultórios hoje. Tente novamente mais tarde.</p><p><a class="ghost" href="' .
                e(href("login")) .
                '">Tentarei mais tarde</a></p></section>',
            ["public" => true, "robots" => "noindex,nofollow"],
        );
        return;
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        if (empty($_POST["trial_accept"])) {
            flash("Confirme que deseja experimentar sem custo.", "bad");
            redirect("signup");
        }
        $pass = (string) $_POST["password"];
        $cpf = only_digits((string) $_POST["cpf"]);
        $doc = only_digits((string) $_POST["legal_document"]);
        if (
            security_rate_limit(security_client_bucket("signup"), 5, 3600) ||
            security_rate_limit(security_ip_bucket("signup"), 20, 3600) ||
            ($cpf !== "" &&
                security_rate_limit(
                    security_value_bucket("signup_cpf", $cpf),
                    3,
                    3600,
                )) ||
            ($doc !== "" &&
                security_rate_limit(
                    security_value_bucket("signup_doc", $doc),
                    4,
                    3600,
                ))
        ) {
            flash(
                "Muitas tentativas de cadastro. Aguarde alguns instantes antes de tentar novamente.",
                "bad",
            );
            redirect("signup");
        }
        $legalType = (string) ($_POST["legal_type"] ?? "");
        $profession = normalize_profession(
            (string) ($_POST["responsible_profession"] ?? ""),
        );
        if (!valid_cpf($cpf)) {
            flash("Este CPF não existe.", "bad");
            redirect("signup");
        }
        if (!valid_birth_date((string) $_POST["birth_date"])) {
            flash(
                "Informe uma data de nascimento válida para o responsável pelo consultório.",
                "bad",
            );
            redirect("signup");
        }
        if (!in_array($legalType, ["cpf", "cnpj"], true)) {
            flash(
                "Escolha se o consultório será pessoa física ou jurídica.",
                "bad",
            );
            redirect("signup");
        }
        if ($legalType === "cpf" && !valid_cpf($doc)) {
            flash("Este CPF não existe.", "bad");
            redirect("signup");
        }
        if ($legalType === "cnpj" && !valid_cnpj($doc)) {
            flash("Informe CNPJ válido para o consultório.", "bad");
            redirect("signup");
        }
        $uf = strtoupper(trim((string) ($_POST["address_state"] ?? "")));
        $city = trim((string) ($_POST["address_city"] ?? ""));
        $cityIbge = (int) ($_POST["address_city_ibge"] ?? 0);
        if (!isset(br_states()[$uf]) || $city === "" || $cityIbge <= 0) {
            flash("Escolha a cidade de atuação na lista do IBGE.", "bad");
            redirect("signup");
        }
        $timezone = timezone_from_location($uf, $city);
        $accentColor = PRONTOO_DEFAULT_ACCENT_COLOR;
        db_begin_transaction();
        try {
            $pid = upsert_person(
                trim((string) $_POST["doctor_name"]),
                $cpf,
                (string) $_POST["birth_date"],
            );
            lock_person_user_identity($pid);
            $existing = one(
                "SELECT id,password_hash,active,email FROM pi_users WHERE person_id=? LIMIT 1",
                [$pid],
            );
            if ($existing) {
                if (!(int) $existing["active"]) {
                    throw new RuntimeException(
                        "Usuário existente está inativo.",
                    );
                }
                if (
                    !password_verify($pass, (string) $existing["password_hash"])
                ) {
                    db_rollback();
                    flash(
                        "Não foi possível confirmar as credenciais informadas. Revise CPF, senha e dados do consultório.",
                        "bad",
                    );
                    redirect("signup");
                }
                $uid = (int) $existing["id"];
                $email = trim((string) $_POST["email"]);
                if ($email !== "" && empty($existing["email"])) {
                    q(
                        "UPDATE pi_users SET email=?,updated_at=NOW() WHERE id=?",
                        [$email, $uid],
                    );
                }
            } else {
                if (!password_ok($pass)) {
                    db_rollback();
                    flash(
                        "Use senha com 8 a 128 caracteres que não seja uma senha comum.",
                        "bad",
                    );
                    redirect("signup");
                }
                q(
                    "INSERT INTO pi_users (person_id,name,email,password_hash,active,created_at) VALUES (?,?,?,?,1,NOW())",
                    [
                        $pid,
                        trim((string) $_POST["doctor_name"]),
                        trim((string) $_POST["email"]) ?: null,
                        password_hash_secure($pass),
                    ],
                );
                $uid = db_last_insert_id();
                counter_inc("users_total");
            }
            $trialStart = time();
            $trialEnd = function_exists("subscription_trial_end_from_start")
                ? subscription_trial_end_from_start(
                    $trialStart,
                    default_trial_days(),
                )
                : $trialStart + max(1, default_trial_days()) * 86400;
            q(
                "INSERT INTO pi_clinics (legal_type,legal_name,legal_document,display_name,phone,responsible_profession,owner_user_id,manager_user_id,accent_color,address_line,address_state,address_city,address_city_ibge,timezone,onboarding_done,onboarding_completed_at,trial_started_at,trial_ends_at,subscription_status,monthly_price_cents,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,'trial',?,?)",
                [
                    $legalType,
                    trim((string) $_POST["legal_name"]),
                    $doc,
                    trim((string) $_POST["display_name"]),
                    phone_br((string) $_POST["phone"]),
                    $profession,
                    $uid,
                    $uid,
                    $accentColor,
                    trim((string) ($_POST["address_line"] ?? "")),
                    $uf,
                    $city,
                    $cityIbge,
                    $timezone,
                    $trialStart,
                    $trialStart,
                    $trialEnd,
                    default_monthly_price_cents(),
                    $trialStart,
                ],
            );
            $cid = db_last_insert_id();
            if (function_exists("ensure_clinic_trial_active")) {
                ensure_clinic_trial_active($cid, false);
            }
            counter_inc("clinics_total");
            q(
                "INSERT INTO pi_user_roles (user_id,clinic_id,role_code,is_owner,active) VALUES (?,?,?,1,1) ON DUPLICATE KEY UPDATE is_owner=1, active=1",
                [$uid, $cid, "gerente"],
            );
            q(
                "INSERT INTO pi_user_roles (user_id,clinic_id,role_code,is_owner,active) VALUES (?,?,?,1,1) ON DUPLICATE KEY UPDATE is_owner=1, active=1",
                [$uid, $cid, "medico"],
            );
            $managerRoleId = (int) (val(
                "SELECT id FROM pi_user_roles WHERE user_id=? AND clinic_id=? AND role_code='gerente' AND active=1 LIMIT 1",
                [$uid, $cid],
            ) ?: 0);
            if ($managerRoleId <= 0) {
                throw new RuntimeException(
                    "Não foi possível definir o ambiente Administrativo inicial.",
                );
            }
            $ownerRoles = q(
                "SELECT role_code FROM pi_user_roles WHERE user_id=? AND clinic_id=? AND active=1 AND role_code IN ('gerente','medico')",
                [$uid, $cid],
            )->fetchAll(PDO::FETCH_COLUMN);
            $ownerRoles = array_values(
                array_unique(array_map("strval", $ownerRoles ?: [])),
            );
            sort($ownerRoles);
            if ($ownerRoles !== ["gerente", "medico"]) {
                throw new RuntimeException(
                    "Não foi possível registrar os ambientes Administrativo e Profissional do responsável.",
                );
            }
            seed_permissions($cid);
            seed_clinic_roles($cid);
            q(
                "UPDATE pi_clinic_roles SET label=? WHERE clinic_id=? AND role_code='medico'",
                [$profession, $cid],
            );
            audit("consultorio_criado", "consultorio", $cid, [
                "clinic_id" => $cid,
                "nome" => $_POST["display_name"],
                "owner_user_id" => $uid,
                "cidade" => $city,
                "uf" => $uf,
                "timezone" => $timezone,
                "accent_color" => $accentColor,
                "onboarding_done" => 1,
            ]);
            db_commit();
            login_last_credential_remember(
                $uid,
                "clinic",
                $managerRoleId,
            );
            flash(
                "Seu consultório foi inaugurado e configurado. Insira CPF e Senha para entrar como Administrativo.",
            );
            redirect("login");
        } catch (Throwable $e) {
            if (pdo()->inTransaction()) {
                db_rollback();
            }
            error_log("[Prontoo signup] " . $e->getMessage());
            $reason =
                "Revise os dados informados, a senha atual do CPF ou o documento do consultório.";
            flash("Não foi possível concluir o cadastro. " . $reason, "bad");
            redirect("signup");
        }
    }
    $signupPrice = money_br(default_monthly_price_cents());
    $signupTrialDays = default_trial_days();
    $signupTrialLabel = trial_period_label($signupTrialDays);
    $signupTrialCopy =
        $signupTrialDays > 0
            ? "Use por " .
                $signupTrialLabel .
                " sem compromisso. Se gostar, renove por mais 30 dias por " .
                $signupPrice .
                "."
            : "Os primeiros 30 dias são uma cortesia. Aproveite!";
    $signupConsent =
        $signupTrialDays > 0
            ? "Quero experimentar por " .
                $signupTrialLabel .
                ", sem nenhum compromisso."
            : "Quero começar sem fidelidade e sem compromisso.";
    $responsibleFields =
        '<div class="signup-ds-grid two">' .
        form_row(
            "CPF",
            input(
                "cpf",
                "text",
                "",
                'required inputmode="numeric" maxlength="14" autocomplete="username" placeholder="000.000.000-00" data-person-cpf-lookup="' .
                    e(href("person_lookup")) .
                    '" data-person-lookup-context="signup" data-person-name-target="doctor_name" data-person-birth-target="birth_date"',
            ),
        ) .
        form_row(
            "Nome completo",
            input(
                "doctor_name",
                "text",
                "",
                'required autocomplete="name" placeholder="Nome do responsável"',
            ),
        ) .
        '</div><div class="signup-ds-grid two">' .
        form_row("Nascimento", input("birth_date", "date", "", "required")) .
        form_row(
            "E-mail",
            input(
                "email",
                "email",
                "",
                'required autocomplete="email" placeholder="email@exemplo.com"',
            ),
        ) .
        "</div>" .
        profession_select_fields() .
        form_row(
            "Senha",
            '<div class="password-field">' .
                input(
                    "password",
                    "password",
                    "",
                    'required minlength="8" maxlength="128" autocomplete="new-password" placeholder="Senha nova ou senha atual se o CPF já existir" data-password-strength data-password-toggle',
                ) .
                '<button type="button" class="password-toggle" data-password-toggle-button aria-label="Mostrar senha">' .
                icon("visibility") .
                "</button></div>",
        );
    $clinicFields =
        '<div class="signup-ds-grid two">' .
        select_label(
            "Tipo de pessoa",
            "legal_type",
            ["cpf" => "Pessoa física", "cnpj" => "Pessoa jurídica"],
            null,
            "required",
        ) .
        form_row(
            "CPF/CNPJ do consultório",
            input(
                "legal_document",
                "text",
                "",
                'required inputmode="numeric" placeholder="Documento do consultório" data-doc-mask data-document-validate',
            ),
        ) .
        '</div><div class="signup-ds-grid two">' .
        form_row(
            "Nome/Razão social",
            input(
                "legal_name",
                "text",
                "",
                'required placeholder="Nome jurídico do consultório"',
            ),
        ) .
        form_row(
            "Nome fantasia",
            input(
                "display_name",
                "text",
                "",
                'required placeholder="Nome exibido no sistema"',
            ),
        ) .
        "</div>" .
        form_row(
            "Telefone principal",
            input(
                "phone",
                "text",
                "",
                'autocomplete="tel" inputmode="tel" placeholder="(00) 00000-0000"',
            ),
        ) .
        clinic_location_fields();
    $form =
        '<section class="auth widebox signup-card signup-steps-card signup-ds-shell signup-screen-flow"><header class="signup-ds-hero"><div class="signup-ds-hero-main"><span class="eyebrow signup-opening-label">' .
        icon("home_health") .
        '<span>Novo consultório</span></span><h1>Criar consultório</h1><p>Configure o acesso inicial, identifique o responsável e registre o consultório em um fluxo simples e seguro. Cada etapa aparece em uma tela própria.</p></div><div class="auth-brandmark signup-brandmark" data-app-favicon-brandmark><img class="auth-brandmark-favicon app-brandmark-img" src="/public/assets/app-icon-' .
        e(PRONTOO_ASSET_REV) .
        '.png" alt="" aria-hidden="true"></div></header><ol class="signup-ds-stepper" aria-label="Etapas para criar consultório"><li class="is-active" data-signup-indicator="0"><b>1</b><span><strong>Experimente</strong><small>Sem compromisso</small></span></li><li data-signup-indicator="1"><b>2</b><span><strong>Responsável</strong><small>CPF e acesso</small></span></li><li data-signup-indicator="2"><b>3</b><span><strong>Consultório</strong><small>Dados principais</small></span></li></ol><form method="post" class="compact signup-form signup-wizard signup-ds-form" data-signup-steps>' .
        csrf_field() .
        '<section class="signup-step signup-ds-step signup-screen-panel is-active" data-signup-step="0"><div class="signup-screen-kicker"><span>Etapa 1 de 3</span><strong>Experimente sem compromisso</strong></div><div class="signup-ds-layout"><article class="signup-ds-offer-card"><span class="signup-ds-icon">' .
        icon("verified") .
        '</span><div><span class="eyebrow">Teste inicial</span><h2>Comece com calma</h2><p>' .
        e($signupTrialCopy) .
        '</p></div></article><article class="signup-ds-price-card"><span class="eyebrow">Após o período inicial</span><strong>' .
        e($signupPrice) .
        '<small>/mês</small></strong><p>Plano mensal, sem fidelidade, com cancelamento livre.</p></article></div><div class="signup-ds-feature-grid"><span>' .
        icon("event_available") .
        "<b>Agenda</b><small>Consultas e bloqueios organizados.</small></span><span>" .
        icon("patient_list") .
        "<b>Pacientes</b><small>Dados clínicos acessíveis por perfil.</small></span><span>" .
        icon("payments") .
        '<b>Financeiro</b><small>Entradas, baixas e acompanhamento.</small></span></div><label class="checkline signup-consent signup-ds-consent"><input type="checkbox" name="trial_accept" value="1" required data-signup-trial-accept data-required-message="Confirme que deseja experimentar sem custo."><span>' .
        e($signupConsent) .
        '</span></label><div class="signup-actions signup-step-actions signup-ds-actions"><a class="ghost" href="' .
        href("login") .
        '">' .
        icon("arrow_back") .
        '<span>Voltar para entrada</span></a><button type="button" class="primary" data-signup-next>' .
        icon("arrow_forward") .
        '<span>Continuar</span></button></div></section><section class="signup-step signup-ds-step signup-screen-panel" data-signup-step="1" hidden><div class="signup-screen-kicker"><span>Etapa 2 de 3</span><strong>Responsável e acesso</strong></div><fieldset class="signup-ds-fieldset"><legend><span class="signup-ds-icon small">' .
        icon("account_circle") .
        '</span><span><b>Responsável pelo consultório</b><small>Use CPF, dados pessoais e senha de acesso.</small></span></legend><p class="field-help">Se este CPF já existir, informe a senha atual para vincular o novo consultório ao mesmo acesso.</p>' .
        $responsibleFields .
        '</fieldset><div class="signup-actions signup-step-actions signup-ds-actions"><button type="button" class="ghost" data-signup-prev>' .
        icon("arrow_back") .
        '<span>Voltar</span></button><button type="button" class="primary" data-signup-next>' .
        icon("arrow_forward") .
        '<span>Dados do consultório</span></button></div></section><section class="signup-step signup-ds-step signup-screen-panel" data-signup-step="2" hidden><div class="signup-screen-kicker"><span>Etapa 3 de 3</span><strong>Identificação do consultório</strong></div><fieldset class="signup-ds-fieldset"><legend><span class="signup-ds-icon small">' .
        icon("domain_add") .
        '</span><span><b>Identificação do consultório</b><small>Dados definitivos para abrir o ambiente inicial.</small></span></legend><p class="field-help">A cidade de atuação define automaticamente o fuso horário. Identidade visual, departamentos, equipe e permissões serão orientados dentro do consultório.</p>' .
        $clinicFields .
        '</fieldset><div class="signup-actions signup-step-actions signup-ds-actions"><button type="button" class="ghost" data-signup-prev>' .
        icon("arrow_back") .
        '<span>Voltar</span></button><button type="submit" class="primary">' .
        icon("check_circle") .
        "<span>Criar consultório</span></button></div></section></form></section>";
    page("Criar consultório", $form, ["public" => true]);
}
function upsert_person(string $name, string $cpf, string $birth): int
{
    /*
     * GUIA DE MANUTENÇÃO — upsert_person
     * Responsabilidade: Implementa a responsabilidade “upsert person” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_signup`, `lead_prepare_person_for_patient`, `page_patients`, `save_team_member`, `prontoo_install`.
     * Dependências chamadas: `trim`, `only_digits`, `RuntimeException`, `valid_cpf`, `valid_birth_date`, `val`, `person_identity_immutable_values`, `app_date_input_from_storage`, `one`, `q`, `implode`, `person_signature_refresh_verified` e mais 2.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    $name = trim($name);
    $cpf = only_digits($cpf);
    if ($name === "") {
        throw new RuntimeException("Nome da pessoa não informado.");
    }
    if (!valid_cpf($cpf)) {
        throw new RuntimeException("Este CPF não existe.");
    }
    if (!valid_birth_date($birth)) {
        throw new RuntimeException("Nascimento inválido.");
    }

    $id = val("SELECT id FROM pi_persons WHERE cpf=?", [$cpf]);
    if ($id) {
        $id = (int) $id;
        $identity = person_identity_immutable_values($id, $cpf, $birth, true);
        $expectedBirth = app_date_input_from_storage(
            (string) $identity["birth_date"],
        );
        $current = one(
            "SELECT full_name,birth_date FROM pi_persons WHERE id=?",
            [$id],
        ) ?: [];
        $sets = [];
        $params = [];
        if (trim((string) ($current["full_name"] ?? "")) === "") {
            $sets[] = "full_name=?";
            $params[] = $name;
        }
        $currentBirth = app_date_input_from_storage(
            (string) ($current["birth_date"] ?? ""),
        );
        if ($currentBirth === "" || !valid_birth_date($currentBirth)) {
            $sets[] = "birth_date=?";
            $params[] = $expectedBirth;
        }
        if ($sets) {
            $sets[] = "updated_at=NOW()";
            $params[] = $id;
            q(
                "UPDATE pi_persons SET " . implode(",", $sets) . " WHERE id=?",
                $params,
            );
        }
        $storedBirth = app_date_input_from_storage(
            (string) val("SELECT birth_date FROM pi_persons WHERE id=?", [$id]),
        );
        if ($storedBirth === "" || $storedBirth !== $expectedBirth) {
            throw new RuntimeException(
                "A data de nascimento não pôde ser registrada corretamente.",
            );
        }
        person_signature_refresh_verified($id);
        return $id;
    }

    q(
        "INSERT INTO pi_persons (full_name,cpf,birth_date,assinatura,created_at) VALUES (?,?,?,?,NOW())",
        [$name, $cpf, $birth, person_signature_value($cpf, $name, $birth)],
    );
    $id = db_last_insert_id();
    person_signature_refresh_verified($id);
    return $id;
}
function lock_person_user_identity(int $personId): void
{
    /*
     * GUIA DE MANUTENÇÃO — lock_person_user_identity
     * Responsabilidade: Implementa a responsabilidade “lock person user identity” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_signup`, `save_team_member`.
     * Dependências chamadas: `pdo`, `->inTransaction`, `RuntimeException`, `one`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($personId <= 0 || !pdo()->inTransaction()) {
        throw new RuntimeException(
            "Não foi possível iniciar a gravação segura do usuário.",
        );
    }
    $locked = one(
        "SELECT id FROM pi_persons WHERE id=? FOR UPDATE",
        [$personId],
    );
    if (!$locked) {
        throw new RuntimeException(
            "A pessoa vinculada ao usuário não foi encontrada.",
        );
    }
}
function save_person_flexible(
    string $name,
    ?string $cpf = null,
    ?string $birth = null,
): int {
    /*
     * GUIA DE MANUTENÇÃO — save_person_flexible
     * Responsabilidade: Valida e executa a mutação “save person flexible”, preservando as invariantes do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `save_person_by_document`, `financial_creditor_upsert_from_post`, `page_leads`.
     * Dependências chamadas: `trim`, `only_digits`, `RuntimeException`, `valid_cpf`, `valid_birth_date`, `session_clinic_scope_id`, `val`, `person_identity_immutable_values`, `one`, `app_date_input_from_storage`, `q`, `implode` e mais 3.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    $name = trim($name);
    $cpf = only_digits((string) ($cpf ?? ""));
    $birth = trim((string) ($birth ?? "")) ?: null;
    if ($name === "") {
        throw new RuntimeException("Nome da pessoa não informado.");
    }
    if ($cpf !== "" && !valid_cpf($cpf)) {
        throw new RuntimeException("Este CPF não existe.");
    }
    if ($birth !== null && !valid_birth_date($birth)) {
        throw new RuntimeException("Nascimento inválido.");
    }
    $clinicId = session_clinic_scope_id() ?: null;
    if ($cpf !== "") {
        $id = val("SELECT id FROM pi_persons WHERE cpf=? LIMIT 1", [$cpf]);
        if ($id) {
            $id = (int) $id;
            $identity = person_identity_immutable_values(
                $id,
                $cpf,
                $birth,
                false,
            );
            $expectedBirth = (string) ($identity["birth_date"] ?? "");
            $current = one(
                "SELECT full_name,birth_date,clinic_id FROM pi_persons WHERE id=?",
                [$id],
            ) ?: [];
            $sets = [];
            $params = [];
            if (
                $clinicId !== null &&
                (int) ($current["clinic_id"] ?? 0) <= 0
            ) {
                $sets[] = "clinic_id=?";
                $params[] = $clinicId;
            }
            if (trim((string) ($current["full_name"] ?? "")) === "") {
                $sets[] = "full_name=?";
                $params[] = $name;
            }
            $currentBirth = app_date_input_from_storage(
                (string) ($current["birth_date"] ?? ""),
            );
            if (
                $expectedBirth !== "" &&
                ($currentBirth === "" || !valid_birth_date($currentBirth))
            ) {
                $sets[] = "birth_date=?";
                $params[] = app_date_input_from_storage($expectedBirth);
            }
            if ($sets) {
                $sets[] = "updated_at=NOW()";
                $params[] = $id;
                q(
                    "UPDATE pi_persons SET " . implode(",", $sets) . " WHERE id=?",
                    $params,
                );
            }
            if ($expectedBirth !== "") {
                $storedBirth = app_date_input_from_storage(
                    (string) val(
                        "SELECT birth_date FROM pi_persons WHERE id=?",
                        [$id],
                    ),
                );
                $normalizedExpected = app_date_input_from_storage($expectedBirth);
                if ($storedBirth === "" || $storedBirth !== $normalizedExpected) {
                    throw new RuntimeException(
                        "A data de nascimento não pôde ser registrada corretamente.",
                    );
                }
            }
            person_signature_refresh_verified($id);
            return $id;
        }
    }

    $signature = $cpf !== ""
        ? person_signature_value($cpf, $name, $birth)
        : null;
    q(
        "INSERT INTO pi_persons (full_name,cpf,birth_date,assinatura,clinic_id,created_at) VALUES (?,?,?,?,?,NOW())",
        [$name, $cpf !== "" ? $cpf : null, $birth, $signature, $clinicId],
    );
    $id = db_last_insert_id();
    if ($cpf !== "") {
        person_signature_refresh_verified($id);
    }
    return $id;
}
function phone_br(?string $phone): string
{
    /*
     * GUIA DE MANUTENÇÃO — phone_br
     * Responsabilidade: Implementa a responsabilidade “phone br” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `admin_clinic_detail_page`, `page_signup`, `page_onboarding`, `page_settings`, `financial_creditor_directory_card`, `page_lead_lookup`, `page_leads`, `patient_legal_guardian_card` e mais 9.
     * Dependências chamadas: `only_digits`, `substr`, `strlen`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $d = only_digits((string) ($phone ?? ""));
    if ($d === "") {
        return "";
    }
    $d = substr($d, 0, 11);
    if (strlen($d) === 11) {
        return "(" .
            substr($d, 0, 2) .
            ") " .
            substr($d, 2, 5) .
            "-" .
            substr($d, 7, 4);
    }
    if (strlen($d) === 10) {
        return "(" .
            substr($d, 0, 2) .
            ") " .
            substr($d, 2, 4) .
            "-" .
            substr($d, 6, 4);
    }
    return $d;
}
function page_person_lookup(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_person_lookup
     * Responsabilidade: Coordena a rota e renderiza a tela “page person lookup”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `only_digits`, `headers_sent`, `header`, `security_rate_limit`, `security_client_bucket`, `security_ip_bucket`, `valid_cpf`, `security_value_bucket`, `http_response_code`, `json_encode`, `one`, `has_session_user` e mais 3.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; produz conteúdo de saída.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    $cpf = only_digits((string) ($_GET["cpf"] ?? ""));
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    $limited =
        security_rate_limit(security_client_bucket("person_lookup"), 8, 300) ||
        security_rate_limit(security_ip_bucket("person_lookup"), 30, 300);
    if ($cpf !== "" && valid_cpf($cpf)) {
        $limited =
            $limited ||
            security_rate_limit(
                security_value_bucket("person_lookup_cpf", $cpf),
                4,
                300,
            );
    }
    if ($limited) {
        http_response_code(429);
        echo json_encode(
            [
                "ok" => false,
                "found" => false,
                "message" => "Aguarde alguns instantes.",
            ],
            JSON_UNESCAPED_UNICODE,
        );
        return;
    }
    if (!valid_cpf($cpf)) {
        echo json_encode(
            [
                "ok" => false,
                "found" => false,
                "message" => "Informe um CPF válido.",
            ],
            JSON_UNESCAPED_UNICODE,
        );
        return;
    }
    $current = ctx();
    $detailed =
        has_session_user() &&
        (can("patients") ||
            can("users") ||
            can("financial") ||
            can("admin_people"));
    $p = null;
    if ($detailed && ($current["scope"] ?? "") === "global" && can("admin_people")) {
        $p = one(
            "SELECT full_name,cpf,birth_date FROM pi_persons WHERE cpf=? LIMIT 1",
            [$cpf],
        );
    } elseif (
        $detailed &&
        ($current["scope"] ?? "") === "clinic" &&
        (int) ($current["clinic_id"] ?? 0) > 0
    ) {
        $cid = (int) $current["clinic_id"];
        $p = one(
            "SELECT p.full_name,p.cpf,p.birth_date
             FROM pi_persons p
             WHERE p.cpf=?
               AND (
                 EXISTS (SELECT 1 FROM pi_patients pat WHERE pat.person_id=p.id AND pat.clinic_id=?)
                 OR EXISTS (SELECT 1 FROM pi_leads l WHERE l.person_id=p.id AND l.clinic_id=?)
                 OR EXISTS (
                   SELECT 1
                   FROM pi_users u
                   JOIN pi_user_roles ur ON ur.user_id=u.id
                   WHERE u.person_id=p.id AND ur.clinic_id=?
                 )
               )
             LIMIT 1",
            [$cpf, $cid, $cid, $cid],
        );
    }
    if (!$p || !$detailed) {
        echo json_encode(
            [
                "ok" => true,
                "found" => false,
                "message" =>
                    "CPF recebido. Continue o cadastro ou informe a senha se já possuir acesso.",
            ],
            JSON_UNESCAPED_UNICODE,
        );
        return;
    }
    echo json_encode(
        [
            "ok" => true,
            "found" => true,
            "message" => "Dados encontrados e preenchidos automaticamente.",
            "name" => (string) ($p["full_name"] ?? ""),
            "cpf" => cpf_br((string) ($p["cpf"] ?? "")),
            "birth_date" => db_birth_date_input($p["birth_date"] ?? ""),
        ],
        JSON_UNESCAPED_UNICODE,
    );
}
function page_logout(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_logout
     * Responsabilidade: Invalida a raiz canônica da autenticação e encerra a sessão local; os demais artefatos derivados falham fechados no próximo uso.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `redirect`, `user_auth_generation_rotate`, `audit`, `secure_session_destroy`, `header`, `href`.
     * Estado externo lido: `$_SERVER`, `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 2: A geração do usuário é a raiz da cascata; não reintroduza limpeza física ampla de cache ou varredura de dispositivos no caminho crítico.
     */
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
        redirect("login");
    }
    $uid = (int) ($_SESSION["uid"] ?? 0);
    $clinicId = (int) ($_SESSION["clinic_id"] ?? 0);
    $roleCode = (string) ($_SESSION["role_code"] ?? "");
    try {
        if ($uid > 0) {
            user_auth_generation_rotate($uid);
        }
        audit("saida_realizada", "seguranca", $uid ?: null, [
            "_skip_runtime_context" => 1,
            "_skip_context_enrichment" => 1,
            "clinic_id" => $clinicId > 0 ? $clinicId : null,
            "role_code" => $roleCode,
            "audit_body" =>
                "Logout concluído pela rotação da geração canônica; sessões, contextos e credenciais derivadas serão recusados na próxima tentativa de uso.",
        ]);
    } catch (Throwable $e) {
        error_log("[Prontoo logout cascade] " . $e->getMessage());
    } finally {
        secure_session_destroy();
    }
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Location: " . href("login"));
    exit();
}
function page_profile(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_profile
     * Responsabilidade: Coordena a rota e renderiza a tela “page profile”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `need_login`, `redirect`, `one`, `trim`, `mb_strlen`, `RuntimeException`, `filter_var`, `val`, `db_begin_transaction`, `q`, `audit`, `db_commit` e mais 28.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Estado externo lido: `$_POST`, `$_SERVER`, `$_SESSION`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; lê ou altera a sessão; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; produz conteúdo de saída; gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 3: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    $c = need_login();
    $uid = (int) ($c["user"]["id"] ?? 0);
    if ($uid <= 0) {
        redirect("login");
    }
    $u = one(
        "SELECT u.id,u.person_id,u.name,u.email,u.password_hash,u.is_global_admin,p.cpf,p.birth_date FROM pi_users u JOIN pi_persons p ON p.id=u.person_id WHERE u.id=? AND u.active=1 LIMIT 1",
        [$uid],
    );
    if (!$u) {
        redirect("login");
    }
    $act = (string) ($_POST["act"] ?? "");
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        if ($act === "") {
            $act = "profile_change_password";
        }
        try {
            if ($act === "profile_update_user") {
                $name = trim((string) ($_POST["name"] ?? ""));
                $email = trim((string) ($_POST["email"] ?? ""));
                if ($name === "" || mb_strlen($name) < 3) {
                    throw new RuntimeException("Informe o nome completo.");
                }
                if (
                    $email !== "" &&
                    !filter_var($email, FILTER_VALIDATE_EMAIL)
                ) {
                    throw new RuntimeException("Informe um e-mail válido.");
                }
                $personId = (int) $u["person_id"];
                if ($email !== "") {
                    $emailOwner =
                        (int) (val(
                            "SELECT id FROM pi_users WHERE email=? AND id<>? LIMIT 1",
                            [$email, $uid],
                        ) ?:
                        0);
                    if ($emailOwner > 0) {
                        throw new RuntimeException(
                            "Este e-mail já está vinculado a outro usuário.",
                        );
                    }
                }
                db_begin_transaction();
                q(
                    "UPDATE pi_persons SET full_name=?, updated_at=NOW() WHERE id=?",
                    [$name, $personId],
                );
                q(
                    "UPDATE pi_users SET name=?, email=?, updated_at=NOW() WHERE id=?",
                    [$name, $email !== "" ? $email : null, $uid],
                );
                audit("usuario_proprio_atualizado", "usuario", $uid, [
                    "target_name" => $name,
                    "audit_body" =>
                        "O próprio usuário atualizou nome e e-mail cadastrais. CPF e nascimento permanecem imutáveis.",
                ]);
                db_commit();
                flash("Dados do usuário atualizados.");
                redirect("profile");
            }
            if ($act === "profile_change_password") {
                $current = (string) ($_POST["current_password"] ?? "");
                $new = (string) ($_POST["new_password"] ?? "");
                $confirm = (string) ($_POST["new_password_confirm"] ?? "");
                if (!password_verify($current, (string) $u["password_hash"])) {
                    throw new RuntimeException("A senha atual não confere.");
                }
                if ($new !== $confirm) {
                    throw new RuntimeException(
                        "A confirmação da nova senha não confere.",
                    );
                }
                if (!password_ok($new)) {
                    throw new RuntimeException(
                        "A nova senha precisa ter entre 8 e 128 caracteres e não pode ser uma senha comum.",
                    );
                }
                if (password_verify($new, (string) $u["password_hash"])) {
                    throw new RuntimeException(
                        "A nova senha precisa ser diferente da senha atual.",
                    );
                }
                db_begin_transaction();
                q(
                    "UPDATE pi_users SET password_hash=?, updated_at=NOW() WHERE id=?",
                    [password_hash_secure($new), $uid],
                );
                user_auth_generation_rotate($uid);
                security_retire_persistent_devices_for_user($uid);
                audit("senha_redefinida", "usuario", $uid, [
                    "target_name" => (string) ($u["name"] ?? ""),
                    "audit_body" =>
                        "O próprio usuário alterou a senha; todas as sessões anteriores foram revogadas.",
                ]);
                db_commit();
                secure_session_destroy();
                header(
                    "Location: " . href("login", ["relogin" => "1"]),
                );
                exit();
            }
            if ($act === "profile_switch_environment") {
                $target = (string) ($_POST["environment"] ?? "");
                if ($target === "global") {
                    if ((int) ($u["is_global_admin"] ?? 0) !== 1) {
                        throw new RuntimeException(
                            "Ambiente indisponível para este usuário.",
                        );
                    }
                    redirect("global_reauth");
                }
                if (!preg_match('/^role:(\d+)$/', $target, $m)) {
                    throw new RuntimeException("Escolha um ambiente válido.");
                }
                $roleId = (int) $m[1];
                $link = one(
                    "SELECT ur.id,ur.clinic_id,ur.role_code,COALESCE(NULLIF(cr.label,''),ur.role_code) role_label FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id AND c.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE ur.id=? AND ur.user_id=? AND ur.active=1 LIMIT 1",
                    [$roleId, $uid],
                );
                if (!$link) {
                    throw new RuntimeException(
                        "Ambiente indisponível para este usuário.",
                    );
                }
                $cid = (int) $link["clinic_id"];
                $role = (string) $link["role_code"];
                $_SESSION["uid"] = $uid;
                $_SESSION["scope"] = "clinic";
                $_SESSION["uc_id"] = (int) $link["id"];
                $_SESSION["clinic_id"] = $cid;
                $_SESSION["role_code"] = $role;
                $_SESSION["effective_roles"] = [$role];
                audit("area_trabalho_alterada", "usuario", $uid, [
                    "clinic_id" => $cid,
                    "role_code" => $role,
                    "role_label" => role_label_for($role, $cid),
                    "audit_body" => "Ambiente alterado na página do usuário.",
                ]);
                flash(
                    "Ambiente alterado para " .
                        role_label_for($role, $cid) .
                        ".",
                );
                redirect("appointments");
            }
            throw new RuntimeException("Ação de perfil inválida.");
        } catch (Throwable $e) {
            if (function_exists("pdo") && pdo()->inTransaction()) {
                db_rollback();
            }
            error_log("[Prontoo profile] " . $e->getMessage());
            flash(
                app_public_error_message(
                    $e,
                    "Não foi possível alterar o perfil agora.",
                ),
                "bad",
            );
            redirect("profile");
        }
    }
    $backRoute = ($c["scope"] ?? "") === "global" ? "admin_painel" : "painel";
    $back =
        '<a class="ghost small" href="' .
        href($backRoute) .
        '">' .
        icon("arrow_back") .
        "<span>Voltar</span></a>";
    $name = (string) ($u["name"] ?? "");
    $email = (string) ($u["email"] ?? "");
    $cpfDigits = only_digits((string) ($u["cpf"] ?? ""));
    $cpf =
        $cpfDigits !== ""
            ? (strlen($cpfDigits) === 11
                ? preg_replace(
                    '/^(\d{3})(\d{3})(\d{3})(\d{2})$/',
                    '$1.$2.$3-$4',
                    $cpfDigits,
                )
                : $cpfDigits)
            : "";
    $birth = db_birth_date_input($u["birth_date"] ?? "");
    $dataForm =
        '<form method="post" class="compact account-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="profile_update_user">' .
        form_row(
            "Nome completo",
            input("name", "text", $name, 'required autocomplete="name"'),
        ) .
        '<div class="two immutable-account-fields">' .
        form_row(
            "CPF",
            input(
                "cpf_display",
                "text",
                $cpf,
                'disabled readonly aria-disabled="true" tabindex="-1"',
            ),
        ) .
        form_row(
            "Nascimento",
            input(
                "birth_date_display",
                "date",
                $birth,
                'disabled readonly aria-disabled="true" tabindex="-1"',
            ),
        ) .
        "</div>" .
        form_row(
            "E-mail",
            input("email", "email", $email, 'autocomplete="email"'),
        ) .
        '<div class="form-actions"><button type="submit" class="primary">' .
        icon("save") .
        "<span>Salvar</span></button></div></form>";
    $passwordForm =
        '<form method="post" class="compact account-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="profile_change_password">' .
        form_row(
            "Senha atual",
            input(
                "current_password",
                "password",
                "",
                'required autocomplete="current-password"',
            ),
        ) .
        '<div class="two">' .
        form_row(
            "Nova senha",
            input(
                "new_password",
                "password",
                "",
                'required minlength="8" maxlength="128" autocomplete="new-password" data-password-strength',
            ),
        ) .
        form_row(
            "Confirmar nova senha",
            input(
                "new_password_confirm",
                "password",
                "",
                'required minlength="8" maxlength="128" autocomplete="new-password"',
            ),
        ) .
        '</div><div class="form-actions"><button type="submit" class="primary">' .
        icon("key") .
        "<span>Alterar senha</span></button></div></form>";
    $envCards = "";
    $envActiveCards = "";
    $envOtherCards = "";
    $currentScope = (string) ($c["scope"] ?? "");
    $currentUc = (int) ($_SESSION["uc_id"] ?? 0);
    $envButton = function (
        string $value,
        string $iconName,
        string $title,
        string $subtitle,
        bool $active,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Auth/AuthOnboarding.php:1865
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de autenticação, sessão e entrada de usuários.
         * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `icon`, `csrf_field`, `e`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $state = $active
            ? '<span class="account-env-status">' .
                icon("check_circle") .
                "<span>Atual</span></span>"
            : '<span class="account-env-enter" aria-hidden="true">' .
                icon("login") .
                "</span>";
        return '<form method="post" class="account-env-item-form' .
            ($active ? " is-active" : "") .
            '">' .
            csrf_field() .
            '<input type="hidden" name="act" value="profile_switch_environment"><input type="hidden" name="environment" value="' .
            e($value) .
            '"><button type="submit" class="account-env-option' .
            ($active ? " active" : "") .
            '"' .
            ($active ? ' disabled aria-disabled="true"' : "") .
            '><span class="account-env-mark">' .
            icon($iconName) .
            '</span><span class="account-env-copy"><strong>' .
            e($title) .
            "</strong><small>" .
            e($subtitle) .
            "</small></span>" .
            $state .
            "</button></form>";
    };
    $envAppend = function (string $html, bool $active) use (
        &$envActiveCards,
        &$envOtherCards,
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Auth/AuthOnboarding.php:1899
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de autenticação, sessão e entrada de usuários.
         * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if ($active) {
            $envActiveCards .= $html;
        } else {
            $envOtherCards .= $html;
        }
    };
    if ((int) ($u["is_global_admin"] ?? 0) === 1) {
        $active = $currentScope === "global";
        $envAppend(
            $envButton(
                "global",
                "admin_panel_settings",
                "Desenvolvedor",
                "Painel do Desenvolvedor",
                $active,
            ),
            $active,
        );
    }
    $rows = q(
        "SELECT ur.id,ur.clinic_id,ur.role_code,c.display_name clinic_name,COALESCE(NULLIF(cr.label,''),ur.role_code) role_label,COALESCE(NULLIF(cr.icon_name,''),'workspaces') icon_name FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id AND c.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE ur.user_id=? AND ur.active=1 ORDER BY c.display_name ASC, ur.is_owner DESC, FIELD(ur.role_code,'gerente','medico','assistente','recepcionista'), ur.id ASC",
        [$uid],
    )->fetchAll();
    foreach ($rows as $r) {
        $rid = (int) $r["id"];
        $role = (string) $r["role_code"];
        $cid = (int) $r["clinic_id"];
        $label = (string) ($r["role_label"] ?: role_label_for($role, $cid));
        $clinic = (string) ($r["clinic_name"] ?? "Consultório");
        $active = $currentScope === "clinic" && $currentUc === $rid;
        $envAppend(
            $envButton(
                "role:" . $rid,
                (string) ($r["icon_name"] ?: role_icon($role, $cid)),
                $label,
                $clinic,
                $active,
            ),
            $active,
        );
    }
    $envCards = $envActiveCards . $envOtherCards;
    if ($envCards === "") {
        $envCards =
            '<div class="empty">Nenhum ambiente ativo disponível para este usuário.</div>';
    } else {
        $envCards = '<div class="account-env-grid">' . $envCards . "</div>";
    }
    $body =
        page_head("Minha conta", "", $back) .
        '<section class="account-profile-grid"><article class="card account-card" id="sobre-mim"><h2>' .
        icon("person") .
        '<span>Sobre Mim</span></h2><p class="muted-copy">Atualize os dados básicos vinculados ao seu acesso.</p>' .
        $dataForm .
        '</article><article class="card account-card" id="alteracao-de-senha"><h2>' .
        icon("key") .
        '<span>Alteração de Senha</span></h2><p class="muted-copy">Confirme a senha atual para cadastrar uma nova senha.</p>' .
        $passwordForm .
        '</article><article class="card account-card account-env-card" id="meus-ambientes"><div class="account-env-card-head"><span class="account-env-card-icon">' .
        icon("workspaces") .
        '</span><div><span class="eyebrow">Acesso</span><h2>Meus ambientes</h2><p class="muted-copy">Escolha em qual consultório ou área você deseja trabalhar nesta sessão.</p></div></div>' .
        $envCards .
        "</article></section>";
    page("Minha conta", $body);
}
function page_switch(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_switch
     * Responsabilidade: Coordena a rota e renderiza a tela “page switch”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `need_login`, `redirect`.
     * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    need_login();
    redirect("profile");
}
function page_onboarding(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_onboarding
     * Responsabilidade: Coordena a rota e renderiza a tela “page onboarding”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `need_login`, `is_responsible_doctor`, `redirect`, `function_exists`, `ensure_clinic_trial_active`, `one`, `seed_clinic_roles`, `array_fill_keys`, `array_keys`, `db_begin_transaction`, `strtoupper`, `trim` e mais 43.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Estado externo lido: `$_SERVER`, `$_POST`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; produz conteúdo de saída; gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     * Cuidado 3: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    $c = need_login();
    if (!is_responsible_doctor($c)) {
        redirect("appointments");
    }
    $cid = (int) $c["clinic_id"];
    if (function_exists("ensure_clinic_trial_active")) {
        ensure_clinic_trial_active($cid, true);
    }
    $cl = one(
        "SELECT id,display_name,legal_name,legal_document,phone,responsible_profession,clinic_icon,accent_color,address_line,address_state,address_city,address_city_ibge,timezone,onboarding_done FROM pi_clinics WHERE id=?",
        [$cid],
    );
    if (!$cl) {
        redirect("appointments");
    }
    seed_clinic_roles($cid);
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $rolesEnabled = array_fill_keys(array_keys(PRONTOO_ROLES), 0);
        foreach ((array) ($_POST["roles"] ?? []) as $r) {
            if (isset(PRONTOO_ROLES[$r])) {
                $rolesEnabled[$r] = 1;
            }
        }
        $rolesEnabled["medico"] = 1;
        $rolesEnabled["gerente"] = 1;
        db_begin_transaction();
        try {
            $uf = strtoupper(trim((string) ($_POST["address_state"] ?? "")));
            $city = trim((string) ($_POST["address_city"] ?? ""));
            $cityIbge = (int) ($_POST["address_city_ibge"] ?? 0);
            if (!isset(br_states()[$uf]) || $city === "" || $cityIbge <= 0) {
                throw new RuntimeException(
                    "Escolha uma cidade da lista do IBGE.",
                );
            }
            $tz = timezone_from_location($uf, $city);
            $profession = normalize_profession(
                (string) ($_POST["responsible_profession"] ?? ""),
            );
            $clinicIcon = normalize_clinic_icon(
                (string) ($_POST["clinic_icon"] ?? ($cl["clinic_icon"] ?? "")),
            );
            $accentColor = normalize_accent_color(
                (string) ($_POST["accent_color"] ??
                    ($cl["accent_color"] ?? "")),
            );
            $trialStart = time();
            $trialEnd = function_exists("subscription_trial_end_from_start")
                ? subscription_trial_end_from_start(
                    $trialStart,
                    default_trial_days(),
                )
                : $trialStart + max(1, default_trial_days()) * 86400;
            q(
                "UPDATE pi_clinics SET display_name=?, phone=?, responsible_profession=?, clinic_icon=?, accent_color=?, address_line=?, address_state=?, address_city=?, address_city_ibge=?, timezone=?, onboarding_done=1, onboarding_completed_at=NOW(), subscription_status='trial', trial_started_at=COALESCE(NULLIF(trial_started_at,0),?), trial_ends_at=IF(trial_ends_at IS NULL OR trial_ends_at=0 OR trial_ends_at<NOW(),?,trial_ends_at), paid_until=NULL, updated_at=NOW() WHERE id=?",
                [
                    trim((string) $_POST["display_name"]),
                    phone_br((string) $_POST["phone"]),
                    $profession,
                    $clinicIcon,
                    $accentColor,
                    trim((string) ($_POST["address_line"] ?? "")),
                    $uf,
                    $city,
                    $cityIbge,
                    $tz,
                    $trialStart,
                    $trialEnd,
                    $cid,
                ],
            );
            $i = 1;
            foreach (PRONTOO_ROLES as $role => $default) {
                $label =
                    $role === "medico"
                        ? $profession
                        : (trim(
                            (string) ($_POST["role_label"][$role] ?? $default),
                        ) ?:
                        $default);
                $ico = default_role_icon($role);
                q(
                    "INSERT INTO pi_clinic_roles (clinic_id,role_code,label,icon_name,enabled,sort_order) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label), icon_name=IF(icon_name='' OR (icon_name='support_agent' AND role_code<>'recepcionista'),VALUES(icon_name),icon_name), enabled=VALUES(enabled), sort_order=VALUES(sort_order)",
                    [$cid, $role, $label, $ico, $rolesEnabled[$role], $i++],
                );
            }
            $defaults = default_permissions();
            foreach (PRONTOO_ROLES as $role => $label) {
                foreach (actions() as $key => $a) {
                    $allow = 0;
                    if ($rolesEnabled[$role]) {
                        $allow = in_array($key, $defaults[$role] ?? [], true)
                            ? 1
                            : 0;
                    }
                    if ($role === "gerente" && $rolesEnabled[$role]) {
                        $allow = 1;
                    }
                    q(
                        "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)",
                        [$cid, $role, $key, $allow],
                    );
                }
            }
            $newUid = save_team_member($cid, $_POST);
            if ($newUid) {
                audit("usuario_salvo", "usuario", $newUid, [
                    "clinic_id" => $cid,
                    "origem" => "wizard",
                ]);
            }
            audit("onboarding_concluido", "consultorio", $cid, [
                "clinic_id" => $cid,
                "clinic_icon" => $clinicIcon,
                "accent_color" => $accentColor,
                "audit_body" =>
                    "Onboarding concluído com identidade visual inicial. Permissões permaneceram com padrão do sistema para ajuste posterior.",
            ]);
            db_commit();
            flash(
                "Configuração inicial concluída. Você pode ajustar equipe, permissões e identidade visual depois em Meu Consultório.",
            );
            redirect("appointments");
        } catch (Throwable $e) {
            if (pdo()->inTransaction()) {
                db_rollback();
            }
            error_log(
                "[Prontoo onboarding] " .
                    get_class($e) .
                    " | " .
                    $e->getMessage() .
                    " | " .
                    $e->getFile() .
                    ":" .
                    $e->getLine(),
            );
            $msg = $e instanceof RuntimeException ? trim($e->getMessage()) : "";
            flash(
                $msg !== ""
                    ? $msg
                    : "Não foi possível salvar a configuração inicial. Revise os campos e tente novamente.",
                "bad",
            );
            redirect("onboarding");
        }
    }
    $roles = clinic_roles($cid, false);
    $enabled = q(
        "SELECT role_code,enabled FROM pi_clinic_roles WHERE clinic_id=?",
        [$cid],
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $roleCards = "";
    foreach (PRONTOO_ROLES as $role => $default) {
        $locked = in_array($role, ["medico", "gerente"], true);
        $checked = $locked || (int) ($enabled[$role] ?? 0) ? "checked" : "";
        $disabled = $locked ? "disabled" : "";
        $hidden = $locked
            ? '<input type="hidden" name="roles[]" value="' . e($role) . '">'
            : "";
        $desc = match ($role) {
            "recepcionista"
                => "Atende pacientes, organiza contatos, confirma chegada e acompanha a agenda.",
            "assistente"
                => "Prepara informações, triagem, tarefas e apoio antes ou depois do atendimento.",
            "medico"
                => "Realiza o atendimento, registra o prontuário e emite documentos clínicos.",
            "gerente"
                => "Organiza equipe, procedimentos e configurações do consultório.",
            default => "",
        };
        $roleCards .=
            '<article class="wiz-role"><label class="check"><input type="checkbox" name="roles[]" value="' .
            e($role) .
            '" ' .
            $checked .
            " " .
            $disabled .
            ">" .
            $hidden .
            " Usar este departamento</label>" .
            form_row(
                "Nome visível",
                input(
                    "role_label[" . $role . "]",
                    "text",
                    $roles[$role] ?? $default,
                    "required",
                ),
            ) .
            "<p>" .
            e($desc) .
            "</p></article>";
    }
    $visual = clinic_visual_from_values(
        $cl["clinic_icon"] ?? null,
        $cl["accent_color"] ?? null,
        $cl["responsible_profession"] ?? null,
    );
    $visualStep =
        '<div class="clinic-visual-preview"><span class="brand-mark">' .
        icon($visual["icon"]) .
        "</span><div><strong>" .
        e($cl["display_name"] ?? "Consultório") .
        "</strong><small>Seus ambientes de trabalho terão as cores e ícones escolhidos.</small></div></div><h3>Escolha um ícone</h3>" .
        clinic_icon_picker($visual["icon"]) .
        "<h3>Escolha uma cor</h3>" .
        clinic_color_picker($visual["brand"]);
    $steps =
        '<div class="wizard-progress" aria-label="Etapas da configuração"><span class="is-active">1</span><span>2</span><span>3</span><span>4</span></div>';
    $form =
        '<section class="wizard onboarding-wizard" data-onboarding-wizard><div class="wizard-head"><span class="eyebrow">Seu consultório, suas regras</span><h1>Vamos deixar seu consultório com a sua cara</h1><p>Informe os dados abaixo. Se precisar, ajustes podem ser feitos mais tarde.</p></div><form method="post" class="compact onboarding-form clinic-settings-form">' .
        csrf_field() .
        $steps .
        '<section class="wizard-step is-active" data-wizard-step="0"><div class="wizard-step-title"><span>' .
        icon("home_health") .
        '</span><div><h2>Fale sobre o Consultório</h2><p>Confirme o nome, telefone, profissão e cidade de atuação.</p></div></div><div class="two">' .
        form_row(
            "Nome do consultório",
            input("display_name", "text", $cl["display_name"], "required"),
        ) .
        form_row(
            "Telefone principal",
            input("phone", "text", $cl["phone"] ?? ""),
        ) .
        "</div>" .
        profession_select_fields($cl) .
        clinic_location_fields($cl) .
        '</section><section class="wizard-step" data-wizard-step="1" hidden><div class="wizard-step-title"><span>' .
        icon("badge") .
        '</span><div><h2>Seus departamentos</h2><p>Os departamentos permitem que você distribua as tarefas por cargos.</p></div></div><div class="wizard-roles">' .
        $roleCards .
        '</div></section><section class="wizard-step" data-wizard-step="2" hidden><div class="wizard-step-title"><span>' .
        icon("person_add") .
        "</span><div><h2>Primeiro membro da equipe</h2><p>Você foi configurado como Administrativo e <span data-onboarding-profession>" .
        e(
            normalize_profession(
                (string) ($cl["responsible_profession"] ?? "Profissional"),
            ),
        ) .
        '</span> do Consultório. Cadastre outros membros da equipe agora ou clique em Avançar para fazer isto mais tarde.</p></div></div><div class="two">' .
        form_row("Nome completo", input("team_name", "text")) .
        form_row(
            "CPF",
            input("team_cpf", "text", "", 'inputmode="numeric" maxlength="14"'),
        ) .
        '</div><div class="two">' .
        form_row("Nascimento", input("team_birth", "date")) .
        form_row("E-mail", input("team_email", "email")) .
        "</div>" .
        form_row(
            "Cargos",
            role_checkbox_group("team_roles", manageable_team_roles($cid), [
                "recepcionista",
            ]),
        ) .
        '<div class="two">' .
        form_row(
            "Senha inicial",
            input(
                "team_password",
                "password",
                "",
                'minlength="8" maxlength="128" autocomplete="new-password" data-password-strength',
            ),
        ) .
        '</div></section><section class="wizard-step" data-wizard-step="3" hidden><div class="wizard-step-title"><span>' .
        icon("palette") .
        "</span><div><h2>Aparência</h2><p>Escolha o ícone e a cor que melhor representam o seu consultório.</p></div></div>" .
        $visualStep .
        '</section><div class="wizard-nav"><button type="button" class="ghost" data-wizard-prev hidden>Voltar</button><button type="button" class="primary" data-wizard-next>Avançar</button><button type="submit" class="primary" data-wizard-submit hidden>Concluir configuração</button></div></form></section>';
    page("Seu consultório, suas regras", $form);
}
function person_autosuggest_datalist(
    int $cid,
    string $id = "prontoo_person_suggestions",
): string {
    /*
     * GUIA DE MANUTENÇÃO — person_autosuggest_datalist
     * Responsabilidade: Implementa a responsabilidade “person autosuggest datalist” dentro do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `page_leads`, `page_patients`, `page_users`.
     * Dependências chamadas: `q`, `->fetchAll`, `e`, `trim`, `mask`, `date_br`, `db_birth_date_input`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $rows = q(
        "SELECT DISTINCT p.id,p.full_name,p.cpf,p.birth_date FROM pi_persons p JOIN (SELECT person_id FROM pi_patients WHERE clinic_id=? AND person_id IS NOT NULL UNION SELECT person_id FROM pi_leads WHERE clinic_id=? AND person_id IS NOT NULL) x ON x.person_id=p.id WHERE p.full_name<>'' ORDER BY p.full_name ASC LIMIT 500",
        [$cid, $cid],
    )->fetchAll();
    $h = '<datalist id="' . e($id) . '">';
    foreach ($rows as $r) {
        $label = trim(
            (!empty($r["cpf"])
                ? "CPF " . mask((string) $r["cpf"])
                : "CPF não informado") .
                " · " .
                (!empty($r["birth_date"])
                    ? "Nascimento " . date_br((string) $r["birth_date"])
                    : "nascimento não informado"),
            " ·",
        );
        $h .=
            '<option value="' .
            e((string) $r["full_name"]) .
            '" label="' .
            e($label) .
            '" data-cpf="' .
            e(mask((string) ($r["cpf"] ?? ""))) .
            '" data-birth="' .
            e(db_birth_date_input($r["birth_date"] ?? "")) .
            '"></option>';
    }
    return $h . "</datalist>";
}
function save_person_by_document(
    string $name,
    string $doc,
    ?string $birth = null,
): int {
    /*
     * GUIA DE MANUTENÇÃO — save_person_by_document
     * Responsabilidade: Valida e executa a mutação “save person by document”, preservando as invariantes do módulo de autenticação, sessão e entrada de usuários.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: `financial_counterparty_light`, `financial_creditor_upsert_from_post`.
     * Dependências chamadas: `trim`, `only_digits`, `RuntimeException`, `strlen`, `save_person_flexible`, `valid_cnpj`, `val`, `q`, `db_last_insert_id`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    $name = trim($name);
    $doc = only_digits($doc);
    if ($name === "") {
        throw new RuntimeException("Nome da pessoa não informado.");
    }
    if (strlen($doc) === 11) {
        return save_person_flexible($name, $doc, $birth);
    }
    if (strlen($doc) === 14) {
        if (!valid_cnpj($doc)) {
            throw new RuntimeException("Informe CNPJ válido.");
        }
        $id = val("SELECT id FROM pi_persons WHERE legal_document=? LIMIT 1", [
            $doc,
        ]);
        if ($id) {
            q(
                "UPDATE pi_persons SET full_name=COALESCE(NULLIF(full_name,''),?), updated_at=NOW() WHERE id=?",
                [$name, $id],
            );
            return (int) $id;
        }
        q(
            "INSERT INTO pi_persons (full_name,cpf,birth_date,legal_document,created_at) VALUES (?,NULL,NULL,?,NOW())",
            [$name, $doc],
        );
        return db_last_insert_id();
    }
    throw new RuntimeException("Informe CPF ou CNPJ válido.");
}
