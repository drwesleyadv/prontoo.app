<?php
declare(strict_types=1);
namespace Prontoo\Core\Integrity;
final class AuditChain
{
    private const CHAIN_NAME = "global";
    private const POLICY_VERSION = "audit-lite-v2-maestro";
    private function __construct() {}
    public static function build(array $row, string $secret): array
    {
        $previous = (string) ($row["previous_hash"] ?? "");
        $base = function_exists("audit_integrity_base")
            ? \audit_integrity_base($row)
            : json_encode($row, JSON_UNESCAPED_UNICODE);
        $integrity = hash_hmac("sha256", (string) $base, $secret);
        $proofJson = json_encode(
            [
                "policy" => self::POLICY_VERSION,
                "route" => function_exists("route") ? \route() : "",
                "method" => $_SERVER["REQUEST_METHOD"] ?? "",
                "scope" => $_SESSION["scope"] ?? "",
                "clinic_id" => $_SESSION["clinic_id"] ?? null,
                "role" => $_SESSION["role_code"] ?? "",
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if ($proofJson === false) {
            $proofJson = "{}";
        }
        $proofHash = hash_hmac("sha256", $proofJson, $secret);
        $chain = hash_hmac(
            "sha256",
            $previous . "|" . $integrity . "|" . $proofHash,
            $secret,
        );
        return [
            "previous_hash" => $previous,
            "integrity_hash" => $integrity,
            "proof_hash" => $proofHash,
            "chain_hash" => $chain,
            "proof_json" => $proofJson,
            "policy_version" => self::POLICY_VERSION,
        ];
    }
    public static function verifyRow(array $row, string $secret): bool
    {
        $integrity = trim((string) ($row["integrity_hash"] ?? ""));
        if ($integrity === "") {
            return true;
        }
        $base = function_exists("audit_integrity_base")
            ? \audit_integrity_base($row)
            : json_encode($row, JSON_UNESCAPED_UNICODE);
        if (
            !hash_equals(
                $integrity,
                hash_hmac("sha256", (string) $base, $secret),
            )
        ) {
            return false;
        }
        $chain = trim((string) ($row["chain_hash"] ?? ""));
        if ($chain === "") {
            return true;
        }
        $previous = (string) ($row["previous_hash"] ?? "");
        $proof = (string) ($row["proof_hash"] ?? "");
        return hash_equals(
            $chain,
            hash_hmac(
                "sha256",
                $previous . "|" . $integrity . "|" . $proof,
                $secret,
            ),
        );
    }
    private static function ensureHead(): void
    {
        return;
    }
    private static function currentHeadForUpdate(): string
    {
        return "";
    }
    private static function advanceHead(string $hash): void
    {
        return;
    }
}
