from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = ROOT / "app/Runtime/AdminPages/AdminPagesRuntimeOperations08.php"
PRESENTATION = ROOT / "app/Presentation/AdminPages/AdminPagesPresentationOperations03.php"

runtime = RUNTIME.read_text(encoding="utf-8")
start_marker = '        $view = preg_replace("/[^a-z_]/", "", (string) ($_GET["view"] ?? "all"));\n'
end_marker = '        $body =\n'
start = runtime.index(start_marker)
end = runtime.index(end_marker, start)
replacement = r'''        $directoryRows = [];
        foreach ($rows as $r) {
            $id = (int) ($r["id"] ?? 0);
            $directoryRows[] = [
                "clinic" => $r,
                "billing" => \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::billing_state($r),
                "professionals" => (int) ($peopleCounts[$id]["professionals"] ?? 0),
                "collaborators" => (int) ($peopleCounts[$id]["collaborators"] ?? 0),
                "global_admin_owned" => $id > 0 && \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_is_global_admin_owned($id),
            ];
        }
        $directory = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::developer_clinic_directory_html(
            $directoryRows,
            (string) ($_GET["view"] ?? "all"),
            static fn(string $route, array $params = []): string => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route, $params),
            static fn(mixed $value): string => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($value),
        );
'''
runtime = runtime[:start] + replacement + runtime[end:]
runtime = runtime.replace(
    '                $onboardingHead . $filters . $table . $advanced,\n',
    '                $directory,\n',
    1,
)
if '$onboardingHead . $filters . $table . $advanced' in runtime:
    raise RuntimeError("legacy clinic directory rendering remained in Runtime08")
RUNTIME.write_text(runtime, encoding="utf-8")

presentation = PRESENTATION.read_text(encoding="utf-8")
insert_marker = '    public static function developer_overview_html(array $context, callable $href, callable $actionLabel): string\n'
if 'public static function developer_clinic_directory_html(' in presentation:
    raise RuntimeError("clinic directory helper already exists")
