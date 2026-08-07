<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\AdminPages;

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
    
        require_can("admin_diagnostics");
        $dbOk = false;
        $dbMsg = "indisponível";
        try {
            $dbOk = (string) val("SELECT 1") === "1";
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
                "title" => bool_status(
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
                "title" => bool_status(
                    is_writable(app_root() . "/ssd"),
                    "Diretório gravável",
                    "Sem escrita em /ssd",
                ),
                "body" => app_root() . "/ssd",
                "meta" =>
                    "Espaço livre: " .
                    (function_exists("disk_free_space")
                        ? human_bytes(
                            (float) @disk_free_space(app_root() . "/ssd"),
                        )
                        : "não informado"),
            ],
            [
                "icon" => "lock",
                "time" => "Instalação",
                "title" => is_file(app_root() . "/storage/install.lock")
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
                "title" => app_debug() ? "Debug ativo" : "Debug inativo",
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
        page(
            "Diagnóstico",
            page_head(
                "Diagnóstico",
                "Verificações rápidas do ambiente sem varrer metadados do MySQL.",
            ) . card(timeline($checks)),
        );
    
    }

    public static function page_admin_errors(): void
    
    {
    
        require_can("admin_errors");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "");
            if ($act !== "resolve_incident") {
                throw new ProntooHttpError(400, "Ação de incidente inválida.");
            }
            $id = (int) ($_POST["id"] ?? 0);
            if ($id <= 0) {
                flash("Incidente não informado.", "bad");
                redirect("admin_errors");
            }
            $note = mb_trim((string) ($_POST["notes"] ?? ""));
            $updated = q(
                "UPDATE pi_error_events SET resolved_at=NOW(), notes=? WHERE id=? AND resolved_at IS NULL",
                [$note, $id],
            )->rowCount();
            if ($updated < 1) {
                flash("Incidente não encontrado ou já resolvido.", "bad");
                redirect("admin_errors");
            }
            audit("erro_marcado_resolvido", "erro", $id);
            flash("Erro marcado como resolvido.");
            redirect("admin_errors");
        }
        $open = (int) val(
            "SELECT COUNT(*) FROM pi_error_events WHERE resolved_at IS NULL",
        );
        $rows = q(
            "SELECT id,route,method,http_status,message,file,line,user_id,clinic_id,notes,resolved_at,created_at FROM pi_error_events WHERE resolved_at IS NULL ORDER BY id DESC LIMIT 80",
        )->fetchAll();
        if (count($rows) < 120) {
            $rows = array_merge(
                $rows,
                q(
                    "SELECT id,route,method,http_status,message,file,line,user_id,clinic_id,notes,resolved_at,created_at FROM pi_error_events WHERE resolved_at IS NOT NULL ORDER BY id DESC LIMIT " .
                        (120 - count($rows)),
                )->fetchAll(),
            );
        }
        $items = [];
        foreach ($rows as $r) {
            $btn = $r["resolved_at"]
                ? ""
                : '<details class="inline"><summary class="ghost small cmdlike">' .
                    action_summary_label("Resolver", "task_alt") .
                    '</summary><form method="post" class="compact">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="resolve_incident">' .
                    '<input type="hidden" name="id" value="' .
                    (int) $r["id"] .
                    '">' .
                    form_row("Nota", textarea("notes")) .
                    '<div class="form-actions"><button type="button" class="ghost small" data-close-panel>' .
                    icon("close") .
                    '<span>Cancelar</span></button><button type="submit" class="small">' .
                    icon("task_alt") .
                    "<span>Marcar resolvido</span></button></div></form></details>";
            $items[] = [
                "icon" => $r["resolved_at"] ? "bug_report" : "report",
                "time" => dt_br($r["created_at"]),
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
                        ? " · resolvido em " . dt_br($r["resolved_at"])
                        : ""),
                "html" => $btn,
            ];
        }
        page(
            "Central de Instabilidades",
            page_head(
                "Central de Instabilidades",
                $open .
                    " erro(s) aberto(s) registrados pelo Prontoo. Clique em cada item para identificar rota, arquivo, linha e mensagem.",
            ) . card(timeline($items, "Nenhum erro registrado.")),
        );
    
    }

    public static function page_admin_onboarding(): void
    
    {
    
        redirect("admin_clinics");
    
    }

    public static function page_admin_integrity(): void
    
    {
    
        require_can("admin_integrity");
        $modelAuditWhere = admin_model_clinic_exclude_where("a.clinic_id");
        $modelScoped = admin_model_clinic_exclude_sql("clinic_id");
        $modelClinic = admin_model_clinic_exclude_sql("id");
        $recent = audit_rows_light($modelAuditWhere, [], 120);
        $bad = 0;
        foreach ($recent as $r) {
            if (!verify_audit_row($r)) {
                $bad++;
            }
        }
        $chainStatus = audit_chain_integrity_status(240);
        if (empty($chainStatus["ok"])) {
            $bad++;
        }
        $scopeViolations = (int) cached_val(
            "integrity_scope_actionable_7d_v2_model_" . admin_model_clinic_id(),
            120,
            "SELECT COUNT(*) FROM pi_scope_violations WHERE created_at>=DATE_SUB(NOW(), INTERVAL 7 DAY) AND violation_key<>'write_in_read_only' $modelScoped",
        );
        $crossClinic = (int) cached_val(
            "integrity_cross_clinic_links_model_" . admin_model_clinic_id(),
            300,
            "SELECT (SELECT COUNT(*) FROM pi_appointments a JOIN pi_patients p ON p.id=a.patient_link_id WHERE a.patient_link_id IS NOT NULL AND a.clinic_id<>p.clinic_id " .
                admin_model_clinic_exclude_sql("a.clinic_id") .
                ") + (SELECT COUNT(*) FROM pi_documents d JOIN pi_patients p ON p.id=d.patient_link_id WHERE d.patient_link_id IS NOT NULL AND d.clinic_id<>p.clinic_id " .
                admin_model_clinic_exclude_sql("d.clinic_id") .
                ") + (SELECT COUNT(*) FROM pi_care c JOIN pi_patients p ON p.id=c.patient_link_id WHERE c.clinic_id<>p.clinic_id " .
                admin_model_clinic_exclude_sql("c.clinic_id") .
                ") + (SELECT COUNT(*) FROM pi_task_details td JOIN pi_tasks t ON t.id=td.task_id WHERE td.clinic_id<>t.clinic_id " .
                admin_model_clinic_exclude_sql("td.clinic_id") .
                ") + (SELECT COUNT(*) FROM pi_task_comments tc JOIN pi_tasks t ON t.id=tc.task_id WHERE tc.clinic_id<>t.clinic_id " .
                admin_model_clinic_exclude_sql("tc.clinic_id") .
                ")",
        );
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
                    admin_model_clinic_count_note() .
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
                    (int) cached_val(
                        "people_unlinked_blocked",
                        PRONTOO_ADMIN_CACHE_TTL,
                        "SELECT COUNT(*) FROM pi_users u WHERE u.is_global_admin=0 AND u.active=0 AND NOT EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.active=1 LIMIT 1)",
                    ) . " colaboradores bloqueados sem vínculo ativo",
                "body" =>
                    "O acesso é bloqueado quando não há vínculo ativo com consultório.",
                "meta" => "Verificar cadastro e vínculos",
            ],
            [
                "icon" => "home_health",
                "time" => "Consultórios",
                "title" =>
                    (int) cached_val(
                        "integrity_clinics_no_manager_model_" .
                            admin_model_clinic_id(),
                        300,
                        "SELECT COUNT(*) FROM pi_clinics WHERE active=1 AND (manager_user_id IS NULL OR manager_user_id=0) $modelClinic",
                    ) . " consultório(s) ativos sem gerente definido",
                "body" => "Afeta suporte e governança local.",
                "meta" => "Revisar responsável",
            ],
            [
                "icon" => "clinical_notes",
                "time" => "Agenda",
                "title" =>
                    (int) cached_val(
                        "integrity_appt_no_patient_model_" .
                            admin_model_clinic_id(),
                        300,
                        "SELECT COUNT(*) FROM pi_appointments WHERE patient_link_id IS NULL AND start_at>=DATE_SUB(NOW(), INTERVAL 30 DAY) $modelScoped",
                    ) . " agendamento(s) recente(s) sem paciente vinculado",
                "body" => "Indica inconsistência de vínculo.",
                "meta" => "Conferência operacional",
            ],
            [
                "icon" => "task_alt",
                "time" => "Tarefas",
                "title" =>
                    (int) cached_val(
                        "integrity_tasks_late_model_" . admin_model_clinic_id(),
                        300,
                        "SELECT COUNT(*) FROM pi_tasks WHERE status='aberta' AND due_at IS NOT NULL AND due_at<NOW() $modelScoped",
                    ) . " tarefa(s) atrasada(s)",
                "body" => "Não é erro técnico, mas sinal de operação parada.",
                "meta" => "Qualidade de uso",
            ],
        ];
        page(
            "Integridade",
            page_head(
                "Integridade",
                "Sinais de consistência dos dados e da operação.",
            ) . card(timeline($issues)),
        );
    
    }
}
