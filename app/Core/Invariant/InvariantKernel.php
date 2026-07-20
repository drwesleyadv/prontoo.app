<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;

use Prontoo\Core\Invariant\Mutation\MutationInvariant;
use Prontoo\Core\Invariant\Mutation\MutationLedger;

final class InvariantKernel
{
    private function __construct() {}

    public static function policyVersion(): string
    {
        return Canonical::POLICY_VERSION;
    }

    public static function authorize(
        string $route,
        string $method,
        array $post,
        array $context,
    ): Decision {
        return CapabilityInvariant::evaluate($route, $method, $post, $context);
    }

    public static function guardMutation(string $sql, array $params = []): void
    {
        MutationInvariant::guard($sql, $params);
    }

    public static function proofSnapshot(): array
    {
        return MutationLedger::snapshot();
    }

    public static function logicSelfTest(): array
    {
        $capabilities = CapabilityInvariant::logicSelfTest();
        $mutations = MutationInvariant::logicSelfTest();
        return [
            "ok" => !empty($capabilities["ok"]) && !empty($mutations["ok"]),
            "policy" => self::policyVersion(),
            "capabilities" => $capabilities,
            "mutations" => $mutations,
        ];
    }
}
