<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\UiComponents;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

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
        $parent = context_parent_for_route($current, $c);
        if (($c["scope"] ?? "") === "global") {
            $alertView = (string) ($_GET["view"] ?? "received");
            if (!in_array($alertView, ["received", "sent"], true)) {
                $alertView = "received";
            }
            return match ($parent) {
                "admin_painel" => [
                    ["admin_painel", "Desenvolvedor", "space_dashboard"],
                    ["admin_operations", "Operação", "account_tree"],
                ],
                "admin_clinics" => [
                    ["admin_clinics", "Consultórios", "home_health"],
                    ["admin_people", "Usuários", "groups"],
                ],
                "admin_health" => [["admin_health", "Incidentes", "crisis_alert"]],
                "admin_alerts" => [
                    ["admin_alerts", "Recebidos", "inbox", ["view" => "received"]],
                    ["admin_alerts", "Enviados", "outbox", ["view" => "sent"]],
                    [
                        "admin_alerts",
                        "Novo aviso",
                        "add_comment",
                        ["view" => $alertView, "compose" => "1"],
                    ],
                ],
                "admin_maintenance" => [
                    ["admin_maintenance", "Manutenção", "construction"],
                    ["admin_global_notices", "Avisos globais", "campaign"],
                    ["admin_deleted", "Excluídos", "restore_from_trash"],
                ],
                "admin_settings" => [
                    ["admin_settings", "Configurações", "settings"],
                ],
                default => [],
            };
        }
        $role = (string) ($c["role"] ?? "");
        $cashLabel = $role === "recepcionista" ? "Caixa" : "Painel";
        $cashIcon =
            $role === "recepcionista"
                ? (function_exists("reception_cash_state_icon")
                    ? reception_cash_state_icon($c)
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
            function_exists("has_effective_role") &&
            has_effective_role($c, "gerente")
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

    public static function operation_menu_html(
        string $label,
        string $iconName,
        array $items,
        string $current,
    ): string 
    {
    
        $links = "";
        $active = false;
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item) || count($item) < 3) {
                continue;
            }
            $route = (string) $item[0];
            $params = (array) ($item[3] ?? []);
            $key = $route . ":" . json_encode($params);
            if ($route === "" || isset($seen[$key])) {
                continue;
            }
            if (function_exists("can") && !can($route)) {
                continue;
            }
            $seen[$key] = 1;
            if (operation_current_match($route, $params, $current)) {
                $active = true;
            }
            $links .= operation_link_html(
                $route,
                (string) $item[1],
                (string) $item[2],
                $current,
                $params,
            );
        }
        if ($links === "") {
            return "";
        }
        $cls = "operation-menu" . ($active ? " is-active" : "");
        return '<div class="' .
            $cls .
            '"><button type="button" class="operation-chip operation-menu-trigger" aria-haspopup="true" aria-expanded="false">' .
            icon($iconName) .
            "<span>" .
            e($label) .
            "</span>" .
            icon("expand_more") .
            '</button><div class="operation-menu-panel" role="menu">' .
            $links .
            "</div></div>";
    
    }

    public static function page_operations_html(string $current, array $c): string
    
    {
    
        $specs = page_operation_specs($current, $c);
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
            if ($route === "__menu") {
                $html .= operation_menu_html(
                    (string) $op[1],
                    (string) $op[2],
                    (array) ($op[3]["items"] ?? []),
                    $current,
                );
                continue;
            }
            if (
                $route === "" ||
                isset($seen[$route . ":" . json_encode($op[3] ?? [])])
            ) {
                continue;
            }
            if (function_exists("can") && !can($route)) {
                continue;
            }
            $seen[$route . ":" . json_encode($op[3] ?? [])] = 1;
            $html .= operation_link_html(
                $route,
                (string) $op[1],
                (string) $op[2],
                $current,
                (array) ($op[3] ?? []),
            );
        }
        return $html !== ""
            ? '<nav class="pagehead-operations" aria-label="Operações">' .
                    $html .
                    "</nav>"
            : "";
    
    }

    public static function page_head_icon_name(string $title = ""): string
    
    {
    
        $current = route();
        $params = $_GET;
        if ($current === "admin_painel") {
            return "network_ping";
        }
        if (function_exists("prontoo_icon_for_route_label")) {
            if (
                str_starts_with($current, "admin_") &&
                function_exists("admin_nav_parent")
            ) {
                $parent = admin_nav_parent($current);
                if ($parent !== $current) {
                    return prontoo_icon_for_route_label(
                        $parent,
                        $title,
                        (array) $params,
                        prontoo_icon_for_route_label(
                            $current,
                            $title,
                            (array) $params,
                            "monitoring",
                        ),
                    );
                }
            }
            $ico = prontoo_icon_for_route_label(
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
            $parent = admin_nav_parent($current);
            if (isset(PRONTOO_ADMIN_ACTIONS[$parent]["icon"])) {
                return (string) PRONTOO_ADMIN_ACTIONS[$parent]["icon"];
            }
        }
        $c = ctx();
        if ($current === "painel" && ($c["scope"] ?? "") === "clinic") {
            return role_icon(
                (string) ($c["role"] ?? ""),
                (int) ($c["clinic_id"] ?? 0),
            );
        }
        if (
            $current === "financial" &&
            ($c["scope"] ?? "") === "clinic" &&
            (string) ($c["role"] ?? "") === "recepcionista"
        ) {
            return reception_cash_state_icon($c);
        }
        $actions = actions();
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
    
        $ico = page_head_icon_name($title);
        $c = ctx();
        if (($c["scope"] ?? "") === "global") {
            $action = "";
        }
        $ops =
            $c && function_exists("page_operations_html")
                ? page_operations_html(route(), $c)
                : "";
        $hasOps = $ops !== "";
        $hasAction = $action !== "";
        return '<section class="pagehead' .
            ($hasOps ? " has-operations" : "") .
            ($hasAction ? " has-actions" : "") .
            '" aria-label="Operações da tela"><div class="pagehead-copy"><h1><span class="pagehead-icon">' .
            icon($ico) .
            "</span><span>" .
            e($title) .
            "</span></h1></div>" .
            $ops .
            ($hasAction
                ? '<div class="pagehead-actions" aria-label="Operações rápidas">' .
                    $action .
                    "</div>"
                : "") .
            "</section>";
    
    }

    public static function action_icon_for(string $label): string
    
    {
    
        $l = mb_strtolower($label);
        if (function_exists("prontoo_icon_for_route_label")) {
            $byLabel = prontoo_icon_for_route_label("", $label, [], "");
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
    
        return icon($iconName !== "" ? $iconName : action_icon_for($label)) .
            "<span>" .
            e($label) .
            "</span>";
    
    }
}
