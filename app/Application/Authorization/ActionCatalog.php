<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization;

use Prontoo\Core\Invariant\Canonical;
use Prontoo\Domain\Authorization\ActionContract;

final class ActionCatalog
{
    public const DEFAULT_ACTION = '__default__';

    private function __construct() {}

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
        $required = self::conditionalRequired($route, $action, $definition, $post);
        return new ActionContract(
            $route,
            $action,
            (string) $definition['scope'],
            $required,
            self::tokens((array) ($definition['effects'] ?? [])),
            self::tokens((array) ($definition['delegated'] ?? [])),
            (string) ($definition['policy'] ?? 'matrix'),
            (string) $definition['primary'],
            (string) $definition['source'],
        );
    }

    /** @return list<ActionContract> */
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

    /** @return array<string,array<string,array<string,mixed>>> */
    public static function definitions(): array
    {
        static $catalog = null;
        if (is_array($catalog)) {
            return $catalog;
        }

        $catalog = [];
        $add = static function (
            string $route,
            string|array $actions,
            string $scope,
            string $source,
            array $required = [],
            array $effects = [],
            array $delegated = [],
            string $policy = 'matrix',
            ?string $primary = null,
        ) use (&$catalog): void {
            foreach ((array) $actions as $action) {
                $action = $action !== '' ? $action : self::DEFAULT_ACTION;
                if (isset($catalog[$route][$action])) {
                    throw new \LogicException("Contrato duplicado: {$route}:{$action}");
                }
                $required = self::tokens($required);
                $catalog[$route][$action] = [
                    'scope' => $scope,
                    'required' => $required,
                    'effects' => self::tokens($effects),
                    'delegated' => self::tokens($delegated),
                    'policy' => $policy,
                    'primary' => $primary ?? ($required[0] ?? ($scope === 'global' ? 'admin:*' : 'session:self')),
                    'source' => $source,
                ];
            }
        };

        $auth = 'Auth/AuthOnboarding.php';
        $runner = 'Runtime/Runner.php';
        $clinic = 'Domain/Clinic/ClinicConfig.php';
        $subscription = 'Domain/Clinic/SubscriptionSettings.php';
        $patients = 'Domain/Patients/Patients.php';
        $leads = 'Domain/Leads/Leads.php';
        $appointments = 'Domain/Appointments/Appointments.php';
        $documents = 'Domain/Documents/Documents.php';
        $tasks = 'Domain/Tasks/TasksNotices.php';
        $users = 'Domain/Permissions/UsersPermissions.php';
        $maestro = 'Domain/Maestro/Maestro.php';
        $financial = 'Domain/Financial/Financial.php';
        $admin = 'Admin/AdminPages.php';

        // Public and authenticated session contracts.
        $add('login', self::DEFAULT_ACTION, 'public', $auth, [], ['session:authenticate']);
        $add('signup', self::DEFAULT_ACTION, 'public', $auth, [], ['clinic:create', 'session:authenticate']);
        $add('login_autotest', self::DEFAULT_ACTION, 'public', $auth, [], ['session:autotest']);
        $add('mobile_web_access', self::DEFAULT_ACTION, 'public', $auth, [], ['session:mobile_probe']);
        $add('logout', self::DEFAULT_ACTION, 'authenticated', $auth, ['session:self'], ['session:logout']);
        $add('switch', [self::DEFAULT_ACTION, 'choose_admin'], 'authenticated', $auth, ['session:self'], ['session:environment']);
        $add('profile', ['profile_update_user', 'profile_change_password', 'profile_switch_environment'], 'authenticated', $auth, ['session:self'], ['identity:self', 'session:environment']);

        // Central request operation.
        foreach (['painel', 'operations', 'leads', 'patients', 'appointments', 'procedures', 'documents', 'tasks', 'maestro', 'audit', 'financial', 'notices', 'users', 'settings', 'permissions'] as $route) {
            $add($route, 'onboarding_tip_dismiss', 'clinic', $runner, ['session:self'], ['onboarding_tip:edit']);
        }

        // Clinic onboarding and settings.
        $add('onboarding', self::DEFAULT_ACTION, 'clinic', $auth, ['settings:edit'], ['clinic:edit', 'permissions:seed']);
        $add('settings', ['profile', 'sectors', 'visual'], 'clinic', $subscription, ['settings:edit'], ['clinic:edit']);
        $add('settings', 'subscription_claim', 'clinic', $subscription, ['settings:edit'], ['subscription:claim'], [], 'subscription_claim');

        // Patients and clinical records.
        $add('patients', self::DEFAULT_ACTION, 'clinic', $patients, ['patients:add'], ['patients:add']);
        $add('patient', ['save_legal_guardian', 'update_patient_contact', 'update_patient', 'create_patient_tab', 'update_care'], 'clinic', $patients, ['patients:edit'], ['patients:edit']);
        $add('patient', ['delete_legal_guardian', 'delete_patient', 'delete_care'], 'clinic', $patients, ['patients:delete'], ['patients:delete']);
        $add('patient', 'issue_patient_document', 'clinic', $patients, ['patients:view', 'documents:add'], ['documents:add']);
        $add('patient', 'patient_revenue_receive', 'clinic', $patients, ['patients:view', 'financial:edit'], ['financial:receipt'], [], 'financial_operational', 'financial:edit');
        $add('patient', 'finish_active_appointment', 'clinic', $patients, ['patients:view', 'appointments:edit'], ['appointments:transition'], ['tasks:edit']);
        $add('patient', [self::DEFAULT_ACTION, 'record'], 'clinic', $patients, ['patients:edit'], ['care:add'], ['appointments:transition', 'tasks:edit']);

        // Leads.
        $add('leads', [self::DEFAULT_ACTION, 'save'], 'clinic', $leads, ['leads:add'], ['leads:add']);
        $add('leads', 'update', 'clinic', $leads, ['leads:edit'], ['leads:edit']);
        $add('leads', 'convert', 'clinic', $leads, ['leads:edit', 'patients:add'], ['leads:convert', 'patients:add']);
        $add('leads', 'archive_lead', 'clinic', $leads, ['leads:edit'], ['leads:archive']);

        // Agenda and Journey workflow.
        $add('appointments', [self::DEFAULT_ACTION, 'create'], 'clinic', $appointments, ['appointments:add'], ['appointments:add'], ['financial:sync', 'tasks:add']);
        $add('appointments', ['agenda_note', 'block'], 'clinic', $appointments, ['appointments:add'], ['agenda:add']);
        $add('appointments', ['agenda_note_delete', 'delete_appointment', 'delete_block', 'unblock', 'cancel'], 'clinic', $appointments, ['appointments:delete'], ['agenda:delete'], ['financial:sync', 'tasks:edit']);
        $add('appointments', ['edit', 'update_block', 'update_appointment', 'confirm', 'arrived', 'no_show', 'start_prepare', 'finish_prepare', 'start_consultation', 'finish_consultation', 'finish_checkout'], 'clinic', $appointments, ['appointments:edit'], ['appointments:transition'], ['tasks:edit', 'financial:sync']);

        // Documents and procedures.
        $add('documents', 'save_template', 'clinic', $documents, [], ['documents:template'], [], 'matrix', 'documents:edit');
        $add('documents', ['approve_template', 'reject_template', 'save_document', 'confirm_document'], 'clinic', $documents, ['documents:edit'], ['documents:edit']);
        $add('documents', 'create_document', 'clinic', $documents, ['documents:add'], ['documents:add']);
        $add('procedures', 'save', 'clinic', $documents, [], ['procedures:write'], [], 'matrix', 'procedures:edit');
        $add('procedures', 'toggle', 'clinic', $documents, ['procedures:edit'], ['procedures:edit']);

        // Tasks and notices.
        $add('tasks', [self::DEFAULT_ACTION, 'create'], 'clinic', $tasks, ['tasks:add'], ['tasks:add']);
        $add('tasks', ['start', 'release', 'done', 'comment', 'comment_edit', 'comment_delete'], 'clinic', $tasks, ['tasks:edit'], ['tasks:edit']);
        $add('notices', [self::DEFAULT_ACTION, 'create'], 'clinic', $tasks, ['notices:add'], ['notices:add']);
        $add('notices', ['support_message', 'ack', 'hide', 'unhide'], 'clinic', $tasks, ['notices:view'], ['notices:self_state'], [], 'notice_support');

        // Collaborators, permissions and Maestro.
        $add('users', [self::DEFAULT_ACTION, 'save'], 'clinic', $users, ['users:add'], ['users:add']);
        $add('users', 'deactivate', 'clinic', $users, ['users:delete'], ['users:deactivate']);
        $add('user', [self::DEFAULT_ACTION, 'update'], 'clinic', $users, ['users:edit'], ['users:edit']);
        $add('user', 'deactivate', 'clinic', $users, ['users:delete'], ['users:deactivate']);
        $add('permissions', self::DEFAULT_ACTION, 'clinic', $users, ['permissions:edit'], ['permissions:edit']);
        $add('maestro', [self::DEFAULT_ACTION, 'save_rule'], 'clinic', $maestro, ['maestro:add'], ['maestro:add'], [], 'manager_only');
        $add('maestro', 'toggle_rule', 'clinic', $maestro, ['maestro:edit'], ['maestro:edit'], [], 'manager_only');
        $add('maestro', 'delete_rule', 'clinic', $maestro, ['maestro:delete'], ['maestro:delete'], [], 'manager_only');

        // Finance: exact operational and administrative tokens.
        $add('financial', ['cash_open', 'cash_keep_closed', 'cash_receipt', 'cash_payment', 'cash_close'], 'clinic', $financial, ['financial:edit'], ['financial:cashier'], [], 'financial_operational');
        $add('financial', ['drawer_create', 'bank_account'], 'clinic', $financial, ['financial:add'], ['financial:add']);
        $add('financial', ['drawer_rename', 'drawer_assign', 'drawer_unassign', 'drawer_deactivate', 'drawer_schedule_unlock', 'goal', 'review_close', 'review_opening', 'daily_consolidate', 'admin_receive', 'admin_payment', 'admin_transfer', 'safe_payment', 'safe_receipt', 'deposit_bank'], 'clinic', $financial, ['financial:edit'], ['financial:edit']);
        $add('creditors', [self::DEFAULT_ACTION, 'creditor_save', 'creditor_deactivate'], 'clinic', $financial, ['financial:edit'], ['financial:counterparty']);

        // Global administration.
        $globalActions = [
            'admin_painel' => ['goal', 'confirm_subscription_payment', 'reject_subscription_payment'],
            'admin_clinics' => ['activate_subscription', 'billing', 'deactivate_subscription', 'default_billing', 'toggle'],
            'admin_global_notices' => ['create', 'toggle'],
            'admin_alerts' => ['create', 'read'],
            'admin_maintenance' => ['save_settings', 'regenerate_footer_seq_alphabet', 'save_maintenance'],
            'admin_deleted' => ['restore_patient', 'restore_care'],
        ];
        foreach ($globalActions as $route => $actions) {
            $add($route, $actions, 'global', $admin, ['admin:*'], ['admin:write'], [], 'global_admin');
        }

        return $catalog;
    }

    /** @return list<string> */
    private static function conditionalRequired(
        string $route,
        string $action,
        array $definition,
        array $post,
    ): array {
        $required = self::tokens((array) ($definition['required'] ?? []));
        if ($route === 'documents' && $action === 'save_template') {
            $required[] = (int) ($post['id'] ?? 0) > 0 ? 'documents:edit' : 'documents:add';
        }
        if ($route === 'procedures' && $action === 'save') {
            $required[] = (int) ($post['id'] ?? 0) > 0 ? 'procedures:edit' : 'procedures:add';
        }
        return self::tokens($required);
    }

    /** @return list<string> */
    private static function tokens(array $values): array
    {
        $tokens = [];
        foreach ($values as $value) {
            $token = trim((string) $value);
            if ($token !== '') {
                $tokens[$token] = true;
            }
        }
        return array_keys($tokens);
    }

    public static function logicSelfTest(): array
    {
        $cases = [];
        $cases['catalog_nonempty'] = self::definitions() !== [];
        $cases['public_login'] = self::resolve('login', [])?->scope === 'public';
        $cases['unknown_denied_by_absence'] = self::resolve('patient', ['act' => 'not_real']) === null;
        $cases['patient_document_composed'] = self::resolve('patient', ['act' => 'issue_patient_document'])?->required === ['patients:view', 'documents:add'];
        $cases['patient_financial_composed'] = self::resolve('patient', ['act' => 'patient_revenue_receive'])?->required === ['patients:view', 'financial:edit'];
        $cases['lead_conversion_composed'] = self::resolve('leads', ['act' => 'convert'])?->required === ['leads:edit', 'patients:add'];
        $cases['template_create_conditional'] = self::resolve('documents', ['act' => 'save_template', 'id' => 0])?->required === ['documents:add'];
        $cases['template_edit_conditional'] = self::resolve('documents', ['act' => 'save_template', 'id' => 5])?->required === ['documents:edit'];
        $cases['sources_declared'] = array_reduce(self::all(), static fn(bool $ok, ActionContract $contract): bool => $ok && $contract->source !== '', true);
        $failed = array_keys(array_filter($cases, static fn(bool $ok): bool => !$ok));
        return [
            'ok' => $failed === [],
            'passed' => count($cases) - count($failed),
            'total' => count($cases),
            'failed' => $failed,
        ];
    }
}
