<?php
declare(strict_types=1);

namespace Prontoo\Domain\AuditActivity;

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

final class AuditTargetPolicy
{
    private function __construct()
    {
    }

    public static function audit_patient_name(array $ctx, mixed $entityId = null): string
    
    {
    
        $name = trim(
            (string) ($ctx["patient_name"] ??
                ($ctx["paciente"] ?? ($ctx["nome_paciente"] ?? ""))),
        );
        if ($name !== "") {
            return $name;
        }
        $id =
            (int) ($ctx["patient_link_id"] ??
                ($ctx["patient_id"] ?? ($entityId ?? 0)));
        return $id > 0 ? "Paciente #" . $id : "Paciente";
    
    }

    public static function audit_person_target(
        string $label,
        array $ctx,
        string $nameKey = "target_name",
    ): string 
    {
    
        $name = trim(
            (string) ($ctx[$nameKey] ?? ($ctx["nome"] ?? ($ctx["name"] ?? ""))),
        );
        return $name !== "" ? $label . " " . $name : $label;
    
    }

    public static function audit_patient_record_target(array $ctx, mixed $entityId = null): string
    
    {
    
        return "o prontuário de " . \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_patient_name($ctx, $entityId);
    
    }

    public static function audit_clinic_target(array $ctx): string
    
    {
    
        $name = mb_trim((string) ($ctx["clinic_name"] ?? ($ctx["consultorio"] ?? "")));
        return $name !== "" ? "Consultório " . $name : "Consultório";
    
    }

    public static function audit_task_target(array $ctx): string
    
    {
    
        $title = mb_trim((string) ($ctx["task_title"] ?? ($ctx["title"] ?? "")));
        return $title !== "" ? "Tarefa “" . $title . "”" : "Tarefa";
    
    }

    public static function audit_notice_target(array $ctx): string
    
    {
    
        $title = mb_trim((string) ($ctx["notice_title"] ?? ($ctx["title"] ?? "")));
        return $title !== "" ? "Aviso “" . $title . "”" : "Aviso";
    
    }

    public static function audit_ctx_pick(array $ctx, array $keys): string
    
    {
    
        foreach ($keys as $key) {
            if (array_key_exists((string) $key, $ctx)) {
                $v = mb_trim((string) $ctx[(string) $key]);
                if ($v !== "" && $v !== "0") {
                    return $v;
                }
            }
        }
        return "";
    
    }

    public static function audit_status_text(array $ctx): string
    
    {
    
        $s = \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_ctx_pick($ctx, ["status", "novo_status", "stage", "active"]);
        if ($s === "") {
            return "";
        }
        if (in_array($s, ["1", "true", "sim"], true)) {
            return "ativo";
        }
        if (in_array($s, ["0", "false", "nao", "não"], true)) {
            return "inativo";
        }
        return str_replace("_", " ", $s);
    
    }

    public static function audit_target_scope_label(array $ctx): string
    
    {
    
        $dest = \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_ctx_pick($ctx, ["destino", "target_scope"]);
        if ($dest === "clinic") {
            return "toda a clínica";
        }
        if ($dest === "role") {
            $cargo = \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_ctx_pick($ctx, ["cargo", "target_role"]);
            return $cargo !== ""
                ? "todos do cargo " . $cargo
                : "um cargo específico";
        }
        if ($dest === "user") {
            $name = \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_ctx_pick($ctx, [
                "assigned_name",
                "target_name",
                "pessoa_nome",
                "pessoa",
            ]);
            return $name !== "" ? "a pessoa " . $name : "uma pessoa específica";
        }
        return $dest !== "" ? $dest : "";
    
    }

    public static function audit_finance_base_label_from_ctx(array $ctx): string
    
    {
    
        $base = \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_ctx_pick($ctx, ["base", "base_metric"]);
        return $base !== "" ? \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_goal_base_label($base) : "";
    
    }

    public static function audit_account_name(array $ctx): string
    
    {
    
        return \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_ctx_pick($ctx, [
            "account_name",
            "conta",
            "name",
            "titulo",
            "title",
        ]);
    
    }

    public static function audit_counterparty_name(array $ctx): string
    
    {
    
        return \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_ctx_pick($ctx, [
            "counterparty_name",
            "credor",
            "target_name",
            "nome",
            "name",
        ]);
    
    }

    public static function audit_financial_title(array $ctx): string
    
    {
    
        return \Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_ctx_pick($ctx, ["titulo", "title", "name", "descricao"]);
    
    }

}
