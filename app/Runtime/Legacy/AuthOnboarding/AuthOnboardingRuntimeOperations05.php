<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\AuthOnboarding;

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
        $cpf = only_digits($cpf);
        if ($name === "") {
            throw new RuntimeException("Nome da pessoa não informado.");
        }
        if (!valid_cpf($cpf)) {
            throw new RuntimeException("Este CPF não existe.");
        }
        if (!valid_birth_date($birth)) {
            throw new RuntimeException("Nascimento inválido.");
        }
    
        $id = val("SELECT id FROM pi_persons WHERE cpf=?", [$cpf]);
        if ($id) {
            $id = (int) $id;
            $identity = person_identity_immutable_values($id, $cpf, $birth, true);
            $expectedBirth = app_date_input_from_storage(
                (string) $identity["birth_date"],
            );
            $current = one(
                "SELECT full_name,birth_date FROM pi_persons WHERE id=?",
                [$id],
            ) ?: [];
            $sets = [];
            $params = [];
            if (mb_trim((string) ($current["full_name"] ?? "")) === "") {
                $sets[] = "full_name=?";
                $params[] = $name;
            }
            $currentBirth = app_date_input_from_storage(
                (string) ($current["birth_date"] ?? ""),
            );
            if ($currentBirth === "" || !valid_birth_date($currentBirth)) {
                $sets[] = "birth_date=?";
                $params[] = $expectedBirth;
            }
            if ($sets) {
                $sets[] = "updated_at=NOW()";
                $params[] = $id;
                q(
                    "UPDATE pi_persons SET " . implode(",", $sets) . " WHERE id=?",
                    $params,
                );
            }
            $storedBirth = app_date_input_from_storage(
                (string) val("SELECT birth_date FROM pi_persons WHERE id=?", [$id]),
            );
            if ($storedBirth === "" || $storedBirth !== $expectedBirth) {
                throw new RuntimeException(
                    "A data de nascimento não pôde ser registrada corretamente.",
                );
            }
            person_signature_refresh_verified($id);
            return $id;
        }
    
        q(
            "INSERT INTO pi_persons (full_name,cpf,birth_date,assinatura,created_at) VALUES (?,?,?,?,NOW())",
            [$name, $cpf, $birth, person_signature_value($cpf, $name, $birth)],
        );
        $id = db_last_insert_id();
        person_signature_refresh_verified($id);
        return $id;
    
    }

    public static function lock_person_user_identity(int $personId): void
    
    {
    
        if ($personId <= 0 || !pdo()->inTransaction()) {
            throw new RuntimeException(
                "Não foi possível iniciar a gravação segura do usuário.",
            );
        }
        $locked = one(
            "SELECT id FROM pi_persons WHERE id=? FOR UPDATE",
            [$personId],
        );
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
        $cpf = only_digits((string) ($cpf ?? ""));
        $birth = mb_trim((string) ($birth ?? "")) ?: null;
        if ($name === "") {
            throw new RuntimeException("Nome da pessoa não informado.");
        }
        if ($cpf !== "" && !valid_cpf($cpf)) {
            throw new RuntimeException("Este CPF não existe.");
        }
        if ($birth !== null && !valid_birth_date($birth)) {
            throw new RuntimeException("Nascimento inválido.");
        }
        $clinicId = session_clinic_scope_id() ?: null;
        if ($cpf !== "") {
            $id = val("SELECT id FROM pi_persons WHERE cpf=? LIMIT 1", [$cpf]);
            if ($id) {
                $id = (int) $id;
                $identity = person_identity_immutable_values(
                    $id,
                    $cpf,
                    $birth,
                    false,
                );
                $expectedBirth = (string) ($identity["birth_date"] ?? "");
                $current = one(
                    "SELECT full_name,birth_date,clinic_id FROM pi_persons WHERE id=?",
                    [$id],
                ) ?: [];
                $sets = [];
                $params = [];
                if (
                    $clinicId !== null &&
                    (int) ($current["clinic_id"] ?? 0) <= 0
                ) {
                    $sets[] = "clinic_id=?";
                    $params[] = $clinicId;
                }
                if (mb_trim((string) ($current["full_name"] ?? "")) === "") {
                    $sets[] = "full_name=?";
                    $params[] = $name;
                }
                $currentBirth = app_date_input_from_storage(
                    (string) ($current["birth_date"] ?? ""),
                );
                if (
                    $expectedBirth !== "" &&
                    ($currentBirth === "" || !valid_birth_date($currentBirth))
                ) {
                    $sets[] = "birth_date=?";
                    $params[] = app_date_input_from_storage($expectedBirth);
                }
                if ($sets) {
                    $sets[] = "updated_at=NOW()";
                    $params[] = $id;
                    q(
                        "UPDATE pi_persons SET " . implode(",", $sets) . " WHERE id=?",
                        $params,
                    );
                }
                if ($expectedBirth !== "") {
                    $storedBirth = app_date_input_from_storage(
                        (string) val(
                            "SELECT birth_date FROM pi_persons WHERE id=?",
                            [$id],
                        ),
                    );
                    $normalizedExpected = app_date_input_from_storage($expectedBirth);
                    if ($storedBirth === "" || $storedBirth !== $normalizedExpected) {
                        throw new RuntimeException(
                            "A data de nascimento não pôde ser registrada corretamente.",
                        );
                    }
                }
                person_signature_refresh_verified($id);
                return $id;
            }
        }
    
        $signature = $cpf !== ""
            ? person_signature_value($cpf, $name, $birth)
            : null;
        q(
            "INSERT INTO pi_persons (full_name,cpf,birth_date,assinatura,clinic_id,created_at) VALUES (?,?,?,?,?,NOW())",
            [$name, $cpf !== "" ? $cpf : null, $birth, $signature, $clinicId],
        );
        $id = db_last_insert_id();
        if ($cpf !== "") {
            person_signature_refresh_verified($id);
        }
        return $id;
    
    }

    public static function phone_br(?string $phone): string
    
    {
    
        $d = only_digits((string) ($phone ?? ""));
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
    
        $cpf = only_digits((string) ($_GET["cpf"] ?? ""));
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        $limited =
            security_rate_limit(security_client_bucket("person_lookup"), 8, 300) ||
            security_rate_limit(security_ip_bucket("person_lookup"), 30, 300);
        if ($cpf !== "" && valid_cpf($cpf)) {
            $limited =
                $limited ||
                security_rate_limit(
                    security_value_bucket("person_lookup_cpf", $cpf),
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
        if (!valid_cpf($cpf)) {
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
        $current = ctx();
        $detailed =
            has_session_user() &&
            (can("patients") ||
                can("users") ||
                can("financial") ||
                can("admin_people"));
        $p = null;
        if ($detailed && ($current["scope"] ?? "") === "global" && can("admin_people")) {
            $p = one(
                "SELECT full_name,cpf,birth_date FROM pi_persons WHERE cpf=? LIMIT 1",
                [$cpf],
            );
        } elseif (
            $detailed &&
            ($current["scope"] ?? "") === "clinic" &&
            (int) ($current["clinic_id"] ?? 0) > 0
        ) {
            $cid = (int) $current["clinic_id"];
            $p = one(
                "SELECT p.full_name,p.cpf,p.birth_date
                 FROM pi_persons p
                 WHERE p.cpf=?
                   AND (
                     EXISTS (SELECT 1 FROM pi_patients pat WHERE pat.person_id=p.id AND pat.clinic_id=?)
                     OR EXISTS (SELECT 1 FROM pi_leads l WHERE l.person_id=p.id AND l.clinic_id=?)
                     OR EXISTS (
                       SELECT 1
                       FROM pi_users u
                       JOIN pi_user_roles ur ON ur.user_id=u.id
                       WHERE u.person_id=p.id AND ur.clinic_id=?
                     )
                   )
                 LIMIT 1",
                [$cpf, $cid, $cid, $cid],
            );
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
                "cpf" => cpf_br((string) ($p["cpf"] ?? "")),
                "birth_date" => db_birth_date_input($p["birth_date"] ?? ""),
            ],
            JSON_UNESCAPED_UNICODE,
        );
    
    }

    public static function page_logout(): void
    
    {
    
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
            redirect("login");
        }
        $uid = (int) ($_SESSION["uid"] ?? 0);
        $clinicId = (int) ($_SESSION["clinic_id"] ?? 0);
        $roleCode = (string) ($_SESSION["role_code"] ?? "");
        try {
            if ($uid > 0) {
                user_auth_generation_rotate($uid);
            }
            $auditContext = [
                "clinic_id" => $clinicId > 0 ? $clinicId : null,
                "role_code" => $roleCode,
                "audit_body" =>
                    "Logout concluído pela rotação da geração canônica; sessões, contextos e credenciais derivadas serão recusados na próxima tentativa de uso.",
            ];
            $queued =
                function_exists("maestro_defer_audit_event") &&
                maestro_defer_audit_event(
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
            secure_session_destroy();
        }
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Location: " . href("login"));
        exit();
    
    }
}
