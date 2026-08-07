<?php
declare(strict_types=1);
namespace Prontoo\Core\Temporal;
final class TemporalSchemaPolicy
{
    private function __construct()
    {
    }

    public static function isTemporalColumn(string $column): bool
    {

        $c = strtolower(trim($column, "` \t\n\r\0\x0B"));
        if ($c === "" || $c === "Seq") {
            return false;
        }
        if (
            in_array(
                $c,
                [
                    "birth_date",
                    "paid_until",
                    "business_date",
                    "drawer_locked_business_date",
                    "document_date",
                    "anchor_date",
                    "competence_date",
                    "reference_date",
                    "payment_date",
                    "due_date",
                ],
                true,
            )
        ) {
            return true;
        }
        if (preg_match('/(_at|_date|_datetime|_timestamp|_until)$/', $c)) {
            return true;
        }
        if (
            in_array(
                $c,
                [
                    "created_at",
                    "updated_at",
                    "deleted_at",
                    "restored_at",
                    "start_at",
                    "end_at",
                    "due_at",
                    "paid_at",
                    "received_at",
                    "expected_at",
                    "issued_at",
                    "confirmed_at",
                    "cancelled_at",
                    "trusted_until",
                    "locked_until",
                    "last_seen_at",
                    "last_login_at",
                ],
                true,
            )
        ) {
            return true;
        }
        return false;
    }
    public static function isDateOnlyColumn(string $column): bool
    {

        $c = strtolower(trim($column, "` \t\n\r\0\x0B"));
        return $c === "birth_date" ||
            $c === "paid_until" ||
            $c === "business_date" ||
            str_ends_with($c, "_date");
    }
    public static function storageDefinitionFor(
        string $column,
        string $definition,
    ): string {

        if (
            !self::isTemporalColumn($column) &&
            !preg_match("/\b(DATETIME|TIMESTAMP|DATE)\b/i", $definition)
        ) {
            return $definition;
        }
        $primary = preg_match("/\bPRIMARY\s+KEY\b/i", $definition) === 1;
        $nullable = !$primary && !preg_match("/\bNOT\s+NULL\b/i", $definition);
        $after = "";
        if (preg_match("/\s+AFTER\s+`?([A-Za-z0-9_]+)`?/i", $definition, $m)) {
            $after = " AFTER `" . $m[1] . "`";
        } elseif (preg_match("/\s+FIRST\b/i", $definition)) {
            $after = " FIRST";
        }
        $comment = "";
        if (
            preg_match('/\s+COMMENT\s+(\'[^\']*\'|"[^"]*")/i', $definition, $m)
        ) {
            $comment = " COMMENT " . $m[1];
        }
        return "`" .
            self::cleanIdent($column) .
            "` BIGINT UNSIGNED " .
            ($nullable ? "NULL" : "NOT NULL DEFAULT 0") .
            ($primary ? " PRIMARY KEY" : "") .
            $comment .
            $after;
    }
    public static function rewriteColumnDefinition(
        string $column,
        string $definition,
    ): string {

        if (
            !self::isTemporalColumn($column) &&
            !preg_match("/\b(DATETIME|TIMESTAMP|DATE)\b/i", $definition)
        ) {
            return $definition;
        }
        $primary = preg_match("/\bPRIMARY\s+KEY\b/i", $definition) === 1;
        $nullable = !$primary && !preg_match("/\bNOT\s+NULL\b/i", $definition);
        $after = "";
        if (preg_match("/\s+AFTER\s+`?([A-Za-z0-9_]+)`?/i", $definition, $m)) {
            $after = " AFTER `" . $m[1] . "`";
        } elseif (preg_match("/\s+FIRST\b/i", $definition)) {
            $after = " FIRST";
        }
        return self::cleanIdent($column) .
            " BIGINT UNSIGNED " .
            ($nullable ? "NULL" : "NOT NULL DEFAULT 0") .
            ($primary ? " PRIMARY KEY" : "") .
            $after;
    }
    public static function rewriteSchemaSql(string $sql): string
    {

        $sql = PiTime::rewriteTemporalFunctions($sql);
        $sql =
            preg_replace_callback(
                "/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?[A-Za-z0-9_]+`?\s*\((.*)\)\s*ENGINE\s*=\s*InnoDB/isU",
                function (array $m): string {

                    $full = $m[0];
                    $inside = $m[1];
                    $rewritten =
                        preg_replace_callback(
                            '/(^|,)(\s*)`?([A-Za-z0-9_]+)`?\s+([^,\n]+(?:\([^\)]*\)[^,\n]*)?)/m',
                            function (array $cm): string {

                                $prefix = $cm[1] . $cm[2];
                                $col = $cm[3];
                                $def = trim($cm[4]);
                                if (
                                    preg_match(
                                        "/^(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FOREIGN|CHECK)\b/i",
                                        $col,
                                    )
                                ) {
                                    return $cm[0];
                                }
                                if (
                                    !self::isTemporalColumn($col) &&
                                    !preg_match(
                                        "/\b(DATETIME|TIMESTAMP|DATE)\b/i",
                                        $def,
                                    )
                                ) {
                                    return $cm[0];
                                }
                                return $prefix .
                                    "`" .
                                    self::cleanIdent($col) .
                                    "` " .
                                    preg_replace(
                                        "/^`?" .
                                            preg_quote($col, "/") .
                                            "`?\s+/i",
                                        "",
                                        self::storageDefinitionFor(
                                            $col,
                                            $col . " " . $def,
                                        ),
                                    );
                            },
                            $inside,
                        ) ?? $inside;
                    return str_replace($inside, $rewritten, $full);
                },
                $sql,
            ) ?? $sql;
        $sql =
            preg_replace_callback(
                '/(ALTER\s+TABLE\s+`?[A-Za-z0-9_]+`?\s+ADD\s+COLUMN\s+)`?([A-Za-z0-9_]+)`?\s+(.+)$/is',
                function (array $m): string {

                    $prefix = $m[1];
                    $col = $m[2];
                    $def = $m[3];
                    if (
                        !self::isTemporalColumn($col) &&
                        !preg_match("/\b(DATETIME|TIMESTAMP|DATE)\b/i", $def)
                    ) {
                        return $m[0];
                    }
                    return $prefix .
                        self::rewriteColumnDefinition($col, $col . " " . $def);
                },
                $sql,
            ) ?? $sql;
        return $sql;
    }
    private static function cleanIdent(string $name): string
    {

        $name = trim($name, "` \t\n\r\0\x0B");
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \RuntimeException(
                "Identificador temporal inválido: " . $name,
            );
        }
        return $name;
    }
}
