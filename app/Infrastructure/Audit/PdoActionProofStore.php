<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Audit;

use Prontoo\Application\Audit\ActionProofPort;
use Prontoo\Core\Invariant\Canonical;
use Prontoo\Core\Invariant\Decision;
use Prontoo\Core\Integrity\PiIntegrity;

final class PdoActionProofStore implements ActionProofPort
{
    public function write(string $route, array $context, Decision $decision): void
    {
        if (!function_exists('pdo')) {
            return;
        }
        try {
            $payload = [
                'audit_body' => $decision->allowed
                    ? 'Prova operacional aprovada pelo núcleo de invariantes.'
                    : 'Prova operacional recusada pelo núcleo de invariantes.',
                'integrity_policy' => Canonical::POLICY_VERSION,
                'route' => Canonical::token($route, 'login'),
                'module' => $decision->module,
                'operation' => $decision->operation,
                'scope' => $decision->scope,
                'role' => (string) ($context['role'] ?? ''),
                'clinic_id' => $context['clinic_id'] ?? null,
                'user_id' => $context['user']['id'] ?? ($_SESSION['uid'] ?? null),
                'allowed' => $decision->allowed,
                'reason' => $decision->reason,
                'decision_proof_hash' => $decision->proofHash,
                'evidence' => $decision->evidence,
            ];
            $sql = 'INSERT INTO pi_action_proofs (clinic_id,user_id,route,module_key,operation_key,scope,role_code,allowed,reason,proof_hash,policy_version,context_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())';
            if (class_exists(PiIntegrity::class)) {
                $sql = PiIntegrity::rewriteSqlForRuntime($sql);
            }
            $statement = \pdo()->prepare($sql);
            $statement->execute([
                $context['clinic_id'] ?? null,
                $context['user']['id'] ?? ($_SESSION['uid'] ?? null),
                Canonical::token($route, 'login'),
                $decision->module,
                $decision->operation,
                $decision->scope,
                (string) ($context['role'] ?? ($_SESSION['role_code'] ?? '')),
                $decision->allowed ? 1 : 0,
                mb_substr($decision->reason, 0, 180, 'UTF-8'),
                Canonical::hash('action_proof', $payload),
                Canonical::POLICY_VERSION,
                Canonical::json($payload),
            ]);
        } catch (\Throwable $error) {
            error_log('[Prontoo layered action proof] não foi possível registrar prova: ' . $error->getMessage());
        }
    }
}
