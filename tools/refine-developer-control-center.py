from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path):
    return (ROOT / path).read_text(encoding="utf-8")


def write(path, text):
    (ROOT / path).write_text(text, encoding="utf-8")


def method_span(text, name):
    marker = f"    public static function {name}("
    start = text.index(marker)
    open_brace = text.index("{", start)
    depth = 0
    quote = None
    i = open_brace
    while i < len(text):
        ch = text[i]
        nxt = text[i + 1] if i + 1 < len(text) else ""
        if quote is not None:
            if ch == "\\":
                i += 2
                continue
            if ch == quote:
                quote = None
            i += 1
            continue
        if ch in ("'", '"'):
            quote = ch
            i += 1
            continue
        if ch == "/" and nxt == "*":
            end = text.index("*/", i + 2)
            i = end + 2
            continue
        if ch == "/" and nxt == "/":
            end = text.find("\n", i + 2)
            i = len(text) if end < 0 else end + 1
            continue
        if ch == "#":
            end = text.find("\n", i + 1)
            i = len(text) if end < 0 else end + 1
            continue
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return start, i + 1
        i += 1
    raise RuntimeError(f"method end not found: {name}")


def replace_method(path, name, replacement):
    text = read(path)
    start, end = method_span(text, name)
    text = text[:start] + replacement.rstrip() + "\n" + text[end:]
    write(path, text)


def remove_method(path, name):
    text = read(path)
    start, end = method_span(text, name)
    text = text[:start] + text[end:]
    while "\n\n\n\n" in text:
        text = text.replace("\n\n\n\n", "\n\n\n")
    write(path, text)


def remove_block(text, marker):
    start = text.index(marker)
    open_brace = text.index("{", start)
    depth = 0
    quote = None
    i = open_brace
    while i < len(text):
        ch = text[i]
        if quote is not None:
            if ch == "\\":
                i += 2
                continue
            if ch == quote:
                quote = None
            i += 1
            continue
        if ch in ("'", '"'):
            quote = ch
        elif ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                end = i + 1
                if end < len(text) and text[end] == "\n":
                    end += 1
                return text[:start] + text[end:]
        i += 1
    raise RuntimeError(f"block end not found: {marker}")


def replace_once(path, old, new):
    text = read(path)
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"expected one occurrence in {path}, got {count}: {old[:80]}")
    write(path, text.replace(old, new, 1))


ops1 = "app/Runtime/AdminPages/AdminPagesRuntimeOperations01.php"
text = read(ops1)
insert_marker = "    public static function platform_login_loaded_audit("
if "public static function platform_health_snapshot(" not in text:
    snapshot = r'''    public static function platform_health_snapshot(array $preloaded = []): array
    {
        $checks = self::platform_backend_selftest($preloaded);
        $databaseOk = !empty($checks["database"]);
        $storageOk = !empty($checks["storage"]);
        $auditOk = !empty(($checks["audit_chain"] ?? [])["ok"]);
        $integrityOk = $auditOk && (int) ($checks["integrity_alerts"] ?? 0) === 0;
        $versionOk = !empty(($checks["version_contract"] ?? [])["ok"]);
        $openErrors = (int) ($checks["open_errors"] ?? 0);
        $locks = (int) ($checks["login_locks"] ?? 0);
        $scope = (int) ($checks["scope_alerts_24h"] ?? 0);
        $securityOk = $locks === 0 && $scope === 0;
        $critical = !$databaseOk || !$storageOk || !$integrityOk || !$versionOk;
        $attention = $openErrors > 0 || !$securityOk;
        $state = $critical ? "Crítico" : ($attention ? "Atenção" : "Operacional");
        return [
            "state" => $state,
            "critical" => $critical,
            "checks" => $checks,
            "counts" => [
                "open_errors" => $openErrors,
                "login_locks" => $locks,
                "scope_alerts_24h" => $scope,
            ],
            "components" => [
                "database" => ["label" => "Banco", "ok" => $databaseOk],
                "storage" => ["label" => "Storage", "ok" => $storageOk],
                "integrity" => ["label" => "Integridade", "ok" => $integrityOk],
                "security" => ["label" => "Segurança", "ok" => $securityOk],
                "version" => ["label" => "Contrato de versão", "ok" => $versionOk],
            ],
            "updated_at" => date("d/m/Y · H:i"),
        ];
    }

'''
    text = text.replace(insert_marker, snapshot + insert_marker, 1)
    write(ops1, text)


