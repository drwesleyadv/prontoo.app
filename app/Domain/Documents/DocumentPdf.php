<?php
declare(strict_types=1);
function document_pdf_link(int $docId, string $class = "ghost small"): string
{

    return '<a class="' .
        e($class) .
        '" href="' .
        href("document_pdf", ["id" => $docId]) .
        '">' .
        icon("picture_as_pdf") .
        "<span>Gerar PDF</span></a>";
}
function document_pdf_public_router_dir(): string
{

    $dir = storage_path("pdfs");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($dir);
    }
    return $dir;
}
function document_pdf_storage_dir(): string
{

    $dir = storage_path("pdfs");
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (function_exists("security_storage_deny_file")) {
        security_storage_deny_file($dir);
    }
    return $dir;
}
function document_pdf_dir(int $cid = 0): string
{

    document_pdf_public_router_dir();
    $dir = document_pdf_storage_dir();
    return $dir;
}
function document_pdf_cleanup(?int $cid = null): void
{

    try {
        $ttl = document_pdf_ttl_seconds();
        $dir = document_pdf_dir((int) ($cid ?? 0));
        if (is_dir($dir)) {
            $cut = time() - $ttl;
            foreach (glob($dir . "/*.pdf") ?: [] as $file) {
                if (
                    is_file($file) &&
                    filemtime($file) !== false &&
                    filemtime($file) < $cut
                ) {
                    @unlink($file);
                    @unlink($file . ".json");
                }
            }
            foreach (glob($dir . "/*.tmp") ?: [] as $file) {
                if (
                    is_file($file) &&
                    filemtime($file) !== false &&
                    filemtime($file) < time() - 3600
                ) {
                    @unlink($file);
                }
            }
        }
        if (has_cfg()) {
            $cutDb = gmdate("Y-m-d H:i:s", time() - $ttl);
            if ($cid !== null && (int) $cid > 0) {
                q(
                    "DELETE FROM pi_document_pdfs WHERE clinic_id=? AND created_at < ?",
                    [(int) $cid, $cutDb],
                );
            } else {
                q("DELETE FROM pi_document_pdfs WHERE created_at < ?", [
                    $cutDb,
                ]);
            }
        }
    } catch (Throwable $e) {
        error_log("[Prontoo document_pdf_cleanup] " . $e->getMessage());
    }
}

function document_pdf_cleanup_due(): void
{

    try {
        $flag = storage_path("cache/document_pdf_cleanup.flag");
        $last = is_file($flag) ? (int) filemtime($flag) : 0;
        if ($last > time() - 3600) {
            return;
        }
        if (!is_dir(dirname($flag))) {
            @mkdir(dirname($flag), 0750, true);
        }
        @touch($flag);
        document_pdf_cleanup(null);
    } catch (Throwable $e) {
        error_log("[Prontoo document_pdf_cleanup_due] " . $e->getMessage());
    }
}
function document_pdf_safe_code(array $doc): string
{

    $code = document_identifier_display($doc["document_identifier"] ?? "");
    if ($code === "") {
        $code = "DOCUMENTO" . max(0, (int) ($doc["id"] ?? 0));
    }
    $code = strtoupper((string) preg_replace("/[^A-Z0-9]+/", "", $code));
    return $code !== "" ? $code : "DOCUMENTO";
}
function document_pdf_file_name(
    array $doc,
    ?int $generatedAt = null,
    int $clinicId = 0,
): string {

    $ts = max(0, (int) ($generatedAt ?? time()));
    $docId = max(0, (int) ($doc["id"] ?? 0));
    $scope =
        $clinicId > 0 || $docId > 0
            ? "_C" . max(0, $clinicId) . "D" . $docId
            : "";
    $nonce = strtoupper(bin2hex(random_bytes(6)));
    return document_pdf_safe_code($doc) .
        $scope .
        "_" .
        $ts .
        "_" .
        $nonce .
        ".pdf";
}
function document_pdf_file_name_valid(string $file): bool
{

    return (bool) preg_match(
        '/^[A-Z0-9]{1,80}(?:_C[0-9]{1,10}D[0-9]{1,10})?_[0-9]{9,14}(?:_[A-F0-9]{12})?\.pdf$/',
        $file,
    );
}
function document_pdf_public_path(string $fileName): string
{

    return "/pdfs/" . basename($fileName);
}
function document_pdf_ttl_seconds(): int
{

    return max(
        300,
        (int) (defined("PRONTOO_DOCUMENT_PDF_TTL_SECONDS")
            ? PRONTOO_DOCUMENT_PDF_TTL_SECONDS
            : 86400),
    );
}
function document_pdf_token_secret(): string
{

    $seed =
        app_config_string("app_key", "") ?:
        app_config_string("app_secret", "") ?:
        (string) (getenv("PRONTOO_APP_KEY") ?: getenv("APP_KEY") ?: "");
    if ($seed === "") {
        $seed =
            __DIR__ .
            "|" .
            (defined("PRONTOO_VERSION") ? PRONTOO_VERSION : "prontoo");
    }
    return hash("sha256", $seed . "|document-pdf-token");
}
function document_pdf_token(
    string $fileName,
    int $cid,
    int $uid,
    int $expires,
): string {

    $payload = basename($fileName) . "|" . $cid . "|" . $uid . "|" . $expires;
    return hash_hmac("sha256", $payload, document_pdf_token_secret());
}
function document_pdf_token_valid(
    string $fileName,
    int $cid,
    int $uid,
    string $token,
    int $expires,
): bool {

    if ($expires < time() || $token === "") {
        return false;
    }
    return hash_equals(
        document_pdf_token($fileName, $cid, $uid, $expires),
        $token,
    );
}
function document_pdf_row_timestamp(array $row): int
{

    if (
        !empty($row["generated_unix"]) &&
        preg_match('/^-?\d+$/', (string) $row["generated_unix"])
    ) {
        return (int) $row["generated_unix"];
    }
    foreach (["created_at", "updated_at"] as $k) {
        if (!empty($row[$k])) {
            $ts = strtotime((string) $row[$k]);
            if ($ts) {
                return $ts;
            }
        }
    }
    return time();
}

