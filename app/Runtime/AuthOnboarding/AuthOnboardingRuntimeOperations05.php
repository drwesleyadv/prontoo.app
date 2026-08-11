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

final class AuthOnboardingRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function upsert_person(string $name, string $cpf, string $birth): int
    
    {
    
        $name = trim($name);
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cpf);
        if ($name === "") {
            throw new RuntimeException("Nome da pessoa não informado.");
        }
        if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            throw new RuntimeException("Este CPF não existe.");
        }
        if (!\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($birth)) {
            throw new RuntimeException("Nascimento inválido.");
        }
    
        $id = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.auth05.upsert_person.01', [$cpf], []);
        if ($id) {
            $id = (int) $id;
            $identity = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_identity_immutable_values($id, $cpf, $birth, true);
            $expectedBirth = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(
                (string) $identity["birth_date"],
            );
            $current = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.auth05.upsert_person.02', [$id], []) ?: [];
            $params = [];
            $updateName = mb_trim((string) ($current["full_name"] ?? "")) === "";
            if ($updateName) {
                $params[] = $name;
            }
            $currentBirth = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(
                (string) ($current["birth_date"] ?? ""),
            );
            $updateBirth = $currentBirth === "" || !\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($currentBirth);
            if ($updateBirth) {
                $params[] = $expectedBirth;
            }
            if ($updateName || $updateBirth) {
                $params[] = $id;
                \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result(
                    'identity.auth05.upsert_person.03',
                    $params,
                    ['update_name' => $updateName, 'update_birth' => $updateBirth],
                );
            }
            $storedBirth = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(
                (string) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.auth05.upsert_person.04', [$id], []),
            );
            if ($storedBirth === "" || $storedBirth !== $expectedBirth) {
                throw new RuntimeException(
                    "A data de nascimento não pôde ser registrada corretamente.",
                );
            }
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_refresh_verified($id);
            return $id;
        }
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth05.upsert_person.05', [$name, $cpf, $birth, \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_value($cpf, $name, $birth)], []);
        $id = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->lastInsertId();
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_refresh_verified($id);
        return $id;
    
    }

    public static function lock_person_user_identity(int $personId): void
    
    {
    
        if ($personId <= 0) {
            throw new RuntimeException(
                "Não foi possível iniciar a gravação segura do usuário.",
            );
        }
        $locked = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.auth05.lock_person_user_identity.01', [$personId], []);
        if (!$locked) {
            throw new RuntimeException(
                "A pessoa vinculada ao usuário não foi encontrada.",
            );
        }
    
    }

    public static function save_person_flexible(
        string $name,
        ?string $cpf = null,
        ?string $birth = null,
    ): int 
    {
    
        $name = trim($name);
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($cpf ?? ""));
        $birth = mb_trim((string) ($birth ?? "")) ?: null;
        if ($name === "") {
            throw new RuntimeException("Nome da pessoa não informado.");
        }
        if ($cpf !== "" && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            throw new RuntimeException("Este CPF não existe.");
        }
        if ($birth !== null && !\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($birth)) {
            throw new RuntimeException("Nascimento inválido.");
        }
        $clinicId = \Prontoo\Runtime\Tenant\SessionTenantAccess::clinicId() ?: null;
        if ($cpf !== "") {
            $id = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.auth05.save_person_flexible.01', [$cpf], []);
            if ($id) {
                $id = (int) $id;
                $identity = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_identity_immutable_values(
                    $id,
                    $cpf,
                    $birth,
                    false,
                );
                $expectedBirth = (string) ($identity["birth_date"] ?? "");
                $current = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.auth05.save_person_flexible.02', [$id], []) ?: [];
                $params = [];
                $updateClinic =
                    $clinicId !== null &&
                    (int) ($current["clinic_id"] ?? 0) <= 0;
                if ($updateClinic) {
                    $params[] = $clinicId;
                }
                $updateName = mb_trim((string) ($current["full_name"] ?? "")) === "";
                if ($updateName) {
                    $params[] = $name;
                }
                $currentBirth = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(
                    (string) ($current["birth_date"] ?? ""),
                );
                $updateBirth =
                    $expectedBirth !== "" &&
                    ($currentBirth === "" || !\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($currentBirth));
                if ($updateBirth) {
                    $params[] = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($expectedBirth);
                }
                if ($updateClinic || $updateName || $updateBirth) {
                    $params[] = $id;
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result(
                        'identity.auth05.save_person_flexible.03',
                        $params,
                        [
                            'update_clinic' => $updateClinic,
                            'update_name' => $updateName,
                            'update_birth' => $updateBirth,
                        ],
                    );
                }
                if ($expectedBirth !== "") {
                    $storedBirth = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(
                        (string) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.auth05.save_person_flexible.04', [$id], []),
                    );
                    $normalizedExpected = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($expectedBirth);
                    if ($storedBirth === "" || $storedBirth !== $normalizedExpected) {
                        throw new RuntimeException(
                            "A data de nascimento não pôde ser registrada corretamente.",
                        );
                    }
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_refresh_verified($id);
                return $id;
            }
        }
    
        $signature = $cpf !== ""
            ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_value($cpf, $name, $birth)
            : null;
        \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.auth05.save_person_flexible.05', [$name, $cpf !== "" ? $cpf : null, $birth, $signature, $clinicId], []);
        $id = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->lastInsertId();
        if ($cpf !== "") {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_refresh_verified($id);
        }
        return $id;
    
    }

    public static function phone_br(?string $phone): string
    
    {
    
        $d = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($phone ?? ""));
        if ($d === "") {
            return "";
        }
        $d = substr($d, 0, 11);
        if (strlen($d) === 11) {
            return "(" .
                substr($d, 0, 2) .
                ") " .
                substr($d, 2, 5) .
                "-" .
                substr($d, 7, 4);
        }
        if (strlen($d) === 10) {
            return "(" .
                substr($d, 0, 2) .
                ") " .
                substr($d, 2, 4) .
                "-" .
                substr($d, 6, 4);
        }
        return $d;
    
    }

    public static function page_person_lookup(): void
    
    {
    
        $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_GET["cpf"] ?? ""));
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        $limited =
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("person_lookup"), 8, 300) ||
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_ip_bucket("person_lookup"), 30, 300);
        if ($cpf !== "" && \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            $limited =
                $limited ||
                \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
                    \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_value_bucket("person_lookup_cpf", $cpf),
                    4,
                    300,
                );
        }
        if ($limited) {
            http_response_code(429);
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => "Aguarde alguns instantes.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => "Informe um CPF válido.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $current = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
        $detailed =
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::has_session_user() &&
            (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("patients") ||
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("users") ||
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("financial") ||
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("admin_people"));
        $p = null;
        if ($detailed && ($current["scope"] ?? "") === "global" && \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::can("admin_people")) {
            $p = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.auth05.page_person_lookup.01', [$cpf], []);
        } elseif (
            $detailed &&
            ($current["scope"] ?? "") === "clinic" &&
            (int) ($current["clinic_id"] ?? 0) > 0
        ) {
            $cid = (int) $current["clinic_id"];
            $p = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.auth05.page_person_lookup.02', [$cpf, $cid, $cid, $cid], []);
        }
        if (!$p || !$detailed) {
            echo json_encode(
                [
                    "ok" => true,
                    "found" => false,
                    "message" =>
                        "CPF recebido. Continue o cadastro ou informe a senha se já possuir acesso.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        echo json_encode(
            [
                "ok" => true,
                "found" => true,
                "message" => "Dados encontrados e preenchidos automaticamente.",
                "name" => (string) ($p["full_name"] ?? ""),
                "cpf" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cpf_br((string) ($p["cpf"] ?? "")),
                "birth_date" => \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::db_birth_date_input($p["birth_date"] ?? ""),
            ],
            JSON_UNESCAPED_UNICODE,
        );
    
    }

    public static function page_logout(): void
    
    {
    
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("login");
        }
        $uid = (int) ($_SESSION["uid"] ?? 0);
        $clinicId = (int) ($_SESSION["clinic_id"] ?? 0);
        $roleCode = (string) ($_SESSION["role_code"] ?? "");
        try {
            if ($uid > 0) {
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_rotate($uid);
            }
            $auditContext = [
                "clinic_id" => $clinicId > 0 ? $clinicId : null,
                "role_code" => $roleCode,
                "audit_body" =>
                    "Logout concluído pela rotação da geração canônica; sessões, contextos e credenciais derivadas serão recusados na próxima tentativa de uso.",
            ];
            $queued =
                is_callable([\Prontoo\Runtime\DeferredAudit\DeferredAuditRuntimeOperations01::class, 'maestro_defer_audit_event']) &&
                \Prontoo\Runtime\DeferredAudit\DeferredAuditRuntimeOperations01::maestro_defer_audit_event(
                    "saida_realizada",
                    "seguranca",
                    $uid ?: null,
                    $auditContext,
                    $uid ?: null,
                );
            if (!$queued) {
                error_log(
                    "[Prontoo logout deferred] Auditoria secundária não pôde ser enfileirada para o usuário " .
                        max(0, $uid) .
                        ".",
                );
            }
        } catch (Throwable $e) {
            error_log("[Prontoo logout cascade] " . $e->getMessage());
        } finally {
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::secure_session_destroy();
        }
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Location: " . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("login"));
        exit();
    
    }
}