presentation1 = "app/Presentation/AdminPages/AdminPagesPresentationOperations01.php"
replace_method(
    presentation1,
    "admin_global_operation_specs",
    r'''    public static function admin_global_operation_specs(string $current, string $parent, string $alertView): array
    {
        if (!in_array($alertView, ["received", "sent"], true)) {
            $alertView = "received";
        }
        if ($current === "admin_alerts") {
            return [
                ["admin_alerts", "Recebidos", "inbox", ["view" => "received"]],
                ["admin_alerts", "Enviados", "outbox", ["view" => "sent"]],
                ["admin_alerts", "Nova mensagem", "add_comment", ["view" => $alertView, "compose" => "1"]],
            ];
        }
        if ($current === "admin_maintenance") {
            return [["admin_maintenance", "Manutenção", "construction"]];
        }
        return match ($parent) {
            "admin_painel" => [["admin_painel", "Visão geral", "space_dashboard"]],
            "admin_clinics" => [["admin_clinics", "Consultórios", "home_health"]],
            "admin_health" => [
                ["admin_health", "Confiabilidade", "shield"],
                ["admin_errors", "Erros", "bug_report"],
                ["admin_security", "Segurança", "security"],
                ["admin_integrity", "Integridade", "verified_user"],
            ],
            "admin_performance" => [["admin_performance", "Observabilidade", "monitoring"]],
            "admin_administration" => [["admin_administration", "Administração", "tune"]],
            default => [],
        };
    }''',
)


