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

final class DocumentHtmlPolicy
{
    private function __construct()
    {
    }

    public static function document_sanitize_html(string $html): string
    
    {
    
        $html = trim($html);
        if ($html === "") {
            return "";
        }
        $html = preg_replace("/<!--.*?-->/s", "", $html);
        $html = preg_replace(
            "/<\s*(script|style|iframe|object|embed|link|meta|form|input|button)[^>]*>.*?<\s*\/\s*\1\s*>/is",
            "",
            $html,
        );
        $html = preg_replace(
            "/<\s*(script|style|iframe|object|embed|link|meta|form|input|button)[^>]*\/?>/is",
            "",
            $html,
        );
        $blockClass = function (string $attrs): string {
    
            $classes = [];
            if (
                preg_match(
                    "/text-align\s*:\s*(left|center|right|justify)/i",
                    $attrs,
                    $a,
                ) ||
                preg_match(
                    "/\balign\s*=\s*[\"']?(left|center|right|justify)/i",
                    $attrs,
                    $a,
                ) ||
                preg_match(
                    "/class\s*=\s*[\"'][^\"']*ta-(left|center|right|justify)/i",
                    $attrs,
                    $a,
                )
            ) {
                $classes[] = "ta-" . strtolower($a[1]);
            }
            $indent = 0;
            if (
                preg_match(
                    "/(?:class\s*=\s*[\"'][^\"']*(?:doc-)?indent-|ql-indent-)([1-3])/i",
                    $attrs,
                    $i,
                )
            ) {
                $indent = (int) $i[1];
            } elseif (
                preg_match(
                    "/margin-left\s*:\s*([0-9.]+)\s*(px|pt|cm|rem|em)?/i",
                    $attrs,
                    $i,
                )
            ) {
                $n = (float) $i[1];
                $u = strtolower($i[2] ?? "px");
                $px =
                    $u === "cm"
                        ? $n * 37.8
                        : ($u === "pt"
                            ? $n * 1.333
                            : ($u === "rem" || $u === "em"
                                ? $n * 16
                                : $n));
                $indent = max(1, min(3, (int) ceil($px / 36)));
            }
            if ($indent > 0) {
                $classes[] = "indent-" . $indent;
            }
            return $classes
                ? ' class="' . implode(" ", array_unique($classes)) . '"'
                : "";
        };
        $html = preg_replace(
            "/<\s*center\b[^>]*>/i",
            '<div class="ta-center">',
            $html,
        );
        $html = preg_replace("/<\s*\/\s*center\s*>/i", "</div>", $html);
        $html = preg_replace_callback(
            "/<\s*(p|div|blockquote|h2|h3)\b([^>]*)>/i",
            function ($m) use ($blockClass) {
    
                $tag =
                    strtolower($m[1]) === "blockquote" ? "div" : strtolower($m[1]);
                return "<" . $tag . $blockClass($m[2] ?? "") . ">";
            },
            $html,
        );
        $html = preg_replace("/<\s*\/\s*blockquote\s*>/i", "</div>", $html);
        $html = strip_tags(
            $html,
            "<b><strong><i><em><u><p><br><div><ul><ol><li><h2><h3>",
        );
    
        $html = preg_replace_callback(
            "/<\s*(\/?)\s*(b|strong|i|em|u|p|br|div|ul|ol|li|h2|h3)\b([^>]*)>/i",
            function ($m) {
    
                $closing = ($m[1] ?? "") === "/";
                $tag = strtolower((string) ($m[2] ?? ""));
                if ($closing) {
                    return $tag === "br" ? "" : "</" . $tag . ">";
                }
                if ($tag === "br") {
                    return "<br>";
                }
                $attrs = $m[3] ?? "";
                $classes = [];
                if (
                    in_array($tag, ["p", "div", "h2", "h3"], true) &&
                    preg_match_all(
                        "/\b(ta-(?:left|center|right|justify)|indent-[1-3])\b/i",
                        $attrs,
                        $mm,
                    )
                ) {
                    foreach ($mm[1] as $c) {
                        $classes[] = strtolower($c);
                    }
                }
                return "<" .
                    $tag .
                    ($classes
                        ? ' class="' . implode(" ", array_unique($classes)) . '"'
                        : "") .
                    ">";
            },
            $html,
        );
        $html = preg_replace("/(?:<br>\s*){4,}/i", "<br><br><br>", $html);
        return trim($html);
    
    }

    public static function document_body_is_empty(string $html): bool
    
    {
    
        return trim(
            preg_replace(
                "/\s+/",
                " ",
                strip_tags(str_replace(["&nbsp;", "<br>"], [" ", " "], $html)),
            ),
        ) === "";
    
    }

    public static function document_context_json_decode(mixed $raw): array
    
    {
    
        if (is_array($raw)) {
            return $raw;
        }
        $raw = mb_trim((string) ($raw ?? ""));
        if ($raw === "") {
            return [];
        }
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    
    }
}
