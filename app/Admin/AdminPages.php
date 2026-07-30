<?php
declare(strict_types=1);
function platform_storage_status(): array
{

    $dir = storage_path();
    $ok = is_dir($dir) && is_writable($dir);
    $free = function_exists("disk_free_space") ? @disk_free_space($dir) : false;
    return [
        "ok" => $ok,
        "free_bytes" => $free === false ? null : (float) $free,
    ];
}
function admin_scope_guard_definition(string $key): array
{

    $definitions = [
        "write_in_read_only" => [
            "tier" => "policy",
            "icon" => "lock_clock",
            "label" => "Escrita bloqueada pela assinatura",
            "cause" =>
                "O consultório estava em Somente Leitura e a ação não integra a lista mínima permitida.",
            "risk" =>
                "É um bloqueio de política comercial, não uma evidência de tentativa de cruzar consultórios.",
            "detection" =>
                "A política de assinatura recusou a escrita antes da preparação do SQL.",
            "next" =>
                "Verifique a situação da assinatura ou a lista de ações permitidas em Somente Leitura.",
        ],
        "write_without_clinic_scope" => [
            "tier" => "objective",
            "icon" => "shield_lock",
            "label" => "Escrita sem escopo explícito",
            "cause" =>
                "A escrita mencionou uma tabela operacional sem demonstrar a coluna clinic_id exigida.",
            "risk" =>
                "Sem o invariante de consultório, a instrução poderia alcançar linhas fora do contexto ativo.",
            "detection" =>
                "O guardião reconheceu a tabela como isolada e não encontrou uma prova de escopo válida.",
            "next" =>
                "Inclua clinic_id e vincule seu valor ao consultório da sessão.",
        ],
        "write_without_where" => [
            "tier" => "objective",
            "icon" => "gpp_bad",
            "label" => "UPDATE/DELETE sem WHERE",
            "cause" =>
                "Uma instrução de alteração ou exclusão não continha cláusula WHERE de nível principal.",
            "risk" =>
                "A ausência de filtro permitiria atingir todas as linhas da tabela operacional.",
            "detection" =>
                "A estrutura do SQL foi analisada antes do PDO e não apresentou WHERE aplicável.",
            "next" =>
                "Limite a instrução por identificador e clinic_id do consultório ativo.",
        ],
        "write_without_clinic_where" => [
            "tier" => "objective",
            "icon" => "gpp_bad",
            "label" => "WHERE sem clinic_id",
            "cause" =>
                "O UPDATE/DELETE tinha WHERE, mas o filtro não continha clinic_id.",
            "risk" =>
                "Um identificador global ou reutilizado não constitui, sozinho, uma fronteira entre consultórios.",
            "detection" =>
                "O analisador isolou o WHERE principal e não encontrou a coluna de escopo.",
            "next" =>
                "Adicione clinic_id=? ao predicado e use o valor da sessão ativa.",
        ],
        "write_changes_clinic_scope" => [
            "tier" => "objective",
            "icon" => "move_down",
            "label" => "Alteração de clinic_id bloqueada",
            "cause" =>
                "O UPDATE tentou alterar a coluna que define o proprietário do registro.",
            "risk" =>
                "Mover uma linha por UPDATE poderia transferir sua visibilidade para outro consultório.",
            "detection" =>
                "A lista SET foi separada do WHERE e continha atribuição direta a clinic_id.",
            "next" =>
                "Não altere clinic_id em rotinas clínicas; trate eventual migração em processo administrativo dedicado e auditado.",
        ],
        "write_mismatched_clinic_where" => [
            "tier" => "objective",
            "icon" => "domain_disabled",
            "label" => "clinic_id divergente no WHERE",
            "cause" =>
                "O valor demonstrável do clinic_id no filtro era diferente do consultório da sessão.",
            "risk" =>
                "Se executada, a instrução teria como alvo explícito outro escopo de consultório.",
            "detection" =>
                "O valor literal ou parâmetro posicional foi comparado ao clinic_id da sessão antes do SQL.",
            "next" =>
                "Rastreie a origem do parâmetro e derive o escopo somente da sessão validada.",
        ],
        "write_unproved_clinic_where" => [
            "tier" => "review",
            "icon" => "rule",
            "label" => "Prova do WHERE insuficiente",
            "cause" =>
                "O analisador não conseguiu demonstrar que todos os ramos booleanos do WHERE exigem o consultório ativo.",
            "risk" =>
                "Pode ser um formato SQL complexo legítimo ou um ramo com OR que escape do escopo; o registro, sozinho, não distingue os dois casos.",
            "detection" =>
                "A prova conservadora exige clinic_id ativo em toda alternativa lógica alcançável.",
            "next" =>
                "Simplifique o predicado ou repita a condição de clinic_id em todos os ramos do OR.",
        ],
        "insert_without_clinic_column" => [
            "tier" => "objective",
            "icon" => "playlist_remove",
            "label" => "INSERT sem coluna clinic_id",
            "cause" =>
                "A lista de colunas da nova linha não continha clinic_id.",
            "risk" =>
                "A linha poderia ficar sem proprietário verificável ou depender de comportamento implícito.",
            "detection" =>
                "As colunas do INSERT/REPLACE foram analisadas antes da execução.",
            "next" =>
                "Grave clinic_id explicitamente com o valor da sessão.",
        ],
        "insert_mismatched_clinic_value" => [
            "tier" => "objective",
            "icon" => "domain_disabled",
            "label" => "clinic_id divergente no INSERT",
            "cause" =>
                "Ao menos uma linha do INSERT/REPLACE recebeu clinic_id diferente do consultório ativo.",
            "risk" =>
                "A nova linha seria criada diretamente no escopo de outro consultório.",
            "detection" =>
                "Todas as tuplas VALUES foram avaliadas, inclusive inserções em lote.",
            "next" =>
                "Use o clinic_id da sessão em todas as linhas do lote.",
        ],
        "insert_unproved_clinic_value" => [
            "tier" => "review",
            "icon" => "rule",
            "label" => "Prova do INSERT insuficiente",
            "cause" =>
                "O formato do INSERT/REPLACE não permitiu provar que todas as linhas usam o consultório ativo.",
            "risk" =>
                "Pode ser incompatibilidade do formato SQL, parâmetro ausente ou atribuição não demonstrável; não confirma travessia.",
            "detection" =>
                "A prova verifica cada tupla VALUES e a eventual atualização de clinic_id no ON DUPLICATE KEY.",
            "next" =>
                "Use lista explícita de colunas e VALUES posicionais demonstráveis.",
        ],
        "entity_outside_clinic" => [
            "tier" => "objective",
            "icon" => "block",
            "label" => "Objeto fora do escopo ativo",
            "cause" =>
                "O identificador solicitado não foi encontrado dentro do consultório ativo.",
            "risk" =>
                "O identificador pode estar incorreto, removido ou pertencer a outro contexto; nenhum dado externo foi devolvido.",
            "detection" =>
                "A busca obrigatória combinou id e clinic_id e falhou fechada com HTTP 403.",
            "next" =>
                "Revise a origem do identificador e descarte links ou formulários desatualizados.",
        ],
        "user_outside_clinic" => [
            "tier" => "objective",
            "icon" => "person_off",
            "label" => "Colaborador fora do escopo ativo",
            "cause" =>
                "O colaborador informado não possui vínculo válido com o consultório ativo.",
            "risk" =>
                "Aceitar o vínculo permitiria associar uma ação clínica a uma identidade de outro contexto.",
            "detection" =>
                "A associação usuário-consultório foi validada antes da operação e falhou fechada.",
            "next" =>
                "Atualize a seleção de colaboradores e confirme o vínculo ativo antes de reenviar.",
        ],
    ];
    return $definitions[$key] ?? [
        "tier" => "review",
        "icon" => "policy",
        "label" => "Evento de escopo não classificado",
        "cause" =>
            "A versão atual ainda não possui uma explicação específica para esta chave de proteção.",
        "risk" =>
            "O evento foi bloqueado, mas precisa de revisão de código antes de receber uma conclusão.",
        "detection" => "O guardião registrou a chave técnica " . $key . ".",
        "next" => "Classifique a nova chave e revise o fingerprint correspondente.",
    ];
}
function admin_scope_guard_stats(int $hours = 24): array
{

    $hours = max(1, min(24 * 30, $hours));
    $objectiveKeys = [
        "write_without_clinic_scope",
        "write_without_where",
        "write_without_clinic_where",
        "write_changes_clinic_scope",
        "write_mismatched_clinic_where",
        "insert_without_clinic_column",
        "insert_mismatched_clinic_value",
        "entity_outside_clinic",
        "user_outside_clinic",
    ];
    $quoted = implode(
        ",",
        array_map(static  fn($key) => "'" . $key . "'", $objectiveKeys),
    );
    $modelWhere = admin_model_clinic_exclude_sql("sv.clinic_id");
    try {
        $row = one(
            "SELECT COUNT(*) AS total," .
                "SUM(sv.violation_key='write_in_read_only') AS policy_total," .
                "SUM(sv.violation_key<>'write_in_read_only') AS actionable_total," .
                "SUM(sv.violation_key IN ($quoted)) AS objective_total," .
                "COUNT(DISTINCT CASE WHEN sv.violation_key<>'write_in_read_only' THEN CONCAT_WS('|',sv.violation_key,sv.sql_fingerprint,sv.route,sv.clinic_id) END) AS actionable_patterns " .
                "FROM pi_scope_violations sv WHERE sv.created_at>=DATE_SUB(NOW(), INTERVAL $hours HOUR) $modelWhere",
        ) ?: [];
    } catch (Throwable $e) {
        return [
            "total" => 0,
            "policy" => 0,
            "actionable" => 0,
            "objective" => 0,
            "review" => 0,
            "patterns" => 0,
        ];
    }
    $actionable = (int) ($row["actionable_total"] ?? 0);
    $objective = min($actionable, (int) ($row["objective_total"] ?? 0));
    return [
        "total" => (int) ($row["total"] ?? 0),
        "policy" => (int) ($row["policy_total"] ?? 0),
        "actionable" => $actionable,
        "objective" => $objective,
        "review" => max(0, $actionable - $objective),
        "patterns" => (int) ($row["actionable_patterns"] ?? 0),
    ];
}
function admin_scope_guard_groups(
    int $hours = 24,
    int $limit = 30,
    bool $includePolicy = false,
): array {

    $hours = max(1, min(24 * 30, $hours));
    $limit = max(1, min(80, $limit));
    $modelWhere = admin_model_clinic_exclude_sql("sv.clinic_id");
    $policyWhere = $includePolicy
        ? ""
        : " AND sv.violation_key<>'write_in_read_only'";
    try {
        return q(
            "SELECT sv.violation_key,sv.sql_fingerprint,sv.route,sv.clinic_id,sv.user_id,sv.role_code,sv.details,c.display_name AS clinic_name,u.name AS user_name,COUNT(*) AS occurrences,MIN(sv.created_at) AS first_at,MAX(sv.created_at) AS last_at " .
                "FROM pi_scope_violations sv " .
                "LEFT JOIN pi_clinics c ON c.id=sv.clinic_id " .
                "LEFT JOIN pi_users u ON u.id=sv.user_id " .
                "WHERE sv.created_at>=DATE_SUB(NOW(), INTERVAL $hours HOUR)$policyWhere $modelWhere " .
                "GROUP BY sv.violation_key,sv.sql_fingerprint,sv.route,sv.clinic_id,sv.user_id,sv.role_code,sv.details,c.display_name,u.name " .
                "ORDER BY last_at DESC LIMIT $limit",
        )->fetchAll();
    } catch (Throwable $e) {
        error_log("[Prontoo scope evidence] " . $e->getMessage());
        return [];
    }
}
function admin_scope_evidence_html(array $row, bool $compact = false): string
{

    $key = (string) ($row["violation_key"] ?? "");
    $definition = admin_scope_guard_definition($key);
    $payload = function_exists("scope_violation_detail_decode")
        ? scope_violation_detail_decode($row["details"] ?? "")
        : ["reason" => (string) ($row["details"] ?? "")];
    $clinic = trim((string) ($row["clinic_name"] ?? ""));
    if ($clinic === "") {
        $clinic = "Consultório #" . (int) ($row["clinic_id"] ?? 0);
    }
    $actor = trim((string) ($row["user_name"] ?? ""));
    $actor = $actor !== "" ? first_name($actor) : "usuário não identificado";
    $context = array_values(
        array_filter([
            trim((string) ($payload["method"] ?? "")),
            trim((string) ($row["route"] ?? "")),
            trim((string) ($payload["action"] ?? "")),
            trim((string) ($payload["operation"] ?? "")),
            trim((string) ($payload["table"] ?? "")),
        ]),
    );
    $fingerprint = substr((string) ($row["sql_fingerprint"] ?? ""), 0, 16);
    $shape = trim((string) ($payload["sql_shape"] ?? ""));
    $recordedReason = trim((string) ($payload["reason"] ?? ""));
    $html =
        '<details class="scope-evidence"><summary>' .
        ($compact ? "Como e por que foi bloqueado" : "Ver prova técnica e contexto seguro") .
        '</summary><div class="scope-evidence-grid"><div><b>Como foi detectado</b><span>' .
        e((string) $definition["detection"]) .
        '</span></div><div><b>Por que exige atenção</b><span>' .
        e((string) $definition["risk"]) .
        '</span></div><div><b>Resultado observado</b><span>Bloqueado antes da execução SQL; este registro não confirma acesso cruzado.</span></div><div><b>Próximo passo</b><span>' .
        e((string) $definition["next"]) .
        "</span></div></div>";
    if ($recordedReason !== "") {
        $html .= '<p class="scope-recorded-reason"><b>Motivo registrado:</b> ' .
            e($recordedReason) .
            "</p>";
    }
    $html .=
        '<div class="scope-evidence-meta"><span>' .
        e($clinic) .
        '</span><span>' .
        e($actor . " · " . ((string) ($row["role_code"] ?? "cargo não informado"))) .
        '</span><span>' .
        e($context ? implode(" · ", $context) : "contexto legado reduzido") .
        '</span><span>Fingerprint ' .
        e($fingerprint !== "" ? $fingerprint : "indisponível") .
        '</span><span>' .
        (int) ($row["occurrences"] ?? 1) .
        " ocorrência(s)</span></div>";
    if (!$compact && $shape !== "") {
        $html .=
            '<div class="scope-sql-shape"><b>Forma sanitizada do SQL</b><code>' .
            e($shape) .
            "</code></div>";
    }
    return $html . "</details>";
}
function admin_scope_guard_timeline_item(array $row): array
{

    $definition = admin_scope_guard_definition(
        (string) ($row["violation_key"] ?? ""),
    );
    $clinic = trim((string) ($row["clinic_name"] ?? ""));
    if ($clinic === "") {
        $clinic = "Consultório #" . (int) ($row["clinic_id"] ?? 0);
    }
    $occurrences = max(1, (int) ($row["occurrences"] ?? 1));
    $routeName = trim((string) ($row["route"] ?? ""));
    return [
        "icon" => (string) $definition["icon"],
        "time" => dt_br((string) ($row["last_at"] ?? "")),
        "title" =>
            (string) $definition["label"] .
            ($routeName !== "" ? " · " . $routeName : ""),
        "body" =>
            (string) $definition["cause"] .
            " A operação foi interrompida antes da execução SQL.",
        "meta" =>
            $clinic .
            " · " .
            $occurrences .
            " ocorrência(s) entre " .
            dt_br((string) ($row["first_at"] ?? "")) .
            " e " .
            dt_br((string) ($row["last_at"] ?? "")),
        "html" => admin_scope_evidence_html($row),
        "class" => "scope-event scope-" . (string) $definition["tier"],
    ];
}
function platform_backend_selftest(array $preloaded = []): array
{

    $checks = [];
    $ok = true;
    try {
        $dbOk = (string) val("SELECT 1") === "1";
    } catch (Throwable $e) {
        $dbOk = false;
    }
    $checks["database"] = $dbOk;
    $ok = $ok && $dbOk;
    $storage = platform_storage_status();
    $checks["storage"] = (bool) $storage["ok"];
    $checks["storage_free_bytes"] = $storage["free_bytes"];
    $ok = $ok && (bool) $storage["ok"];
    $scopeModelWhere = admin_model_clinic_exclude_sql("clinic_id");
    $auditModelWhere = admin_model_clinic_exclude_where("a.clinic_id");
    $checks["open_errors"] = array_key_exists("open_errors", $preloaded)
        ? (int) $preloaded["open_errors"]
        : (int) cached_val(
            "platform_selftest_open_errors",
            45,
            "SELECT COUNT(*) FROM pi_error_events WHERE resolved_at IS NULL",
        );
    $checks["login_locks"] = array_key_exists("login_locks", $preloaded)
        ? (int) $preloaded["login_locks"]
        : (int) cached_val(
            "platform_selftest_login_locks",
            45,
            "SELECT COUNT(*) FROM pi_login_locks WHERE locked_until>NOW()",
        );
    $checks["scope_alerts_24h"] = array_key_exists("scope_alerts_24h", $preloaded)
        ? (int) $preloaded["scope_alerts_24h"]
        : (int) cached_val(
            "platform_selftest_scope_actionable_24h_v2_" . admin_model_clinic_id(),
            45,
            "SELECT COUNT(*) FROM pi_scope_violations WHERE created_at>=DATE_SUB(NOW(), INTERVAL 24 HOUR) AND violation_key<>'write_in_read_only' $scopeModelWhere",
        );
    $scopeLogic = class_exists("\\Prontoo\\Core\\Database\\SqlScopeGuard")
        ? \Prontoo\Core\Database\SqlScopeGuard::logicSelfTest()
        : ["ok" => false, "passed" => 0, "total" => 0, "failed" => ["class_missing"]];
    $checks["scope_guard_logic"] = $scopeLogic;
    $ok = $ok && !empty($scopeLogic["ok"]);
    $scopeContext = function_exists("scope_guard_context_selftest")
        ? scope_guard_context_selftest()
        : ["ok" => false, "passed" => 0, "total" => 0, "failed" => ["function_missing"]];
    $checks["scope_guard_context"] = $scopeContext;
    $ok = $ok && !empty($scopeContext["ok"]);
    $checks["integrity_alerts"] = (int) cache_remember(
        "platform_selftest_integrity_alerts_" . admin_model_clinic_id(),
        60,
        static function () use ($auditModelWhere): int {

            $alerts = 0;
            try {
                foreach (audit_rows_light($auditModelWhere, [], 50) as $row) {
                    if (!verify_audit_row($row)) {
                        $alerts++;
                    }
                }
            } catch (Throwable $e) {
                return 0;
            }
            return $alerts;
        },
    );
    $checks["audit_chain"] = function_exists("audit_chain_integrity_status")
        ? audit_chain_integrity_status(240)
        : ["ok" => false, "sequence_ok" => false, "head_ok" => false, "checked" => 0];
    $ok = $ok && !empty($checks["audit_chain"]["ok"]);
    $versionContract = function_exists("prontoo_version_contract_status")
        ? prontoo_version_contract_status()
        : ["ok" => true, "version" => PRONTOO_VERSION, "issues" => []];
    $checks["version"] = (string) ($versionContract["version"] ?? PRONTOO_VERSION);
    $checks["version_contract"] = $versionContract;
    $ok = $ok && !empty($versionContract["ok"]);
    $checks["ok"] = $ok;
    return $checks;
}
function platform_autotest_actions(array $checks): array
{

    $actions = [];
    if (empty($checks["database"])) {
        $actions[] = "Banco de dados indisponível no autoteste do login.";
    }
    if (empty($checks["storage"])) {
        $actions[] = "Diretório persistente /ssd sem permissão de escrita.";
    }
    if ((int) ($checks["open_errors"] ?? 0) > 0) {
        $actions[] =
            (int) $checks["open_errors"] .
            " incidente(s) técnico(s) aberto(s).";
    }
    if ((int) ($checks["login_locks"] ?? 0) > 0) {
        $actions[] =
            (int) $checks["login_locks"] . " bloqueio(s) de login ativo(s).";
    }
    if ((int) ($checks["scope_alerts_24h"] ?? 0) > 0) {
        $actions[] =
            (int) $checks["scope_alerts_24h"] .
            " operação(ões) de escopo bloqueada(s) nas últimas 24 horas; nenhum acesso cruzado é confirmado por essa contagem.";
    }
    $scopeLogic = isset($checks["scope_guard_logic"]) && is_array($checks["scope_guard_logic"])
        ? $checks["scope_guard_logic"]
        : [];
    if (!$scopeLogic || empty($scopeLogic["ok"])) {
        $actions[] = "A prova lógica interna do guardião de escopo não concluiu todos os casos de segurança.";
    }
    $scopeContext = isset($checks["scope_guard_context"]) && is_array($checks["scope_guard_context"])
        ? $checks["scope_guard_context"]
        : [];
    if (!$scopeContext || empty($scopeContext["ok"])) {
        $actions[] = "O contrato determinístico entre contexto de sistema e consultório não concluiu todos os casos de segurança.";
    }
    if ((int) ($checks["integrity_alerts"] ?? 0) > 0) {
        $actions[] =
            (int) $checks["integrity_alerts"] .
            " registro(s) recente(s) com integridade inválida.";
    }
    $versionContract = isset($checks["version_contract"]) && is_array($checks["version_contract"])
        ? $checks["version_contract"]
        : [];
    if ($versionContract && empty($versionContract["ok"])) {
        $actions[] = "Divergência no contrato de versão: " .
            implode(", ", array_map("strval", (array) ($versionContract["issues"] ?? []))) .
            ".";
    }
    return $actions;
}
function platform_login_loaded_audit(
    array $checks,
    bool $autoLogin = false,
): void {

    try {
        $bucket = security_client_bucket("login_loaded_audit");
        if (!security_rate_limit($bucket, 1, 300)) {
            audit("login_carregado", "login", null, [
                "autoteste_ok" => !empty($checks["ok"]),
                "auto_login" => $autoLogin ? 1 : 0,
                "audit_body" =>
                    "Página de login carregada com a inicialização concluída.",
            ]);
        }
        $actions = platform_autotest_actions($checks);
        if (
            $actions &&
            !security_rate_limit(
                security_client_bucket("login_autotest_alert"),
                1,
                300,
            )
        ) {
            audit("autoteste_aviso", "plataforma", null, [
                "acoes_recomendadas" => $actions,
                "audit_body" =>
                    "A inicialização encontrou sinais que devem aparecer como ações recomendadas para o Desenvolvedor.",
            ]);
        }
    } catch (Throwable $e) {
        error_log("[Prontoo login autotest audit] " . $e->getMessage());
    }
}
function admin_nav_parent(string $route): string
{

    return match ($route) {
        "admin_stats", "admin_operations" => "admin_painel",
        "admin_onboarding",
        "admin_users",
        "admin_people",
        "admin_payment_proof"
            => "admin_clinics",
        "admin_errors",
        "admin_diagnostics",
        "admin_integrity",
        "admin_security",
        "admin_audit"
            => "admin_health",
        "admin_global_notices",
        "admin_deleted",
        "admin_settings"
            => "admin_maintenance",
        default => $route,
    };
}
function stat_link_card(
    string $label,
    mixed $value,
    string $iconName,
    string $note = "",
    string $route = "",
): string {

    $inner =
        icon($iconName) .
        "<div><b>" .
        n($value) .
        "</b><span>" .
        e($label) .
        "</span>" .
        ($note ? "<small>" . e($note) . "</small>" : "") .
        "</div>";
    return $route !== ""
        ? '<a class="stat-card stat-link" href="' .
                href($route) .
                '">' .
                $inner .
                "</a>"
        : '<article class="stat-card">' . $inner . "</article>";
}
if (!function_exists("admin_choice_card")) {
    function admin_choice_card(): string
    {

        return '<button class="clinic-choice credential-choice admin-choice" type="submit" name="act" value="choose_admin"><span class="credential-icon app-brandmark-inline" data-app-brandmark><img class="auth-brandmark-favicon app-brandmark-img" src="/public/assets/app-icon-' .
            e(PRONTOO_ASSET_REV) .
            '.png" alt="" aria-hidden="true"></span><span class="credential-main"><span class="credential-role">Desenvolvedor</span><span class="credential-context"><span>Desenvolvedor Prontoo</span><small>Gerenciamento técnico da plataforma</small></span></span><span class="credential-enter">' .
            icon("login") .
            "</span></button>";
    }
}
function admin_global_timezone_options(): array
{

    $priority = [
        "America/Cuiaba" => "Cuiabá / Mato Grosso",
        "America/Sao_Paulo" => "Brasília / São Paulo",
        "America/Campo_Grande" => "Campo Grande",
        "America/Manaus" => "Manaus",
        "America/Porto_Velho" => "Porto Velho",
        "America/Boa_Vista" => "Boa Vista",
        "America/Rio_Branco" => "Rio Branco",
        "America/Eirunepe" => "Eirunepé",
        "America/Noronha" => "Fernando de Noronha",
    ];
    $out = [];
    foreach ($priority as $tz => $label) {
        if (in_array($tz, timezone_identifiers_list(), true)) {
            $out[$tz] = $label . " — " . $tz;
        }
    }
    foreach (timezone_identifiers_list() as $tz) {
        if (!isset($out[$tz]) && str_starts_with($tz, "America/")) {
            $out[$tz] = $tz;
        }
    }
    return $out;
}
function admin_quick_links(): string
{

    $links = [
        [
            "admin_painel",
            "Desenvolvedor",
            "Observação e ação técnica sobre a plataforma.",
            "space_dashboard",
        ],
        [
            "admin_clinics",
            "Consultórios",
            "Clientes, assinatura, status e operação.",
            "home_health",
        ],
        [
            "admin_health",
            "Incidentes",
            "Erros abertos, integridade, segurança e operação.",
            "crisis_alert",
        ],
        [
            "admin_performance",
            "Performance",
            "Tempo médio por rota nas últimas 24 horas.",
            "speed",
        ],
        [
            "admin_alerts",
            "Avisos",
            "Mensagens restritas ao Desenvolvedor Prontoo.",
            "campaign",
        ],
        [
            "admin_maintenance",
            "Manutenção",
            "Manutenção programada e parâmetros globais da plataforma.",
            "construction",
        ],
    ];
    $h = '<div class="admin-grid admin-grid-context">';
    foreach ($links as $l) {
        $h .=
            '<a class="admin-tile" href="' .
            href($l[0]) .
            '">' .
            icon($l[3]) .
            "<b>" .
            e($l[1]) .
            "</b><span>" .
            e($l[2]) .
            "</span></a>";
    }
    return $h . "</div>";
}
function human_bytes(float $bytes): string
{

    $u = ["B", "KB", "MB", "GB", "TB"];
    $i = 0;
    while ($bytes >= 1024 && $i < count($u) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, $i ? 1 : 0, ",", ".") . " " . $u[$i];
}
function bool_status(
    bool $ok,
    string $okTxt = "OK",
    string $badTxt = "Atenção",
): string {

    return $ok ? $okTxt : $badTxt;
}
function admin_global_compact_pill(
    string $label,
    string $value,
    string $iconName,
    string $note = "",
    string $route = "",
): string {

    $inner =
        icon($iconName) .
        "<b>" .
        e($value) .
        "</b><span>" .
        e($label) .
        "</span>" .
        ($note !== "" ? "<small>" . e($note) . "</small>" : "");
    return $route !== ""
        ? '<a class="global-compact-pill" href="' .
                href($route) .
                '">' .
                $inner .
                "</a>"
        : '<span class="global-compact-pill">' . $inner . "</span>";
}
function admin_global_ops_finance_html(): string
{

    $qInt = function (string $sql, array $p = []): int {

        return (int) safe_val($sql, $p, 0);
    };
    $qCents = function (string $sql, array $p = []): int {

        return (int) safe_val($sql, $p, 0);
    };
    $since = "DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $modelClinicWhere = admin_model_clinic_exclude_sql("id");
    $modelScopedWhere = admin_model_clinic_exclude_sql("clinic_id");
    $activeClinics = $qInt(
        "SELECT COUNT(DISTINCT clinic_id) FROM pi_audit WHERE clinic_id IS NOT NULL AND created_at>=$since $modelScopedWhere",
    );
    if ($activeClinics <= 0) {
        $activeClinics = $qInt(
            "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND (created_at>=$since OR updated_at>=$since OR trial_started_at>=$since OR paid_until>=CURDATE()) $modelClinicWhere",
        );
    }
    $newClinics = $qInt(
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND created_at>=$since $modelClinicWhere",
    );
    $exemptClinics = $qInt(
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status='exempt' $modelClinicWhere",
    );
    $readOnly = $qInt(
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND (subscription_status='read_only' OR (paid_until IS NOT NULL AND paid_until<CURDATE())) $modelClinicWhere",
    );
    $linkedUsers = $qInt(
        "SELECT COUNT(DISTINCT user_id) FROM pi_user_roles WHERE active=1 $modelScopedWhere",
    );
    $appointments30 = $qInt(
        "SELECT COUNT(*) FROM pi_appointments WHERE start_at>=$since AND start_at<NOW() AND status<>'cancelado' $modelScopedWhere",
    );
    $appointmentsFuture = $qInt(
        "SELECT COUNT(*) FROM pi_appointments WHERE start_at>=NOW() AND start_at<DATE_ADD(NOW(), INTERVAL 30 DAY) AND status<>'cancelado' $modelScopedWhere",
    );
    $activeLeads = $qInt(
        "SELECT COUNT(*) FROM pi_leads WHERE created_at>=$since AND " .
            lead_active_stage_sql("stage") .
            " $modelScopedWhere",
    );
    $patients30 = $qInt(
        "SELECT COUNT(*) FROM pi_patients WHERE created_at>=$since AND active=1 AND deleted_at IS NULL $modelScopedWhere",
    );
    $documents30 = $qInt(
        "SELECT COUNT(*) FROM pi_documents WHERE issued_at>=$since AND document_status<>'cancelado' $modelScopedWhere",
    );
    $tasks30 = $qInt(
        "SELECT COUNT(*) FROM pi_tasks WHERE created_at>=$since AND status NOT IN ('concluida','cancelada') $modelScopedWhere",
    );
    $overdueTasks = $qInt(
        "SELECT COUNT(*) FROM pi_tasks WHERE due_at>=$since AND due_at<NOW() AND status NOT IN ('concluida','cancelada') $modelScopedWhere",
    );
    $clinicNotices30 = $qInt(
        "SELECT COUNT(*) FROM pi_notices WHERE created_at>=$since $modelScopedWhere",
    );
    $globalNotices30 = $qInt(
        "SELECT COUNT(*) FROM pi_global_notices WHERE created_at>=$since",
    );
    $notices30 = $clinicNotices30 + $globalNotices30;
    $maestroRegencies = $qInt(
        "SELECT COUNT(*) FROM pi_maestro_rules WHERE 1=1 $modelScopedWhere",
    );
    $revenue30 = $qCents(
        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE status='efetivada' AND received_at>=$since $modelScopedWhere",
    );
    $expected30 = $qCents(
        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE status='prevista' AND expected_at>=$since $modelScopedWhere",
    );
    $expenses30 = $qCents(
        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_expenses WHERE status='paga' AND paid_at>=$since $modelScopedWhere",
    );
    $result30 = $revenue30 - $expenses30;
    $note = "Ativas";
    return '<div class="global-compact-summary"><div class="global-pill-section"><div class="global-pill-title">' .
        icon("account_tree") .
        '<b>Operação · últimos 30 dias</b></div><div class="global-pill-list">' .
        admin_global_compact_pill(
            "novos consultórios",
            (string) $newClinics,
            "domain",
            "Últimos 30 dias",
            "admin_clinics",
        ) .
        admin_global_compact_pill(
            "consultórios ativos",
            (string) $activeClinics,
            "verified",
            $note,
            "admin_clinics",
        ) .
        admin_global_compact_pill(
            "isentos",
            (string) $exemptClinics,
            "workspace_premium",
            "fora da cobrança",
            "admin_clinics",
        ) .
        admin_global_compact_pill(
            "somente leitura",
            (string) $readOnly,
            "lock",
            "status atual",
            "admin_clinics",
        ) .
        admin_global_compact_pill(
            "colaboradores",
            (string) $linkedUsers,
            "group",
            "com vínculo",
            "admin_people",
        ) .
        admin_global_compact_pill(
            "interessados",
            (string) $activeLeads,
            "person",
            "ativos criados",
            "admin_integrity",
        ) .
        admin_global_compact_pill(
            "pacientes",
            (string) $patients30,
            "person",
            "Últimos 30 dias",
            "admin_integrity",
        ) .
        admin_global_compact_pill(
            "agendamentos",
            (string) $appointments30,
            "calendar_month",
            $appointmentsFuture . " próximos",
            "admin_integrity",
        ) .
        admin_global_compact_pill(
            "documentos",
            (string) $documents30,
            "description",
            "Últimos 30 dias",
            "admin_integrity",
        ) .
        admin_global_compact_pill(
            "tarefas",
            (string) $tasks30,
            "task_alt",
            $overdueTasks . " vencidas",
            "admin_integrity",
        ) .
        admin_global_compact_pill(
            "avisos",
            (string) $notices30,
            "campaign",
            "inclui globais",
            "admin_global_notices",
        ) .
        admin_global_compact_pill(
            "rotinas",
            (string) $maestroRegencies,
            "event_repeat",
            "cadastradas",
        ) .
        '</div></div><div class="global-pill-section"><div class="global-pill-title">' .
        icon("payments") .
        '<b>Financeiro · últimos 30 dias</b></div><div class="global-pill-list">' .
        admin_global_compact_pill(
            "receita efetivada",
            money_br($revenue30),
            "payments",
            "Últimos 30 dias",
        ) .
        admin_global_compact_pill(
            "receita prevista",
            money_br($expected30),
            "payments",
            "Últimos 30 dias",
        ) .
        admin_global_compact_pill(
            "despesas pagas",
            money_br($expenses30),
            "receipt",
            "Últimos 30 dias",
        ) .
        admin_global_compact_pill(
            "resultado",
            money_br($result30),
            "account_balance",
            "efetivado",
        ) .
        "</div></div></div>";
}
function admin_global_metric_series_24h(string $metric): array
{

    static $requestSeries = null;
    $metric = $metric === "response" ? "query_ms" : $metric;
    if (!is_array($requestSeries)) {
        $tz = telemetry_cuiaba_tz();
        $now = new DateTimeImmutable("now", $tz);
        $nowTs = $now->getTimestamp();
        $startTs = $nowTs - 24 * 3600;
        $requestSeries = ["query_ms" => [], "load" => [], "queries" => []];
        for ($i = 0; $i <= 1440; $i++) {
            $ts = $startTs + $i * 60;
            $dt = new DateTimeImmutable("@" . $ts)->setTimezone($tz);
            $baseRow = [
                "ts" => $ts,
                "label" => $dt->format("H:i"),
                "tooltip" => $dt->format("d/m H:i"),
                "value" => 0.0,
                "sum" => 0.0,
                "count" => 0,
            ];
            $requestSeries["query_ms"][$i] = $baseRow;
            $requestSeries["load"][$i] = $baseRow;
            $requestSeries["queries"][$i] = $baseRow;
        }
        foreach (telemetry_read_events() as $event) {
            if (!is_array($event)) {
                continue;
            }
            $ts = (int) ($event["ts"] ?? 0);
            if ($ts < $startTs || $ts > $nowTs) {
                continue;
            }
            $idx = (int) floor(($ts - $startTs) / 60);
            if ($idx < 0 || $idx > 1440) {
                continue;
            }
            $requestSeries["queries"][$idx]["value"] +=
                (int) ($event["queries"] ?? 0);
            $load = max(0.0, (float) ($event["elapsed_ms"] ?? 0));
            if ($load > 0) {
                $requestSeries["load"][$idx]["sum"] += $load;
                $requestSeries["load"][$idx]["count"]++;
            }
            $queryMs = max(0.0, (float) ($event["query_ms"] ?? 0));
            if ($queryMs <= 0) {
                $queryMs = $load;
            }
            if ($queryMs > 0) {
                $requestSeries["query_ms"][$idx]["sum"] += $queryMs;
                $requestSeries["query_ms"][$idx]["count"]++;
            }
        }
        foreach (["query_ms", "load"] as $averageMetric) {
            foreach ($requestSeries[$averageMetric] as &$row) {
                $row["value"] =
                    $row["count"] > 0
                        ? round($row["sum"] / $row["count"], 1)
                        : 0.0;
                $row["samples"] = (int) $row["count"];
                unset($row["sum"], $row["count"]);
            }
            unset($row);
        }
        foreach ($requestSeries["queries"] as &$row) {
            unset($row["sum"], $row["count"]);
        }
        unset($row);
        foreach ($requestSeries as $seriesKey => $seriesRows) {
            $requestSeries[$seriesKey] = array_values($seriesRows);
        }
    }
    return $requestSeries[$metric] ?? $requestSeries["load"];
}
function admin_metric_duration_label(
    float $milliseconds,
    bool $compact = false,
): string {

    $milliseconds = max(0.0, $milliseconds);
    if ($milliseconds >= 1000.0) {
        $seconds = $milliseconds / 1000.0;
        $decimals = $seconds >= 10 ? 1 : 2;
        $label = number_format($seconds, $decimals, ",", ".");
        $label = preg_replace('/,0+$/', "", $label) ?? $label;
        $label = preg_replace('/,(\d*[1-9])0+$/', ',$1', $label) ?? $label;
        return $label . " s";
    }
    return number_format($milliseconds, $compact ? 0 : 1, ",", ".") . " ms";
}
function admin_metric_value_label(float $value, string $mode): string
{

    if ($mode === "ms") {
        return admin_metric_duration_label($value, false);
    }
    return number_format($value, 0, ",", ".");
}
function admin_metric_value_compact(float $value, string $mode): string
{
    if ($mode === "ms") {
        return admin_metric_duration_label($value, true);
    }
    return number_format($value, $value === floor($value) ? 0 : 1, ",", ".");
}
function admin_metric_recent_average(array $series, int $minutes = 5): float
{

    $recent = array_slice($series, -max(1, $minutes));
    $weightedSum = 0.0;
    $weightedCount = 0;
    $values = [];
    foreach ($recent as $row) {
        if (!is_array($row)) {
            continue;
        }
        $value = (float) ($row["value"] ?? 0);
        $samples = (int) ($row["samples"] ?? 0);
        if ($samples > 0) {
            $weightedSum += $value * $samples;
            $weightedCount += $samples;
        } elseif ($value > 0) {
            $values[] = $value;
        }
    }
    if ($weightedCount > 0) {
        return round($weightedSum / $weightedCount, 1);
    }
    return $values ? round(array_sum($values) / count($values), 1) : 0.0;
}
function admin_metric_line_chart(
    string $title,
    string $description,
    array $series,
    string $iconName,
    string $mode = "count",
): string {

    $values = array_map( fn($r) => (float) ($r["value"] ?? 0), $series);
    if (!$values) {
        $values = [0.0];
    }
    $max = max($values);
    if ($max <= 0) {
        $max = 1.0;
    }
    $w = 720;
    $h = 190;
    $padL = 34;
    $padR = 14;
    $padT = 18;
    $padB = 32;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;
    $n = count($series);
    $points = [];
    foreach ($series as $idx => $row) {
        $v = (float) ($row["value"] ?? 0);
        $x = $padL + ($n <= 1 ? 0 : $idx * ($plotW / ($n - 1)));
        $y = $padT + $plotH - ($v / $max) * $plotH;
        $points[] = [
            round($x, 2),
            round($y, 2),
            $v,
            (string) ($row["label"] ?? ""),
            (string) ($row["tooltip"] ?? ($row["label"] ?? "")),
        ];
    }
    $d = "";
    foreach ($points as $i => $pt) {
        $d .= ($i === 0 ? "M" : "L") . $pt[0] . " " . $pt[1] . " ";
    }
    $baseline = $padT + $plotH;
    $fillD = "";
    if ($points) {
        $firstPt = $points[0];
        $lastSeriesPt = $points[count($points) - 1];
        $fillD =
            trim($d) .
            " L " .
            $lastSeriesPt[0] .
            " " .
            round($baseline, 2) .
            " L " .
            $firstPt[0] .
            " " .
            round($baseline, 2) .
            " Z";
    }
    $last = $points ? $points[count($points) - 1][2] : 0.0;
    $nowAvg5 = admin_metric_recent_average($series, 5);
    $nonZero = array_values(
        array_filter($values, static  fn($v) => (float) $v > 0),
    );
    $avg24 = count($nonZero) ? array_sum($nonZero) / count($nonZero) : 0.0;
    $recentValues = array_slice($values, -31);
    $recentNonZero = array_values(
        array_filter($recentValues, static  fn($v) => (float) $v > 0),
    );
    $avg30 = count($recentNonZero)
        ? array_sum($recentNonZero) / count($recentNonZero)
        : 0.0;
    $grid = "";
    for ($i = 0; $i <= 3; $i++) {
        $gy = $padT + $i * ($plotH / 3);
        $gv = $max - ($max / 3) * $i;
        $grid .=
            '<line x1="' .
            $padL .
            '" y1="' .
            round($gy, 2) .
            '" x2="' .
            ($w - $padR) .
            '" y2="' .
            round($gy, 2) .
            '" class="metric-grid-line"/><text x="6" y="' .
            round($gy + 4, 2) .
            '" class="metric-axis-label">' .
            e(number_format($gv, 0, ",", ".")) .
            "</text>";
    }
    $ticks = "";
    $tickEvery = max(1, (int) ceil(max(1, $n) / 8));
    foreach ($points as $i => $pt) {
        if ($i % $tickEvery === 0 || $i === $n - 1) {
            $ticks .=
                '<text x="' .
                $pt[0] .
                '" y="' .
                ($h - 8) .
                '" class="metric-axis-label metric-x-label">' .
                e($pt[3]) .
                "</text>";
        }
    }
    $hover = "";
    foreach ($points as $pt) {
        $label = $pt[4] . " · " . admin_metric_value_label($pt[2], $mode);
        $hover .=
            '<circle cx="' .
            $pt[0] .
            '" cy="' .
            $pt[1] .
            '" r="4.5" class="metric-chart-hit"><title>' .
            e($label) .
            "</title></circle>";
    }
    $lastPt = end($points);
    $circle = $lastPt
        ? '<circle cx="' .
            $lastPt[0] .
            '" cy="' .
            $lastPt[1] .
            '" r="3.5" class="metric-chart-dot"><title>' .
            e(
                $lastPt[4] .
                    " · " .
                    admin_metric_value_label($lastPt[2], $mode),
            ) .
            "</title></circle>"
        : "";
    $summary =
        $title .
        ": agora, média dos últimos 5 minutos, " .
        admin_metric_value_compact($nowAvg5, $mode) .
        ", média de 30 minutos " .
        admin_metric_value_compact($avg30, $mode) .
        ", média de 24 horas " .
        admin_metric_value_compact($avg24, $mode);
    $nowCompact = admin_metric_value_compact($nowAvg5, $mode);
    $avg30Compact = admin_metric_value_compact($avg30, $mode);
    $avg24Compact = admin_metric_value_compact($avg24, $mode);
    $pills =
        '<span title="Média dos últimos 5 minutos" aria-label="Agora, média dos últimos 5 minutos: ' .
        e($nowCompact) .
        '">' .
        icon("radio_button_checked") .
        "<b>" .
        e($nowCompact) .
        "</b></span>" .
        '<span title="Média dos últimos 30 minutos" aria-label="Média dos últimos 30 minutos: ' .
        e($avg30Compact) .
        '">' .
        icon("timer") .
        "<b>" .
        e($avg30Compact) .
        "</b></span>" .
        '<span title="Média das últimas 24 horas" aria-label="Média das últimas 24 horas: ' .
        e($avg24Compact) .
        '">' .
        icon("calendar_today") .
        "<b>" .
        e($avg24Compact) .
        "</b></span>";
    return '<article class="metric-line-chart metric-area-chart" data-ds-card="admin-area-chart" aria-label="' .
        e($title) .
        '"><header><div class="metric-chart-title">' .
        icon($iconName) .
        "<div><h3>" .
        e($title) .
        '</h3></div></div><div class="metric-chart-value"><div class="metric-chart-pills">' .
        $pills .
        '</div></div></header><p class="sr-only">' .
        e($summary) .
        '</p><svg viewBox="0 0 ' .
        $w .
        " " .
        $h .
        '" aria-hidden="true" focusable="false"><g>' .
        $grid .
        "</g>" .
        ($fillD !== ""
            ? '<path d="' . e($fillD) . '" class="metric-chart-fill"/>'
            : "") .
        '<path d="' .
        trim($d) .
        '" class="metric-chart-line"/>' .
        $circle .
        $hover .
        $ticks .
        "</svg></article>";
}
function admin_metric_dual_area_chart(
    string $title,
    array $loadSeries,
    array $responseSeries,
    string $iconName = "speed",
    array $presentation = [],
): string {

    $primaryLabel = trim((string) ($presentation["primary_label"] ?? "Carregamento"));
    $secondaryLabel = trim((string) ($presentation["secondary_label"] ?? "Resposta"));
    $valueType = (string) ($presentation["value_type"] ?? "ms");
    if ($primaryLabel === "") {
        $primaryLabel = "Carregamento";
    }
    if ($secondaryLabel === "") {
        $secondaryLabel = "Resposta";
    }
    if (!in_array($valueType, ["ms", "count"], true)) {
        $valueType = "ms";
    }
    $recentPoints = max(1, (int) ($presentation["recent_points"] ?? 5));
    $middlePoints = max(1, (int) ($presentation["middle_points"] ?? 31));
    $recentTitle = trim((string) ($presentation["recent_title"] ?? "Carregamento médio dos últimos 5 minutos"));
    $middleTitle = trim((string) ($presentation["middle_title"] ?? "Carregamento médio dos últimos 30 minutos"));
    $overallTitle = trim((string) ($presentation["overall_title"] ?? "Carregamento médio das últimas 24 horas"));
    $summaryLead = trim((string) ($presentation["summary_lead"] ?? "indicadores exibem somente o tempo de carregamento."));
    $loadValues = array_map( fn($r) => (float) ($r["value"] ?? 0), $loadSeries);
    $responseValues = array_map(
         fn($r) => (float) ($r["value"] ?? 0),
        $responseSeries,
    );
    if (!$loadValues) {
        $loadValues = [0.0];
    }
    if (!$responseValues) {
        $responseValues = [0.0];
    }
    $max = max(max($loadValues), max($responseValues));
    if ($max <= 0) {
        $max = 1.0;
    }
    $w = 720;
    $h = 190;
    $padL = 34;
    $padR = 14;
    $padT = 18;
    $padB = 32;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;
    $baseline = $padT + $plotH;
    $makePoints = static function (array $series) use (
        $padL,
        $plotW,
        $padT,
        $plotH,
        $max,
    ): array {

        $n = count($series);
        $points = [];
        foreach ($series as $idx => $row) {
            $v = max(0.0, (float) ($row["value"] ?? 0));
            $x = $padL + ($n <= 1 ? 0 : $idx * ($plotW / ($n - 1)));
            $y = $padT + $plotH - ($v / $max) * $plotH;
            $points[] = [
                round($x, 2),
                round($y, 2),
                $v,
                (string) ($row["label"] ?? ""),
                (string) ($row["tooltip"] ?? ($row["label"] ?? "")),
            ];
        }
        return $points;
    };
    $path = static function (array $points): string {

        $d = "";
        foreach ($points as $i => $pt) {
            $d .= ($i === 0 ? "M" : "L") . $pt[0] . " " . $pt[1] . " ";
        }
        return trim($d);
    };
    $fill = static function (
        string $d,
        array $points,
        float $baseline,
    ): string {

        if (!$points || $d === "") {
            return "";
        }
        $first = $points[0];
        $last = $points[count($points) - 1];
        return $d .
            " L " .
            $last[0] .
            " " .
            round($baseline, 2) .
            " L " .
            $first[0] .
            " " .
            round($baseline, 2) .
            " Z";
    };
    $loadPoints = $makePoints($loadSeries);
    $responsePoints = $makePoints($responseSeries);
    $loadD = $path($loadPoints);
    $responseD = $path($responsePoints);
    $loadFill = $fill($loadD, $loadPoints, $baseline);
    $responseFill = $fill($responseD, $responsePoints, $baseline);
    $grid = "";
    for ($i = 0; $i <= 3; $i++) {
        $gy = $padT + $i * ($plotH / 3);
        $gv = $max - ($max / 3) * $i;
        $grid .=
            '<line x1="' .
            $padL .
            '" y1="' .
            round($gy, 2) .
            '" x2="' .
            ($w - $padR) .
            '" y2="' .
            round($gy, 2) .
            '" class="metric-grid-line"/><text x="6" y="' .
            round($gy + 4, 2) .
            '" class="metric-axis-label">' .
            e(number_format($gv, 0, ",", ".")) .
            "</text>";
    }
    $ticks = "";
    $n = count($loadPoints);
    $tickEvery = max(1, (int) ceil(max(1, $n) / 8));
    foreach ($loadPoints as $i => $pt) {
        if ($i % $tickEvery === 0 || $i === $n - 1) {
            $ticks .=
                '<text x="' .
                $pt[0] .
                '" y="' .
                ($h - 8) .
                '" class="metric-axis-label metric-x-label">' .
                e($pt[3]) .
                "</text>";
        }
    }
    $hover = "";
    foreach ($loadPoints as $pt) {
        $hover .=
            '<circle cx="' .
            $pt[0] .
            '" cy="' .
            $pt[1] .
            '" r="4.5" class="metric-chart-hit"><title>' .
            e(
                $pt[4] .
                    " · " . $primaryLabel . " " .
                    admin_metric_value_label($pt[2], $valueType),
            ) .
            "</title></circle>";
    }
    foreach ($responsePoints as $pt) {
        $hover .=
            '<circle cx="' .
            $pt[0] .
            '" cy="' .
            $pt[1] .
            '" r="3.8" class="metric-chart-hit"><title>' .
            e(
                $pt[4] .
                    " · " . $secondaryLabel . " " .
                    admin_metric_value_label($pt[2], $valueType),
            ) .
            "</title></circle>";
    }
    $recentValues = array_slice($loadValues, -$recentPoints);
    $recentValue = $valueType === "count"
        ? ($recentValues
            ? array_sum($recentValues) / count($recentValues)
            : 0.0)
        : admin_metric_recent_average($loadSeries, $recentPoints);
    $overallAverageValues = $valueType === "count"
        ? $loadValues
        : array_values(
            array_filter($loadValues, static fn($v) => (float) $v > 0),
        );
    $overallValue = count($overallAverageValues)
        ? array_sum($overallAverageValues) / count($overallAverageValues)
        : 0.0;
    $middleValues = array_slice($loadValues, -$middlePoints);
    $middleAverageValues = $valueType === "count"
        ? $middleValues
        : array_values(
            array_filter($middleValues, static fn($v) => (float) $v > 0),
        );
    $middleValue = count($middleAverageValues)
        ? array_sum($middleAverageValues) / count($middleAverageValues)
        : 0.0;
    $recentCompact = admin_metric_value_compact($recentValue, $valueType);
    $middleCompact = admin_metric_value_compact($middleValue, $valueType);
    $overallCompact = admin_metric_value_compact($overallValue, $valueType);
    $pills =
        '<span title="' .
        e($recentTitle) .
        '" aria-label="' .
        e($recentTitle . ": " . $recentCompact) .
        '">' .
        icon("radio_button_checked") .
        "<b>" .
        e($recentCompact) .
        "</b></span>" .
        '<span title="' .
        e($middleTitle) .
        '" aria-label="' .
        e($middleTitle . ": " . $middleCompact) .
        '">' .
        icon("timer") .
        "<b>" .
        e($middleCompact) .
        "</b></span>" .
        '<span title="' .
        e($overallTitle) .
        '" aria-label="' .
        e($overallTitle . ": " . $overallCompact) .
        '">' .
        icon("calendar_today") .
        "<b>" .
        e($overallCompact) .
        "</b></span>";
    $legend = "";
    $lastLoad = end($loadPoints);
    $lastResp = end($responsePoints);
    $loadCircle = $lastLoad
        ? '<circle cx="' .
            $lastLoad[0] .
            '" cy="' .
            $lastLoad[1] .
            '" r="3.6" class="metric-chart-dot metric-chart-dot-load"><title>' .
            e(
                $lastLoad[4] .
                    " · " . $primaryLabel . " " .
                    admin_metric_value_label($lastLoad[2], $valueType),
            ) .
            "</title></circle>"
        : "";
    $responseCircle = $lastResp
        ? '<circle cx="' .
            $lastResp[0] .
            '" cy="' .
            $lastResp[1] .
            '" r="3.2" class="metric-chart-dot metric-chart-dot-response"><title>' .
            e(
                $lastResp[4] .
                    " · " . $secondaryLabel . " " .
                    admin_metric_value_label($lastResp[2], $valueType),
            ) .
            "</title></circle>"
        : "";
    $summary =
        $title .
        ": " .
        $summaryLead .
        " " .
        $recentTitle .
        " " .
        $recentCompact .
        ", " .
        $middleTitle .
        " " .
        $middleCompact .
        ", " .
        $overallTitle .
        " " .
        $overallCompact .
        ".";
    $loadArea =
        $loadFill !== ""
            ? '<path d="' . e($loadFill) . '" class="metric-chart-fill-load"/>'
            : "";
    $responseArea =
        $responseFill !== ""
            ? '<path d="' .
                e($responseFill) .
                '" class="metric-chart-fill-response"/>'
            : "";
    $fillAreas = $loadArea . $responseArea;
    return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-metric-value-type="' .
        e($valueType) .
        '" data-ds-card="admin-dual-area-chart" aria-label="' .
        e($title) .
        '"><header><div class="metric-chart-title">' .
        icon($iconName) .
        "<div><h3>" .
        e($title) .
        '</h3></div></div><div class="metric-chart-value"><div class="metric-chart-pills">' .
        $pills .
        '</div></div></header><p class="sr-only">' .
        e($summary) .
        '</p><svg viewBox="0 0 ' .
        $w .
        " " .
        $h .
        '" aria-hidden="true" focusable="false"><g>' .
        $grid .
        "</g>" .
        $fillAreas .
        ($loadD !== ""
            ? '<path d="' . e($loadD) . '" class="metric-chart-line-load"/>'
            : "") .
        ($responseD !== ""
            ? '<path d="' .
                e($responseD) .
                '" class="metric-chart-line-response"/>'
            : "") .
        $loadCircle .
        $responseCircle .
        $hover .
        $ticks .
        "</svg></article>";
}
function admin_global_sequence_series_30d(): array
{

    $tz = telemetry_cuiaba_tz();
    $today = new DateTimeImmutable("today", $tz);
    $days = [];
    $select = [];
    $params = [];
    for ($i = 29; $i >= 0; $i--) {
        $day = $today->modify("-" . $i . " days");
        $next = $day->modify("+1 day");
        $key = $day->format("Y-m-d");
        $days[$key] = [
            "key" => $key,
            "label" => $day->format("d/m"),
            "tooltip" => $day->format("d/m/Y"),
            "value" => 0,
        ];
        $select[] =
            "SUM(CASE WHEN created_at>=? AND created_at<? THEN 1 ELSE 0 END) AS d" .
            (29 - $i);
        $params[] = $day->getTimestamp();
        $params[] = $next->getTimestamp();
    }
    try {
        if (
            function_exists("db_table_exists") &&
            !db_table_exists("pi_action_ledger")
        ) {
            return array_values($days);
        }
        $sql =
            "SELECT " .
            implode(",", $select) .
            " FROM pi_action_ledger WHERE created_at>=? AND created_at<?";
        $first = array_key_first($days);
        $last = array_key_last($days);
        $params[] = new DateTimeImmutable(
            $first . " 00:00:00",
            $tz,
        )->getTimestamp();
        $params[] = new DateTimeImmutable($last . " 00:00:00", $tz)
            ->modify("+1 day")
            ->getTimestamp();
        $row = q($sql, $params)->fetch() ?: [];
        $idx = 0;
        foreach ($days as $key => &$day) {
            $day["value"] = (int) ($row["d" . $idx] ?? 0);
            $idx++;
        }
        unset($day);
    } catch (Throwable $e) {
        error_log("[Prontoo admin seq chart] " . $e->getMessage());
    }
    return array_values($days);
}
function admin_maestro_health_time_label(?string $value): string
{

    $value = trim((string) ($value ?? ""));
    if ($value === "") {
        return "--h--";
    }
    $context = null;
    try {
        if (function_exists("ctx")) {
            $context = ctx();
        }
    } catch (Throwable $e) {
        $context = null;
    }
    $clinicId =
        is_array($context) && ($context["scope"] ?? "") === "clinic"
            ? (int) ($context["clinic_id"] ?? 0)
            : 0;
    if (function_exists("app_db_utc_to_local")) {
        $dt = app_db_utc_to_local(
            $value,
            $clinicId,
            is_array($context) ? $context : null,
        );
        if ($dt) {
            return $dt->format("H\hi");
        }
    }
    $ts = strtotime($value);
    return $ts ? gmdate("H\hi", $ts) : "--h--";
}
function admin_maestro_health_pill_html(bool $allowSchemaEnsure = true): string
{

    $ok = false;
    $label = "Rotinas sem execução registrada";
    try {
        if (has_cfg()) {
            $schemaReady = true;
            if ($allowSchemaEnsure) {
                maestro_ensure_schema();
            } elseif (function_exists("db_table_exists")) {
                $schemaReady = db_table_exists("pi_maestro_job_runs");
            }
            if ($schemaReady) {
                $hasSuccess = db_column_exists("pi_maestro_job_runs", "success");
            $cols = $hasSuccess
                ? "started_at,finished_at,duration_ms,note,success,errors_count"
                : "started_at,finished_at,duration_ms,note";
            $latest = q(
                "SELECT $cols FROM pi_maestro_job_runs ORDER BY id DESC LIMIT 1",
            )->fetch();
            if ($latest) {
                $finished = trim((string) ($latest["finished_at"] ?? ""));
                $started = trim((string) ($latest["started_at"] ?? ""));
                $note = mb_strtolower(
                    trim((string) ($latest["note"] ?? "")),
                    "UTF-8",
                );
                if ($hasSuccess) {
                    $ok = (int) ($latest["success"] ?? 0) === 1;
                } else {
                    $ok =
                        $finished !== "" &&
                        $note !== "" &&
                        !str_contains($note, "erro") &&
                        !str_contains($note, "falha") &&
                        !str_contains($note, "exception");
                }
                $base = $ok
                    ? ($finished !== ""
                        ? $finished
                        : $started)
                    : ($finished !== ""
                        ? $finished
                        : $started);
                $timeLabel = admin_maestro_health_time_label($base);
                $label = $ok
                    ? "Rotinas em dia às " . $timeLabel
                    : "Rotinas com atenção desde " . $timeLabel;
            }
            }
        }
    } catch (Throwable $e) {
        error_log("[Prontoo admin maestro health] " . $e->getMessage());
    }
    $class = $ok ? "ok" : "warn";
    $ico = $ok ? "tune" : "warning";
    return '<span class="maestro-health-pill ' .
        $class .
        '">' .
        icon($ico) .
        "<b>" .
        e($label) .
        "</b></span>";
}
function admin_global_perf_charts_html(): string
{
    $response = admin_global_metric_series_24h("query_ms");
    $load = admin_global_metric_series_24h("load");
    $records = admin_global_sequence_series_30d();
    $requests = function_exists("telemetry_route_requests_series_30d")
        ? telemetry_route_requests_series_30d()
        : [];
    return '<div class="global-performance-charts global-area-charts" data-admin-global-charts data-refresh-ms="900000" data-chart-window="5min">' .
        admin_metric_dual_area_chart("Velocidade", $load, $response, "speed") .
        admin_metric_dual_area_chart(
            "Leitura e gravação",
            $requests,
            $records,
            "speed",
            [
                "primary_label" => "Requisições",
                "secondary_label" => "Registros",
                "value_type" => "count",
                "recent_points" => 1,
                "middle_points" => 7,
                "recent_title" => "Requisições de hoje",
                "middle_title" => "Média diária de requisições nos últimos 7 dias",
                "overall_title" => "Média diária de requisições nos últimos 30 dias",
                "summary_lead" => "dados de Requisições e Registros dos últimos 30 dias.",
            ],
        ) .
        "</div>";
}
function admin_performance_card_content_html(bool $public = false): string
{
    return '<div class="section-head admin-performance-head"><h2>Desempenho geral</h2>' .
        admin_maestro_health_pill_html(!$public) .
        "</div>" .
        admin_global_perf_charts_html();
}
function admin_performance_card_html(bool $public = false): string
{
    return card(
        admin_performance_card_content_html($public),
        "admin-performance-card",
    );
}
function page_status(): void
{
    if (strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")) !== "GET") {
        throw new ProntooHttpError(405, "Método não permitido.");
    }
    if (!headers_sent()) {
        header("Content-Type: text/html; charset=utf-8");
        header("Cache-Control: public, max-age=60, stale-while-revalidate=60");
        header("X-Robots-Tag: noindex, nofollow");
    }
    $assetRevision = defined("PRONTOO_ASSET_REV")
        ? (string) PRONTOO_ASSET_REV
        : (string) PRONTOO_VERSION;
    $card = admin_performance_card_html(true);
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="canonical" href="https://prontoo.app/status"><title>Status · Prontoo</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#238763"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="prontoo-version" content="' .
        e(PRONTOO_VERSION) .
        '"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/png" href="/public/assets/favicon-' .
        rawurlencode($assetRevision) .
        '.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,-25..200&display=swap" rel="stylesheet"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
        rawurlencode($assetRevision) .
        '&release=' .
        rawurlencode(PRONTOO_VERSION) .
        '"><script defer src="/public/assets/app.js?v=' .
        rawurlencode($assetRevision) .
        '&release=' .
        rawurlencode(PRONTOO_VERSION) .
        '"></script></head><body class="public scope-global status-public" style="--clinic-accent:#238763;--clinic-accent-dark:#105e44;--clinic-accent-soft:#dff3ea;--clinic-on-accent:#ffffff;" data-route="status" data-app-version="' .
        e(PRONTOO_VERSION) .
        '"><main id="conteudo" tabindex="-1">' .
        $card .
        "</main></body></html>";
}
function page_admin_operations(): void
{

    require_can("admin_operations");
    $body =
        page_head("Painel operacional", "") .
        card(
            '<div class="section-head admin-performance-head"><h2>Operação e financeiro</h2><p>Indicadores dos últimos 30 dias, organizados por operação e caixa da plataforma.</p></div>' .
                admin_global_ops_finance_html(),
            "admin-ops-finance-card",
        );
    page("Painel operacional", $body);
}
function page_admin_deleted(): void
{

    require_can("admin_health");
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "");
        $id = (int) ($_POST["id"] ?? 0);
        if ($id <= 0) {
            flash("Registro não informado.", "bad");
            redirect("admin_deleted");
        }
        if ($act === "restore_patient") {
            $pat = one(
                "SELECT id,person_id,clinic_id FROM pi_patients WHERE id=? AND deleted_at IS NOT NULL",
                [$id],
            );
            if ($pat) {
                q(
                    "UPDATE pi_patients SET active=1,registration_needs_update=1,deleted_at=NULL,deleted_by=NULL,restored_at=NOW(),restored_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [
                        (int) ($_SESSION["uid"] ?? 0),
                        $id,
                        (int) $pat["clinic_id"],
                    ],
                );
                audit("paciente_recuperado", "paciente", $id, [
                    "campos" => ["Restauração administrativa"],
                    "audit_body" =>
                        "Paciente excluído foi restaurado pelo Desenvolvedor. Atualização cadastral deve ser conferida pela clínica.",
                ]);
                flash(
                    "Paciente restaurado. A clínica deverá revisar o cadastro.",
                );
            }
            redirect("admin_deleted");
        }
        if ($act === "restore_care") {
            $care = one(
                "SELECT id,patient_link_id,clinic_id FROM pi_care WHERE id=? AND deleted_at IS NOT NULL",
                [$id],
            );
            if ($care) {
                q(
                    "UPDATE pi_care SET deleted_at=NULL,deleted_by=NULL,restored_at=NOW(),restored_by=? WHERE id=? AND clinic_id=?",
                    [
                        (int) ($_SESSION["uid"] ?? 0),
                        $id,
                        (int) $care["clinic_id"],
                    ],
                );
                audit(
                    "prontuario_alterado",
                    "paciente",
                    (int) $care["patient_link_id"],
                    [
                        "campos" => ["Restauração administrativa de anotação"],
                        "audit_body" =>
                            "Anotação excluída foi restaurada pelo Desenvolvedor.",
                    ],
                );
                flash("Anotação restaurada.");
            }
            redirect("admin_deleted");
        }
    }
    $patients = q(
        "SELECT id,person_id,clinic_id,deleted_at,deleted_by FROM pi_patients WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 80",
    )->fetchAll();
    $persons = fetch_map(
        "pi_persons",
        int_ids($patients, "person_id"),
        "id,full_name,cpf,birth_date",
    );
    $clinics = fetch_map(
        "pi_clinics",
        int_ids($patients, "clinic_id"),
        "id,display_name",
    );
    $users = fetch_map("pi_users", int_ids($patients, "deleted_by"), "id,name");
    $pitems = [];
    foreach ($patients as $r) {
        $ps = $persons[(int) $r["person_id"]] ?? [];
        $cl = $clinics[(int) $r["clinic_id"]] ?? [];
        $by = $users[(int) ($r["deleted_by"] ?? 0)] ?? [];
        $form =
            '<form method="post" class="inline">' .
            csrf_field() .
            '<input type="hidden" name="act" value="restore_patient"><input type="hidden" name="id" value="' .
            (int) $r["id"] .
            '"><button type="submit" class="small primary">Restaurar</button></form>';
        $pitems[] = [
            "icon" => "restore_from_trash",
            "time" => dt_br($r["deleted_at"]),
            "title" => $ps["full_name"] ?? "Paciente #" . $r["id"],
            "body" =>
                ($cl["display_name"] ?? "Consultório") .
                " · CPF " .
                mask((string) ($ps["cpf"] ?? "")),
            "meta" => "Excluído por " . first_name($by["name"] ?? ""),
            "html" => $form,
        ];
    }
    $care = q(
        "SELECT id,patient_link_id,record_type,title,deleted_at,deleted_by FROM pi_care WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 80",
    )->fetchAll();
    $patientsMap = fetch_map(
        "pi_patients",
        int_ids($care, "patient_link_id"),
        "id,person_id,clinic_id",
    );
    $personIds = [];
    $clinicIds = [];
    foreach ($patientsMap as $pm) {
        if (!empty($pm["person_id"])) {
            $personIds[] = (int) $pm["person_id"];
        }
        if (!empty($pm["clinic_id"])) {
            $clinicIds[] = (int) $pm["clinic_id"];
        }
    }
    $personMap = fetch_map(
        "pi_persons",
        array_values(array_unique($personIds)),
        "id,full_name",
    );
    $clinicMap = fetch_map(
        "pi_clinics",
        array_values(array_unique($clinicIds)),
        "id,display_name",
    );
    $users2 = fetch_map("pi_users", int_ids($care, "deleted_by"), "id,name");
    $citems = [];
    foreach ($care as $r) {
        $pm = $patientsMap[(int) $r["patient_link_id"]] ?? [];
        $ps = $personMap[(int) ($pm["person_id"] ?? 0)] ?? [];
        $cl = $clinicMap[(int) ($pm["clinic_id"] ?? 0)] ?? [];
        $by = $users2[(int) ($r["deleted_by"] ?? 0)] ?? [];
        $form =
            '<form method="post" class="inline">' .
            csrf_field() .
            '<input type="hidden" name="act" value="restore_care"><input type="hidden" name="id" value="' .
            (int) $r["id"] .
            '"><button type="submit" class="small primary">Restaurar</button></form>';
        $citems[] = [
            "icon" => "clinical_notes",
            "time" => dt_br($r["deleted_at"]),
            "title" => $r["title"] ?: ucfirst((string) $r["record_type"]),
            "body" =>
                "Prontuário de " .
                ($ps["full_name"] ?? "paciente #" . $r["patient_link_id"]) .
                " · " .
                ($cl["display_name"] ?? "Consultório"),
            "meta" => "Excluída por " . first_name($by["name"] ?? ""),
            "html" => $form,
        ];
    }
    page(
        "Registros excluídos",
        page_head(
            "Registros excluídos",
            "Restauração administrativa de dados preservados por integridade.",
        ) .
            '<div class="two"><section class="card"><h2>Pacientes excluídos</h2>' .
            timeline($pitems, "Nenhum paciente excluído.") .
            '</section><section class="card"><h2>Anotações excluídas</h2>' .
            timeline($citems, "Nenhuma anotação excluída.") .
            "</section></div>",
    );
}
function page_admin_health(): void
{

    require_can("admin_health");
    $dbOk = false;
    try {
        $dbOk = (string) val("SELECT 1") === "1";
    } catch (Throwable $e) {
        $dbOk = false;
    }
    $modelClinicWhere = admin_model_clinic_exclude_sql("id");
    $modelAuditWhere = admin_model_clinic_exclude_where("a.clinic_id");
    $openErrors = (int) cached_val(
        "kpi_errors_open",
        120,
        "SELECT COUNT(*) FROM pi_error_events WHERE resolved_at IS NULL",
    );
    $errors24h = (int) cached_val(
        "kpi_errors_24h",
        120,
        "SELECT COUNT(*) FROM pi_error_events WHERE created_at>=DATE_SUB(NOW(), INTERVAL 24 HOUR)",
    );
    $locks = (int) cached_val(
        "kpi_locks",
        60,
        "SELECT COUNT(*) FROM pi_login_locks WHERE locked_until>NOW()",
    );
    $scope24h = (int) cached_val(
        "kpi_scope_actionable_24h_v2_" . admin_model_clinic_id(),
        120,
        "SELECT COUNT(*) FROM pi_scope_violations WHERE created_at>=DATE_SUB(NOW(), INTERVAL 24 HOUR) AND violation_key<>'write_in_read_only' " .
            admin_model_clinic_exclude_sql("clinic_id"),
    );
    $trialing = (int) cached_val(
        "kpi_trialing_model_" . admin_model_clinic_id(),
        PRONTOO_ADMIN_CACHE_TTL,
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND trial_ends_at>=NOW() $modelClinicWhere",
    );
    $readonly = (int) cached_val(
        "kpi_readonly_model_" . admin_model_clinic_id(),
        PRONTOO_ADMIN_CACHE_TTL,
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status='read_only' $modelClinicWhere",
    );
    $recent = audit_rows_light($modelAuditWhere, [], 80);
    $bad = 0;
    foreach ($recent as $r) {
        if (!verify_audit_row($r)) {
            $bad++;
        }
    }
    $chainStatus = audit_chain_integrity_status(240);
    if (empty($chainStatus["ok"])) {
        $bad++;
    }
    $severity =
        !$dbOk || $openErrors > 0 || $bad > 0 || $scope24h > 0
            ? "Atenção"
            : "Estável";
    $summary =
        '<div class="stats-grid admin-health-compact-kpis">' .
        stat_card(
            "Estado",
            $severity,
            $severity === "Estável" ? "verified" : "crisis_alert",
            $dbOk ? "banco responde" : "banco indisponível",
        ) .
        stat_link_card(
            "Erros abertos",
            $openErrors,
            "bug_report",
            $errors24h . " nas últimas 24h",
            "admin_errors",
        ) .
        stat_card("Bloqueios", $locks, "lock_clock", "login ativo") .
        stat_link_card(
            "Escopo 24h",
            $scope24h,
            "policy",
            "operações bloqueadas",
            "admin_security",
        ) .
        stat_card(
            "Integridade",
            $bad,
            "verified_user",
            "amostra de auditoria",
        ) .
        stat_card("Somente leitura", $readonly, "lock", "consultórios") .
        "</div>";
    $items = [
        [
            "icon" => $dbOk ? "check_circle" : "warning",
            "time" => "Banco",
            "title" => $dbOk ? "Conexão operacional" : "Conexão com atenção",
            "body" => "Verificação leve com SELECT 1.",
            "meta" =>
                "Diagnóstico consolidado no próprio painel de Incidentes.",
        ],
        [
            "icon" => $openErrors ? "bug_report" : "check_circle",
            "time" => "Erros",
            "title" => $openErrors . " erro(s) aberto(s)",
            "body" => $errors24h . " registro(s) nas últimas 24 horas.",
            "meta" =>
                "Abra a central de erros apenas quando precisar investigar arquivo, linha e rota.",
            "html" =>
                '<a class="ghost small" href="' .
                href("admin_errors") .
                '">' .
                action_summary_label("Ver erros", "bug_report") .
                "</a>",
        ],
        [
            "icon" => $bad ? "gpp_bad" : "verified_user",
            "time" => "Integridade",
            "title" => $bad . " anotação(ões) recentes com assinatura alterada",
            "body" =>
                "Amostra de atividades recentes, descontando consultórios isentos do Desenvolvedor.",
            "meta" => admin_model_clinic_count_note(),
            "html" =>
                '<a class="ghost small" href="' .
                href("admin_integrity") .
                '">' .
                action_summary_label("Ver integridade", "verified_user") .
                "</a>",
        ],
        [
            "icon" => $locks ? "lock_clock" : "shield",
            "time" => "Segurança",
            "title" => $locks . " bloqueio(s) de login ativo(s)",
            "body" =>
                "Bloqueios temporários de entrada permanecem visíveis em leitura única.",
            "meta" =>
                "Use a tela dedicada só para liberar ou auditar tentativas.",
            "html" =>
                '<a class="ghost small" href="' .
                href("admin_security") .
                '">' .
                action_summary_label("Ver segurança", "security") .
                "</a>",
        ],
        [
            "icon" => "home_health",
            "time" => "Consultórios",
            "title" =>
                $trialing .
                " trial(s) em curso · " .
                $readonly .
                " em somente leitura",
            "body" => "Assinaturas e adoção ficam no painel de Consultórios.",
            "meta" =>
                "Acompanhamento financeiro e operacional sem nova tela de incidente.",
            "html" =>
                '<a class="ghost small" href="' .
                href("admin_clinics") .
                '">' .
                action_summary_label("Ver consultórios", "home_health") .
                "</a>",
        ],
    ];
    $body =
        page_head("Incidentes", "") .
        card($summary, "admin-health-compact-card") .
        card(
            "<h2>Sinais principais</h2>" . timeline($items),
            "admin-health-events-card",
        );
    page("Incidentes", $body);
}
function page_admin_diagnostics(): void
{

    require_can("admin_diagnostics");
    $dbOk = false;
    $dbMsg = "indisponível";
    try {
        $dbOk = (string) val("SELECT 1") === "1";
        $dbMsg = "conexão operacional";
    } catch (Throwable $e) {
        $dbMsg = $e->getMessage();
    }
    $checks = [
        [
            "icon" => "php",
            "time" => "PHP",
            "title" => PHP_VERSION,
            "body" => "Versão ativa do interpretador",
            "meta" => "Recomendado: PHP 8.4 ou superior",
        ],
        [
            "icon" => "database",
            "time" => "Banco",
            "title" => bool_status(
                $dbOk,
                "Banco acessível",
                "Banco indisponível",
            ),
            "body" => $dbMsg,
            "meta" => "Ativo leve: SELECT 1",
        ],
        [
            "icon" => "folder_managed",
            "time" => "Storage",
            "title" => bool_status(
                is_writable(app_root() . "/ssd"),
                "Diretório gravável",
                "Sem escrita em /ssd",
            ),
            "body" => app_root() . "/ssd",
            "meta" =>
                "Espaço livre: " .
                (function_exists("disk_free_space")
                    ? human_bytes(
                        (float) @disk_free_space(app_root() . "/ssd"),
                    )
                    : "não informado"),
        ],
        [
            "icon" => "lock",
            "time" => "Instalação",
            "title" => is_file(app_root() . "/storage/install.lock")
                ? "Instalação bloqueada"
                : "Instalação aberta",
            "body" => "Arquivo install.lock",
            "meta" => "Evita reinstalação indevida",
        ],
        [
            "icon" => "login",
            "time" => "Inicial",
            "title" => "Login como tela inicial",
            "body" =>
                "A LandingPage foi removida do projeto e transferida para comunicação externa.",
            "meta" => "index.php abre diretamente o sistema.",
        ],
        [
            "icon" => "security",
            "time" => "Debug",
            "title" => app_debug() ? "Debug ativo" : "Debug inativo",
            "body" => "Configuração atual do app",
            "meta" => "Em produção, manter inativo",
        ],
        [
            "icon" => "schema",
            "time" => "Schema",
            "title" => "Schema imutável em runtime",
            "body" =>
                "A instalação limpa aplica um único contrato SQL; requisições comuns não criam nem alteram tabelas.",
            "meta" => "Revisão: " . PRONTOO_SCHEMA_REV,
        ],
        [
            "icon" => "rule",
            "time" => "Somente leitura",
            "title" => "Allowlist declarativa",
            "body" =>
                "Consultórios vencidos só podem gravar registros técnicos de login e regularização de assinatura.",
            "meta" => "Demais escritas clínicas seguem bloqueadas",
        ],
    ];
    page(
        "Diagnóstico",
        page_head(
            "Diagnóstico",
            "Verificações rápidas do ambiente sem varrer metadados do MySQL.",
        ) . card(timeline($checks)),
    );
}
function page_admin_errors(): void
{

    require_can("admin_errors");
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "");
        if ($act !== "resolve_incident") {
            throw new ProntooHttpError(400, "Ação de incidente inválida.");
        }
        $id = (int) ($_POST["id"] ?? 0);
        if ($id <= 0) {
            flash("Incidente não informado.", "bad");
            redirect("admin_errors");
        }
        $note = trim((string) ($_POST["notes"] ?? ""));
        $updated = q(
            "UPDATE pi_error_events SET resolved_at=NOW(), notes=? WHERE id=? AND resolved_at IS NULL",
            [$note, $id],
        )->rowCount();
        if ($updated < 1) {
            flash("Incidente não encontrado ou já resolvido.", "bad");
            redirect("admin_errors");
        }
        audit("erro_marcado_resolvido", "erro", $id);
        flash("Erro marcado como resolvido.");
        redirect("admin_errors");
    }
    $open = (int) val(
        "SELECT COUNT(*) FROM pi_error_events WHERE resolved_at IS NULL",
    );
    $rows = q(
        "SELECT id,route,method,http_status,message,file,line,user_id,clinic_id,notes,resolved_at,created_at FROM pi_error_events WHERE resolved_at IS NULL ORDER BY id DESC LIMIT 80",
    )->fetchAll();
    if (count($rows) < 120) {
        $rows = array_merge(
            $rows,
            q(
                "SELECT id,route,method,http_status,message,file,line,user_id,clinic_id,notes,resolved_at,created_at FROM pi_error_events WHERE resolved_at IS NOT NULL ORDER BY id DESC LIMIT " .
                    (120 - count($rows)),
            )->fetchAll(),
        );
    }
    $items = [];
    foreach ($rows as $r) {
        $btn = $r["resolved_at"]
            ? ""
            : '<details class="inline"><summary class="ghost small cmdlike">' .
                action_summary_label("Resolver", "task_alt") .
                '</summary><form method="post" class="compact">' .
                csrf_field() .
                '<input type="hidden" name="act" value="resolve_incident">' .
                '<input type="hidden" name="id" value="' .
                (int) $r["id"] .
                '">' .
                form_row("Nota", textarea("notes")) .
                '<div class="form-actions"><button type="button" class="ghost small" data-close-panel>' .
                icon("close") .
                '<span>Cancelar</span></button><button type="submit" class="small">' .
                icon("task_alt") .
                "<span>Marcar resolvido</span></button></div></form></details>";
        $items[] = [
            "icon" => $r["resolved_at"] ? "bug_report" : "report",
            "time" => dt_br($r["created_at"]),
            "title" =>
                "#" .
                $r["id"] .
                " · " .
                ($r["route"] ?: "rota não informada") .
                " · HTTP " .
                ($r["http_status"] ?: "—"),
            "body" => $r["message"],
            "meta" =>
                ($r["file"] ?: "arquivo não informado") .
                ":" .
                ($r["line"] ?: "—") .
                ($r["resolved_at"]
                    ? " · resolvido em " . dt_br($r["resolved_at"])
                    : ""),
            "html" => $btn,
        ];
    }
    page(
        "Central de Instabilidades",
        page_head(
            "Central de Instabilidades",
            $open .
                " erro(s) aberto(s) registrados pelo Prontoo. Clique em cada item para identificar rota, arquivo, linha e mensagem.",
        ) . card(timeline($items, "Nenhum erro registrado.")),
    );
}
function onboarding_score(array $r): array
{

    $checks = [
        (int) $r["onboarding_done"] === 1,
        (int) $r["team"] > 0,
        (int) $r["doctors"] > 0,
        (int) $r["patients"] > 0,
        (int) $r["appointments"] > 0,
        (int) $r["tasks"] > 0,
    ];
    $done = count(array_filter($checks));
    return [$done, count($checks)];
}
function page_admin_onboarding(): void
{

    redirect("admin_clinics");
}
function page_admin_integrity(): void
{

    require_can("admin_integrity");
    $modelAuditWhere = admin_model_clinic_exclude_where("a.clinic_id");
    $modelScoped = admin_model_clinic_exclude_sql("clinic_id");
    $modelClinic = admin_model_clinic_exclude_sql("id");
    $recent = audit_rows_light($modelAuditWhere, [], 120);
    $bad = 0;
    foreach ($recent as $r) {
        if (!verify_audit_row($r)) {
            $bad++;
        }
    }
    $chainStatus = audit_chain_integrity_status(240);
    if (empty($chainStatus["ok"])) {
        $bad++;
    }
    $scopeViolations = (int) cached_val(
        "integrity_scope_actionable_7d_v2_model_" . admin_model_clinic_id(),
        120,
        "SELECT COUNT(*) FROM pi_scope_violations WHERE created_at>=DATE_SUB(NOW(), INTERVAL 7 DAY) AND violation_key<>'write_in_read_only' $modelScoped",
    );
    $crossClinic = (int) cached_val(
        "integrity_cross_clinic_links_model_" . admin_model_clinic_id(),
        300,
        "SELECT (SELECT COUNT(*) FROM pi_appointments a JOIN pi_patients p ON p.id=a.patient_link_id WHERE a.patient_link_id IS NOT NULL AND a.clinic_id<>p.clinic_id " .
            admin_model_clinic_exclude_sql("a.clinic_id") .
            ") + (SELECT COUNT(*) FROM pi_documents d JOIN pi_patients p ON p.id=d.patient_link_id WHERE d.patient_link_id IS NOT NULL AND d.clinic_id<>p.clinic_id " .
            admin_model_clinic_exclude_sql("d.clinic_id") .
            ") + (SELECT COUNT(*) FROM pi_care c JOIN pi_patients p ON p.id=c.patient_link_id WHERE c.clinic_id<>p.clinic_id " .
            admin_model_clinic_exclude_sql("c.clinic_id") .
            ") + (SELECT COUNT(*) FROM pi_task_details td JOIN pi_tasks t ON t.id=td.task_id WHERE td.clinic_id<>t.clinic_id " .
            admin_model_clinic_exclude_sql("td.clinic_id") .
            ") + (SELECT COUNT(*) FROM pi_task_comments tc JOIN pi_tasks t ON t.id=tc.task_id WHERE tc.clinic_id<>t.clinic_id " .
            admin_model_clinic_exclude_sql("tc.clinic_id") .
            ")",
    );
    $issues = [
        [
            "icon" => $scopeViolations ? "shield_lock" : "verified_user",
            "time" => "Isolamento",
            "title" => $scopeViolations
                ? $scopeViolations . " operação(ões) de escopo bloqueada(s) nos últimos 7 dias"
                : "Nenhuma operação de escopo bloqueada recentemente",
            "body" =>
                "A contagem exclui bloqueios de assinatura e não significa que dados tenham atravessado consultórios.",
            "meta" =>
                admin_model_clinic_count_note() .
                " · detalhes técnicos em Segurança",
        ],
        [
            "icon" => $crossClinic ? "hub" : "verified_user",
            "time" => "Vínculos",
            "title" => $crossClinic
                ? $crossClinic . " vínculo(s) cruzando consultórios"
                : "Sem vínculo cruzado detectado",
            "body" =>
                "Validação de consultas, documentos, prontuários e tarefas contra o consultório proprietário.",
            "meta" => "Integridade multi-consultório",
        ],
        [
            "icon" => $bad ? "gpp_bad" : "verified_user",
            "time" => "Atividades",
            "title" => $bad
                ? $bad . " assinatura(s) alterada(s)"
                : "Assinaturas recentes válidas",
            "body" =>
                "Amostra dos últimos 200 eventos auditáveis, descontando consultórios isentos do Desenvolvedor.",
            "meta" => "Integridade lógica",
        ],
        [
            "icon" => "person_off",
            "time" => "Colaboradores",
            "title" =>
                (int) cached_val(
                    "people_unlinked_blocked",
                    PRONTOO_ADMIN_CACHE_TTL,
                    "SELECT COUNT(*) FROM pi_users u WHERE u.is_global_admin=0 AND u.active=0 AND NOT EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.active=1 LIMIT 1)",
                ) . " colaboradores bloqueados sem vínculo ativo",
            "body" =>
                "O acesso é bloqueado quando não há vínculo ativo com consultório.",
            "meta" => "Verificar cadastro e vínculos",
        ],
        [
            "icon" => "home_health",
            "time" => "Consultórios",
            "title" =>
                (int) cached_val(
                    "integrity_clinics_no_manager_model_" .
                        admin_model_clinic_id(),
                    300,
                    "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND (manager_user_id IS NULL OR manager_user_id=0) $modelClinic",
                ) . " consultório(s) ativos sem gerente definido",
            "body" => "Afeta suporte e governança local.",
            "meta" => "Revisar responsável",
        ],
        [
            "icon" => "clinical_notes",
            "time" => "Agenda",
            "title" =>
                (int) cached_val(
                    "integrity_appt_no_patient_model_" .
                        admin_model_clinic_id(),
                    300,
                    "SELECT COUNT(*) FROM pi_appointments WHERE patient_link_id IS NULL AND start_at>=DATE_SUB(NOW(), INTERVAL 30 DAY) $modelScoped",
                ) . " agendamento(s) recente(s) sem paciente vinculado",
            "body" => "Indica inconsistência de vínculo.",
            "meta" => "Conferência operacional",
        ],
        [
            "icon" => "task_alt",
            "time" => "Tarefas",
            "title" =>
                (int) cached_val(
                    "integrity_tasks_late_model_" . admin_model_clinic_id(),
                    300,
                    "SELECT COUNT(*) FROM pi_tasks WHERE status='aberta' AND due_at IS NOT NULL AND due_at<NOW() $modelScoped",
                ) . " tarefa(s) atrasada(s)",
            "body" => "Não é erro técnico, mas sinal de operação parada.",
            "meta" => "Qualidade de uso",
        ],
    ];
    page(
        "Integridade",
        page_head(
            "Integridade",
            "Sinais de consistência dos dados e da operação.",
        ) . card(timeline($issues)),
    );
}
function page_admin_maintenance(): void
{

    $c = require_can("admin_maintenance");
    $uid = (int) ($c["user"]["id"] ?? 0);
    $tab = preg_replace(
        "/[^a-z_]/",
        "",
        (string) ($_GET["tab"] ?? ($_POST["tab"] ?? "manutencao")),
    );
    if (!in_array($tab, ["manutencao", "configuracoes"], true)) {
        $tab = "manutencao";
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "save_maintenance");
        if ($act === "save_maintenance") {
            meta_set("maintenance_active", isset($_POST["active"]) ? "1" : "0");
            meta_set(
                "maintenance_message",
                trim((string) ($_POST["message"] ?? "")) ?:
                "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.",
            );
            audit("manutencao_atualizada", "manutencao", null, $_POST);
            flash("Manutenção atualizada.");
            redirect("admin_maintenance", ["tab" => "manutencao"]);
        }
        if ($act === "save_settings") {
            foreach (
                ["support_email", "support_phone", "session_policy_note"]
                as $k
            ) {
                meta_set($k, trim((string) ($_POST[$k] ?? "")));
            }
            $adminTimezone = app_timezone_safe(
                (string) ($_POST["global_admin_timezone"] ??
                    app_global_admin_timezone($uid)),
            );
            meta_set("global_admin_timezone_user_" . $uid, $adminTimezone);
            app_apply_request_timezone($adminTimezone);
            $defaultPrice = max(
                0,
                (int) round(
                    ((float) str_replace(
                        ",",
                        ".",
                        (string) ($_POST["default_monthly_price"] ??
                            number_format(
                                default_monthly_price_cents() / 100,
                                2,
                                ".",
                                "",
                            )),
                    )) * 100,
                ),
            );
            if ($defaultPrice <= 0) {
                $defaultPrice = PRONTOO_MONTHLY_PRICE_CENTS;
            }
            $defaultTrialDays = max(
                0,
                min(
                    3650,
                    (int) ($_POST["default_trial_days"] ??
                        default_trial_days()),
                ),
            );
            meta_set("default_monthly_price_cents", (string) $defaultPrice);
            meta_set("default_trial_days", (string) $defaultTrialDays);
            meta_set(
                "subscription_pix_key",
                normalize_subscription_pix_key(
                    (string) ($_POST["subscription_pix_key"] ?? ""),
                ),
            );
            meta_set(
                "signup_clinics_blocked",
                isset($_POST["signup_clinics_blocked"]) ? "1" : "0",
            );
            audit("config_global_atualizada", "configuracao", null, [
                "campos" => [
                    "suporte",
                    "pix_assinatura",
                    "regra_comercial",
                    "bloqueio_cadastros",
                    "fuso_administrador",
                    "politica_sessao",
                ],
                "price_cents" => $defaultPrice,
                "trial_days" => $defaultTrialDays,
                "global_admin_timezone" => $adminTimezone,
                "audit_body" =>
                    "Configurações globais da plataforma atualizadas.",
            ]);
            flash("Configurações globais salvas.");
            redirect("admin_maintenance", ["tab" => "configuracoes"]);
        }
    }
    $tabs =
        '<nav class="notice-filter-chips lead-filter-chips admin-maintenance-tabs" aria-label="Operações de manutenção"><a class="lead-chip ds-filter-chip ' .
        ($tab === "manutencao" ? "active" : "") .
        '" href="' .
        e(href("admin_maintenance", ["tab" => "manutencao"])) .
        '">' .
        icon("construction") .
        '<span class="lead-chip-label">Manutenção</span></a><a class="lead-chip ds-filter-chip ' .
        ($tab === "configuracoes" ? "active" : "") .
        '" href="' .
        e(href("admin_maintenance", ["tab" => "configuracoes"])) .
        '">' .
        icon("settings") .
        '<span class="lead-chip-label">Configurações</span></a></nav>';
    if ($tab === "configuracoes") {
        $defaultPrice = default_monthly_price_cents();
        $defaultTrialDays = default_trial_days();
        $signupBlocked = function_exists("clinic_signup_blocked")
            ? clinic_signup_blocked()
            : meta_get("signup_clinics_blocked", "0") === "1";
        $adminTimezone = app_global_admin_timezone($uid);
        $timezoneNow = app_now_in_timezone(0, [
            "scope" => "global",
            "timezone" => $adminTimezone,
        ])->format("d/m/Y \à\s H\hi");
        $form =
            '<form method="post" class="compact">' .
            csrf_field() .
            '<input type="hidden" name="tab" value="configuracoes"><input type="hidden" name="act" value="save_settings"><section class="settings-section full"><h2>Suporte</h2>' .
            form_row(
                "E-mail de suporte",
                input("support_email", "email", meta_get("support_email", "")),
            ) .
            form_row(
                "Telefone/WhatsApp de suporte",
                input("support_phone", "text", meta_get("support_phone", "")),
            ) .
            '</section><section class="settings-section full"><h2>Preferências do Desenvolvedor</h2><p class="field-help">Este fuso é aplicado apenas à sua credencial global. Consultórios continuam usando o fuso da cidade cadastrada.</p>' .
            select_label(
                "Meu fuso horário",
                "global_admin_timezone",
                admin_global_timezone_options(),
                $adminTimezone,
                "required",
            ) .
            '<p class="field-help">Agora para você: ' .
            e($timezoneNow) .
            '</p></section><section class="settings-section full"><h2>Assinatura</h2><p class="field-help">Regra comercial padrão dos novos consultórios e chave Pix exibida no card de pagamento.</p><div class="two">' .
            form_row(
                "Mensalidade padrão",
                input(
                    "default_monthly_price",
                    "number",
                    number_format($defaultPrice / 100, 2, ".", ""),
                    'step="0.01" min="0"',
                ),
            ) .
            form_row(
                "Dias de gratuidade",
                input(
                    "default_trial_days",
                    "number",
                    (string) $defaultTrialDays,
                    'min="0" max="3650" step="1"',
                ),
            ) .
            "</div>" .
            form_row(
                "Chave Pix da assinatura",
                input(
                    "subscription_pix_key",
                    "text",
                    subscription_pix_key(),
                    'required maxlength="140" placeholder="Ex.: pix@prontoo.app"',
                ),
            ) .
            '<label class="check-row admin-signup-lock"><input type="checkbox" name="signup_clinics_blocked" value="1" ' .
            ($signupBlocked ? "checked" : "") .
            '><span><b>Bloquear temporariamente novos consultórios</b><small>Quando ativo, os botões públicos de criação de consultório ficam ocultos e a rota de cadastro exibe aviso de pausa.</small></span></label></section><section class="settings-section full"><h2>Segurança</h2>' .
            form_row(
                "Nota interna de política de sessão",
                textarea(
                    "session_policy_note",
                    meta_get(
                        "session_policy_note",
                        "Sessões administrativas devem ser encerradas ao final do uso.",
                    ),
                ),
            ) .
            '</section><button type="submit" class="primary">' .
            icon("save") .
            "<span>Salvar configurações</span></button></form>";
        page(
            "Manutenção e Configurações",
            page_head("Manutenção e Configurações", "") . $tabs . card($form),
        );
        return;
    }
    $active = maintenance_active();
    $msg = (string) meta_get(
        "maintenance_message",
        "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.",
    );
    $form =
        '<form method="post" class="compact">' .
        csrf_field() .
        '<input type="hidden" name="tab" value="manutencao"><input type="hidden" name="act" value="save_maintenance"><label class="check"><input type="checkbox" name="active" ' .
        ($active ? "checked" : "") .
        "> Ativar manutenção para colaboradores não globais</label>" .
        form_row("Mensagem pública de manutenção", textarea("message", $msg)) .
        '<button type="submit" class="primary">' .
        icon("save") .
        "<span>Salvar manutenção</span></button></form>";
    $items = [
        [
            "icon" => $active ? "engineering" : "check_circle",
            "time" => "Agora",
            "title" => $active
                ? "Modo manutenção ativo"
                : "Modo manutenção inativo",
            "body" => $msg,
            "meta" => "A tela de login permanece acessível.",
        ],
    ];
    page(
        "Manutenção e Configurações",
        page_head("Manutenção e Configurações", "") .
            $tabs .
            card($form) .
            card(timeline($items)),
    );
}
function page_admin_settings(): void
{

    redirect("admin_maintenance", ["tab" => "configuracoes"]);
}
function page_admin_painel(): void
{

    require_can("admin_painel");
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "");
        if ($act === "goal") {
            $goalContext = ctx();
            $cid = (int) ($goalContext["clinic_id"] ?? 0);
            $uid = (int) ($goalContext["user"]["id"] ?? 0);
            if ($cid <= 0 || $uid <= 0) {
                throw new RuntimeException(
                    "A meta mensal exige um consultório ativo.",
                );
            }
            $target = parse_money_cents((string) ($_POST["target"] ?? "0"));
            $share = isset($_POST["share_with_team"]) ? 1 : 0;
            $base = (string) ($_POST["base_metric"] ?? "efetivada");
            if (!in_array($base, ["prevista", "efetivada"], true)) {
                $base = "efetivada";
            }
            $month = app_month_in_timezone($cid, $goalContext);
            q(
                "INSERT INTO pi_financial_goals (clinic_id,month_key,target_cents,base_metric,share_with_team,updated_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_cents=VALUES(target_cents), base_metric=VALUES(base_metric), share_with_team=VALUES(share_with_team), updated_by=VALUES(updated_by), updated_at=NOW()",
                [$cid, $month, $target, $base, $share, $uid],
            );
            audit("meta_financeira_salva", "financeiro", $cid, [
                "valor" => $target,
                "base" => $base,
                "compartilhar" => $share,
            ]);
            flash("Meta mensal atualizada.");
            redirect("financial", ["tab" => "meta"]);
        }
        if (
            in_array(
                $act,
                ["confirm_subscription_payment", "reject_subscription_payment"],
                true,
            )
        ) {
            $pid = (int) ($_POST["payment_id"] ?? 0);
            $p =
                $pid > 0
                    ? one(
                        "SELECT sp.*,c.display_name FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.id=? AND sp.status='pending_admin'",
                        [$pid],
                    )
                    : null;
            if (!$p) {
                flash(
                    "Pedido de assinatura não encontrado ou já analisado.",
                    "bad",
                );
                redirect("admin_painel");
            }
            $adminId = (int) (ctx()["user"]["id"] ?? 0);
            $cid = (int) $p["clinic_id"];
            $hadProof = trim((string) ($p["proof_path"] ?? "")) !== "";
            if ($act === "confirm_subscription_payment") {
                $reviewNote = $hadProof
                    ? "Comprovante aprovado."
                    : "Recebimento confirmado.";
                if ($hadProof) {
                    subscription_payment_delete_proof(
                        (string) $p["proof_path"],
                    );
                }
                q(
                    "UPDATE pi_subscription_payments SET status='confirmed', reviewed_by=?, reviewed_at=NOW(), review_note=?, proof_path=NULL WHERE id=? AND clinic_id=?",
                    [$adminId, $reviewNote, $pid, $cid],
                );
                q(
                    "UPDATE pi_clinics SET active=1, subscription_status='active', paid_until=COALESCE(?,paid_until), subscription_trust_blocked_until=NULL, subscription_last_payment_claim_at=NULL, updated_at=NOW() WHERE id=?",
                    [$p["applied_until"] ?: null, $cid],
                );
                audit(
                    $hadProof
                        ? "assinatura_comprovante_aprovado"
                        : "assinatura_pagamento_confirmado",
                    "assinatura",
                    $cid,
                    [
                        "pagamento_id" => $pid,
                        "audit_body" => $hadProof
                            ? "Desenvolvedor aprovou o comprovante enviado. A assinatura foi ativada de forma definitiva."
                            : "Desenvolvedor confirmou o pagamento informado. A assinatura foi ativada de forma definitiva.",
                    ],
                );
                if ($hadProof) {
                    audit(
                        "assinatura_comprovante_excluido",
                        "assinatura",
                        $cid,
                        [
                            "pagamento_id" => $pid,
                            "audit_body" =>
                                "Após a aprovação, o comprovante enviado foi excluído dos registros operacionais da assinatura.",
                        ],
                    );
                }
                flash(
                    $hadProof
                        ? "Comprovante aprovado. A assinatura foi ativada de forma definitiva."
                        : "Pagamento confirmado. A assinatura foi ativada de forma definitiva.",
                );
                redirect("admin_painel");
            }
            $reviewNote = $hadProof
                ? "Comprovante recusado."
                : "Recebimento não confirmado.";
            q(
                "UPDATE pi_subscription_payments SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=? AND clinic_id=?",
                [$adminId, $reviewNote, $pid, $cid],
            );
            $trustBlockedUntil = app_storage_timestamp(
                "2099-12-31 23:59:59",
            );
            q(
                "UPDATE pi_clinics SET subscription_status='read_only', paid_until=CURDATE(), subscription_trust_blocked_until=?, updated_at=NOW() WHERE id=?",
                [$trustBlockedUntil, $cid],
            );
            clinic_subscription_rejected_notice($cid, $hadProof);
            audit(
                $hadProof
                    ? "assinatura_comprovante_recusado"
                    : "assinatura_pagamento_nao_confirmado",
                "assinatura",
                $cid,
                [
                    "pagamento_id" => $pid,
                    "audit_body" => $hadProof
                        ? "Desenvolvedor recusou o comprovante enviado. Consultório retornou para Somente Leitura e poderá enviar novo comprovante."
                        : "Desenvolvedor recusou o pagamento informado. Consultório retornou para Somente Leitura e exigirá comprovante.",
                ],
            );
            flash(
                $hadProof
                    ? "Comprovante recusado. O consultório voltou para Somente Leitura e poderá enviar novo comprovante."
                    : "Pagamento recusado. O consultório voltou para Somente Leitura e a clínica recebeu aviso.",
            );
            redirect("admin_painel");
        }
    }
    $qInt = function (string $sql, array $p = []): int {

        return (int) safe_val($sql, $p, 0);
    };
    $modelClinicWhere = admin_model_clinic_exclude_sql("id");
    $modelAuditWhere = admin_model_clinic_exclude_sql("clinic_id");
    $readOnly = $qInt(
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND (subscription_status='read_only' OR (paid_until IS NOT NULL AND paid_until<CURDATE())) $modelClinicWhere",
    );
    $trialEnding = $qInt(
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status='trial' AND trial_ends_at IS NOT NULL AND trial_ends_at>=NOW() AND trial_ends_at<DATE_ADD(NOW(), INTERVAL 7 DAY) $modelClinicWhere",
    );
    $onboardingPending = $qInt(
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND onboarding_done=0 $modelClinicWhere",
    );
    $activeUsers24h = $qInt(
        "SELECT COUNT(DISTINCT user_id) FROM pi_audit WHERE user_id IS NOT NULL AND created_at>=DATE_SUB(NOW(), INTERVAL 24 HOUR) $modelAuditWhere",
    );
    $performance24h = function_exists("telemetry_route_performance_summary")
        ? telemetry_route_performance_summary(24)
        : ["routes" => [], "total" => 0];
    $requests24h = max(0, (int) ($performance24h["total"] ?? 0));
    $averageResponseMs = max(0.0, (float) ($performance24h["avg_ms"] ?? 0));
    $landingRequests24h = 0;
    foreach ((array) ($performance24h["routes"] ?? []) as $routePerformance) {
        if ((string) ($routePerformance["route"] ?? "") !== "landing") {
            continue;
        }
        $landingRequests24h = max(
            0,
            (int) ($routePerformance["count"] ?? 0),
        );
        break;
    }
    $locks = $qInt(
        "SELECT COUNT(*) FROM pi_login_locks WHERE locked_until>NOW()",
    );
    $openErrors = $qInt(
        "SELECT COUNT(*) FROM pi_error_events WHERE resolved_at IS NULL",
    );
    $errors24h = $qInt(
        "SELECT COUNT(*) FROM pi_error_events WHERE created_at>=DATE_SUB(NOW(), INTERVAL 24 HOUR)",
    );
    $scopeStats24h = admin_scope_guard_stats(24);
    $scopeViolations24h = (int) $scopeStats24h["actionable"];
    $scopeGroups24h = $scopeViolations24h > 0
        ? admin_scope_guard_groups(24, 12)
        : [];
    $checks = platform_backend_selftest([
        "open_errors" => $openErrors,
        "login_locks" => $locks,
        "scope_alerts_24h" => $scopeViolations24h,
    ]);
    $actions = [];
    try {
        $pendingModelWhere = admin_model_clinic_exclude_sql("sp.clinic_id");
        $pending = q(
            "SELECT sp.id,sp.clinic_id,sp.amount_cents,sp.account_self,sp.account_holder_name,sp.proof_path,sp.applied_until,sp.created_at,c.display_name,(SELECT COUNT(*) FROM pi_subscription_payments spr WHERE spr.clinic_id=sp.clinic_id AND spr.status='rejected') AS rejected_count FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.status='pending_admin' $pendingModelWhere ORDER BY sp.created_at ASC LIMIT 20",
        )->fetchAll();
        foreach ($pending as $p) {
            $hasProof = trim((string) ($p["proof_path"] ?? "")) !== "";
            $hadRejected = (int) ($p["rejected_count"] ?? 0) > 0;
            $holder =
                (int) $p["account_self"] === 1
                    ? "Conta própria"
                    : "Titular: " .
                        ($p["account_holder_name"] ?: "não informado");
            $view = subscription_payment_proof_view_link($p);
            $isProofReview = subscription_payment_is_proof_review($p);
            $confirmLabel = $isProofReview
                ? "Aprovar comprovante"
                : "Confirmar pagamento";
            $confirmIcon = $isProofReview ? "verified" : "check_circle";
            $rejectLabel = $isProofReview ? "Recusar comprovante" : "Recusar";
            $rejectQuestion = $isProofReview
                ? "Recusar este comprovante?"
                : "Recusar este pagamento informado?";
            $forms =
                $view .
                '<form method="post" class="inline">' .
                csrf_field() .
                '<input type="hidden" name="act" value="confirm_subscription_payment"><input type="hidden" name="payment_id" value="' .
                (int) $p["id"] .
                '"><button class="primary small" type="submit">' .
                action_summary_label($confirmLabel, $confirmIcon) .
                '</button></form><form method="post" class="inline" onsubmit="return confirm(&quot;' .
                e($rejectQuestion) .
                '&quot;)">' .
                csrf_field() .
                '<input type="hidden" name="act" value="reject_subscription_payment"><input type="hidden" name="payment_id" value="' .
                (int) $p["id"] .
                '"><button class="danger small" type="submit">' .
                action_summary_label($rejectLabel, "block") .
                "</button></form>";
            if ($isProofReview) {
                $actions[] = [
                    "icon" => "upload_file",
                    "time" => "Assinatura",
                    "title" =>
                        "Visualizar e aprovar comprovante de " .
                        ($p["display_name"] ?? "consultório"),
                    "body" =>
                        "O consultório enviou comprovante após um pagamento recusado. Abra o arquivo antes de aprovar ou recusar. Valor informado: " .
                        money_br((int) $p["amount_cents"]) .
                        " · " .
                        $holder,
                    "meta" =>
                        "Ao aprovar, a assinatura fica ativa de forma definitiva. Ao recusar, o consultório volta para Somente Leitura.",
                    "html" => $forms,
                    "class" => "subscription-action proof-review",
                ];
            } else {
                $actions[] = [
                    "icon" => "payments",
                    "time" => "Assinatura",
                    "title" =>
                        "Confirmar pagamento informado por " .
                        ($p["display_name"] ?? "consultório"),
                    "body" =>
                        "Valor informado: " .
                        money_br((int) $p["amount_cents"]) .
                        " · " .
                        $holder,
                    "meta" =>
                        "Ao confirmar, a assinatura fica ativa de forma definitiva. Ao recusar, o consultório volta para Somente Leitura e deverá enviar comprovante.",
                    "html" => $forms,
                    "class" => "subscription-action",
                ];
            }
        }
    } catch (Throwable $e) {
        error_log(
            "[Prontoo pending subscription payments] " . $e->getMessage(),
        );
    }
    if (empty($checks["database"])) {
        $actions[] = [
            "icon" => "database_off",
            "time" => "Banco",
            "title" => "Banco de dados indisponível no autoteste",
            "body" =>
                "A plataforma não conseguiu confirmar a conexão básica com o banco.",
            "meta" => "Incidente técnico crítico.",
        ];
    }
    if (empty($checks["storage"])) {
        $actions[] = [
            "icon" => "folder_off",
            "time" => "Arquivos",
            "title" => "Storage sem permissão de escrita",
            "body" =>
                "Arquivos temporários, métricas e comprovantes dependem de escrita em /ssd.",
            "meta" => "Verifique permissões.",
        ];
    }
    if ($openErrors > 0) {
        $actions[] = [
            "icon" => "bug_report",
            "time" => "Erros",
            "title" => $openErrors . " erro(s) aberto(s)",
            "body" => "Há eventos técnicos sem resolução registrada.",
            "meta" => $errors24h . " nas últimas 24h.",
        ];
    }
    if ($locks > 0) {
        $actions[] = [
            "icon" => "lock_clock",
            "time" => "Segurança",
            "title" => $locks . " bloqueio(s) de login ativo(s)",
            "body" =>
                "Confirme se são usuários reais com dificuldade ou tentativa indevida.",
            "meta" => "Use o painel de Segurança.",
        ];
    }
    if ($scopeViolations24h > 0) {
        $patterns = max(1, (int) ($scopeStats24h["patterns"] ?? 0));
        $objective = (int) ($scopeStats24h["objective"] ?? 0);
        $review = (int) ($scopeStats24h["review"] ?? 0);
        $latest = $scopeGroups24h[0] ?? [];
        $latestDefinition = $latest
            ? admin_scope_guard_definition(
                (string) ($latest["violation_key"] ?? ""),
            )
            : [];
        $detailsHtml = $latest
            ? admin_scope_evidence_html($latest, true)
            : "";
        $detailsHtml .=
            '<a class="ghost small" href="' .
            href("admin_security") .
            '#scope-isolation">Abrir todas as evidências em Segurança</a>';
        $actions[] = [
            "icon" => "policy",
            "time" => "Isolamento",
            "title" =>
                $patterns .
                " padrão(ões) de escopo bloqueado(s) nas últimas 24h",
            "body" =>
                "As " .
                $scopeViolations24h .
                " ocorrência(s) foram interrompidas antes da execução SQL. " .
                $objective .
                " têm causa estrutural demonstrável e " .
                $review .
                " exigem revisão porque a prova lógica foi insuficiente." .
                ($latestDefinition
                    ? " Mais recente: " .
                        (string) $latestDefinition["cause"]
                    : ""),
            "meta" =>
                "O agrupamento por fingerprint reduz duplicidade; esta contagem não confirma acesso cruzado.",
            "html" => $detailsHtml,
            "class" => "scope-recommended-action",
        ];
    }
    if ($readOnly > 0) {
        $actions[] = [
            "icon" => "payments",
            "time" => "Assinaturas",
            "title" =>
                $readOnly . " consultório(s) em somente leitura ou vencido(s)",
            "body" =>
                "O acesso operacional pode estar limitado por assinatura.",
            "meta" => "Impacta agenda, financeiro e rotina dos consultórios.",
        ];
    }
    if ($trialEnding > 0) {
        $actions[] = [
            "icon" => "hourglass_top",
            "time" => "Assinaturas",
            "title" =>
                $trialEnding . " assinatura(s) iniciais vencendo em até 7 dias",
            "body" => "São consultórios próximos da decisão de contratação.",
            "meta" => "Sinal de conversão ou risco de perda.",
        ];
    }
    if ($onboardingPending > 0) {
        $actions[] = [
            "icon" => "playlist_add_check",
            "time" => "Onboarding",
            "title" =>
                $onboardingPending .
                " consultório(s) ainda sem onboarding concluído",
            "body" => "A configuração inicial incompleta reduz adoção.",
            "meta" =>
                "Revise dados do consultório, cargos, procedimentos e agenda.",
        ];
    }
    $charts = admin_performance_card_html();
    $telemetry =
        '<div class="stats-grid admin-overview-kpis global-telemetry-grid">' .
        stat_card(
            "Requisições",
            $requests24h,
            "route",
            "últimas 24 horas",
        ) .
        stat_link_card(
            "Tempo Médio",
            $averageResponseMs > 0
                ? admin_performance_format_ms($averageResponseMs)
                : "—",
            "speed",
            "resposta nas últimas 24 horas",
            "admin_performance",
        ) .
        stat_link_card(
            "Landing Page",
            $landingRequests24h,
            "web",
            "requisições nas últimas 24 horas",
            "admin_performance",
        ) .
        stat_card(
            "Usuários Ativos",
            $activeUsers24h,
            "person_check",
            "últimas 24 horas",
        ) .
        "</div>";
    $actionsCard = $actions
        ? card(
            "<h2>Ações recomendadas</h2>" . timeline($actions),
            "priority-actions",
        )
        : "";
    $body =
        page_head("Desenvolvedor Prontoo", "") .
        card(
            "<h2>Telemetria do sistema</h2>" . $telemetry,
            "admin-telemetry-card",
        ) .
        $charts .
        $actionsCard;
    page("Desenvolvedor Prontoo", $body);
}
function page_admin_people(): void
{

    require_can("admin_users");
    $rows = q(
        "SELECT u.id,u.name,u.email,u.active,u.is_global_admin,p.cpf,p.birth_date,COUNT(ur.id) AS vinculos FROM pi_users u JOIN pi_persons p ON p.id=u.person_id LEFT JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.active=1 GROUP BY u.id,u.name,u.email,u.active,u.is_global_admin,p.cpf,p.birth_date ORDER BY u.name ASC LIMIT 200",
    )->fetchAll();
    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            "icon" => (int) $r["active"] ? "person" : "person_off",
            "time" => (int) $r["is_global_admin"] ? "Desenvolvedor" : "Usuário",
            "title" => (string) $r["name"],
            "body" =>
                "CPF " .
                mask((string) ($r["cpf"] ?? "")) .
                " · " .
                ((int) $r["vinculos"]) .
                " vínculo(s) com consultórios",
            "meta" => trim((string) ($r["email"] ?? "")) ?: "sem e-mail",
        ];
    }
    page(
        "Usuários",
        page_head(
            "Usuários",
            "Credenciais, pessoas cadastradas e vínculos ativos na plataforma.",
        ) .
            card(
                timeline($items, "Nenhuma pessoa cadastrada."),
                "admin-people-card",
            ),
    );
}
function page_admin_users(): void
{

    redirect("admin_people");
}
function page_admin_stats(): void
{

    redirect("admin_painel");
}
function onboarding_use_icon(bool $ok, string $label): string
{

    return '<span class="onboard-cell ' .
        ($ok ? "ok" : "bad") .
        '" title="' .
        e($label . ": " . ($ok ? "usado" : "pendente")) .
        '" aria-label="' .
        e($label . ": " . ($ok ? "usado" : "pendente")) .
        '">' .
        icon($ok ? "check_circle" : "radio_button_unchecked") .
        "</span>";
}
function onboarding_progress_bar(array $used): string
{

    $total = 6;
    $labels = [
        "Cadastro",
        "Equipe",
        "Profissional",
        "Paciente",
        "Agenda",
        "Tarefa",
    ];
    $done = 0;
    $segments = "";
    for ($i = 0; $i < $total; $i++) {
        $ok = !empty($used[$i]);
        if ($ok) {
            $done++;
        }
        $label = $labels[$i] ?? "Etapa " . ($i + 1);
        $segments .=
            '<span class="onboard-stage ' .
            ($ok ? "is-done" : "is-empty") .
            '" title="' .
            e(
                $i +
                    1 .
                    "/" .
                    $total .
                    " · " .
                    $label .
                    ": " .
                    ($ok ? "concluída" : "pendente"),
            ) .
            '" aria-hidden="true"></span>';
    }
    return '<span class="onboard-progress" role="img" aria-label="' .
        e("Onboard: " . $done . " de " . $total . " etapas concluídas") .
        '" title="' .
        e("Onboard: " . $done . " de " . $total . " etapas concluídas") .
        '">' .
        $segments .
        "<b>" .
        e($done . "/" . $total) .
        "</b></span>";
}
function admin_clinic_detail_item(
    string $iconName,
    string $label,
    string $value,
    string $note = "",
): string {

    $value = trim($value) !== "" ? $value : "Não informado";
    return '<div class="admin-clinic-detail-item"><span class="admin-clinic-detail-item-icon">' .
        icon($iconName) .
        '</span><div><small>' .
        e($label) .
        '</small><b>' .
        e($value) .
        '</b>' .
        ($note !== "" ? '<span>' . e($note) . '</span>' : "") .
        '</div></div>';
}
function admin_clinic_detail_page(int $id): void
{

    $clinic = one(
        "SELECT c.*,ou.name AS owner_name,ou.email AS owner_email,ou.active AS owner_active,ou.last_login_at AS owner_last_login_at,ou.created_at AS owner_created_at,op.full_name AS owner_person_name,op.cpf AS owner_cpf,op.birth_date AS owner_birth_date,op.phone AS owner_phone,op.email AS owner_person_email,op.address AS owner_address,op.address_number AS owner_address_number,op.address_neighborhood AS owner_address_neighborhood,op.address_complement AS owner_address_complement,op.address_city AS owner_address_city,op.address_state AS owner_address_state,mu.name AS manager_name,mu.email AS manager_email FROM pi_clinics c JOIN pi_users ou ON ou.id=c.owner_user_id JOIN pi_persons op ON op.id=ou.person_id LEFT JOIN pi_users mu ON mu.id=c.manager_user_id WHERE c.id=?",
        [$id],
    );
    if (!$clinic) {
        flash("Consultório não encontrado.", "bad");
        redirect("admin_clinics");
    }

    $billing = billing_state($clinic);
    $status = (string) ($billing["status"] ?? "active");
    $currentStatus = in_array($status, ["active", "read_only", "exempt"], true)
        ? $status
        : (!empty($billing["exempt"])
            ? "exempt"
            : (!empty($billing["read_only"])
                ? "read_only"
                : "active"));
    $statusLabel = (int) $clinic["active"] !== 1
        ? "Inativo"
        : ([
            "active" => "Ativo",
            "read_only" => "Somente leitura",
            "exempt" => "Isento",
            "trial" => "Período gratuito",
            "cancelled" => "Cancelado",
            "suspended" => "Suspenso",
        ][$status] ?? ucfirst($status));
    $statusIcon = !empty($billing["exempt"])
        ? "workspace_premium"
        : (!empty($billing["read_only"])
            ? "lock"
            : ((int) $clinic["active"] === 1
                ? "verified"
                : "pause_circle"));
    $statusClass = !empty($billing["exempt"])
        ? "is-exempt"
        : (!empty($billing["read_only"])
            ? "is-critical"
            : ((int) $clinic["active"] === 1
                ? "is-stable"
                : "is-muted"));

    $team = (int) val(
        "SELECT COUNT(*) FROM pi_user_roles WHERE clinic_id=? AND active=1",
        [$id],
    );
    $professionals = (int) val(
        "SELECT COUNT(*) FROM pi_user_roles WHERE clinic_id=? AND role_code='medico' AND active=1",
        [$id],
    );
    $roleRows = q(
        "SELECT role_code FROM pi_user_roles WHERE clinic_id=? AND user_id=? AND active=1 ORDER BY role_code",
        [$id, (int) $clinic["owner_user_id"]],
    )->fetchAll();
    $roleLabels = [];
    foreach ($roleRows as $roleRow) {
        $code = (string) ($roleRow["role_code"] ?? "");
        $roleLabels[] = PRONTOO_ROLES[$code] ?? $code;
    }
    $rolesText = $roleLabels ? implode(" · ", array_unique($roleLabels)) : "Sem cargo ativo";

    $clinicDocument = trim((string) ($clinic["legal_document"] ?? ""));
    if ($clinicDocument !== "" && (string) ($clinic["legal_type"] ?? "") === "cpf") {
        $clinicDocument = cpf_br($clinicDocument);
    } elseif (preg_match('/^\d{14}$/', $clinicDocument)) {
        $clinicDocument = preg_replace(
            '/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/',
            '$1.$2.$3/$4-$5',
            $clinicDocument,
        ) ?: $clinicDocument;
    }
    $addressParts = array_filter([
        trim((string) ($clinic["address_line"] ?? "")),
        trim((string) ($clinic["address_city"] ?? "")),
        trim((string) ($clinic["address_state"] ?? "")),
    ]);
    $clinicAddress = implode(" · ", $addressParts);
    $ownerAddressParts = array_filter([
        trim((string) ($clinic["owner_address"] ?? "")) .
            (trim((string) ($clinic["owner_address_number"] ?? "")) !== ""
                ? ", " . trim((string) $clinic["owner_address_number"])
                : ""),
        trim((string) ($clinic["owner_address_neighborhood"] ?? "")),
        trim((string) ($clinic["owner_address_city"] ?? "")),
        trim((string) ($clinic["owner_address_state"] ?? "")),
    ]);
    $ownerAddress = implode(" · ", $ownerAddressParts);
    $ownerEmail = trim((string) ($clinic["owner_person_email"] ?? "")) ?:
        trim((string) ($clinic["owner_email"] ?? ""));
    $ownerName = trim((string) ($clinic["owner_person_name"] ?? "")) ?:
        trim((string) ($clinic["owner_name"] ?? ""));
    $dueLabel = !empty($billing["exempt"])
        ? "Isento"
        : (trim((string) ($billing["paid_until"] ?? "")) !== ""
            ? date_br($billing["paid_until"])
            : (!empty($billing["trial_active"])
                ? date_br($billing["trial_ends_at"] ?? "")
                : "Sem vencimento"));

    $hero =
        '<div class="admin-clinic-detail-toolbar"><a class="ghost small cmdlike" href="' .
        e(href("admin_clinics")) .
        '">' .
        icon("arrow_back") .
        '<span>Voltar aos consultórios</span></a></div><section class="admin-clinic-detail-hero ' .
        e($statusClass) .
        '"><span class="admin-clinic-detail-hero-icon">' .
        icon("home_health") .
        '</span><div class="admin-clinic-detail-hero-copy"><span class="eyebrow">Consultório #' .
        $id .
        '</span><h2>' .
        e((string) $clinic["display_name"]) .
        '</h2><p>' .
        e((string) $clinic["legal_name"]) .
        '</p></div><span class="clinic-attention-chip ' .
        e($statusClass) .
        '">' .
        icon($statusIcon) .
        '<b>' .
        e($statusLabel) .
        '</b></span></section>';

    $clinicData =
        '<div class="section-head"><div><span class="eyebrow">Cadastro</span><h2>Dados do consultório</h2></div></div><div class="admin-clinic-detail-grid">' .
        admin_clinic_detail_item("badge", "Razão social", (string) $clinic["legal_name"]) .
        admin_clinic_detail_item("storefront", "Nome de exibição", (string) $clinic["display_name"]) .
        admin_clinic_detail_item("id_card", strtoupper((string) $clinic["legal_type"]), $clinicDocument) .
        admin_clinic_detail_item("call", "Telefone", phone_br((string) ($clinic["phone"] ?? ""))) .
        admin_clinic_detail_item("stethoscope", "Área profissional", (string) $clinic["responsible_profession"]) .
        admin_clinic_detail_item("location_on", "Endereço", $clinicAddress) .
        admin_clinic_detail_item("schedule", "Fuso horário", (string) $clinic["timezone"]) .
        admin_clinic_detail_item("event", "Criado em", date_br($clinic["created_at"] ?? "")) .
        admin_clinic_detail_item("groups", "Equipe ativa", (string) $team, $professionals . " profissional(is)") .
        admin_clinic_detail_item(
            "checklist",
            "Configuração inicial",
            (int) $clinic["onboarding_done"] === 1 ? "Concluída" : "Pendente",
        ) .
        '</div>';

    $responsibleData =
        '<div class="section-head"><div><span class="eyebrow">Responsável</span><h2>Responsável pelo consultório</h2></div></div><div class="admin-clinic-detail-grid">' .
        admin_clinic_detail_item("person", "Nome", $ownerName) .
        admin_clinic_detail_item("fingerprint", "CPF", cpf_br((string) ($clinic["owner_cpf"] ?? ""))) .
        admin_clinic_detail_item("cake", "Data de nascimento", date_br($clinic["owner_birth_date"] ?? "")) .
        admin_clinic_detail_item("mail", "E-mail", $ownerEmail) .
        admin_clinic_detail_item("call", "Telefone", phone_br((string) ($clinic["owner_phone"] ?? ""))) .
        admin_clinic_detail_item("work", "Cargos no consultório", $rolesText) .
        admin_clinic_detail_item(
            "verified_user",
            "Conta",
            (int) $clinic["owner_active"] === 1 ? "Ativa" : "Inativa",
            trim((string) ($clinic["owner_last_login_at"] ?? "")) !== ""
                ? "Último acesso: " . dt_br($clinic["owner_last_login_at"])
                : "Ainda não acessou",
        ) .
        admin_clinic_detail_item("home", "Endereço do responsável", $ownerAddress) .
        '</div>';
    if ((int) $clinic["manager_user_id"] !== (int) $clinic["owner_user_id"]) {
        $responsibleData .=
            '<div class="admin-clinic-manager-note">' .
            icon("manage_accounts") .
            '<div><small>Gestor cadastrado</small><b>' .
            e((string) ($clinic["manager_name"] ?? "")) .
            '</b><span>' .
            e((string) ($clinic["manager_email"] ?? "")) .
            '</span></div></div>';
    }

    $hidden = '<input type="hidden" name="id" value="' .
        $id .
        '"><input type="hidden" name="return_clinic_id" value="' .
        $id .
        '">';
    $operationActions =
        '<div class="admin-clinic-action-block"><div class="admin-clinic-action-copy">' .
        icon("power_settings_new") .
        '<div><b>Operação do consultório</b><span>Ative ou desative o acesso operacional sem apagar o cadastro.</span></div></div><form method="post">' .
        csrf_field() .
        $hidden .
        '<button class="' .
        ((int) $clinic["active"] === 1 ? "danger-soft" : "primary") .
        '" type="submit">' .
        icon((int) $clinic["active"] === 1 ? "toggle_off" : "toggle_on") .
        '<span>' .
        e((int) $clinic["active"] === 1 ? "Desativar consultório" : "Ativar consultório") .
        '</span></button></form></div>';
    $subscriptionActions =
        '<div class="admin-clinic-action-block"><div class="admin-clinic-action-copy">' .
        icon("verified") .
        '<div><b>Ações rápidas da assinatura</b><span>Atualize imediatamente o estado comercial do consultório.</span></div></div><div class="admin-clinic-action-buttons"><form method="post">' .
        csrf_field() .
        $hidden .
        '<input type="hidden" name="act" value="activate_subscription"><button class="primary" type="submit">' .
        icon("verified") .
        '<span>Definir como Ativo</span></button></form><form method="post" onsubmit="return confirm(&quot;Colocar este consultório em Somente leitura?&quot;)">' .
        csrf_field() .
        $hidden .
        '<input type="hidden" name="act" value="deactivate_subscription"><button class="danger-soft" type="submit">' .
        icon("lock") .
        '<span>Somente leitura</span></button></form></div></div>';
    $billingForm =
        '<div class="admin-clinic-billing-panel"><div class="admin-clinic-action-copy">' .
        icon("tune") .
        '<div><b>Status e vencimento</b><span>Defina o estado vigente e a data de validade da assinatura.</span></div></div><form method="post" class="compact admin-clinic-billing-form">' .
        csrf_field() .
        $hidden .
        '<input type="hidden" name="act" value="billing"><div class="admin-clinic-billing-fields">' .
        select_label(
            "Status",
            "subscription_status",
            [
                "active" => "Ativo",
                "read_only" => "Somente leitura",
                "exempt" => "Isento",
            ],
            $currentStatus,
        ) .
        form_row(
            "Vencimento",
            input(
                "paid_until",
                "date",
                app_date_input_from_storage($billing["paid_until"] ?? ""),
            ),
        ) .
        '</div><div class="admin-clinic-billing-footer"><span>' .
        icon("event_available") .
        '<span>Vencimento atual: <b>' .
        e($dueLabel) .
        '</b></span></span><button class="primary" type="submit">' .
        icon("save") .
        '<span>Salvar assinatura</span></button></div></form></div>';

    $actions =
        '<div class="section-head"><div><span class="eyebrow">Gestão</span><h2>Ações do Desenvolvedor</h2><p>As mesmas ações do antigo modal, agora organizadas em uma tela própria.</p></div></div><div class="admin-clinic-actions-stack">' .
        $operationActions .
        $subscriptionActions .
        $billingForm .
        '</div>';

    $body =
        page_head("Consultório", "") .
        $hero .
        '<div class="admin-clinic-detail-columns">' .
        card($clinicData, "admin-clinic-detail-card") .
        card($responsibleData, "admin-clinic-detail-card") .
        '</div>' .
        card($actions, "admin-clinic-actions-card");
    page("Consultório · " . (string) $clinic["display_name"], $body);
}
function admin_clinic_people_counts_by_cpf(array $clinicIds): array
{

    $clinicIds = array_values(
        array_unique(array_filter(array_map("intval", $clinicIds))),
    );
    if (!$clinicIds) {
        return [];
    }
    sort($clinicIds, SORT_NUMERIC);
    $clinicIds = array_slice($clinicIds, 0, 300);
    $loader = static function () use ($clinicIds): array {

        $out = [];
        foreach ($clinicIds as $clinicId) {
            $out[$clinicId] = ["professionals" => 0, "collaborators" => 0];
        }
        $placeholders = implode(",", array_fill(0, count($clinicIds), "?"));
        try {
            $rows = q(
                "SELECT ur.clinic_id,COUNT(DISTINCT CASE WHEN ur.role_code='medico' THEN NULLIF(p.cpf,'') END) AS professionals,COUNT(DISTINCT NULLIF(p.cpf,'')) AS collaborators FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 JOIN pi_persons p ON p.id=u.person_id WHERE ur.clinic_id IN ($placeholders) AND ur.active=1 GROUP BY ur.clinic_id",
                $clinicIds,
            )->fetchAll();
            foreach ($rows as $row) {
                $clinicId = (int) ($row["clinic_id"] ?? 0);
                if (isset($out[$clinicId])) {
                    $out[$clinicId] = [
                        "professionals" =>
                            (int) ($row["professionals"] ?? 0),
                        "collaborators" =>
                            (int) ($row["collaborators"] ?? 0),
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo admin clinic CPF counts] " . $e->getMessage(),
            );
        }
        return $out;
    };
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "dashboard",
            server_json_cache_safe_key("admin_clinic_people_by_cpf", [
                $clinicIds,
            ]),
            server_json_cache_ttl("dashboard"),
            $loader,
            [
                "table:pi_user_roles",
                "table:pi_users",
                "table:pi_persons",
                "admin:clinic_counts",
            ],
        );
    }
    return $loader();
}
function page_admin_clinics(): void
{

    require_can("admin_clinics");
    $detailId = max(0, (int) ($_GET["clinic_id"] ?? 0));
    $returnClinicId = max(0, (int) ($_POST["return_clinic_id"] ?? 0));
    $redirectAfterClinicAction = static function (int $clinicId = 0): void {

        redirect(
            "admin_clinics",
            $clinicId > 0 ? ["clinic_id" => $clinicId] : [],
        );
    };
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "toggle");
        if ($act === "default_billing") {
            $price = max(
                0,
                (int) round(
                    ((float) str_replace(
                        ",",
                        ".",
                        (string) ($_POST["default_monthly_price"] ??
                            number_format(
                                default_monthly_price_cents() / 100,
                                2,
                                ".",
                                "",
                            )),
                    )) * 100,
                ),
            );
            if ($price <= 0) {
                $price = PRONTOO_MONTHLY_PRICE_CENTS;
            }
            $trialDays = max(
                0,
                min(
                    3650,
                    (int) ($_POST["default_trial_days"] ??
                        default_trial_days()),
                ),
            );
            meta_set("default_monthly_price_cents", (string) $price);
            meta_set("default_trial_days", (string) $trialDays);
            audit("assinatura_atualizada", "assinatura", null, [
                "price_cents" => $price,
                "trial_days" => $trialDays,
                "audit_body" =>
                    "Regra comercial padrão da plataforma atualizada.",
            ]);
            flash("Regra comercial padrão atualizada.");
            $redirectAfterClinicAction();
        }
        $id = (int) ($_POST["id"] ?? 0);
        $cl =
            $id > 0
                ? one(
                    "SELECT id,active,monthly_price_cents FROM pi_clinics WHERE id=?",
                    [$id],
                )
                : null;
        if (!$cl) {
            flash("Consultório não encontrado.", "bad");
            $redirectAfterClinicAction();
        }
        if ($act === "activate_subscription") {
            $price = default_monthly_price_cents();
            q(
                "UPDATE pi_clinics SET active=1, subscription_status='active', paid_until=DATE_ADD(CURDATE(), INTERVAL 30 DAY), monthly_price_cents=?, updated_at=NOW() WHERE id=?",
                [$price, $id],
            );
            if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
            }
            audit("assinatura_ativada", "assinatura", $id, [
                "audit_body" =>
                    "Consultório definido como Ativo pelo Desenvolvedor.",
            ]);
            flash("Consultório definido como Ativo.");
            $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
        }
        if ($act === "deactivate_subscription") {
            q(
                "UPDATE pi_clinics SET subscription_status='read_only', paid_until=CURDATE(), updated_at=NOW() WHERE id=?",
                [$id],
            );
            if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
            }
            audit("assinatura_desativada", "assinatura", $id, [
                "audit_body" =>
                    "Consultório definido como Somente leitura pelo Desenvolvedor.",
            ]);
            flash("Consultório definido como Somente leitura.");
            $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
        }
        if ($act === "billing") {
            $status = (string) ($_POST["subscription_status"] ?? "active");
            if (!in_array($status, ["active", "read_only", "exempt"], true)) {
                $status = "active";
            }
            $paid = trim((string) ($_POST["paid_until"] ?? "")) ?: null;
            if ($status === "exempt") {
                $paid = null;
            }
            $price = default_monthly_price_cents();
            q(
                "UPDATE pi_clinics SET subscription_status=?, paid_until=?, monthly_price_cents=?, updated_at=NOW() WHERE id=?",
                [$status, $paid, $price, $id],
            );
            if (class_exists("\Prontoo\Core\Tenant\TenantRegistry")) {
                \Prontoo\Core\Tenant\TenantRegistry::resetModelClinicCache();
            }
            audit("assinatura_atualizada", "assinatura", $id, [
                "status" => $status,
                "paid_until" => $paid,
                "price_cents" => $price,
                "audit_body" =>
                    "Status do consultório atualizado para " .
                    ([
                        "active" => "Ativo",
                        "read_only" => "Somente leitura",
                        "exempt" => "Isento",
                    ][$status] ??
                        $status) .
                    ".",
            ]);
            flash("Status do consultório atualizado.");
            $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
        }
        $new = (int) $cl["active"] ? 0 : 1;
        q("UPDATE pi_clinics SET active=?,updated_at=NOW() WHERE id=?", [
            $new,
            $id,
        ]);
        audit("clinica_status", "clinica", $id, [
            "status" => $new ? "ativa" : "inativa",
        ]);
        flash("Status do consultório atualizado.");
        $redirectAfterClinicAction($returnClinicId === $id ? $id : 0);
    }
    if ($detailId > 0) {
        admin_clinic_detail_page($detailId);
        return;
    }
    $exclude = admin_model_clinic_exclude_sql("id");
    $total = (int) val("SELECT COUNT(*) FROM pi_clinics WHERE 1=1 $exclude");
    $active = (int) val(
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 $exclude",
    );
    $exempt = (int) val(
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status='exempt' $exclude",
    );
    $readonly = (int) val(
        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND subscription_status<>'exempt' AND (subscription_status='read_only' OR (subscription_status<>'active' AND (paid_until IS NULL OR paid_until<CURDATE()) AND (trial_ends_at IS NULL OR trial_ends_at<NOW()))) $exclude",
    );
    $activeOperational = max(0, $active - $readonly - $exempt);
    $defaultPrice = default_monthly_price_cents();
    $defaultTrialDays = default_trial_days();
    $defaultBilling =
        '<details class="stat-card default-price-card"><summary>' .
        icon("payments") .
        "<div><b>" .
        money_br($defaultPrice) .
        "</b><span>Mensalidade única</span><small>" .
        (int) $defaultTrialDays .
        ' dias gratuitos</small></div></summary><form method="post" class="compact">' .
        csrf_field() .
        '<input type="hidden" name="act" value="default_billing">' .
        form_row(
            "Novo valor padrão",
            input(
                "default_monthly_price",
                "number",
                number_format($defaultPrice / 100, 2, ".", ""),
                'step="0.01" min="0"',
            ),
        ) .
        form_row(
            "Dias gratuitos",
            input(
                "default_trial_days",
                "number",
                (string) $defaultTrialDays,
                'min="0" max="3650" step="1"',
            ),
        ) .
        form_actions("Salvar", "primary small") .
        "</form></details>";
    $commercial =
        '<div class="stats-grid admin-clinic-attention-grid">' .
        stat_card(
            "Ativo",
            $activeOperational,
            "verified",
            "operação liberada",
        ) .
        stat_card(
            "Somente leitura",
            $readonly,
            "lock",
            "alterações bloqueadas",
        ) .
        stat_card("Isento", $exempt, "workspace_premium", "fora da cobrança") .
        stat_card(
            "Consultórios",
            $total,
            "home_health",
            $active . " ativo(s)",
        ) .
        $defaultBilling .
        "</div>";
    $rows = q(
        "SELECT id,display_name,responsible_profession,active,onboarding_done,owner_user_id,manager_user_id,created_at,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents FROM pi_clinics ORDER BY CASE WHEN active=0 THEN 4 WHEN subscription_status='exempt' THEN 3 WHEN subscription_status='read_only' OR (subscription_status<>'active' AND (paid_until IS NULL OR paid_until<CURDATE()) AND (trial_ends_at IS NULL OR trial_ends_at<NOW())) THEN 0 ELSE 2 END ASC, updated_at DESC, id DESC LIMIT 120",
    )->fetchAll();
    $ids = int_ids($rows, "id");
    $peopleCounts = admin_clinic_people_counts_by_cpf($ids);
    $bodyRows = "";
    foreach ($rows as $r) {
        $id = (int) $r["id"];
        $professionals =
            (int) ($peopleCounts[$id]["professionals"] ?? 0);
        $collaborators =
            (int) ($peopleCounts[$id]["collaborators"] ?? 0);
        $billing = billing_state($r);
        $status = (string) ($billing["status"] ?? "active");
        $attentionClass = "is-stable";
        $attentionIcon = "verified";
        $attentionLabel = "Ativo";
        $attentionNote = "";
        if ((int) $r["active"] !== 1) {
            $attentionClass = "is-muted";
            $attentionIcon = "pause_circle";
            $attentionLabel = "Inativo";
            $attentionNote = "Consultório desativado";
        } elseif (!empty($billing["exempt"])) {
            $attentionClass = "is-exempt";
            $attentionIcon = "workspace_premium";
            $attentionLabel = "Isento";
            $attentionNote = clinic_is_global_admin_owned($id)
                ? "Isento do Desenvolvedor fora das estatísticas"
                : "Isento de cobrança";
        } elseif (!empty($billing["read_only"])) {
            $attentionClass = "is-critical";
            $attentionIcon = "lock";
            $attentionLabel = "Somente leitura";
            $attentionNote = "Alterações temporariamente bloqueadas";
        }
        $attentionChip =
            '<span class="clinic-attention-chip ' .
            $attentionClass .
            '">' .
            icon($attentionIcon) .
            "<b>" .
            e($attentionLabel) .
            "</b></span>";
        $actions =
            '<a class="ghost small cmdlike clinic-actions-summary" href="' .
            e(href("admin_clinics", ["clinic_id" => $id])) .
            '" aria-label="Abrir gestão do consultório">' .
            icon("arrow_forward") .
            '<span>Gerenciar</span></a>';
        $createdLabel = date_br($r["created_at"] ?? "");
        $dueLabel = !empty($billing["exempt"])
            ? "Isento"
            : (trim((string) ($billing["paid_until"] ?? "")) !== ""
                ? date_br($billing["paid_until"])
                : (!empty($billing["trial_active"])
                    ? date_br($billing["trial_ends_at"] ?? "")
                    : "Sem vencimento"));
        $professionLabel =
            trim((string) ($r["responsible_profession"] ?? "")) ?:
            "Área não informada";
        $adminStatsNote =
            !empty($billing["exempt"]) && clinic_is_global_admin_owned($id)
                ? '<span class="ds-clinic-test-pill">' .
                    icon("bar_chart_off") .
                    "<span>Fora das estatísticas</span></span>"
                : "";
        $clinicMetrics =
            '<span class="ds-clinic-row-meta-chip">' .
            icon("event") .
            "<b>" .
            e($createdLabel) .
            '</b><small>Data Cadastro</small></span><span class="ds-clinic-row-meta-chip">' .
            icon("stethoscope") .
            "<b>" .
            e((string) $professionals) .
            '</b><small>Profissionais</small></span><span class="ds-clinic-row-meta-chip">' .
            icon("groups") .
            "<b>" .
            e((string) $collaborators) .
            '</b><small>Colaboradores</small></span><span class="ds-clinic-row-meta-chip ds-clinic-due-chip">' .
            icon("event_available") .
            "<b>" .
            e($dueLabel) .
            "</b><small>Vencimento</small></span>";
        $bodyRows .=
            '<article class="clinic-attention-item ds-clinic-list-item ds-clinic-list-item-inline ' .
            $attentionClass .
            '"><div class="ds-clinic-list-identity clinic-attention-main"><span class="clinic-attention-icon">' .
            icon($attentionIcon) .
            '</span><span class="clinic-attention-copy"><strong>' .
            e($r["display_name"]) .
            "</strong><small>" .
            e(
                ((int) $r["active"] ? "Operando" : "Inativo") .
                    " · " .
                    $professionLabel,
            ) .
            '</small></span></div><div class="clinic-attention-state ds-clinic-list-status">' .
            $attentionChip .
            ($attentionNote !== ""
                ? "<small>" . e($attentionNote) . "</small>"
                : "") .
            $adminStatsNote .
            '</div><div class="ds-clinic-row-meta">' .
            $clinicMetrics .
            '</div><div class="clinic-attention-actions">' .
            $actions .
            "</div></article>";
    }
    $table =
        '<div class="clinic-attention-list">' .
        ($bodyRows !== ""
            ? $bodyRows
            : '<div class="empty">Nenhum consultório exige atenção agora.</div>') .
        "</div>";
    $onboardingHead =
        '<div class="admin-onboarding-head clinic-attention-head"><div><span class="eyebrow">Acompanhamento</span><h2>Consultórios</h2><p>Leitura compacta por status, data de cadastro, profissionais, colaboradores e vencimento. Status padronizados: Ativo, Somente leitura e Isento.</p></div></div>';
    $body =
        page_head("Consultórios", "") .
        card($commercial, "admin-clinics-focus-card") .
        card(
            $onboardingHead . $table,
            "admin-onboarding-card admin-clinics-list-card clinic-attention-card",
        );
    page("Consultórios", $body);
}
function page_admin_security(): void
{

    require_can("admin_security");
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "");
        if ($act !== "release_login_lock") {
            throw new ProntooHttpError(400, "Ação de segurança inválida.");
        }
        $id = (int) ($_POST["id"] ?? 0);
        if ($id <= 0) {
            flash("Bloqueio não informado.", "bad");
            redirect("admin_security");
        }
        $removed = q("DELETE FROM pi_login_locks WHERE id=?", [$id])->rowCount();
        if ($removed < 1) {
            flash("Bloqueio não encontrado ou já liberado.", "bad");
            redirect("admin_security");
        }
        audit("bloqueio_login_removido", "seguranca", $id);
        flash("Bloqueio removido.");
        redirect("admin_security");
    }
    $items = [
        [
            "icon" => "verified_user",
            "time" => "Sistema",
            "title" => "Versão " . PRONTOO_VERSION,
            "body" =>
                "PHP " .
                PHP_VERSION .
                " · " .
                (is_file(app_root() . "/storage/install.lock")
                    ? "instalação bloqueada"
                    : "instalação aberta"),
            "meta" => "Banco limpo com tabelas pi_",
        ],
        [
            "icon" => "lock",
            "time" => "Agora",
            "title" =>
                (int) val(
                    "SELECT COUNT(*) FROM pi_login_locks WHERE locked_until>NOW()",
                ) . " bloqueio(s) de entrada ativo(s)",
            "body" => "Pausas progressivas por CPF e IP continuam no servidor.",
            "meta" => "Proteção de força bruta",
        ],
    ];
    $locks = q(
        "SELECT id,fail_count,locked_until FROM pi_login_locks WHERE locked_until>NOW() ORDER BY id DESC LIMIT 30",
    )->fetchAll();
    foreach ($locks as $l) {
        $btn =
            '<form method="post" class="inline">' .
            csrf_field() .
            '<input type="hidden" name="act" value="release_login_lock">' .
            '<input type="hidden" name="id" value="' .
            (int) $l["id"] .
            '"><button type="submit" class="danger small">Liberar</button></form>';
        $items[] = [
            "icon" => "lock_clock",
            "time" => dt_br($l["locked_until"]),
            "title" => "Entrada pausada",
            "body" => "Falhas consecutivas: " . $l["fail_count"],
            "meta" => "Identificadores armazenados por hash",
            "html" => $btn,
        ];
    }
    $scopeStats = admin_scope_guard_stats(24);
    $scopeGroups = (int) $scopeStats["actionable"] > 0
        ? admin_scope_guard_groups(24, 40)
        : [];
    $scopeItems = [];
    foreach ($scopeGroups as $scopeGroup) {
        $scopeItems[] = admin_scope_guard_timeline_item($scopeGroup);
    }
    $scopeLogic = class_exists("\\Prontoo\\Core\\Database\\SqlScopeGuard")
        ? \Prontoo\Core\Database\SqlScopeGuard::logicSelfTest()
        : ["ok" => false, "passed" => 0, "total" => 0, "failed" => ["class_missing"]];
    $scopeContext = function_exists("scope_guard_context_selftest")
        ? scope_guard_context_selftest()
        : ["ok" => false, "passed" => 0, "total" => 0, "failed" => ["function_missing"]];
    $scopeSummary =
        '<section id="scope-isolation" class="scope-isolation-panel"><div class="section-head"><div><span class="eyebrow">Isolamento entre consultórios</span><h2>Bloqueios com evidência explicável</h2><p>O guardião nega a operação antes do SQL. Os números abaixo medem bloqueios preventivos, não a probabilidade nem a confirmação de vazamento.</p></div><a class="ghost small" href="' .
        href("admin_integrity") .
        '">Ver vínculos persistidos</a></div><div class="stats-grid scope-guard-kpis">' .
        stat_card(
            "Causa demonstrável",
            (int) $scopeStats["objective"],
            "shield_lock",
            "ocorrências em 24h",
        ) .
        stat_card(
            "Prova insuficiente",
            (int) $scopeStats["review"],
            "rule",
            "revisão de código",
        ) .
        stat_card(
            "Padrões distintos",
            (int) $scopeStats["patterns"],
            "fingerprint",
            "chave + rota + consultório",
        ) .
        stat_card(
            "Política de assinatura",
            (int) $scopeStats["policy"],
            "lock_clock",
            "fora do risco de isolamento",
        ) .
        stat_card(
            "Prova lógica interna",
            !empty($scopeLogic["ok"]) ? "Aprovada" : "Falhou",
            !empty($scopeLogic["ok"]) ? "verified" : "gpp_bad",
            (int) ($scopeLogic["passed"] ?? 0) .
                "/" .
                (int) ($scopeLogic["total"] ?? 0) .
                " casos lógicos críticos",
        ) .
        stat_card(
            "Contexto determinístico",
            !empty($scopeContext["ok"]) ? "Aprovado" : "Falhou",
            !empty($scopeContext["ok"]) ? "account_tree" : "gpp_bad",
            (int) ($scopeContext["passed"] ?? 0) .
                "/" .
                (int) ($scopeContext["total"] ?? 0) .
                " contratos sistema/consultório",
        ) .
        '</div><div class="kpi-info-strip scope-isolation-note">' .
        icon("info") .
        '<span>Repetições idênticas dentro da mesma requisição são deduplicadas e eventos recentes são agrupados por fingerprint. Recorrência ajuda a priorizar a correção, mas não é usada como probabilidade de acesso cruzado.</span></div>' .
        timeline(
            $scopeItems,
            "Nenhuma operação de escopo exigiu revisão nas últimas 24 horas.",
        ) .
        "</section>";
    page(
        "Segurança",
        page_head(
            "Segurança",
            "Robustez, bloqueios de entrada e isolamento explicável entre consultórios.",
        ) .
            card(timeline($items), "admin-security-access-card") .
            card($scopeSummary, "admin-security-scope-card"),
    );
}
function page_admin_audit(): void
{

    require_can("admin_health");
    $rows = audit_rows_light("1=1", [], 120);
    page(
        "Atividades",
        page_head(
            "Atividades",
            "Histórico direto recente de toda a plataforma.",
        ) .
            card(
                '<div class="activity-timeline">' .
                    timeline(
                        audit_items($rows, true),
                        "Nenhuma atividade encontrada.",
                    ) .
                    "</div>",
            ),
    );
}
function admin_alerts_ensure_schema(): void
{

    static $validated = false;
    if ($validated || !has_cfg()) {
        return;
    }
    if (!db_table_exists("pi_admin_alerts")) {
        throw new RuntimeException(
            "Schema incompleto: alertas administrativos indisponíveis.",
        );
    }
    foreach (["sender_clinic_id", "source_scope"] as $column) {
        if (!db_column_exists("pi_admin_alerts", $column)) {
            throw new RuntimeException(
                "Schema incompleto: pi_admin_alerts.{$column} ausente.",
            );
        }
    }
    $validated = true;
}