presentation3 = "app/Presentation/AdminPages/AdminPagesPresentationOperations03.php"
replace_method(
    presentation3,
    "developer_overview_html",
    r'''    public static function developer_overview_html(array $context, callable $href, callable $actionLabel): string
    {
        $actions = (array) ($context["actions"] ?? []);
        $health = (array) ($context["health"] ?? []);
        $healthState = (string) ($health["state"] ?? "Operacional");
        $state = $healthState === "Crítico" ? "Crítico" : ($actions ? "Atenção" : $healthState);
        $critical = $state === "Crítico";
        $stateCopy = match ($state) {
            "Crítico" => "Existe uma condição estrutural que exige intervenção técnica.",
            "Atenção" => "Há decisões ou exceções que justificam sua revisão.",
            default => "Nada exige intervenção neste momento.",
        };
        $stateClass = match ($state) {
            "Crítico" => "is-critical",
            "Atenção" => "is-attention",
            default => "is-operational",
        };
        $updatedAt = mb_trim((string) ($health["updated_at"] ?? ""));
        $statusCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="developer-control-status"><div><span class="eyebrow">Estado do Prontoo</span><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                '</h2><p>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($stateCopy) .
                '</p>' .
                ($updatedAt !== "" ? '<small>Atualizado em ' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($updatedAt) . '</small>' : "") .
                '</div><span class="developer-state-badge ' .
                $stateClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($critical ? "crisis_alert" : ($actions ? "notification_important" : "verified")) .
                '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                '</span></span></div>',
            "developer-status-card",
        );
        $actionsBody = $actions
            ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($actions)
            : '<div class="developer-empty-state">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check_circle") .
                '<div><b>Nada precisa de você agora</b><p>Decisões, incidentes e bloqueios relevantes aparecerão aqui.</p></div></div>';
        $actionsCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Precisa de você</h2><p>Somente itens que pedem decisão ou intervenção.</p></div></div>' . $actionsBody,
            "developer-actions-card",
        );
        return '<section class="developer-overview-minimal">' . $statusCard . $actionsCard . '</section>';
    }''',
)
replace_method(
    presentation3,
    "developer_administration_tools_html",
    r'''    public static function developer_administration_tools_html(callable $href): string
    {
        $primary = [
            ["groups", "Usuários", "Credenciais, vínculos e identidades administrativas.", "admin_people"],
            ["mail", "Mensagens internas", "Comunicação restrita aos perfis do Desenvolvedor.", "admin_alerts"],
            ["notifications_active", "Avisos aos consultórios", "Comunicações institucionais exibidas nos ambientes clínicos.", "admin_global_notices"],
            ["history", "Auditoria", "Rastreabilidade administrativa e eventos relevantes.", "admin_audit"],
        ];
        $advanced = [
            ["settings", "Configurações", "Parâmetros globais de baixa frequência.", "admin_settings"],
            ["construction", "Manutenção", "Controles extraordinários de disponibilidade.", "admin_maintenance"],
        ];
        $render = static function (array $tools) use ($href): string {
            $grid = '<div class="developer-tool-grid">';
            foreach ($tools as [$icon, $title, $description, $route]) {
                $grid .=
                    '<a class="developer-tool-card" href="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href($route)) .
                    '"><span class="developer-tool-icon">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($icon) .
                    '</span><span><b>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
                    '</b><small>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($description) .
                    '</small></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a>';
            }
            return $grid . '</div>';
        };
        return '<div class="section-head"><div><h2>Administração</h2><p>Acesso, comunicação e rastreabilidade em um único lugar.</p></div></div>' .
            $render($primary) .
            '<details class="form-panel developer-advanced-tools"><summary><span>Configuração avançada</span></summary>' .
            $render($advanced) .
            '</details>';
    }''',
)
replace_method(
    presentation3,
    "admin_observability_html",
    r'''    public static function admin_observability_html(array $summary, string $telemetryCharts): string
    {
        $rows = isset($summary["routes"]) && is_array($summary["routes"])
            ? $summary["routes"]
            : [];
        $total = (int) ($summary["total"] ?? 0);
        $avg = (float) ($summary["avg_ms"] ?? 0);
        $slow = $rows[0] ?? null;
        $updated = (string) ($summary["updated_at"] ?? "");
        $errors = 0;
        foreach ($rows as $row) {
            $errors += max(0, (int) ($row["errors"] ?? 0));
        }
        $failureRate = $total > 0 ? ($errors / $total) * 100 : 0.0;
        $failureLabel = number_format($failureRate, $failureRate < 1 ? 2 : 1, ",", ".") . "%";
        $stats =
            '<div class="stats-grid admin-performance-stats">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Requisições · 10d", $total, "route", "eventos canônicos") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Latência média", self::admin_performance_format_ms($avg), "speed", "média geral") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Falhas", $failureLabel, "error", $errors . " evento(s)") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Rota mais lenta",
                $slow ? self::admin_performance_format_ms((float) ($slow["avg_ms"] ?? 0)) : "—",
                "timer",
                $slow ? (string) ($slow["route"] ?? "") : "Sem dados",
            ) .
            '</div>';
        $routesCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head admin-performance-head"><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("route") .
                '<span>Rotas</span></h2><p>Detalhamento dos últimos 10 dias.' .
                ($updated !== "" ? " Última atualização: " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($updated) . "." : "") .
                '</p></div>' .
                self::admin_performance_rows_html($rows),
            "admin-performance-card",
        );
        return '<section class="admin-performance-screen">' .
            $stats .
            $telemetryCharts .
            $routesCard .
            '</section>';
    }''',
)
remove_method(presentation3, "admin_telemetry_variation_badge")


