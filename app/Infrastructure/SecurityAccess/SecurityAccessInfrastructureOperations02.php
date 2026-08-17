<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SecurityAccess;

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

final class SecurityAccessInfrastructureOperations02
{
    private function __construct()
    {
    }

    public static function scope_guard_context_selftest(): array
    
    {
    
        $hadExpected = array_key_exists(
            "PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID",
            $GLOBALS,
        );
        $previousExpected =
            $GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] ?? null;
        $hadSystem = array_key_exists("PRONTOO_SCOPE_GUARD_SYSTEM", $GLOBALS);
        $previousSystem = $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] ?? null;
        unset(
            $GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"],
            $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"],
        );
        $cases = [];
        try {
            $cases["explicit_clinic_is_active"] = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_clinic(
                17,
                static  fn(): bool => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::scope_guard_expected_clinic_id() === 17,
            );
            $cases["same_clinic_can_nest"] = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_clinic(
                17,
                static  fn(): bool => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_clinic(
                    17,
                    static  fn(): bool => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::scope_guard_expected_clinic_id() === 17,
                ),
            );
            $clinicSwitchBlocked = false;
            try {
                \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_clinic(
                    17,
                    static  fn() => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_clinic(18, static  fn() => true),
                );
            } catch (LogicException $expected) {
                $clinicSwitchBlocked = true;
            }
            $cases["clinic_switch_is_blocked"] = $clinicSwitchBlocked;
            $systemInsideClinicBlocked = false;
            try {
                \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_clinic(
                    17,
                    static  fn() => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_disabled(static  fn() => true),
                );
            } catch (LogicException $expected) {
                $systemInsideClinicBlocked = true;
            }
            $cases["system_inside_clinic_is_blocked"] =
                $systemInsideClinicBlocked;
            $clinicInsideSystemBlocked = false;
            try {
                \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_disabled(
                    static  fn() => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_clinic(17, static  fn() => true),
                );
            } catch (LogicException $expected) {
                $clinicInsideSystemBlocked = true;
            }
            $cases["clinic_inside_system_is_blocked"] =
                $clinicInsideSystemBlocked;
            $cases["context_is_restored"] =
                !array_key_exists(
                    "PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID",
                    $GLOBALS,
                ) && !array_key_exists("PRONTOO_SCOPE_GUARD_SYSTEM", $GLOBALS);
        } finally {
            if ($hadExpected) {
                $GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] =
                    $previousExpected;
            } else {
                unset($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"]);
            }
            if ($hadSystem) {
                $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] = $previousSystem;
            } else {
                unset($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"]);
            }
        }
        $failed = array_keys(array_filter($cases, static  fn($ok) => !$ok));
        return [
            "ok" => $failed === [],
            "passed" => count($cases) - count($failed),
            "total" => count($cases),
            "failed" => $failed,
        ];
    
    }

    public static function sql_table_hit(string $norm, string $table): bool
    
    {
    
        $tl = strtolower($table);
        return (bool) preg_match("/\b" . preg_quote($tl, "/") . "\b/", $norm) ||
            strpos($norm, "`" . $tl . "`") !== false;
    
    }

    public static function prontoo_icon_matrix(): array
    
    {
    
        return [
            "context" => [
                "painel" => "space_dashboard",
                "admin_painel" => "space_dashboard",
                "operations" => "account_tree",
                "admin_operations" => "account_tree",
                "maestro" => "event_repeat",
                "leads" => "person_search",
                "patients" => "patient_list",
                "creditors" => "receipt_long",
                "people" => "groups",
                "appointments" => "calendar_month",
                "financial" => "payments",
                "cash" => "point_of_sale",
                "procedures" => "medical_services",
                "documents" => "description",
                "tasks" => "task_alt",
                "notices" => "campaign",
                "admin_alerts" => "campaign",
                "users" => "groups",
                "permissions" => "admin_panel_settings",
                "audit" => "history",
                "settings" => "home_health",
                "clinic" => "home_health",
                "admin_clinics" => "home_health",
                "admin_maintenance" => "construction",
                "admin_settings" => "settings",
            ],
            "operation" => [
                "summary" => "space_dashboard",
                "daily" => "today",
                "weekly" => "view_week",
                "monthly" => "calendar_month",
                "received" => "inbox",
                "sent" => "outbox",
                "new_notice" => "add_comment",
                "new_patient" => "person_add",
                "new_lead" => "person_search",
                "schedule" => "event_available",
                "block" => "event_busy",
                "save" => "save",
                "edit" => "edit",
                "delete" => "delete",
                "close" => "close",
                "confirm" => "check_circle",
                "print" => "print",
                "view" => "visibility",
                "search" => "search",
                "archive" => "archive",
            ],
            "label" => [
                "painel" => "space_dashboard",
                "operação" => "account_tree",
                "fluxo" => "account_tree",
                "maestro" => "event_repeat",
                "interessados" => "person_search",
                "pacientes" => "patient_list",
                "credores" => "receipt_long",
                "pessoas" => "groups",
                "agenda" => "calendar_month",
                "diário" => "today",
                "diario" => "today",
                "semanal" => "view_week",
                "mensal" => "calendar_month",
                "financeiro" => "payments",
                "caixa" => "point_of_sale",
                "procedimentos" => "medical_services",
                "documentos" => "description",
                "tarefas" => "task_alt",
                "avisos" => "campaign",
                "aviso" => "campaign",
                "recebidos" => "inbox",
                "enviados" => "outbox",
                "novo aviso" => "add_comment",
                "colaboradores" => "groups",
                "permissões" => "admin_panel_settings",
                "permissoes" => "admin_panel_settings",
                "atividades" => "history",
                "auditoria" => "history",
                "consultório" => "home_health",
                "consultorio" => "home_health",
                "dados" => "home_health",
                "departamentos" => "corporate_fare",
                "aparência" => "palette",
                "aparencia" => "palette",
                "assinatura" => "credit_card",
                "configurações" => "settings",
                "configuracoes" => "settings",
                "consultórios" => "home_health",
                "consultorios" => "home_health",
                "incidentes" => "crisis_alert",
                "erros" => "bug_report",
                "diagnóstico" => "troubleshoot",
                "diagnostico" => "troubleshoot",
                "integridade" => "verified_user",
                "segurança" => "security",
                "seguranca" => "security",
                "manutenção" => "construction",
                "manutencao" => "construction",
                "excluídos" => "restore_from_trash",
                "excluidos" => "restore_from_trash",
                "onboarding" => "assignment_turned_in",
            ],
        ];
    
    }

    public static function prontoo_icon_for(string $key, string $fallback = "monitoring"): string
    
    {
    
        $key = mb_strtolower(trim($key));
        if ($key === "") {
            return $fallback;
        }
        $m = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_matrix();
        foreach (["context", "operation", "label"] as $group) {
            if (isset($m[$group][$key])) {
                return (string) $m[$group][$key];
            }
        }
        return $fallback;
    
    }

    public static function prontoo_icon_for_route_label(
        string $route,
        string $label = "",
        array $params = [],
        string $fallback = "monitoring",
    ): string 
    {
    
        $route = trim($route);
        $labelKey = mb_strtolower(trim($label));
        if ($route === "financial" && $labelKey === "caixa") {
            return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("cash", $fallback);
        }
        if ($route === "patients" && $labelKey === "pessoas") {
            return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("people", $fallback);
        }
        if ($route === "appointments") {
            $view = (string) ($params["view"] ?? "");
            if ($view === "resumo") {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("summary", $fallback);
            }
            if ($view === "diario") {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("daily", $fallback);
            }
            if ($view === "semanal") {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("weekly", $fallback);
            }
            if ($view === "mensal") {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("monthly", $fallback);
            }
        }
        if ($route === "settings") {
            $tab = (string) ($params["tab"] ?? "");
            if ($tab === "visual") {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("aparência", $fallback);
            }
            if ($tab === "assinatura") {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("assinatura", $fallback);
            }
            if ($tab === "setores") {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("departamentos", $fallback);
            }
            if ($tab === "perfil") {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("dados", $fallback);
            }
        }
        if ($route === "admin_alerts") {
            if (!empty($params["compose"])) {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("new_notice", $fallback);
            }
            $view = (string) ($params["view"] ?? "");
            if ($view === "sent") {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("sent", $fallback);
            }
            if ($view === "received") {
                return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("received", $fallback);
            }
        }
        if ($labelKey !== "" && ($ico = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for($labelKey, "")) !== "") {
            return $ico;
        }
        if ($route !== "" && ($ico = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for($route, "")) !== "") {
            return $ico;
        }
        return $fallback;
    
    }

    public static function actions(): array
    
    {
    
        return [
            "painel" => [
                "label" => "Painel",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("painel"),
                "roles" => ["recepcionista", "assistente", "medico", "gerente"],
            ],
            "operations" => [
                "label" => "Fluxo",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("operations"),
                "roles" => ["gerente"],
            ],
            "maestro" => [
                "label" => "Rotinas",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("maestro"),
                "roles" => ["gerente"],
            ],
            "leads" => [
                "label" => "Interessados",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("leads"),
                "roles" => ["recepcionista", "gerente"],
            ],
            "patients" => [
                "label" => "Pacientes",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("patients"),
                "roles" => ["recepcionista", "assistente", "medico", "gerente"],
            ],
            "creditors" => [
                "label" => "Credores",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("creditors"),
                "roles" => ["gerente"],
            ],
            "appointments" => [
                "label" => "Agenda",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("appointments"),
                "roles" => ["recepcionista", "assistente", "medico", "gerente"],
            ],
            "financial" => [
                "label" => "Financeiro",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("financial"),
                "roles" => ["recepcionista", "gerente"],
            ],
            "procedures" => [
                "label" => "Procedimentos",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("procedures"),
                "roles" => ["gerente"],
            ],
            "documents" => [
                "label" => "Documentos",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("documents"),
                "roles" => ["recepcionista", "assistente", "medico", "gerente"],
            ],
            "tasks" => [
                "label" => "Tarefas",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("tasks"),
                "roles" => ["recepcionista", "assistente", "medico", "gerente"],
            ],
            "notices" => [
                "label" => "Avisos",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("notices"),
                "roles" => ["recepcionista", "assistente", "medico", "gerente"],
            ],
            "users" => [
                "label" => "Colaboradores",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("users"),
                "roles" => ["gerente"],
            ],
            "permissions" => [
                "label" => "Permissões",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("permissions"),
                "roles" => ["gerente"],
            ],
            "audit" => [
                "label" => "Atividades",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("audit"),
                "roles" => ["recepcionista", "assistente", "medico", "gerente"],
            ],
            "settings" => [
                "label" => "Consultório",
                "icon" => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for("settings"),
                "roles" => ["medico", "gerente"],
            ],
        ];
    
    }

    public static function role_rank(string $role): int
    
    {
    
        $rank = [
            "recepcionista" => 10,
            "assistente" => 20,
            "medico" => 30,
            "gerente" => 40,
        ];
        return $rank[$role] ?? 0;
    
    }

    public static function primary_role_from_codes(array $roles): string
    
    {
    
        $roles = array_values(array_unique(array_map("strval", $roles)));
        usort($roles, static  fn($a, $b) => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::role_rank($b) <=> \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::role_rank($a));
        return $roles[0] ?? "";
    
    }

    public static function has_effective_role(array $c, string $role): bool
    
    {
    
        return in_array(
            $role,
            array_map(
                "strval",
                (array) ($c["effective_roles"] ?? [$c["role"] ?? ""]),
            ),
            true,
        );
    
    }

    public static function default_permissions(): array
    
    {
    
        return [
            "recepcionista" => [
                "painel",
                "leads",
                "appointments",
                "patients",
                "financial",
                "tasks",
                "documents",
                "notices",
                "audit",
            ],
            "assistente" => [
                "painel",
                "appointments",
                "patients",
                "tasks",
                "documents",
                "notices",
                "audit",
            ],
            "medico" => [
                "painel",
                "appointments",
                "patients",
                "tasks",
                "documents",
                "notices",
                "audit",
            ],
            "gerente" => array_keys(\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::actions()),
        ];
    
    }
}
