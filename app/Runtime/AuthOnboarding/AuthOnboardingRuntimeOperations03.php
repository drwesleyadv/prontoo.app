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
use Prontoo\Presentation\Http\JsonResponder;
use Prontoo\Runtime\Boot\RuntimeBootCoordinator;
use Prontoo\Runtime\Routing\RouteCatalog;

final class AuthOnboardingRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function page_login(): void
    
    {
    
        unset($_SESSION["pending_login_uid"], $_SESSION["pending_device_login"]);
        $wantsJson = RouteCatalog::wantsJson(
            "login",
            (string) ($_SERVER["HTTP_ACCEPT"] ?? ""),
        );
        $currentCtx = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
        if ($currentCtx) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect(
                ($currentCtx["scope"] ?? "") === "global"
                    ? "admin_painel"
                    : "appointments",
            );
        }
        $pendingMfaUser = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::mfa_pending_login_user();
        $pendingMfaState = is_array($pendingMfaUser)
            ? \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state((int) ($pendingMfaUser["id"] ?? 0))
            : "inactive";
        $mfaStage =
            is_array($pendingMfaUser) &&
            $pendingMfaState === "active";
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($_POST["cpf"] ?? ($_SESSION["login_last_cpf"] ?? ""));
        $wait = max($cpf ? \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::login_lock($cpf) : 0, \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_wait());
        if ($wait <= 0 && !$mfaStage) {
            \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_forget();
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            if ((string) ($_POST["act"] ?? "") === "mfa_verify") {
                $user = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::mfa_pending_login_user();
                $userMfaState = $user
                    ? \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state((int) ($user["id"] ?? 0))
                    : "inactive";
                if ($user && $userMfaState === "unavailable") {
                    \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::mfa_pending_login_clear();
                    $message =
                        "Não foi possível consultar a verificação em duas etapas agora. O acesso não foi liberado; tente novamente em instantes.";
                    if ($wantsJson) {
                        JsonResponder::send(
                            [
                                "ok" => false,
                                "stage" => "password",
                                "reset" => true,
                                "message" => $message,
                            ],
                            503,
                        );
                        return;
                    }
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($message, "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
                }
                if (!$user || $userMfaState !== "active") {
                    \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::mfa_pending_login_clear();
                    if ($wantsJson) {
                        JsonResponder::send(
                            [
                                "ok" => false,
                                "stage" => "password",
                                "reset" => true,
                                "message" =>
                                    "A verificação expirou. Informe novamente o CPF e a senha.",
                            ],
                            409,
                        );
                        return;
                    }
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "A verificação expirou. Informe novamente o CPF e a senha.",
                        "warn",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
                }
                $uid = (int) $user["id"];
                if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_attempt_limited($uid, "login")) {
                    if ($wantsJson) {
                        JsonResponder::send(
                            [
                                "ok" => false,
                                "stage" => "mfa",
                                "message" =>
                                    "Muitas tentativas de autenticação. Aguarde alguns minutos.",
                                "retry_after" => 300,
                            ],
                            429,
                        );
                        return;
                    }
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Muitas tentativas de autenticação. Aguarde alguns minutos.",
                        "bad",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
                }
                try {
                    if (
                        !\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_verify_user_code(
                            $uid,
                            (string) ($_POST["code"] ?? ""),
                        )
                    ) {
                        throw new RuntimeException(
                            "O código de autenticação não confere.",
                        );
                    }
                    $_SESSION["mfa_pending_verified"] = true;
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("mfa_validado", "usuario", $uid, [
                        "audit_body" =>
                            "Segundo fator do usuário validado na mesma tela do login antes da criação da sessão autenticada.",
                    ]);
                    \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_forget();
                    $destination = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_complete_pending_login(!$wantsJson);
                    if ($wantsJson) {
                        JsonResponder::send([
                            "ok" => true,
                            "stage" => "complete",
                            "redirect" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($destination),
                        ]);
                        return;
                    }
                } catch (Throwable $e) {
                    usleep(random_int(250000, 450000));
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("falha_mfa", "login", $uid, [
                        "motivo_hash" => hash("sha256", $e->getMessage()),
                    ]);
                    $message = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                        $e,
                        "Não foi possível confirmar o código de verificação.",
                    );
                    if ($wantsJson) {
                        JsonResponder::send(
                            [
                                "ok" => false,
                                "stage" => "mfa",
                                "message" => $message,
                            ],
                            401,
                        );
                        return;
                    }
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($message, "bad");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
                }
            }
            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::login_locks_cleanup_maybe();
            $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($_POST["cpf"] ?? "");
            $_SESSION["login_last_cpf"] = $cpf;
            $cpfValid = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf);
            [$loginSubjectHash, $loginIpHash] = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::login_key($cpf);
            $loginAttemptLock =
                "prontoo_login_" .
                substr(
                    hash("sha256", $loginSubjectHash . "|" . $loginIpHash),
                    0,
                    48,
                );
            $loginAttemptLocked = false;
            $loginAttemptState = "busy";
            $person = null;
            $userRow = null;
            $wait = 2;
            try {
                $loginAttemptLocked =
                    (int) \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val("SELECT GET_LOCK(?,2)", [$loginAttemptLock]) === 1;
                if ($loginAttemptLocked) {
                    $wait = max(
                        $cpf ? \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::login_lock($cpf) : 0,
                        \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_wait(),
                    );
                    if ($wait > 0) {
                        $loginAttemptState = "locked";
                    } else {
                        $person = $cpfValid
                            ? \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                                "SELECT p.full_name,p.cpf,p.birth_date,u.id uid,u.password_hash,u.active,u.is_global_admin
                                 FROM pi_persons p
                                 JOIN pi_users u ON u.person_id=p.id
                                 WHERE p.cpf=? LIMIT 1",
                                [$cpf],
                            )
                            : null;
                        $userRow = $person;
                        $passwordValid = $person && $userRow
                            ? password_verify(
                                (string) $_POST["password"],
                                (string) $person["password_hash"],
                            )
                            : password_verify(
                                (string) ($_POST["password"] ?? ""),
                                '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
                            );
                        if (
                            !$person ||
                            !$userRow ||
                            !(int) $person["active"] ||
                            !$passwordValid
                        ) {
                            $wait = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::login_fail($cpf);
                            $loginAttemptState = "invalid";
                        } else {
                            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::login_clear($cpf);
                            $loginAttemptState = "authenticated";
                        }
                    }
                }
            } finally {
                if ($loginAttemptLocked) {
                    try {
                        \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val("SELECT RELEASE_LOCK(?)", [$loginAttemptLock]);
                    } catch (Throwable $unlockError) {
                        error_log(
                            "[Prontoo login attempt unlock] " .
                                $unlockError->getMessage(),
                        );
                    }
                }
            }
            if (in_array($loginAttemptState, ["busy", "locked"], true)) {
                \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_remember(
                    $cpf,
                    $wait,
                    "Você tentou entrar muitas vezes.",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("falha_entrada", "login", null, [
                    "cpf" => $cpf,
                    "motivo" =>
                        $loginAttemptState === "busy"
                            ? "tentativa concorrente"
                            : "tentativa durante pausa",
                    "aguarde_segundos" => $wait,
                ]);
                if ($wantsJson) {
                    JsonResponder::send(
                        [
                            "ok" => false,
                            "stage" => "password",
                            "message" => "Você tentou entrar muitas vezes.",
                            "retry_after" => max(1, $wait),
                        ],
                        429,
                    );
                    return;
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
            }
            if ($loginAttemptState === "invalid") {
                \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_remember(
                    $cpf,
                    $wait,
                    "CPF ou senha não conferem.",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("falha_entrada", "login", null, [
                    "cpf" => $cpf,
                    "aguarde_segundos" => $wait,
                ]);
                if ($wantsJson) {
                    JsonResponder::send(
                        [
                            "ok" => false,
                            "stage" => "password",
                            "message" => "CPF ou senha não conferem.",
                            "retry_after" => max(1, $wait),
                        ],
                        401,
                    );
                    return;
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
            }
            \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_forget();
            $uid = (int) $person["uid"];
            $choices = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::active_clinic_roles_for_user($uid);
            $credential = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_resolve_user_credential(
                $uid,
                (int) $person["is_global_admin"] === 1,
                $choices,
            );
            if (!$credential) {
                $w = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::login_fail($cpf);
                \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_remember(
                    $cpf,
                    $w,
                    "CPF ou senha não conferem.",
                );
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("falha_entrada", "login", null, [
                    "cpf" => $cpf,
                    "motivo" => "sem vínculo clínico ativo",
                    "aguarde_segundos" => $w,
                ]);
                if ($wantsJson) {
                    JsonResponder::send(
                        [
                            "ok" => false,
                            "stage" => "password",
                            "message" => "CPF ou senha não conferem.",
                            "retry_after" => max(1, $w),
                        ],
                        401,
                    );
                    return;
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
            }
            $isGlobalAdmin = (int) $person["is_global_admin"] === 1;
            $mfaState = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state($uid);
            if ($mfaState === "unavailable") {
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("falha_mfa", "login", $uid, [
                    "motivo" => "cadastro_mfa_indisponivel",
                    "audit_body" =>
                        "A senha foi confirmada, mas o estado MFA não pôde ser comprovado; a sessão não foi criada.",
                ]);
                $message =
                    "Não foi possível consultar a verificação em duas etapas agora. O acesso não foi liberado; tente novamente em instantes.";
                if ($wantsJson) {
                    JsonResponder::send(
                        [
                            "ok" => false,
                            "stage" => "password",
                            "message" => $message,
                        ],
                        503,
                    );
                    return;
                }
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash($message, "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
            }
            $enrolled = $mfaState === "active";
            if ($isGlobalAdmin || $enrolled) {
                $_SESSION["login_last_cpf"] = $cpf;
                \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::mfa_begin_pending_login($uid, $credential);
                if ($isGlobalAdmin && !$enrolled) {
                    if ($wantsJson) {
                        JsonResponder::send([
                            "ok" => true,
                            "stage" => "enroll",
                            "redirect" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("mfa"),
                        ]);
                        return;
                    }
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("mfa");
                }
                if ($wantsJson) {
                    JsonResponder::send([
                        "ok" => true,
                        "stage" => "mfa",
                        "message" =>
                            "Senha confirmada. Agora, digite o código do aplicativo.",
                        "csrf" => \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::csrf(),
                    ]);
                    return;
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
            }
            RuntimeBootCoordinator::postPasswordMaintenance($uid);
            $destination = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_apply_resolved_credential(
                $uid,
                $credential,
                null,
                !$wantsJson,
            );
            if ($wantsJson) {
                JsonResponder::send([
                    "ok" => true,
                    "stage" => "complete",
                    "redirect" => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($destination),
                ]);
                return;
            }
        }
        $wait = $mfaStage
            ? 0
            : max($cpf ? \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::login_lock($cpf) : 0, \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_session_wait());
        $prefill = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($_SESSION["login_last_cpf"] ?? "");
        $lockTitle = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(
            (string) ($_SESSION["login_lock_message"] ?? "CPF ou senha não conferem."),
        );
        $reloginNotice =
            (string) ($_GET["relogin"] ?? "") === "1"
                ? '<div class="flash warn" role="status">Entre novamente para continuar.</div>'
                : "";
        $msg =
            !$mfaStage && $wait > 0
                ? '<div class="login-lock-panel" role="alert" aria-live="polite" data-login-wait data-login-lock-title="' .
                    $lockTitle .
                    '" data-login-lock-total="' .
                    max(1, $wait) .
                    '"><div class="login-lock-icon">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("lock_clock") .
                    '</div><div class="login-lock-copy"><strong>' .
                    $lockTitle .
                    '</strong><span>Tente novamente em <em data-countdown="' .
                    $wait .
                    '">' .
                    \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::seconds_label($wait) .
                    '</em>.</span></div><div class="login-lock-meter" aria-hidden="true"><i data-countdown-bar style="--progress:100%"></i></div></div>'
                : ($mfaStage ? "" : $reloginNotice);
        $bootStatus = $mfaStage
            ? "Senha confirmada. Agora, digite o código do aplicativo."
            : "Informe seu CPF e senha.";
        $bootIcon = $mfaStage ? "verified_user" : "login";
        $cpfAttributes =
            'required inputmode="numeric" autocomplete="username" maxlength="14" placeholder="000.000.000-00" data-login-cpf' .
            ($mfaStage ? ' readonly aria-readonly="true"' : "");
        $credentialField = $mfaStage
            ? '<input type="hidden" name="act" value="mfa_verify" data-login-act>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Código de verificação",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "code",
                        "text",
                        "",
                        'required autocomplete="one-time-code" maxlength="16" placeholder="Digite o código" autocapitalize="characters" spellcheck="false" aria-describedby="login-verification-help" data-login-code',
                    ),
                ) .
                '<small id="login-verification-help" class="field-help login-mfa-help" data-login-mfa-help>Abra seu aplicativo autenticador e digite o código exibido. Você também pode usar um código de recuperação.</small>'
            : \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Senha",
                '<div class="password-field">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "password",
                        "password",
                        "",
                        'required minlength="8" maxlength="128" autocomplete="current-password" placeholder="Sua senha" data-login-password data-password-toggle',
                    ) .
                    '<button type="button" class="password-toggle" data-password-toggle-button aria-label="Mostrar senha">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("visibility") .
                    "</button></div>",
            );
        $submitLabel = $mfaStage ? "Validar e entrar" : "Entrar";
        $submitIcon = $mfaStage ? "verified_user" : "hourglass_top";
        $form =
            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::login_telemetry_wave_html() .
            '<section class="auth login-card login-shell"><div class="auth-titleline login-titleline"><div class="auth-brandmark" data-app-favicon-brandmark><img class="auth-brandmark-favicon" src="/public/assets/app-icon-' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(PRONTOO_ASSET_REV) .
            '.png" alt="" aria-hidden="true"></div><div><span class="eyebrow">Prontoo</span><h1>Meu Consultório</h1></div></div>' .
            $msg .
            '<div class="login-boot' .
            ($mfaStage ? " is-ok" : "") .
            '" data-login-boot role="status" aria-live="polite"><span data-login-boot-icon>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($bootIcon) .
            "</span><small data-login-boot-status>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($bootStatus) .
            "</small></div>" .
            '<form method="post" data-login-form data-login-stage="' .
            ($mfaStage ? "mfa" : "password") .
            '"' .
            ' data-autotest-ready="1"' .
            ' data-login-locked="' .
            (!$mfaStage && $wait > 0 ? "1" : "0") .
            '" class="login-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "cpf",
                    "text",
                    $prefill,
                    $cpfAttributes,
                ),
            ) .
            $credentialField .
            '<button type="submit" class="primary wide login-submit" data-login-submit>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($submitIcon) .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($submitLabel) .
            "</span></button></form>" .
            ($mfaStage ||
            (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::class, 'clinic_signup_blocked']) && \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::clinic_signup_blocked())
                ? ""
                : '<div class="auth-footer"><span>Ainda não usa o Prontoo?</span><a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("signup") .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("home_health") .
                    "<span>Criar consultório</span></a></div>") .
            "</section>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Meu Consultório", $form, ["public" => true]);
    
    }

    public static function login_key(string $cpf): array
    
    {
    
        $secret = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key();
        return [
            hash_hmac("sha256", $cpf . "|subject", $secret),
            hash_hmac("sha256", ($_SERVER["REMOTE_ADDR"] ?? "") . "|ip", $secret),
        ];
    
    }

    public static function login_bucket_keys(string $cpf): array
    
    {
        [$subject, $ip] = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations03::login_key($cpf);
        $secret = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key();
        return [
            [$subject, $ip],
            [
                $subject,
                hash_hmac("sha256", "login|all-ip-addresses", $secret),
            ],
            [
                hash_hmac("sha256", "login|all-subjects", $secret),
                $ip,
            ],
        ];
    
    }

    public static function login_locks_cleanup_maybe(): void
    
    {
        if (random_int(1, 64) !== 1) {
            return;
        }
        try {
            \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "DELETE FROM pi_login_locks WHERE locked_until<UNIX_TIMESTAMP()-604800 LIMIT 500",
            );
        } catch (Throwable $e) {
            error_log("[Prontoo login lock cleanup] " . $e->getMessage());
        }
    
    }
}