ops3 = "app/Runtime/AdminPages/AdminPagesRuntimeOperations03.php"
remove_method(ops3, "admin_telemetry_kpi_cards_html")
remove_method(ops3, "page_status")
replace_method(
    ops3,
    "page_admin_operations",
    r'''    public static function page_admin_operations(): void
    {
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_operations");
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Indicadores do negócio",
                "Relatório sob demanda de adoção, operação e financeiro da plataforma.",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_ops_finance_html(),
                "admin-ops-finance-card",
            );
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Indicadores do negócio · Desenvolvedor", $body);
    }''',
)
replace_method(
    ops3,
    "page_admin_health",
    r'''    public static function page_admin_health(): void
    {
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_health");
        $health = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::platform_health_snapshot();
        $checks = (array) ($health["checks"] ?? []);
        $counts = (array) ($health["counts"] ?? []);
        $components = (array) ($health["components"] ?? []);
        $state = (string) ($health["state"] ?? "Operacional");
        $stateClass = match ($state) {
            "Crítico" => "is-critical",
            "Atenção" => "is-attention",
            default => "is-operational",
        };
        $stateCopy = match ($state) {
            "Crítico" => "Uma condição estrutural exige intervenção técnica.",
            "Atenção" => "Há exceções técnicas que merecem investigação.",
            default => "Banco, storage, integridade, segurança e contrato de versão estão sem sinais de atenção.",
        };
        $summary = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="developer-control-status"><div><span class="eyebrow">Confiabilidade</span><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                '</h2><p>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($stateCopy) .
                '</p></div><span class="developer-state-badge ' .
                $stateClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($state === "Crítico" ? "crisis_alert" : ($state === "Atenção" ? "warning" : "verified")) .
                '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                '</span></span></div>',
            "admin-health-compact-card",
        );
        $items = [];
        if (empty(($components["database"] ?? [])["ok"])) {
            $items[] = ["icon" => "database_off", "time" => "Banco", "title" => "Conexão indisponível", "body" => "A conectividade essencial não foi confirmada.", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_diagnostics") . '">Diagnosticar</a>'];
        }
        if (empty(($components["storage"] ?? [])["ok"])) {
            $items[] = ["icon" => "folder_off", "time" => "Storage", "title" => "Escrita indisponível", "body" => "O diretório persistente não confirmou escrita.", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_diagnostics") . '">Diagnosticar</a>'];
        }
        $openErrors = (int) ($counts["open_errors"] ?? 0);
        if ($openErrors > 0) {
            $items[] = ["icon" => "bug_report", "time" => "Erros", "title" => $openErrors . " erro(s) aberto(s)", "body" => "Há incidentes técnicos sem resolução registrada.", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_errors") . '">Ver erros</a>'];
        }
        $locks = (int) ($counts["login_locks"] ?? 0);
        $scope = (int) ($counts["scope_alerts_24h"] ?? 0);
        if ($locks > 0 || $scope > 0) {
            $items[] = ["icon" => "security", "time" => "Segurança", "title" => ($locks + $scope) . " sinal(is) para revisar", "body" => $locks . " bloqueio(s) de login · " . $scope . " operação(ões) de escopo bloqueada(s).", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_security") . '">Ver segurança</a>'];
        }
        if (empty(($components["integrity"] ?? [])["ok"])) {
            $items[] = ["icon" => "gpp_bad", "time" => "Integridade", "title" => "Integridade exige revisão", "body" => "A cadeia de auditoria ou registros recentes não concluíram a verificação.", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_integrity") . '">Ver integridade</a>'];
        }
        if (empty(($components["version"] ?? [])["ok"])) {
            $issues = implode(", ", array_map("strval", (array) (($checks["version_contract"] ?? [])["issues"] ?? [])));
            $items[] = ["icon" => "deployed_code_alert", "time" => "Versão", "title" => "Contrato de versão divergente", "body" => $issues !== "" ? $issues : "A release publicada não concluiu o contrato determinístico.", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_diagnostics") . '">Diagnosticar</a>'];
        }
        $signals = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Exceções</h2><p>Somente sinais que mudam uma decisão técnica.</p></div></div>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($items, "Nenhuma exceção técnica ativa."),
            "admin-health-events-card",
        );
        $advanced = '<details class="form-panel developer-advanced-tools"><summary><span>Ferramentas avançadas</span></summary><div class="developer-tool-grid"><a class="developer-tool-card" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_diagnostics") .
            '"><span class="developer-tool-icon">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("troubleshoot") . '</span><span><b>Diagnóstico</b><small>Ambiente, runtime e storage sob demanda.</small></span></a><a class="developer-tool-card" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_deleted") .
            '"><span class="developer-tool-icon">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("restore_from_trash") . '</span><span><b>Recuperação</b><small>Restauração administrativa de registros preservados.</small></span></a></div></details>';
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Confiabilidade", "") .
            $summary .
            $signals .
            $advanced;
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Confiabilidade · Desenvolvedor", $body);
    }''',
)


