<?php
declare(strict_types=1);

namespace Prontoo\Domain\Legacy\Documents;

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

final class DocumentsDomainOperations02
{
    private function __construct()
    {
    }

    public static function document_context_json_encode(array $context): string
    
    {
    
        $clean = [];
        foreach (
            [
                "lead_id",
                "procedure_id",
                "task_id",
                "care_id",
                "collaborator_user_id",
            ]
            as $k
        ) {
            $v = max(0, (int) ($context[$k] ?? 0));
            if ($v > 0) {
                $clean[$k] = $v;
            }
        }
        return $clean
            ? (json_encode(
                $clean,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ) ?:
                "{}")
            : "{}";
    
    }

    public static function patient_summary_excerpt(string $text, int $limit = 220): string
    
    {
    
        $text = str_ireplace(["<br>", "<br/>", "<br />"], "\n", $text);
        $plain = html_entity_decode(
            strip_tags($text),
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8",
        );
        $plain = mb_trim((string) preg_replace("/\s+/u", " ", $plain));
        if ($plain === "") {
            return "Sem conteúdo registrado.";
        }
        return mb_strlen($plain) > $limit
            ? mb_substr($plain, 0, $limit) . "…"
            : $plain;
    
    }
}
