<?php
declare(strict_types=1);
namespace Prontoo\Core\Integrity;

use Prontoo\Core\Invariant\Canonical;
use Prontoo\Core\Invariant\Decision;
use Prontoo\Core\Invariant\InvariantKernel;

final class ActionProof
{
    private function __construct() {}

    public static function enforce(
        string $route,
        string $method,
        array $post,
        array $ctx,
    ): void {
        $decision = InvariantKernel::authorize($route, $method, $post, $ctx);
        if ($decision->skipped) {
            return;
        }
        self::writeProof($route, $ctx, $decision);
        if (!$decision->allowed) {
            throw new \ProntooHttpError(
                403,
                "Esta ação não está disponível para sua credencial atual.",
            );
        }
    }

    public static function logicSelfTest(): array
    {
        return InvariantKernel::logicSelfTest();
    }

    private static function writeProof(
        string $route,
        array $ctx,
        Decision $decision,
    ): void {
        if (!function_exists("pdo")) {
            return;
        }
        try {
            $payload = [
                "audit_body" => $decision->allowed
                    ? "Prova operacional aprovada pelo núcleo de invariantes."
                    : "Prova operacional recusada pelo núcleo de invariantes.",
                "integrity_policy" => Canonical::POLICY_VERSION,
                "route" => Canonical::token($route, "login"),
                "module" => $decision->module,
                "operation" => $decision->operation,
                "scope" => $decision->scope,
                "role" => (string) ($ctx["role"] ?? ""),
                "clinic_id" => $ctx["clinic_id"] ?? null,
                "user_id" => $ctx["user"]["id"] ?? ($_SESSION["uid"] ?? null),
                "allowed" => $decision->allowed,
                "reason" => $decision->reason,
                "decision_proof_hash" => $decision->proofHash,
                "evidence" => $decision->evidence,
            ];
            $json = Canonical::json($payload);
            $proofHash = Canonical::hash("action_proof", $payload);
            $sql =
                "INSERT INTO pi_action_proofs (clinic_id,user_id,route,module_key,operation_key,scope,role_code,allowed,reason,proof_hash,policy_version,context_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
            if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
                $sql = PiIntegrity::rewriteSqlForRuntime($sql);
            }
            $statement = \pdo()->prepare($sql);
            $statement->execute([
                $ctx["clinic_id"] ?? null,
                $ctx["user"]["id"] ?? ($_SESSION["uid"] ?? null),
                Canonical::token($route, "login"),
                $decision->module,
                $decision->operation,
                $decision->scope,
                (string) ($ctx["role"] ?? ($_SESSION["role_code"] ?? "")),
                $decision->allowed ? 1 : 0,
                mb_substr($decision->reason, 0, 180, "UTF-8"),
                $proofHash,
                Canonical::POLICY_VERSION,
                $json,
            ]);
        } catch (\Throwable $error) {
            error_log(
                "[Prontoo invariant action proof] não foi possível registrar prova: " .
                    $error->getMessage(),
            );
        }
    }
}