function document_pdf_expired_row(array $row): bool
{

    return document_pdf_row_timestamp($row) + document_pdf_ttl_seconds() <
        time();
}
function document_pdf_delete_pair(string $fileName, int $cid): void
{

    try {
        $path = document_pdf_absolute_path($fileName);
        if (is_file($path)) {
            @unlink($path);
        }
        if (is_file($path . ".json")) {
            @unlink($path . ".json");
        }
    } catch (Throwable $e) {
        error_log(
            "[Prontoo recoverable " . __FUNCTION__ . "] " . $e->getMessage(),
        );
    }
    try {
        if ($cid > 0 && has_cfg()) {
            q(
                "DELETE FROM pi_document_pdfs WHERE clinic_id=? AND file_name=?",
                [$cid, basename($fileName)],
            );
        }
    } catch (Throwable $e) {
        error_log(
            "[Prontoo recoverable " . __FUNCTION__ . "] " . $e->getMessage(),
        );
    }
}

function document_pdf_public_url(
    string $fileName,
    ?string $token = null,
    ?int $expires = null,
): string {

    $url = (base_path() ?: "") . "/pdfs/" . rawurlencode(basename($fileName));
    $qs = [];
    if ($expires !== null && $expires > 0) {
        $qs["exp"] = (string) $expires;
    }
    if ($token) {
        $qs["t"] = $token;
    }
    return $qs ? $url . "?" . http_build_query($qs) : $url;
}

