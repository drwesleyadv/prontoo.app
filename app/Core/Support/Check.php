<?php
declare(strict_types=1);
namespace Prontoo\Core\Support;
final class Check
{
    private function __construct() {}
    public static function positiveInt(mixed $value, string $field): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, [
            "options" => ["min_range" => 1],
        ]);
        if ($int === false) {
            throw new \InvalidArgumentException(
                $field . " deve ser um inteiro positivo.",
            );
        }
        return (int) $int;
    }
    public static function nonNegativeInt(mixed $value, string $field): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, [
            "options" => ["min_range" => 0],
        ]);
        if ($int === false) {
            throw new \InvalidArgumentException(
                $field . " deve ser zero ou inteiro positivo.",
            );
        }
        return (int) $int;
    }
    public static function identifier(
        string $value,
        string $field = "identificador",
    ): string {
        $value = trim($value);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new \InvalidArgumentException($field . " inválido.");
        }
        return $value;
    }
    public static function scopedColumn(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^[A-Za-z0-9_`\.]+$/', $value)) {
            throw new \InvalidArgumentException("Coluna de escopo inválida.");
        }
        return $value;
    }
    public static function normalizedSql(string $sql): string
    {
        return strtolower(preg_replace("/\s+/", " ", trim($sql)) ?? "");
    }
    public static function writeOperation(string $sql): ?string
    {
        if (
            !preg_match(
                "/^\s*(UPDATE|DELETE|INSERT|REPLACE)\s+/i",
                $sql,
                $match,
            )
        ) {
            return null;
        }
        return strtoupper($match[1]);
    }
    public static function sqlFingerprint(string $sql): string
    {
        return hash("sha256", preg_replace("/\s+/", " ", trim($sql)) ?? "");
    }
    public static function tableHit(string $normalizedSql, string $table): bool
    {
        self::identifier($table, "tabela");
        $table = strtolower($table);
        $quoted = "`" . $table . "`";
        return preg_match(
            "/\b" . preg_quote($table, "/") . "\b/",
            $normalizedSql,
        ) === 1 || str_contains($normalizedSql, $quoted);
    }
    public static function clampText(string $text, int $max): string
    {
        $max = max(1, $max);
        if (function_exists("mb_substr")) {
            return mb_substr($text, 0, $max, "UTF-8");
        }
        return substr($text, 0, $max);
    }
}
