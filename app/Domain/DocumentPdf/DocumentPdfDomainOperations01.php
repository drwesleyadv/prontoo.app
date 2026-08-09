<?php
declare(strict_types=1);

namespace Prontoo\Domain\DocumentPdf;

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

final class DocumentPdfDomainOperations01
{
    private function __construct()
    {
    }

    public static function document_pdf_safe_code(array $doc): string
    
    {
    
        $code = \Prontoo\Domain\Documents\DocumentIdentifierPolicy::document_identifier_display($doc["document_identifier"] ?? "");
        if ($code === "") {
            $code = "DOCUMENTO" . max(0, (int) ($doc["id"] ?? 0));
        }
        $code = strtoupper((string) preg_replace("/[^A-Z0-9]+/", "", $code));
        return $code !== "" ? $code : "DOCUMENTO";
    
    }

    public static function document_pdf_file_name(
        array $doc,
        ?int $generatedAt = null,
        int $clinicId = 0,
    ): string 
    {
    
        $ts = max(0, (int) ($generatedAt ?? time()));
        $docId = max(0, (int) ($doc["id"] ?? 0));
        $scope =
            $clinicId > 0 || $docId > 0
                ? "_C" . max(0, $clinicId) . "D" . $docId
                : "";
        $nonce = strtoupper(bin2hex(random_bytes(6)));
        return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_safe_code($doc) .
            $scope .
            "_" .
            $ts .
            "_" .
            $nonce .
            ".pdf";
    
    }

    public static function document_pdf_file_name_valid(string $file): bool
    
    {
    
        return (bool) preg_match(
            '/^[A-Z0-9]{1,80}(?:_C[0-9]{1,10}D[0-9]{1,10})?_[0-9]{9,14}(?:_[A-F0-9]{12})?\.pdf$/',
            $file,
        );
    
    }

    public static function document_pdf_public_path(string $fileName): string
    
    {
    
        return "/pdfs/" . basename($fileName);
    
    }

    public static function document_pdf_ttl_seconds(): int
    
    {
    
        return max(
            300,
            (int) (defined("PRONTOO_DOCUMENT_PDF_TTL_SECONDS")
                ? PRONTOO_DOCUMENT_PDF_TTL_SECONDS
                : 86400),
        );
    
    }

    public static function document_pdf_row_timestamp(array $row): int
    
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

    public static function document_pdf_expired_row(array $row): bool
    
    {
    
        return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_row_timestamp($row) + \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_ttl_seconds() <
            time();
    
    }

    public static function document_pdf_text_width(string $text, float $fontSize = 12.0): float
    
    {
    
        $len = mb_strlen($text, "UTF-8");
        if ($len <= 0) {
            return 0.0;
        }
        $wide = preg_match_all("/[MWÁÀÂÃÉÊÓÔÕÚÜÇ@#%&]/u", $text, $m);
        $thin = preg_match_all('/[ilI\.,:;\|!\' ]/u', $text, $n);
        return max(0.0, ($len * 0.52 + $wide * 0.16 - $thin * 0.18) * $fontSize);
    
    }

    public static function document_pdf_html_blocks(string $html): array
    
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

    public static function document_pdf_wrap_text(
        string $text,
        float $maxWidth,
        float $fontSize = 12.0,
    ): array 
    {
    
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
                if (\Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_text_width($candidate, $fontSize) <= $maxWidth) {
                    $line = $candidate;
                    continue;
                }
                if ($line !== "") {
                    $lines[] = $line;
                }
                if (\Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_text_width($word, $fontSize) <= $maxWidth) {
                    $line = $word;
                    continue;
                }
                $piece = "";
                $chars = preg_split("//u", $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($chars as $ch) {
                    $cand = $piece . $ch;
                    if (
                        $piece !== "" &&
                        \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_text_width($cand, $fontSize) > $maxWidth
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

    public static function document_pdf_escape(string $text): string
    
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

    public static function document_pdf_identifier_footer_stream(
        string $identifier,
        float $pageW,
        float $marginLeft,
        float $marginRight,
        float $marginBottom,
    ): string 
    {
    
        $id = \Prontoo\Domain\Documents\DocumentIdentifierPolicy::document_identifier_display($identifier);
        if ($id === "") {
            return "";
        }
        $fontSize = 7.25;
        $textW = \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_text_width($id, $fontSize);
        $x = max($marginLeft, $pageW - $marginRight - $textW);
        $y = max(18.0, $marginBottom / 2.0 - 1.0);
        return sprintf(
            "q BT /F1 %.3F Tf 0.22 0.26 0.23 rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET Q
    ",
            $fontSize,
            $x,
            $y,
            \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_escape($id),
        );
    
    }

    public static function document_pdf_build_simple(string $html, array $meta = []): string
    
    {
    
        $pageW = 595.276;
        $pageH = 841.89;
        $marginTop = 85.039;
        $marginRight = 56.693;
        $marginBottom = 85.039;
        $marginLeft = 56.693;
        $fontSize = 12.0;
        $lineHeight = 18.0;
        $footerIdentifier = \Prontoo\Domain\Documents\DocumentIdentifierPolicy::document_identifier_display(
            $meta["document_identifier"] ?? null,
        );
        $maxW = $pageW - $marginLeft - $marginRight;
        $yStart = $pageH - $marginTop;
        $yMin = $marginBottom;
        $pages = [];
        $cur = [];
        $y = $yStart;
        foreach (\Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_html_blocks($html) as $block) {
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
            $lines = \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_wrap_text(
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
                $w = \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_text_width($line, $blockFontSize);
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
                $stream .= \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_identifier_footer_stream(
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
                    \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_escape($ln["text"]),
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
}
