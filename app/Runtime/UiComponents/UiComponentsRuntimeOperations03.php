<?php
declare(strict_types=1);

namespace Prontoo\Runtime\UiComponents;

final class UiComponentsRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function page_operation_specs(string $current, array $c): array
    
    {
    
        if (!$c) {
            return [];
        }
        if ($current === "audit") {
            return [];
        }
        $parent = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::context_parent_for_route($current, $c);
        if (($c["scope"] ?? "") === "global") {
            $alertView = (string) ($_GET["view"] ?? "received");
            if (!in_array($alertView, ["received", "sent"], true)) {
                $alertView = "received";
            }
            if ($current === "admin_alerts") {
                return [
                    ["admin_alerts", "Recebidos", "inbox", ["view" => "received"]],
                    ["admin_alerts", "Enviados", "outbox", ["view" => "sent"]],
                    ["admin_alerts", "Novo aviso", "add_comment", ["view" => $alertView, "compose" => "1"]],
                ];
            }
            if ($current === "admin_maintenance") {
                return [
                    ["admin_maintenance", "Manutenção", "construction"],
                    ["admin_settings", "Configurações", "settings"],
                    ["admin_global_notices", "Avisos globais", "campaign"],
                ];
            }
            return match ($parent) {
                "admin_painel" => [["admin_painel", "Visão geral", "space_dashboard"]],
                "admin_clinics" => [
                    ["admin_clinics", "Consultórios", "home_health"],
                    ["admin_onboarding", "Onboarding", "playlist_add_check"],
                    ["admin_operations", "Operação", "account_tree"],
                ],
                "admin_health" => [
                    ["admin_health", "Confiabilidade", "shield"],
                    ["admin_errors", "Erros", "bug_report"],
                    ["admin_security", "Segurança", "security"],
                    ["admin_integrity", "Integridade", "verified_user"],
                    ["admin_diagnostics", "Diagnósticos", "troubleshoot"],
                    ["admin_deleted", "Recuperação", "restore_from_trash"],
                ],
                "admin_performance" => [["admin_performance", "Observabilidade", "monitoring"]],
                "admin_administration" => [
                    ["admin_administration", "Administração", "tune"],
                    ["admin_people", "Usuários", "groups"],
                    ["admin_alerts", "Comunicação", "campaign"],
                    ["admin_global_notices", "Avisos globais", "notifications_active"],
                    ["admin_maintenance", "Manutenção", "construction"],
                    ["admin_settings", "Configurações", "settings"],
                    ["admin_audit", "Auditoria", "history"],
                ],
                default => [],
            };
        }
        $role = (string) ($c["role"] ?? "");
        $cashLabel = $role === "recepcionista" ? "Caixa" : "Painel";
        $cashIcon =
            $role === "recepcionista"
                ? (is_callable([\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::class, 'reception_cash_state_icon'])
                    ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::reception_cash_state_icon($c)
                    : "point_of_sale")
                : "monitoring";
        $agendaParams = [];
        if (
            isset($_GET["d"]) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET["d"])
        ) {
            $agendaParams["d"] = (string) $_GET["d"];
        }
        if (isset($_GET["doctor"]) && (int) $_GET["doctor"] > 0) {
            $agendaParams["doctor"] = (int) $_GET["doctor"];
        }
        $peopleRoute = "patients";
        $ops = match ($parent) {
            "appointments" => [
                [
                    "appointments",
                    "Painel",
                    "space_dashboard",
                    $agendaParams + ["view" => "resumo"],
                ],
                [
                    "appointments",
                    "Diário",
                    "today",
                    $agendaParams + ["view" => "diario"],
                ],
                [
                    "appointments",
                    "Semanal",
                    "view_week",
                    $agendaParams + ["view" => "semanal"],
                ],
                [
                    "appointments",
                    "Mensal",
                    "calendar_month",
                    $agendaParams + ["view" => "mensal"],
                ],
            ],
            "patients" => [
                ["leads", "Interessados", "person_search"],
                ["patients", "Pacientes", "patient_list"],
            ],
            "documents" => [
                ["documents", "Modelos", "edit_note", ["models" => "1"]],
                ["documents", "Emitidos", "description", ["recent" => "1"]],
            ],
            "tasks" => [["tasks", "Tarefas", "task_alt"]],
            "financial" => [["financial", $cashLabel, $cashIcon]],
            "settings" => [
                ["settings", "Identificação", "home_health", ["tab" => "perfil"]],
                [
                    "settings",
                    "Departamentos",
                    "corporate_fare",
                    ["tab" => "setores"],
                ],
                ["procedures", "Procedimentos", "medical_services"],
                ["users", "Colaboradores", "groups"],
            ],
            "profile" => [
                ["settings", "Aparência", "palette", ["tab" => "visual"]],
                ["settings", "Assinatura", "credit_card", ["tab" => "assinatura"]],
                ["permissions", "Permissões", "admin_panel_settings"],
                ["operations", "Fluxo", "account_tree"],
            ],
            "maestro" => [
                ["maestro", "Rotinas", "event_repeat"],
                ["tasks", "Tarefas", "task_alt"],
            ],
            default => [],
        };
        if (
            $parent === "patients" &&
            is_callable([\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::class, 'has_effective_role']) &&
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente")
        ) {
            $ops[] = ["creditors", "Credores", "receipt_long"];
        }
        if ($role === "assistente" || $role === "medico") {
            $ops = array_values(
                array_filter(
                    $ops,
                    static  fn($op) => !in_array(
                        (string) $op[0],
                        [
                            "leads",
                            "financial",
                            "procedures",
                            "users",
                            "permissions",
                            "operations",
                            "settings",
                        ],
                        true,
                    ),
                ),
            );
        }
        if ($role === "recepcionista") {
            $ops = array_values(
                array_filter(
                    $ops,
                    static  fn($op) => !in_array(
                        (string) $op[0],
                        [
                            "procedures",
                            "users",
                            "permissions",
                            "operations",
                            "settings",
                            "maestro",
                        ],
                        true,
                    ),
                ),
            );
        }
        return $ops;
    
    }

    public static function page_operations_html(string $current, array $c): string
    
    {
    
        $specs = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_operation_specs($current, $c);
        if (!$specs) {
            return "";
        }
        $seen = [];
        $html = "";
        foreach ($specs as $op) {
            if (!is_array($op) || count($op) < 3) {
                continue;
            }
            $route = (string) $op[0];
            if (
                $route === "" ||
                isset($seen[$route . ":" . json_encode($op[3] ?? [])])
            ) {
                continue;
            }
            if (is_callable([\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::class, 'can']) && !\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can($route)) {
                continue;
            }
            $seen[$route . ":" . json_encode($op[3] ?? [])] = 1;
            $html .= \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::operation_link_html(
                $route,
                (string) $op[1],
                (string) $op[2],
                $current,
                (array) ($op[3] ?? []),
            );
        }
        return $html !== ""
            ? '<nav class="pagehead-controls pagehead-controls--navigation" aria-label="Operações">' .
                    $html .
                    "</nav>"
            : "";
    
    }

    public static function page_head_icon_name(string $title = ""): string
    
    {
    
        $current = \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route();
        $params = $_GET;
        if ($current === "admin_painel") {
            return "space_dashboard";
        }
        if (is_callable([\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::class, 'prontoo_icon_for_route_label'])) {
            if (
                str_starts_with($current, "admin_") &&
                is_callable([\Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::class, 'admin_nav_parent'])
            ) {
                $parent = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_nav_parent($current);
                if ($parent !== $current) {
                    return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for_route_label(
                        $parent,
                        $title,
                        (array) $params,
                        \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for_route_label(
                            $current,
                            $title,
                            (array) $params,
                            "monitoring",
                        ),
                    );
                }
            }
            $ico = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for_route_label(
                $current,
                $title,
                (array) $params,
                "",
            );
            if ($ico !== "") {
                return $ico;
            }
        }
        if (str_starts_with($current, "admin_")) {
            $parent = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_nav_parent($current);
            if (isset(PRONTOO_ADMIN_ACTIONS[$parent]["icon"])) {
                return (string) PRONTOO_ADMIN_ACTIONS[$parent]["icon"];
            }
        }
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
        if ($current === "painel" && ($c["scope"] ?? "") === "clinic") {
            return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_icon(
                (string) ($c["role"] ?? ""),
                (int) ($c["clinic_id"] ?? 0),
            );
        }
        if (
            $current === "financial" &&
            ($c["scope"] ?? "") === "clinic" &&
            (string) ($c["role"] ?? "") === "recepcionista"
        ) {
            return \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::reception_cash_state_icon($c);
        }
        $actions = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::actions();
        if (isset($actions[$current]["icon"])) {
            return (string) $actions[$current]["icon"];
        }
        $map = [
            "install" => "settings_applications",
            "login" => "login",
            "signup" => "add_business",
            "logout" => "logout",
            "patient" => "patient_list",
            "document_view" => "visibility",
            "document_print" => "print",
            "admin_deleted" => "restore_from_trash",
            "admin_diagnostics" => "troubleshoot",
            "admin_instabilities" => "crisis_alert",
            "admin_integrity" => "verified_user",
            "admin_security" => "security",
            "maintenance" => "construction",
        ];
        if (isset($map[$current])) {
            return $map[$current];
        }
        $t = mb_strtolower($title);
        if (str_contains($t, "documento")) {
            return "description";
        }
        if (str_contains($t, "paciente")) {
            return "patient_list";
        }
        if (str_contains($t, "anotação") || str_contains($t, "anotacao")) {
            return "sticky_note_2";
        }
        if (str_contains($t, "agenda") || str_contains($t, "consulta")) {
            return "calendar_month";
        }
        if (str_contains($t, "atividade")) {
            return "history";
        }
        if (str_contains($t, "aviso") || str_contains($t, "notifica")) {
            return "campaign";
        }
        if (str_contains($t, "tarefa")) {
            return "task_alt";
        }
        if (str_contains($t, "colaborador")) {
            return "groups";
        }
        if (str_contains($t, "caixa")) {
            return "point_of_sale";
        }
        if (str_contains($t, "credor")) {
            return "receipt_long";
        }
        if (str_contains($t, "financeiro")) {
            return "payments";
        }
        if (str_contains($t, "procedimento")) {
            return "medical_services";
        }
        if (str_contains($t, "consultório")) {
            return "home_health";
        }
        if (str_contains($t, "permiss")) {
            return "admin_panel_settings";
        }
        return "monitoring";
    
    }

    public static function page_head(string $title, string $sub = "", string $action = ""): string
    {
        $icon = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head_icon_name($title);
        $context = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
        if (($context["scope"] ?? "") === "global") {
            $action = "";
        }
        $operations = $context
            ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_operations_html(
                \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route(),
                $context,
            )
            : "";
        return \Prontoo\Presentation\UiComponents\PageHeadControlPresentationOperations01::pageHead(
            $title,
            $icon,
            $operations,
            $action,
        );
    }

    public static function action_icon_for(string $label): string
    
    {
    
        $l = mb_strtolower($label);
        if (is_callable([\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::class, 'prontoo_icon_for_route_label'])) {
            $byLabel = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for_route_label("", $label, [], "");
            if ($byLabel !== "") {
                return $byLabel;
            }
        }
        if (str_contains($l, "salvar")) {
            return "save";
        }
        if (str_contains($l, "excluir") || str_contains($l, "remover")) {
            return "delete";
        }
        if (str_contains($l, "cancelar") || str_contains($l, "descartar")) {
            return "close";
        }
        if (str_contains($l, "bloquear")) {
            return "event_busy";
        }
        if (str_contains($l, "consulta") || str_contains($l, "agenda")) {
            return "calendar_month";
        }
        if (str_contains($l, "paciente")) {
            return "patient_list";
        }
        if (str_contains($l, "credor")) {
            return "receipt_long";
        }
        if (str_contains($l, "interessado")) {
            return "person_search";
        }
        if (str_contains($l, "tarefa")) {
            return "add_task";
        }
        if (str_contains($l, "aviso") || str_contains($l, "notifica")) {
            return "campaign";
        }
        if (str_contains($l, "alterar")) {
            return "edit";
        }
        if (str_contains($l, "resolver")) {
            return "task_alt";
        }
        if (str_contains($l, "assinatura")) {
            return "credit_card";
        }
        if (str_contains($l, "modelo")) {
            return "note_add";
        }
        if (str_contains($l, "procedimento")) {
            return "medical_services";
        }
        return "add";
    
    }

    public static function action_summary_label(string $label, string $iconName = ""): string
    
    {
    
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName !== "" ? $iconName : \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_icon_for($label)) .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span>";
    
    }
}