function document_pdf_absolute_path(string $fileName): string
{

    $fileName = basename($fileName);
    if (!document_pdf_file_name_valid($fileName)) {
        throw new RuntimeException("Nome de PDF inválido.");
    }
    return document_pdf_dir() . "/" . $fileName;
}
function document_pdf_text_width(string $text, float $fontSize = 12.0): float
{

    $len = mb_strlen($text, "UTF-8");
    if ($len <= 0) {
        return 0.0;
    }
    $wide = preg_match_all("/[MWÁÀÂÃÉÊÓÔÕÚÜÇ@#%&]/u", $text, $m);
    $thin = preg_match_all('/[ilI\.,:;\|!\' ]/u', $text, $n);
    return max(0.0, ($len * 0.52 + $wide * 0.16 - $thin * 0.18) * $fontSize);
}
function document_pdf_html_blocks(string $html): array
{

    $html = trim($html);
    if ($html === "") {
        return [
            [
                "text" => "",
                "tag" => "p",
                "align" => "left",
                "indent" => 0,
                "bold" => false,
                "italic" => false,
                "underline" => false,
                "size" => 12.0,
                "space_after" => 6.0,
            ],
        ];
    }
    $html = preg_replace("/<\s*br\s*\/?>/i", "\n", $html);
    $html = preg_replace(
        "/<\s*\/\s*(p|div|h2|h3|li)\s*>/i",
        '</$1>' . "\n",
        $html,
    );
    $blocks = [];
    if (
        preg_match_all(
            '/<\s*(h2|h3|p|div|li)\b([^>]*)>(.*?)<\s*\/\s*\1\s*>/is',
            $html,
            $ms,
            PREG_SET_ORDER,
        )
    ) {
        foreach ($ms as $m) {
            $tag = strtolower($m[1] ?? "p");
            $attrs = $m[2] ?? "";
            $inner = $m[3] ?? "";
            $align = "left";
            $indent = 0;
            if (preg_match("/ta-(left|center|right|justify)/i", $attrs, $a)) {
                $align = strtolower($a[1]);
            } elseif (
                preg_match(
                    "/text-align\s*:\s*(left|center|right|justify)/i",
                    $attrs,
                    $a,
                )
            ) {
                $align = strtolower($a[1]);
            }
            if (preg_match("/indent-([1-3])/i", $attrs, $i)) {
                $indent = (int) $i[1];
            }
            $text = html_entity_decode(
                strip_tags($inner),
                ENT_QUOTES | ENT_SUBSTITUTE,
                "UTF-8",
            );
            $text = mb_trim((string) preg_replace('/[ \t\r]+/u', " ", $text));
            if ($text === "") {
                continue;
            }
            $isList = $tag === "li";
            if ($isList && !preg_match("/^[•\-–]/u", $text)) {
                $text = "• " . $text;
            }
            $size = $tag === "h2" ? 14.4 : ($tag === "h3" ? 12.8 : 12.0);
            $space = $tag === "h2" ? 8.0 : ($tag === "h3" ? 7.0 : 6.0);
            $blocks[] = [
                "text" => $text,
                "tag" => $tag,
                "align" => $align,
                "indent" => $indent + ($isList ? 1 : 0),
                "bold" =>
                    $tag === "h2" ||
                    $tag === "h3" ||
                    (bool) preg_match("/<\s*(b|strong)\b/i", $inner),
                "italic" => (bool) preg_match("/<\s*(i|em)\b/i", $inner),
                "underline" => (bool) preg_match("/<\s*u\b/i", $inner),
                "size" => $size,
                "space_after" => $space,
            ];
        }
    }
    if (!$blocks) {
        $plain = html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8",
        );
        $plain = mb_trim((string) preg_replace('/[ \t\r]+/u', " ", $plain));
        foreach (preg_split('/\n{2,}/u', $plain) ?: [] as $b) {
            $b = trim($b);
            if ($b !== "") {
                $blocks[] = [
                    "text" => $b,
                    "tag" => "p",
                    "align" => "left",
                    "indent" => 0,
                    "bold" => false,
                    "italic" => false,
                    "underline" => false,
                    "size" => 12.0,
                    "space_after" => 6.0,
                ];
            }
        }
    }
    return $blocks ?: [
            [
                "text" => "",
                "tag" => "p",
                "align" => "left",
                "indent" => 0,
                "bold" => false,
                "italic" => false,
                "underline" => false,
                "size" => 12.0,
                "space_after" => 6.0,
            ],
        ];
}
function document_pdf_wrap_text(
    string $text,
    float $maxWidth,
    float $fontSize = 12.0,
): array {

    $lines = [];
    $paras = preg_split('/\n+/u', $text) ?: [$text];
    foreach ($paras as $para) {
        $para = trim($para);
        if ($para === "") {
            $lines[] = "";
            continue;
        }
        $words = preg_split("/\s+/u", $para) ?: [$para];
        $line = "";
        foreach ($words as $word) {
            if ($word === "") {
                continue;
            }
            $candidate = $line === "" ? $word : $line . " " . $word;
            if (document_pdf_text_width($candidate, $fontSize) <= $maxWidth) {
                $line = $candidate;
                continue;
            }
            if ($line !== "") {
                $lines[] = $line;
            }
            if (document_pdf_text_width($word, $fontSize) <= $maxWidth) {
                $line = $word;
                continue;
            }
            $piece = "";
            $chars = preg_split("//u", $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($chars as $ch) {
                $cand = $piece . $ch;
                if (
                    $piece !== "" &&
                    document_pdf_text_width($cand, $fontSize) > $maxWidth
                ) {
                    $lines[] = $piece;
                    $piece = $ch;
                } else {
                    $piece = $cand;
                }
            }
            $line = $piece;
        }
        if ($line !== "") {
            $lines[] = $line;
        }
    }
    return $lines;
}
function document_pdf_escape(string $text): string
{

    $bytes = @iconv("UTF-8", "Windows-1252//TRANSLIT//IGNORE", $text);
    if ($bytes === false) {
        $bytes = preg_replace('/[^\x20-\x7E]/', "?", $text) ?? $text;
    }
    return str_replace(
        ["\\", "(", ")", "\r"],
        ["\\", "\\(", "\\)", ""],
        $bytes,
    );
}
function document_pdf_identifier_footer_stream(
    string $identifier,
    float $pageW,
    float $marginLeft,
    float $marginRight,
    float $marginBottom,
): string {

    $id = document_identifier_display($identifier);
    if ($id === "") {
        return "";
    }
    $fontSize = 7.25;
    $textW = document_pdf_text_width($id, $fontSize);
    $x = max($marginLeft, $pageW - $marginRight - $textW);
    $y = max(18.0, $marginBottom / 2.0 - 1.0);
    return sprintf(
        "q BT /F1 %.3F Tf 0.22 0.26 0.23 rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET Q
",
        $fontSize,
        $x,
        $y,
        document_pdf_escape($id),
    );
}
function document_pdf_build_simple(string $html, array $meta = []): string
{

    $pageW = 595.276;
    $pageH = 841.89;
    $marginTop = 85.039;
    $marginRight = 56.693;
    $marginBottom = 85.039;
    $marginLeft = 56.693;
    $fontSize = 12.0;
    $lineHeight = 18.0;
    $footerIdentifier = document_identifier_display(
        $meta["document_identifier"] ?? null,
    );
    $maxW = $pageW - $marginLeft - $marginRight;
    $yStart = $pageH - $marginTop;
    $yMin = $marginBottom;
    $pages = [];
    $cur = [];
    $y = $yStart;
    foreach (document_pdf_html_blocks($html) as $block) {
        $align = (string) ($block["align"] ?? "left");
        $indent = (int) ($block["indent"] ?? 0);
        $blockFontSize = (float) ($block["size"] ?? $fontSize);
        if ($blockFontSize <= 0) {
            $blockFontSize = $fontSize;
        }
        $blockLineHeight = max($blockFontSize * 1.45, 16.0);
        $blockSpace = max(0.0, (float) ($block["space_after"] ?? 6.0));
        $indentPt = max(0, min(4, $indent)) * 28.346;
        $available = max(72.0, $maxW - $indentPt);
        $lines = document_pdf_wrap_text(
            (string) ($block["text"] ?? ""),
            $available,
            $blockFontSize,
        );
        foreach ($lines as $line) {
            if ($y < $yMin) {
                $pages[] = $cur;
                $cur = [];
                $y = $yStart;
            }
            $w = document_pdf_text_width($line, $blockFontSize);
            $x = $marginLeft + $indentPt;
            if ($align === "center") {
                $x = $marginLeft + $indentPt + max(0, ($available - $w) / 2);
            } elseif ($align === "right") {
                $x = $marginLeft + $indentPt + max(0, $available - $w);
            }
            $cur[] = [
                "x" => $x,
                "y" => $y,
                "text" => $line,
                "bold" => !empty($block["bold"]),
                "italic" => !empty($block["italic"]),
                "underline" => !empty($block["underline"]),
                "w" => $w,
                "size" => $blockFontSize,
            ];
            $y -= $blockLineHeight;
        }
        $y -= $blockSpace;
    }
    if ($cur || !$pages) {
        $pages[] = $cur;
    }
    $objects = [
        1 => "<< /Type /Catalog /Pages 2 0 R >>",
        2 => "",
        3 => "<< /Type /Font /Subtype /Type1 /BaseFont /FiraSans-Regular /Encoding /WinAnsiEncoding >>",
        4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
        5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
        6 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>",
        7 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-BoldOblique /Encoding /WinAnsiEncoding >>",
    ];
    $kids = [];
    foreach ($pages as $pageLines) {
        $stream = "";
        if ($footerIdentifier !== "") {
            $stream .= document_pdf_identifier_footer_stream(
                $footerIdentifier,
                $pageW,
                $marginLeft,
                $marginRight,
                $marginBottom,
            );
        }
        foreach ($pageLines as $ln) {
            $font = "/F1";
            if (!empty($ln["bold"]) && !empty($ln["italic"])) {
                $font = "/F5";
            } elseif (!empty($ln["bold"])) {
                $font = "/F3";
            } elseif (!empty($ln["italic"])) {
                $font = "/F4";
            }
            $stream .= sprintf(
                "BT %s %.2F Tf 0 0 0 rg 0 0 0 RG 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                $font,
                (float) ($ln["size"] ?? $fontSize),
                $ln["x"],
                $ln["y"],
                document_pdf_escape($ln["text"]),
            );
            if (!empty($ln["underline"])) {
                $stream .= sprintf(
                    "%.2F %.2F m %.2F %.2F l 0.60 w S\n",
                    $ln["x"],
                    $ln["y"] - 2.0,
                    $ln["x"] + ($ln["w"] ?? 0),
                    $ln["y"] - 2.0,
                );
            }
        }
        $contentId = count($objects) + 1;
        $objects[$contentId] =
            "<< /Length " .
            strlen($stream) .
            " >>" .
            "\nstream\n" .
            $stream .
            "endstream";
        $pageId = count($objects) + 1;
        $objects[$pageId] =
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " .
            sprintf("%.3F %.3F", $pageW, $pageH) .
            "] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R /F4 6 0 R /F5 7 0 R >> >> /Contents " .
            $contentId .
            " 0 R >>";
        $kids[] = $pageId . " 0 R";
    }
    $objects[2] =
        "<< /Type /Pages /Kids [" .
        implode(" ", $kids) .
        "] /Count " .
        count($kids) .
        " >>";
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0 => 0];
    foreach ($objects as $id => $obj) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $obj . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .=
        "trailer\n<< /Size " .
        (count($objects) + 1) .
        " /Root 1 0 R >>\nstartxref\n" .
        $xref .
        "\n%%EOF\n";
    return $pdf;
}
function document_pdf_register_file(
    array $c,
    array $doc,
    string $path,
    string $name,
    int $generatedAt,
): void {

    try {
        $cid = (int) ($c["clinic_id"] ?? 0);
        $hash = is_file($path) ? (hash_file("sha256", $path) ?: "") : "";
        if ($hash === "") {
            $hash = str_repeat("0", 64);
        }
        $size = is_file($path) ? max(0, (int) filesize($path)) : 0;
        q(
            "INSERT INTO pi_document_pdfs (clinic_id,document_id,generated_by,document_identifier,generated_unix,file_name,file_path,file_sha256,file_size,created_at) VALUES (?,?,?,?,?,?,?,?,?,FROM_UNIXTIME(?)) ON DUPLICATE KEY UPDATE clinic_id=VALUES(clinic_id), document_id=VALUES(document_id), generated_by=VALUES(generated_by), document_identifier=VALUES(document_identifier), generated_unix=VALUES(generated_unix), file_path=VALUES(file_path), file_sha256=VALUES(file_sha256), file_size=VALUES(file_size), created_at=VALUES(created_at)",
            [
                $cid,
                (int) $doc["id"],
                (int) ($c["user"]["id"] ?? 0),
                document_identifier_display($doc["document_identifier"] ?? ""),
                $generatedAt,
                $name,
                document_pdf_public_path($name),
                $hash,
                $size,
                $generatedAt,
            ],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo document_pdf_register_file] " . $e->getMessage());
    }
}
function document_pdf_create_file(array $c, array $doc): string
{

    $cid = (int) ($c["clinic_id"] ?? 0);
    document_pdf_cleanup($cid);
    $dir = document_pdf_dir($cid);
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException(
            "Não foi possível preparar o armazenamento do PDF.",
        );
    }
    $generatedAt = time();
    $name = "";
    $path = "";
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $candidate = document_pdf_file_name($doc, $generatedAt, $cid);
        $candidatePath = $dir . "/" . $candidate;
        if (!is_file($candidatePath) && !document_pdf_catalog_row($candidate)) {
            $name = $candidate;
            $path = $candidatePath;
            break;
        }
    }
    if ($name === "" || $path === "") {
        throw new RuntimeException(
            "Não foi possível reservar um nome único para o PDF.",
        );
    }
    $pdf = document_pdf_build_simple(
        document_body_to_html((string) ($doc["content"] ?? "")),
        [
            "title" => $doc["title"] ?? "Documento",
            "document_identifier" => $doc["document_identifier"] ?? null,
        ],
    );
    $tmp = $path . "." . bin2hex(random_bytes(4)) . ".tmp";
    if (file_put_contents($tmp, $pdf, LOCK_EX) === false) {
        throw new RuntimeException("Não foi possível gerar o PDF.");
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Não foi possível finalizar o PDF gerado.");
    }
    @chmod($path, 0640);
    $hash = hash_file("sha256", $path) ?: "";
    $meta = [
        "clinic_id" => $cid,
        "document_id" => (int) $doc["id"],
        "generated_by" => (int) ($c["user"]["id"] ?? 0),
        "generated_at" => date("Y-m-d H:i:s", $generatedAt),
        "generated_unix" => $generatedAt,
        "path" => document_pdf_public_path($name),
        "download_name" => $name,
        "file_sha256" => $hash,
        "file_size" => is_file($path) ? (int) filesize($path) : 0,
        "font_family" => "Fira Sans",
        "identifier_font_family" => "Science Gothic",
        "document_identifier" => document_identifier_display(
            $doc["document_identifier"] ?? "",
        ),
    ];
    @file_put_contents(
        $path . ".json",
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX,
    );
    @chmod($path . ".json", 0640);
    document_pdf_register_file($c, $doc, $path, $name, $generatedAt);
    return $path;
}
function document_pdf_catalog_row(string $fileName): ?array
{

    if (!document_pdf_file_name_valid($fileName)) {
        return null;
    }
    try {
        $r = one(
            "SELECT clinic_id,document_id,file_sha256,generated_unix,created_at FROM pi_document_pdfs WHERE file_name=? LIMIT 1",
            [$fileName],
        );
        if ($r) {
            return $r;
        }
    } catch (Throwable $e) {
        error_log("[Prontoo document_pdf_catalog_row] " . $e->getMessage());
    }
    $metaPath = document_pdf_dir() . "/" . $fileName . ".json";
    if (is_file($metaPath)) {
        $raw = json_decode((string) file_get_contents($metaPath), true);
        if (is_array($raw)) {
            return [
                "clinic_id" => (int) ($raw["clinic_id"] ?? 0),
                "document_id" => (int) ($raw["document_id"] ?? 0),
                "generated_by" => (int) ($raw["generated_by"] ?? 0),
                "document_identifier" =>
                    (string) ($raw["document_identifier"] ?? ""),
                "generated_unix" => (int) ($raw["generated_unix"] ?? 0),
                "file_name" => $fileName,
                "file_path" => document_pdf_public_path($fileName),
                "file_sha256" => (string) ($raw["file_sha256"] ?? ""),
                "file_size" => (int) ($raw["file_size"] ?? 0),
                "created_at" => (string) ($raw["generated_at"] ?? ""),
            ];
        }
    }
    return null;
}
function document_pdf_send_file(string $path, string $downloadName): void
{

    if (!is_file($path)) {
        throw new RuntimeException("PDF não encontrado.");
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/pdf");
        header(
            'Content-Disposition: attachment; filename="' .
                str_replace('"', "", basename($downloadName)) .
                '"',
        );
        header("Content-Length: " . filesize($path));
        header("Cache-Control: private, no-store, max-age=0");
        header("X-Content-Type-Options: nosniff");
    }
    readfile($path);
    exit();
}
function page_document_pdf_file(): void
{

    $c = need_login();
    if (($c["scope"] ?? "") !== "clinic") {
        throw new ProntooHttpError(
            403,
            "PDF disponível apenas no contexto do consultório.",
        );
    }
    $cid = (int) ($c["clinic_id"] ?? 0);
    $uid = (int) ($c["user"]["id"] ?? ($c["user_id"] ?? 0));
    $file = basename((string) ($_GET["file"] ?? ""));
    if (!document_pdf_file_name_valid($file)) {
        throw new ProntooHttpError(404, "PDF não encontrado.");
    }
    $path = document_pdf_absolute_path($file);
    if (!is_file($path)) {
        throw new ProntooHttpError(404, "PDF não encontrado.");
    }
    $row = document_pdf_catalog_row($file);
    if (!$row || (int) ($row["clinic_id"] ?? 0) !== $cid) {
        throw new ProntooHttpError(
            404,
            "PDF não encontrado para esta credencial.",
        );
    }
    if (document_pdf_expired_row($row)) {
        document_pdf_delete_pair($file, $cid);
        throw new ProntooHttpError(
            410,
            "PDF expirado. Gere uma nova pré-visualização.",
        );
    }
    $expires = (int) ($_GET["exp"] ?? 0);
    $token = (string) ($_GET["t"] ?? "");
    if (!document_pdf_token_valid($file, $cid, $uid, $token, $expires)) {
        throw new ProntooHttpError(
            403,
            "Link de PDF expirado ou inválido. Gere uma nova pré-visualização.",
        );
    }
    $doc = fetch_document_for_current_user(
        $c,
        (int) ($row["document_id"] ?? 0),
    );
    if (!$doc) {
        throw new ProntooHttpError(
            404,
            "Documento não encontrado para esta credencial.",
        );
    }
    if ((string) ($doc["document_status"] ?? "") !== "emitido") {
        throw new ProntooHttpError(
            403,
            "PDF disponível apenas para documentos já emitidos.",
        );
    }
    $types = document_type_options();
    $actualHash = hash_file("sha256", $path) ?: "";
    $knownHash = (string) ($row["file_sha256"] ?? "");
    if ($knownHash !== "" && !hash_equals($knownHash, $actualHash)) {
        throw new ProntooHttpError(
            409,
            "O PDF gerado não confere com o registro de integridade.",
        );
    }
    audit("documento_pdf_baixado", "documento", (int) $doc["id"], [
        "titulo" => $doc["title"] ?? "",
        "document_type" => (string) ($doc["type_key"] ?? ""),
        "document_type_label" =>
            $types[(string) ($doc["type_key"] ?? "")] ?? "Documento",
        "patient_link_id" => (int) ($doc["patient_link_id"] ?? 0),
        "patient_name" => $doc["patient_name"] ?? "",
        "arquivo" => document_pdf_public_path($file),
        "generated_unix" => (int) ($row["generated_unix"] ?? 0),
        "file_sha256" => $actualHash,
        "audit_body" =>
            "PDF servido por /pdfs/ após validação da sessão, consultório, permissão documental, token temporário, expiração e hash do arquivo.",
    ]);
    document_pdf_send_file($path, $file);
}

