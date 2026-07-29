<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Audit;

use Prontoo\Application\Audit\ActionProofPort;
use Prontoo\Core\Invariant\Canonical;
use Prontoo\Core\Invariant\Decision;
use Prontoo\Core\Integrity\PiIntegrity;

final class PdoActionProofStore implements ActionProofPort
{
    public function write(string $route, array $context, Decision $decision): bool
    {
        









        if (!function_exists('pdo')) {
            error_log('[Prontoo layered action ledger] PDO indisponível.');
            return false;
        }
        try {
            $requestId = bin2hex(random_bytes(16));
            $payload = [
                'proof_type' => 'authorization_precondition',
                'integrity_policy' => Canonical::POLICY_VERSION,
                'request_id' => $requestId,
                'route' => Canonical::token($route, 'login'),
                'action' => (string) ($decision->evidence['action'] ?? ''),
                'module' => $decision->module,
                'operation' => $decision->operation,
                'scope' => $decision->scope,
                'role' => (string) ($context['role'] ?? ''),
                'clinic_id' => $context['clinic_id'] ?? null,
                'user_id' => $context['user']['id'] ?? ($_SESSION['uid'] ?? null),
                'allowed' => $decision->allowed,
                'reason' => $decision->reason,
                'decision_proof_hash' => $decision->proofHash,
                'contract_hash' => (string) ($decision->evidence['contract_hash'] ?? ''),
                'evidence' => $decision->evidence,
            ];
            $authorizationHash = Canonical::hash('action_ledger_authorization', $payload);
            $sql = "INSERT INTO pi_action_ledger (request_id,clinic_id,user_id,route,action_key,module_key,operation_key,scope,role_code,allowed,status,reason,authorization_hash,contract_hash,context_json,policy_version,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,NOW())";
            $sql = PiIntegrity::rewriteSqlForRuntime($sql);
            $statement = \pdo()->prepare($sql);
            $statement->execute([
                $requestId,
                $context['clinic_id'] ?? null,
                $context['user']['id'] ?? ($_SESSION['uid'] ?? null),
                Canonical::token($route, 'login'),
                (string) ($decision->evidence['action'] ?? ''),
                $decision->module,
                $decision->operation,
                $decision->scope,
                (string) ($context['role'] ?? ($_SESSION['role_code'] ?? '')),
                $decision->allowed ? 1 : 0,
                $decision->allowed ? 'authorized' : 'denied',
                mb_substr($decision->reason, 0, 180, 'UTF-8'),
                $authorizationHash,
                (string) ($decision->evidence['contract_hash'] ?? '') ?: null,
                Canonical::json($payload),
                PiIntegrity::POLICY_VERSION,
            ]);
            $ledgerId = (int) \pdo()->lastInsertId();
            if ($decision->allowed && $ledgerId > 0) {
                $GLOBALS['PRONTOO_ACTION_LEDGER_ID'] = $ledgerId;
                $GLOBALS['PRONTOO_ACTION_LEDGER_REQUEST_ID'] = $requestId;
            }
            return $ledgerId > 0;
        } catch (\Throwable $error) {
            error_log('[Prontoo layered action ledger] não foi possível registrar autorização: ' . $error->getMessage());
            return false;
        }
    }
}
