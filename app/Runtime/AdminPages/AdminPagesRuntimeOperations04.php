<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AdminPages;

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

final class AdminPagesRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function page_admin_diagnostics(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_diagnostics");
        $dbOk = false;
        $dbMsg = "indisponível";
        try {
            $dbOk = (string) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.04.page_admin_diagnostics.01', [], []) === "1";
            $dbMsg = "conexão operacional";
        } catch (Throwable $e) {
            $dbMsg = $e->getMessage();
        }
        $checks = [
            [
                "icon" => "php",
                "time" => "PHP",
                "title" => PHP_VERSION,
                "body" => "Versão ativa do interpretador",
                "meta" => "Recomendado: PHP 8.4 ou superior",
            ],
            [
                "icon" => "database",
                "time" => "Banco",
                "title" => \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::bool_status(
                    $dbOk,
                    "Banco acessível",
                    "Banco indisponível",
                ),
                "body" => $dbMsg,
                "meta" => "Ativo leve: SELECT 1",
            ],
            [
                "icon" => "folder_managed",
                "time" => "Storage",
                "title" => \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::bool_status(
                    is_writable(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_root() . "/ssd"),
                    "Diretório gravável",
                    "Sem escrita em /ssd",
                ),
                "body" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_root() . "/ssd",
                "meta" =>
                    "Espaço livre: " .
                    (function_exists("disk_free_space")
                        ? \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::human_bytes(
                            (float) @disk_free_space(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_root() . "/ssd"),
                        )
                        : "não informado"),
            ],
            [
                "icon" => "lock",
                "time" => "Instalação",
                "title" => is_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_root() . "/storage/install.lock")
                    ? "Instalação bloqueada"
                    : "Instalação aberta",
                "body" => "Arquivo install.lock",
                "meta" => "Evita reinstalação indevida",
            ],
            [
                "icon" => "login",
                "time" => "Inicial",
                "title" => "Login como tela inicial",
                "body" =>
                    "A LandingPage foi removida do projeto e transferida para comunicação externa.",
                "meta" => "index.php abre diretamente o sistema.",
            ],
            [
                "icon" => "security",
                "time" => "Debug",
                "title" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_debug() ? "Debug ativo" : "Debug inativo",
                "body" => "Configuração atual do app",
                "meta" => "Em produção, manter inativo",
            ],
            [
                "icon" => "schema",
                "time" => "Schema",
                "title" => "Schema imutável em runtime",
                "body" =>
                    "A instalação limpa aplica um único contrato SQL; requisições comuns não criam nem alteram tabelas.",
                "meta" => "Revisão: " . PRONTOO_SCHEMA_REV,
            ],
            [
                "icon" => "rule",
                "time" => "Somente leitura",
                "title" => "Allowlist declarativa",
                "body" =>
                    "Consultórios vencidos só podem gravar registros técnicos de login e regularização de assinatura.",
                "meta" => "Demais escritas clínicas seguem bloqueadas",
            ],
        ];
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Diagnóstico",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Diagnóstico",
                "Verificações rápidas do ambiente sem varrer metadados do MySQL.",
            ) . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($checks)),
        );
    
    }

    public static function page_admin_errors(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_errors");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "");
            if ($act !== "resolve_incident") {
                throw new ProntooHttpError(400, "Ação de incidente inválida.");
            }
            $id = (int) ($_POST["id"] ?? 0);
            if ($id <= 0) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Incidente não informado.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_errors");
            }
            $note = mb_trim((string) ($_POST["notes"] ?? ""));
            $updated = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.04.page_admin_errors.01', [$note, $id], [])->rowCount();
            if ($updated < 1) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Incidente não encontrado ou já resolvido.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_errors");
            }
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("erro_marcado_resolvido", "erro", $id);
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Erro marcado como resolvido.");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_errors");
        }
        $open = (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.04.page_admin_errors.02', [], []);
        $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.04.page_admin_errors.03', [], [])->fetchAll();
        if (count($rows) < 120) {
            $rows = array_merge(
                $rows,
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.04.page_admin_errors.04', [], ['rows' => $rows])->fetchAll(),
            );
        }
        $items = [];
        foreach ($rows as $r) {
            $btn = $r["resolved_at"]
                ? ""
                : '<details class="inline"><summary class="ghost small cmdlike">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Resolver", "task_alt") .
                    '</summary><form method="post" class="compact">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="act" value="resolve_incident">' .
                    '<input type="hidden" name="id" value="' .
                    (int) $r["id"] .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Nota", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("notes")) .
                    '<div class="form-actions"><button type="button" class="ghost small" data-close-panel>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                    '<span>Cancelar</span></button><button type="submit" class="small">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("task_alt") .
                    "<span>Marcar resolvido</span></button></div></form></details>";
            $items[] = [
                "icon" => $r["resolved_at"] ? "bug_report" : "report",
                "time" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["created_at"]),
                "title" =>
                    "#" .
                    $r["id"] .
                    " · " .
                    ($r["route"] ?: "rota não informada") .
                    " · HTTP " .
                    ($r["http_status"] ?: "—"),
                "body" => $r["message"],
                "meta" =>
                    ($r["file"] ?: "arquivo não informado") .
                    ":" .
                    ($r["line"] ?: "—") .
                    ($r["resolved_at"]
                        ? " · resolvido em " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["resolved_at"])
                        : ""),
                "html" => $btn,
            ];
        }
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Central de Instabilidades",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Central de Instabilidades",
                $open .
                    " erro(s) aberto(s) registrados pelo Prontoo. Clique em cada item para identificar rota, arquivo, linha e mensagem.",
            ) . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($items, "Nenhum erro registrado.")),
        );
    
    }

    public static function page_admin_onboarding(): void
    
    {
    
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_clinics");
    
    }

    public static function page_admin_integrity(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_integrity");
        $recent = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_rows_light(["scope" => "model_excluded"], [], 120);
        $bad = 0;
        foreach ($recent as $r) {
            if (!\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::verify_audit_row($r)) {
                $bad++;
            }
        }
        $chainStatus = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_chain_integrity_status(240);
        if (empty($chainStatus["ok"])) {
            $bad++;
        }
        $scopeViolations = (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("integrity_scope_actionable_7d_v2_model_" . \Prontoo\Core\Tenant\TenantRegistry::modelClinicId(), 120, 'read.admin_pages.04.page_admin_integrity.01', [], []);
        $crossClinic = (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("integrity_cross_clinic_links_model_" . \Prontoo\Core\Tenant\TenantRegistry::modelClinicId(), 300, 'read.admin_pages.04.page_admin_integrity.02', [], []);
        $issues = [
            [
                "icon" => $scopeViolations ? "shield_lock" : "verified_user",
                "time" => "Isolamento",
                "title" => $scopeViolations
                    ? $scopeViolations . " operação(ões) de escopo bloqueada(s) nos últimos 7 dias"
                    : "Nenhuma operação de escopo bloqueada recentemente",
                "body" =>
                    "A contagem exclui bloqueios de assinatura e não significa que dados tenham atravessado consultórios.",
                "meta" =>
                    \Prontoo\Core\Metrics\GlobalMetricScope::countNote() .
                    " · detalhes técnicos em Segurança",
            ],
            [
                "icon" => $crossClinic ? "hub" : "verified_user",
                "time" => "Vínculos",
                "title" => $crossClinic
                    ? $crossClinic . " vínculo(s) cruzando consultórios"
                    : "Sem vínculo cruzado detectado",
                "body" =>
                    "Validação de consultas, documentos, prontuários e tarefas contra o consultório proprietário.",
                "meta" => "Integridade multi-consultório",
            ],
            [
                "icon" => $bad ? "gpp_bad" : "verified_user",
                "time" => "Atividades",
                "title" => $bad
                    ? $bad . " assinatura(s) alterada(s)"
                    : "Assinaturas recentes válidas",
                "body" =>
                    "Amostra dos últimos 200 eventos auditáveis, descontando consultórios isentos do Desenvolvedor.",
                "meta" => "Integridade lógica",
            ],
            [
                "icon" => "person_off",
                "time" => "Colaboradores",
                "title" =>
                    (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("people_unlinked_blocked", PRONTOO_ADMIN_CACHE_TTL, 'read.admin_pages.04.page_admin_integrity.03', [], []) . " colaboradores bloqueados sem vínculo ativo",
                "body" =>
                    "O acesso é bloqueado quando não há vínculo ativo com consultório.",
                "meta" => "Verificar cadastro e vínculos",
            ],
            [
                "icon" => "home_health",
                "time" => "Consultórios",
                "title" =>
                    (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("integrity_clinics_no_manager_model_" .
                            \Prontoo\Core\Tenant\TenantRegistry::modelClinicId(), 300, 'read.admin_pages.04.page_admin_integrity.04', [], ['modelClinic' => $modelClinic]) . " consultório(s) ativos sem gerente definido",
                "body" => "Afeta suporte e governança local.",
                "meta" => "Revisar responsável",
            ],
            [
                "icon" => "clinical_notes",
                "time" => "Agenda",
                "title" =>
                    (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("integrity_appt_no_patient_model_" .
                            \Prontoo\Core\Tenant\TenantRegistry::modelClinicId(), 300, 'read.admin_pages.04.page_admin_integrity.05', [], ['modelScoped' => $modelScoped]) . " agendamento(s) recente(s) sem paciente vinculado",
                "body" => "Indica inconsistência de vínculo.",
                "meta" => "Conferência operacional",
            ],
            [
                "icon" => "task_alt",
                "time" => "Tarefas",
                "title" =>
                    (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("integrity_tasks_late_model_" . \Prontoo\Core\Tenant\TenantRegistry::modelClinicId(), 300, 'read.admin_pages.04.page_admin_integrity.06', [], ['modelScoped' => $modelScoped]) . " tarefa(s) atrasada(s)",
                "body" => "Não é erro técnico, mas sinal de operação parada.",
                "meta" => "Qualidade de uso",
            ],
        ];
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Integridade",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Integridade",
                "Sinais de consistência dos dados e da operação.",
            ) . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($issues)),
        );
    
    }
}
