<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization\Definitions;

use Prontoo\Application\Authorization\ActionDefinitionCollection;
use Prontoo\Application\Authorization\ActionDefinitionSource;

final class AuthActionDefinitions implements ActionDefinitionSource
{
    public function definitions(): array
    {
        $definitions = new ActionDefinitionCollection();
        $auth = 'Auth/AuthOnboarding.php';
        $subscription = 'Domain/Clinic/SubscriptionSettings.php';
        $admin = 'Admin/AdminPages.php';

        $definitions->add('login', '__default__', 'public', $auth, [], ['session:authenticate']);
        $definitions->add('login', 'mfa_verify', 'public', $auth, [], ['session:mfa']);
        $definitions->add('mfa', ['mfa_enroll', 'mfa_continue'], 'public', $auth, [], ['session:mfa']);
        $definitions->add('signup', '__default__', 'public', $auth, [], ['clinic:create', 'session:authenticate']);
        $definitions->add('login_autotest', '__default__', 'public', $auth, [], ['session:autotest']);
        $definitions->add('mobile_web_access', '__default__', 'public', $auth, [], ['session:mobile_probe']);
        $definitions->add('logout', '__default__', 'authenticated', $auth, ['session:self'], ['session:logout']);
        $definitions->add('switch', '__default__', 'authenticated', $auth, ['session:self'], ['session:environment']);
        $definitions->add('switch', 'choose_admin', 'authenticated', $auth, ['session:self'], ['session:environment'], [], 'matrix', null, [$admin]);
        $definitions->add(
            'profile',
            [
                'profile_update_user',
                'profile_change_password',
                'profile_mfa_prepare',
                'profile_mfa_enable',
                'profile_mfa_cancel',
                'profile_mfa_recovery_ack',
                'profile_mfa_recovery_regenerate',
                'profile_mfa_replace_prepare',
                'profile_mfa_replace_enable',
                'profile_mfa_disable',
                'profile_switch_environment',
            ],
            'authenticated',
            $auth,
            ['session:self'],
            ['identity:self', 'session:mfa', 'session:environment'],
        );
        $definitions->add('global_reauth', '__default__', 'authenticated', $auth, ['session:self'], ['session:privileged']);

        foreach (['painel', 'operations', 'leads', 'patients', 'appointments', 'procedures', 'documents', 'tasks', 'maestro', 'audit', 'financial', 'notices', 'users', 'settings', 'permissions'] as $route) {
            $definitions->add($route, 'onboarding_tip_dismiss', 'clinic', $auth, ['session:self'], ['onboarding_tip:edit']);
        }

        $definitions->add('onboarding', '__default__', 'clinic', $auth, ['settings:edit'], ['clinic:edit', 'permissions:seed']);
        $definitions->add('settings', ['profile', 'sectors', 'visual'], 'clinic', $subscription, ['settings:edit'], ['clinic:edit']);
        $definitions->add('settings', 'subscription_claim', 'clinic', $subscription, ['settings:edit'], ['subscription:claim'], [], 'subscription_claim');

        return $definitions->definitions();
    }
}
