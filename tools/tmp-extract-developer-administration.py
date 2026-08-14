from pathlib import Path
import re

runtime = Path('app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php')
text = runtime.read_text()

admin_pattern = r'\n    public static function page_admin_administration\(\): void.*?\n    \}\n\n    public static function page_admin_people'
admin_replacement = r'''
    public static function page_admin_administration(): void

    {

        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_administration");
        $content = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::developer_administration_tools_html(
            static fn(string $route): string => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route),
        );
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Administração", "") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($content, "developer-administration-card");
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Administração · Desenvolvedor", $body);
    }

    public static function page_admin_people'''
text, count = re.subn(admin_pattern, lambda _m: admin_replacement, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit(f'Runtime06 administration extraction match count={count}')

overview_pattern = r'\n        \$auditChainOk = .*?\\Prontoo\\Runtime\\UiComponents\\UiComponentsRuntimeOperations02::page\("Visão geral · Desenvolvedor", \$body\);\n'
overview_replacement = r'''
        $overview = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::developer_overview_html(
            [
                "actions" => $actions,
                "checks" => $checks,
                "locks" => $locks,
                "scope_violations" => $scopeViolations24h,
                "read_only" => $readOnly,
                "trial_ending" => $trialEnding,
                "onboarding_pending" => $onboardingPending,
            ],
            static fn(string $route): string => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route),
            static fn(string $label, string $icon): string => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label($label, $icon),
        );
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Visão geral", "") .
            $overview;
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Visão geral · Desenvolvedor", $body);
'''
text, count = re.subn(overview_pattern, lambda _m: overview_replacement, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit(f'Runtime06 overview extraction match count={count}')
runtime.write_text(text)

presentation = Path('app/Presentation/AdminPages/AdminPagesPresentationOperations03.php')
text = presentation.read_text()
if 'developer_administration_tools_html' in text or 'developer_overview_html' in text:
    raise SystemExit('Developer presentation helper already exists')
helpers = r'''

    public static function developer_overview_html(array $context, callable $href, callable $actionLabel): string

    {

        $actions = (array) ($context["actions"] ?? []);
        $checks = (array) ($context["checks"] ?? []);
        $locks = (int) ($context["locks"] ?? 0);
        $scopeViolations = (int) ($context["scope_violations"] ?? 0);
        $readOnly = (int) ($context["read_only"] ?? 0);
        $trialEnding = (int) ($context["trial_ending"] ?? 0);
        $onboardingPending = (int) ($context["onboarding_pending"] ?? 0);
        $auditChainOk = !empty(($checks["audit_chain"] ?? [])["ok"]);
        $versionContractOk = !empty(($checks["version_contract"] ?? [])["ok"]);
        $integrityOk = $auditChainOk && (int) ($checks["integrity_alerts"] ?? 0) === 0;
        $securityOk = $locks === 0 && $scopeViolations === 0;
        $critical = empty($checks["database"]) || empty($checks["storage"]) || !$auditChainOk || !$versionContractOk;
        $state = $critical ? "Crítico" : ($actions ? "Atenção" : "Operacional");
        $stateCopy = match ($state) {
            "Crítico" => "Há uma condição estrutural que exige intervenção técnica.",
            "Atenção" => "A plataforma está disponível, mas existem decisões ou sinais que merecem revisão.",
            default => "Nenhuma condição relevante exige intervenção neste momento.",
        };
        $stateClass = match ($state) {
            "Crítico" => "is-critical",
            "Atenção" => "is-attention",
            default => "is-operational",
        };
        $statusCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="developer-control-status"><div><span class="eyebrow">Estado do Prontoo</span><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                '</h2><p>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($stateCopy) .
                '</p><small>Atualizado em ' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(date("d/m/Y · H:i")) .
                '</small></div><span class="developer-state-badge ' .
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
                '<div><b>Nada precisa de você agora</b><p>Não há pendências ou sinais relevantes aguardando intervenção.</p></div></div>';
        $actionsCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Precisa de você</h2><p>Somente decisões e sinais que justificam intervenção.</p></div></div>' . $actionsBody,
            "developer-actions-card",
        );
        $clinicStats =
            '<div class="stats-grid developer-compact-stats">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Somente leitura", $readOnly, "lock", "consultórios") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Vencem em 7 dias", $trialEnding, "hourglass_top", "assinaturas") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Onboarding pendente", $onboardingPending, "playlist_add_check", "consultórios") .
            "</div>";
        $clinicsCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Consultórios</h2><p>Ciclo de vida, adoção e situação comercial.</p></div></div>' .
                $clinicStats .
                '<a class="ghost small developer-domain-link" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href("admin_clinics")) .
                '">' .
                (string) $actionLabel("Abrir Consultórios", "arrow_forward") .
                "</a>",
            "developer-domain-card",
        );
        $platformStats =
            '<div class="stats-grid developer-platform-stats">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Banco", !empty($checks["database"]) ? "Normal" : "Atenção", "database", "conectividade") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Storage", !empty($checks["storage"]) ? "Normal" : "Atenção", "folder", "persistência") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Integridade", $integrityOk ? "Normal" : "Atenção", "verified_user", "auditoria") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Segurança", $securityOk ? "Normal" : "Atenção", "shield", "acesso e escopo") .
            "</div>";
        $platformCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Plataforma</h2><p>Confiabilidade resumida; detalhes aparecem apenas quando necessários.</p></div></div>' .
                $platformStats .
                '<a class="ghost small developer-domain-link" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href("admin_health")) .
                '">' .
                (string) $actionLabel("Abrir Confiabilidade", "arrow_forward") .
                "</a>",
            "developer-domain-card",
        );
        $observabilityCard = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Observabilidade</h2><p>Latência, volume de requisições e comportamento das rotas ficam fora da visão diária e disponíveis para investigação.</p></div></div><a class="ghost small developer-domain-link" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href("admin_performance")) .
                '">' .
                (string) $actionLabel("Abrir Observabilidade", "monitoring") .
                "</a>",
            "developer-observability-card",
        );
        return $statusCard .
            $actionsCard .
            '<div class="developer-overview-grid">' .
            $clinicsCard .
            $platformCard .
            "</div>" .
            $observabilityCard;
    }

    public static function developer_administration_tools_html(callable $href): string

    {

        $tools = [
            ["groups", "Usuários e pessoas", "Credenciais, vínculos e identidades administrativas da plataforma.", "admin_people"],
            ["campaign", "Comunicação técnica", "Avisos entre perfis do Desenvolvedor e acompanhamento das mensagens recebidas.", "admin_alerts"],
            ["notifications_active", "Avisos globais", "Comunicações institucionais exibidas nos ambientes dos consultórios.", "admin_global_notices"],
            ["construction", "Manutenção", "Modo de manutenção e controles operacionais extraordinários.", "admin_maintenance"],
            ["settings", "Configurações", "Parâmetros globais que não pertencem à operação cotidiana.", "admin_settings"],
            ["history", "Auditoria", "Rastreabilidade administrativa e eventos relevantes da plataforma.", "admin_audit"],
        ];
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
        return '<div class="section-head"><div><h2>Ferramentas administrativas</h2><p>Governança, comunicação e suporte ficam agrupados aqui para não competir com a operação diária.</p></div></div>' .
            $grid .
            "</div>";
    }
'''
pos = text.rfind('\n}')
if pos < 0:
    raise SystemExit('Presentation class closing brace not found')
presentation.write_text(text[:pos] + helpers + text[pos:])