helper = r'''    public static function developer_clinic_directory_html(
        array $rows,
        string $view,
        callable $href,
        callable $dateLabel,
    ): string {
        $view = preg_replace("/[^a-z_]/", "", $view) ?: "all";
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
                    (string) $href("admin_clinics", $filterKey === "all" ? [] : ["view" => $filterKey]),
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
        $now = time();
        foreach ($rows as $entry) {
            $r = (array) ($entry["clinic"] ?? []);
            $billing = (array) ($entry["billing"] ?? []);
            $id = (int) ($r["id"] ?? 0);
            $professionals = (int) ($entry["professionals"] ?? 0);
            $collaborators = (int) ($entry["collaborators"] ?? 0);
            $globalAdminOwned = !empty($entry["global_admin_owned"]);
            $dueCandidate = mb_trim((string) ($billing["paid_until"] ?? ""));
            if ($dueCandidate === "") {
                $dueCandidate = mb_trim((string) ($billing["trial_ends_at"] ?? ""));
            }
            $dueTimestamp = $dueCandidate !== "" ? strtotime($dueCandidate) : false;
            $expiresSoon = empty($billing["exempt"]) &&
                $dueTimestamp !== false &&
                $dueTimestamp >= $now &&
                $dueTimestamp <= $now + 7 * 86400;
            $onboardingPending = (int) ($r["onboarding_done"] ?? 0) !== 1;
            $needsAttention = (int) ($r["active"] ?? 0) !== 1 || !empty($billing["read_only"]) || $onboardingPending || $expiresSoon;
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
            $attentionClass = "is-stable";
            $attentionIcon = "verified";
            $attentionLabel = "Ativo";
            $attentionNote = "";
            if ((int) ($r["active"] ?? 0) !== 1) {
                $attentionClass = "is-muted";
                $attentionIcon = "pause_circle";
                $attentionLabel = "Inativo";
                $attentionNote = "Consultório desativado";
            } elseif (!empty($billing["exempt"])) {
                $attentionClass = "is-exempt";
                $attentionIcon = "workspace_premium";
                $attentionLabel = "Isento";
                $attentionNote = $globalAdminOwned
                    ? "Isento do Desenvolvedor fora das estatísticas"
                    : "Isento de cobrança";
            } elseif (!empty($billing["read_only"])) {
                $attentionClass = "is-critical";
                $attentionIcon = "lock";
                $attentionLabel = "Somente leitura";
                $attentionNote = "Alterações temporariamente bloqueadas";
            }
            $attentionChip = '<span class="clinic-attention-chip ' .
                $attentionClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($attentionIcon) .
                '<b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($attentionLabel) .
                '</b></span>';
            $actions = '<a class="pagehead-control pagehead-control--secondary clinic-actions-summary" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href("admin_clinics", ["clinic_id" => $id])) .
                '" aria-label="Abrir gestão do consultório">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_forward") .
                '<span>Gerenciar</span></a>';
            $createdLabel = (string) $dateLabel($r["created_at"] ?? "");
            $dueLabel = !empty($billing["exempt"])
                ? "Isento"
                : (mb_trim((string) ($billing["paid_until"] ?? "")) !== ""
                    ? (string) $dateLabel($billing["paid_until"])
                    : (!empty($billing["trial_active"])
                        ? (string) $dateLabel($billing["trial_ends_at"] ?? "")
                        : "Sem vencimento"));
            $professionLabel = mb_trim((string) ($r["responsible_profession"] ?? "")) ?: "Área não informada";
            $adminStatsNote = !empty($billing["exempt"]) && $globalAdminOwned
                ? '<span class="ds-clinic-test-pill">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("bar_chart_off") .
                    '<span>Fora das estatísticas</span></span>'
                : "";
            $clinicMetrics = '<span class="ds-clinic-row-meta-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event") .
                '<b>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($createdLabel) . '</b><small>Data Cadastro</small></span>' .
                '<span class="ds-clinic-row-meta-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("stethoscope") .
                '<b>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $professionals) . '</b><small>Profissionais</small></span>' .
                '<span class="ds-clinic-row-meta-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("groups") .
                '<b>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $collaborators) . '</b><small>Colaboradores</small></span>' .
                '<span class="ds-clinic-row-meta-chip ds-clinic-due-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
                '<b>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($dueLabel) . '</b><small>Vencimento</small></span>';
            $bodyRows .= '<article class="clinic-attention-item ds-clinic-list-item ds-clinic-list-item-inline ' .
                $attentionClass .
                '"><div class="ds-clinic-list-identity clinic-attention-main"><span class="clinic-attention-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($attentionIcon) .
                '</span><span class="clinic-attention-copy"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($r["display_name"] ?? "Consultório")) .
                '</strong><small>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(((int) ($r["active"] ?? 0) ? "Operando" : "Inativo") . " · " . $professionLabel) .
                '</small></span></div><div class="clinic-attention-state ds-clinic-list-status">' .
                $attentionChip .
                ($attentionNote !== "" ? '<small>' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($attentionNote) . '</small>' : "") .
                $adminStatsNote .
                '</div><div class="ds-clinic-row-meta">' .
                $clinicMetrics .
                '</div><div class="clinic-attention-actions">' .
                $actions .
                '</div></article>';
        }
        $table = '<div class="clinic-attention-list">' .
            ($bodyRows !== "" ? $bodyRows : '<div class="empty">Nenhum consultório corresponde a este filtro.</div>') .
            '</div>';
        $head = '<div class="admin-onboarding-head clinic-attention-head"><div><span class="eyebrow">Acompanhamento</span><h2>Consultórios</h2><p>Leitura compacta por status, data de cadastro, profissionais, colaboradores e vencimento.</p></div></div>';
        $advanced = '<details class="form-panel developer-advanced-tools"><summary><span>Mais opções</span></summary><div class="developer-tool-grid"><a class="developer-tool-card" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href("admin_operations")) .
            '"><span class="developer-tool-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("insights") .
            '</span><span><b>Indicadores do negócio</b><small>Adoção, operação e financeiro sob demanda.</small></span></a></div></details>';
        return $head . $filters . $table . $advanced;
    }

'''
presentation = presentation.replace(insert_marker, helper + insert_marker, 1)
PRESENTATION.write_text(presentation, encoding="utf-8")

print("clinic presentation extracted")
