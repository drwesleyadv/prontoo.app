<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AuthOnboarding;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;
use Prontoo\Runtime\Boot\RuntimeBootCoordinator;

final class AuthOnboardingRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function mfa_complete_pending_login(bool $redirectAfterLogin = true): string
    
    {
        $user = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::mfa_pending_login_user();
        $pending = $_SESSION["pending_mfa_login"] ?? null;
        if (
            !$user ||
            !is_array($pending) ||
            empty($_SESSION["mfa_pending_verified"])
        ) {
            \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::mfa_pending_login_clear();
            if ($redirectAfterLogin) {
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login", ["relogin" => "1"]);
            }
            throw new RuntimeException(
                "A verificação expirou. Informe novamente o CPF e a senha.",
            );
        }
        $uid = (int) $user["id"];
        $isGlobalAdmin = (int) ($user["is_global_admin"] ?? 0) === 1;
        $choices = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::active_clinic_roles_for_user($uid);
        $wanted = [
            "scope" => (string) ($pending["scope"] ?? "clinic"),
            "clinic_role_id" => (int) ($pending["clinic_role_id"] ?? 0),
        ];
        $credential = \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_credential_match(
            $uid,
            $isGlobalAdmin,
            $choices,
            $wanted,
        );
        if (!$credential) {
            $credential = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_resolve_user_credential(
                $uid,
                $isGlobalAdmin,
                $choices,
            );
        }
        if (!$credential) {
            \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::mfa_pending_login_clear();
            throw new RuntimeException(
                "Não foi possível carregar a credencial do usuário.",
            );
        }
        \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::mfa_pending_login_clear();
        $_SESSION["mfa_verified_at"] = time();
        if ($isGlobalAdmin) {
            $_SESSION["privileged_auth_at"] = time();
        } else {
            unset($_SESSION["privileged_auth_at"]);
        }
        RuntimeBootCoordinator::postPasswordMaintenance($uid);
        return \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_apply_resolved_credential(
            $uid,
            $credential,
            (string) ($user["user_auth_generation"] ?? ""),
            $redirectAfterLogin,
        );
    
    }

    public static function mfa_attempt_limited(int $uid, string $purpose): bool
    
    {
        return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_value_bucket("mfa_" . $purpose, (string) $uid),
            10,
            300,
        ) ||
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_ip_bucket("mfa_" . $purpose),
                30,
                300,
            );
    
    }

    public static function page_mfa(): void
    
    {
        $user = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::mfa_pending_login_user();
        if (!$user) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login", ["relogin" => "1"]);
        }
        $uid = (int) $user["id"];
        if ((int) ($user["is_global_admin"] ?? 0) !== 1) {
            \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::mfa_pending_login_clear();
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
        }
        $enrolled = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_is_enrolled($uid);
        if ($enrolled && empty($_SESSION["mfa_recovery_codes"])) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
        }
        if (!$enrolled && empty($_SESSION["mfa_enrollment_secret"])) {
            $_SESSION["mfa_enrollment_secret"] = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_secret_generate();
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "");
            if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_attempt_limited($uid, "login")) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    "Muitas tentativas de autenticação. Aguarde alguns minutos.",
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("mfa");
            }
            try {
                if ($act === "mfa_enroll" && !$enrolled) {
                    $secret = (string) ($_SESSION["mfa_enrollment_secret"] ?? "");
                    if ($secret === "") {
                        throw new RuntimeException(
                            "A configuração expirou. Inicie novamente.",
                        );
                    }
                    $codes = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enroll_user(
                        $uid,
                        $secret,
                        (string) ($_POST["code"] ?? ""),
                    );
                    $_SESSION["mfa_recovery_codes"] = $codes;
                    $_SESSION["mfa_pending_verified"] = true;
                    unset($_SESSION["mfa_enrollment_secret"]);
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("mfa_cadastrado", "usuario", $uid, [
                        "audit_body" =>
                            "MFA obrigatório do Desenvolvedor cadastrado e confirmado por TOTP.",
                    ]);
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("mfa");
                }
                if (
                    $act === "mfa_continue" &&
                    !empty($_SESSION["mfa_pending_verified"]) &&
                    !empty($_SESSION["mfa_recovery_codes"])
                ) {
                    unset($_SESSION["mfa_recovery_codes"]);
                    \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_complete_pending_login();
                }
                throw new RuntimeException("Ação MFA inválida.");
            } catch (Throwable $e) {
                usleep(random_int(250000, 450000));
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("falha_mfa", "login", $uid, [
                    "motivo_hash" => hash("sha256", $e->getMessage()),
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                        $e,
                        "Não foi possível confirmar o código de verificação.",
                    ),
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("mfa");
            }
        }
        $recovery = (array) ($_SESSION["mfa_recovery_codes"] ?? []);
        if ($recovery) {
            $items = "";
            foreach ($recovery as $code) {
                $items .=
                    '<li><code tabindex="0">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $code) .
                    "</code></li>";
            }
            $body =
                '<section class="auth login-card security-auth-card security-recovery-card"><div class="auth-titleline security-auth-titleline"><span class="auth-brandmark security-auth-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified_user") .
                '</span><div><span class="eyebrow">Última etapa</span><h1>Guarde seus códigos de recuperação</h1><p>Eles permitem entrar se você perder o acesso ao aplicativo autenticador.</p></div></div><div class="security-auth-notice" role="status"><span class="security-auth-notice-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("key") .
                '</span><div><strong>Salve em um lugar seguro</strong><span>Cada código funciona uma única vez e não será exibido novamente.</span></div></div><ul class="recovery-code-list" aria-label="Códigos de recuperação">' .
                $items .
                '</ul><form method="post" class="security-auth-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="mfa_continue"><button type="submit" class="primary wide security-auth-submit">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_forward") .
                "<span>Já guardei. Entrar no Prontoo</span></button></form></section>";
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Códigos de recuperação", $body, ["public" => true]);
            return;
        }
        if (!$enrolled) {
            $secret = (string) $_SESSION["mfa_enrollment_secret"];
            $account =
                mb_trim((string) ($user["email"] ?? "")) ?:
                ((string) ($user["name"] ?? "Desenvolvedor") . " #" . $uid);
            $uri = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_otpauth_uri($account, $secret);
            $body =
                '<section class="auth login-card security-auth-card security-enrollment-card"><div class="auth-titleline security-auth-titleline"><span class="auth-brandmark security-auth-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("shield_lock") .
                '</span><div><span class="eyebrow">Primeiro acesso</span><h1>Ative a verificação em duas etapas</h1><p>Depois da senha, você usará um código do aplicativo autenticador.</p></div></div><div class="mfa-setup-step"><span class="mfa-step-number" aria-hidden="true">1</span><div class="mfa-step-content"><strong>Conecte seu aplicativo</strong><small>Abra o aplicativo autenticador no celular e adicione uma nova conta.</small><a class="ghost wide security-auth-launch" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($uri) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("open_in_new") .
                '<span>Abrir aplicativo autenticador</span></a><details class="mfa-manual-setup"><summary>Configurar com uma chave manual</summary><div class="mfa-secret"><div><span>Chave de configuração</span><small>No aplicativo, escolha a opção de inserir uma chave.</small></div><code tabindex="0" aria-label="Chave manual do autenticador">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($secret) .
                '</code></div></details></div></div><div class="mfa-setup-step"><span class="mfa-step-number" aria-hidden="true">2</span><div class="mfa-step-content"><strong>Confirme o código</strong><small>Digite os seis dígitos exibidos pelo aplicativo.</small><form method="post" class="compact security-auth-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="mfa_enroll">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Código de verificação",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "code",
                        "text",
                        "",
                        'required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" aria-describedby="mfa-code-help"',
                    ),
                ) .
                '<small id="mfa-code-help" class="field-help">Use o código atual do aplicativo.</small><button type="submit" class="primary wide security-auth-submit">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check_circle") .
                "<span>Ativar e continuar</span></button></form></div></div></section>";
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Verificação em duas etapas", $body, ["public" => true]);
            return;
        }
    
    }

    public static function page_global_reauth(): void
    
    {
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        if (($c["scope"] ?? "") === "global") {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_performance");
        }
        $uid = (int) ($c["user"]["id"] ?? 0);
        $user = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.auth02.page_global_reauth.01', [$uid], []);
        if (
            !$user ||
            (int) ($user["is_global_admin"] ?? 0) !== 1 ||
            !\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_is_enrolled($uid)
        ) {
            throw new ProntooHttpError(
                403,
                "Ambiente global indisponível para este usuário.",
            );
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            try {
                if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_attempt_limited($uid, "elevation")) {
                    throw new RuntimeException(
                        "Muitas tentativas. Aguarde alguns minutos.",
                    );
                }
                $password = (string) ($_POST["password"] ?? "");
                $code = (string) ($_POST["code"] ?? "");
                if (
                    !password_verify($password, (string) $user["password_hash"]) ||
                    !\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_verify_user_code($uid, $code)
                ) {
                    usleep(random_int(250000, 450000));
                    throw new RuntimeException(
                        "A senha ou o código de autenticação não confere.",
                    );
                }
                session_regenerate_id(true);
                $_SESSION["csrf"] = bin2hex(random_bytes(32));
                $_SESSION["scope"] = "global";
                $_SESSION["mfa_verified_at"] = time();
                $_SESSION["privileged_auth_at"] = time();
                unset(
                    $_SESSION["uc_id"],
                    $_SESSION["clinic_id"],
                    $_SESSION["role_code"],
                    $_SESSION["effective_roles"],
                );
                \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_last_credential_remember($uid, "global", null);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("elevacao_global_reautenticada", "usuario", $uid, [
                    "scope" => "global",
                    "audit_body" =>
                        "Entrada no ambiente do Desenvolvedor autorizada após nova confirmação de senha e MFA.",
                ]);
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_performance");
            } catch (Throwable $e) {
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("falha_elevacao_global", "seguranca", $uid, [
                    "motivo_hash" => hash("sha256", $e->getMessage()),
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                        $e,
                        "Não foi possível confirmar a elevação de acesso.",
                    ),
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("global_reauth");
            }
        }
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Confirmar identidade",
                "Para entrar no ambiente global, confirme novamente seus dados.",
            ) .
            '<section class="card account-card security-reauth-card"><header class="security-reauth-head"><span class="security-reauth-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("admin_panel_settings") .
            '</span><div><span class="eyebrow">Ambiente do Desenvolvedor</span><h2>Confirme que é você</h2><p>Informe sua senha e o código do aplicativo autenticador.</p></div></header><div class="security-auth-notice security-auth-notice-soft"><span class="security-auth-notice-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("enhanced_encryption") .
            '</span><div><strong>Confirmação temporária</strong><span>Ela vale somente para esta sessão.</span></div></div><form method="post" class="compact security-reauth-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Senha atual",
                '<div class="password-field">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "password",
                        "password",
                        "",
                        'required autocomplete="current-password" data-password-toggle',
                    ) .
                    '<button type="button" class="password-toggle" data-password-toggle-button aria-label="Mostrar senha">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("visibility") .
                    "</button></div>",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Código de verificação",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "code",
                    "text",
                    "",
                    'required autocomplete="one-time-code" maxlength="16" placeholder="Digite o código" autocapitalize="characters" spellcheck="false" aria-describedby="global-verification-help"',
                ),
            ) .
            '<small id="global-verification-help" class="field-help">Use o código atual do aplicativo ou um código de recuperação.</small>' .
            '<div class="form-actions security-reauth-actions"><a class="ghost" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("profile") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            '<span>Cancelar</span></a><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified_user") .
            "<span>Entrar no ambiente global</span></button></div></form></section>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Confirmar identidade", $body);
    
    }
}
