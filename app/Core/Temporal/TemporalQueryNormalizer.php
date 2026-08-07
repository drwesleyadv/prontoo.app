<?php
declare(strict_types=1);
namespace Prontoo\Core\Temporal;
final class TemporalQueryNormalizer
{
    private const FALLBACK_TZ = "America/Cuiaba";

    private function __construct()
    {
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

        $now = (string) PiTime::now();
        $todayYmd = self::contextToday();
        $today = (string) PiTime::dateOnlyToTimestamp($todayYmd);
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
    public static function expandDynamicIntervals(
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
    public static function convertParamsBySql(
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
                    $params[$k] = PiTime::toStorage(
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
            if ($col !== "" && PiTime::isTemporalColumn($col)) {
                $params[$i] = PiTime::toStorage($v, PiTime::isDateOnlyColumn($col));
            }
        }
        return $params;
    }
    public static function inferPlaceholderColumns(
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
    public static function replaceCallbackOutsideStrings(
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
    public static function replaceOutsideStrings(
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
    public static function contextToday(): string
    {

        return (new \DateTimeImmutable(
            "now",
            new \DateTimeZone(self::contextTimezone()),
        ))->format("Y-m-d");
    }
    public static function contextDayRange(string $ymd): array
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
    public static function adjustCurdateInterval(
        string $todayYmd,
        int $amount,
        string $unit,
    ): int {

        if (in_array($unit, ["MONTH", "YEAR"], true)) {
            return self::adjustTimestamp(
                PiTime::dateOnlyToTimestamp($todayYmd),
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
    public static function adjustTimestamp(int $base, int $amount, string $unit): int
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
    public static function intervalSeconds(int $n, string $unit): int
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
    public static function looksTemporalValue(mixed $v): bool
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
    public static function looksDateOnlyValue(mixed $v): bool
    {

        return is_string($v) &&
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($v));
    }
    public static function keySuggestsDate(string $key): bool
    {

        return str_ends_with(strtolower($key), "date") ||
            strtolower($key) === "birth_date";
    }
    public static function contextTimezone(): string
    {

        return self::safeTimezone(
            (string) ($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? self::FALLBACK_TZ),
        );
    }
    public static function safeTimezone(string $tz): string
    {

        $tz = trim($tz);
        return in_array($tz, \timezone_identifiers_list(), true)
            ? $tz
            : self::FALLBACK_TZ;
    }
}