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

final class AuthOnboardingRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function page_login(): void
    
    {
    
        unset($_SESSION["pending_login_uid"], $_SESSION["pending_device_login"]);
        $wantsJson =
            function_exists("prontoo_route_wants_json") &&
            prontoo_route_wants_json("login");
        $currentCtx = ctx();
        if ($currentCtx) {
            redirect(
                ($currentCtx["scope"] ?? "") === "global"
                    ? "admin_painel"
                    : "appointments",
            );
        }
        $pendingMfaUser = mfa_pending_login_user();
        $pendingMfaState = is_array($pendingMfaUser)
            ? mfa_enrollment_state((int) ($pendingMfaUser["id"] ?? 0))
            : "inactive";
        $mfaStage =
            is_array($pendingMfaUser) &&
            $pendingMfaState === "active";
        $cpf = only_digits($_POST["cpf"] ?? ($_SESSION["login_last_cpf"] ?? ""));
        $wait = max($cpf ? login_lock($cpf) : 0, login_session_wait());
        if ($wait <= 0 && !$mfaStage) {
            login_session_forget();
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            if ((string) ($_POST["act"] ?? "") === "mfa_verify") {
                $user = mfa_pending_login_user();
                $userMfaState = $user
                    ? mfa_enrollment_state((int) ($user["id"] ?? 0))
                    : "inactive";
                if ($user && $userMfaState === "unavailable") {
                    mfa_pending_login_clear();
                    $message =
                        "Não foi possível consultar a verificação em duas etapas agora. O acesso não foi liberado; tente novamente em instantes.";
                    if ($wantsJson) {
                        prontoo_json_response(
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
                    flash($message, "bad");
                    redirect("login");
                }
                if (!$user || $userMfaState !== "active") {
                    mfa_pending_login_clear();
                    if ($wantsJson) {
                        prontoo_json_response(
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
                    flash(
                        "A verificação expirou. Informe novamente o CPF e a senha.",
                        "warn",
                    );
                    redirect("login");
                }
                $uid = (int) $user["id"];
                if (mfa_attempt_limited($uid, "login")) {
                    if ($wantsJson) {
                        prontoo_json_response(
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
                    flash(
                        "Muitas tentativas de autenticação. Aguarde alguns minutos.",
                        "bad",
                    );
                    redirect("login");
                }
                try {
                    if (
                        !mfa_verify_user_code(
                            $uid,
                            (string) ($_POST["code"] ?? ""),
                        )
                    ) {
                        throw new RuntimeException(
                            "O código de autenticação não confere.",
                        );
                    }
                    $_SESSION["mfa_pending_verified"] = true;
                    audit("mfa_validado", "usuario", $uid, [
                        "audit_body" =>
                            "Segundo fator do usuário validado na mesma tela do login antes da criação da sessão autenticada.",
                    ]);
                    login_session_forget();
                    $destination = mfa_complete_pending_login(!$wantsJson);
                    if ($wantsJson) {
                        prontoo_json_response([
                            "ok" => true,
                            "stage" => "complete",
                            "redirect" => href($destination),
                        ]);
                        return;
                    }
                } catch (Throwable $e) {
                    usleep(random_int(250000, 450000));
                    audit("falha_mfa", "login", $uid, [
                        "motivo_hash" => hash("sha256", $e->getMessage()),
                    ]);
                    $message = app_public_error_message(
                        $e,
                        "Não foi possível confirmar o código de verificação.",
                    );
                    if ($wantsJson) {
                        prontoo_json_response(
                            [
                                "ok" => false,
                                "stage" => "mfa",
                                "message" => $message,
                            ],
                            401,
                        );
                        return;
                    }
                    flash($message, "bad");
                    redirect("login");
                }
            }
            login_locks_cleanup_maybe();
            $cpf = only_digits($_POST["cpf"] ?? "");
            $_SESSION["login_last_cpf"] = $cpf;
            $cpfValid = valid_cpf($cpf);
            [$loginSubjectHash, $loginIpHash] = login_key($cpf);
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
                    (int) val("SELECT GET_LOCK(?,2)", [$loginAttemptLock]) === 1;
                if ($loginAttemptLocked) {
                    $wait = max(
                        $cpf ? login_lock($cpf) : 0,
                        login_session_wait(),
                    );
                    if ($wait > 0) {
                        $loginAttemptState = "locked";
                    } else {
                        $person = $cpfValid
                            ? one(
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
                            $wait = login_fail($cpf);
                            $loginAttemptState = "invalid";
                        } else {
                            login_clear($cpf);
                            $loginAttemptState = "authenticated";
                        }
                    }
                }
            } finally {
                if ($loginAttemptLocked) {
                    try {
                        val("SELECT RELEASE_LOCK(?)", [$loginAttemptLock]);
                    } catch (Throwable $unlockError) {
                        error_log(
                            "[Prontoo login attempt unlock] " .
                                $unlockError->getMessage(),
                        );
                    }
                }
            }
            if (in_array($loginAttemptState, ["busy", "locked"], true)) {
                login_session_remember(
                    $cpf,
                    $wait,
                    "Você tentou entrar muitas vezes.",
                );
                audit("falha_entrada", "login", null, [
                    "cpf" => $cpf,
                    "motivo" =>
                        $loginAttemptState === "busy"
                            ? "tentativa concorrente"
                            : "tentativa durante pausa",
                    "aguarde_segundos" => $wait,
                ]);
                if ($wantsJson) {
                    prontoo_json_response(
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
                redirect("login");
            }
            if ($loginAttemptState === "invalid") {
                login_session_remember(
                    $cpf,
                    $wait,
                    "CPF ou senha não conferem.",
                );
                audit("falha_entrada", "login", null, [
                    "cpf" => $cpf,
                    "aguarde_segundos" => $wait,
                ]);
                if ($wantsJson) {
                    prontoo_json_response(
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
                redirect("login");
            }
            login_session_forget();
            $uid = (int) $person["uid"];
            $choices = active_clinic_roles_for_user($uid);
            $credential = login_resolve_user_credential(
                $uid,
                (int) $person["is_global_admin"] === 1,
                $choices,
            );
            if (!$credential) {
                $w = login_fail($cpf);
                login_session_remember(
                    $cpf,
                    $w,
                    "CPF ou senha não conferem.",
                );
                audit("falha_entrada", "login", null, [
                    "cpf" => $cpf,
                    "motivo" => "sem vínculo clínico ativo",
                    "aguarde_segundos" => $w,
                ]);
                if ($wantsJson) {
                    prontoo_json_response(
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
                redirect("login");
            }
            $isGlobalAdmin = (int) $person["is_global_admin"] === 1;
            $mfaState = mfa_enrollment_state($uid);
            if ($mfaState === "unavailable") {
                audit("falha_mfa", "login", $uid, [
                    "motivo" => "cadastro_mfa_indisponivel",
                    "audit_body" =>
                        "A senha foi confirmada, mas o estado MFA não pôde ser comprovado; a sessão não foi criada.",
                ]);
                $message =
                    "Não foi possível consultar a verificação em duas etapas agora. O acesso não foi liberado; tente novamente em instantes.";
                if ($wantsJson) {
                    prontoo_json_response(
                        [
                            "ok" => false,
                            "stage" => "password",
                            "message" => $message,
                        ],
                        503,
                    );
                    return;
                }
                flash($message, "bad");
                redirect("login");
            }
            $enrolled = $mfaState === "active";
            if ($isGlobalAdmin || $enrolled) {
                $_SESSION["login_last_cpf"] = $cpf;
                mfa_begin_pending_login($uid, $credential);
                if ($isGlobalAdmin && !$enrolled) {
                    if ($wantsJson) {
                        prontoo_json_response([
                            "ok" => true,
                            "stage" => "enroll",
                            "redirect" => href("mfa"),
                        ]);
                        return;
                    }
                    redirect("mfa");
                }
                if ($wantsJson) {
                    prontoo_json_response([
                        "ok" => true,
                        "stage" => "mfa",
                        "message" =>
                            "Senha confirmada. Agora, digite o código do aplicativo.",
                        "csrf" => csrf(),
                    ]);
                    return;
                }
                redirect("login");
            }
            prontoo_login_post_password_maintenance($uid);
            $destination = login_apply_resolved_credential(
                $uid,
                $credential,
                null,
                !$wantsJson,
            );
            if ($wantsJson) {
                prontoo_json_response([
                    "ok" => true,
                    "stage" => "complete",
                    "redirect" => href($destination),
                ]);
                return;
            }
        }
        $wait = $mfaStage
            ? 0
            : max($cpf ? login_lock($cpf) : 0, login_session_wait());
        $prefill = e($_SESSION["login_last_cpf"] ?? "");
        $lockTitle = e(
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
                    icon("lock_clock") .
                    '</div><div class="login-lock-copy"><strong>' .
                    $lockTitle .
                    '</strong><span>Tente novamente em <em data-countdown="' .
                    $wait .
                    '">' .
                    seconds_label($wait) .
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
                form_row(
                    "Código de verificação",
                    input(
                        "code",
                        "text",
                        "",
                        'required autocomplete="one-time-code" maxlength="16" placeholder="Digite o código" autocapitalize="characters" spellcheck="false" aria-describedby="login-verification-help" data-login-code',
                    ),
                ) .
                '<small id="login-verification-help" class="field-help login-mfa-help" data-login-mfa-help>Abra seu aplicativo autenticador e digite o código exibido. Você também pode usar um código de recuperação.</small>'
            : form_row(
                "Senha",
                '<div class="password-field">' .
                    input(
                        "password",
                        "password",
                        "",
                        'required minlength="8" maxlength="128" autocomplete="current-password" placeholder="Sua senha" data-login-password data-password-toggle',
                    ) .
                    '<button type="button" class="password-toggle" data-password-toggle-button aria-label="Mostrar senha">' .
                    icon("visibility") .
                    "</button></div>",
            );
        $submitLabel = $mfaStage ? "Validar e entrar" : "Entrar";
        $submitIcon = $mfaStage ? "verified_user" : "hourglass_top";
        $form =
            login_telemetry_wave_html() .
            '<section class="auth login-card login-shell"><div class="auth-titleline login-titleline"><div class="auth-brandmark" data-app-favicon-brandmark><img class="auth-brandmark-favicon" src="/public/assets/app-icon-' .
            e(PRONTOO_ASSET_REV) .
            '.png" alt="" aria-hidden="true"></div><div><span class="eyebrow">Prontoo</span><h1>Meu Consultório</h1></div></div>' .
            $msg .
            '<div class="login-boot' .
            ($mfaStage ? " is-ok" : "") .
            '" data-login-boot role="status" aria-live="polite"><span data-login-boot-icon>' .
            icon($bootIcon) .
            "</span><small data-login-boot-status>" .
            e($bootStatus) .
            "</small></div>" .
            '<form method="post" data-login-form data-login-stage="' .
            ($mfaStage ? "mfa" : "password") .
            '"' .
            ' data-autotest-ready="1"' .
            ' data-login-locked="' .
            (!$mfaStage && $wait > 0 ? "1" : "0") .
            '" class="login-form">' .
            csrf_field() .
            form_row(
                "CPF",
                input(
                    "cpf",
                    "text",
                    $prefill,
                    $cpfAttributes,
                ),
            ) .
            $credentialField .
            '<button type="submit" class="primary wide login-submit" data-login-submit>' .
            icon($submitIcon) .
            "<span>" .
            e($submitLabel) .
            "</span></button></form>" .
            ($mfaStage ||
            (function_exists("clinic_signup_blocked") && clinic_signup_blocked())
                ? ""
                : '<div class="auth-footer"><span>Ainda não usa o Prontoo?</span><a class="ghost small" href="' .
                    href("signup") .
                    '">' .
                    icon("home_health") .
                    "<span>Criar consultório</span></a></div>") .
            "</section>";
        page("Meu Consultório", $form, ["public" => true]);
    
    }

    public static function login_key(string $cpf): array
    
    {
    
        $secret = secret_key();
        return [
            hash_hmac("sha256", $cpf . "|subject", $secret),
            hash_hmac("sha256", ($_SERVER["REMOTE_ADDR"] ?? "") . "|ip", $secret),
        ];
    
    }

    public static function login_bucket_keys(string $cpf): array
    
    {
        [$subject, $ip] = login_key($cpf);
        $secret = secret_key();
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
            q(
                "DELETE FROM pi_login_locks WHERE locked_until<UNIX_TIMESTAMP()-604800 LIMIT 500",
            );
        } catch (Throwable $e) {
            error_log("[Prontoo login lock cleanup] " . $e->getMessage());
        }
    
    }
}