ops4 = "app/Runtime/AdminPages/AdminPagesRuntimeOperations04.php"
replace_method(
    ops4,
    "page_admin_onboarding",
    r'''    public static function page_admin_onboarding(): void
    {
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_clinics", ["view" => "onboarding"]);
    }''',
)


ops6 = "app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php"
replace_once(
    ops6,
    '''        $trialEnding = $qInt('read.admin_pages.06.page_admin_painel.02');\n        $onboardingPending = $qInt('read.admin_pages.06.page_admin_painel.03');\n''',
    "",
)
replace_once(
    ops6,
    '''        $checks = \\Prontoo\\Runtime\\AdminPages\\AdminPagesRuntimeOperations01::platform_backend_selftest([\n            "open_errors" => $openErrors,\n            "login_locks" => $locks,\n            "scope_alerts_24h" => $scopeViolations24h,\n        ]);\n''',
    '''        $health = \\Prontoo\\Runtime\\AdminPages\\AdminPagesRuntimeOperations01::platform_health_snapshot([\n            "open_errors" => $openErrors,\n            "login_locks" => $locks,\n            "scope_alerts_24h" => $scopeViolations24h,\n        ]);\n        $checks = (array) ($health["checks"] ?? []);\n''',
)
text = read(ops6)
text = remove_block(text, "        if ($trialEnding > 0) {")
text = remove_block(text, "        if ($onboardingPending > 0) {")
write(ops6, text)
replace_once(
    ops6,
    '''            [\n                "actions" => $actions,\n                "checks" => $checks,\n                "locks" => $locks,\n                "scope_violations" => $scopeViolations24h,\n                "read_only" => $readOnly,\n                "trial_ending" => $trialEnding,\n                "onboarding_pending" => $onboardingPending,\n            ],\n''',
    '''            [\n                "actions" => $actions,\n                "health" => $health,\n            ],\n''',
)


