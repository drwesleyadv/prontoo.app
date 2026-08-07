<?php
declare(strict_types=1);
namespace Prontoo\Infrastructure\Audit;
final class AuditChain
{
    private const CHAIN_NAME = "global";
    private const META_KEY = "audit_chain_head:global";
    private const POLICY_VERSION = "audit-chain-v3-linked";
    private function __construct() {

    }
    public static function build(
        array $row,
        string $secret,
        ?array $proofContext = null,
    ): array
    {

        $previous = self::currentHeadForUpdate();
        $proof = self::buildFromPrevious(
            $row,
            $secret,
            $previous,
            $proofContext ?? [],
        );
        self::advanceHead((string) $proof["chain_hash"]);
        return $proof;
    }
    public static function buildFromPrevious(
        array $row,
        string $secret,
        string $previous,
        array $proofContext = [],
    ): array {

        $previous = trim($previous);
        if ($previous !== "" && preg_match('/^[a-f0-9]{64}$/', $previous) !== 1) {
            throw new \InvalidArgumentException("Hash anterior de auditoria inválido.");
        }
        $base = function_exists("audit_integrity_base")
            ? \audit_integrity_base($row)
            : json_encode($row, JSON_UNESCAPED_UNICODE);
        $integrity = hash_hmac("sha256", (string) $base, $secret);
        $proofContext["policy"] = self::POLICY_VERSION;
        $proofJson = json_encode(
            self::canonicalize($proofContext),
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

        $integrity = strtolower(mb_trim((string) ($row["integrity_hash"] ?? "")));
        $previous = strtolower(mb_trim((string) ($row["previous_hash"] ?? "")));
        $proof = strtolower(mb_trim((string) ($row["proof_hash"] ?? "")));
        $chain = strtolower(mb_trim((string) ($row["chain_hash"] ?? "")));
        $proofJson = (string) ($row["proof_json"] ?? "");
        $policy = mb_trim((string) ($row["policy_version"] ?? ""));
        foreach ([$integrity, $proof, $chain] as $requiredHash) {
            if (preg_match('/^[a-f0-9]{64}$/', $requiredHash) !== 1) {
                return false;
            }
        }
        if (
            $previous !== "" &&
            preg_match('/^[a-f0-9]{64}$/', $previous) !== 1
        ) {
            return false;
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
        if (
            $proofJson === "" ||
            !hash_equals(
                $proof,
                hash_hmac("sha256", $proofJson, $secret),
            )
        ) {
            return false;
        }
        $proofContext = json_decode($proofJson, true);
        if (
            !is_array($proofContext) ||
            $policy === "" ||
            !hash_equals(
                $policy,
                (string) ($proofContext["policy"] ?? ""),
            )
        ) {
            return false;
        }
        return hash_equals(
            $chain,
            hash_hmac(
                "sha256",
                $previous . "|" . $integrity . "|" . $proof,
                $secret,
            ),
        );
    }
    public static function verifySequence(array $rows, string $secret): bool
    {

        usort(
            $rows,
            static  fn(array $left, array $right): int =>
                ((int) ($left["id"] ?? 0)) <=> ((int) ($right["id"] ?? 0)),
        );
        $previousRow = null;
        $previousId = 0;
        foreach ($rows as $row) {
            $id = (int) ($row["id"] ?? 0);
            if ($id <= $previousId || !self::verifyRow($row, $secret)) {
                return false;
            }
            $policy = (string) ($row["policy_version"] ?? "");
            if (
                $policy === self::POLICY_VERSION &&
                is_array($previousRow) &&
                !hash_equals(
                    (string) ($previousRow["chain_hash"] ?? ""),
                    (string) ($row["previous_hash"] ?? ""),
                )
            ) {
                return false;
            }
            $previousRow = $row;
            $previousId = $id;
        }
        return true;
    }
    public static function storedHeadMatchesLatest(): bool
    {

        $head = \one(
            "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
            [self::META_KEY],
        );
        $latest = \one(
            "SELECT chain_hash,policy_version FROM pi_audit ORDER BY id DESC LIMIT 1",
        );
        $stored = mb_trim((string) ($head["meta_value"] ?? ""));
        $actual = mb_trim((string) ($latest["chain_hash"] ?? ""));
        if ($stored === "" && $actual === "") {
            return true;
        }
        if (
            $stored === "" &&
            $actual !== "" &&
            (string) ($latest["policy_version"] ?? "") !== self::POLICY_VERSION
        ) {
            return true;
        }
        return $stored !== "" && $actual !== "" && hash_equals($stored, $actual);
    }
    private static function canonicalize(mixed $value): mixed
    {

        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }
        return $value;
    }
    private static function ensureHead(): void
    {

        \q(
            "INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES (?,NULL,NOW()) ON DUPLICATE KEY UPDATE meta_key=VALUES(meta_key)",
            [self::META_KEY],
        );
        $head = \one(
            "SELECT meta_value FROM pi_meta WHERE meta_key=? FOR UPDATE",
            [self::META_KEY],
        );
        if (mb_trim((string) ($head["meta_value"] ?? "")) !== "") {
            return;
        }
        $latest = \one(
            "SELECT chain_hash FROM pi_audit WHERE chain_hash IS NOT NULL AND chain_hash<>'' ORDER BY id DESC LIMIT 1",
        );
        $legacyHead = mb_trim((string) ($latest["chain_hash"] ?? ""));
        \q(
            "UPDATE pi_meta SET meta_value=?,updated_at=NOW() WHERE meta_key=?",
            [$legacyHead !== "" ? $legacyHead : null, self::META_KEY],
        );
    }
    private static function currentHeadForUpdate(): string
    {

        self::ensureHead();
        $row = \one(
            "SELECT meta_value FROM pi_meta WHERE meta_key=? FOR UPDATE",
            [self::META_KEY],
        );
        return mb_trim((string) ($row["meta_value"] ?? ""));
    }
    private static function advanceHead(string $hash): void
    {

        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new \InvalidArgumentException("Novo hash de auditoria inválido.");
        }
        $statement = \q(
            "UPDATE pi_meta SET meta_value=?,updated_at=NOW() WHERE meta_key=?",
            [$hash, self::META_KEY],
        );
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException(
                "A cabeça da cadeia de auditoria não pôde ser avançada.",
            );
        }
    }
}
