<?php
declare(strict_types=1);
function seq_footer_symbol_pool(): string
{
    return "BCDFGHJKLMNPQRSTVWXYZbcdfghjklmnpqrstvwxyz0123456789";
}
function seq_footer_meta_key(): string
{
    return "global_footer_seq_alphabet";
}
function seq_footer_min_digits(): int
{
    return 4;
}
function seq_footer_normalize_alphabet(string $alphabet): string
{
    $allowed = str_split(seq_footer_symbol_pool());
    $allowedMap = array_fill_keys($allowed, true);
    $out = [];
    foreach (str_split($alphabet) as $ch) {
        if (isset($allowedMap[$ch]) && !isset($out[$ch])) {
            $out[$ch] = $ch;
        }
    }
    return count($out) === count($allowed)
        ? implode("", array_values($out))
        : "";
}
function seq_footer_deterministic_alphabet(): string
{
    $chars = str_split(seq_footer_symbol_pool());
    $secret = "prontoo-footer-seq";
    if (function_exists("cfg")) {
        try {
            $secret = (string) (cfg()["secret"] ?? $secret);
        } catch (\Throwable $recoverableError) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $recoverableError->getMessage(),
            );
        }
    }
    usort(
        $chars,
        static fn(string $a, string $b): int => strcmp(
            hash("sha256", $secret . "|" . $a),
            hash("sha256", $secret . "|" . $b),
        ),
    );
    return implode("", $chars);
}
function seq_footer_generate_alphabet(): string
{
    $chars = str_split(seq_footer_symbol_pool());
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode("", $chars);
}
function seq_footer_alphabet(bool $persistIfMissing = false): string
{
    static $cache = null;
    if (is_string($cache) && $cache !== "") {
        return $cache;
    }
    $stored = "";
    if (
        function_exists("meta_get") &&
        function_exists("has_cfg") &&
        has_cfg()
    ) {
        try {
            $stored = (string) meta_get(seq_footer_meta_key(), "");
        } catch (Throwable) {
            $stored = "";
        }
    }
    $normalized = seq_footer_normalize_alphabet($stored);
    if ($normalized !== "") {
        return $cache = $normalized;
    }
    $generated = $persistIfMissing
        ? seq_footer_generate_alphabet()
        : seq_footer_deterministic_alphabet();
    if (
        $persistIfMissing &&
        function_exists("meta_set") &&
        function_exists("has_cfg") &&
        has_cfg()
    ) {
        try {
            meta_set(seq_footer_meta_key(), $generated);
        } catch (Throwable $e) {
            error_log("[Prontoo seq footer alphabet] " . $e->getMessage());
        }
    }
    return $cache = $generated;
}
function seq_footer_ensure_alphabet(): string
{
    return seq_footer_alphabet(true);
}
function seq_footer_regenerate_alphabet(): string
{
    $alphabet = seq_footer_generate_alphabet();
    if (function_exists("meta_set")) {
        meta_set(seq_footer_meta_key(), $alphabet);
    }
    return $alphabet;
}
function seq_footer_encode(
    int $value,
    ?string $alphabet = null,
    int $minDigits = 4,
): string {
    $alphabet = seq_footer_normalize_alphabet(
        (string) ($alphabet ?? seq_footer_alphabet(false)),
    );
    if ($alphabet === "") {
        $alphabet = seq_footer_symbol_pool();
    }
    $chars = str_split($alphabet);
    $base = count($chars);
    $value = max(0, $value);
    $digits = [];
    do {
        $digits[] = $chars[$value % $base];
        $value = intdiv($value, $base);
    } while ($value > 0);
    while (count($digits) < max(1, $minDigits)) {
        $digits[] = $chars[0];
    }
    return implode("", array_reverse($digits));
}
function seq_footer_flush_pending_before_render(): void
{
    return;
}
function seq_footer_max_seq(): int
{
    if (!function_exists("has_cfg") || !has_cfg() || !function_exists("pdo")) {
        return 0;
    }
    try {
        if (
            function_exists("db_table_exists") &&
            !db_table_exists("pi_sequence")
        ) {
            return 0;
        }
        $maxSeq =
            (int) (pdo()
                ->query("SELECT COALESCE(MAX(`Seq`),0) FROM `pi_sequence`")
                ?->fetchColumn() ?:
            0);
        $autoNext = 0;
        try {
            $st = pdo()->prepare(
                "SELECT COALESCE(AUTO_INCREMENT,0) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='pi_sequence' LIMIT 1",
            );
            $st->execute();
            $autoNext = max(0, (int) $st->fetchColumn() - 1);
        } catch (Throwable) {
            $autoNext = 0;
        }
        return max(0, $maxSeq, $autoNext);
    } catch (Throwable $e) {
        error_log("[Prontoo seq footer max] " . $e->getMessage());
        return 0;
    }
}
function seq_footer_code(): string
{
    return seq_footer_encode(
        seq_footer_max_seq(),
        seq_footer_alphabet(false),
        seq_footer_min_digits(),
    );
}
function seq_footer_capacity_for_width(
    int $width,
    ?string $alphabet = null,
): int {
    $alphabet = seq_footer_normalize_alphabet(
        (string) ($alphabet ?? seq_footer_alphabet(false)),
    );
    $base = max(1, strlen($alphabet ?: seq_footer_symbol_pool()));
    $capacity = 1;
    for ($i = 0; $i < max(1, $width); $i++) {
        $capacity *= $base;
    }
    return max(0, $capacity - 1);
}
function seq_footer_html(?array $context = null): string
{
    return "";
}
