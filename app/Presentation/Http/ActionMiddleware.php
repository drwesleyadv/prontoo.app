<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Http;

use Prontoo\Application\Audit\ActionProofPort;
use Prontoo\Application\Authorization\AuthorizationService;
use Prontoo\Application\Authorization\CapabilityProvider;
use Prontoo\Core\Invariant\Decision;
use Prontoo\Domain\Authorization\ActionContract;

final readonly class ActionMiddleware
{
    public function __construct(
        private AuthorizationService $authorization,
        private ActionProofPort $proofs,
    ) {
        /*
         * GUIA DE MANUTENÇÃO — Presentation.Http.ActionMiddleware::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Presentation/Http/ActionMiddleware.php (adaptação HTTP e apresentação).
         * Chamadores detectados: `Runtime.LayeredKernel::actionMiddleware`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public function enforce(
        string $route,
        string $method,
        array $post,
        array $context,
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — Presentation.Http.ActionMiddleware::enforce
         * Responsabilidade: Implementa a responsabilidade “enforce” dentro do módulo de adaptação HTTP e apresentação.
         * Local arquitetural: app/Presentation/Http/ActionMiddleware.php (adaptação HTTP e apresentação).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `->evaluate`, `->write`.
         * Classes ou serviços instanciados: `.ProntooHttpError`.
         * Estado externo lido: `$GLOBALS`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $decision = $this->authorization->evaluate($route, $method, $post, $context);
        if ($decision->skipped) {
            return;
        }

        $proofWritten = $this->proofs->write($route, $context, $decision);
        if (!$decision->allowed) {
            throw new \ProntooHttpError(
                403,
                'Esta ação não está disponível para sua credencial atual.',
            );
        }
        if (!$proofWritten) {
            throw new \ProntooHttpError(
                503,
                'Não foi possível registrar a prova de autorização. Nenhuma alteração foi executada.',
            );
        }

        $GLOBALS['PRONTOO_ACTION_AUTHORIZATION_SEAL'] = [
            'route' => (string) ($decision->evidence['route'] ?? $route),
            'action' => (string) ($decision->evidence['action'] ?? ''),
            'user_id' => (int) ($decision->evidence['user_id'] ?? 0),
            'clinic_id' => (int) ($decision->evidence['clinic_id'] ?? 0),
            'contract_hash' => (string) ($decision->evidence['contract_hash'] ?? ''),
            'decision_proof_hash' => $decision->proofHash,
            'ledger_id' => (int) ($GLOBALS['PRONTOO_ACTION_LEDGER_ID'] ?? 0),
            'request_id' => (string) ($GLOBALS['PRONTOO_ACTION_LEDGER_REQUEST_ID'] ?? ''),
        ];
    }

    public static function logicSelfTest(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Presentation.Http.ActionMiddleware::logicSelfTest
         * Responsabilidade: Executa verificações regressivas embutidas para confirmar que os contratos lógicos deste componente permanecem válidos.
         * Local arquitetural: app/Presentation/Http/ActionMiddleware.php (adaptação HTTP e apresentação).
         * Chamadores detectados: `Runtime.LayeredKernel::logicSelfTest`.
         * Dependências chamadas: `grants`, `in_array`, `write`, `self`, `AuthorizationService`, `->enforce`, `array_keys`, `array_filter`, `count`.
         * Classes ou serviços instanciados: `self`, `AuthorizationService`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $provider = new class implements CapabilityProvider {
            public function grants(ActionContract $contract, string $capability, array $context): bool
            {
                /*
                 * GUIA DE MANUTENÇÃO — Presentation.Http.anonymous@58::grants
                 * Responsabilidade: Implementa a responsabilidade “grants” dentro do módulo de adaptação HTTP e apresentação.
                 * Local arquitetural: app/Presentation/Http/ActionMiddleware.php (adaptação HTTP e apresentação).
                 * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
                 * Dependências chamadas: `in_array`.
                 * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
                 * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
                 */
                return in_array($capability, (array) ($context['grants'] ?? []), true);
            }
        };
        $proofs = new class implements ActionProofPort {
            public bool $result = true;
            public int $writes = 0;

            public function write(string $route, array $context, Decision $decision): bool
            {
                /*
                 * GUIA DE MANUTENÇÃO — Presentation.Http.anonymous@64::write
                 * Responsabilidade: Valida e executa a mutação “write”, preservando as invariantes do módulo de adaptação HTTP e apresentação.
                 * Local arquitetural: app/Presentation/Http/ActionMiddleware.php (adaptação HTTP e apresentação).
                 * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
                 * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
                 * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
                 * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
                 */
                $this->writes++;
                return $this->result;
            }
        };
        $middleware = new self(new AuthorizationService($provider), $proofs);
        $context = [
            'scope' => 'clinic',
            'clinic_id' => 17,
            'user' => ['id' => 3],
            'role' => 'medico',
            'grants' => ['patients:edit'],
        ];
        $cases = [];

        $middleware->enforce('patient', 'POST', ['act' => 'record'], $context);
        $cases['allowed_with_proof'] = $proofs->writes === 1;

        $proofs->result = false;
        $failClosed = false;
        try {
            $middleware->enforce('patient', 'POST', ['act' => 'record'], $context);
        } catch (\ProntooHttpError $error) {
            $failClosed = $error->status === 503;
        }
        $cases['allowed_without_proof_is_blocked'] = $failClosed;

        $deniedStillDenied = false;
        try {
            $middleware->enforce('patient', 'POST', ['act' => 'delete_patient'], $context);
        } catch (\ProntooHttpError $error) {
            $deniedStillDenied = $error->status === 403;
        }
        $cases['denied_without_proof_remains_denied'] = $deniedStillDenied;

        $before = $proofs->writes;
        $middleware->enforce('patient', 'GET', [], $context);
        $cases['read_skips_proof'] = $proofs->writes === $before;

        $failed = array_keys(array_filter($cases, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de adaptação HTTP e apresentação. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn(bool $ok): bool => !$ok));
        return [
            'ok' => $failed === [],
            'passed' => count($cases) - count($failed),
            'total' => count($cases),
            'failed' => $failed,
        ];
    }
}