function admin_alerts_admin_users(): array
{

    try {
        $rows = q(
            "SELECT id,name,email FROM pi_users WHERE is_global_admin=1 AND active=1 ORDER BY name ASC,id ASC LIMIT 300",
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r["id"]] = $r;
        }
        return $out;
    } catch (Throwable $e) {
        error_log("[Prontoo admin alerts users] " . $e->getMessage());
        return [];
    }
}
function admin_alert_contact_label(
    ?array $user,
    int $currentUserId = 0,
    bool $asSupport = false,
): string {

    if ($asSupport) {
        return "Suporte";
    }
    $name = trim((string) ($user["name"] ?? ""));
    if ($name === "") {
        return "Desenvolvedor";
    }
    return first_name($name);
}
function page_admin_alerts(): void
{

    $c = require_can("admin_alerts");
    admin_alerts_ensure_schema();
    $uid = (int) ($c["user"]["id"] ?? 0);
    $admins = admin_alerts_admin_users();
    $view = preg_replace(
        "/[^a-z_]/",
        "",
        (string) ($_GET["view"] ?? ($_POST["view"] ?? "received")),
    );
    if (!in_array($view, ["received", "sent"], true)) {
        $view = "received";
    }
    $composeOpen = (string) ($_GET["compose"] ?? "") === "1";
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "create");
        if ($act === "read") {
            $id = (int) ($_POST["id"] ?? 0);
            if ($id > 0) {
                q(
                    "UPDATE pi_admin_alerts SET read_at=COALESCE(read_at,NOW()), read_by=COALESCE(read_by,?) WHERE id=? AND recipient_user_id=?",
                    [$uid, $id, $uid],
                );
            }
            redirect("admin_alerts", ["view" => $view, "alert" => $id]);
        }
        $title = trim((string) ($_POST["title"] ?? ""));
        $body = trim((string) ($_POST["body"] ?? ""));
        $severity = (string) ($_POST["severity"] ?? "info");
        if (!in_array($severity, ["info", "warning", "critical"], true)) {
            $severity = "info";
        }
        $recipient = (int) ($_POST["recipient_user_id"] ?? 0);
        $recipientId = null;
        if ($recipient > 0) {
            if (!isset($admins[$recipient])) {
                flash(
                    "Escolha um perfil de Desenvolvedor válido para receber o aviso.",
                    "bad",
                );
                redirect("admin_alerts", ["view" => $view]);
            }
            $recipientId = $recipient;
        }
        if ($title === "" || $body === "") {
            flash("Informe título e mensagem do aviso.", "bad");
            redirect("admin_alerts", ["view" => $view]);
        }
        q(
            "INSERT INTO pi_admin_alerts (sender_user_id,recipient_user_id,title,body,severity,created_at) VALUES (?,?,?,?,?,NOW())",
            [$uid, $recipientId, mb_substr($title, 0, 180), $body, $severity],
        );
        $id = db_last_insert_id();
        audit("aviso_admin_criado", "aviso_admin", $id, [
            "destinatario" => $recipientId ?: "todos_desenvolvedores",
            "severity" => $severity,
            "audit_body" =>
                "Desenvolvedor enviou aviso restrito a perfis de administração técnica.",
        ]);
        flash("Aviso enviado.");
        redirect("admin_alerts", ["view" => "sent", "alert" => $id]);
    }
    $openId = (int) ($_GET["alert"] ?? 0);
    $recipientOpts = '<option value="0">Todos os Desenvolvedores</option>';
    foreach ($admins as $id => $u) {
        $recipientOpts .=
            '<option value="' .
            (int) $id .
            '">' .
            e((string) ($u["name"] ?? "Desenvolvedor")) .
            ($id === $uid ? " — você" : "") .
            "</option>";
    }
    $compose =
        '<details class="form-panel admin-alert-compose admin-alert-compose-panel"' .
        ($composeOpen ? " open" : "") .
        '><summary class="admin-alert-compose-summary"><span class="admin-alert-compose-summary-main"><span class="admin-alert-compose-icon">' .
        icon("campaign") .
        '</span><span><b>Novo aviso</b><small>Comunicação restrita ao ambiente técnico</small></span></span><span class="admin-alert-compose-toggle" aria-hidden="true">' .
        icon("expand_more") .
        '</span></summary><form method="post" class="compact admin-alert-form notice-form">' .
        csrf_field() .
        '<input type="hidden" name="view" value="' .
        e($view) .
        '"><div class="two">' .
        form_row(
            "Destinatário",
            '<select name="recipient_user_id">' . $recipientOpts . "</select>",
        ) .
        select_label(
            "Prioridade",
            "severity",
            [
                "info" => "Informativo",
                "warning" => "Atenção",
                "critical" => "Crítico",
            ],
            "info",
        ) .
        "</div>" .
        form_row(
            "Título",
            input(
                "title",
                "text",
                "",
                'required placeholder="Assunto do aviso"',
            ),
        ) .
        form_row(
            "Mensagem",
            textarea(
                "body",
                "",
                'required rows="5" placeholder="Mensagem restrita ao Desenvolvedor Prontoo."',
            ),
        ) .
        '<p class="field-help admin-alert-scope-help">' .
        icon("shield_lock") .
        '<span>Este conteúdo fica restrito aos perfis do Desenvolvedor Prontoo e não é exibido nos ambientes dos consultórios.</span></p>' .
        form_actions("Enviar aviso") .
        "</form></details>";
    $rows = q(
        "SELECT id,sender_user_id,sender_clinic_id,source_scope,recipient_user_id,title,severity,read_at,read_by,created_at FROM pi_admin_alerts WHERE sender_user_id=? OR recipient_user_id=? OR recipient_user_id IS NULL ORDER BY id DESC LIMIT 180",
        [$uid, $uid],
    )->fetchAll();
    $clinicNames = [];
    $clinicIds = int_ids($rows, "sender_clinic_id");
    if ($clinicIds) {
        $clinicNames = fetch_map("pi_clinics", $clinicIds, "id,display_name");
    }
    $sentCount = 0;
    $receivedCount = 0;
    $unreadCount = 0;
    foreach ($rows as $r) {
        $mine = (int) $r["sender_user_id"] === $uid;
        if ($mine) {
            $sentCount++;
        } else {
            $receivedCount++;
            if (
                (int) ($r["recipient_user_id"] ?? 0) === $uid &&
                empty($r["read_at"])
            ) {
                $unreadCount++;
            }
        }
    }
    $receivedActive = $view === "received";
    $sentActive = $view === "sent";
    $filters =
        '<nav class="notice-filter-chips lead-filter-chips ds-notice-filters admin-alert-filters" aria-label="Caixas de avisos"><span class="admin-alert-filter-label">Caixa</span><a class="lead-chip ds-filter-chip ' .
        ($receivedActive ? "active is-active" : "") .
        '" href="' .
        e(href("admin_alerts", ["view" => "received"])) .
        '"' .
        ($receivedActive ? ' aria-current="page"' : "") .
        '">' .
        icon("inbox") .
        '<span class="lead-chip-label">Recebidos</span><em>' .
        (int) $receivedCount .
        '</em></a><a class="lead-chip ds-filter-chip ' .
        ($sentActive ? "active is-active" : "") .
        '" href="' .
        e(href("admin_alerts", ["view" => "sent"])) .
        '"' .
        ($sentActive ? ' aria-current="page"' : "") .
        '">' .
        icon("send") .
        '<span class="lead-chip-label">Enviados</span><em>' .
        (int) $sentCount .
        "</em></a></nav>";
    $detail = "";
    if ($openId > 0) {
        $open = null;
        foreach ($rows as $r) {
            if ((int) $r["id"] === $openId) {
                $open = $r;
                break;
            }
        }
        if ($open) {
            $openBody = one(
                "SELECT body FROM pi_admin_alerts WHERE id=? AND (sender_user_id=? OR recipient_user_id=? OR recipient_user_id IS NULL) LIMIT 1",
                [$openId, $uid, $uid],
            );
            $open["body"] = (string) ($openBody["body"] ?? "");
            $mine = (int) $open["sender_user_id"] === $uid;
            if (
                !$mine &&
                (int) ($open["recipient_user_id"] ?? 0) === $uid &&
                empty($open["read_at"])
            ) {
                q(
                    "UPDATE pi_admin_alerts SET read_at=NOW(), read_by=? WHERE id=? AND recipient_user_id=?",
                    [$uid, $openId, $uid],
                );
                $open["read_at"] = date("Y-m-d H:i:s");
            }
            $sender = $admins[(int) $open["sender_user_id"]] ?? [];
            $senderClinicId = (int) ($open["sender_clinic_id"] ?? 0);
            $senderClinic =
                $senderClinicId > 0
                    ? (string) ($clinicNames[$senderClinicId]["display_name"] ??
                        "Consultório")
                    : "";
            $recipientId = (int) ($open["recipient_user_id"] ?? 0);
            $recipient = $recipientId > 0 ? $admins[$recipientId] ?? [] : null;
            $from =
                $senderClinic !== ""
                    ? "Consultório: " . $senderClinic
                    : admin_alert_contact_label($sender, $uid, !$mine);
            $to =
                $recipientId > 0
                    ? admin_alert_contact_label($recipient, $uid, false)
                    : "Desenvolvedores";
            $state = $mine ? "Enviado para " . $to : "Recebido de " . $from;
            $openSeverity = (string) ($open["severity"] ?? "info");
            $openSeverityLabel = match ($openSeverity) {
                "critical" => "Crítico",
                "warning" => "Atenção",
                default => "Informativo",
            };
            $detail = card(
                '<article class="notice-reader admin-alert-reader severity-' .
                    e($openSeverity) .
                    '"><header class="admin-alert-reader-toolbar"><a class="ghost small" href="' .
                    e(
                        href("admin_alerts", [
                            "view" => $mine ? "sent" : "received",
                        ]),
                    ) .
                    '">' .
                    icon("arrow_back") .
                    '<span>Voltar</span></a><div class="admin-alert-reader-meta"><span class="notice-reader-state">' .
                    icon($mine ? "send" : "support_agent") .
                    " " .
                    e($state) .
                    "</span><time>" .
                    e(dt_notice_br($open["created_at"])) .
                    '</time></div></header><div class="admin-alert-reader-content"><span class="eyebrow">' .
                    icon("mark_email_read") .
                    '<span>Aviso técnico</span></span><h2>' .
                    e($open["title"]) .
                    '</h2><div class="notice-reader-body">' .
                    nl2br(e($open["body"])) .
                    '</div></div><footer><span class="notice-direction-pill ' .
                    ($mine ? "is-sent" : "is-received") .
                    '">' .
                    icon($mine ? "north_east" : "south_west") .
                    " " .
                    e($mine ? $to : $from) .
                    '</span><span class="pill">' .
                    e($openSeverityLabel) .
                    "</span></footer></article>",
                "notice-card-shell admin-alert-reader-shell",
            );
        } else {
            $detail = card(
                '<div class="notice-empty">' .
                    icon("campaign") .
                    "<strong>Aviso não encontrado.</strong></div>",
                "notice-card-shell",
            );
        }
    }
    $cards = "";
    foreach ($rows as $r) {
        $mine = (int) $r["sender_user_id"] === $uid;
        if ($view === "received" && $mine) {
            continue;
        }
        if ($view === "sent" && !$mine) {
            continue;
        }
        $sender = $admins[(int) $r["sender_user_id"]] ?? [];
        $senderClinicId = (int) ($r["sender_clinic_id"] ?? 0);
        $senderClinic =
            $senderClinicId > 0
                ? (string) ($clinicNames[$senderClinicId]["display_name"] ??
                    "Consultório")
                : "";
        $recipientId = (int) ($r["recipient_user_id"] ?? 0);
        $recipient = $recipientId > 0 ? $admins[$recipientId] ?? [] : null;
        $name = $mine
            ? ($recipientId > 0
                ? admin_alert_contact_label($recipient, $uid, false)
                : "Desenvolvedores")
            : ($senderClinic !== ""
                ? "Consultório: " . $senderClinic
                : admin_alert_contact_label($sender, $uid, true));
        $sev = (string) ($r["severity"] ?? "info");
        $sevLabel = match ($sev) {
            "critical" => "Crítico",
            "warning" => "Atenção",
            default => "Informativo",
        };
        $iconName = $mine
            ? "send"
            : ($sev === "critical"
                ? "priority_high"
                : ($sev === "warning"
                    ? "warning"
                    : "support_agent"));
        $unread = !$mine && $recipientId === $uid && empty($r["read_at"]);
        $rowCurrent = (int) $r["id"] === $openId;
        $cards .=
            '<a class="patient-card-row admin-alert-patient-row admin-alert-row severity-' .
            e($sev) .
            " " .
            ($unread
                ? "patient-status-warn needs-ack"
                : "patient-status-ok is-read") .
            ($rowCurrent ? " is-current" : "") .
            '" href="' .
            e(
                href("admin_alerts", [
                    "view" => $view,
                    "alert" => (int) $r["id"],
                ]),
            ) .
            '" aria-label="' .
            e(
                ($mine ? "Enviado para " : "Recebido de ") .
                    $name .
                    ": " .
                    (string) $r["title"],
            ) .
            '"' .
            ($rowCurrent ? ' aria-current="true"' : "") .
            '><span class="patient-card-avatar admin-alert-avatar" aria-hidden="true">' .
            icon($iconName) .
            '</span><span class="patient-card-main"><span class="patient-card-title"><strong>' .
            e($r["title"]) .
            '</strong><span class="admin-alert-row-badges">' .
            ($unread
                ? '<span class="admin-alert-unread-badge">Não lido</span>'
                : "") .
            '<span class="pill ' .
            ($sev === "critical"
                ? "bad"
                : ($sev === "warning"
                    ? "warn"
                    : "ok")) .
            '">' .
            e($sevLabel) .
            '</span></span></span><span class="patient-card-meta"><span>' .
            icon($mine ? "outbox" : "inbox") .
            e($name) .
            "</span><span>" .
            icon("schedule") .
            e(dt_notice_br($r["created_at"])) .
            '</span></span></span><span class="patient-card-actions admin-alert-actions"><span class="admin-alert-open-icon" aria-hidden="true">' .
            icon("chevron_right") .
            "</span></span></a>";
    }
    if ($cards === "") {
        $cards =
            '<div class="notice-empty">' .
            icon("campaign") .
            "<strong>" .
            e(
                $view === "sent"
                    ? "Nenhum aviso enviado."
                    : "Nenhum aviso recebido.",
            ) .
            "</strong></div>";
    }
    $intro =
        '<section class="admin-alert-hero" aria-label="Central de avisos técnicos"><span class="admin-alert-hero-icon">' .
        icon("campaign") .
        '</span><div class="admin-alert-hero-copy"><span class="eyebrow">Central técnica</span><h2>Comunicação do Desenvolvedor</h2><p>Organize avisos recebidos e enviados sem expor o conteúdo aos ambientes dos consultórios.</p></div><span class="admin-alert-private-chip">' .
        icon("shield_lock") .
        "<span>Acesso restrito</span></span></section>";
    $stats =
        '<div class="notice-kpis notice-gmail-kpis ds-notice-kpis admin-alert-kpis"><div class="notice-kpi ds-kpi ' .
        ($unreadCount ? "notice-kpi-pending" : "") .
        '">' .
        icon("inbox") .
        "<div><b>" .
        (int) $receivedCount .
        '</b><span>Recebidos</span><small>' .
        (int) $unreadCount .
        ' não lido(s)</small></div></div><div class="notice-kpi ds-kpi notice-kpi-sent">' .
        icon("send") .
        "<div><b>" .
        (int) $sentCount .
        '</b><span>Enviados</span><small>por você</small></div></div><div class="notice-kpi ds-kpi notice-kpi-critical">' .
        icon("admin_panel_settings") .
        "<div><b>" .
        count($admins) .
        "</b><span>Desenvolvedores</span><small>perfis ativos</small></div></div></div>";
    $list = card(
        '<div class="notice-section-head ds-section-head admin-alert-list-head"><div><span class="eyebrow">' .
            e($view === "sent" ? "Histórico" : "Entrada") .
            '</span><h2>' .
            e($view === "sent" ? "Avisos enviados" : "Avisos recebidos") .
            '</h2></div><span class="admin-alert-result-count">' .
            (int) ($view === "sent" ? $sentCount : $receivedCount) .
            ' registro(s)</span></div><div class="ds-patient-list admin-alert-patient-list">' .
            $cards .
            "</div>",
        "patient-list-card patient-directory-card ds-filter-list-block admin-alert-shell",
    );
    page(
        "Avisos do Desenvolvedor",
        page_head("Avisos") .
            '<section class="notice-screen admin-alert-screen patient-directory-screen">' .
            $intro .
            $stats .
            '<section class="notice-filter-list-block ds-filter-list-block" aria-label="Filtros e lista de avisos administrativos">' .
            $filters .
            $compose .
            $detail .
            $list .
            "</section></section>",
    );
}