ops8 = "app/Runtime/AdminPages/AdminPagesRuntimeOperations08.php"
replace_once(
    ops8,
    '''        $bodyRows = "";\n        foreach ($rows as $r) {\n''',
    r'''        $view = preg_replace("/[^a-z_]/", "", (string) ($_GET["view"] ?? "all"));
        if (!in_array($view, ["all", "attention", "onboarding", "readonly", "expiring"], true)) {
            $view = "all";
        }
        $filterSpecs = [
            "all" => ["Todos", "format_list_bulleted"],
            "attention" => ["Atenção", "priority_high"],
            "onboarding" => ["Onboarding", "playlist_add_check"],
            "readonly" => ["Somente leitura", "lock"],
            "expiring" => ["Vencendo", "event_upcoming"],
        ];
        $filters = '<nav class="notice-filter-chips lead-filter-chips ds-notice-filters" aria-label="Filtrar consultórios">';
        foreach ($filterSpecs as $filterKey => [$filterLabel, $filterIcon]) {
            $activeFilter = $view === $filterKey;
            $filters .= '<a class="lead-chip ds-filter-chip ' .
                ($activeFilter ? "active is-active" : "") .
                '" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href(
                        "admin_clinics",
                        $filterKey === "all" ? [] : ["view" => $filterKey],
                    ),
                ) .
                '"' .
                ($activeFilter ? ' aria-current="page"' : "") .
                '>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($filterIcon) .
                '<span class="lead-chip-label">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($filterLabel) .
                '</span></a>';
        }
        $filters .= '</nav>';
        $bodyRows = "";
        foreach ($rows as $r) {
''',
)
replace_once(
    ops8,
    '''            $billing = \\Prontoo\\Runtime\\SecurityAccess\\SecurityAccessRuntimeOperations03::billing_state($r);\n            $status = (string) ($billing["status"] ?? "active");\n''',
    r'''            $billing = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::billing_state($r);
            $status = (string) ($billing["status"] ?? "active");
            $dueCandidate = mb_trim((string) ($billing["paid_until"] ?? ""));
            if ($dueCandidate === "") {
                $dueCandidate = mb_trim((string) ($billing["trial_ends_at"] ?? ""));
            }
            $dueTimestamp = $dueCandidate !== "" ? strtotime($dueCandidate) : false;
            $now = time();
            $expiresSoon = empty($billing["exempt"]) &&
                $dueTimestamp !== false &&
                $dueTimestamp >= $now &&
                $dueTimestamp <= $now + 7 * 86400;
            $onboardingPending = (int) ($r["onboarding_done"] ?? 0) !== 1;
            $needsAttention = (int) $r["active"] !== 1 || !empty($billing["read_only"]) || $onboardingPending || $expiresSoon;
            $matchesFilter = match ($view) {
                "attention" => $needsAttention,
                "onboarding" => $onboardingPending,
                "readonly" => !empty($billing["read_only"]),
                "expiring" => $expiresSoon,
                default => true,
            };
            if (!$matchesFilter) {
                continue;
            }
''',
)
replace_once(
    ops8,
    '''                : '<div class="empty">Nenhum consultório exige atenção agora.</div>') .\n''',
    '''                : '<div class="empty">Nenhum consultório corresponde a este filtro.</div>') .\n''',
)
replace_once(
    ops8,
    '''        $body =\n            \\Prontoo\\Runtime\\UiComponents\\UiComponentsRuntimeOperations03::page_head("Consultórios", "") .\n''',
    r'''        $advanced = '<details class="form-panel developer-advanced-tools"><summary><span>Mais opções</span></summary><div class="developer-tool-grid"><a class="developer-tool-card" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_operations") .
            '"><span class="developer-tool-icon">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("insights") . '</span><span><b>Indicadores do negócio</b><small>Adoção, operação e financeiro sob demanda.</small></span></a></div></details>';
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Consultórios", "") .
''',
)
replace_once(
    ops8,
    '''                $onboardingHead . $table,\n''',
    '''                $onboardingHead . $filters . $advanced . $table,\n''',
)


ops9 = "app/Runtime/AdminPages/AdminPagesRuntimeOperations09.php"
replace_method(
    ops9,
    "page_admin_performance",
    r'''    public static function page_admin_performance(): void
    {
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_performance");
        $summary = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations02::telemetry_route_performance_summary(240);
        $telemetryCharts = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_performance_card_html();
        $content = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_observability_html(
            $summary,
            $telemetryCharts,
        );
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Observabilidade",
                "Requisições, latência, falhas e rotas para investigação técnica.",
            ) .
            $content;
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Observabilidade · Desenvolvedor", $body);
    }''',
)
replace_once(ops9, '"Avisos do Desenvolvedor",', '"Mensagens internas",')
replace_once(ops9, 'page_head("Avisos")', 'page_head("Mensagens internas")')
replace_once(ops9, '<span class="eyebrow">Central técnica</span><h2>Comunicação do Desenvolvedor</h2>', '<span class="eyebrow">Central técnica</span><h2>Mensagens internas</h2>')


