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
use Prontoo\Runtime\Patients\PatientViewComposition;

final class AuthOnboardingRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function onboarding_tip_dismissed(array $c, string $route): bool
    
    {
    
        $uid = (int) ($c["user"]["id"] ?? 0);
        if ($uid <= 0) {
            return true;
        }
        $key = \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::onboarding_tip_key($c, $route);
        try {
            self::auditRemediationDataService()->ensureOnboardingTipsSchema();
            return (int) self::auditRemediationDataService()->scalar('identity.auth01.onboarding_tip_dismissed.01', [$uid, $key], []) > 0;
        } catch (Throwable $e) {
            error_log("[Prontoo onboarding tip read] " . $e->getMessage());
            return false;
        }
    
    }

    public static function onboarding_tip_dismiss(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        $uid = (int) ($c["user"]["id"] ?? 0);
        $key = mb_substr(mb_trim((string) ($_POST["tip_key"] ?? "")), 0, 120);
        if ($uid > 0 && $key !== "") {
            try {
                self::auditRemediationDataService()->ensureOnboardingTipsSchema();
                self::auditRemediationDataService()->result('identity.auth01.onboarding_tip_dismiss.01', [$uid, $key], []);
            } catch (Throwable $e) {
                error_log("[Prontoo onboarding tip dismiss] " . $e->getMessage());
            }
        }
        $to = (string) ($_POST["return_to"] ?? "");
        $base = \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::base_path() ?: "";
        if ($to !== "" && str_starts_with($to, $base . "/?")) {
            header("Location: " . $to);
            exit();
        }
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect(\Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route());
    
    }

    public static function onboarding_tip_copy(array $c, string $route): ?array
    
    {
    
        $role = (string) ($c["role"] ?? "");
        $roleName = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($role, (int) ($c["clinic_id"] ?? 0));
        $tips = [
            "painel" => [
                "icon" => "space_dashboard",
                "title" => "Seu resumo do dia:",
                "body" =>
                    "O consultório já está pronto para uso. Para concluir os ajustes iniciais, comece em Meu Consultório: revise aparência e departamentos; depois cadastre a equipe e confira as permissões de cada cargo.",
            ],
            "operations" => [
                "icon" => "account_tree",
                "title" => "Um passeio pelo consultório!",
                "body" =>
                    "Acompanhe o trabalho de outros departamentos, de forma centralizada.",
            ],
            "leads" => [
                "icon" => "person_search",
                "title" => "Como usar o recurso Interessados?",
                "body" =>
                    "Interessados são todas as pessoas que entraram em contato e demonstraram interesse, mas ainda não são Pacientes. Eles são identificados pelo número de telefone e permitem que, caso outro membro da equipe já o tenha atendido, você tenha o histórico do que foi conversado.",
            ],
            "patients" => [
                "icon" => "patient_list",
                "title" => "Seus ativos mais Preciosos!",
                "body" =>
                    "Pacientes são a base da Agenda e dos Documentos. Um Paciente estar cadastrado é o primeiro passo para permitir que o nome dele seja usado em outras áreas da plataforma, como Agendamentos e Documentos.",
            ],
            "procedures" => [
                "icon" => "medical_services",
                "title" => "O que você faz e quanto custa?",
                "body" =>
                    "Procedimentos são serviços que você oferece, que podem ser agendados e quanto custam. Apenas Procedimentos cadastrados podem ser Agendados e o tempo médio deles é considerado no momento de reservar o horário na Agenda.",
            ],
            "appointments" => [
                "icon" => "calendar_month",
                "title" => "Seu dia, sem atropelos.",
                "body" =>
                    "Antes de criar um Agendamento, cadastre o Paciente e confira se o Procedimento e o Profissional estão disponíveis. Isso evita horários soltos ou incompletos.",
            ],
            "documents" => [
                "icon" => "description",
                "title" => "Documentos gerados com eficiência.",
                "body" =>
                    "Comece criando Modelos de Documentos. Depois, é só selecionar Paciente e clicar em Emitir Documento!",
            ],
            "tasks" => [
                "icon" => "task_alt",
                "title" => "Tarefas distribuem a rotina",
                "body" =>
                    "As Tarefas transformam pendências em responsabilidade clara. Quem vai fazer? Até que dia? Está atrasado? Aqui você controla tudo isso.",
            ],
            "maestro" => [
                "icon" => "event_repeat",
                "title" => "Rotinas automáticas para reduzir repetição.",
                "body" =>
                    "Precisa que a Recepção ligue para o Paciente alguns dias antes e confirme a Consulta? As Rotinas geram a Tarefa para o Colaborador responsável e ajudam a equipe a manter o fluxo em dia.",
            ],
            "audit" => [
                "icon" => "history",
                "title" => "Quem fez o quê?",
                "body" =>
                    "As Atividades são o diário da sua equipe. Saiba que tarefas foram feitas para evitar o retrabalho.",
            ],
            "financial" => [
                "icon" => "payments",
                "title" => "Rotina financeira de forma clara!",
                "body" =>
                    "Quando o Paciente paga na Recepção, o dinheiro vai para uma Gaveta. As Gavetas são conferidas no início e final do expediente. No fechamento, parte continua na Gaveta para troco e o restante é enviado para Cofres. Entenda Cofre como o local onde o dinheiro fica antes de ser depositado. Após ser depositado, ele sai do Cofre e chega a um Banco. Assim, é possível saber quanto dinheiro têm e onde está!",
            ],
            "notices" => [
                "icon" => "campaign",
                "title" => "Avisos para alinhar a equipe",
                "body" =>
                    "Use Avisos para recados internos onde todos podem confirmar que estão cientes. Para pedidos com prazo ou responsável, prefira criar uma Tarefa.",
            ],
            "users" => [
                "icon" => "groups",
                "title" => "Seu time espera clareza!",
                "body" =>
                    "Cadastre aqui os membros da equipe que antes poderiam ser incluídos no assistente inicial. Defina os cargos de cada pessoa e, para profissionais que participam da Agenda, informe também os horários disponíveis.",
            ],
            "permissions" => [
                "icon" => "admin_panel_settings",
                "title" => "Quem pode ver ou alterar o quê?",
                "body" =>
                    "Depois de revisar departamentos e cadastrar a equipe, confira o que cada cargo pode ver, criar ou alterar. Mantenha somente as permissões necessárias para o trabalho de cada ambiente.",
            ],
            "settings" => [
                "icon" => "home_health",
                "title" => "Seu consultório, do seu jeito!",
                "body" =>
                    "A identificação e o fuso já foram definidos na criação. Em Identificação, escolha o ícone e a cor do consultório; em Departamentos, revise nomes e ative somente os ambientes usados pela equipe.",
            ],
        ];
        $tip = $tips[$route] ?? null;
        if (!$tip) {
            return null;
        }
        $tip["role"] = $roleName;
        return $tip;
    
    }

    public static function onboarding_tip_html(array $c, string $route): string
    
    {
    
        if (!$c || ($c["scope"] ?? "") !== "clinic") {
            return "";
        }
        if (!in_array($route, \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::onboarding_tip_module_routes(), true)) {
            return "";
        }
        if ($route === "documents" && (int) ($_GET["doc"] ?? 0) > 0) {
            return "";
        }
        if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::onboarding_tip_dismissed($c, $route)) {
            return "";
        }
        $tip = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::onboarding_tip_copy($c, $route);
        if (!$tip) {
            return "";
        }
        $key = \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::onboarding_tip_key($c, $route);
        $params = $_GET;
        unset($params["r"]);
        $return = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route, $params);
        return PatientViewComposition::onboardingTip($tip, $key, $return, \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field());
    
    }

    public static function valid_cpf(string $cpf): bool
    
    {
        return \Prontoo\Domain\Identity\IdentityDocumentValidator::cpf(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cpf));
    
    }

    public static function valid_cnpj(string $cnpj): bool
    
    {
        return \Prontoo\Domain\Identity\IdentityDocumentValidator::cnpj(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($cnpj));
    
    }

    public static function db_birth_date_input(null|string|int $birth): string
    
    {
    
        return is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'app_date_input_from_storage'])
            ? \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($birth)
            : (string) $birth;
    
    }

    public static function login_last_credential_remember(
        int $uid,
        string $scope,
        ?int $clinicRoleId = null,
    ): void 
    {
        
        if ($uid <= 0 || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        $scope = $scope === "global" ? "global" : "clinic";
        if ($scope === "clinic" && ($clinicRoleId === null || $clinicRoleId <= 0)) {
            return;
        }
        try {
            $payload = json_encode(
                [
                    "scope" => $scope,
                    "clinic_role_id" =>
                        $scope === "clinic" ? (int) $clinicRoleId : null,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            if (!is_string($payload)) {
                return;
            }
            self::auditRemediationDataService()->result('identity.auth01.login_last_credential_remember.01', [\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_last_credential_key($uid), $payload], []);
            if (
                is_callable([\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::class, 'server_json_cache_file']) &&
                is_callable([\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::class, 'server_json_cache_safe_key'])
            ) {
                $cacheFile = \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_file(
                    "meta",
                    \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("meta", [
                        \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_last_credential_key($uid),
                    ]),
                );
                if (is_file($cacheFile)) {
                    @unlink($cacheFile);
                }
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo login last credential remember] " . $e->getMessage(),
            );
        }
    
    }

    public static function login_last_credential_from_meta(int $uid): ?array
    
    {
    
        if ($uid <= 0 || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return null;
        }
        try {
            return \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_last_credential_normalize(
                self::auditRemediationDataService()->scalar(
                    'identity.auth01.login_last_credential_from_meta.01',
                    [\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_last_credential_key($uid)],
                    [],
                ) ?? '',
            );
        } catch (Throwable $e) {
            error_log("[Prontoo login last credential meta] " . $e->getMessage());
            return null;
        }
    
    }

    public static function login_resolve_user_credential(
        int $uid,
        bool $isAdmin,
        array $choices,
    ): ?array 
    {
    
        $fromMeta = \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::login_credential_match(
            $uid,
            $isAdmin,
            $choices,
            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_last_credential_from_meta($uid),
        );
        if ($fromMeta) {
            return $fromMeta;
        }
        if (count($choices) > 0) {
            return [
                "scope" => "clinic",
                "clinic_role_id" => (int) $choices[0]["id"],
                "choice" => $choices[0],
            ];
        }
        if ($isAdmin) {
            return [
                "scope" => "global",
                "clinic_role_id" => null,
                "choice" => null,
            ];
        }
        return null;
    
    }

    public static function login_apply_resolved_credential(
        int $uid,
        array $credential,
        ?string $verifiedUserGeneration = null,
        bool $redirectAfterLogin = true,
    ): string 
    {
    
        $scope = (string) ($credential["scope"] ?? "clinic");
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::session_harden_after_login($uid, $verifiedUserGeneration);
        $_SESSION["uid"] = $uid;
        unset($_SESSION["pending_login_uid"]);
        \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::mfa_pending_login_clear();
        if ($scope === "global") {
            $_SESSION["scope"] = "global";
            unset(
                $_SESSION["uc_id"],
                $_SESSION["clinic_id"],
                $_SESSION["role_code"],
                $_SESSION["effective_roles"],
            );
            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::developer_first_login_clear_json_cache($uid, true);
            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::mark_login_success($uid);
            \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_last_credential_remember($uid, "global", null);
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("entrada_realizada", "usuario", $uid, [
                "scope" => "global",
                "audit_body" =>
                    "Entrada realizada com a última credencial do usuário carregada automaticamente.",
            ], [
                "skip_runtime_context" => true,
                "skip_context_enrichment" => true,
            ]);
            if ($redirectAfterLogin) {
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_performance");
            }
            return "admin_performance";
        }
        $choice = $credential["choice"] ?? null;
        if (!$choice || (int) ($choice["id"] ?? 0) <= 0) {
            throw new RuntimeException(
                "Não foi possível carregar a credencial de trabalho.",
            );
        }
        $_SESSION["scope"] = "clinic";
        $_SESSION["uc_id"] = (int) $choice["id"];
        $_SESSION["clinic_id"] = (int) $choice["clinic_id"];
        $_SESSION["role_code"] = (string) $choice["role_code"];
        $_SESSION["effective_roles"] = [(string) $choice["role_code"]];
        \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations04::mark_login_success($uid);
        \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::login_last_credential_remember($uid, "clinic", (int) $choice["id"]);
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("entrada_realizada", "usuario", $uid, [
            "clinic_id" => (int) $choice["clinic_id"],
            "role_code" => (string) $choice["role_code"],
            "audit_body" =>
                "Entrada realizada com a última credencial de trabalho do usuário carregada automaticamente.",
        ], [
            "skip_runtime_context" => true,
            "skip_context_enrichment" => true,
        ]);
        $destination = "appointments";
        if ($redirectAfterLogin) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect($destination);
        }
        return $destination;
    
    }

    public static function developer_first_login_clear_json_cache(
        int $uid,
        bool $knownDeveloper = false,
    ): bool
    
    {
    
        if (
            $uid <= 0 ||
            !is_callable([\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::class, 'server_json_cache_clear_all_json_files'])
        ) {
            return false;
        }
        $marker = "developer_first_login_json_cache_cleared_v1";
        $lockName = "prontoo_developer_first_login_json_cache";
        $locked = false;
        try {
            $isDeveloper =
                $knownDeveloper ||
                (int) self::auditRemediationDataService()->scalar('identity.auth01.developer_first_login_clear_json_cache.01', [$uid], []) === 1;
            if (!$isDeveloper) {
                return false;
            }
            $ready =
                (string) (self::auditRemediationDataService()->scalar('identity.auth01.developer_first_login_clear_json_cache.02', [$marker], []) ?? "") === "1";
            if ($ready) {
                return true;
            }
            $locked =
                (int) self::auditRemediationDataService()->scalar('identity.auth01.developer_first_login_clear_json_cache.03', [$lockName], []) === 1;
            if (!$locked) {
                return false;
            }
            $ready =
                (string) (self::auditRemediationDataService()->scalar('identity.auth01.developer_first_login_clear_json_cache.04', [$marker], []) ?? "") === "1";
            if ($ready) {
                return true;
            }
            $deleted = \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_all_json_files();
            self::auditRemediationDataService()->result('identity.auth01.developer_first_login_clear_json_cache.05', [$marker, time()], []);
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("cache_instalacao_limpo", "plataforma", null, [
                "arquivos_json_removidos" => $deleted,
                "primeiro_login_desenvolvedor" => true,
                "audit_body" =>
                    "O cache JSON da instalação foi limpo uma única vez no primeiro acesso do Desenvolvedor.",
            ]);
            return true;
        } catch (Throwable $e) {
            error_log(
                "[Prontoo first developer login cache] " . $e->getMessage(),
            );
            return false;
        } finally {
            if ($locked) {
                try {
                    self::auditRemediationDataService()->scalar('identity.auth01.developer_first_login_clear_json_cache.06', [$lockName], []);
                } catch (Throwable $e) {
                    error_log(
                        "[Prontoo first developer login unlock] " .
                            $e->getMessage(),
                    );
                }
            }
        }
    
    }

    public static function mfa_begin_pending_login(int $uid, array $credential): void
    
    {
        session_regenerate_id(true);
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
        $_SESSION["pending_mfa_login"] = [
            "uid" => $uid,
            "scope" => (string) ($credential["scope"] ?? "clinic"),
            "clinic_role_id" => (int) ($credential["clinic_role_id"] ?? 0),
            "issued_at" => time(),
            "user_auth_generation" => \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_ensure($uid),
        ];
        unset(
            $_SESSION["mfa_enrollment_secret"],
            $_SESSION["mfa_recovery_codes"],
            $_SESSION["mfa_pending_verified"],
        );
    
    }

    public static function mfa_pending_login_user(): ?array
    
    {
        $pending = $_SESSION["pending_mfa_login"] ?? null;
        if (
            !is_array($pending) ||
            (int) ($pending["uid"] ?? 0) <= 0 ||
            time() - (int) ($pending["issued_at"] ?? 0) > 300
        ) {
            \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::mfa_pending_login_clear();
            return null;
        }
        $uid = (int) $pending["uid"];
        $user = self::auditRemediationDataService()->row('identity.auth01.mfa_pending_login_user.01', [$uid], []);
        if (
            !$user ||
            !hash_equals(
                (string) ($user["user_auth_generation"] ?? "0"),
                (string) ($pending["user_auth_generation"] ?? ""),
            )
        ) {
            \Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::mfa_pending_login_clear();
            return null;
        }
        return $user;
    
    }
    private static function auditRemediationDataService()
    {
        return \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService();
    }

}
