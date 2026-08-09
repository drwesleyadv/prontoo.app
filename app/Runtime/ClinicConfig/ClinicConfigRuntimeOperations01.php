<?php
declare(strict_types=1);

namespace Prontoo\Runtime\ClinicConfig;

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

final class ClinicConfigRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function clinic_visual(?int $clinicId = null, ?array $ctx = null): array
    
    {
    
        $ctx = $ctx ?: [];
        if ($ctx && ($ctx["scope"] ?? "") === "clinic") {
            return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_visual_from_values(
                $ctx["clinic_icon"] ?? null,
                $ctx["accent_color"] ?? null,
                $ctx["responsible_profession"] ?? null,
            );
        }
        if ($clinicId && $clinicId > 0 && \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            $loader = function () use ($clinicId): array {
    
                try {
                    $row = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                        "SELECT clinic_icon,accent_color,responsible_profession FROM pi_clinics WHERE id=?",
                        [$clinicId],
                    );
                    if ($row) {
                        return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_visual_from_values(
                            $row["clinic_icon"] ?? null,
                            $row["accent_color"] ?? null,
                            $row["responsible_profession"] ?? null,
                        );
                    }
                } catch (Throwable $e) {
                    if (
                        !\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_schema_error_is_missing_table($e) &&
                        stripos($e->getMessage(), "Unknown column") === false
                    ) {
                        error_log("[Prontoo clinic visual] " . $e->getMessage());
                    }
                }
                return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_visual_from_values();
            };
            if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
                return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                    "clinic",
                    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("visual", [$clinicId]),
                    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("clinic"),
                    $loader,
                    ["clinic:" . $clinicId],
                );
            }
            return $loader();
        }
        return \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::clinic_visual_from_values();
    
    }

    public static function clinic_role_label_fields(int $cid): string
    
    {
    
        \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::seed_clinic_roles($cid);
        $labels = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_roles($cid, false);
        $defs = [
            "recepcionista" => "Recepção",
            "assistente" => "Assistente",
            "medico" => "Profissional",
            "gerente" => "Administrativo",
        ];
        $old = [
            "recepcionista" => "Atendimento",
            "assistente" => "Triagem",
            "medico" => "Médica",
            "gerente" => "Gestão",
        ];
        $html = '<div class="two">';
        foreach ($defs as $role => $label) {
            $value = mb_trim((string) ($labels[$role] ?? $label));
            if (isset($old[$role]) && $value === $old[$role]) {
                $value = $label;
            }
            $html .= \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                $label,
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "role_label[" . $role . "]",
                    "text",
                    $value,
                    'maxlength="80" required',
                ),
            );
        }
        return $html . "</div>";
    
    }

    public static function clinic_role_sector_fields(int $cid): string
    
    {
    
        \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::seed_clinic_roles($cid);
        $labels = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_roles($cid, false);
        $icons = [];
        try {
            $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "SELECT role_code,icon_name FROM pi_clinic_roles WHERE clinic_id=?",
                [$cid],
            )->fetchAll();
            foreach ($rows as $r) {
                $icons[(string) $r["role_code"]] = (string) ($r["icon_name"] ?? "");
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " . __FUNCTION__ . "] " . $e->getMessage(),
            );
        }
        $defs = [
            "recepcionista" => "Recepção",
            "assistente" => "Assistente",
            "medico" => "Profissional",
            "gerente" => "Administrativo",
        ];
        $descriptions = [
            "recepcionista" => "Entrada, acolhimento, cadastro e agenda.",
            "assistente" => "Apoio, preparo, conferência e documentos.",
            "medico" => "Atendimento profissional e registros clínicos.",
            "gerente" => "Gestão, permissões, equipe e financeiro.",
        ];
        $html =
            '<div class="sector-editor sector-editor-modern sector-editor-compact">';
        foreach ($defs as $role => $fallback) {
            $opts = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::role_icon_options($role);
            $label = mb_trim((string) ($labels[$role] ?? $fallback));
            if ($label === "") {
                $label = $fallback;
            }
            $ico = mb_trim((string) ($icons[$role] ?? \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::default_role_icon($role)));
            if ($ico === "" || !isset($opts[$ico])) {
                $ico = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::default_role_icon($role);
            }
            if (!isset($opts[$ico])) {
                $ico = array_key_first($opts) ?: "groups";
            }
            $description = $descriptions[$role] ?? "Departamento do consultório.";
            $html .=
                '<section class="sector-card sector-card-' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($role) .
                '"><div class="sector-card-top"><span class="sector-card-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ico) .
                '</span><div class="sector-card-copy"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($fallback) .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($description) .
                '</small></div><span class="sector-card-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("visibility") .
                '<span>Visível</span></span></div><div class="sector-card-fields">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Renomear",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "role_label[" . $role . "]",
                        "text",
                        $label,
                        'maxlength="80" required',
                    ),
                ) .
                '<div class="sector-icon-field"><div class="sector-icon-field-head"><span>Ícone do departamento</span><small>Escolha apenas pelo símbolo.</small></div>' .
                \Prontoo\Presentation\ClinicConfig\ClinicConfigPresentationOperations01::role_icon_picker("role_icon[" . $role . "]", $ico, $role) .
                "</div></div></section>";
        }
        return $html . "</div>";
    
    }

    public static function clinic_profession(int $clinicId): string
    
    {
    
        if ($clinicId <= 0) {
            return "Profissional";
        }
        $loader = function () use ($clinicId): string {
    
            try {
                $value = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
                    "SELECT responsible_profession FROM pi_clinics WHERE id=?",
                    [$clinicId],
                );
                if ($value !== null && mb_trim((string) $value) !== "") {
                    return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations01::normalize_profession((string) $value);
                }
            } catch (Throwable $e) {
                if (
                    !\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations02::db_schema_error_is_missing_table($e) &&
                    stripos($e->getMessage(), "Unknown column") === false
                ) {
                    error_log("[Prontoo profession] " . $e->getMessage());
                }
            }
            return "Profissional";
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return (string) \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "clinic",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("profession", [$clinicId]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("clinic"),
                $loader,
                ["clinic:" . $clinicId],
            );
        }
        return $loader();
    
    }

    public static function clinic_is_global_admin_owned(int $clinicId): bool
    
    {
    
        if ($clinicId <= 0) {
            return false;
        }
        $loader = function () use ($clinicId): bool {
    
            try {
                return (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::safe_val(
                    "SELECT COUNT(*) FROM pi_clinics c LEFT JOIN pi_users owner_user ON owner_user.id=c.owner_user_id LEFT JOIN pi_users manager_user ON manager_user.id=c.manager_user_id WHERE c.id=? AND (COALESCE(owner_user.is_global_admin,0)=1 OR COALESCE(manager_user.is_global_admin,0)=1)",
                    [$clinicId],
                    0,
                ) > 0;
            } catch (Throwable $e) {
                error_log("[Prontoo global admin clinic check] " . $e->getMessage());
                return false;
            }
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return (bool) \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "clinic",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("global_admin_owned", [$clinicId]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("clinic"),
                $loader,
                ["clinic:" . $clinicId],
            );
        }
        return $loader();
    
    }

    public static function open_incidents_count(): int
    
    {
    
        try {
            return (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val(
                "kpi_errors_open",
                60,
                "SELECT COUNT(*) FROM pi_error_events WHERE resolved_at IS NULL",
            );
        } catch (Throwable $e) {
            return 0;
        }
    
    }

    public static function clinic_metric_inc(?int $clinicId, string $metric, int $by = 1): void
    
    {
    
        if (!$clinicId) {
            return;
        }
        try {
            \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "INSERT INTO pi_clinic_daily_stats (clinic_id,day_date,metric_key,metric_value,updated_at) VALUES (?,CURDATE(),?,?,NOW()) ON DUPLICATE KEY UPDATE metric_value=metric_value+VALUES(metric_value), updated_at=NOW()",
                [$clinicId, \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::counter_key($metric), $by],
            );
        } catch (Throwable $e) {
            error_log("[Prontoo clinic_metric_inc] " . $e->getMessage());
        }
    
    }

    public static function clinic_read_only_db(int $cid): bool
    
    {
    
        static $cache = [];
        if ($cid <= 0) {
            return false;
        }
        if (array_key_exists($cid, $cache)) {
            return (bool) $cache[$cid];
        }
        try {
            $row = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                "SELECT subscription_status,paid_until,trial_ends_at FROM pi_clinics WHERE id=? LIMIT 1",
                [$cid],
            );
            if (!$row) {
                return $cache[$cid] = false;
            }
            $status = (string) ($row["subscription_status"] ?? "trial");
            if ($status === "exempt") {
                return $cache[$cid] = false;
            }
            if ($status === "read_only") {
                return $cache[$cid] = true;
            }
            if (
                $status === "active" &&
                mb_trim((string) ($row["paid_until"] ?? "")) === ""
            ) {
                return $cache[$cid] = false;
            }
            $paidActive = is_callable([\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::class, 'subscription_paid_is_active'])
                ? \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_paid_is_active($row["paid_until"] ?? null, $cid)
                : \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_date_only_end_timestamp($row["paid_until"] ?? null, $cid) >= time();
            $trialActive =
                $status === "trial" &&
                (is_callable([\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::class, 'subscription_trial_is_active'])
                    ? \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_trial_is_active($row["trial_ends_at"] ?? null)
                    : \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($row["trial_ends_at"] ?? null, true) >=
                        time());
            return $cache[$cid] = !($paidActive || $trialActive);
        } catch (Throwable $e) {
            error_log("[Prontoo read-only db guard] " . $e->getMessage());
            return false;
        }
    
    }

    public static function clinic_context_required(?array $ctx = null): array
    
    {
    
        $ctx = $ctx ?: \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
        if (
            !$ctx ||
            ($ctx["scope"] ?? "") !== "clinic" ||
            (int) ($ctx["clinic_id"] ?? 0) <= 0
        ) {
            throw new ProntooHttpError(
                403,
                "Operação disponível apenas no contexto de um consultório.",
            );
        }
        return $ctx;
    
    }

    public static function clinic_id_required(?array $ctx = null): int
    
    {
    
        $ctx = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_context_required($ctx);
        return (int) $ctx["clinic_id"];
    
    }

    public static function seed_clinic_roles(int $clinicId): void
    
    {
    
        \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_read_only_guard_disabled(static function () use ($clinicId): void {
    
            $position = 1;
            foreach (PRONTOO_ROLES as $code => $label) {
                $enabled = in_array($code, ["medico", "gerente"], true) ? 1 : 0;
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "INSERT INTO pi_clinic_roles (clinic_id,role_code,label,icon_name,enabled,sort_order) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE icon_name=IF(icon_name='',VALUES(icon_name),icon_name), sort_order=IF(sort_order IS NULL OR sort_order=0,VALUES(sort_order),sort_order)",
                    [
                        $clinicId,
                        $code,
                        $label,
                        \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::default_role_icon($code),
                        $enabled,
                        $position,
                    ],
                );
                $position++;
            }
        });
    
    }

    public static function clinic_roles(int $clinicId, bool $enabledOnly = false): array
    
    {
    
        if ($clinicId <= 0) {
            return $enabledOnly ? [] : PRONTOO_ROLES;
        }
        $loader = function () use ($clinicId, $enabledOnly): array {
    
            $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "SELECT role_code,label,enabled FROM pi_clinic_roles WHERE clinic_id=? ORDER BY sort_order, role_code",
                [$clinicId],
            )->fetchAll();
            if (!$rows) {
                \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::seed_clinic_roles($clinicId);
                $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "SELECT role_code,label,enabled FROM pi_clinic_roles WHERE clinic_id=? ORDER BY sort_order, role_code",
                    [$clinicId],
                )->fetchAll();
            }
            $out = [];
            foreach ($rows as $r) {
                if (!$enabledOnly || (int) $r["enabled"]) {
                    $out[(string) $r["role_code"]] = (string) $r["label"];
                }
            }
            foreach (PRONTOO_ROLES as $role => $label) {
                if (!isset($out[$role]) && !$enabledOnly) {
                    $out[$role] = $label;
                }
            }
            return $out;
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "clinic",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("roles", [$clinicId, $enabledOnly]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("clinic"),
                $loader,
                ["clinic:" . $clinicId, "table:pi_clinic_roles"],
            );
        }
        return $loader();
    
    }

    public static function clinic_role_icons(int $clinicId, bool $enabledOnly = false): array
    
    {
    
        $fallback = [];
        foreach (array_keys(PRONTOO_ROLES) as $role) {
            $fallback[$role] = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::default_role_icon($role);
        }
        if ($clinicId <= 0) {
            return $enabledOnly ? [] : $fallback;
        }
        $loader = function () use ($clinicId, $enabledOnly, $fallback): array {
    
            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::seed_clinic_roles($clinicId);
            try {
                $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "SELECT role_code,icon_name,enabled FROM pi_clinic_roles WHERE clinic_id=? ORDER BY sort_order, role_code",
                    [$clinicId],
                )->fetchAll();
            } catch (Throwable $e) {
                error_log("[Prontoo clinic role icons] " . $e->getMessage());
                return $enabledOnly ? [] : $fallback;
            }
            $out = [];
            foreach ($rows as $r) {
                $role = (string) ($r["role_code"] ?? "");
                if ($role === "" || ($enabledOnly && !(int) ($r["enabled"] ?? 0))) {
                    continue;
                }
                $opts = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::role_icon_options($role);
                $iconName = mb_trim((string) ($r["icon_name"] ?? ""));
                if ($iconName === "" || !isset($opts[$iconName])) {
                    $iconName = \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::default_role_icon($role);
                }
                $out[$role] = $iconName;
            }
            foreach ($fallback as $role => $iconName) {
                if (!isset($out[$role]) && !$enabledOnly) {
                    $out[$role] = $iconName;
                }
            }
            return $out;
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "clinic",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("role_icons", [$clinicId, $enabledOnly]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("clinic"),
                $loader,
                ["clinic:" . $clinicId, "table:pi_clinic_roles"],
            );
        }
        return $loader();
    
    }

    public static function clinic_role_options(int $clinicId, bool $enabledOnly = true): array
    
    {
    
        return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_roles($clinicId, $enabledOnly);
    
    }

    public static function role_label_for(string $role, ?int $clinicId = null): string
    
    {
    
        if ($clinicId && $clinicId > 0) {
            $roles = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_roles($clinicId, false);
            if (isset($roles[$role])) {
                return (string) $roles[$role];
            }
        }
        return PRONTOO_ROLES[$role] ?? $role;
    
    }

    public static function role_icon_for(string $role, ?int $clinicId = null): string
    
    {
    
        if ($clinicId && $clinicId > 0) {
            $icons = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_icons($clinicId, false);
            if (isset($icons[$role])) {
                return $icons[$role];
            }
        }
        return \Prontoo\Domain\ClinicConfig\ClinicConfigDomainOperations02::default_role_icon($role);
    
    }

    public static function is_responsible_doctor(array $c): bool
    
    {
    
        if (($c["scope"] ?? "") !== "clinic") {
            return false;
        }
        return (bool) \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
            "SELECT id FROM pi_clinics WHERE id=? AND owner_user_id=?",
            [(int) $c["clinic_id"], (int) $c["user"]["id"]],
        );
    
    }

    public static function onboarding_pending(?array $c = null): bool
    
    {
    
        $c ??= \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
        if (!$c || ($c["scope"] ?? "") !== "clinic" || !\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::is_responsible_doctor($c)) {
            return false;
        }
        return (int) \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val("SELECT onboarding_done FROM pi_clinics WHERE id=?", [
            (int) $c["clinic_id"],
        ]) !== 1;
    
    }

    public static function role_icon(string $r, ?int $clinicId = null): string
    
    {
    
        return \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_icon_for($r, $clinicId) ?: "badge";
    
    }
}