function admin_performance_format_ms(float $ms): string
{

    return number_format(max(0.0, $ms), 1, ",", ".") . " ms";
}
function admin_performance_rows_html(array $rows): string
{

    if (!$rows) {
        return '<div class="empty">Ainda não há dados de performance nas últimas 24 horas. Use o sistema por alguns minutos e retorne a esta tela.</div>';
    }
    $h =
        '<div class="admin-performance-table-wrap"><table class="admin-performance-table"><thead><tr><th>Rota</th><th>Requisições</th><th>Tempo médio</th><th>SQL médio</th><th>Queries/req.</th><th>SELECT amplo/req.</th><th>PHP carregado/req.</th><th>Máximo</th><th>Falhas</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $route = (string) ($r["route"] ?? "");
        $count = (int) ($r["count"] ?? 0);
        $avg = (float) ($r["avg_ms"] ?? 0);
        $qavg = (float) ($r["query_avg_ms"] ?? 0);
        $queries = (float) ($r["queries_avg"] ?? 0);
        $wideSelects = (float) ($r["wide_selects_avg"] ?? 0);
        $moduleBytes = (float) ($r["module_bytes_avg"] ?? 0);
        $moduleFiles = (float) ($r["module_files_avg"] ?? 0);
        $max = (float) ($r["max_ms"] ?? 0);
        $errors = (int) ($r["errors"] ?? 0);
        $tone = $avg >= 1500 ? "is-bad" : ($avg >= 800 ? "is-warn" : "is-ok");
        $h .=
            '<tr class="' .
            $tone .
            '"><td><code>' .
            e($route) .
            "</code></td><td>" .
            n($count) .
            "</td><td><b>" .
            e(admin_performance_format_ms($avg)) .
            "</b></td><td>" .
            e(admin_performance_format_ms($qavg)) .
            "</td><td>" .
            e(number_format($queries, 1, ",", ".")) .
            "</td><td>" .
            e(number_format($wideSelects, 1, ",", ".")) .
            "</td><td title=\"" .
            e(number_format($moduleFiles, 1, ",", ".") . " módulos por requisição") .
            "\">" .
            e(number_format($moduleBytes / 1024, 1, ",", ".") . " KiB") .
            "</td><td>" .
            e(admin_performance_format_ms($max)) .
            "</td><td>" .
            ($errors > 0
                ? '<span class="status danger">' . n($errors) . "</span>"
                : '<span class="status ok">0</span>') .
            "</td></tr>";
    }
    return $h . "</tbody></table></div>";
}
function page_admin_performance(): void
{

    require_can("admin_performance");
    $summary = function_exists("telemetry_route_performance_summary")
        ? telemetry_route_performance_summary(24)
        : ["routes" => [], "total" => 0, "avg_ms" => 0, "updated_at" => ""];
    $cacheSummary = function_exists("telemetry_cache_performance_summary")
        ? telemetry_cache_performance_summary(24)
        : ["total" => []];

    $rows = isset($summary["routes"]) && is_array($summary["routes"])
        ? $summary["routes"]
        : [];
    $total = (int) ($summary["total"] ?? 0);
    $avg = (float) ($summary["avg_ms"] ?? 0);
    $slow = $rows[0] ?? null;
    $updated = (string) ($summary["updated_at"] ?? "");
    $cacheTotal = isset($cacheSummary["total"]) && is_array($cacheSummary["total"])
        ? $cacheSummary["total"]
        : [];
    $cacheLookups = (int) ($cacheTotal["lookups"] ?? 0);
    $cacheHits = (int) ($cacheTotal["hits"] ?? 0);
    $cacheHitRate = (float) ($cacheTotal["hit_rate"] ?? 0);

    $stats =
        '<div class="stats-grid admin-performance-stats">' .
        stat_card("Requisições 24h", $total, "route", "Dados agregados no período") .
        stat_card(
            "Tempo médio de resposta",
            admin_performance_format_ms($avg),
            "speed",
            "Média geral nas últimas 24 horas",
        ) .
        stat_card(
            "Rota mais lenta",
            $slow ? admin_performance_format_ms((float) ($slow["avg_ms"] ?? 0)) : "—",
            "timer",
            $slow ? (string) ($slow["route"] ?? "") : "Sem dados",
        ) .
        stat_card(
            "Acertos de Cache",
            $cacheLookups > 0 ? number_format($cacheHitRate, 1, ",", ".") . "%" : "—",
            "cached",
            $cacheLookups > 0
                ? n($cacheHits) . " de " . n($cacheLookups) . " consultas"
                : "Medição ainda sem amostras",
        ) .
        '</div>';

    $routesCard = card(
        '<div class="section-head admin-performance-head"><h2>' .
            icon("speed") .
            '<span>Rotas nas últimas 24 horas</span></h2><p>Detalhamento das rotas para identificar tempos elevados, consultas excessivas e falhas.' .
            ($updated !== "" ? " Última atualização: " . e($updated) . "." : "") .
            '</p></div>' .
            admin_performance_rows_html($rows),
        "admin-performance-card",
    );

    $body =
        page_head(
            "Performance",
            "Visão direta das requisições, do tempo de resposta e da eficiência do cache nas últimas 24 horas.",
        ) .
        '<section class="admin-performance-screen">' .
        $stats .
        $routesCard .
        '</section>';
    page("Performance", $body);
}
