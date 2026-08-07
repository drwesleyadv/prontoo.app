<?php
declare(strict_types=1);

namespace Prontoo\Domain\Legacy\AuditActivity;

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

final class AuditCopyPolicy
{
    private function __construct()
    {
    }

    public static function pt_list(array $items): string
    
    {
    
        $items = array_values(
            array_filter(
                array_map(static  fn($v) => mb_trim((string) $v), $items),
                static  fn($v) => $v !== "",
            ),
        );
        $n = count($items);
        if ($n === 0) {
            return "";
        }
        if ($n === 1) {
            return $items[0];
        }
        if ($n === 2) {
            return $items[0] . " e " . $items[1];
        }
        return implode(", ", array_slice($items, 0, -1)) . " e " . $items[$n - 1];
    
    }

    public static function audit_change_body(array $fields): string
    
    {
    
        $fields = array_values(
            array_unique(
                array_filter(
                    array_map(static  fn($v) => mb_trim((string) $v), $fields),
                    static  fn($v) => $v !== "",
                ),
            ),
        );
        if (!$fields) {
            return "Nenhuma informação foi alterada.";
        }
        if (count($fields) === 1) {
            return "O campo " . pt_list($fields) . " foi modificado.";
        }
        return "Os campos " . pt_list($fields) . " foram modificados.";
    
    }

    public static function audit_value_present(mixed $v): bool
    
    {
    
        if (is_array($v)) {
            return !empty($v);
        }
        return mb_trim((string) $v) !== "";
    
    }

    public static function audit_field_list(array $ctx, array $labels, array $forced = []): array
    
    {
    
        $out = [];
        foreach ($forced as $label) {
            if (mb_trim((string) $label) !== "") {
                $out[] = (string) $label;
            }
        }
        foreach ($labels as $key => $label) {
            if (
                array_key_exists((string) $key, $ctx) &&
                audit_value_present($ctx[(string) $key])
            ) {
                $out[] = (string) $label;
            }
        }
        return array_values(array_unique($out));
    
    }

    public static function audit_registered_body(
        array $fields,
        string $empty = "Nenhum dado adicional foi informado.",
    ): string 
    {
    
        $fields = array_values(
            array_unique(
                array_filter(
                    array_map(static  fn($v) => mb_trim((string) $v), $fields),
                    static  fn($v) => $v !== "",
                ),
            ),
        );
        if (!$fields) {
            return $empty;
        }
        if (count($fields) === 1) {
            return "Foi registrado: " . $fields[0] . ".";
        }
        return "Foram registrados: " . pt_list($fields) . ".";
    
    }

    public static function audit_status_body(array $ctx, string $label = "status"): string
    
    {
    
        $status = (string) ($ctx["status"] ?? ($ctx["novo_status"] ?? ""));
        return $status !== ""
            ? "O " . $label . " foi alterado para " . $status . "."
            : "O " . $label . " foi alterado.";
    
    }

}