function page_document_pdf(): void
{

    $c = need_login();
    if (($c["scope"] ?? "") !== "clinic") {
        throw new ProntooHttpError(
            403,
            "Documento disponível apenas no contexto do consultório.",
        );
    }
    $doc = fetch_document_for_current_user($c, (int) ($_GET["id"] ?? 0));
    if (!$doc) {
        http_response_code(404);
        page(
            "PDF do documento",
            '<div class="empty">Documento não encontrado para esta credencial.</div>',
        );
        return;
    }
    if ((string) ($doc["document_status"] ?? "") !== "emitido") {
        throw new ProntooHttpError(
            403,
            "PDF disponível apenas para documentos já emitidos.",
        );
    }
    if (!function_exists("document_print_document_shell_html")) {
        throw new RuntimeException(
            "Motor de impressão documental indisponível.",
        );
    }
    $types = document_type_options();
    audit("documento_pdf_preparado", "documento", (int) $doc["id"], [
        "titulo" => $doc["title"] ?? "",
        "document_type" => (string) ($doc["type_key"] ?? ""),
        "document_type_label" =>
            $types[(string) ($doc["type_key"] ?? "")] ?? "Documento",
        "patient_link_id" => (int) ($doc["patient_link_id"] ?? 0),
        "patient_name" => $doc["patient_name"] ?? "",
        "audit_body" =>
            "PDF preparado a partir do mesmo HTML/CSS da pré-visualização. O navegador abre o modo de impressão para salvar em PDF ou imprimir com layout idêntico ao conferido.",
    ]);
    if (!headers_sent()) {
        header("Content-Type: text/html; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
    }
    echo document_print_document_shell_html($doc, true, "pdf");
    exit();
}
