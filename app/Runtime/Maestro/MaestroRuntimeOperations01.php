<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Maestro;

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

final class MaestroRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function maestro_runtime_access_marker_ready(): bool
    
    {
    
        if (array_key_exists("PRONTOO_MAESTRO_RUNTIME_ACCESS_READY", $GLOBALS)) {
            return (bool) $GLOBALS["PRONTOO_MAESTRO_RUNTIME_ACCESS_READY"];
        }
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return false;
        }
        try {
            $ready =
                (string) (\Prontoo\Runtime\Operational\OperationalComposition::maestro()->scalar('operational.maestro.01.maestro_runtime_access_marker_ready.01', [], []) ?? "") === "1";
        } catch (Throwable $e) {
            $ready = false;
        }
        $GLOBALS["PRONTOO_MAESTRO_RUNTIME_ACCESS_READY"] = $ready;
        return $ready;
    
    }

    public static function maestro_ensure_schema(): void
    
    {
    
        static $validated = false;
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        if (!$validated) {
            \Prontoo\Infrastructure\Maestro\MaestroInfrastructureOperations01::maestro_global_physical_rollback();
            foreach (["success", "errors_count", "deferred_count"] as $column) {
                if (!\Prontoo\Runtime\Operational\OperationalComposition::administration()->columnExists("pi_maestro_job_runs", $column)) {
                    throw new RuntimeException(
                        "Schema incompleto: pi_maestro_job_runs.{$column} ausente.",
                    );
                }
            }
            if (!\Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_runtime_access_marker_ready()) {
                \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_grant_runtime_access();
            }
            $validated = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_runtime_access_marker_ready();
        }
    
    }

    public static function maestro_grant_runtime_access(): void
    
    {
    
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg() || \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_runtime_access_marker_ready()) {
            return;
        }
        try {
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_disabled(static function (): void {
    
                \Prontoo\Runtime\Operational\OperationalComposition::maestroCommands()
                    ->grantRuntimeAccess();
            });
            $GLOBALS["PRONTOO_MAESTRO_RUNTIME_ACCESS_READY"] = true;
        } catch (Throwable $e) {
            error_log("[Prontoo maestro grant] " . $e->getMessage());
        }
    
    }

    public static function maestro_runtime_upgrade(): void
    
    {
    
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        try {
            \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_ensure_schema();
        } catch (Throwable $e) {
            error_log("[Prontoo maestro upgrade] " . $e->getMessage());
        }
    
    }

    public static function maestro_local_day(int $clinicId, int $offsetDays = 0): string
    
    {
        $offsetDays = max(-365, min(365, $offsetDays));
        $modifier = ($offsetDays >= 0 ? "+" : "") . $offsetDays . " days";
        return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_now_in_timezone($clinicId)
            ->setTime(0, 0, 0)
            ->modify($modifier)
            ->format("Y-m-d");
    
    }

    public static function maestro_local_day_utc_range(int $clinicId, string $day): array
    
    {
        [$start, $end] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($day, $clinicId);
        return [
            gmdate("Y-m-d H:i:s", (int) $start),
            gmdate("Y-m-d H:i:s", (int) $end),
        ];
    
    }

    public static function maestro_due_dt(int $offsetDays = 0, int $clinicId = 0): ?string
    
    {
        $offsetDays = max(0, min(365, $offsetDays));
        if ($clinicId <= 0) {
            return gmdate("Y-m-d 17:00:00", strtotime("+" . $offsetDays . " days UTC"));
        }
        $local = new DateTimeImmutable(
            \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($clinicId, $offsetDays) . " 17:00:00",
            new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone(null, $clinicId)),
        );
        return $local
            ->setTimezone(new DateTimeZone("UTC"))
            ->format("Y-m-d H:i:s");
    
    }

    public static function maestro_save_rule(array $c): void
    
    {
    
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        $catalog = \Prontoo\Domain\Maestro\MaestroDomainOperations01::maestro_trigger_catalog();
        $actions = \Prontoo\Domain\Maestro\MaestroDomainOperations01::maestro_action_types();
        $id = max(0, (int) ($_POST["id"] ?? 0));
        $trigger = (string) ($_POST["trigger_event"] ?? "");
        if (!isset($catalog[$trigger])) {
            throw new RuntimeException("Condição inválida.");
        }
        $item = $catalog[$trigger];
        $unit = (string) ($item["unit"] ?? "days");
        $defaultAmount =
            (int) ($item["amount_default"] ?? ($item["days_default"] ?? 1));
        $action =
            (string) ($_POST["action_type"] ??
                ($item["default_action"] ?? "create_task"));
        if (!isset($actions[$action])) {
            throw new RuntimeException("Ação da rotina inválida.");
        }
        $name = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_text((string) ($_POST["name"] ?? ""), 160);
        if ($name === "") {
            $name = (string) ($item["default_name"] ?? $item["label"]);
        }
        $amount = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_amount(
            $_POST["trigger_amount"] ?? ($_POST["trigger_days"] ?? $defaultAmount),
            $defaultAmount,
            $unit,
        );
        $priority = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_priority(
            $_POST["priority"] ?? ($item["default_priority"] ?? 50),
        );
        $minInterval = max(
            PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES,
            min(
                1440,
                (int) ($_POST["min_interval_minutes"] ??
                    PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES),
            ),
        );
        $targetScope = (string) ($_POST["target_scope"] ?? "role");
        if (!in_array($targetScope, ["clinic", "role", "user"], true)) {
            throw new RuntimeException("Escopo de destinatário inválido.");
        }
        $targetRole =
            (string) ($_POST["target_role"] ??
                ($item["default_target_role"] ?? "recepcionista"));
        $roleOpts = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_options($cid, true);
        if ($targetScope === "role" && !isset($roleOpts[$targetRole])) {
            throw new RuntimeException("Cargo destinatário inválido para o consultório.");
        }
        $targetUserId = max(0, (int) ($_POST["target_user_id"] ?? 0));
        if ($targetScope === "user" && !\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_user_exists($cid, $targetUserId)) {
            throw new RuntimeException("Pessoa destinatária inválida para o consultório.");
        }
        if ($targetScope !== "role") {
            $targetRole = null;
        }
        if ($targetScope !== "user") {
            $targetUserId = null;
        }
        $title = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_text((string) ($_POST["task_title"] ?? ""), 180);
        if ($title === "") {
            $title =
                (string) ($item["default_title"] ??
                    "Ação da rotina para {{origem}}");
        }
        $description = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_template(
            (string) ($_POST["task_description"] ?? ""),
            1200,
        );
        if ($description === "") {
            $description =
                (string) ($item["default_description"] ??
                    "Rotina criada para acompanhamento de {{origem}}.");
        }
        $cond = [
            "amount" => $amount,
            "days" => $amount,
            "unit" => $unit,
            "status" => \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_text((string) ($_POST["trigger_status"] ?? ""), 60),
        ];
        $act = [
            "target_scope" => $targetScope,
            "target_role" => $targetRole,
            "target_user_id" => $targetUserId,
            "title" => $title,
            "description" => $description,
            "due_offset_days" => \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_days($_POST["due_offset_days"] ?? 0, 0),
        ];
        $active = empty($_POST["active"]) ? 0 : 1;
        $module = (string) $item["module"];
        if ($id > 0) {
            \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.01.maestro_save_rule.01', [
                    $name,
                    $active,
                    $module,
                    $trigger,
                    \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_json($cond),
                    $action,
                    \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_json($act),
                    $priority,
                    $minInterval,
                    $uid,
                    $id,
                    $cid,
                ], []);
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("maestro_regra_atualizada", "maestro", $id, [
                "nome" => $name,
                "condicao" => $trigger,
                "audit_body" =>
                    "Rotina atualizada pelo administrador do consultório.",
            ]);
        } else {
            \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.01.maestro_save_rule.02', [
                    $cid,
                    $name,
                    $active,
                    $module,
                    $trigger,
                    \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_json($cond),
                    $action,
                    \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_json($act),
                    $priority,
                    $minInterval,
                    $uid,
                ], []);
            $id = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->lastInsertId();
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("maestro_regra_criada", "maestro", $id, [
                "nome" => $name,
                "condicao" => $trigger,
                "audit_body" => "Rotina criada pelo administrador do consultório.",
            ]);
        }
    
    }

    public static function maestro_target_label(array $act, int $cid): string
    
    {
    
        $dest = (string) ($act["target_scope"] ?? "clinic");
        if ($dest === "role") {
            return "Naipe: " .
                \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for((string) ($act["target_role"] ?? ""), $cid);
        }
        if ($dest === "user") {
            $uid = (int) ($act["target_user_id"] ?? 0);
            $name =
                $uid > 0
                    ? (string) (\Prontoo\Runtime\Operational\OperationalComposition::maestro()->scalar('operational.maestro.01.maestro_target_label.01', [$uid, $cid], []) ?:
                    "")
                    : "";
            return $name !== "" ? "Pessoa: " . $name : "Pessoa específica";
        }
        return "Toda a equipe";
    
    }

    public static function maestro_last_label(?string $value): string
    
    {
    
        $value = mb_trim((string) $value);
        return $value !== "" ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($value) : "Ainda não afinada";
    
    }

    public static function maestro_next_label(?string $value): string
    
    {
    
        $value = mb_trim((string) $value);
        if ($value === "") {
            return "No próximo ciclo";
        }
        $ts = strtotime($value);
        if (!$ts) {
            return "No próximo ciclo";
        }
        return $ts <= time() ? "No próximo ciclo" : \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($value);
    
    }
}
