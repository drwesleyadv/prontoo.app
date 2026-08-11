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
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        $uid = (int) ($c["user"]["id"] ?? 0);
        if ($uid <= 0) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
        }
        $u = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.auth06.page_profile.01', [$uid], []);
        if (!$u) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
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
                            (int) (\Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.auth06.page_profile.02', [$email, $uid], []) ?:
                            0);
                        if ($emailOwner > 0) {
                            throw new RuntimeException(
                                "Este e-mail já está vinculado a outro usuário.",
                            );
                        }
                    }
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->atomic(
                        function () use ($name, $personId, $email, $uid): void {
                            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth06.page_profile.03', [$name, $personId], []);
                            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth06.page_profile.04', [$name, $email !== "" ? $email : null, $uid], []);
                            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("usuario_proprio_atualizado", "usuario", $uid, [
                                "target_name" => $name,
                                "audit_body" =>
                                    "O próprio usuário atualizou nome e e-mail cadastrais. CPF e nascimento permanecem imutáveis.",
                            ]);
                        },
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Dados do usuário atualizados.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
                }
                if ($act === "profile_mfa_prepare") {
                    $mfaState = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state($uid);
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
                    if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_attempt_limited($uid, "enrollment")) {
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
                        \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_secret_generate();
                    $_SESSION["profile_mfa_enrollment_issued_at"] = time();
                    $_SESSION["profile_mfa_password_verified_at"] = time();
                    $_SESSION["profile_mfa_enrollment_mode"] = "enable";
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("mfa_cadastro_iniciado", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário confirmou a senha e iniciou o cadastro opcional de MFA em Minha conta.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Senha confirmada. Siga as duas etapas abaixo.",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
                }
                if ($act === "profile_mfa_cancel") {
                    unset(
                        $_SESSION["profile_mfa_enrollment_secret"],
                        $_SESSION["profile_mfa_enrollment_issued_at"],
                        $_SESSION["profile_mfa_password_verified_at"],
                        $_SESSION["profile_mfa_enrollment_mode"],
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Configuração cancelada.", "warn");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
                }
                if ($act === "profile_mfa_enable") {
                    if (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state($uid) !== "inactive") {
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
                    if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_attempt_limited($uid, "enrollment")) {
                        throw new RuntimeException(
                            "Muitas tentativas. Aguarde alguns minutos.",
                        );
                    }
                    $codes = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enroll_user(
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
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($uid);
                    session_regenerate_id(true);
                    $_SESSION["csrf"] = bin2hex(random_bytes(32));
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("mfa_cadastrado", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário ativou MFA opcional em Minha conta; as demais sessões foram revogadas.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Verificação ativada. Guarde agora seus códigos de recuperação.",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
                }
                if ($act === "profile_mfa_recovery_ack") {
                    unset(
                        $_SESSION["profile_mfa_recovery_codes"],
                        $_SESSION["profile_mfa_recovery_codes_issued_at"],
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Códigos de recuperação confirmados.");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
                }
                if ($act === "profile_mfa_recovery_regenerate") {
                    if (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state($uid) !== "active") {
                        throw new RuntimeException(
                            "A verificação em duas etapas não está disponível agora.",
                        );
                    }
                    if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_attempt_limited($uid, "management")) {
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
                    $codes = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_recovery_codes_regenerate(
                        $uid,
                        (string) ($_POST["current_code"] ?? ""),
                    );
                    $_SESSION["profile_mfa_recovery_codes"] = $codes;
                    $_SESSION["profile_mfa_recovery_codes_issued_at"] = time();
                    $_SESSION["mfa_verified_at"] = time();
                    $_SESSION["user_auth_generation"] =
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($uid);
                    session_regenerate_id(true);
                    $_SESSION["csrf"] = bin2hex(random_bytes(32));
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("mfa_recuperacao_regenerada", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário regenerou os códigos de recuperação após confirmar senha e MFA; os códigos anteriores foram invalidados.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Novos códigos gerados. Guarde-os e confirme abaixo.",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
                }
                if ($act === "profile_mfa_replace_prepare") {
                    if (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state($uid) !== "active") {
                        throw new RuntimeException(
                            "A verificação em duas etapas não está disponível agora.",
                        );
                    }
                    if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_attempt_limited($uid, "management")) {
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
                        \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_secret_generate();
                    $_SESSION["profile_mfa_enrollment_issued_at"] = time();
                    $_SESSION["profile_mfa_password_verified_at"] = time();
                    $_SESSION["profile_mfa_enrollment_mode"] = "replace";
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("mfa_troca_iniciada", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário confirmou a senha e iniciou a troca reautenticada do aplicativo autenticador.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Senha confirmada. Siga as duas etapas abaixo para trocar o aplicativo.",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
                }
                if ($act === "profile_mfa_replace_enable") {
                    if (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state($uid) !== "active") {
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
                    if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_attempt_limited($uid, "management")) {
                        throw new RuntimeException(
                            "Muitas tentativas. Aguarde alguns minutos.",
                        );
                    }
                    $codes = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_replace_user(
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
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($uid);
                    session_regenerate_id(true);
                    $_SESSION["csrf"] = bin2hex(random_bytes(32));
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("mfa_autenticador_substituido", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário substituiu o autenticador após confirmar senha, MFA atual e novo TOTP; sessões e recuperações anteriores foram revogadas.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Autenticador substituído. Guarde os novos códigos de recuperação.",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
                }
                if ($act === "profile_mfa_disable") {
                    if ((int) ($u["is_global_admin"] ?? 0) === 1) {
                        throw new RuntimeException(
                            "A verificação em duas etapas é obrigatória para Desenvolvedor.",
                        );
                    }
                    if (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state($uid) !== "active") {
                        throw new RuntimeException(
                            "A verificação em duas etapas não está disponível agora.",
                        );
                    }
                    if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations02::mfa_attempt_limited($uid, "management")) {
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
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_disable_user(
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
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($uid);
                    session_regenerate_id(true);
                    $_SESSION["csrf"] = bin2hex(random_bytes(32));
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("mfa_desativado", "usuario", $uid, [
                        "audit_body" =>
                            "O próprio usuário regular desativou MFA após confirmar senha e segundo fator; as demais sessões foram revogadas.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Verificação em duas etapas desativada.", "warn");
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
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
                    if (!\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_ok($new)) {
                        throw new RuntimeException(
                            "A nova senha precisa ter entre 8 e 128 caracteres e não pode ser uma senha comum.",
                        );
                    }
                    if (password_verify($new, (string) $u["password_hash"])) {
                        throw new RuntimeException(
                            "A nova senha precisa ser diferente da senha atual.",
                        );
                    }
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->atomic(
                        function () use ($new, $uid, $u): void {
                            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth06.page_profile.05', [\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::password_hash_secure($new), $uid], []);
                            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($uid);
                            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_retire_persistent_devices_for_user($uid);
                            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("senha_redefinida", "usuario", $uid, [
                                "target_name" => (string) ($u["name"] ?? ""),
                                "audit_body" =>
                                    "O próprio usuário alterou a senha; todas as sessões anteriores foram revogadas.",
                            ]);
                        },
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::secure_session_destroy();
                    header(
                        "Location: " . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("login", ["relogin" => "1"]),
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
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("global_reauth");
                    }
                    if (!preg_match('/^role:(\d+)$/', $target, $m)) {
                        throw new RuntimeException("Escolha um ambiente válido.");
                    }
                    $roleId = (int) $m[1];
                    $link = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.auth06.page_profile.06', [$roleId, $uid], []);
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
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("area_trabalho_alterada", "usuario", $uid, [
                        "clinic_id" => $cid,
                        "role_code" => $role,
                        "role_label" => \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($role, $cid),
                        "audit_body" => "Ambiente alterado na página do usuário.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Ambiente alterado para " .
                            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($role, $cid) .
                            ".",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("appointments");
                }
                throw new RuntimeException("Ação de perfil inválida.");
            } catch (Throwable $e) {
                error_log("[Prontoo profile] " . $e->getMessage());
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_public_error_message(
                        $e,
                        "Não foi possível alterar o perfil agora.",
                    ),
                    "bad",
                );
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("profile");
            }
        }
        $backRoute = ($c["scope"] ?? "") === "global" ? "admin_painel" : "painel";
        $back =
            '<a class="ghost small" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($backRoute) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            "<span>Voltar</span></a>";
        $name = (string) ($u["name"] ?? "");
        $email = (string) ($u["email"] ?? "");
        $cpfDigits = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($u["cpf"] ?? ""));
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
        $birth = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::db_birth_date_input($u["birth_date"] ?? "");
        $dataForm =
            '<form method="post" class="compact account-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="profile_update_user">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome completo",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("name", "text", $name, 'required autocomplete="name"'),
            ) .
            '<div class="two immutable-account-fields">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "cpf_display",
                    "text",
                    $cpf,
                    'disabled readonly aria-disabled="true" tabindex="-1"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nascimento",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "birth_date_display",
                    "date",
                    $birth,
                    'disabled readonly aria-disabled="true" tabindex="-1"',
                ),
            ) .
            "</div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "E-mail",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("email", "email", $email, 'autocomplete="email"'),
            ) .
            '<div class="form-actions"><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
            "<span>Salvar</span></button></div></form>";
        $passwordForm =
            '<form method="post" class="compact account-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="profile_change_password">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Senha atual",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "current_password",
                    "password",
                    "",
                    'required autocomplete="current-password"',
                ),
            ) .
            '<div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nova senha",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "new_password",
                    "password",
                    "",
                    'required minlength="8" maxlength="128" autocomplete="new-password" data-password-strength',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Confirmar nova senha",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "new_password_confirm",
                    "password",
                    "",
                    'required minlength="8" maxlength="128" autocomplete="new-password"',
                ),
            ) .
            '</div><div class="form-actions"><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("key") .
            "<span>Alterar senha</span></button></div></form>";
        $mfaState = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state($uid);
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
        $managementPasswordField = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
            "Senha atual",
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                "current_password",
                "password",
                "",
                'required autocomplete="current-password"',
            ),
        );
        $managementCodeField = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
            "Código de verificação",
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
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
            $uri = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_otpauth_uri($account, $profileMfaSecret);
            $isReplacement = $profileMfaMode === "replace";
            $configurationFields = $isReplacement
                ? $managementCodeField .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                        "Código do novo aplicativo",
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                            "new_code",
                            "text",
                            "",
                            'required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000"',
                        ),
                    )
                : \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Código de verificação",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "code",
                        "text",
                        "",
                        'required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000"',
                    ),
                );
            $mfaPanel =
                '<section class="account-mfa-panel is-configuring" aria-labelledby="account-mfa-title"><div class="account-mfa-head"><span class="account-mfa-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("shield_lock") .
                '</span><div><span class="eyebrow">Proteção Avançada</span><h3 id="account-mfa-title">' .
                ($isReplacement
                    ? "Troque o aplicativo autenticador"
                    : "Ative a verificação em duas etapas") .
                "</h3><p>" .
                ($isReplacement
                    ? "Conecte o novo aplicativo e confirme um código do aplicativo atual e outro do novo."
                    : "Conecte seu aplicativo autenticador e confirme o primeiro código.") .
                '</p></div></div><div class="mfa-setup-step"><span class="mfa-step-number" aria-hidden="true">1</span><div class="mfa-step-content"><strong>Conecte o aplicativo</strong><small>Abra o aplicativo autenticador e adicione uma nova conta.</small><a class="ghost wide security-auth-launch" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($uri) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("open_in_new") .
                '<span>Abrir aplicativo autenticador</span></a><details class="mfa-manual-setup"><summary>Configurar com uma chave manual</summary><div class="mfa-secret"><div><span>Chave de configuração</span><small>No aplicativo, escolha a opção de inserir uma chave.</small></div><code tabindex="0" aria-label="Chave manual do autenticador">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($profileMfaSecret) .
                '</code></div></details></div></div><div class="mfa-setup-step"><span class="mfa-step-number" aria-hidden="true">2</span><div class="mfa-step-content"><strong>' .
                ($isReplacement
                    ? "Confirme os dois aplicativos"
                    : "Confirme o código") .
                '</strong><small>' .
                ($isReplacement
                    ? "Use um código do aplicativo atual e outro do novo."
                    : "Digite os seis dígitos exibidos pelo aplicativo.") .
                '</small><form method="post" class="compact account-mfa-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="' .
                ($isReplacement
                    ? "profile_mfa_replace_enable"
                    : "profile_mfa_enable") .
                '">' .
                $configurationFields .
                '<div class="form-actions account-mfa-actions"><button type="submit" class="primary">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check_circle") .
                "<span>" .
                ($isReplacement ? "Concluir troca" : "Ativar verificação") .
                '</span></button></div></form></div></div><form method="post" class="account-mfa-cancel-form">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="profile_mfa_cancel"><button type="submit" class="ghost small">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                "<span>Cancelar</span></button></form></section>";
        } elseif ($mfaEnrolled) {
            $recoveryHtml = "";
            if ($profileRecoveryCodes) {
                $items = "";
                foreach ($profileRecoveryCodes as $code) {
                    $items .=
                        '<li><code tabindex="0">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $code) .
                        "</code></li>";
                }
                $recoveryHtml =
                    '<div class="security-auth-notice" role="status"><span class="security-auth-notice-icon">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("key") .
                    '</span><div><strong>Guarde seus novos códigos</strong><span>Use-os para entrar se perder o acesso ao aplicativo. Cada código funciona uma única vez.</span></div></div><ul class="recovery-code-list" aria-label="Códigos de recuperação">' .
                    $items .
                    '</ul><form method="post" class="account-mfa-cancel-form">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="profile_mfa_recovery_ack"><button type="submit" class="ghost small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check") .
                    "<span>Já guardei</span></button></form>";
            }
            $disableForm =
                (int) ($u["is_global_admin"] ?? 0) === 1
                    ? '<div class="security-auth-notice security-auth-notice-soft"><span class="security-auth-notice-icon">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("policy") .
                        '</span><div><strong>Verificação obrigatória</strong><span>Esta proteção não pode ser desativada para Desenvolvedor.</span></div></div>'
                    : '<form method="post" class="compact account-mfa-form account-mfa-option is-critical"><header class="account-mfa-option-head"><span>' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("lock_open") .
                        '</span><div><strong>Desativar verificação</strong><small>Você voltará a entrar usando somente a senha.</small></div></header>' .
                        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                        '<input type="hidden" name="act" value="profile_mfa_disable">' .
                        $managementPasswordField .
                        $managementCodeField .
                        '<div class="form-actions"><button type="submit" class="ghost">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("lock_open") .
                        "<span>Desativar verificação</span></button></div></form>";
            $mfaPanel =
                '<section class="account-mfa-panel is-active" aria-labelledby="account-mfa-title"><div class="account-mfa-head"><span class="account-mfa-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified_user") .
                '</span><div><span class="eyebrow">Proteção Avançada</span><h3 id="account-mfa-title">Verificação em duas etapas ativa</h3><p>Ao entrar, o código do aplicativo será pedido logo após a senha.</p></div></div>' .
                $recoveryHtml .
                '<details class="account-mfa-management"><summary>Gerenciar verificação em duas etapas</summary><div class="account-mfa-management-grid"><form method="post" class="compact account-mfa-form account-mfa-option"><header class="account-mfa-option-head"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("key") .
                '</span><div><strong>Códigos de recuperação</strong><small>Gere outros se perdeu ou já usou os atuais.</small></div></header>' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="profile_mfa_recovery_regenerate">' .
                $managementPasswordField .
                $managementCodeField .
                '<div class="form-actions"><button type="submit" class="ghost">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("key") .
                '<span>Gerar novos códigos</span></button></div></form><form method="post" class="compact account-mfa-form account-mfa-option"><header class="account-mfa-option-head"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("sync_lock") .
                '</span><div><strong>Aplicativo autenticador</strong><small>Troque o aplicativo ou cadastre esta conta novamente.</small></div></header>' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="profile_mfa_replace_prepare">' .
                $managementPasswordField .
                '<div class="form-actions"><button type="submit" class="ghost">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("sync_lock") .
                "<span>Começar troca</span></button></div></form>" .
                $disableForm .
                "</div></details></section>";
        } elseif ($mfaState === "unavailable") {
            $mfaPanel =
                '<section class="account-mfa-panel" aria-labelledby="account-mfa-title"><div class="account-mfa-head"><span class="account-mfa-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("gpp_bad") .
                '</span><div><span class="eyebrow">Proteção Avançada</span><h3 id="account-mfa-title">Verificação temporariamente indisponível</h3><p>Não foi possível consultar esta proteção agora. Nenhuma alteração foi feita; tente novamente em instantes.</p></div></div></section>';
        } else {
            $mfaPanel =
                '<section class="account-mfa-panel" aria-labelledby="account-mfa-title"><div class="account-mfa-head"><span class="account-mfa-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("security_key") .
                '</span><div><span class="eyebrow">Proteção Avançada</span><h3 id="account-mfa-title">Verificação em duas etapas</h3><p>Além da senha, você usará um código do aplicativo autenticador para entrar.</p></div></div><form method="post" class="compact account-mfa-form account-mfa-start">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="profile_mfa_prepare">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Para continuar, confirme sua senha",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "current_password",
                        "password",
                        "",
                        'required autocomplete="current-password"',
                    ),
                ) .
                '<div class="form-actions account-mfa-actions"><button type="submit" class="primary">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("shield_lock") .
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
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("check_circle") .
                    "<span>Atual</span></span>"
                : '<span class="account-env-enter" aria-hidden="true">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("login") .
                    "</span>";
            return '<form method="post" class="account-env-item-form' .
                ($active ? " is-active" : "") .
                '">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="profile_switch_environment"><input type="hidden" name="environment" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value) .
                '"><button type="submit" class="account-env-option' .
                ($active ? " active" : "") .
                '"' .
                ($active ? ' disabled aria-disabled="true"' : "") .
                '><span class="account-env-mark">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
                '</span><span class="account-env-copy"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($subtitle) .
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
        $rows = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth06.page_profile.07', [$uid], [])->fetchAll();
        foreach ($rows as $r) {
            $rid = (int) $r["id"];
            $role = (string) $r["role_code"];
            $cid = (int) $r["clinic_id"];
            $label = (string) ($r["role_label"] ?: \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($role, $cid));
            $clinic = (string) ($r["clinic_name"] ?? "Consultório");
            $active = $currentScope === "clinic" && $currentUc === $rid;
            $envAppend(
                $envButton(
                    "role:" . $rid,
                    (string) ($r["icon_name"] ?: \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_icon($role, $cid)),
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
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Minha conta", "", $back) .
            '<section class="account-profile-grid"><article class="card account-card" id="sobre-mim"><h2>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("person") .
            '<span>Sobre Mim</span></h2><p class="muted-copy">Atualize os dados básicos vinculados ao seu acesso.</p>' .
            $dataForm .
            '</article><article class="card account-card" id="alteracao-de-senha"><h2>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("key") .
            '<span>Alteração de Senha</span></h2><p class="muted-copy">Confirme a senha atual para cadastrar uma nova senha.</p>' .
            $passwordForm .
            $mfaPanel .
            '</article><article class="card account-card account-env-card" id="meus-ambientes"><div class="account-env-card-head"><span class="account-env-card-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("workspaces") .
            '</span><div><span class="eyebrow">Acesso</span><h2>Meus ambientes</h2><p class="muted-copy">Escolha em qual consultório ou área você deseja trabalhar nesta sessão.</p></div></div>' .
            $envCards .
            "</article></section>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Minha conta", $body);
    
    }
}
