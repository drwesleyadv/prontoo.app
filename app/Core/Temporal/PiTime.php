<?php
declare(strict_types=1);
namespace Prontoo\Core\Temporal;
final class PiTime
{
    public const POLICY_VERSION = "pi-time-unix-utc-v2.3";
    private const FALLBACK_TZ = "America/Cuiaba";
    private function __construct() {
        








    }
    public static function now(): int
    {
        








        return time();
    }
    public static function todayUtcNoon(): int
    {
        








        return (int) \gmmktime(
            12,
            0,
            0,
            (int) \gmdate("n"),
            (int) \gmdate("j"),
            (int) \gmdate("Y"),
        );
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
        








        $sql = self::rewriteTemporalFunctions($sql);
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
    public static function prepareRuntimeQuery(
        string $sql,
        array $params,
    ): array {
        








        $sql = self::rewriteTemporalFunctions($sql);
        [$sql, $params] = self::expandDynamicIntervals($sql, $params);
        $params = self::convertParamsBySql($sql, $params);
        return [$sql, $params];
    }
    public static function rewriteTemporalFunctions(string $sql): string
    {
        








        $now = (string) self::now();
        $todayYmd = self::contextToday();
        $today = (string) self::dateOnlyToTimestamp($todayYmd);
        [$dayStart, $dayEnd] = self::contextDayRange($todayYmd);
        $sql = self::replaceCallbackOutsideStrings(
            $sql,
            "/DATE_(ADD|SUB)\s*\(\s*CURDATE\s*\(\s*\)\s*,\s*INTERVAL\s+([0-9]+)\s+(MINUTE|HOUR|DAY|MONTH|YEAR)\s*\)/i",
            static function (array $m) use ($todayYmd): string {
                








                $amount = (strtoupper($m[1]) === "ADD" ? 1 : -1) * (int) $m[2];
                return (string) self::adjustCurdateInterval(
                    $todayYmd,
                    $amount,
                    strtoupper($m[3]),
                );
            },
        );
        $sql = self::replaceOutsideStrings($sql, [
            "/\bDATE\s*\(\s*([A-Za-z0-9_`.]+)\s*\)\s*=\s*CURDATE\s*\(\s*\)/i" => "($1 >= " . $dayStart . " AND $1 < " . $dayEnd . ")",
            "/\bCURDATE\s*\(\s*\)\s*=\s*DATE\s*\(\s*([A-Za-z0-9_`.]+)\s*\)/i" => "($1 >= " . $dayStart . " AND $1 < " . $dayEnd . ")",
        ]);
        $out = self::replaceOutsideStrings($sql, [
            "/\bCURRENT_TIMESTAMP\s*(?:\(\s*\))?/i" => $now,
            "/\bUTC_TIMESTAMP\s*\(\s*\)/i" => $now,
            "/\bNOW\s*\(\s*\)/i" => $now,
            "/\bCURDATE\s*\(\s*\)/i" => $today,
            "/\bFROM_UNIXTIME\s*\(\s*\?\s*\)/i" => "?",
            "/\bUNIX_TIMESTAMP\s*\(\s*\)/i" => $now,
        ]);
        $out =
            preg_replace_callback(
                "/DATE_FORMAT\s*\(\s*([A-Za-z0-9_`.]+)\s*,/i",
                function (array $m): string {
                    








                    $expr = trim($m[1]);
                    if (stripos($expr, "FROM_UNIXTIME") !== false) {
                        return $m[0];
                    }
                    return "DATE_FORMAT(FROM_UNIXTIME(" . $expr . "),";
                },
                $out,
            ) ?? $out;
        $out =
            preg_replace_callback(
                "/\bDATE\s*\(\s*([A-Za-z0-9_`.]+)\s*\)/i",
                function (array $m): string {
                    








                    $expr = trim($m[1]);
                    if (stripos($expr, "FROM_UNIXTIME") !== false) {
                        return $m[0];
                    }
                    return "DATE(FROM_UNIXTIME(" . $expr . "))";
                },
                $out,
            ) ?? $out;
        $out =
            preg_replace_callback(
                "/DATE_SUB\s*\(\s*([0-9]+)\s*,\s*INTERVAL\s+([0-9]+)\s+(MINUTE|HOUR|DAY|MONTH|YEAR)\s*\)/i",
                 fn($m) => (string) self::adjustTimestamp(
                    (int) $m[1],
                    -(int) $m[2],
                    strtoupper($m[3]),
                ),
                $out,
            ) ?? $out;
        $out =
            preg_replace_callback(
                "/DATE_ADD\s*\(\s*([0-9]+)\s*,\s*INTERVAL\s+([0-9]+)\s+(MINUTE|HOUR|DAY|MONTH|YEAR)\s*\)/i",
                 fn($m) => (string) self::adjustTimestamp(
                    (int) $m[1],
                    (int) $m[2],
                    strtoupper($m[3]),
                ),
                $out,
            ) ?? $out;
        $out =
            preg_replace_callback(
                "/DATE_(ADD|SUB)\s*\(\s*([0-9]+)\s*,\s*INTERVAL\s+([A-Za-z0-9_`.]+)\s+(MINUTE|HOUR|DAY|MONTH|YEAR)\s*\)/i",
                function (array $m): string {
                    








                    $factor = self::intervalSeconds(1, strtoupper($m[4]));
                    $sign = strtoupper($m[1]) === "ADD" ? "+" : "-";
                    return "(" .
                        (int) $m[2] .
                        " " .
                        $sign .
                        " (" .
                        trim($m[3]) .
                        " * " .
                        $factor .
                        "))";
                },
                $out,
            ) ?? $out;
        return $out;
    }
    private static function expandDynamicIntervals(
        string $sql,
        array $params,
    ): array {
        








        if (!$params || array_keys($params) !== range(0, count($params) - 1)) {
            return [$sql, $params];
        }
        $pattern =
            "/DATE_(ADD|SUB)\s*\(\s*([0-9]+)\s*,\s*INTERVAL\s+\?\s+(MINUTE|HOUR|DAY|MONTH|YEAR)\s*\)/i";
        $offset = 0;
        while (preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $full = $m[0][0];
            $start = $m[0][1];
            $op = strtoupper($m[1][0]);
            $base = (int) $m[2][0];
            $unit = strtoupper($m[3][0]);
            $idx = substr_count(substr($sql, 0, $start), "?");
            if (array_key_exists($idx, $params)) {
                $n = (int) $params[$idx];
                $ts = self::adjustTimestamp(
                    $base,
                    ($op === "ADD" ? 1 : -1) * $n,
                    $unit,
                );
                $params[$idx] = $ts;
                $sql =
                    substr($sql, 0, $start) .
                    "?" .
                    substr($sql, $start + strlen($full));
                $offset = $start + 1;
            } else {
                $offset = $start + strlen($full);
            }
        }
        return [$sql, $params];
    }
    private static function convertParamsBySql(
        string $sql,
        array $params,
    ): array {
        








        if (!$params) {
            return $params;
        }
        $positional = array_keys($params) === range(0, count($params) - 1);
        if (!$positional) {
            foreach ($params as $k => $v) {
                if (self::looksTemporalValue($v)) {
                    $params[$k] = self::toStorage(
                        $v,
                        self::keySuggestsDate((string) $k),
                    );
                }
            }
            return $params;
        }
        $columns = self::inferPlaceholderColumns($sql, count($params));
        foreach ($params as $i => $v) {
            $col = $columns[$i] ?? "";
            if ($col !== "" && self::isTemporalColumn($col)) {
                $params[$i] = self::toStorage($v, self::isDateOnlyColumn($col));
            }
        }
        return $params;
    }
    private static function inferPlaceholderColumns(
        string $sql,
        int $count,
    ): array {
        








        $cols = [];
        if (
            preg_match(
                "/INSERT\s+INTO\s+`?[A-Za-z0-9_]+`?\s*\(([^\)]*)\)\s*VALUES\s*\(([^\)]*)\)/is",
                $sql,
                $m,
            )
        ) {
            $names = array_map(
                 fn($v) => trim($v, "` \t\n\r\0\x0B"),
                explode(",", $m[1]),
            );
            $vals = array_map("trim", explode(",", $m[2]));
            $p = 0;
            foreach ($vals as $idx => $val) {
                if ($val === "?") {
                    $cols[$p++] = $names[$idx] ?? "";
                }
            }
        }
        if (
            preg_match(
                "/UPDATE\s+`?[A-Za-z0-9_]+`?\s+SET\s+(.+?)\s+WHERE\s+/is",
                $sql,
                $m,
            )
        ) {
            $assign = $m[1];
            if (
                preg_match_all("/`?([A-Za-z0-9_]+)`?\s*=\s*\?/i", $assign, $mm)
            ) {
                foreach ($mm[1] as $col) {
                    $cols[] = $col;
                }
            }
        }
        if (
            preg_match_all(
                "/`?([A-Za-z0-9_]+)`?\s*(?:=|>=|<=|>|<)\s*\?/i",
                $sql,
                $mm,
            )
        ) {
            foreach ($mm[1] as $col) {
                $cols[] = $col;
            }
        }
        if (
            preg_match_all(
                "/`?([A-Za-z0-9_]+)`?\s+BETWEEN\s+\?\s+AND\s+\?/i",
                $sql,
                $mm,
            )
        ) {
            foreach ($mm[1] as $col) {
                $cols[] = $col;
                $cols[] = $col;
            }
        }
        return array_slice($cols + array_fill(0, $count, ""), 0, $count);
    }
    public static function toStorage(
        mixed $value,
        bool $dateOnly = false,
    ): mixed {
        









        if ($value === null || $value === "") {
            return $value;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        $s = trim((string) $value);
        if ($s === "") {
            return $value;
        }
        if (preg_match('/^-?\d+$/', $s)) {
            return (int) $s;
        }
        if (self::looksDateOnlyValue($s) || $dateOnly) {
            return self::dateOnlyToTimestamp(substr($s, 0, 10));
        }
        try {
            $tz = new \DateTimeZone(self::contextTimezone());
            $normalized = str_replace("T", " ", $s);
            $dt = new \DateTimeImmutable($normalized, $tz);
            return $dt->setTimezone(new \DateTimeZone("UTC"))->getTimestamp();
        } catch (\Throwable $e) {
            return $value;
        }
    }
    public static function dateOnlyToTimestamp(string $ymd): int
    {
        








        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return 0;
        }
        $year = (int) substr($ymd, 0, 4);
        $month = (int) substr($ymd, 5, 2);
        $day = (int) substr($ymd, 8, 2);
        if (!checkdate($month, $day, $year)) {
            return 0;
        }
        return (int) \gmmktime(12, 0, 0, $month, $day, $year);
    }
    public static function toLocalDateTime(
        mixed $value,
        ?string $tz = null,
    ): ?\DateTimeImmutable {
        









        if ($value === null || $value === "") {
            return null;
        }
        try {
            if (
                is_int($value) ||
                (is_string($value) && preg_match('/^-?\d+$/', trim($value)))
            ) {
                return new \DateTimeImmutable("@" . (int) $value)->setTimezone(
                    new \DateTimeZone(
                        self::safeTimezone($tz ?? self::contextTimezone()),
                    ),
                );
            }
            return new \DateTimeImmutable(
                (string) $value,
                new \DateTimeZone("UTC"),
            )->setTimezone(
                new \DateTimeZone(
                    self::safeTimezone($tz ?? self::contextTimezone()),
                ),
            );
        } catch (\Throwable $e) {
            return null;
        }
    }
    public static function fromLocalToUtcTimestamp(
        ?string $value,
        bool $dateOnly = false,
    ): string {
        








        $v = self::toStorage($value, $dateOnly);
        return $v === null || $v === "" ? "" : (string) $v;
    }
    public static function normalizeInsertedTemporalDefaults(
        \PDO $pdo,
        string $table,
        string|int $id,
    ): void {
        








        try {
            $table = self::cleanIdent($table);
            $pk = self::singlePrimaryKey($pdo, $table) ?: "id";
            $cols = self::columns($pdo, $table);
            $sets = [];
            $params = [];
            $now = time();
            foreach (["created_at", "updated_at"] as $col) {
                if (!in_array($col, $cols, true)) {
                    continue;
                }
                if ($col === "created_at") {
                    $sets[] =
                        "`created_at`=IF(`created_at` IS NULL OR `created_at`=0, ?, `created_at`)";
                    $params[] = $now;
                }
            }
            if (!$sets) {
                return;
            }
            $params[] = (string) $id;
            $st = $pdo->prepare(
                "UPDATE `" .
                    $table .
                    "` SET " .
                    implode(",", $sets) .
                    " WHERE `" .
                    self::cleanIdent($pk) .
                    "`=? LIMIT 1",
            );
            $st->execute($params);
        } catch (\Throwable $e) {
            error_log(
                "[Prontoo PI time normalize insert] " .
                    $table .
                    " | " .
                    $e->getMessage(),
            );
        }
    }
    private static function singlePrimaryKey(\PDO $pdo, string $table): ?string
    {
        








        $st = $pdo->prepare(
            "SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_key='PRI' ORDER BY ordinal_position",
        );
        $st->execute([$table]);
        $pks = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        return count($pks) === 1 ? (string) $pks[0] : null;
    }
    private static function columns(\PDO $pdo, string $table): array
    {
        








        $st = $pdo->prepare(
            "SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ordinal_position",
        );
        $st->execute([$table]);
        return array_values(
            array_map("strval", $st->fetchAll(\PDO::FETCH_COLUMN) ?: []),
        );
    }
    public static function countTemporalViolations(?\PDO $pdo = null): array
    {
        








        $out = [
            "datetime_columns" => 0,
            "date_columns" => 0,
            "timestamp_columns" => 0,
            "columns" => [],
        ];
        try {
            $pdo = $pdo ?: (function_exists("pdo") ? \pdo() : null);
            if (!$pdo) {
                return $out;
            }
            $st = $pdo->query(
                "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, DATA_TYPE AS data_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name LIKE 'pi\\_%' AND data_type IN ('datetime','timestamp','date') ORDER BY TABLE_NAME,COLUMN_NAME",
            );
            foreach (($st ? $st->fetchAll(\PDO::FETCH_ASSOC) : []) ?: [] as $r) {
                $row = [];
                foreach ($r as $k => $v) {
                    if (is_string($k)) {
                        $row[strtolower($k)] = $v;
                    }
                }
                $dt = strtolower((string) ($row["data_type"] ?? ""));
                $table = trim((string) ($row["table_name"] ?? ""));
                $column = trim((string) ($row["column_name"] ?? ""));
                if ($dt === "" || $table === "" || $column === "") {
                    continue;
                }
                $out[$dt . "_columns"] =
                    (int) ($out[$dt . "_columns"] ?? 0) + 1;
                $out["columns"][] = $table . "." . $column . ":" . $dt;
            }
        } catch (\Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
        return $out;
    }
    private static function replaceCallbackOutsideStrings(
        string $sql,
        string $pattern,
        callable $callback,
    ): string {
        








        $parts = preg_split(
            "/('(?:''|[^'])*'|\"(?:\\\\.|[^\"])*\")/",
            $sql,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );
        if (!$parts) {
            return $sql;
        }
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = preg_replace_callback($pattern, $callback, $part) ?? $part;
            }
        }
        return implode("", $parts);
    }
    private static function replaceOutsideStrings(
        string $sql,
        array $patterns,
    ): string {
        








        $parts = preg_split(
            "/('(?:''|[^'])*'|\"(?:\\\\.|[^\"])*\")/",
            $sql,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );
        if (!$parts) {
            return $sql;
        }
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                continue;
            }
            foreach ($patterns as $pattern => $replacement) {
                $part = preg_replace($pattern, $replacement, $part) ?? $part;
            }
            $parts[$i] = $part;
        }
        return implode("", $parts);
    }
    private static function contextToday(): string
    {
        









        return (new \DateTimeImmutable(
            "now",
            new \DateTimeZone(self::contextTimezone()),
        ))->format("Y-m-d");
    }
    private static function contextDayRange(string $ymd): array
    {
        









        $zone = new \DateTimeZone(self::contextTimezone());
        $start = new \DateTimeImmutable($ymd . " 00:00:00", $zone);
        $end = $start->modify("+1 day");
        $utc = new \DateTimeZone("UTC");
        return [
            $start->setTimezone($utc)->getTimestamp(),
            $end->setTimezone($utc)->getTimestamp(),
        ];
    }
    private static function adjustCurdateInterval(
        string $todayYmd,
        int $amount,
        string $unit,
    ): int {
        









        if (in_array($unit, ["MONTH", "YEAR"], true)) {
            return self::adjustTimestamp(
                self::dateOnlyToTimestamp($todayYmd),
                $amount,
                $unit,
            );
        }
        $zone = new \DateTimeZone(self::contextTimezone());
        $base = new \DateTimeImmutable($todayYmd . " 00:00:00", $zone);
        $spec = match ($unit) {
            "MINUTE" => ($amount >= 0 ? "+" : "") . $amount . " minutes",
            "HOUR" => ($amount >= 0 ? "+" : "") . $amount . " hours",
            default => ($amount >= 0 ? "+" : "") . $amount . " days",
        };
        return $base
            ->modify($spec)
            ->setTimezone(new \DateTimeZone("UTC"))
            ->getTimestamp();
    }
    private static function adjustTimestamp(int $base, int $amount, string $unit): int
    {
        









        $unit = strtoupper($unit);
        if ($amount === 0) {
            return $base;
        }
        if (in_array($unit, ["MINUTE", "HOUR", "DAY"], true)) {
            return $base + $amount * self::intervalSeconds(1, $unit);
        }
        $dt = (new \DateTimeImmutable("@" . $base))->setTimezone(
            new \DateTimeZone("UTC"),
        );
        $year = (int) $dt->format("Y");
        $month = (int) $dt->format("n");
        $day = (int) $dt->format("j");
        if ($unit === "YEAR") {
            $year += $amount;
        } elseif ($unit === "MONTH") {
            $total = $year * 12 + ($month - 1) + $amount;
            $year = intdiv($total, 12);
            $month = $total % 12 + 1;
            if ($month <= 0) {
                $month += 12;
                $year--;
            }
        } else {
            return $base + $amount;
        }
        $day = min($day, cal_days_in_month(CAL_GREGORIAN, $month, $year));
        return $dt
            ->setDate($year, $month, $day)
            ->getTimestamp();
    }
    private static function intervalSeconds(int $n, string $unit): int
    {
        








        return match ($unit) {
            "MINUTE" => $n * 60,
            "HOUR" => $n * 3600,
            "DAY" => $n * 86400,
            "MONTH" => $n * 2592000,
            "YEAR" => $n * 31536000,
            default => $n,
        };
    }
    private static function looksTemporalValue(mixed $v): bool
    {
        








        if (!is_string($v)) {
            return false;
        }
        $s = trim($v);
        return self::looksDateOnlyValue($s) ||
            (bool) preg_match(
                '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?$/',
                $s,
            );
    }
    private static function looksDateOnlyValue(mixed $v): bool
    {
        








        return is_string($v) &&
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($v));
    }
    private static function keySuggestsDate(string $key): bool
    {
        








        return str_ends_with(strtolower($key), "date") ||
            strtolower($key) === "birth_date";
    }
    private static function contextTimezone(): string
    {
        









        return self::safeTimezone(
            (string) ($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? self::FALLBACK_TZ),
        );
    }
    private static function safeTimezone(string $tz): string
    {
        








        $tz = trim($tz);
        return in_array($tz, \timezone_identifiers_list(), true)
            ? $tz
            : self::FALLBACK_TZ;
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