contract = r'''<?php
declare(strict_types=1);
require_once __DIR__ . '/canonical-source.php';
$root = dirname(__DIR__);
require_once $root . '/app/Runtime/Autoload/ProntooAutoloader.php';
$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('Contrato de fontes JSON de telemetria: ' . $message);
    }
};
$assert(str_ends_with(\Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_views_file(), '/views.json'), 'views.json não é canônico');
$assert(str_ends_with(\Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_speed_file(), '/speed.json'), 'speed.json não é canônico');
$assert(str_ends_with(\Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations03::telemetry_database_records_file(), '/database.json'), 'database.json não é canônico');
$assert(class_exists(\Prontoo\Infrastructure\SupportTelemetry\TelemetryJsonStore::class), 'store JSON canônico não foi carregado');
$admin2 = (string) file_get_contents($root . '/app/Runtime/AdminPages/AdminPagesRuntimeOperations02.php');
$admin3 = (string) file_get_contents($root . '/app/Runtime/AdminPages/AdminPagesRuntimeOperations03.php');
$admin6 = (string) file_get_contents($root . '/app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php');
$admin9 = (string) file_get_contents($root . '/app/Runtime/AdminPages/AdminPagesRuntimeOperations09.php');
$login = (string) file_get_contents($root . '/app/Runtime/AuthOnboarding/AuthOnboardingRuntimeOperations07.php');
$presentation3 = (string) file_get_contents($root . '/app/Presentation/AdminPages/AdminPagesPresentationOperations03.php');
$speedGeometry = (string) file_get_contents($root . '/app/Presentation/SpeedChartGeometry/SpeedChartGeometryPresentationOperations01.php');
$css = (string) file_get_contents($root . '/public/assets/presentation.css');
foreach (["\"Velocidade\"", "\"Volume\"", "\"visual_mode\" => \"telemetry\""] as $token) {
    $assert(str_contains($admin2, $token), 'gráficos compartilhados perderam ' . $token);
}
$assert(
    str_contains($admin2, 'admin_global_metric_series_24h("route")') &&
    str_contains($admin2, 'admin_global_metric_series_24h("database")') &&
    str_contains($admin2, 'admin_global_volume_series_30d("page_load")') &&
    str_contains($admin2, 'admin_global_volume_series_30d("database_queries")'),
    'gráficos devem usar as séries canônicas de latência e volume',
);
$assert(class_exists(\Prontoo\Infrastructure\Database\PdoQueryTelemetry::class), 'acumulador de latência PDO ausente');
\Prontoo\Infrastructure\Database\PdoQueryTelemetry::reset();
\Prontoo\Infrastructure\Database\PdoQueryTelemetry::record(2000000);
\Prontoo\Infrastructure\Database\PdoQueryTelemetry::record(4000000);
$querySnapshot = \Prontoo\Infrastructure\Database\PdoQueryTelemetry::snapshot();
$assert(
    (int) ($querySnapshot['count'] ?? 0) === 2 &&
    (int) ($querySnapshot['duration_ns'] ?? 0) === 6000000 &&
    abs((float) ($querySnapshot['average_ms'] ?? 0.0) - 3.0) < 0.000001,
    'média ponderada de consultas PDO divergente',
);
$assert(
    str_contains($admin9, 'telemetry_route_performance_summary(240)') &&
    !str_contains($admin9, 'telemetry_comparative_summary()') &&
    !str_contains($admin9, 'admin_telemetry_kpi_cards_html('),
    'Observabilidade deve usar uma única camada de resumo',
);
$assert(
    !str_contains($admin3, 'admin_telemetry_kpi_cards_html(') &&
    !str_contains($admin3, 'page_status()') &&
    !str_contains($admin6, 'admin_performance_card_html('),
    'renderers legados de telemetria não podem voltar à central do Desenvolvedor',
);
foreach (["Requisições · 10d", "Latência média", "Falhas", "Rota mais lenta"] as $label) {
    $assert(str_contains($presentation3, $label), 'Observabilidade perdeu o indicador ' . $label);
}
$assert(!str_contains($presentation3, 'stat_card("Landing Page"'), 'Landing Page não deve duplicar o resumo de Observabilidade');
$assert(str_contains($admin2, 'data-refresh-ms="60000"'), 'gráficos devem atualizar a cada minuto');
$loadAreaPos = strpos($presentation3, '$loadArea =');
$responseAreaPos = strpos($presentation3, '$responseArea =');
$fillOrderPos = strpos($presentation3, '$fillAreas = $loadArea . $responseArea;');
$assert($loadAreaPos !== false && $responseAreaPos > $loadAreaPos && $fillOrderPos !== false, 'ordem das áreas dos gráficos divergiu');
foreach ([
    '.metric-dual-time-chart[data-metric-visual="telemetry"] .metric-chart-fill-load{fill:#1f6f56;opacity:.5}',
    '.metric-dual-time-chart[data-metric-visual="telemetry"] .metric-chart-fill-response{fill:#347963;opacity:.5}',
    '.metric-dual-time-chart[data-metric-visual="telemetry"] .metric-chart-line-load{stroke:none}',
    '.metric-dual-time-chart[data-metric-visual="telemetry"] .metric-chart-line-response{stroke:none}',
] as $token) {
    $assert(str_contains($css, $token), 'hierarquia visual da telemetria divergente: ' . $token);
}
foreach ([
    'class="metric-chart-fill-load" style="fill-opacity:0.5"',
    'class="metric-chart-fill-response" style="fill-opacity:0.5"',
] as $token) {
    $assert(str_contains($speedGeometry, $token), 'gráfico de 30 dias perdeu transparência obrigatória: ' . $token);
}
$assert(str_contains($login, 'telemetry_route_requests_series_20d()') && str_contains($login, 'telemetry_sequence_records_series_20d()'), 'rodapé deixou de usar as séries canônicas');
unset($root, $assert, $admin2, $admin3, $admin6, $admin9, $login, $presentation3, $speedGeometry, $css, $querySnapshot, $loadAreaPos, $responseAreaPos, $fillOrderPos, $token, $label);
'''
write("tools/telemetry-json-source-contract-check", contract)


