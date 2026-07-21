<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;

final class Canonical
{
    public const POLICY_VERSION = "layer3-php-layered-invariants-v1";

    private function __construct() {}

    public static function value(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map([self::class, "value"], $value);
            }
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[(string) $key] = self::value($item);
            }
            ksort($normalized, SORT_STRING);
            return $normalized;
        }
        if (is_object($value)) {
            return self::value(get_object_vars($value));
        }
        if (is_float($value)) {
            if (is_nan($value) || is_infinite($value)) {
                return (string) $value;
            }
            return (float) sprintf("%.12F", $value);
        }
        if (is_resource($value)) {
            return get_resource_type($value);
        }
        return $value;
    }

    public static function json(mixed $value): string
    {
        try {
            return json_encode(
                self::value($value),
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRESERVE_ZERO_FRACTION |
                    JSON_THROW_ON_ERROR,
            );
        } catch (\Throwable) {
            return "{}";
        }
    }

    public static function hash(string $domain, mixed $value): string
    {
        $domain = preg_replace("/[^a-z0-9_.:-]/i", "_", trim($domain)) ?: "proof";
        return hash(
            "sha256",
            self::POLICY_VERSION . "|" . $domain . "|" . self::json($value),
        );
    }

    public static function token(string $value, string $fallback = "unknown"): string
    {
        return preg_replace("/[^a-z0-9_\-]/i", "", trim($value)) ?: $fallback;
    }
}
