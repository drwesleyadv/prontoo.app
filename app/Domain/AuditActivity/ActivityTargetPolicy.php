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

final class ActivityTargetPolicy
{
    private function __construct()
    {
    }

    public static function activity_patient_name(array $ctx, mixed $entityId = null): string
    
    {
    
        return \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_clean_name(\Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_patient_name($ctx, $entityId), "paciente");
    
    }

    public static function activity_title_from_ctx(
        array $ctx,
        string $fallback = "registro",
    ): string 
    {
    
        return \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_clean_name(
            \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value(
                $ctx["titulo"] ??
                    ($ctx["title"] ?? ($ctx["name"] ?? ($ctx["descricao"] ?? ""))),
            ),
            $fallback,
        );
    
    }

    public static function activity_person_from_ctx(
        array $ctx,
        string $fallback = "colaborador",
    ): string 
    {
    
        $n = \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value(
            $ctx["target_name"] ?? ($ctx["nome"] ?? ($ctx["name"] ?? "")),
        );
        if ($n === "") {
            $n = trim(\Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_person_target("", $ctx));
        }
        return \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_clean_name($n, $fallback);
    
    }

    public static function activity_target_scope_human(array $ctx): string
    
    {
    
        $scope = \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value(
            $ctx["destino"] ?? ($ctx["target_scope"] ?? ""),
        );
        if ($scope === "role") {
            $cargo = \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value(
                $ctx["cargo"] ?? ($ctx["target_role"] ?? ""),
            );
            return $cargo !== "" ? "para o setor " . $cargo : "para um setor";
        }
        if ($scope === "user") {
            $name = \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value(
                $ctx["assigned_name"] ?? ($ctx["target_name"] ?? ""),
            );
            return $name !== "" ? "para " . $name : "para uma pessoa da equipe";
        }
        if ($scope === "clinic") {
            return "para toda a equipe";
        }
        return $scope !== "" ? "para " . $scope : "";
    
    }

    public static function activity_financial_label(array $ctx, string $fallback): string
    
    {
    
        $title = \Prontoo\Domain\AuditActivity\ActivityTargetPolicy::activity_title_from_ctx($ctx, "");
        if ($title !== "") {
            return $title;
        }
        $cp = \Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_clean_name(\Prontoo\Domain\AuditActivity\AuditTargetPolicy::audit_counterparty_name($ctx), "");
        return $cp !== "" ? $cp : $fallback;
    
    }

    public static function activity_action_verb(string $event): string
    
    {
    
        [$axis] = \Prontoo\Domain\AuditActivity\ActivityTaxonomy::activity_axis_for_event($event);
        return [
            "Criar" => "cadastrou",
            "Modificar" => "alterou",
            "Consultar" => "consultou",
            "Excluir" => "excluiu",
        ][$axis] ?? "alterou";
    
    }

    public static function activity_direct_target(
        string $event,
        ?string $entity,
        mixed $entityId,
        array $ctx,
    ): string 
    {
    
        $patient = \Prontoo\Domain\AuditActivity\ActivityTargetPolicy::activity_patient_name($ctx, $entityId);
        $title = \Prontoo\Domain\AuditActivity\ActivityTargetPolicy::activity_title_from_ctx($ctx, "");
        if ($event === "janela_aberta") {
            return $entity === "paciente" ||
                mb_trim((string) ($ctx["patient_name"] ?? "")) !== ""
                ? "a ficha do paciente " . $patient
                : "a tela " .
                        (\Prontoo\Domain\AuditActivity\ActivityValuePolicy::activity_text_value($ctx["janela"] ?? $title) ?:
                            "do sistema");
        }
        if ($entity === "paciente") {
            return "a ficha do paciente " . $patient;
        }
        if ($entity === "consulta") {
            return "a consulta de " . $patient;
        }
        if ($entity === "documento") {
            return "o documento " . \Prontoo\Domain\AuditActivity\AuditDocumentPolicy::audit_document_type_text($ctx);
        }
        if ($entity === "tarefa") {
            return "a tarefa " . \Prontoo\Domain\AuditActivity\AuditActivityDomainOperations03::audit_task_name($ctx);
        }
        if ($entity === "usuario") {
            return "o colaborador " . \Prontoo\Domain\AuditActivity\ActivityTargetPolicy::activity_person_from_ctx($ctx);
        }
        if ($entity === "financeiro") {
            return "o financeiro";
        }
        $label = \Prontoo\Domain\AuditActivity\AuditActivityDomainOperations03::entity_label($entity);
        $article = in_array(
            $label,
            [
                "consulta",
                "tarefa",
                "aviso",
                "janela",
                "rota",
                "configuração",
                "assinatura",
                "entrada",
                "segurança",
            ],
            true,
        )
            ? "a"
            : "o";
        return $article . " " . $label . ($title !== "" ? " " . $title : "");
    
    }

}