docs = '''# Central do Desenvolvedor

## Princípio

O ambiente global do Desenvolvedor é um plano de controle operacional. A interface prioriza decisões e exceções; informação normal, repetida ou de baixa frequência permanece fora do primeiro plano.

## Hierarquia

1. **Visão geral** — responde somente ao estado atual e ao que precisa de intervenção.
2. **Consultórios** — ciclo de vida e gestão, com filtros de Todos, Atenção, Onboarding, Somente leitura e Vencendo.
3. **Confiabilidade** — exceções técnicas e acesso direto a Erros, Segurança e Integridade.
4. **Observabilidade** — requisições, latência, falhas, gráficos e comportamento das rotas.
5. **Administração** — hub de acesso, comunicação e auditoria; configuração e manutenção ficam progressivamente reveladas.

## Terceira passada de carga cognitiva

- A Visão geral contém apenas **Estado do Prontoo** e **Precisa de você**.
- Onboarding e vencimentos deixam de ser alertas globais e passam a filtros contextuais de Consultórios.
- `admin_onboarding` permanece apenas como alias de compatibilidade e redireciona ao filtro de onboarding.
- `admin_operations` vira **Indicadores do negócio**, relatório sob demanda acessível por Mais opções em Consultórios.
- Confiabilidade mostra somente exceções; Diagnóstico e Recuperação ficam em Ferramentas avançadas.
- Observabilidade tem uma única camada de quatro KPIs: Requisições, Latência média, Falhas e Rota mais lenta; depois gráficos e rotas.
- Administração não replica seus filhos na navegação da PageHead. O hub mostra Usuários, Mensagens internas, Avisos aos consultórios e Auditoria; Configurações e Manutenção ficam em Configuração avançada.
- O estado técnico é calculado por um único snapshot de saúde compartilhado por Visão geral e Confiabilidade.

## Regra de UX

Uma informação só ocupa espaço permanente se alterar uma decisão frequente. Drill-down existe para explicar uma exceção, não para repetir o resumo. Ferramentas raras continuam acessíveis, mas não competem visualmente com a operação diária.
'''
write("docs/operations/developer-control-center.md", docs)

for path in [
    ops1,
    presentation1,
    presentation3,
    ops3,
    ops4,
    ops6,
    ops8,
    ops9,
    "tools/telemetry-json-source-contract-check",
    "docs/operations/developer-control-center.md",
]:
    data = read(path)
    if "admin_telemetry_kpi_cards_html(" in data and path in [ops3, ops9]:
        raise RuntimeError(f"legacy telemetry renderer remains in {path}")

print("developer control center refinement applied")
