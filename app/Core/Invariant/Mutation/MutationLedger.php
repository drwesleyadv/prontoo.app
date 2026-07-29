<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Mutation;

use Prontoo\Core\Invariant\Canonical;

final class MutationLedger
{
    private static ?string $chain = null;
    private static array $proofs = [];

    private function __construct() {
        








    }

    public static function record(array $evidence): string
    {
        









        if (self::$chain === null) {
            self::$chain = Canonical::hash("mutation_genesis", [
                "route" => function_exists("route") ? \route() : "runtime",
                "clinic_id" => (int) ($_SESSION["clinic_id"] ?? 0),
                "user_id" => (int) ($_SESSION["uid"] ?? 0),
            ]);
        }
        $proof = Canonical::hash("mutation", $evidence);
        self::$chain = hash("sha256", self::$chain . "|" . $proof);
        self::$proofs[] = [
            "proof" => $proof,
            "table" => (string) ($evidence["table"] ?? ""),
            "operation" => (string) ($evidence["operation"] ?? ""),
        ];
        $GLOBALS["PRONTOO_INVARIANT_PROOF_HASH"] = self::$chain;
        $GLOBALS["PRONTOO_INVARIANT_PROOF_COUNT"] = count(self::$proofs);
        return $proof;
    }

    public static function snapshot(): array
    {
        








        return [
            "policy" => Canonical::POLICY_VERSION,
            "chain" => self::$chain,
            "count" => count(self::$proofs),
            "proofs" => self::$proofs,
        ];
    }

    public static function reset(): void
    {
        









        self::$chain = null;
        self::$proofs = [];
        unset(
            $GLOBALS["PRONTOO_INVARIANT_PROOF_HASH"],
            $GLOBALS["PRONTOO_INVARIANT_PROOF_COUNT"],
        );
    }
}
