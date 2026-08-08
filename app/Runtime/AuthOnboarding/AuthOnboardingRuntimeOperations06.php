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

final class AuthOnboardingRuntimeOperations06
{
    private function __construct()
    {
    }

    public static function page_profile(): void
    
    {
    
        $c = need_login();
        $uid = (int) ($c["user"]["id"] ?? 0);
        if ($uid <= 0) {
            redirect("login");
        }
        $u = one(
            "SELECT u.id,u.person_id,u.name,u.email,u.password_hash,u.is_global_admin,p.cpf,p.birth_date FROM pi_users u JOIN pi_persons p ON p.id=u.person_id WHERE u.id=? AND u.active=1 LIMIT 1",
            [$uid],
        );
        if (!$u) {
            redirect("login");
        }
        $act = (string) ($_POST["act"] ?? "");
        $profileMfaIssuedAt = (int) (
            $_SESSION["profile_mfa_enrollment_issued_at"] ?? 0
        );
        if (
            $profileMfaIssuedAt > 0 &&
            time() - $profileMfaIssuedAt > 600
        ) {
            unset(
                $_SESSION["profile_mfa_enrollment_secret"],
                $_SESSION["profile_mfa_enrollment_issued_at"],
                $_SESSION["profile_mfa_password_verified_at"],
                $_SESSION["profile_mfa_enrollment_mode"],
            );
            $profileMfaIssuedAt = 0;
        }
        $profileMfaRecoveryIssuedAt = (int) (
            $_SESSION["profile_mfa_recovery_codes_issued_at"] ?? 0
        );
        if (
            $profileMfaRecoveryIssuedAt > 0 &&
            time() - $profileMfaRecoveryIssuedAt > 600
        ) {
            unset(
                $_SESSION["profile_mfa_recovery_codes"],
                $_SESSION["profile_mfa_recovery_codes_issued_at"],
            );
        }
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            if ($act === "") {
                $act = "profile_change_password";
            }
            try {
                if ($act === "profile_update_user") {
                    $name = mb_trim((string) ($_POST["name"] ?? ""));
                    $email = mb_trim((string) ($_POST["email"] ?? ""));
                    if ($name === "" || mb_strlen($name) < 3) {
                        throw new RuntimeException("Informe o nome completo.");
                    }
                    if (
                        $email !== "" &&
                        !filter_var($email, FILTER_VALIDATE_EMAIL)
                    ) {
                        throw new RuntimeException("Informe um e-mail válido.");
                    }
                    $personId = (int) $u["person_id"];
                    if ($email !== "") {
                        $emailOwner =
                            (int) (val(
                                "SELECT id FROM pi_users WHERE email=? AND id<>? LIMIT 1",
                                [$email, $uid],
                            ) ?:
                            0);
                        if ($emailOwner > 0) {
                            throw new RuntimeException(
                                "Este e-mail já está vinculado a outro usuário.",
                            );
                        }
                    }
                    db_begin_transaction();
                    q(
                        "UPDATE pi_persons SET full_name=?, updated_at=NOW() WHERE id=?",
                        [$name, $personId],
                    );
                    q(
                        "UPDATE pi_users SET name=?, email=?, updated_at=NOW() WHERE id=?",
                        [$name, $email !== "" ? $email : null, $uid],
                    );
                    audit("usuario_proprio_atualizado", "usuario", $uid, [
                        "target_name" => $name,
                        "audit_body" =>
                            "O próprio usuário atualizou nome e e-mail cadastrais. CPF e nascimento permanecem imutáveis.",
                    ]);
                    db_commit();
                    flash("Dados do usuário atualizados.");
                    redirect("profile");
                }
                if ($act === "profile_mfa_prepare") {
                    $mfaState = mfa_enrollment_state($uid);
                    if ($mfaState === "unavailable") {
                        throw new RuntimeException(
                            "Não foi possível consultar a verificação em duas etapas agora.",
                        );
                    }
                    if ($mfaState === "active") {
                        throw new RuntimeException(
                            "A verificação em duas etapas já está ativa.",
                        );
                    }
                    if (mfa_attempt_limited($uid, "enrollment")) {
                        throw new RuntimeException(
                            "Muitas tentativas. Aguarde alguns minutos.",
                        );
                    }
                    if (
                        !password_verify(
                            (string) ($_POST["current_password"] ?? ""),
                            (string) $u["password_hash"],
                        )
                    ) {
                        usleep(random_int(250000, 450000));
                        throw new RuntimeException("A senha atual não confere.");
                    }
                    $_SESSION["profile_mfa_enrollment_secret"] =
                        mfa_totp_secret_generate();
                    $_SESSION["profile_mfa_enrollment_issued_at"] = time();
                    $_SESSION["profile_mfa_password_verified_at"] = time();
                    $_SESSION["profile_mfa_enrollment_mode"] = "enable";
                    audit("mfa_cadastro_iniciado", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário confirmou a senha e iniciou o cadastro opcional de MFA em Minha conta.",
                    ]);
                    flash(
                        "Senha confirmada. Siga as duas etapas abaixo.",
                    );
                    redirect("profile");
                }
                if ($act === "profile_mfa_cancel") {
                    unset(
                        $_SESSION["profile_mfa_enrollment_secret"],
                        $_SESSION["profile_mfa_enrollment_issued_at"],
                        $_SESSION["profile_mfa_password_verified_at"],
                        $_SESSION["profile_mfa_enrollment_mode"],
                    );
                    flash("Configuração cancelada.", "warn");
                    redirect("profile");
                }
                if ($act === "profile_mfa_enable") {
                    if (mfa_enrollment_state($uid) !== "inactive") {
                        throw new RuntimeException(
                            "Não foi possível iniciar uma nova configuração.",
                        );
                    }
                    $secret = (string) (
                        $_SESSION["profile_mfa_enrollment_secret"] ?? ""
                    );
                    $issuedAt = (int) (
                        $_SESSION["profile_mfa_enrollment_issued_at"] ?? 0
                    );
                    $passwordVerifiedAt = (int) (
                        $_SESSION["profile_mfa_password_verified_at"] ?? 0
                    );
                    $enrollmentMode = (string) (
                        $_SESSION["profile_mfa_enrollment_mode"] ?? ""
                    );
                    if (
                        $secret === "" ||
                        $enrollmentMode !== "enable" ||
                        $issuedAt <= 0 ||
                        $passwordVerifiedAt <= 0 ||
                        time() - $issuedAt > 600 ||
                        time() - $passwordVerifiedAt > 600
                    ) {
                        throw new RuntimeException(
                            "A ativação expirou. Confirme novamente sua senha.",
                        );
                    }
                    if (mfa_attempt_limited($uid, "enrollment")) {
                        throw new RuntimeException(
                            "Muitas tentativas. Aguarde alguns minutos.",
                        );
                    }
                    $codes = mfa_enroll_user(
                        $uid,
                        $secret,
                        (string) ($_POST["code"] ?? ""),
                    );
                    $_SESSION["profile_mfa_recovery_codes"] = $codes;
                    $_SESSION["profile_mfa_recovery_codes_issued_at"] = time();
                    unset(
                        $_SESSION["profile_mfa_enrollment_secret"],
                        $_SESSION["profile_mfa_enrollment_issued_at"],
                        $_SESSION["profile_mfa_password_verified_at"],
                        $_SESSION["profile_mfa_enrollment_mode"],
                    );
                    $_SESSION["mfa_verified_at"] = time();
                    $_SESSION["user_auth_generation"] =
                        user_auth_generation_rotate($uid);
                    session_regenerate_id(true);
                    $_SESSION["csrf"] = bin2hex(random_bytes(32));
                    audit("mfa_cadastrado", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário ativou MFA opcional em Minha conta; as demais sessões foram revogadas.",
                    ]);
                    flash(
                        "Verificação ativada. Guarde agora seus códigos de recuperação.",
                    );
                    redirect("profile");
                }
                if ($act === "profile_mfa_recovery_ack") {
                    unset(
                        $_SESSION["profile_mfa_recovery_codes"],
                        $_SESSION["profile_mfa_recovery_codes_issued_at"],
                    );
                    flash("Códigos de recuperação confirmados.");
                    redirect("profile");
                }
                if ($act === "profile_mfa_recovery_regenerate") {
                    if (mfa_enrollment_state($uid) !== "active") {
                        throw new RuntimeException(
                            "A verificação em duas etapas não está disponível agora.",
                        );
                    }
                    if (mfa_attempt_limited($uid, "management")) {
                        throw new RuntimeException(
                            "Muitas tentativas. Aguarde alguns minutos.",
                        );
                    }
                    if (
                        !password_verify(
                            (string) ($_POST["current_password"] ?? ""),
                            (string) $u["password_hash"],
                        )
                    ) {
                        usleep(random_int(250000, 450000));
                        throw new RuntimeException("A senha atual não confere.");
                    }
                    $codes = mfa_recovery_codes_regenerate(
                        $uid,
                        (string) ($_POST["current_code"] ?? ""),
                    );
                    $_SESSION["profile_mfa_recovery_codes"] = $codes;
                    $_SESSION["profile_mfa_recovery_codes_issued_at"] = time();
                    $_SESSION["mfa_verified_at"] = time();
                    $_SESSION["user_auth_generation"] =
                        user_auth_generation_rotate($uid);
                    session_regenerate_id(true);
                    $_SESSION["csrf"] = bin2hex(random_bytes(32));
                    audit("mfa_recuperacao_regenerada", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário regenerou os códigos de recuperação após confirmar senha e MFA; os códigos anteriores foram invalidados.",
                    ]);
                    flash(
                        "Novos códigos gerados. Guarde-os e confirme abaixo.",
                    );
                    redirect("profile");
                }
                if ($act === "profile_mfa_replace_prepare") {
                    if (mfa_enrollment_state($uid) !== "active") {
                        throw new RuntimeException(
                            "A verificação em duas etapas não está disponível agora.",
                        );
                    }
                    if (mfa_attempt_limited($uid, "management")) {
                        throw new RuntimeException(
                            "Muitas tentativas. Aguarde alguns minutos.",
                        );
                    }
                    if (
                        !password_verify(
                            (string) ($_POST["current_password"] ?? ""),
                            (string) $u["password_hash"],
                        )
                    ) {
                        usleep(random_int(250000, 450000));
                        throw new RuntimeException("A senha atual não confere.");
                    }
                    $_SESSION["profile_mfa_enrollment_secret"] =
                        mfa_totp_secret_generate();
                    $_SESSION["profile_mfa_enrollment_issued_at"] = time();
                    $_SESSION["profile_mfa_password_verified_at"] = time();
                    $_SESSION["profile_mfa_enrollment_mode"] = "replace";
                    audit("mfa_troca_iniciada", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário confirmou a senha e iniciou a troca reautenticada do aplicativo autenticador.",
                    ]);
                    flash(
                        "Senha confirmada. Siga as duas etapas abaixo para trocar o aplicativo.",
                    );
                    redirect("profile");
                }
                if ($act === "profile_mfa_replace_enable") {
                    if (mfa_enrollment_state($uid) !== "active") {
                        throw new RuntimeException(
                            "A troca do aplicativo não está disponível agora.",
                        );
                    }
                    $secret = (string) (
                        $_SESSION["profile_mfa_enrollment_secret"] ?? ""
                    );
                    $issuedAt = (int) (
                        $_SESSION["profile_mfa_enrollment_issued_at"] ?? 0
                    );
                    $passwordVerifiedAt = (int) (
                        $_SESSION["profile_mfa_password_verified_at"] ?? 0
                    );
                    $enrollmentMode = (string) (
                        $_SESSION["profile_mfa_enrollment_mode"] ?? ""
                    );
                    if (
                        $secret === "" ||
                        $enrollmentMode !== "replace" ||
                        $issuedAt <= 0 ||
                        $passwordVerifiedAt <= 0 ||
                        time() - $issuedAt > 600 ||
                        time() - $passwordVerifiedAt > 600
                    ) {
                        throw new RuntimeException(
                            "A troca expirou. Confirme novamente sua senha.",
                        );
                    }
                    if (mfa_attempt_limited($uid, "management")) {
                        throw new RuntimeException(
                            "Muitas tentativas. Aguarde alguns minutos.",
                        );
                    }
                    $codes = mfa_replace_user(
                        $uid,
                        (string) ($_POST["current_code"] ?? ""),
                        $secret,
                        (string) ($_POST["new_code"] ?? ""),
                    );
                    $_SESSION["profile_mfa_recovery_codes"] = $codes;
                    $_SESSION["profile_mfa_recovery_codes_issued_at"] = time();
                    unset(
                        $_SESSION["profile_mfa_enrollment_secret"],
                        $_SESSION["profile_mfa_enrollment_issued_at"],
                        $_SESSION["profile_mfa_password_verified_at"],
                        $_SESSION["profile_mfa_enrollment_mode"],
                    );
                    $_SESSION["mfa_verified_at"] = time();
                    $_SESSION["user_auth_generation"] =
                        user_auth_generation_rotate($uid);
                    session_regenerate_id(true);
                    $_SESSION["csrf"] = bin2hex(random_bytes(32));
                    audit("mfa_autenticador_substituido", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário substituiu o autenticador após confirmar senha, MFA atual e novo TOTP; sessões e recuperações anteriores foram revogadas.",
                    ]);
                    flash(
                        "Autenticador substituído. Guarde os novos códigos de recuperação.",
                    );
                    redirect("profile");
                }
                if ($act === "profile_mfa_disable") {
                    if ((int) ($u["is_global_admin"] ?? 0) === 1) {
                        throw new RuntimeException(
                            "A verificação em duas etapas é obrigatória para Desenvolvedor.",
                        );
                    }
                    if (mfa_enrollment_state($uid) !== "active") {
                        throw new RuntimeException(
                            "A verificação em duas etapas não está disponível agora.",
                        );
                    }
                    if (mfa_attempt_limited($uid, "management")) {
                        throw new RuntimeException(
                            "Muitas tentativas. Aguarde alguns minutos.",
                        );
                    }
                    if (
                        !password_verify(
                            (string) ($_POST["current_password"] ?? ""),
                            (string) $u["password_hash"],
                        )
                    ) {
                        usleep(random_int(250000, 450000));
                        throw new RuntimeException("A senha atual não confere.");
                    }
                    mfa_disable_user(
                        $uid,
                        (string) ($_POST["current_code"] ?? ""),
                    );
                    unset(
                        $_SESSION["mfa_verified_at"],
                        $_SESSION["privileged_auth_at"],
                        $_SESSION["profile_mfa_recovery_codes"],
                        $_SESSION["profile_mfa_recovery_codes_issued_at"],
                    );
                    $_SESSION["user_auth_generation"] =
                        user_auth_generation_rotate($uid);
                    session_regenerate_id(true);
                    $_SESSION["csrf"] = bin2hex(random_bytes(32));
                    audit("mfa_desativado", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário regular desativou MFA após confirmar senha e segundo fator; as demais sessões foram revogadas.",
                    ]);
                    flash("Verificação em duas etapas desativada.", "warn");
                    redirect("profile");
                }
                if ($act === "profile_change_password") {
                    $current = (string) ($_POST["current_password"] ?? "");
                    $new = (string) ($_POST["new_password"] ?? "");
                    $confirm = (string) ($_POST["new_password_confirm"] ?? "");
                    if (!password_verify($current, (string) $u["password_hash"])) {
                        throw new RuntimeException("A senha atual não confere.");
                    }
                    if ($new !== $confirm) {
                        throw new RuntimeException(
                            "A confirmação da nova senha não confere.",
                        );
                    }
                    if (!password_ok($new)) {
                        throw new RuntimeException(
                            "A nova senha precisa ter entre 8 e 128 caracteres e não pode ser uma senha comum.",
                        );
                    }
                    if (password_verify($new, (string) $u["password_hash"])) {
                        throw new RuntimeException(
                            "A nova senha precisa ser diferente da senha atual.",
                        );
                    }
                    db_begin_transaction();
                    q(
                        "UPDATE pi_users SET password_hash=?, updated_at=NOW() WHERE id=?",
                        [password_hash_secure($new), $uid],
                    );
                    user_auth_generation_rotate($uid);
                    security_retire_persistent_devices_for_user($uid);
                    audit("senha_redefinida", "usuario", $uid, [
                        "target_name" => (string) ($u["name"] ?? ""),
                        "audit_body" =>
                            "O próprio usuário alterou a senha; todas as sessões anteriores foram revogadas.",
                    ]);
                    db_commit();
                    secure_session_destroy();
                    header(
                        "Location: " . href("login", ["relogin" => "1"]),
                    );
                    exit();
                }
                if ($act === "profile_switch_environment") {
                    $target = (string) ($_POST["environment"] ?? "");
                    if ($target === "global") {
                        if ((int) ($u["is_global_admin"] ?? 0) !== 1) {
                            throw new RuntimeException(
                                "Ambiente indisponível para este usuário.",
                            );
                        }
                        redirect("global_reauth");
                    }
                    if (!preg_match('/^role:(\d+)$/', $target, $m)) {
                        throw new RuntimeException("Escolha um ambiente válido.");
                    }
                    $roleId = (int) $m[1];
                    $link = one(
                        "SELECT ur.id,ur.clinic_id,ur.role_code,COALESCE(NULLIF(cr.label,''),ur.role_code) role_label FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id AND c.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE ur.id=? AND ur.user_id=? AND ur.active=1 LIMIT 1",
                        [$roleId, $uid],
                    );
                    if (!$link) {
                        throw new RuntimeException(
                            "Ambiente indisponível para este usuário.",
                        );
                    }
                    $cid = (int) $link["clinic_id"];
                    $role = (string) $link["role_code"];
                    $_SESSION["uid"] = $uid;
                    $_SESSION["scope"] = "clinic";
                    $_SESSION["uc_id"] = (int) $link["id"];
                    $_SESSION["clinic_id"] = $cid;
                    $_SESSION["role_code"] = $role;
                    $_SESSION["effective_roles"] = [$role];
                    audit("area_trabalho_alterada", "usuario", $uid, [
                        "clinic_id" => $cid,
                        "role_code" => $role,
                        "role_label" => role_label_for($role, $cid),
                        "audit_body" => "Ambiente alterado na página do usuário.",
                    ]);
                    flash(
                        "Ambiente alterado para " .
                            role_label_for($role, $cid) .
                            ".",
                    );
                    redirect("appointments");
                }
                throw new RuntimeException("Ação de perfil inválida.");
            } catch (Throwable $e) {
                if (function_exists("pdo") && pdo()->inTransaction()) {
                    db_rollback();
                }
                error_log("[Prontoo profile] " . $e->getMessage());
                flash(
                    app_public_error_message(
                        $e,
                        "Não foi possível alterar o perfil agora.",
                    ),
                    "bad",
                );
                redirect("profile");
            }
        }
        $backRoute = ($c["scope"] ?? "") === "global" ? "admin_painel" : "painel";
        $back =
            '<a class="ghost small" href="' .
            href($backRoute) .
            '">' .
            icon("arrow_back") .
            "<span>Voltar</span></a>";
        $name = (string) ($u["name"] ?? "");
        $email = (string) ($u["email"] ?? "");
        $cpfDigits = only_digits((string) ($u["cpf"] ?? ""));
        $cpf =
            $cpfDigits !== ""
                ? (strlen($cpfDigits) === 11
                    ? preg_replace(
                        '/^(\d{3})(\d{3})(\d{3})(\d{2})$/',
                        '$1.$2.$3-$4',
                        $cpfDigits,
                    )
                    : $cpfDigits)
                : "";
        $birth = db_birth_date_input($u["birth_date"] ?? "");
        $dataForm =
            '<form method="post" class="compact account-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="profile_update_user">' .
            form_row(
                "Nome completo",
                input("name", "text", $name, 'required autocomplete="name"'),
            ) .
            '<div class="two immutable-account-fields">' .
            form_row(
                "CPF",
                input(
                    "cpf_display",
                    "text",
                    $cpf,
                    'disabled readonly aria-disabled="true" tabindex="-1"',
                ),
            ) .
            form_row(
                "Nascimento",
                input(
                    "birth_date_display",
                    "date",
                    $birth,
                    'disabled readonly aria-disabled="true" tabindex="-1"',
                ),
            ) .
            "</div>" .
            form_row(
                "E-mail",
                input("email", "email", $email, 'autocomplete="email"'),
            ) .
            '<div class="form-actions"><button type="submit" class="primary">' .
            icon("save") .
            "<span>Salvar</span></button></div></form>";
        $passwordForm =
            '<form method="post" class="compact account-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="profile_change_password">' .
            form_row(
                "Senha atual",
                input(
                    "current_password",
                    "password",
                    "",
                    'required autocomplete="current-password"',
                ),
            ) .
            '<div class="two">' .
            form_row(
                "Nova senha",
                input(
                    "new_password",
                    "password",
                    "",
                    'required minlength="8" maxlength="128" autocomplete="new-password" data-password-strength',
                ),
            ) .
            form_row(
                "Confirmar nova senha",
                input(
                    "new_password_confirm",
                    "password",
                    "",
                    'required minlength="8" maxlength="128" autocomplete="new-password"',
                ),
            ) .
            '</div><div class="form-actions"><button type="submit" class="primary">' .
            icon("key") .
            "<span>Alterar senha</span></button></div></form>";
        $mfaState = mfa_enrollment_state($uid);
        $mfaEnrolled = $mfaState === "active";
        $profileMfaSecret = (string) (
            $_SESSION["profile_mfa_enrollment_secret"] ?? ""
        );
        $profileMfaMode = (string) (
            $_SESSION["profile_mfa_enrollment_mode"] ?? ""
        );
        $profileRecoveryCodes = (array) (
            $_SESSION["profile_mfa_recovery_codes"] ?? []
        );
        $managementPasswordField = form_row(
            "Senha atual",
            input(
                "current_password",
                "password",
                "",
                'required autocomplete="current-password"',
            ),
        );
        $managementCodeField = form_row(
            "Código de verificação",
            input(
                "current_code",
                "text",
                "",
                'required autocomplete="one-time-code" maxlength="16" placeholder="Digite o código"',
            ),
        );
        if ($profileMfaSecret !== "") {
            $account =
                mb_trim((string) ($u["email"] ?? "")) ?:
                ((string) ($u["name"] ?? "Usuário") . " #" . $uid);
            $uri = mfa_otpauth_uri($account, $profileMfaSecret);
            $isReplacement = $profileMfaMode === "replace";
            $configurationFields = $isReplacement
                ? $managementCodeField .
                    form_row(
                        "Código do novo aplicativo",
                        input(
                            "new_code",
                            "text",
                            "",
                            'required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000"',
                        ),
                    )
                : form_row(
                    "Código de verificação",
                    input(
                        "code",
                        "text",
                        "",
                        'required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000"',
                    ),
                );
            $mfaPanel =
                '<section class="account-mfa-panel is-configuring" aria-labelledby="account-mfa-title"><div class="account-mfa-head"><span class="account-mfa-icon">' .
                icon("shield_lock") .
                '</span><div><span class="eyebrow">Proteção Avançada</span><h3 id="account-mfa-title">' .
                ($isReplacement
                    ? "Troque o aplicativo autenticador"
                    : "Ative a verificação em duas etapas") .
                "</h3><p>" .
                ($isReplacement
                    ? "Conecte o novo aplicativo e confirme um código do aplicativo atual e outro do novo."
                    : "Conecte seu aplicativo autenticador e confirme o primeiro código.") .
                '</p></div></div><div class="mfa-setup-step"><span class="mfa-step-number" aria-hidden="true">1</span><div class="mfa-step-content"><strong>Conecte o aplicativo</strong><small>Abra o aplicativo autenticador e adicione uma nova conta.</small><a class="ghost wide security-auth-launch" href="' .
                e($uri) .
                '">' .
                icon("open_in_new") .
                '<span>Abrir aplicativo autenticador</span></a><details class="mfa-manual-setup"><summary>Configurar com uma chave manual</summary><div class="mfa-secret"><div><span>Chave de configuração</span><small>No aplicativo, escolha a opção de inserir uma chave.</small></div><code tabindex="0" aria-label="Chave manual do autenticador">' .
                e($profileMfaSecret) .
                '</code></div></details></div></div><div class="mfa-setup-step"><span class="mfa-step-number" aria-hidden="true">2</span><div class="mfa-step-content"><strong>' .
                ($isReplacement
                    ? "Confirme os dois aplicativos"
                    : "Confirme o código") .
                '</strong><small>' .
                ($isReplacement
                    ? "Use um código do aplicativo atual e outro do novo."
                    : "Digite os seis dígitos exibidos pelo aplicativo.") .
                '</small><form method="post" class="compact account-mfa-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="' .
                ($isReplacement
                    ? "profile_mfa_replace_enable"
                    : "profile_mfa_enable") .
                '">' .
                $configurationFields .
                '<div class="form-actions account-mfa-actions"><button type="submit" class="primary">' .
                icon("check_circle") .
                "<span>" .
                ($isReplacement ? "Concluir troca" : "Ativar verificação") .
                '</span></button></div></form></div></div><form method="post" class="account-mfa-cancel-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="profile_mfa_cancel"><button type="submit" class="ghost small">' .
                icon("close") .
                "<span>Cancelar</span></button></form></section>";
        } elseif ($mfaEnrolled) {
            $recoveryHtml = "";
            if ($profileRecoveryCodes) {
                $items = "";
                foreach ($profileRecoveryCodes as $code) {
                    $items .=
                        '<li><code tabindex="0">' .
                        e((string) $code) .
                        "</code></li>";
                }
                $recoveryHtml =
                    '<div class="security-auth-notice" role="status"><span class="security-auth-notice-icon">' .
                    icon("key") .
                    '</span><div><strong>Guarde seus novos códigos</strong><span>Use-os para entrar se perder o acesso ao aplicativo. Cada código funciona uma única vez.</span></div></div><ul class="recovery-code-list" aria-label="Códigos de recuperação">' .
                    $items .
                    '</ul><form method="post" class="account-mfa-cancel-form">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="profile_mfa_recovery_ack"><button type="submit" class="ghost small">' .
                    icon("check") .
                    "<span>Já guardei</span></button></form>";
            }
            $disableForm =
                (int) ($u["is_global_admin"] ?? 0) === 1
                    ? '<div class="security-auth-notice security-auth-notice-soft"><span class="security-auth-notice-icon">' .
                        icon("policy") .
                        '</span><div><strong>Verificação obrigatória</strong><span>Esta proteção não pode ser desativada para Desenvolvedor.</span></div></div>'
                    : '<form method="post" class="compact account-mfa-form account-mfa-option is-critical"><header class="account-mfa-option-head"><span>' .
                        icon("lock_open") .
                        '</span><div><strong>Desativar verificação</strong><small>Você voltará a entrar usando somente a senha.</small></div></header>' .
                        csrf_field() .
                        '<input type="hidden" name="act" value="profile_mfa_disable">' .
                        $managementPasswordField .
                        $managementCodeField .
                        '<div class="form-actions"><button type="submit" class="ghost">' .
                        icon("lock_open") .
                        "<span>Desativar verificação</span></button></div></form>";
            $mfaPanel =
                '<section class="account-mfa-panel is-active" aria-labelledby="account-mfa-title"><div class="account-mfa-head"><span class="account-mfa-icon">' .
                icon("verified_user") .
                '</span><div><span class="eyebrow">Proteção Avançada</span><h3 id="account-mfa-title">Verificação em duas etapas ativa</h3><p>Ao entrar, o código do aplicativo será pedido logo após a senha.</p></div></div>' .
                $recoveryHtml .
                '<details class="account-mfa-management"><summary>Gerenciar verificação em duas etapas</summary><div class="account-mfa-management-grid"><form method="post" class="compact account-mfa-form account-mfa-option"><header class="account-mfa-option-head"><span>' .
                icon("key") .
                '</span><div><strong>Códigos de recuperação</strong><small>Gere outros se perdeu ou já usou os atuais.</small></div></header>' .
                csrf_field() .
                '<input type="hidden" name="act" value="profile_mfa_recovery_regenerate">' .
                $managementPasswordField .
                $managementCodeField .
                '<div class="form-actions"><button type="submit" class="ghost">' .
                icon("key") .
                '<span>Gerar novos códigos</span></button></div></form><form method="post" class="compact account-mfa-form account-mfa-option"><header class="account-mfa-option-head"><span>' .
                icon("sync_lock") .
                '</span><div><strong>Aplicativo autenticador</strong><small>Troque o aplicativo ou cadastre esta conta novamente.</small></div></header>' .
                csrf_field() .
                '<input type="hidden" name="act" value="profile_mfa_replace_prepare">' .
                $managementPasswordField .
                '<div class="form-actions"><button type="submit" class="ghost">' .
                icon("sync_lock") .
                "<span>Começar troca</span></button></div></form>" .
                $disableForm .
                "</div></details></section>";
        } elseif ($mfaState === "unavailable") {
            $mfaPanel =
                '<section class="account-mfa-panel" aria-labelledby="account-mfa-title"><div class="account-mfa-head"><span class="account-mfa-icon">' .
                icon("gpp_bad") .
                '</span><div><span class="eyebrow">Proteção Avançada</span><h3 id="account-mfa-title">Verificação temporariamente indisponível</h3><p>Não foi possível consultar esta proteção agora. Nenhuma alteração foi feita; tente novamente em instantes.</p></div></div></section>';
        } else {
            $mfaPanel =
                '<section class="account-mfa-panel" aria-labelledby="account-mfa-title"><div class="account-mfa-head"><span class="account-mfa-icon">' .
                icon("security_key") .
                '</span><div><span class="eyebrow">Proteção Avançada</span><h3 id="account-mfa-title">Verificação em duas etapas</h3><p>Além da senha, você usará um código do aplicativo autenticador para entrar.</p></div></div><form method="post" class="compact account-mfa-form account-mfa-start">' .
                csrf_field() .
                '<input type="hidden" name="act" value="profile_mfa_prepare">' .
                form_row(
                    "Para continuar, confirme sua senha",
                    input(
                        "current_password",
                        "password",
                        "",
                        'required autocomplete="current-password"',
                    ),
                ) .
                '<div class="form-actions account-mfa-actions"><button type="submit" class="primary">' .
                icon("shield_lock") .
                "<span>Começar configuração</span></button></div></form></section>";
        }
        $envCards = "";
        $envActiveCards = "";
        $envOtherCards = "";
        $currentScope = (string) ($c["scope"] ?? "");
        $currentUc = (int) ($_SESSION["uc_id"] ?? 0);
        $envButton = function (
            string $value,
            string $iconName,
            string $title,
            string $subtitle,
            bool $active,
        ): string {
    
            $state = $active
                ? '<span class="account-env-status">' .
                    icon("check_circle") .
                    "<span>Atual</span></span>"
                : '<span class="account-env-enter" aria-hidden="true">' .
                    icon("login") .
                    "</span>";
            return '<form method="post" class="account-env-item-form' .
                ($active ? " is-active" : "") .
                '">' .
                csrf_field() .
                '<input type="hidden" name="act" value="profile_switch_environment"><input type="hidden" name="environment" value="' .
                e($value) .
                '"><button type="submit" class="account-env-option' .
                ($active ? " active" : "") .
                '"' .
                ($active ? ' disabled aria-disabled="true"' : "") .
                '><span class="account-env-mark">' .
                icon($iconName) .
                '</span><span class="account-env-copy"><strong>' .
                e($title) .
                "</strong><small>" .
                e($subtitle) .
                "</small></span>" .
                $state .
                "</button></form>";
        };
        $envAppend = function (string $html, bool $active) use (
            &$envActiveCards,
            &$envOtherCards,
        ): void {
    
            if ($active) {
                $envActiveCards .= $html;
            } else {
                $envOtherCards .= $html;
            }
        };
        if ((int) ($u["is_global_admin"] ?? 0) === 1) {
            $active = $currentScope === "global";
            $envAppend(
                $envButton(
                    "global",
                    "admin_panel_settings",
                    "Desenvolvedor",
                    "Painel do Desenvolvedor",
                    $active,
                ),
                $active,
            );
        }
        $rows = q(
            "SELECT ur.id,ur.clinic_id,ur.role_code,c.display_name clinic_name,COALESCE(NULLIF(cr.label,''),ur.role_code) role_label,COALESCE(NULLIF(cr.icon_name,''),'workspaces') icon_name FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id AND c.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE ur.user_id=? AND ur.active=1 ORDER BY c.display_name ASC, ur.is_owner DESC, FIELD(ur.role_code,'gerente','medico','assistente','recepcionista'), ur.id ASC",
            [$uid],
        )->fetchAll();
        foreach ($rows as $r) {
            $rid = (int) $r["id"];
            $role = (string) $r["role_code"];
            $cid = (int) $r["clinic_id"];
            $label = (string) ($r["role_label"] ?: role_label_for($role, $cid));
            $clinic = (string) ($r["clinic_name"] ?? "Consultório");
            $active = $currentScope === "clinic" && $currentUc === $rid;
            $envAppend(
                $envButton(
                    "role:" . $rid,
                    (string) ($r["icon_name"] ?: role_icon($role, $cid)),
                    $label,
                    $clinic,
                    $active,
                ),
                $active,
            );
        }
        $envCards = $envActiveCards . $envOtherCards;
        if ($envCards === "") {
            $envCards =
                '<div class="empty">Nenhum ambiente ativo disponível para este usuário.</div>';
        } else {
            $envCards = '<div class="account-env-grid">' . $envCards . "</div>";
        }
        $body =
            page_head("Minha conta", "", $back) .
            '<section class="account-profile-grid"><article class="card account-card" id="sobre-mim"><h2>' .
            icon("person") .
            '<span>Sobre Mim</span></h2><p class="muted-copy">Atualize os dados básicos vinculados ao seu acesso.</p>' .
            $dataForm .
            '</article><article class="card account-card" id="alteracao-de-senha"><h2>' .
            icon("key") .
            '<span>Alteração de Senha</span></h2><p class="muted-copy">Confirme a senha atual para cadastrar uma nova senha.</p>' .
            $passwordForm .
            $mfaPanel .
            '</article><article class="card account-card account-env-card" id="meus-ambientes"><div class="account-env-card-head"><span class="account-env-card-icon">' .
            icon("workspaces") .
            '</span><div><span class="eyebrow">Acesso</span><h2>Meus ambientes</h2><p class="muted-copy">Escolha em qual consultório ou área você deseja trabalhar nesta sessão.</p></div></div>' .
            $envCards .
            "</article></section>";
        page("Minha conta", $body);
    
    }
}
