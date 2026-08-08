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

final class AuditDocumentPolicy
{
    private function __construct()
    {
    }

    public static function audit_document_type_text(array $ctx): string
    
    {
    
        $label = mb_trim((string) ($ctx["document_type_label"] ?? ""));
        if ($label === "") {
            $key = (string) ($ctx["document_type"] ?? ($ctx["type_key"] ?? ""));
            $types = function_exists("document_type_options")
                ? document_type_options()
                : [];
            $label = $types[$key] ?? "";
        }
        if ($label === "") {
            $label = mb_trim((string) ($ctx["titulo"] ?? "Documento"));
        }
        return $label !== "" ? $label : "Documento";
    
    }

    public static function audit_document_article(string $label): string
    
    {
    
        $l = mb_strtolower(trim($label));
        foreach (["receita", "declaração", "solicitação", "orientação"] as $fem) {
            if (str_starts_with($l, $fem)) {
                return "uma";
            }
        }
        return "um";
    
    }

    public static function audit_document_activity_sentence(
        string $who,
        string $action,
        array $ctx,
    ): string 
    {
    
        $doc = audit_document_type_text($ctx);
        $patient = mb_trim((string) ($ctx["patient_name"] ?? ""));
        $txt =
            $who . " " . $action . " " . audit_document_article($doc) . " " . $doc;
        if ($patient !== "") {
            $txt .= ($action === "visualizou" ? " de " : " para ") . $patient;
        }
        return $txt . ".";
    
    }

    public static function audit_model_title(array $ctx): string
    
    {
    
        $t = trim(
            (string) ($ctx["template_title"] ??
                ($ctx["titulo"] ?? ($ctx["title"] ?? ($ctx["modelo"] ?? "")))),
        );
        return $t !== "" ? $t : "documento";
    
    }

    public static function audit_model_activity_sentence(
        string $who,
        string $action,
        array $ctx,
    ): string 
    {
    
        $model = audit_model_title($ctx);
        return match ($action) {
            "criou" => "$who criou um modelo de $model.",
            "alterou" => "$who alterou o modelo $model.",
            "aprovou" => "$who aprovou o modelo $model.",
            "rejeitou" => "$who rejeitou o modelo $model.",
            default => "$who alterou o modelo $model.",
        };
    
    }
}
