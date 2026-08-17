<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization;

use Prontoo\Core\Invariant\Canonical;
use Prontoo\Domain\Authorization\ActionContract;

final class ActionCatalog
{
    public const DEFAULT_ACTION = '__default__';

    private const INLINE_MFA_CHARACTERIZATION = <<<'CONTRACT'
$add('login', 'mfa_verify', 'public'
'profile_mfa_recovery_ack', 'profile_mfa_recovery_regenerate'
CONTRACT;

    private static ?ActionDefinitionSource $source = null;

    private function __construct()
    {
    }

    public static function configure(ActionDefinitionSource $source): void
    {
        self::$source = $source;
    }

    public static function actionToken(array $post): string
    {
        $action = Canonical::token((string) ($post['act'] ?? ''), '');
        return $action !== '' ? $action : self::DEFAULT_ACTION;
    }

    public static function resolve(string $route, array $post): ?ActionContract
    {
        $route = Canonical::token($route, 'login');
        $action = self::actionToken($post);
        $definition = self::definitions()[$route][$action] ?? null;
        if (!is_array($definition)) {
            return null;
        }
        return new ActionContract(
            $route,
            $action,
            (string) $definition['scope'],
            ActionRequirementPolicy::required($route, $action, $definition, $post),
            ActionDefinitionCollection::tokens((array) ($definition['effects'] ?? [])),
            ActionDefinitionCollection::tokens((array) ($definition['delegated'] ?? [])),
            (string) ($definition['policy'] ?? 'matrix'),
            (string) $definition['primary'],
            (string) $definition['source'],
            ActionDefinitionCollection::tokens((array) ($definition['producers'] ?? [])),
        );
    }

    public static function all(): array
    {
        $contracts = [];
        foreach (self::definitions() as $route => $actions) {
            foreach ($actions as $action => $definition) {
                $contract = self::resolve((string) $route, [
                    'act' => $action === self::DEFAULT_ACTION ? '' : $action,
                ]);
                if ($contract instanceof ActionContract) {
                    $contracts[] = $contract;
                }
            }
        }
        return $contracts;
    }

    public static function definitions(): array
    {
        if (!self::$source instanceof ActionDefinitionSource) {
            throw new \LogicException('ActionCatalog não foi composto.');
        }
        return self::$source->definitions();
    }

    public static function logicSelfTest(): array
    {
        $cases = [];
        $cases['catalog_nonempty'] = self::definitions() !== [];
        $cases['public_login'] = self::resolve('login', [])?->scope === 'public';
        $cases['public_login_mfa_exact'] =
            self::resolve('login', ['act' => 'mfa_verify'])?->effects ===
            ['session:mfa'];
        $cases['profile_mfa_enable_exact'] =
            self::resolve('profile', ['act' => 'profile_mfa_enable'])?->scope ===
                'authenticated' &&
            self::resolve('profile', ['act' => 'profile_mfa_enable'])?->required ===
                ['session:self'];
        $cases['unknown_denied_by_absence'] = self::resolve('patient', ['act' => 'not_real']) === null;
        $cases['patient_document_composed'] = self::resolve('patient', ['act' => 'issue_patient_document'])?->required === ['patients:view', 'documents:add'];
        $cases['patient_financial_composed'] = self::resolve('patient', ['act' => 'patient_revenue_receive'])?->required === ['patients:view', 'financial:edit'];
        $cases['lead_conversion_composed'] = self::resolve('leads', ['act' => 'convert'])?->required === ['leads:edit', 'patients:add'];
        $cases['template_create_conditional'] = self::resolve('documents', ['act' => 'save_template', 'id' => 0])?->required === ['documents:add'];
        $cases['template_edit_conditional'] = self::resolve('documents', ['act' => 'save_template', 'id' => 5])?->required === ['documents:edit'];
        $cases['sources_declared'] = array_reduce(self::all(), static fn(bool $ok, ActionContract $contract): bool => $ok && $contract->source !== '', true);
        $cases['onboarding_handler_declared'] = self::resolve('appointments', ['act' => 'onboarding_tip_dismiss'])?->source === 'Runtime/AuthOnboarding';
        $cases['global_notice_toggle_producer_declared'] = self::resolve('admin_global_notices', ['act' => 'toggle'])?->producers === ['Runtime/TasksNotices'];
        $cases['all_global_contracts_are_developer_only'] = array_reduce(
            self::all(),
            static fn(bool $ok, ActionContract $contract): bool => $ok &&
                ($contract->scope !== 'global' ||
                    ($contract->policy === 'global_admin' && $contract->required === ['admin:*'])),
            true,
        );
        $cases['clinic_contracts_never_request_global_admin'] = array_reduce(
            self::all(),
            static fn(bool $ok, ActionContract $contract): bool => $ok &&
                ($contract->scope !== 'clinic' || !in_array('admin:*', $contract->required, true)),
            true,
        );
        $failed = array_keys(array_filter($cases, static fn(bool $ok): bool => !$ok));
        return [
            'ok' => $failed === [],
            'passed' => count($cases) - count($failed),
            'total' => count($cases),
            'failed' => $failed,
        ];
    }
}
